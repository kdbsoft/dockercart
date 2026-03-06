<?php
class ModelExtensionModuleDockercartNewsletter extends Model {
    private $schema_ensured = false;

    public function install() {
        $this->ensureSchema();
    }

    public function uninstall() {
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "dockercart_newsletter_subscriber`");
    }

    public function getSubscribers($data = array()) {
        $this->ensureSchema();

        $subquery = $this->buildCombinedSubscribersSubquery();

        $sql = "SELECT * FROM (" . $subquery . ") ns WHERE 1=1";

        if (!empty($data['filter_email'])) {
            $sql .= " AND ns.email LIKE '%" . $this->db->escape($data['filter_email']) . "%'";
        }

        $sql .= " ORDER BY ns.date_added DESC, ns.email ASC";

        if (isset($data['start']) || isset($data['limit'])) {
            $start = isset($data['start']) ? (int)$data['start'] : 0;
            $limit = isset($data['limit']) ? (int)$data['limit'] : 20;

            if ($start < 0) {
                $start = 0;
            }

            if ($limit < 1) {
                $limit = 20;
            }

            $sql .= " LIMIT " . $start . "," . $limit;
        }

        return $this->db->query($sql)->rows;
    }

    public function getTotalSubscribers($data = array()) {
        $this->ensureSchema();

        $subquery = $this->buildCombinedSubscribersSubquery();

        $sql = "SELECT COUNT(*) AS total FROM (" . $subquery . ") ns WHERE 1=1";

        if (!empty($data['filter_email'])) {
            $sql .= " AND ns.email LIKE '%" . $this->db->escape($data['filter_email']) . "%'";
        }

        $query = $this->db->query($sql);

        return (int)$query->row['total'];
    }

    public function getStats() {
        $this->ensureSchema();

        $guest_total = (int)$this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "dockercart_newsletter_subscriber` WHERE status = '1'")->row['total'];
        $customer_total = (int)$this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "customer` WHERE newsletter = '1'")->row['total'];

        return array(
            'guest_total' => $guest_total,
            'customer_total' => $customer_total,
            'total' => $guest_total + $customer_total
        );
    }

    public function getSubscribersForExport() {
        $this->ensureSchema();

        $subquery = $this->buildCombinedSubscribersSubquery();
        $sql = "SELECT * FROM (" . $subquery . ") ns ORDER BY ns.date_added DESC, ns.email ASC";

        return $this->db->query($sql)->rows;
    }

    public function importEmail($email) {
        $this->ensureSchema();

        $normalized_email = $this->normalizeEmail($email);

        if ($normalized_email === '') {
            return 'invalid';
        }

        $customer = $this->db->query("SELECT customer_id, newsletter FROM `" . DB_PREFIX . "customer` WHERE LCASE(email) COLLATE utf8mb4_unicode_ci = '" . $this->db->escape($normalized_email) . "' COLLATE utf8mb4_unicode_ci LIMIT 1");

        if ($customer->num_rows) {
            if ((int)$customer->row['newsletter'] === 1) {
                return 'already';
            }

            $this->db->query("UPDATE `" . DB_PREFIX . "customer` SET newsletter = '1' WHERE customer_id = '" . (int)$customer->row['customer_id'] . "'");

            return 'subscribed_customer';
        }

        $existing = $this->db->query("SELECT subscriber_id, status FROM `" . DB_PREFIX . "dockercart_newsletter_subscriber` WHERE email = '" . $this->db->escape($normalized_email) . "' LIMIT 1");

        if ($existing->num_rows && (int)$existing->row['status'] === 1) {
            return 'already';
        }

        $this->db->query("INSERT INTO `" . DB_PREFIX . "dockercart_newsletter_subscriber` SET
            email = '" . $this->db->escape($normalized_email) . "',
            source = 'import',
            status = '1',
            date_added = NOW(),
            date_modified = NOW()
            ON DUPLICATE KEY UPDATE
                source = 'import',
                status = '1',
                date_modified = NOW()");

        return $existing->num_rows ? 'reactivated' : 'subscribed_guest';
    }

    public function deleteGuestSubscriber($subscriber_id) {
        $this->ensureSchema();

        $this->db->query("DELETE FROM `" . DB_PREFIX . "dockercart_newsletter_subscriber` WHERE subscriber_id = '" . (int)$subscriber_id . "'");
    }

    public function unsubscribeCustomer($customer_id) {
        $this->ensureSchema();

        $customer_id = (int)$customer_id;

        $query = $this->db->query("SELECT email FROM `" . DB_PREFIX . "customer` WHERE customer_id = '" . $customer_id . "' LIMIT 1");

        if ($query->num_rows) {
            $this->db->query("UPDATE `" . DB_PREFIX . "customer` SET newsletter = '0' WHERE customer_id = '" . $customer_id . "'");

            $email = $this->normalizeEmail($query->row['email']);
            if ($email !== '') {
                $this->db->query("DELETE FROM `" . DB_PREFIX . "dockercart_newsletter_subscriber` WHERE email = '" . $this->db->escape($email) . "'");
            }
        }
    }

    private function buildCombinedSubscribersSubquery() {
        $guest_sql = "
            SELECT
                s.subscriber_id,
                s.email COLLATE utf8mb4_unicode_ci AS email,
                0 AS customer_id,
                s.source,
                s.status,
                s.date_added,
                'guest' AS subscriber_type
            FROM `" . DB_PREFIX . "dockercart_newsletter_subscriber` s
            LEFT JOIN `" . DB_PREFIX . "customer` c ON (LCASE(c.email) COLLATE utf8mb4_unicode_ci = LCASE(s.email) COLLATE utf8mb4_unicode_ci AND c.newsletter = '1')
            WHERE s.status = '1' AND c.customer_id IS NULL
        ";

        $customer_sql = "
            SELECT
                0 AS subscriber_id,
                c.email COLLATE utf8mb4_unicode_ci AS email,
                c.customer_id,
                'customer' AS source,
                c.newsletter AS status,
                c.date_added,
                'customer' AS subscriber_type
            FROM `" . DB_PREFIX . "customer` c
            WHERE c.newsletter = '1'
        ";

        return $guest_sql . " UNION ALL " . $customer_sql;
    }

    private function ensureSchema() {
        if ($this->schema_ensured) {
            return;
        }

        $this->schema_ensured = true;

        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "dockercart_newsletter_subscriber` (
            `subscriber_id` int(11) NOT NULL AUTO_INCREMENT,
            `email` varchar(191) NOT NULL,
            `source` varchar(32) NOT NULL DEFAULT 'guest',
            `status` tinyint(1) NOT NULL DEFAULT '1',
            `date_added` datetime NOT NULL,
            `date_modified` datetime NOT NULL,
            PRIMARY KEY (`subscriber_id`),
            UNIQUE KEY `email` (`email`),
            KEY `status_date_added` (`status`, `date_added`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    private function normalizeEmail($email) {
        return strtolower(trim((string)$email));
    }
}
