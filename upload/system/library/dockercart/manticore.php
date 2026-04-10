<?php
/**
 * Manticore Search Client Library for OpenCart
 * 
 * Provides a wrapper for Manticore Search interactions via MySQL protocol
 * Supports RT (Real-Time) indexes with multi-language capabilities
 * 
 * @package    DockerCart
 * @subpackage Library
 * @author     DockerCart Team
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
            $this->connection = new \mysqli($this->host, '', '', '', $this->port);
            
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
            
            if (is_int($value) || is_float($value)) {
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
