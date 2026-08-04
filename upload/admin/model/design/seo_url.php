<?php
class ModelDesignSeoUrl extends Model {
	private const SEO_URL_CACHE_VERSION_KEY = 'dockercart.seo_url_cache.version';

	public function addSeoUrl($data) {
		$this->db->query("INSERT INTO `" . DB_PREFIX . "seo_url` SET store_id = '" . (int)$data['store_id'] . "', language_id = '" . (int)$data['language_id'] . "', query = '" . $this->db->escape($data['query']) . "', keyword = '" . $this->db->escape($data['keyword']) . "'");
		$this->invalidateSeoUrlCache();
	}

	public function editSeoUrl($seo_url_id, $data) {
		$this->db->query("UPDATE `" . DB_PREFIX . "seo_url` SET store_id = '" . (int)$data['store_id'] . "', language_id = '" . (int)$data['language_id'] . "', query = '" . $this->db->escape($data['query']) . "', keyword = '" . $this->db->escape($data['keyword']) . "' WHERE seo_url_id = '" . (int)$seo_url_id . "'");
		$this->invalidateSeoUrlCache();
	}

	public function deleteSeoUrl($seo_url_id) {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "seo_url` WHERE seo_url_id = '" . (int)$seo_url_id . "'");
		$this->invalidateSeoUrlCache();
	}

	public function invalidateSeoUrlCache() {
		$version = (int)$this->cache->get(self::SEO_URL_CACHE_VERSION_KEY);

		if ($version < 1) {
			$version = 1;
		}

		$this->cache->set(self::SEO_URL_CACHE_VERSION_KEY, $version + 1);
	}
	
	public function getSeoUrl($seo_url_id) {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "seo_url` WHERE seo_url_id = '" . (int)$seo_url_id . "'");

		return $query->row;
	}

	public function getSeoUrls($data = array()) {
		$sql = "SELECT *, (SELECT `name` FROM `" . DB_PREFIX . "store` s WHERE s.store_id = su.store_id) AS store, (SELECT `name` FROM `" . DB_PREFIX . "language` l WHERE l.language_id = su.language_id) AS language FROM `" . DB_PREFIX . "seo_url` su";

		$implode = array();

		if (!empty($data['filter_query'])) {
			$implode[] = "`query` LIKE '" . $this->db->escape($data['filter_query']) . "'";
		}
		
		if (!empty($data['filter_keyword'])) {
			$implode[] = "`keyword` LIKE '" . $this->db->escape($data['filter_keyword']) . "'";
		}
		
		if (isset($data['filter_store_id']) && $data['filter_store_id'] !== '') {
			$implode[] = "`store_id` = '" . (int)$data['filter_store_id'] . "'";
		}
				
		if (!empty($data['filter_language_id']) && $data['filter_language_id'] !== '') {
			$implode[] = "`language_id` = '" . (int)$data['filter_language_id'] . "'";
		}
		
		if ($implode) {
			$sql .= " WHERE " . implode(" AND ", $implode);
		}	
		
		$sort_data = array(
			'query',
			'keyword',
			'language_id',
			'store_id'
		);

		if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
			$sql .= " ORDER BY " . $data['sort'];
		} else {
			$sql .= " ORDER BY query";
		}

		if (isset($data['order']) && ($data['order'] == 'DESC')) {
			$sql .= " DESC";
		} else {
			$sql .= " ASC";
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
	 * Fetch SEO URL rows as groups keyed by (query, store_id) so that all
	 * language variants for the same alias collapse into a single list row.
	 * Each group carries a `keywords` map of language_id => array(seo_url_id, keyword).
	 */
	public function getSeoUrlGroups($data = array()) {
		$sql = "SELECT su.query, su.store_id, (SELECT `name` FROM `" . DB_PREFIX . "store` s WHERE s.store_id = su.store_id) AS store FROM `" . DB_PREFIX . "seo_url` su";

		$implode = array();

		if (!empty($data['filter_query'])) {
			$implode[] = "su.`query` LIKE '" . $this->db->escape($data['filter_query']) . "'";
		}

		if (!empty($data['filter_keyword'])) {
			$implode[] = "su.`keyword` LIKE '" . $this->db->escape($data['filter_keyword']) . "'";
		}

		if (isset($data['filter_store_id']) && $data['filter_store_id'] !== '') {
			$implode[] = "su.`store_id` = '" . (int)$data['filter_store_id'] . "'";
		}

		if (!empty($data['filter_language_id']) && $data['filter_language_id'] !== '') {
			$implode[] = "EXISTS (SELECT 1 FROM `" . DB_PREFIX . "seo_url` su2 WHERE su2.`query` = su.`query` AND su2.`store_id` = su.`store_id` AND su2.`language_id` = '" . (int)$data['filter_language_id'] . "')";
		}

		if ($implode) {
			$sql .= " WHERE " . implode(" AND ", $implode);
		}

		$sql .= " GROUP BY su.`query`, su.`store_id`";

		$sort_data = array(
			'query',
			'store_id'
		);

		if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
			$sql .= " ORDER BY su." . $data['sort'];
		} else {
			$sql .= " ORDER BY su.query";
		}

		if (isset($data['order']) && ($data['order'] == 'DESC')) {
			$sql .= " DESC";
		} else {
			$sql .= " ASC";
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

		$groups = $query->rows;

		// Fetch every concrete seo_url row belonging to the page's groups in a
		// single follow-up query and group them by (query, store_id).
		$conditions = array();
		$keywords = array();

		foreach ($groups as $group) {
			$conditions[] = "(su.`query` = '" . $this->db->escape($group['query']) . "' AND su.`store_id` = '" . (int)$group['store_id'] . "')";
		}

		if ($conditions) {
			$keyword_query = $this->db->query("SELECT `seo_url_id`, `query`, `store_id`, `language_id`, `keyword` FROM `" . DB_PREFIX . "seo_url` su WHERE " . implode(" OR ", $conditions));

			foreach ($keyword_query->rows as $row) {
				$key = $row['query'] . '|' . $row['store_id'];
				$keywords[$key][$row['language_id']] = array(
					'seo_url_id' => $row['seo_url_id'],
					'keyword'    => $row['keyword']
				);
			}
		}

		foreach ($groups as &$group) {
			$key = $group['query'] . '|' . $group['store_id'];
			$group['keywords'] = isset($keywords[$key]) ? $keywords[$key] : array();
		}

		return $groups;
	}

	public function getTotalSeoUrlGroups($data = array()) {
		$sql = "SELECT COUNT(*) AS total FROM (SELECT su.query, su.store_id FROM `" . DB_PREFIX . "seo_url` su";

		$implode = array();

		if (!empty($data['filter_query'])) {
			$implode[] = "su.`query` LIKE '" . $this->db->escape($data['filter_query']) . "'";
		}

		if (!empty($data['filter_keyword'])) {
			$implode[] = "su.`keyword` LIKE '" . $this->db->escape($data['filter_keyword']) . "'";
		}

		if (isset($data['filter_store_id']) && $data['filter_store_id'] !== '') {
			$implode[] = "su.`store_id` = '" . (int)$data['filter_store_id'] . "'";
		}

		if (!empty($data['filter_language_id']) && $data['filter_language_id'] !== '') {
			$implode[] = "EXISTS (SELECT 1 FROM `" . DB_PREFIX . "seo_url` su2 WHERE su2.`query` = su.`query` AND su2.`store_id` = su.`store_id` AND su2.`language_id` = '" . (int)$data['filter_language_id'] . "')";
		}

		if ($implode) {
			$sql .= " WHERE " . implode(" AND ", $implode);
		}

		$sql .= " GROUP BY su.`query`, su.`store_id`) AS t";

		$query = $this->db->query($sql);

		return $query->row['total'];
	}

	public function getTotalSeoUrls($data = array()) {
		$sql = "SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "seo_url`";
		
		$implode = array();

		if (!empty($data['filter_query'])) {
			$implode[] = "query LIKE '" . $this->db->escape($data['filter_query']) . "'";
		}
		
		if (!empty($data['filter_keyword'])) {
			$implode[] = "keyword LIKE '" . $this->db->escape($data['filter_keyword']) . "'";
		}
		
		if (!empty($data['filter_store_id']) && $data['filter_store_id'] !== '') {
			$implode[] = "store_id = '" . (int)$data['filter_store_id'] . "'";
		}
				
		if (!empty($data['filter_language_id']) && $data['filter_language_id'] !== '') {
			$implode[] = "language_id = '" . (int)$data['filter_language_id'] . "'";
		}
		
		if ($implode) {
			$sql .= " WHERE " . implode(" AND ", $implode);
		}		
		
		$query = $this->db->query($sql);

		return $query->row['total'];
	}
	
	public function getSeoUrlsByKeyword($keyword, $language_id) {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "seo_url` WHERE keyword = '" . $this->db->escape($keyword) . "' AND language_id = '" . (int)$language_id . "'");

		return $query->rows;
	}	
	
	public function getSeoUrlsByQuery($query, $language_id) {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "seo_url` WHERE query = '" . $this->db->escape($query) . "' AND language_id = '" . (int)$language_id . "'");

		return $query->rows;
	}
	
	public function getSeoUrlsByQueryId($seo_url_id, $query, $language_id) {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "seo_url` WHERE query = '" . $this->db->escape($query) . "' AND language_id = '" . (int)$language_id . "' AND seo_url_id != '" . (int)$seo_url_id . "'");

		return $query->rows;
	}

	public function getSeoUrlsArray($query) {
		$seo_url_data = array();
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "seo_url` WHERE query = '" . $this->db->escape($query) . "'");

		foreach ($query->rows as $result) {
			$seo_url_data[$result['store_id']][$result['language_id']] = $result['keyword'];
		}

		return $seo_url_data;
	}

	public function deleteSeoUrlsByQuery($query) {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "seo_url` WHERE query = '" . $this->db->escape($query) . "'");
		$this->invalidateSeoUrlCache();
	}

	/**
	 * Delete every language variant of one alias group (one query in one store).
	 */
	public function deleteSeoUrlGroup($query, $store_id) {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "seo_url` WHERE query = '" . $this->db->escape($query) . "' AND store_id = '" . (int)$store_id . "'");
		$this->invalidateSeoUrlCache();
	}

	/**
	 * Delete a single language variant of one alias group.
	 */
	public function deleteSeoUrlRecord($query, $store_id, $language_id) {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "seo_url` WHERE query = '" . $this->db->escape($query) . "' AND store_id = '" . (int)$store_id . "' AND language_id = '" . (int)$language_id . "'");
		$this->invalidateSeoUrlCache();
	}

	/**
	 * Find the concrete row for one (query, store_id, language_id) combination.
	 */
	public function getSeoUrlIdByGroup($query, $store_id, $language_id) {
		$query = $this->db->query("SELECT `seo_url_id` FROM `" . DB_PREFIX . "seo_url` WHERE query = '" . $this->db->escape($query) . "' AND store_id = '" . (int)$store_id . "' AND language_id = '" . (int)$language_id . "' LIMIT 1");

		return $query->row ? (int)$query->row['seo_url_id'] : 0;
	}

	public function getSeoUrlsByKeywordId($seo_url_id, $keyword, $language_id) {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "seo_url` WHERE keyword = '" . $this->db->escape($keyword) . "' AND language_id = '" . (int)$language_id . "' AND seo_url_id != '" . (int)$seo_url_id . "'");

		return $query->rows;
	}	
}