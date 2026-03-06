<?php
class ModelExtensionModuleDockercartNewsletter extends Model {
    private $schema_ensured = false;

    public function isSubscribed($email) {
        $this->ensureSchema();

        $email = $this->normalizeEmail($email);

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $customer = $this->db->query("SELECT customer_id FROM `" . DB_PREFIX . "customer` WHERE LCASE(email) COLLATE utf8mb4_unicode_ci = '" . $this->db->escape($email) . "' COLLATE utf8mb4_unicode_ci AND newsletter = '1' LIMIT 1");
        if ($customer->num_rows) {
            return true;
        }

        $subscriber = $this->db->query("SELECT subscriber_id FROM `" . DB_PREFIX . "dockercart_newsletter_subscriber` WHERE email = '" . $this->db->escape($email) . "' AND status = '1' LIMIT 1");

        return (bool)$subscriber->num_rows;
    }

    public function subscribeEmail($email) {
        $this->ensureSchema();

        $email = $this->normalizeEmail($email);

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'invalid';
        }

        $customer_query = $this->db->query("SELECT customer_id, newsletter FROM `" . DB_PREFIX . "customer` WHERE LCASE(email) COLLATE utf8mb4_unicode_ci = '" . $this->db->escape($email) . "' COLLATE utf8mb4_unicode_ci LIMIT 1");

        if ($customer_query->num_rows) {
            $customer_id = (int)$customer_query->row['customer_id'];

            if ((int)$customer_query->row['newsletter'] === 1) {
                return 'already';
            }

            $this->db->query("UPDATE `" . DB_PREFIX . "customer` SET newsletter = '1' WHERE customer_id = '" . $customer_id . "'");
            return 'subscribed_customer';
        }

        $existing = $this->db->query("SELECT subscriber_id, status FROM `" . DB_PREFIX . "dockercart_newsletter_subscriber` WHERE email = '" . $this->db->escape($email) . "' LIMIT 1");

        if ($existing->num_rows && (int)$existing->row['status'] === 1) {
            return 'already';
        }

        $this->db->query("INSERT INTO `" . DB_PREFIX . "dockercart_newsletter_subscriber` SET
            email = '" . $this->db->escape($email) . "',
            source = 'frontend',
            status = '1',
            date_added = NOW(),
            date_modified = NOW()
            ON DUPLICATE KEY UPDATE
                source = 'frontend',
                status = '1',
                date_modified = NOW()");

        return $existing->num_rows ? 'reactivated' : 'subscribed_guest';
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
