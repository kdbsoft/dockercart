<?php
/**
 * DockerCart Blog - Category Catalog Model
 */

class ModelExtensionModuleDockercartBlogCategory extends Model {

	public function getCategory($category_id) {
		$cache_key = 'blog.category.' . (int)$category_id . '.' . (int)$this->config->get('config_language_id');
		$category = $this->cache->get($cache_key);

		if (!$category) {
			$query = $this->db->query("SELECT DISTINCT * 
				FROM `" . DB_PREFIX . "blog_category` bc
				LEFT JOIN `" . DB_PREFIX . "blog_category_description` bcd ON (bc.category_id = bcd.category_id)
				WHERE bc.category_id = '" . (int)$category_id . "' 
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
				AND bc.status = '1'");

			$category = $query->row;

			$this->cache->set($cache_key, $category, 3600);
		}

		return $category;
	}

	public function getCategories($parent_id = 0) {
		$cache_key = 'blog.categories.' . (int)$parent_id . '.' . (int)$this->config->get('config_language_id') . '.' . (int)$this->config->get('config_store_id');
		$categories = $this->cache->get($cache_key);

		if (!$categories) {
			$query = $this->db->query("SELECT * 
				FROM `" . DB_PREFIX . "blog_category` bc
				LEFT JOIN `" . DB_PREFIX . "blog_category_description` bcd ON (bc.category_id = bcd.category_id)
				WHERE bc.parent_id = '" . (int)$parent_id . "' 
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

			$categories = $query->rows;

			$this->cache->set($cache_key, $categories, 3600);
		}

		return $categories;
	}

	public function getTotalPostsByCategory($category_id) {
		$query = $this->db->query("SELECT COUNT(*) AS total 
			FROM `" . DB_PREFIX . "blog_post` bp
			LEFT JOIN `" . DB_PREFIX . "blog_post_to_category` bpc ON (bp.post_id = bpc.post_id)
			WHERE bpc.category_id = '" . (int)$category_id . "' 
			AND bp.status = '1' 
			AND bp.date_published <= NOW()");

		return $query->row['total'];
	}
}
