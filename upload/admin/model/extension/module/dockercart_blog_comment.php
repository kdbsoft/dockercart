<?php
/**
 * DockerCart Blog - Comment Admin Model
 */

class ModelExtensionModuleDockercartBlogComment extends Model {

	public function addComment($data) {
		$this->db->query("INSERT INTO `" . DB_PREFIX . "blog_comment` SET 
			post_id = '" . (int)$data['post_id'] . "', 
			customer_id = '" . (int)$data['customer_id'] . "', 
			author = '" . $this->db->escape($data['author']) . "', 
			email = '" . $this->db->escape($data['email']) . "', 
			text = '" . $this->db->escape($data['text']) . "', 
			rating = '" . (int)$data['rating'] . "', 
			status = '" . (int)$data['status'] . "', 
			ip = '" . $this->db->escape($data['ip']) . "', 
			date_added = NOW(), 
			date_modified = NOW()");

		$this->cache->delete('blog.comment');
		return $this->db->getLastId();
	}

	public function editComment($comment_id, $data) {
		$this->db->query("UPDATE `" . DB_PREFIX . "blog_comment` SET 
			author = '" . $this->db->escape($data['author']) . "', 
			email = '" . $this->db->escape($data['email']) . "', 
			text = '" . $this->db->escape($data['text']) . "', 
			rating = '" . (int)$data['rating'] . "', 
			status = '" . (int)$data['status'] . "', 
			date_modified = NOW() 
			WHERE comment_id = '" . (int)$comment_id . "'");

		$this->cache->delete('blog.comment');
	}

	public function deleteComment($comment_id) {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "blog_comment` WHERE comment_id = '" . (int)$comment_id . "'");
		$this->cache->delete('blog.comment');
	}

	public function getComment($comment_id) {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "blog_comment` WHERE comment_id = '" . (int)$comment_id . "'");
		return $query->row;
	}

	public function getComments($data = array()) {
		$sql = "SELECT bc.*, bpd.name as post_name 
				FROM `" . DB_PREFIX . "blog_comment` bc
				LEFT JOIN `" . DB_PREFIX . "blog_post_description` bpd ON (bc.post_id = bpd.post_id)
				WHERE bpd.language_id = '" . (int)$this->config->get('config_language_id') . "'";

		if (isset($data['filter_status']) && $data['filter_status'] !== '') {
			$sql .= " AND bc.status = '" . (int)$data['filter_status'] . "'";
		}

		if (isset($data['filter_post']) && $data['filter_post'] !== '') {
			$sql .= " AND bpd.name LIKE '%" . $this->db->escape($data['filter_post']) . "%'";
		}

		if (isset($data['filter_author']) && $data['filter_author'] !== '') {
			$sql .= " AND bc.author LIKE '%" . $this->db->escape($data['filter_author']) . "%'";
		}

		if (isset($data['filter_email']) && $data['filter_email'] !== '') {
			$sql .= " AND bc.email LIKE '%" . $this->db->escape($data['filter_email']) . "%'";
		}



		$sql .= " ORDER BY bc.date_added DESC";

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

	public function getTotalComments($data = array()) {
		$sql = "SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "blog_comment` bc
			LEFT JOIN `" . DB_PREFIX . "blog_post_description` bpd ON (bc.post_id = bpd.post_id)
			WHERE bpd.language_id = '" . (int)$this->config->get('config_language_id') . "'";

		if (isset($data['filter_status']) && $data['filter_status'] !== '') {
			$sql .= " AND bc.status = '" . (int)$data['filter_status'] . "'";
		}

		if (isset($data['filter_post']) && $data['filter_post'] !== '') {
			$sql .= " AND bpd.name LIKE '%" . $this->db->escape($data['filter_post']) . "%'";
		}

		if (isset($data['filter_author']) && $data['filter_author'] !== '') {
			$sql .= " AND bc.author LIKE '%" . $this->db->escape($data['filter_author']) . "%'";
		}

		if (isset($data['filter_email']) && $data['filter_email'] !== '') {
			$sql .= " AND bc.email LIKE '%" . $this->db->escape($data['filter_email']) . "%'";
		}



		$query = $this->db->query($sql);
		return $query->row['total'];
	}

	public function getTotalCommentsByPostId($post_id) {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "blog_comment` 
				WHERE post_id = '" . (int)$post_id . "' AND status = '1'");
		return $query->row['total'];
	}
}
