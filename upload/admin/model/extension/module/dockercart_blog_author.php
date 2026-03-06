<?php
/**
 * DockerCart Blog - Author Admin Model
 */

class ModelExtensionModuleDockercartBlogAuthor extends Model {

	public function addAuthor($data) {
		$this->db->query("INSERT INTO `" . DB_PREFIX . "blog_author` SET 
			name = '" . $this->db->escape($data['name']) . "', 
			email = '" . $this->db->escape($data['email']) . "', 
			image = '" . $this->db->escape($data['image']) . "',
			bio = '" . $this->db->escape($data['bio']) . "', 
			status = '" . (int)$data['status'] . "', 
			sort_order = '" . (int)$data['sort_order'] . "', 
			date_added = NOW(), 
			date_modified = NOW()");

		$this->cache->delete('blog.author');
		return $this->db->getLastId();
	}

	public function editAuthor($author_id, $data) {
		$this->db->query("UPDATE `" . DB_PREFIX . "blog_author` SET 
			name = '" . $this->db->escape($data['name']) . "', 
			email = '" . $this->db->escape($data['email']) . "', 
			image = '" . $this->db->escape($data['image']) . "',
			bio = '" . $this->db->escape($data['bio']) . "', 
			status = '" . (int)$data['status'] . "', 
			sort_order = '" . (int)$data['sort_order'] . "', 
			date_modified = NOW() 
			WHERE author_id = '" . (int)$author_id . "'");

		$this->cache->delete('blog.author');
	}

	/**
	 * Find author by name or email
	 *
	 * @param string $name
	 * @param string $email
	 * @return array|null
	 */
	public function getAuthorByNameOrEmail($name, $email) {
		// Compare case-insensitive for name and email
		$name = $this->db->escape(mb_strtolower(trim($name)));
		$email = $this->db->escape(mb_strtolower(trim($email)));
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "blog_author` WHERE LCASE(TRIM(name)) = '" . $name . "' OR LCASE(TRIM(email)) = '" . $email . "' LIMIT 1");
		if ($query->num_rows) {
			return $query->row;
		}
		return null;
	}

	public function deleteAuthor($author_id) {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "blog_author` WHERE author_id = '" . (int)$author_id . "'");
		$this->cache->delete('blog.author');
	}

	public function getAuthor($author_id) {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "blog_author` WHERE author_id = '" . (int)$author_id . "'");
		return $query->row;
	}

	public function getAuthors($data = array()) {
		$sql = "SELECT * FROM `" . DB_PREFIX . "blog_author` ORDER BY sort_order ASC, name ASC";

		if (isset($data['start']) || isset($data['limit'])) {
			if ($data['start'] < 0) {
				$data['start'] = 0;
			}

			if ($data['limit'] < 1) {
				$data['limit'] = 20;
			}

			$sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
		}

		$query = $this->db->query($sql);
		return $query->rows;
	}

	public function getTotalAuthors() {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "blog_author`");
		return $query->row['total'];
	}
}
