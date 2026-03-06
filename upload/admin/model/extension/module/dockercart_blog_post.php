<?php
/**
 * DockerCart Blog - Post Admin Model
 * 
 * @package    DockerCart Blog
 * @version    1.0.0
 * @author     DockerCart Team
 * 
 * Description: Model for blog post management in admin panel.
 *              Handles CRUD operations, filtering, sorting, SEO URLs.
 */

class ModelExtensionModuleDockercartBlogPost extends Model {

	/**
	 * Add new blog post
	 * 
	 * @param array $data Post data
	 * @return int Post ID
	 */
	public function addPost($data) {
		// Ensure date_published is provided; default to NOW() if not
		if (isset($data['date_published']) && $data['date_published']) {
			$date_published_sql = "date_published = '" . $this->db->escape($data['date_published']) . "', ";
		} else {
			$date_published_sql = "date_published = NOW(), ";
		}

		$this->db->query("INSERT INTO `" . DB_PREFIX . "blog_post` SET 
			author_id = '" . (int)$data['author_id'] . "', 
			image = '" . $this->db->escape($data['image']) . "',
			status = '" . (int)$data['status'] . "', 
			featured = '" . (int)$data['featured'] . "', 
			allow_comments = '" . (int)$data['allow_comments'] . "', 
			sort_order = '" . (int)$data['sort_order'] . "', 
			" . $date_published_sql . "
			date_added = NOW(), 
			date_modified = NOW()");

		$post_id = $this->db->getLastId();

		// Post descriptions (multi-language)
		if (isset($data['post_description'])) {
			foreach ($data['post_description'] as $language_id => $value) {
				$this->db->query("INSERT INTO `" . DB_PREFIX . "blog_post_description` SET 
					post_id = '" . (int)$post_id . "', 
					language_id = '" . (int)$language_id . "', 
					name = '" . $this->db->escape($value['title']) . "', 
					description = '" . $this->db->escape($value['excerpt']) . "', 
					content = '" . $this->db->escape($value['content']) . "', 
					meta_title = '" . $this->db->escape($value['meta_title']) . "', 
					meta_description = '" . $this->db->escape($value['meta_description']) . "', 
					meta_keyword = '" . $this->db->escape($value['meta_keyword']) . "'");
			}
		}

		// Post to store
		if (isset($data['post_store'])) {
			foreach ($data['post_store'] as $store_id) {
				$this->db->query("INSERT INTO `" . DB_PREFIX . "blog_post_to_store` SET 
					post_id = '" . (int)$post_id . "', 
					store_id = '" . (int)$store_id . "'");
			}
		}

		// Post to category
		if (isset($data['post_category'])) {
			foreach ($data['post_category'] as $category_id) {
				$this->db->query("INSERT INTO `" . DB_PREFIX . "blog_post_to_category` SET 
					post_id = '" . (int)$post_id . "', 
					category_id = '" . (int)$category_id . "'");
			}
		}

		// Tags
		if (isset($data['post_description'])) {
			foreach ($data['post_description'] as $language_id => $value) {
				if (isset($value['tags']) && $value['tags']) {
					$tag_array = explode(',', $value['tags']);
					foreach ($tag_array as $tag) {
						$tag = trim($tag);
						if ($tag) {
							$this->db->query("INSERT INTO `" . DB_PREFIX . "blog_post_tag` SET 
								post_id = '" . (int)$post_id . "', 
								language_id = '" . (int)$language_id . "', 
								tag = '" . $this->db->escape($tag) . "'");
						}
					}
				}
			}
		}

		// SEO URLs
		if (isset($data['post_seo_url'])) {
			foreach ($data['post_seo_url'] as $store_id => $language) {
				foreach ($language as $language_id => $keyword) {
					if (trim($keyword)) {
						$this->db->query("INSERT INTO `" . DB_PREFIX . "blog_seo_url` SET 
							store_id = '" . (int)$store_id . "', 
							language_id = '" . (int)$language_id . "', 
							query = 'blog_post_id=" . (int)$post_id . "', 
							keyword = '" . $this->db->escape($keyword) . "'");
					}
				}
			}
		}

		$this->cache->delete('blog.post');

		return $post_id;
	}

	/**
	 * Edit existing blog post
	 * 
	 * @param int $post_id
	 * @param array $data
	 */
	public function editPost($post_id, $data) {
		// Ensure date_published is provided; default to NOW() if not
		if (isset($data['date_published']) && $data['date_published']) {
			$date_published_sql = "date_published = '" . $this->db->escape($data['date_published']) . "', ";
		} else {
			$date_published_sql = "date_published = NOW(), ";
		}

		$this->db->query("UPDATE `" . DB_PREFIX . "blog_post` SET 
			author_id = '" . (int)$data['author_id'] . "', 
			image = '" . $this->db->escape($data['image']) . "',
			status = '" . (int)$data['status'] . "', 
			featured = '" . (int)$data['featured'] . "', 
			allow_comments = '" . (int)$data['allow_comments'] . "', 
			sort_order = '" . (int)$data['sort_order'] . "', 
			" . $date_published_sql . "
			date_modified = NOW() 
			WHERE post_id = '" . (int)$post_id . "'");

		// Delete old descriptions
		$this->db->query("DELETE FROM `" . DB_PREFIX . "blog_post_description` WHERE post_id = '" . (int)$post_id . "'");

		// Insert new descriptions
		if (isset($data['post_description'])) {
			foreach ($data['post_description'] as $language_id => $value) {
				$this->db->query("INSERT INTO `" . DB_PREFIX . "blog_post_description` SET 
					post_id = '" . (int)$post_id . "', 
					language_id = '" . (int)$language_id . "', 
					name = '" . $this->db->escape($value['title']) . "', 
					description = '" . $this->db->escape($value['excerpt']) . "', 
					content = '" . $this->db->escape($value['content']) . "', 
					meta_title = '" . $this->db->escape($value['meta_title']) . "', 
					meta_description = '" . $this->db->escape($value['meta_description']) . "', 
					meta_keyword = '" . $this->db->escape($value['meta_keyword']) . "'");
			}
		}

		// Update stores
		$this->db->query("DELETE FROM `" . DB_PREFIX . "blog_post_to_store` WHERE post_id = '" . (int)$post_id . "'");
		if (isset($data['post_store'])) {
			foreach ($data['post_store'] as $store_id) {
				$this->db->query("INSERT INTO `" . DB_PREFIX . "blog_post_to_store` SET 
					post_id = '" . (int)$post_id . "', 
					store_id = '" . (int)$store_id . "'");
			}
		}

		// Update categories
		$this->db->query("DELETE FROM `" . DB_PREFIX . "blog_post_to_category` WHERE post_id = '" . (int)$post_id . "'");
		if (isset($data['post_category'])) {
			foreach ($data['post_category'] as $category_id) {
				$this->db->query("INSERT INTO `" . DB_PREFIX . "blog_post_to_category` SET 
					post_id = '" . (int)$post_id . "', 
					category_id = '" . (int)$category_id . "'");
			}
		}

		// Update tags
		$this->db->query("DELETE FROM `" . DB_PREFIX . "blog_post_tag` WHERE post_id = '" . (int)$post_id . "'");
		if (isset($data['post_description'])) {
			foreach ($data['post_description'] as $language_id => $value) {
				if (isset($value['tags']) && $value['tags']) {
					$tag_array = explode(',', $value['tags']);
					foreach ($tag_array as $tag) {
						$tag = trim($tag);
						if ($tag) {
							$this->db->query("INSERT INTO `" . DB_PREFIX . "blog_post_tag` SET 
								post_id = '" . (int)$post_id . "', 
								language_id = '" . (int)$language_id . "', 
								tag = '" . $this->db->escape($tag) . "'");
						}
					}
				}
			}
		}

		// Update SEO URLs
		$this->db->query("DELETE FROM `" . DB_PREFIX . "blog_seo_url` WHERE query = 'blog_post_id=" . (int)$post_id . "'");
		if (isset($data['post_seo_url'])) {
			foreach ($data['post_seo_url'] as $store_id => $language) {
				foreach ($language as $language_id => $keyword) {
					if (trim($keyword)) {
						$this->db->query("INSERT INTO `" . DB_PREFIX . "blog_seo_url` SET 
							store_id = '" . (int)$store_id . "', 
							language_id = '" . (int)$language_id . "', 
							query = 'blog_post_id=" . (int)$post_id . "', 
							keyword = '" . $this->db->escape($keyword) . "'");
					}
				}
			}
		}

		$this->cache->delete('blog.post');
	}

	/**
	 * Delete blog post
	 * 
	 * @param int $post_id
	 */
	public function deletePost($post_id) {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "blog_post` WHERE post_id = '" . (int)$post_id . "'");
		$this->db->query("DELETE FROM `" . DB_PREFIX . "blog_post_description` WHERE post_id = '" . (int)$post_id . "'");
		$this->db->query("DELETE FROM `" . DB_PREFIX . "blog_post_to_store` WHERE post_id = '" . (int)$post_id . "'");
		$this->db->query("DELETE FROM `" . DB_PREFIX . "blog_post_to_category` WHERE post_id = '" . (int)$post_id . "'");
		$this->db->query("DELETE FROM `" . DB_PREFIX . "blog_post_tag` WHERE post_id = '" . (int)$post_id . "'");
		$this->db->query("DELETE FROM `" . DB_PREFIX . "blog_comment` WHERE post_id = '" . (int)$post_id . "'");
		$this->db->query("DELETE FROM `" . DB_PREFIX . "blog_seo_url` WHERE query = 'blog_post_id=" . (int)$post_id . "'");

		$this->cache->delete('blog.post');
	}

	/**
	 * Get single post
	 * 
	 * @param int $post_id
	 * @return array|false
	 */
	public function getPost($post_id) {
		$query = $this->db->query("SELECT DISTINCT * FROM `" . DB_PREFIX . "blog_post` WHERE post_id = '" . (int)$post_id . "'");

		return $query->row;
	}

	/**
	 * Get posts with filtering and pagination
	 * 
	 * @param array $data Filters
	 * @return array
	 */
	public function getPosts($data = array()) {
		$sql = "SELECT bp.*, bpd.name, ba.name as author_name 
				FROM `" . DB_PREFIX . "blog_post` bp 
				LEFT JOIN `" . DB_PREFIX . "blog_post_description` bpd ON (bp.post_id = bpd.post_id) 
				LEFT JOIN `" . DB_PREFIX . "blog_author` ba ON (bp.author_id = ba.author_id) 
				WHERE bpd.language_id = '" . (int)$this->config->get('config_language_id') . "'";

		if (!empty($data['filter_name'])) {
			$sql .= " AND bpd.name LIKE '%" . $this->db->escape($data['filter_name']) . "%'";
		}

		if (isset($data['filter_status']) && $data['filter_status'] !== '') {
			$sql .= " AND bp.status = '" . (int)$data['filter_status'] . "'";
		}

		if (!empty($data['filter_author_id'])) {
			$sql .= " AND bp.author_id = '" . (int)$data['filter_author_id'] . "'";
		}

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
				WHERE bpd.language_id = '" . (int)$this->config->get('config_language_id') . "'";

		if (!empty($data['filter_name'])) {
			$sql .= " AND bpd.name LIKE '%" . $this->db->escape($data['filter_name']) . "%'";
		}

		if (isset($data['filter_status']) && $data['filter_status'] !== '') {
			$sql .= " AND bp.status = '" . (int)$data['filter_status'] . "'";
		}

		if (!empty($data['filter_author_id'])) {
			$sql .= " AND bp.author_id = '" . (int)$data['filter_author_id'] . "'";
		}

		$query = $this->db->query($sql);

		return $query->row['total'];
	}

	/**
	 * Get post descriptions for all languages
	 * 
	 * @param int $post_id
	 * @return array
	 */
	public function getPostDescriptions($post_id) {
		$post_description_data = array();

		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "blog_post_description` WHERE post_id = '" . (int)$post_id . "'");

		// Fetch description rows
		foreach ($query->rows as $result) {
			$post_description_data[$result['language_id']] = array(
				'title'              => $result['name'],
				'excerpt'            => $result['description'],
				'content'            => $result['content'],
				'meta_title'         => $result['meta_title'],
				'meta_description'   => $result['meta_description'],
				'meta_keyword'       => $result['meta_keyword'],
				'tags'               => ''
			);
		}

		// Populate tags per language from blog_post_tag table
		$post_tags = $this->getPostTags($post_id);
		if (!empty($post_tags)) {
			foreach ($post_tags as $language_id => $tags) {
				if (isset($post_description_data[$language_id])) {
					$post_description_data[$language_id]['tags'] = $tags;
				} else {
					// Ensure description exists for language even if missing in description table
					$post_description_data[$language_id] = array(
						'title' => '',
						'excerpt' => '',
						'content' => '',
						'meta_title' => '',
						'meta_description' => '',
						'meta_keyword' => '',
						'tags' => $tags
					);
				}
			}
		}

		return $post_description_data;
	}

	/**
	 * Get post categories
	 * 
	 * @param int $post_id
	 * @return array
	 */
	public function getPostCategories($post_id) {
		$post_category_data = array();

		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "blog_post_to_category` WHERE post_id = '" . (int)$post_id . "'");

		foreach ($query->rows as $result) {
			$post_category_data[] = $result['category_id'];
		}

		return $post_category_data;
	}

	/**
	 * Get post stores
	 * 
	 * @param int $post_id
	 * @return array
	 */
	public function getPostStores($post_id) {
		$post_store_data = array();

		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "blog_post_to_store` WHERE post_id = '" . (int)$post_id . "'");

		foreach ($query->rows as $result) {
			$post_store_data[] = $result['store_id'];
		}

		return $post_store_data;
	}

	/**
	 * Get post tags
	 * 
	 * @param int $post_id
	 * @return array
	 */
	public function getPostTags($post_id) {
		$post_tag_data = array();

		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "blog_post_tag` WHERE post_id = '" . (int)$post_id . "'");

		foreach ($query->rows as $result) {
			if (!isset($post_tag_data[$result['language_id']])) {
				$post_tag_data[$result['language_id']] = '';
			}
			
			if ($post_tag_data[$result['language_id']]) {
				$post_tag_data[$result['language_id']] .= ', ';
			}
			
			$post_tag_data[$result['language_id']] .= $result['tag'];
		}

		return $post_tag_data;
	}

	/**
	 * Get post SEO URLs
	 * 
	 * @param int $post_id
	 * @return array
	 */
	public function getPostSeoUrls($post_id) {
		$post_seo_url_data = array();
		
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "blog_seo_url` WHERE query = 'blog_post_id=" . (int)$post_id . "'");

		foreach ($query->rows as $result) {
			$post_seo_url_data[$result['store_id']][$result['language_id']] = $result['keyword'];
		}

		return $post_seo_url_data;
	}
}
