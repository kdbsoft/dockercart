<?php
/**
 * DockerCart Blog - Comment Catalog Model
 * 
 * @package    DockerCart Blog
 * @version    1.0.0
 * @author     DockerCart Team
 * 
 * Description: Model for blog comments (frontend).
 */

class ModelExtensionModuleDockercartBlogComment extends Model {

	/**
	 * Add new comment
	 * 
	 * @param array $data Comment data
	 * @return int Comment ID
	 */
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

		$comment_id = $this->db->getLastId();

		$this->cache->delete('blog.comment');

		return $comment_id;
	}

	/**
	 * Get approved comments for a post
	 * 
	 * @param int $post_id
	 * @param int $start
	 * @param int $limit
	 * @return array
	 */
	public function getCommentsByPostId($post_id, $start = 0, $limit = 20) {
		if ($start < 0) {
			$start = 0;
		}

		if ($limit < 1) {
			$limit = 20;
		}

		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "blog_comment` 
				WHERE post_id = '" . (int)$post_id . "' 
				AND status = '1' 
				ORDER BY date_added DESC 
				LIMIT " . (int)$start . "," . (int)$limit);

		return $query->rows;
	}

	/**
	 * Get total approved comments for a post
	 * 
	 * @param int $post_id
	 * @return int
	 */
	public function getTotalCommentsByPostId($post_id) {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "blog_comment` 
				WHERE post_id = '" . (int)$post_id . "' 
				AND status = '1'");

		return $query->row['total'];
	}
}
