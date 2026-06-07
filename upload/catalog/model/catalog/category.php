<?php
class ModelCatalogCategory extends Model {
	public function getCategory($category_id) {
		$cache_key = 'category.' . (int)$category_id . '.' . (int)$this->config->get('config_language_id') . '.' . (int)$this->config->get('config_store_id');
		$category = $this->cache->get($cache_key);

		if (!$category) {
			$query = $this->db->query("SELECT DISTINCT * FROM " . DB_PREFIX . "category c LEFT JOIN " . DB_PREFIX . "category_description cd ON (c.category_id = cd.category_id) LEFT JOIN " . DB_PREFIX . "category_to_store c2s ON (c.category_id = c2s.category_id) WHERE c.category_id = '" . (int)$category_id . "' AND cd.language_id = '" . (int)$this->config->get('config_language_id') . "' AND c2s.store_id = '" . (int)$this->config->get('config_store_id') . "' AND c.status = '1'");
			$category = $query->row;
			$this->cache->set($cache_key, $category, 3600);
		}

		return $category;
	}

	public function getCategories($parent_id = 0) {
		$cache_key = 'category.parent.' . (int)$parent_id . '.' . (int)$this->config->get('config_language_id') . '.' . (int)$this->config->get('config_store_id');
		$category_data = $this->cache->get($cache_key);

		if (!is_array($category_data)) {
			$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "category c LEFT JOIN " . DB_PREFIX . "category_description cd ON (c.category_id = cd.category_id) LEFT JOIN " . DB_PREFIX . "category_to_store c2s ON (c.category_id = c2s.category_id) WHERE c.parent_id = '" . (int)$parent_id . "' AND cd.language_id = '" . (int)$this->config->get('config_language_id') . "' AND c2s.store_id = '" . (int)$this->config->get('config_store_id') . "'  AND c.status = '1' ORDER BY c.sort_order, LCASE(cd.name)");
			$category_data = $query->rows;
			$this->cache->set($cache_key, $category_data, 3600);
		}

		return $category_data;
	}

	public function getCategoryLayoutId($category_id) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "category_to_layout WHERE category_id = '" . (int)$category_id . "' AND store_id = '" . (int)$this->config->get('config_store_id') . "'");

		if ($query->num_rows) {
			return (int)$query->row['layout_id'];
		} else {
			return 0;
		}
	}

	public function getTotalCategoriesByCategoryId($parent_id = 0) {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "category c LEFT JOIN " . DB_PREFIX . "category_to_store c2s ON (c.category_id = c2s.category_id) WHERE c.parent_id = '" . (int)$parent_id . "' AND c2s.store_id = '" . (int)$this->config->get('config_store_id') . "' AND c.status = '1'");

		return $query->row['total'];
	}

	public function getFirstProductImageByCategoryId($category_id) {
		$cache_key = 'category.first_product_image.' . (int)$category_id . '.' . (int)$this->config->get('config_store_id');
		$image = $this->cache->get($cache_key);

		if ($image === false) {
			$query = $this->db->query("SELECT p.image FROM " . DB_PREFIX . "product p LEFT JOIN " . DB_PREFIX . "product_to_category p2c ON (p.product_id = p2c.product_id) LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id) WHERE p2c.category_id = '" . (int)$category_id . "' AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "' AND p.status = '1' AND p.date_available <= NOW() AND p.image != '' ORDER BY p.sort_order ASC, p.product_id ASC LIMIT 1");
			$image = !empty($query->row['image']) ? $query->row['image'] : '';
			$this->cache->set($cache_key, $image, 3600);
		}

		return $image;
	}

	public function getCategoriesByIds(array $category_ids) {
		if (empty($category_ids)) {
			return array();
		}

		$ids = array_map('intval', $category_ids);
		$cache_key = 'category.ids.' . md5(implode(',', $ids)) . '.' . (int)$this->config->get('config_language_id') . '.' . (int)$this->config->get('config_store_id');
		$categories = $this->cache->get($cache_key);

		if (!is_array($categories)) {
			$sql = "SELECT c.category_id, c.parent_id, c.image, c.sort_order, cd.name, cd.description FROM " . DB_PREFIX . "category c LEFT JOIN " . DB_PREFIX . "category_description cd ON (c.category_id = cd.category_id) LEFT JOIN " . DB_PREFIX . "category_to_store c2s ON (c.category_id = c2s.category_id) WHERE c.category_id IN (" . implode(',', $ids) . ") AND cd.language_id = '" . (int)$this->config->get('config_language_id') . "' AND c2s.store_id = '" . (int)$this->config->get('config_store_id') . "' AND c.status = '1' ORDER BY c.sort_order, LCASE(cd.name)";

			$categories = array();
			foreach ($this->db->query($sql)->rows as $row) {
				$categories[(int)$row['category_id']] = $row;
			}

			$this->cache->set($cache_key, $categories, 3600);
		}

		return $categories;
	}

	public function getCategoryProductCounts(array $product_ids) {
		if (empty($product_ids)) {
			return array();
		}

		$ids = array_map('intval', $product_ids);
		$cache_key = 'category.product_counts.' . md5(implode(',', $ids)) . '.' . (int)$this->config->get('config_store_id');
		$counts = $this->cache->get($cache_key);

		if (!is_array($counts)) {
			$sql = "SELECT p2c.category_id, COUNT(DISTINCT p2c.product_id) AS total FROM " . DB_PREFIX . "product_to_category p2c WHERE p2c.product_id IN (" . implode(',', $ids) . ") GROUP BY p2c.category_id";

			$counts = array();
			foreach ($this->db->query($sql)->rows as $row) {
				$counts[(int)$row['category_id']] = (int)$row['total'];
			}

			$this->cache->set($cache_key, $counts, 1800);
		}

		return $counts;
	}
}