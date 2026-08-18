<?php

declare(strict_types=1);

class ModelToolRecycleBin extends Model
{
    protected const RETENTION_DAYS = 30;

    /**
     * Per-entity descriptor map. `table`/`pk` are the primary table and its
     * auto-increment column. `description` is the multilingual description
     * table (row keys must unravel from ODBC style). `to_store` holds the
     * multi-store mapping table (nil when the entity has none). `seo_query`
     * is the query prefix used when writing oc_seo_url. `fk_reset` lists
     * columns on the primary table that are reset to 0 / NULL on restore
     * because their target rows may themselves have been deleted (best-effort
     * fidelity). `cache_keys` are cleared on restore/purge.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getEntityDescriptors(): array
    {
        return array(
            'product'    => array(
                'entity_type' => 'product',
                'label'       => 'text_product',
                'table'       => 'product',
                'pk'          => 'product_id',
                'primary_name'=> '',
                'description' => array('table' => 'product_description', 'fk' => 'product_id', 'name' => 'name'),
                'to_store'    => array('table' => 'product_to_store'),
                'seo_query'   => 'product_id=',
                'seo_table'   => 'seo_url',
                'fk_reset'    => array('manufacturer_id', 'main_category_id'),
                'cache_keys'  => array('product'),
            ),
            'category'   => array(
                'entity_type' => 'category',
                'label'       => 'text_category',
                'table'       => 'category',
                'pk'          => 'category_id',
                'primary_name'=> '',
                'description' => array('table' => 'category_description', 'fk' => 'category_id', 'name' => 'name'),
                'to_store'    => array('table' => 'category_to_store'),
                'seo_query'   => 'category_id=',
                'seo_table'   => 'seo_url',
                'fk_reset'    => array('parent_id'),
                'cache_keys'  => array('category', 'product'),
            ),
            'manufacturer' => array(
                'entity_type' => 'manufacturer',
                'label'       => 'text_manufacturer',
                'table'       => 'manufacturer',
                'pk'          => 'manufacturer_id',
                'primary_name'=> 'name',
                'description' => array('table' => 'manufacturer_description', 'fk' => 'manufacturer_id', 'name' => 'name'),
                'to_store'    => array('table' => 'manufacturer_to_store'),
                'seo_query'   => 'manufacturer_id=',
                'seo_table'   => 'seo_url',
                'fk_reset'    => array(),
                'cache_keys'  => array('manufacturer'),
            ),
            'information' => array(
                'entity_type' => 'information',
                'label'       => 'text_information',
                'table'       => 'information',
                'pk'          => 'information_id',
                'primary_name'=> '',
                'description' => array('table' => 'information_description', 'fk' => 'information_id', 'name' => 'title'),
                'to_store'    => array('table' => 'information_to_store'),
                'seo_query'   => 'information_id=',
                'seo_table'   => 'seo_url',
                'fk_reset'    => array(),
                'cache_keys'  => array('information'),
            ),
            'review'     => array(
                'entity_type' => 'review',
                'label'       => 'text_review',
                'table'       => 'review',
                'pk'          => 'review_id',
                'primary_name'=> '',
                'description' => null,
                'to_store'    => null,
                'seo_query'   => '',
                'seo_table'   => 'seo_url',
                'fk_reset'    => array('product_id', 'customer_id', 'parent_id'),
                'cache_keys'  => array('product'),
            ),
            'customer'   => array(
                'entity_type' => 'customer',
                'label'       => 'text_customer',
                'table'       => 'customer',
                'pk'          => 'customer_id',
                'primary_name'=> '',
                'description' => null,
                'to_store'    => null,
                'seo_query'   => '',
                'seo_table'   => 'seo_url',
                'fk_reset'    => array(),
                'cache_keys'  => array(),
            ),
            'order'      => array(
                'entity_type' => 'order',
                'label'       => 'text_order',
                'table'       => 'order',
                'pk'          => 'order_id',
                'primary_name'=> '',
                'description' => null,
                'to_store'    => null,
                'seo_query'   => '',
                'seo_table'   => 'seo_url',
                'fk_reset'    => array('customer_id', 'customer_group_id'),
                'related'     => array(
                    array('table' => 'order_history', 'fk' => 'order_id', 'pk' => 'order_history_id'),
                    array('table' => 'order_total', 'fk' => 'order_id', 'pk' => 'order_total_id'),
                ),
                'cache_keys'  => array('product'),
            ),
            'blog_post'  => array(
                'entity_type' => 'blog_post',
                'label'       => 'text_blog_post',
                'table'       => 'blog_post',
                'pk'          => 'post_id',
                'primary_name'=> '',
                'description' => array('table' => 'blog_post_description', 'fk' => 'post_id', 'name' => 'name'),
                'to_store'    => array('table' => 'blog_post_to_store'),
                'seo_query'   => 'blog_post_id=',
                'seo_table'   => 'blog_seo_url',
                'fk_reset'    => array('author_id'),
                'cache_keys'  => array('blog.post', 'blog.popular', 'blog.recent', 'blog.archive', 'blog.category', 'blog.author'),
            ),
        );
    }

    /**
     * Map a model delete route (as passed to the event) to an entity_type.
     */
    public function entityTypeFromRoute(string $route): string
    {
        $map = array(
            'catalog/product/deleteProduct'                 => 'product',
            'catalog/category/deleteCategory'               => 'category',
            'catalog/manufacturer/deleteManufacturer'       => 'manufacturer',
            'catalog/information/deleteInformation'         => 'information',
            'catalog/review/deleteReview'                   => 'review',
            'customer/customer/deleteCustomer'              => 'customer',
            'sale/order/deleteOrder'                        => 'order',
            'extension/module/dockercart_blog_post/deletePost' => 'blog_post',
        );

        return isset($map[$route]) ? $map[$route] : '';
    }

    /**
     * Capture a delete into oc_trash (called from the event before the core
     * DELETE runs, so the rows are still present).
     */
    public function capture(string $entity_type, int $entity_id, int $deleted_by = 0): void
    {
        $descriptors = $this->getEntityDescriptors();

        if (!isset($descriptors[$entity_type]) || $entity_id <= 0) {
            return;
        }

        $descriptor   = $descriptors[$entity_type];
        $primary      = $this->fetchByPk($descriptor['table'], $descriptor['pk'], $entity_id);
        $description  = array();
        $to_store     = array();
        $seo_url      = array();
        $related      = array();

        if (!$primary) {
            return;
        }

        if ($descriptor['description'] !== null) {
            $description = $this->fetchRelated($descriptor['description']['table'], $descriptor['description']['fk'], (string) $entity_id);
        }

        if ($descriptor['to_store'] !== null) {
            $fk = $descriptor['pk'];
            $to_store = $this->fetchRelated($descriptor['to_store']['table'], $fk, (string) $entity_id);
        }

        if (!empty($descriptor['related'])) {
            foreach ($descriptor['related'] as $rel) {
                $related[$rel['table']] = $this->fetchRelated($rel['table'], $rel['fk'], (string) $entity_id);
            }
        }

        if ($descriptor['seo_query'] !== '') {
            $seo_url = $this->fetchRelated('seo_url', 'query', $descriptor['seo_query'] . (int) $entity_id, true);
        }

        $name = $this->resolveName($entity_type, $descriptor, $primary, $description);

        $data = json_encode(
            array(
                'primary'     => $primary,
                'description' => $description,
                'to_store'    => $to_store,
                'seo_url'     => $seo_url,
                'related'     => $related,
            ),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        // Avoid double-capture when a record is deleted more than once.
        $this->db->query(
            "DELETE FROM `" . DB_PREFIX . "trash`
             WHERE `entity_type` = '" . $this->db->escape($entity_type) . "'
               AND `entity_id` = '" . (int) $entity_id . "'
               AND `restored_at` IS NULL"
        );

        $this->db->query(
            "INSERT INTO `" . DB_PREFIX . "trash` SET
             `entity_type` = '" . $this->db->escape($entity_type) . "',
             `entity_id`   = '" . (int) $entity_id . "',
             `name`        = '" . $this->db->escape($name) . "',
             `data`        = '" . $this->db->escape($data) . "',
             `deleted_by`  = '" . (int) $deleted_by . "',
             `deleted_at`  = NOW()"
        );
    }

    public function getTrash(array $data = array()): array
    {
        $sql = "SELECT t.*, u.username AS deleted_username
                FROM `" . DB_PREFIX . "trash` t
                LEFT JOIN `" . DB_PREFIX . "user` u ON (u.user_id = t.deleted_by)
                WHERE t.restored_at IS NULL
                  AND t.deleted_at >= DATE_SUB(NOW(), INTERVAL " . (int) self::RETENTION_DAYS . " DAY)";

        if (!empty($data['filter_entity_type'])) {
            $sql .= " AND t.entity_type = '" . $this->db->escape($data['filter_entity_type']) . "'";
        }

        if (!empty($data['filter_name'])) {
            if (empty($data['filter_exact'])) {
                $sql .= " AND t.name LIKE '%" . $this->db->escape($data['filter_name']) . "%'";
            } else {
                $sql .= " AND t.name = '" . $this->db->escape($data['filter_name']) . "'";
            }
        }

        $sort_data = array('name', 'entity_type', 'deleted_at', 'deleted_username');

        if (isset($data['sort']) && in_array($data['sort'], $sort_data, true)) {
            $sql .= " ORDER BY t." . $data['sort'];
        } else {
            $sql .= " ORDER BY t.deleted_at";
        }

        if (isset($data['order']) && $data['order'] === 'ASC') {
            $sql .= " ASC";
        } else {
            $sql .= " DESC";
        }

        if (isset($data['start']) || isset($data['limit'])) {
            if ($data['start'] < 0) {
                $data['start'] = 0;
            }

            if ($data['limit'] < 1) {
                $data['limit'] = 20;
            }

            $sql .= " LIMIT " . (int) $data['start'] . "," . (int) $data['limit'];
        }

        $query = $this->db->query($sql);

        return $query->rows;
    }

    public function getTotalTrash(array $data = array()): int
    {
        $sql = "SELECT COUNT(*) AS total
                FROM `" . DB_PREFIX . "trash` t
                WHERE t.restored_at IS NULL
                  AND t.deleted_at >= DATE_SUB(NOW(), INTERVAL " . (int) self::RETENTION_DAYS . " DAY)";

        if (!empty($data['filter_entity_type'])) {
            $sql .= " AND t.entity_type = '" . $this->db->escape($data['filter_entity_type']) . "'";
        }

        if (!empty($data['filter_name'])) {
            if (empty($data['filter_exact'])) {
                $sql .= " AND t.name LIKE '%" . $this->db->escape($data['filter_name']) . "%'";
            } else {
                $sql .= " AND t.name = '" . $this->db->escape($data['filter_name']) . "'";
            }
        }

        $query = $this->db->query($sql);

        return (int) $query->row['total'];
    }

    public function getTrashEntry(int $trash_id): array
    {
        $query = $this->db->query(
            "SELECT * FROM `" . DB_PREFIX . "trash`
             WHERE trash_id = '" . (int) $trash_id . "' LIMIT 1"
        );

        if (!$query->num_rows) {
            return array();
        }

        return $query->row;
    }

    /**
     * Restore an entity from trash (re-insert with new auto-increment ID).
     */
    public function restore(int $trash_id): bool
    {
        $entry = $this->getTrashEntry($trash_id);

        if (empty($entry)) {
            return false;
        }

        $entity_type = $entry['entity_type'];
        $data        = json_decode($entry['data'], true);

        if (!is_array($data) || empty($data['primary'])) {
            return false;
        }

        $descriptors = $this->getEntityDescriptors();

        if (!isset($descriptors[$entity_type])) {
            return false;
        }

        $descriptor = $descriptors[$entity_type];

        $this->db->query("START TRANSACTION");

        try {
            $captured_descriptions = !empty($data['description']) ? $data['description'] : array();
            $new_id                = $this->insertPrimary($descriptor, $data['primary'], $captured_descriptions);

            if ($new_id <= 0) {
                throw new \Exception('Recycle bin: failed to restore primary row.');
            }

            if (!empty($data['description'])) {
                $this->reinsertDescriptions($descriptor, $new_id, $captured_descriptions);
            }

            if (!empty($data['to_store'])) {
                $this->reinsertToStore($descriptor, $new_id, $data['to_store']);
            }

            if (!empty($data['related'])) {
                $this->reinsertRelated($descriptor, $new_id, $data['related']);
            }

            if (!empty($data['seo_url'])) {
                $new_query = $descriptor['seo_query'] . $new_id;
                foreach ($data['seo_url'] as $seo) {
                    $this->insertSeoUrl($descriptor, $new_query, $seo);
                }
            }

            if ($entity_type === 'category') {
                $this->repairCategoryPath($new_id);
            }

            $this->db->query(
                "UPDATE `" . DB_PREFIX . "trash`
                 SET restored_at = NOW()
                 WHERE trash_id = '" . (int) $trash_id . "'"
            );

            $this->db->query("COMMIT");
        } catch (\Exception $e) {
            $this->db->query("ROLLBACK");

            return false;
        }

        foreach ($descriptor['cache_keys'] as $cache_key) {
            $this->cache->delete($cache_key);
        }

        $this->load->model('design/seo_url');

        if ($this->registry->has('model_design_seo_url')) {
            $this->model_design_seo_url->invalidateSeoUrlCache();
        }

        $this->cache->delete('seo_url');

        return true;
    }

    /**
     * Permanently remove a trash entry (the real entity delete already ran at
     * trash time; this only removes the snapshot).
     */
    public function purge(int $trash_id): void
    {
        $this->db->query(
            "DELETE FROM `" . DB_PREFIX . "trash` WHERE trash_id = '" . (int) $trash_id . "'"
        );
    }

    /**
     * Permanently remove every non-restored trash entry (the snapshots only).
     */
    public function clearAll(): int
    {
        $query = $this->db->query(
            "SELECT trash_id FROM `" . DB_PREFIX . "trash`
             WHERE restored_at IS NULL
               AND deleted_at >= DATE_SUB(NOW(), INTERVAL " . (int) self::RETENTION_DAYS . " DAY)"
        );

        $count = 0;

        foreach ($query->rows as $row) {
            $this->purge((int) $row['trash_id']);
            ++$count;
        }

        return $count;
    }

    /**
     * Purge trash older than the retention period. Returns count removed.
     */
    public function purgeExpired(int $days = self::RETENTION_DAYS): int
    {
        if ($days < 1) {
            $days = self::RETENTION_DAYS;
        }

        $query = $this->db->query(
            "SELECT trash_id FROM `" . DB_PREFIX . "trash`
             WHERE deleted_at < DATE_SUB(NOW(), INTERVAL " . (int) $days . " DAY)"
        );

        $count = 0;

        foreach ($query->rows as $row) {
            $this->purge((int) $row['trash_id']);
            ++$count;
        }

        return $count;
    }

    /**************************************************************************
     * Capture helpers
     **************************************************************************/

    private function fetchByPk(string $table, string $pk, int $id): array
    {
        $query = $this->db->query(
            "SELECT * FROM `" . DB_PREFIX . $table . "`
             WHERE `" . $pk . "` = '" . (int) $id . "' LIMIT 1"
        );

        return $query->num_rows ? $query->row : array();
    }

    private function fetchRelated(string $table, string $column, string $value, bool $is_string = false): array
    {
        $escaped = $is_string ? "'" . $this->db->escape($value) . "'" : "'" . (int) $value . "'";

        $query = $this->db->query(
            "SELECT * FROM `" . DB_PREFIX . $table . "`
             WHERE `" . $column . "` = " . $escaped
        );

        return $query->rows;
    }

    private function resolveName(string $entity_type, array $descriptor, array $primary, array $description): string
    {
        if ($entity_type === 'customer') {
            $name = trim(($primary['firstname'] ?? '') . ' ' . ($primary['lastname'] ?? ''));

            return $name !== '' ? $name : ($primary['email'] ?? '');
        }

        if ($entity_type === 'order') {
            return '#' . (int) ($primary['order_id'] ?? 0) . ' — ' . ($primary['store_name'] ?? '');
        }

        if ($entity_type === 'review') {
            return ($primary['author'] ?? '') !== '' ? $primary['author'] : '#' . (int) ($primary['review_id'] ?? 0);
        }

        if ($descriptor['primary_name'] !== '' && isset($primary[$descriptor['primary_name']]) && $primary[$descriptor['primary_name']] !== '') {
            return $primary[$descriptor['primary_name']];
        }

        if (!empty($description) && isset($descriptor['description']['name'])) {
            $name_col = $descriptor['description']['name'];

            foreach ($description as $row) {
                if (!empty($row[$name_col])) {
                    return $row[$name_col];
                }
            }
        }

        return '#' . (int) ($primary[$descriptor['pk']] ?? 0);
    }

    /**************************************************************************
     * Restore helpers
     **************************************************************************/

    private function insertPrimary(array $descriptor, array $row, array $descriptions = array()): int
    {
        $columns = array();
        $values  = array();

        foreach ($row as $column => $value) {
            if ($column === $descriptor['pk']) {
                continue;
            }

            if (in_array($column, $descriptor['fk_reset'], true)) {
                $value = 0;
            }

            $columns[] = '`' . $column . '`';
            $values[]  = $value === null ? 'NULL' : "'" . $this->db->escape((string) $value) . "'";
        }

        if (!$columns) {
            return 0;
        }

        $this->db->query(
            "INSERT INTO `" . DB_PREFIX . $descriptor['table'] . "` (" . implode(', ', $columns) . ")
             VALUES (" . implode(', ', $values) . ")"
        );

        $new_id = (int) $this->db->getLastId();

        if ($descriptor['primary_name'] !== '' && $new_id > 0) {
            $default_name = $this->resolveDescriptionName($descriptor, $descriptions);

            if ($default_name !== '') {
                $this->db->query(
                    "UPDATE `" . DB_PREFIX . $descriptor['table'] . "`
                     SET `" . $descriptor['primary_name'] . "` = '" . $this->db->escape($default_name) . "'
                     WHERE `" . $descriptor['pk'] . "` = '" . (int) $new_id . "'"
                );
            }
        }

        return $new_id;
    }

    private function insertRelatedRow(string $table, array $row): void
    {
        if (!$row) {
            return;
        }

        $columns = array();
        $values  = array();

        foreach ($row as $column => $value) {
            $columns[] = '`' . $column . '`';
            $values[]  = $value === null ? 'NULL' : "'" . $this->db->escape((string) $value) . "'";
        }

        $this->db->query(
            "INSERT INTO `" . DB_PREFIX . $table . "` (" . implode(', ', $columns) . ")
             VALUES (" . implode(', ', $values) . ")"
        );
    }

    private function reinsertDescriptions(array $descriptor, int $new_id, array $rows): void
    {
        $table = $descriptor['description']['table'];
        $fk    = $descriptor['description']['fk'];

        foreach ($rows as $row) {
            unset($row[$fk]);
            $row[$fk] = $new_id;
            $this->insertRelatedRow($table, $row);
        }
    }

    private function reinsertToStore(array $descriptor, int $new_id, array $rows): void
    {
        $table = $descriptor['to_store']['table'];
        $fk    = $descriptor['pk'];

        foreach ($rows as $row) {
            unset($row[$fk]);
            $row[$fk] = $new_id;
            $this->insertRelatedRow($table, $row);
        }
    }

    private function reinsertRelated(array $descriptor, int $new_id, array $grouped): void
    {
        $map = array();

        foreach ($descriptor['related'] as $rel) {
            $map[$rel['table']] = $rel;
        }

        foreach ($grouped as $table => $rows) {
            $rel = $map[$table] ?? null;

            foreach ($rows as $row) {
                if ($rel !== null && !empty($rel['pk'])) {
                    unset($row[$rel['pk']]);
                }

                if (isset($row['order_id'])) {
                    $row['order_id'] = $new_id;
                }

                $this->insertRelatedRow($table, $row);
            }
        }
    }

    private function insertSeoUrl(array $descriptor, string $new_query, array $seo): void
    {
        $table = $descriptor['seo_table'] ?? 'seo_url';

        $store_id = (int) ($seo['store_id'] ?? 0);
        $language_id = (int) ($seo['language_id'] ?? 0);

        if (empty($seo['keyword'])) {
            return;
        }

        $columns = '`store_id`, `language_id`, `query`, `keyword`';
        $values  = "'" . (int) $store_id . "', '" . (int) $language_id . "', '" . $this->db->escape($new_query) . "', '" . $this->db->escape($seo['keyword']) . "'";

        // Keep the old keyword unless it has since been claimed by another record.
        $conflict = $this->db->query(
            "SELECT 1 FROM `" . DB_PREFIX . $table . "`
             WHERE `keyword` = '" . $this->db->escape($seo['keyword']) . "'
               AND `store_id` = '" . (int) $store_id . "'
               AND `language_id` = '" . (int) $language_id . "'
             LIMIT 1"
        );

        if (!$conflict->num_rows) {
            $this->db->query(
                "INSERT INTO `" . DB_PREFIX . $table . "` (" . $columns . ") VALUES (" . $values . ")"
            );
        }
    }

    private function repairCategoryPath(int $category_id): void
    {
        // Best-effort: ensure the category has at least its own path row so it
        // is listed as a root category. The administrator can re-parent it.
        $exists = $this->db->query(
            "SELECT 1 FROM `" . DB_PREFIX . "category_path`
             WHERE category_id = '" . (int) $category_id . "' AND path_id = '" . (int) $category_id . "'
             LIMIT 1"
        );

        if (!$exists->num_rows) {
            $this->db->query(
                "INSERT IGNORE INTO `" . DB_PREFIX . "category_path`
                 SET category_id = '" . (int) $category_id . "', path_id = '" . (int) $category_id . "', level = 0"
            );
        }
    }

    private function resolveDescriptionName(array $descriptor, array $descriptions): string
    {
        if ($descriptor['description'] === null || empty($descriptions)) {
            return '';
        }

        $name_col = $descriptor['description']['name'];

        foreach ($descriptions as $row) {
            if (!empty($row[$name_col])) {
                return $row[$name_col];
            }
        }

        return '';
    }
}