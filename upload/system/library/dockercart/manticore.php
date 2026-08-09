<?php
/**
 * Manticore Search Client Library for OpenCart
 *
 * Provides a wrapper for Manticore Search interactions via MySQL protocol
 * Supports RT (Real-Time) indexes with multi-language capabilities
 *
 * @package    DockerCart
 * @subpackage Library
 * @author     DockerCart Official
 * @copyright  2026 DockerCart
 * @license    MIT
 * @version    1.0.0
 * @link       https://github.com/dockercart
 */

namespace Dockercart;

class ManticoreClient {
    private $host;
    private $port;
    private $connection;
    private $connected = false;
    private $last_error = '';

    /**
     * Constructor
     *
     * @param string $host Manticore host (default: 127.0.0.1)
     * @param int    $port Manticore MySQL protocol port (default: 9306)
     */
    public function __construct($host = '127.0.0.1', $port = 9306) {
        $this->host = $host;
        $this->port = $port;
    }

    /**
     * Connect to Manticore Search
     *
     * @return bool
     */
    public function connect() {
        if ($this->connected) {
            return true;
        }

        try {
            // Suppress the E_WARNING emitted by mysqli when the host is
            // unreachable (DNS/connect failure) — the error is read from
            // connect_error below, and callers expect a clean false.
            $this->connection = @new \mysqli($this->host, '', '', '', $this->port);

            if ($this->connection->connect_error) {
                $this->last_error = 'Connection failed: ' . $this->connection->connect_error;
                return false;
            }

            $this->connection->set_charset('utf8mb4');
            $this->connected = true;

            return true;
        } catch (\Exception $e) {
            $this->last_error = 'Exception: ' . $e->getMessage();
            return false;
        }
    }

    /**
     * Disconnect from Manticore
     */
    public function disconnect() {
        if ($this->connected && $this->connection) {
            $this->connection->close();
            $this->connected = false;
        }
    }

    /**
     * Execute raw SQL query
     *
     * @param string $query SQL query
     * @return mixed Result object or false on failure
     */
    public function query($query) {
        if (!$this->connect()) {
            return false;
        }

        // Suppress warnings from mysqli for expected server-side errors (eg. "field already in schema").
        // We still read the error via $this->connection->error when $result` is false.
        $result = @$this->connection->query($query);

        if (!$result) {
            $this->last_error = 'Query error: ' . $this->connection->error;
            return false;
        }

        return $result;
    }

    /**
     * Insert document into RT index
     *
     * @param string $index Index name
     * @param array  $data  Document data (id + fields + attributes)
     * @return bool
     */
    public function insert($index, $data) {
        if (empty($data['id'])) {
            $this->last_error = 'Document ID is required';
            return false;
        }

        $fields = [];
        $values = [];

        foreach ($data as $field => $value) {
            $fields[] = $this->escapeIdentifier($field);

            if (is_int($value) || is_float($value)) {
                $values[] = $value;
            } else {
                $values[] = "'" . $this->escape($value) . "'";
            }
        }

        $query = "INSERT INTO {$this->escapeIdentifier($index)} (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $values) . ")";

        return $this->query($query) !== false;
    }

    /**
     * Replace (insert or update) document in RT index
     *
     * @param string $index Index name
     * @param array  $data  Document data
     * @return bool
     */
    public function replace($index, $data) {
        if (empty($data['id'])) {
            $this->last_error = 'Document ID is required';
            return false;
        }

        $fields = [];
        $values = [];

        foreach ($data as $field => $value) {
            $fields[] = $this->escapeIdentifier($field);

            if (is_array($value)) {
                $values[] = '(' . implode(',', array_map('intval', $value)) . ')';
            } elseif (is_int($value) || is_float($value)) {
                $values[] = $value;
            } else {
                $values[] = "'" . $this->escape($value) . "'";
            }
        }

        $query = "REPLACE INTO {$this->escapeIdentifier($index)} (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $values) . ")";

        return $this->query($query) !== false;
    }

    /**
     * Delete document from RT index
     *
     * @param string $index Index name
     * @param int    $id    Document ID
     * @return bool
     */
    public function delete($index, $id) {
        $query = "DELETE FROM {$this->escapeIdentifier($index)} WHERE id = " . (int)$id;
        return $this->query($query) !== false;
    }

    /**
     * Search in index
     *
     * @param string $index      Index name
     * @param string $query_text Search query
     * @param array  $options    Search options (filters, limit, offset, wildcard, ranker, sort, order)
     * @return array Search results (flat array of rows)
     */
    public function search($index, $query_text, $options = []) {
        // Build MATCH expression.
        // Tokenize the user query and, when wildcard is enabled, perform per-token
        // (token | token*) matching. The previous implementation appended * to
        // the whole query which produced incorrect results for multi-word queries
        // (e.g. "red chair" -> "red chair*" didn't match "red wooden chair").
        $raw = (string)$query_text;

        // Split on whitespace (preserve multi-byte characters)
        $tokens = preg_split('/\s+/u', trim($raw), -1, PREG_SPLIT_NO_EMPTY);

        if (!empty($options['wildcard'])) {
            $parts = [];
            foreach ($tokens as $t) {
                $t_esc = $this->escape($t);
                // For each token use (token | token*) so both exact and prefix matches
                // are found. Join tokens with space so Manticore treats them as AND.
                $parts[] = "{$t_esc} | {$t_esc}*";
            }

            if ($parts) {
                $match_expr = implode(' ', $parts);
            } else {
                // Fallback to empty-escaped string
                $match_expr = $this->escape('');
            }
        } else {
            $match_expr = $this->escape($raw);
        }

        // For article-like queries containing spaces/_/- between letters and digits,
        // add compact variant OR-branch so:
        //   A 123, A-123, A_123, A123
        // all can match each other when compact value exists in index.
        $compact_variant = $this->buildCompactArticleVariant($raw);
        if ($compact_variant !== '') {
            $compact_esc = $this->escape($compact_variant);

            if (!empty($options['wildcard'])) {
                $compact_expr = "{$compact_esc} | {$compact_esc}*";
            } else {
                $compact_expr = $compact_esc;
            }

            $match_expr = "({$match_expr}) | ({$compact_expr})";
        }

        // Build WHERE clause
        $where = ["MATCH('{$match_expr}')"];

        // Add filters
        if (!empty($options['filters'])) {
            foreach ($options['filters'] as $field => $value) {
                if (is_array($value)) {
                    $where[] = $this->escapeIdentifier($field) . ' IN (' . implode(',', array_map('intval', $value)) . ')';
                } else {
                    if (is_int($value) || is_float($value)) {
                        $where[] = $this->escapeIdentifier($field) . ' = ' . $value;
                    } else {
                        $where[] = $this->escapeIdentifier($field) . " = '" . $this->escape($value) . "'";
                    }
                }
            }
        }

        // Build ORDER BY
        $order_by = '';
        if (!empty($options['sort'])) {
            $order_by = ' ORDER BY ' . $this->escapeIdentifier($options['sort']);
            if (!empty($options['order']) && strtoupper($options['order']) === 'DESC') {
                $order_by .= ' DESC';
            } else {
                $order_by .= ' ASC';
            }
        }

        // Build LIMIT
        $limit = '';
        if (isset($options['limit'])) {
            $offset = isset($options['offset']) ? (int)$options['offset'] : 0;
            $limit = ' LIMIT ' . $offset . ', ' . (int)$options['limit'];
        }

        // Build query
        $query = "SELECT * FROM {$this->escapeIdentifier($index)} WHERE " . implode(' AND ', $where) . $order_by . $limit;

        // Add OPTION clause for ranking
        if (!empty($options['ranker'])) {
            $query .= " OPTION ranker=" . $options['ranker'];
        }

        $result = $this->query($query);

        if (!$result) {
            return [];
        }

        $results = [];
        while ($row = $result->fetch_assoc()) {
            $results[] = $row;
        }

        return $results;
    }

    /**
     * Get autocomplete suggestions using prefix-wildcard matching.
     * Delegates to search() with wildcard=true — same engine as full search.
     *
     * @param string $index      Index name
     * @param string $query_text Partial search query
     * @param array  $options    Options (limit, filters, etc.)
     * @return array Suggestions (same format as search())
     */
    public function suggest($index, $query_text, $options = []) {
        // Enable wildcard for prefix matching (no manual escape + append needed)
        $options['wildcard'] = true;
        $options['limit']    = $options['limit'] ?? 10;

        return $this->search($index, $query_text, $options);
    }

    /**
     * Get spell-correction suggestions ("did you mean") for a query via CALL QSUGGEST.
     *
     * Corrects each token separately (single-word QSUGGEST) and recombines the
     * corrected phrase, so unchanged words are preserved. Sentence mode of
     * QSUGGEST is not used: it returns only the corrected words, dropping the
     * rest of the query. Requires the table to have infixing (min_infix_len)
     * and dict=keywords (default for RT tables). With morphology enabled, the
     * table must set index_exact_words=1 so the dictionary keeps original words.
     *
     * @param string $index      Index name
     * @param string $query_text Query text to correct
     * @param array  $options    Options (limit, max_edits, reject)
     * @return array [['suggest' => string, 'distance' => int, 'docs' => int], ...]
     */
    public function suggestCorrected($index, $query_text, $options = []) {
        $query_text = trim((string)$query_text);

        if ($query_text === '') {
            return [];
        }

        if (!$this->connect()) {
            return [];
        }

        $limit     = isset($options['limit'])     ? (int)$options['limit']     : 3;
        $max_edits = isset($options['max_edits']) ? (int)$options['max_edits'] : 2;
        $reject    = isset($options['reject'])    ? (int)$options['reject']    : 1;

        $tokens = preg_split('/\s+/u', $query_text, -1, PREG_SPLIT_NO_EMPTY);

        if (count($tokens) > 8) {
            return []; // too long to correct reliably
        }

        $corrected      = [];
        $total_distance = 0;
        $min_docs       = PHP_INT_MAX;
        $query_lc       = mb_strtolower($query_text, 'UTF-8');

        foreach ($tokens as $token) {
            $best      = null;
            $has_exact = false;

            // Skip too-short tokens — corrections for them are unreliable
            if (mb_strlen($token, 'UTF-8') >= 2) {
                // Note: CALL QSUGGEST expects the index name as a quoted string literal
                $query = "CALL QSUGGEST('" . $this->escape($token) . "', '" . $this->escape($index)
                    . "', " . $limit . " as limit, " . $max_edits . " as max_edits, " . $reject . " as reject)";

                $result = $this->query($query);

                if ($result) {
                    $token_lc = mb_strtolower($token, 'UTF-8');

                    while ($row = $result->fetch_assoc()) {
                        $suggest  = trim((string)$row['suggest']);
                        $distance = isset($row['distance']) ? (int)$row['distance'] : 0;

                        // Distance 0 means the token itself exists in the index
                        // dictionary — it is a real word, not a typo.
                        if ($distance === 0) {
                            $has_exact = true;
                            continue;
                        }

                        if ($suggest === '') {
                            continue;
                        }

                        if (mb_strtolower($suggest, 'UTF-8') === $token_lc) {
                            continue;
                        }

                        // Skip infix fragments of the token ("kankeb" -> "kank"):
                        // with prefix wildcard matching the original query already
                        // covers token prefixes, so these suggestions are noise.
                        if (mb_strpos(mb_strtolower($suggest, 'UTF-8'), $token_lc) !== false
                            && mb_strlen($suggest, 'UTF-8') < mb_strlen($token, 'UTF-8')) {
                            continue;
                        }

                        $docs = isset($row['docs']) ? (int)$row['docs'] : 0;

                        // Prefer the closest suggestion with the most documents behind it
                        if ($best === null
                            || $distance < $best['distance']
                            || ($distance === $best['distance'] && $docs > $best['docs'])) {
                            $best = ['suggest' => $suggest, 'distance' => $distance, 'docs' => $docs];
                        }
                    }
                }
            }

            // A token that exists in the dictionary is left as-is; otherwise
            // replace it with the best spelling correction found.
            if ($has_exact || $best === null) {
                $corrected[] = $token;
            } else {
                $corrected[] = $best['suggest'];
                $total_distance += $best['distance'];
                $min_docs = min($min_docs, $best['docs']);
            }
        }

        $suggestion = trim(implode(' ', $corrected));

        if ($suggestion === '' || mb_strtolower($suggestion, 'UTF-8') === $query_lc) {
            return [];
        }

        return [[
            'suggest'  => $suggestion,
            'distance' => $total_distance,
            'docs'     => $min_docs,
        ]];
    }

    /**
     * Search with real total count via SHOW META.
     *
     * Returns both the page of results AND the engine-reported total_found,
     * which is required for correct pagination on the search results page.
     *
     * @param string $index      Index name
     * @param string $query_text Search query
     * @param array  $options    Same options as search()
     * @return array ['results' => array, 'total' => int]
     */
    public function searchWithMeta($index, $query_text, $options = []) {
        $results = $this->search($index, $query_text, $options);

        // Fetch total_found from SHOW META (must be called right after the search query)
        $total = count($results); // safe fallback

        $meta_result = $this->query('SHOW META');
        if ($meta_result) {
            while ($row = $meta_result->fetch_assoc()) {
                if ($row['Variable_name'] === 'total_found') {
                    $total = (int)$row['Value'];
                    break;
                }
            }
        }

        return ['results' => $results, 'total' => $total];
    }

    /**
     * Truncate RT index (remove all documents)
     *
     * @param string $index Index name
     * @return bool
     */
    public function truncate($index) {
        $query = "TRUNCATE RTINDEX {$this->escapeIdentifier($index)}";
        return $this->query($query) !== false;
    }

    /**
     * Get index status
     *
     * @param string $index Index name
     * @return array Index statistics
     */
    public function getIndexStatus($index) {
        $query = "SHOW INDEX {$this->escapeIdentifier($index)} STATUS";
        $result = $this->query($query);

        if (!$result) {
            return [];
        }

        $status = [];
        while ($row = $result->fetch_assoc()) {
            $status[$row['Variable_name']] = $row['Value'];
        }

        return $status;
    }

    /**
     * Escape string for query
     *
     * @param string $value Value to escape
     * @return string Escaped value
     */
    private function escape($value) {
        // Handle null values
        if ($value === null) {
            return '';
        }

        // Convert to string if not already
        $value = (string)$value;

        if (!$this->connect()) {
            return addslashes($value);
        }

        return $this->connection->real_escape_string($value);
    }

    /**
     * Escape identifier (table/column name)
     *
     * @param string $identifier Identifier to escape
     * @return string Escaped identifier
     */
    private function escapeIdentifier($identifier) {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    /**
     * Build compact article-like query variant by removing spaces, underscores and hyphens.
     *
     * Returns non-empty value only when:
     *  - query contains at least one separator from [space,_,-], and
     *  - query contains both letters and digits (article-like pattern).
     */
    private function buildCompactArticleVariant($query_text) {
        $query_text = trim((string)$query_text);

        if ($query_text === '' || !preg_match('/[\s_-]/u', $query_text)) {
            return '';
        }

        if (!preg_match('/\p{L}/u', $query_text) || !preg_match('/\d/u', $query_text)) {
            return '';
        }

        $compact = preg_replace('/[\s_-]+/u', '', $query_text);

        if ($compact === '' || $compact === $query_text) {
            return '';
        }

        return $compact;
    }

    /**
     * Get last error message
     *
     * @return string Error message
     */
    public function getLastError() {
        return $this->last_error;
    }

    /**
     * Check if connected
     *
     * @return bool
     */
    public function isConnected() {
        return $this->connected;
    }

    /**
     * Ping Manticore server
     *
     * @return bool
     */
    public function ping() {
        if (!$this->connect()) {
            return false;
        }

        $result = $this->query('SHOW TABLES');
        return $result !== false;
    }

    /**
     * Destructor - close connection
     */
    public function __destruct() {
        $this->disconnect();
    }
}
