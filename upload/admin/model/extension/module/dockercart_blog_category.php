<?php
/**
 * DockerCart Blog - Category Admin Model
 */

class ModelExtensionModuleDockercartBlogCategory extends Model {

	public function addCategory($data) {
		$this->db->query("INSERT INTO `" . DB_PREFIX . "blog_category` SET 
			parent_id = '" . (int)$data['parent_id'] . "', 
			image = '" . $this->db->escape($data['image']) . "',
			status = '" . (int)$data['status'] . "', 
			sort_order = '" . (int)$data['sort_order'] . "', 
			date_added = NOW(), 
			date_modified = NOW()");

		$category_id = $this->db->getLastId();

		if (isset($data['category_description'])) {
			foreach ($data['category_description'] as $language_id => $value) {
				$this->db->query("INSERT INTO `" . DB_PREFIX . "blog_category_description` SET 
					category_id = '" . (int)$category_id . "', 
					language_id = '" . (int)$language_id . "', 
					name = '" . $this->db->escape($value['name']) . "', 
					description = '" . $this->db->escape($value['description']) . "', 
					meta_title = '" . $this->db->escape($value['meta_title']) . "', 
					meta_description = '" . $this->db->escape($value['meta_description']) . "', 
					meta_keyword = '" . $this->db->escape($value['meta_keyword']) . "'");
			}
		}

		if (isset($data['category_store'])) {
			foreach ($data['category_store'] as $store_id) {
				$this->db->query("INSERT INTO `" . DB_PREFIX . "blog_category_to_store` SET 
					category_id = '" . (int)$category_id . "', 
					store_id = '" . (int)$store_id . "'");
			}
		}

		// SEO URLs (blog-specific)
		if (isset($data['category_seo_url'])) {
			$seo_url_updated = false;
			foreach ($data['category_seo_url'] as $store_id => $language) {
				foreach ($language as $language_id => $keyword) {
					if (trim($keyword)) {
						$this->db->query("INSERT INTO `" . DB_PREFIX . "blog_seo_url` SET 
							store_id = '" . (int)$store_id . "', 
							language_id = '" . (int)$language_id . "', 
							query = 'blog_category_id=" . (int)$category_id . "', 
							keyword = '" . $this->db->escape($keyword) . "'");
						$seo_url_updated = true;
					}
				}
			}

			if ($seo_url_updated) {
				$this->load->model('design/seo_url');
				$this->model_design_seo_url->invalidateSeoUrlCache();
			}
		}

		$this->cache->delete('blog.category');
		$this->cache->delete('blog.post');
		$this->cache->delete('blog.popular');
		$this->cache->delete('blog.recent');
		$this->cache->delete('blog.archive');
		return $category_id;
	}

	public function editCategory($category_id, $data) {
		$this->db->query("UPDATE `" . DB_PREFIX . "blog_category` SET 
			parent_id = '" . (int)$data['parent_id'] . "', 
			image = '" . $this->db->escape($data['image']) . "',
			status = '" . (int)$data['status'] . "', 
			sort_order = '" . (int)$data['sort_order'] . "', 
			date_modified = NOW() 
			WHERE category_id = '" . (int)$category_id . "'");

		$this->db->query("DELETE FROM `" . DB_PREFIX . "blog_category_description` WHERE category_id = '" . (int)$category_id . "'");

		if (isset($data['category_description'])) {
			foreach ($data['category_description'] as $language_id => $value) {
				$this->db->query("INSERT INTO `" . DB_PREFIX . "blog_category_description` SET 
					category_id = '" . (int)$category_id . "', 
					language_id = '" . (int)$language_id . "', 
					name = '" . $this->db->escape($value['name']) . "', 
					description = '" . $this->db->escape($value['description']) . "', 
					meta_title = '" . $this->db->escape($value['meta_title']) . "', 
					meta_description = '" . $this->db->escape($value['meta_description']) . "', 
					meta_keyword = '" . $this->db->escape($value['meta_keyword']) . "'");
			}
		}

		$this->db->query("DELETE FROM `" . DB_PREFIX . "blog_category_to_store` WHERE category_id = '" . (int)$category_id . "'");

		if (isset($data['category_store'])) {
			foreach ($data['category_store'] as $store_id) {
				$this->db->query("INSERT INTO `" . DB_PREFIX . "blog_category_to_store` SET 
					category_id = '" . (int)$category_id . "', 
					store_id = '" . (int)$store_id . "'");
			}
		}
		$this->cache->delete('blog.category');
		$this->cache->delete('blog.post');
		$this->cache->delete('blog.popular');
		$this->cache->delete('blog.recent');
		$this->cache->delete('blog.archive');

		// Update SEO URLs (blog-specific)
		$this->db->query("DELETE FROM `" . DB_PREFIX . "blog_seo_url` WHERE query = 'blog_category_id=" . (int)$category_id . "'");
		$seo_url_updated = true;

		if (isset($data['category_seo_url'])) {
			foreach ($data['category_seo_url'] as $store_id => $language) {
				foreach ($language as $language_id => $keyword) {
					if (trim($keyword)) {
						$this->db->query("INSERT INTO `" . DB_PREFIX . "blog_seo_url` SET 
							store_id = '" . (int)$store_id . "', 
							language_id = '" . (int)$language_id . "', 
							query = 'blog_category_id=" . (int)$category_id . "', 
							keyword = '" . $this->db->escape($keyword) . "'");
					}
				}
			}
		}

		if ($seo_url_updated) {
			$this->load->model('design/seo_url');
			$this->model_design_seo_url->invalidateSeoUrlCache();
		}

		$this->cache->delete('blog.category');
		$this->cache->delete('blog.post');
		$this->cache->delete('blog.popular');
		$this->cache->delete('blog.recent');
		$this->cache->delete('blog.archive');
	}

	public function deleteCategory($category_id) {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "blog_category` WHERE category_id = '" . (int)$category_id . "'");
		$this->db->query("DELETE FROM `" . DB_PREFIX . "blog_category_description` WHERE category_id = '" . (int)$category_id . "'");
		$this->db->query("DELETE FROM `" . DB_PREFIX . "blog_category_to_store` WHERE category_id = '" . (int)$category_id . "'");
		// Remove blog-specific SEO URLs
		$this->db->query("DELETE FROM `" . DB_PREFIX . "blog_seo_url` WHERE query = 'blog_category_id=" . (int)$category_id . "'");
		$this->load->model('design/seo_url');
		$this->model_design_seo_url->invalidateSeoUrlCache();
		$this->cache->delete('blog.category');
		$this->cache->delete('blog.post');
		$this->cache->delete('blog.popular');
		$this->cache->delete('blog.recent');
		$this->cache->delete('blog.archive');
	}

	public function copyCategory($category_id)
	{
		$query = $this->db->query(
			"SELECT * FROM `" .
				DB_PREFIX .
				"blog_category` WHERE category_id = '" .
				(int) $category_id .
				"'",
		);

		if (!$query->num_rows) {
			return false;
		}

		$category = $query->row;

		$data = [];

		$data["parent_id"] = $category["parent_id"];
		$data["image"] = $category["image"];
		$data["status"] = $category["status"];
		$data["sort_order"] = $category["sort_order"];
		$data["category_description"] = $this->getCategoryDescriptions(
			$category_id,
		);
		$data["category_store"] = $this->getCategoryStores($category_id);

		// Make name unique for default language
		$default_language_id = (int) $this->config->get("config_language_id");

		if (
			isset(
				$data["category_description"][$default_language_id]["name"],
			)
		) {
			$data["category_description"][$default_language_id][
				"name"
			] = $this->getUniqueCopyName(
				$data["category_description"][$default_language_id]["name"],
				DB_PREFIX . "blog_category_description",
				"name",
			);
		}

		// Make SEO URL keywords unique
		$seo_urls = $this->getCategorySeoUrls($category_id);

		foreach ($seo_urls as $store_id => &$languages) {
			foreach ($languages as $language_id => &$keyword) {
				$keyword = $this->getUniqueCopyName(
					$keyword,
					DB_PREFIX . "blog_seo_url",
					"keyword",
				);
			}
		}
		unset($languages, $keyword);

		$data["category_seo_url"] = $seo_urls;

		return $this->addCategory($data);
	}

	private function getUniqueCopyName($original, $table, $column)
	{
		$base = $original;

		if (preg_match('/^(.+)-copy(\d*)$/', $original, $matches)) {
			$base = $matches[1];
		}

		$counter = 0;

		do {
			$counter++;
			$suffix = $counter > 1 ? (string) $counter : "";
			$candidate = $base . "-copy" . $suffix;

			$query = $this->db->query(
				"SELECT COUNT(*) AS total FROM " .
					$table .
					" WHERE " .
					$column .
					" = '" .
					$this->db->escape($candidate) .
					"'",
			);
		} while ($query->row["total"] > 0);

		return $candidate;
	}

	public function getCategory($category_id) {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "blog_category` WHERE category_id = '" . (int)$category_id . "'");
		return $query->row;
	}

	public function getCategories($data = array()) {
		$sql = "SELECT bc.*, bcd.name 
				FROM `" . DB_PREFIX . "blog_category` bc
				LEFT JOIN `" . DB_PREFIX . "blog_category_description` bcd ON (bc.category_id = bcd.category_id)
				WHERE bcd.language_id = '" . (int)$this->config->get('config_language_id') . "'
				ORDER BY bc.sort_order ASC, bcd.name ASC";

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

	public function getTotalCategories() {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "blog_category` b 
			LEFT JOIN `" . DB_PREFIX . "blog_category_description` bd ON (b.category_id = bd.category_id) 
			WHERE bd.language_id = '" . (int)$this->config->get('config_language_id') . "'");

		return $query->row['total'];
	}

	public function getCategoryDescriptions($category_id) {
		$category_description_data = array();

		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "blog_category_description` WHERE category_id = '" . (int)$category_id . "'");

		foreach ($query->rows as $result) {
			$category_description_data[$result['language_id']] = array(
				'name'              => $result['name'],
				'description'       => $result['description'],
				'meta_title'        => $result['meta_title'],
				'meta_description'  => $result['meta_description'],
				'meta_keyword'      => $result['meta_keyword']
			);
		}

		return $category_description_data;
	}

	/**
	 * Get blog category SEO URLs
	 *
	 * @param int $category_id
	 * @return array
	 */
	public function getCategorySeoUrls($category_id) {
		$category_seo_url_data = array();
		
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "blog_seo_url` WHERE query = 'blog_category_id=" . (int)$category_id . "'");

		foreach ($query->rows as $result) {
			$category_seo_url_data[$result['store_id']][$result['language_id']] = $result['keyword'];
		}

		return $category_seo_url_data;
	}

	public function getCategoryStores($category_id) {
		$category_store_data = array();

		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "blog_category_to_store` WHERE category_id = '" . (int)$category_id . "'");

		foreach ($query->rows as $result) {
			$category_store_data[] = $result['store_id'];
		}

		return $category_store_data;
	}
}
