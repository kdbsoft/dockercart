<?php
/**
 * DockerCart Blog - Post Catalog Model
 * 
 * @package    DockerCart Blog
 * @version    1.0.0
 * @author     DockerCart Team
 * 
 * Description: Catalog model for blog posts (frontend).
 *              Provides methods for retrieving published posts with caching.
 */

class ModelExtensionModuleDockercartBlogPost extends Model {

	/**
	 * Get single post by ID
	 * 
	 * @param int $post_id
	 * @return array|false
	 */
	public function getPost($post_id) {
		$cache_key = 'blog.post.' . (int)$post_id . '.' . (int)$this->config->get('config_language_id') . '.' . (int)$this->config->get('config_store_id');
		
		$post_data = $this->cache->get($cache_key);
		
		if (!$post_data) {
			$query = $this->db->query("SELECT DISTINCT bp.*, bpd.name, bpd.description, bpd.content, 
					bpd.meta_title, bpd.meta_description, bpd.meta_keyword, ba.name as author_name
					FROM `" . DB_PREFIX . "blog_post` bp
					LEFT JOIN `" . DB_PREFIX . "blog_post_description` bpd ON (bp.post_id = bpd.post_id)
					LEFT JOIN `" . DB_PREFIX . "blog_post_to_store` bps ON (bp.post_id = bps.post_id)
					LEFT JOIN `" . DB_PREFIX . "blog_author` ba ON (bp.author_id = ba.author_id)
					WHERE bp.post_id = '" . (int)$post_id . "' 
					AND bpd.language_id = '" . (int)$this->config->get('config_language_id') . "' 
					AND bps.store_id = '" . (int)$this->config->get('config_store_id') . "' 
					AND bp.status = '1' 
					AND bp.date_published <= NOW()");

			if ($query->num_rows) {
				$post_data = $query->row;

				// Get tags
				$tag_query = $this->db->query("SELECT GROUP_CONCAT(tag SEPARATOR ', ') as tags 
						FROM `" . DB_PREFIX . "blog_post_tag` 
						WHERE post_id = '" . (int)$post_id . "' 
						AND language_id = '" . (int)$this->config->get('config_language_id') . "'");

				$post_data['tags'] = $tag_query->row['tags'];

				$this->cache->set($cache_key, $post_data, 3600);
			}
		}

		return $post_data;
	}

	/**
	 * Get posts with filtering and pagination
	 * 
	 * @param array $data Filters
	 * @return array
	 */
	public function getPosts($data = array()) {
		$sql = "SELECT bp.*, bpd.name, bpd.description, ba.name as author_name 
				FROM `" . DB_PREFIX . "blog_post` bp 
				LEFT JOIN `" . DB_PREFIX . "blog_post_description` bpd ON (bp.post_id = bpd.post_id) 
				LEFT JOIN `" . DB_PREFIX . "blog_post_to_store` bps ON (bp.post_id = bps.post_id)
				LEFT JOIN `" . DB_PREFIX . "blog_author` ba ON (bp.author_id = ba.author_id)
				WHERE bpd.language_id = '" . (int)$this->config->get('config_language_id') . "' 
				AND bps.store_id = '" . (int)$this->config->get('config_store_id') . "' 
				AND bp.status = '1' 
				AND bp.date_published <= NOW()";

		// Filter by category
		if (!empty($data['filter_category_id'])) {
			$sql .= " AND EXISTS (SELECT 1 FROM `" . DB_PREFIX . "blog_post_to_category` bpc 
					WHERE bpc.post_id = bp.post_id 
					AND bpc.category_id = '" . (int)$data['filter_category_id'] . "')";
		}

		// Filter by author
		if (!empty($data['filter_author_id'])) {
			$sql .= " AND bp.author_id = '" . (int)$data['filter_author_id'] . "'";
		}

		// Filter by featured
		if (isset($data['filter_featured'])) {
			$sql .= " AND bp.featured = '" . (int)$data['filter_featured'] . "'";
		}

		// Filter by tag
		if (!empty($data['filter_tag'])) {
			$sql .= " AND EXISTS (SELECT 1 FROM `" . DB_PREFIX . "blog_post_tag` bpt 
					WHERE bpt.post_id = bp.post_id 
					AND bpt.language_id = '" . (int)$this->config->get('config_language_id') . "' 
					AND bpt.tag = '" . $this->db->escape($data['filter_tag']) . "')";
		}

		// Search
		if (!empty($data['filter_search'])) {
			$sql .= " AND (bpd.name LIKE '%" . $this->db->escape($data['filter_search']) . "%' 
					OR bpd.description LIKE '%" . $this->db->escape($data['filter_search']) . "%' 
					OR bpd.content LIKE '%" . $this->db->escape($data['filter_search']) . "%')";
		}

		$sql .= " GROUP BY bp.post_id";

		// Sorting
		$sort_data = array(
			'bpd.name',
			'bp.date_published',
			'bp.views',
			'bp.sort_order'
		);

		if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
			$sql .= " ORDER BY " . $data['sort'];
		} else {
			$sql .= " ORDER BY bp.date_published";
		}

		if (isset($data['order']) && ($data['order'] == 'ASC')) {
			$sql .= " ASC";
		} else {
			$sql .= " DESC";
		}

		// Pagination
		if (isset($data['start']) || isset($data['limit'])) {
			if ($data['start'] < 0) {
				$data['start'] = 0;
			}

			if ($data['limit'] < 1) {
				$data['limit'] = 10;
			}

			$sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
		}

		$query = $this->db->query($sql);

		return $query->rows;
	}

	/**
	 * Get total posts count
	 * 
	 * @param array $data Filters
	 * @return int
	 */
	public function getTotalPosts($data = array()) {
		$sql = "SELECT COUNT(DISTINCT bp.post_id) AS total 
				FROM `" . DB_PREFIX . "blog_post` bp 
				LEFT JOIN `" . DB_PREFIX . "blog_post_description` bpd ON (bp.post_id = bpd.post_id) 
				LEFT JOIN `" . DB_PREFIX . "blog_post_to_store` bps ON (bp.post_id = bps.post_id)
				WHERE bpd.language_id = '" . (int)$this->config->get('config_language_id') . "' 
				AND bps.store_id = '" . (int)$this->config->get('config_store_id') . "' 
				AND bp.status = '1' 
				AND bp.date_published <= NOW()";

		if (!empty($data['filter_category_id'])) {
			$sql .= " AND EXISTS (SELECT 1 FROM `" . DB_PREFIX . "blog_post_to_category` bpc 
					WHERE bpc.post_id = bp.post_id 
					AND bpc.category_id = '" . (int)$data['filter_category_id'] . "')";
		}

		if (!empty($data['filter_author_id'])) {
			$sql .= " AND bp.author_id = '" . (int)$data['filter_author_id'] . "'";
		}

		if (isset($data['filter_featured'])) {
			$sql .= " AND bp.featured = '" . (int)$data['filter_featured'] . "'";
		}

		if (!empty($data['filter_tag'])) {
			$sql .= " AND EXISTS (SELECT 1 FROM `" . DB_PREFIX . "blog_post_tag` bpt 
					WHERE bpt.post_id = bp.post_id 
					AND bpt.language_id = '" . (int)$this->config->get('config_language_id') . "' 
					AND bpt.tag = '" . $this->db->escape($data['filter_tag']) . "')";
		}

		if (!empty($data['filter_search'])) {
			$sql .= " AND (bpd.name LIKE '%" . $this->db->escape($data['filter_search']) . "%' 
					OR bpd.description LIKE '%" . $this->db->escape($data['filter_search']) . "%' 
					OR bpd.content LIKE '%" . $this->db->escape($data['filter_search']) . "%')";
		}

		$query = $this->db->query($sql);

		return $query->row['total'];
	}

	/**
	 * Get post categories
	 * 
	 * @param int $post_id
	 * @return array
	 */
	public function getPostCategories($post_id) {
		$query = $this->db->query("SELECT bpc.category_id, bcd.name 
				FROM `" . DB_PREFIX . "blog_post_to_category` bpc
				LEFT JOIN `" . DB_PREFIX . "blog_category` bc ON (bpc.category_id = bc.category_id)
				LEFT JOIN `" . DB_PREFIX . "blog_category_description` bcd ON (bpc.category_id = bcd.category_id)
				WHERE bpc.post_id = '" . (int)$post_id . "' 
				AND bcd.language_id = '" . (int)$this->config->get('config_language_id') . "'
				AND (
					EXISTS (
						SELECT 1 FROM `" . DB_PREFIX . "blog_category_to_store` bcs
						WHERE bcs.category_id = bc.category_id
						AND bcs.store_id = '" . (int)$this->config->get('config_store_id') . "'
					)
					OR NOT EXISTS (
						SELECT 1 FROM `" . DB_PREFIX . "blog_category_to_store` bcs_all
						WHERE bcs_all.category_id = bc.category_id
					)
				)
				AND bc.status = '1'
				ORDER BY bc.sort_order, bcd.name");

		return $query->rows;
	}

	/**
	 * Increment post view counter
	 * 
	 * @param int $post_id
	 */
	public function incrementViews($post_id) {
		$this->db->query("UPDATE `" . DB_PREFIX . "blog_post` 
				SET views = views + 1 
				WHERE post_id = '" . (int)$post_id . "'");
		
		// Invalidate cache after incrementing views
		$cache_key = 'blog.post.' . (int)$post_id . '.' . (int)$this->config->get('config_language_id') . '.' . (int)$this->config->get('config_store_id');
		$this->cache->delete($cache_key);
	}

	/**
	 * Get related posts based on categories
	 * 
	 * @param int $post_id
	 * @param int $limit
	 * @return array
	 */
	public function getRelatedPosts($post_id, $limit = 5) {
		$post_data = array();

		$query = $this->db->query("SELECT DISTINCT bp.*, bpd.name, bpd.description, ba.name as author_name
				FROM `" . DB_PREFIX . "blog_post` bp
				LEFT JOIN `" . DB_PREFIX . "blog_post_description` bpd ON (bp.post_id = bpd.post_id)
				LEFT JOIN `" . DB_PREFIX . "blog_post_to_store` bps ON (bp.post_id = bps.post_id)
				LEFT JOIN `" . DB_PREFIX . "blog_post_to_category` bpc ON (bp.post_id = bpc.post_id)
				LEFT JOIN `" . DB_PREFIX . "blog_author` ba ON (bp.author_id = ba.author_id)
				WHERE bpd.language_id = '" . (int)$this->config->get('config_language_id') . "'
				AND bps.store_id = '" . (int)$this->config->get('config_store_id') . "'
				AND bp.status = '1'
				AND bp.date_published <= NOW()
				AND bp.post_id != '" . (int)$post_id . "'
				AND bpc.category_id IN (SELECT category_id FROM `" . DB_PREFIX . "blog_post_to_category` WHERE post_id = '" . (int)$post_id . "')
				GROUP BY bp.post_id
				ORDER BY bp.date_published DESC
				LIMIT " . (int)$limit);

		return $query->rows;
	}
}
