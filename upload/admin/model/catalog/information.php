<?php
class ModelCatalogInformation extends Model {
	public function addInformation($data) {
		$this->db->query("INSERT INTO " . DB_PREFIX . "information SET sort_order = '" . (int)$data['sort_order'] . "', bottom = '" . (isset($data['bottom']) ? (int)$data['bottom'] : 0) . "', top = '" . (isset($data['top']) ? (int)$data['top'] : 0) . "', full_width = '" . (isset($data['full_width']) ? (int)$data['full_width'] : 0) . "', use_builder = '" . (isset($data['use_builder']) ? (int)$data['use_builder'] : 0) . "', status = '" . (int)$data['status'] . "'");

		$information_id = $this->db->getLastId();

		foreach ($data['information_description'] as $language_id => $value) {
			$this->db->query("INSERT INTO " . DB_PREFIX . "information_description SET information_id = '" . (int)$information_id . "', language_id = '" . (int)$language_id . "', title = '" . $this->db->escape($value['title']) . "', description = '" . $this->db->escape($value['description']) . "', meta_title = '" . $this->db->escape($value['meta_title']) . "', meta_description = '" . $this->db->escape($value['meta_description']) . "', meta_keyword = '" . $this->db->escape($value['meta_keyword']) . "'");
		}

		if (isset($data['information_store'])) {
			foreach ($data['information_store'] as $store_id) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "information_to_store SET information_id = '" . (int)$information_id . "', store_id = '" . (int)$store_id . "'");
			}
		}

		// SEO URL
		if (isset($data['information_seo_url'])) {
			$seo_url_updated = false;
			foreach ($data['information_seo_url'] as $store_id => $language) {
				foreach ($language as $language_id => $keyword) {
					if (!empty($keyword)) {
						$this->db->query("INSERT INTO " . DB_PREFIX . "seo_url SET store_id = '" . (int)$store_id . "', language_id = '" . (int)$language_id . "', query = 'information_id=" . (int)$information_id . "', keyword = '" . $this->db->escape($keyword) . "'");
						$seo_url_updated = true;
					}
				}
			}

			if ($seo_url_updated) {
				$this->load->model('design/seo_url');
				$this->model_design_seo_url->invalidateSeoUrlCache();
			}
		}
		
		if (isset($data['information_layout'])) {
			foreach ($data['information_layout'] as $store_id => $layout_id) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "information_to_layout SET information_id = '" . (int)$information_id . "', store_id = '" . (int)$store_id . "', layout_id = '" . (int)$layout_id . "'");
			}
		}

		$this->cache->delete('information');

		return $information_id;
	}

	public function editInformation($information_id, $data) {
		$this->db->query("UPDATE " . DB_PREFIX . "information SET sort_order = '" . (int)$data['sort_order'] . "', bottom = '" . (isset($data['bottom']) ? (int)$data['bottom'] : 0) . "', top = '" . (isset($data['top']) ? (int)$data['top'] : 0) . "', full_width = '" . (isset($data['full_width']) ? (int)$data['full_width'] : 0) . "', use_builder = '" . (isset($data['use_builder']) ? (int)$data['use_builder'] : 0) . "', status = '" . (int)$data['status'] . "' WHERE information_id = '" . (int)$information_id . "'");

		$this->db->query("DELETE FROM " . DB_PREFIX . "information_description WHERE information_id = '" . (int)$information_id . "'");

		foreach ($data['information_description'] as $language_id => $value) {
			$this->db->query("INSERT INTO " . DB_PREFIX . "information_description SET information_id = '" . (int)$information_id . "', language_id = '" . (int)$language_id . "', title = '" . $this->db->escape($value['title']) . "', description = '" . $this->db->escape($value['description']) . "', meta_title = '" . $this->db->escape($value['meta_title']) . "', meta_description = '" . $this->db->escape($value['meta_description']) . "', meta_keyword = '" . $this->db->escape($value['meta_keyword']) . "'");
		}

		$this->db->query("DELETE FROM " . DB_PREFIX . "information_to_store WHERE information_id = '" . (int)$information_id . "'");

		if (isset($data['information_store'])) {
			foreach ($data['information_store'] as $store_id) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "information_to_store SET information_id = '" . (int)$information_id . "', store_id = '" . (int)$store_id . "'");
			}
		}

		$this->db->query("DELETE FROM " . DB_PREFIX . "seo_url WHERE query = 'information_id=" . (int)$information_id . "'");
		$seo_url_updated = true;

		if (isset($data['information_seo_url'])) {
			foreach ($data['information_seo_url'] as $store_id => $language) {
				foreach ($language as $language_id => $keyword) {
					if (trim($keyword)) {
						$this->db->query("INSERT INTO `" . DB_PREFIX . "seo_url` SET store_id = '" . (int)$store_id . "', language_id = '" . (int)$language_id . "', query = 'information_id=" . (int)$information_id . "', keyword = '" . $this->db->escape($keyword) . "'");
					}
				}
			}
		}

		if ($seo_url_updated) {
			$this->load->model('design/seo_url');
			$this->model_design_seo_url->invalidateSeoUrlCache();
		}

		$this->db->query("DELETE FROM `" . DB_PREFIX . "information_to_layout` WHERE information_id = '" . (int)$information_id . "'");

		if (isset($data['information_layout'])) {
			foreach ($data['information_layout'] as $store_id => $layout_id) {
				$this->db->query("INSERT INTO `" . DB_PREFIX . "information_to_layout` SET information_id = '" . (int)$information_id . "', store_id = '" . (int)$store_id . "', layout_id = '" . (int)$layout_id . "'");
			}
		}

		$this->cache->delete('information');
	}

	public function deleteInformation($information_id) {
		// Cascade the owned landing (its composed blocks) along with the page.
		$landing_id = $this->getBuilderLandingId($information_id);

		if ($landing_id) {
			$this->load->model('design/landing');

			$this->model_design_landing->deleteLanding($landing_id);
		}

		$this->db->query("DELETE FROM `" . DB_PREFIX . "information` WHERE information_id = '" . (int)$information_id . "'");
		$this->db->query("DELETE FROM `" . DB_PREFIX . "information_description` WHERE information_id = '" . (int)$information_id . "'");
		$this->db->query("DELETE FROM `" . DB_PREFIX . "information_to_store` WHERE information_id = '" . (int)$information_id . "'");
		$this->db->query("DELETE FROM `" . DB_PREFIX . "information_to_layout` WHERE information_id = '" . (int)$information_id . "'");
		$this->db->query("DELETE FROM `" . DB_PREFIX . "seo_url` WHERE query = 'information_id=" . (int)$information_id . "'");

		$this->load->model('design/seo_url');
		$this->model_design_seo_url->invalidateSeoUrlCache();

		$this->cache->delete('information');
	}

	public function copyInformation($information_id)
	{
		$query = $this->db->query(
			"SELECT * FROM " .
				DB_PREFIX .
				"information WHERE information_id = '" .
				(int) $information_id .
				"'",
		);

		if (!$query->num_rows) {
			return false;
		}

		$information = $query->row;

		$data = [];

		$data["bottom"] = $information["bottom"];
		$data["top"] = $information["top"];
		$data["full_width"] = $information["full_width"];
		$data["sort_order"] = $information["sort_order"];
		$data["status"] = $information["status"];
		$data["information_description"] = $this->getInformationDescriptions(
			$information_id,
		);
		$data["information_store"] = $this->getInformationStores(
			$information_id,
		);

		// Make title unique for default language
		$default_language_id = (int) $this->config->get("config_language_id");

		if (
			isset(
				$data["information_description"][$default_language_id]["title"],
			)
		) {
			$data["information_description"][$default_language_id][
				"title"
			] = $this->getUniqueCopyName(
				$data["information_description"][$default_language_id]["title"],
				DB_PREFIX . "information_description",
				"title",
			);
		}

		// Make SEO URL keywords unique
		$seo_urls = $this->getInformationSeoUrls($information_id);

		foreach ($seo_urls as $store_id => &$languages) {
			foreach ($languages as $language_id => &$keyword) {
				$keyword = $this->getUniqueCopyName(
					$keyword,
					DB_PREFIX . "seo_url",
					"keyword",
				);
			}
		}
		unset($languages, $keyword);

		$data["information_seo_url"] = $seo_urls;
		$data["information_layout"] = $this->getInformationLayouts(
			$information_id,
		);

		$data["use_builder"] = (int) ($information["use_builder"] ?? 0);

		$new_information_id = $this->addInformation($data);

		// A page composed in the builder carries its sections over to the copy.
		$source_landing_id = $this->getBuilderLandingId($information_id);

		if ($source_landing_id) {
			$this->load->model("design/landing");

			$this->model_design_landing->duplicateLanding(
				$source_landing_id,
				(int) $new_information_id,
			);
		}

		return $new_information_id;
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

	public function getInformation($information_id) {
		$query = $this->db->query("SELECT DISTINCT * FROM " . DB_PREFIX . "information WHERE information_id = '" . (int)$information_id . "'");

		return $query->row;
	}

	public function getInformations($data = array()) {
		if ($data) {
			$sql = "SELECT * FROM " . DB_PREFIX . "information i LEFT JOIN " . DB_PREFIX . "information_description id ON (i.information_id = id.information_id) WHERE id.language_id = '" . (int)$this->config->get('config_language_id') . "'";

			if (!empty($data['filter_name'])) {
				$sql .= " AND id.title LIKE '%" . $this->db->escape($data['filter_name']) . "%'";
			}

		$sort_data = array(
			'id.title',
			'i.sort_order',
			'i.status'
		);

			if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
				$sql .= " ORDER BY " . $data['sort'];
			} else {
				$sql .= " ORDER BY id.title";
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
		} else {
			$information_data = $this->cache->get('information.' . (int)$this->config->get('config_language_id'));

			if (!$information_data) {
				$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "information i LEFT JOIN " . DB_PREFIX . "information_description id ON (i.information_id = id.information_id) WHERE id.language_id = '" . (int)$this->config->get('config_language_id') . "' ORDER BY id.title");

				$information_data = $query->rows;

				$this->cache->set('information.' . (int)$this->config->get('config_language_id'), $information_data);
			}

			return $information_data;
		}
	}

	public function getInformationDescriptions($information_id) {
		$information_description_data = array();

		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "information_description WHERE information_id = '" . (int)$information_id . "'");

		foreach ($query->rows as $result) {
			$information_description_data[$result['language_id']] = array(
				'title'            => $result['title'],
				'description'      => $result['description'],
				'meta_title'       => $result['meta_title'],
				'meta_description' => $result['meta_description'],
				'meta_keyword'     => $result['meta_keyword']
			);
		}

		return $information_description_data;
	}

	public function getInformationStores($information_id) {
		$information_store_data = array();

		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "information_to_store WHERE information_id = '" . (int)$information_id . "'");

		foreach ($query->rows as $result) {
			$information_store_data[] = $result['store_id'];
		}

		return $information_store_data;
	}

	public function getInformationSeoUrls($information_id) {
		$information_seo_url_data = array();
		
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "seo_url WHERE query = 'information_id=" . (int)$information_id . "'");

		foreach ($query->rows as $result) {
			$information_seo_url_data[$result['store_id']][$result['language_id']] = $result['keyword'];
		}

		return $information_seo_url_data;
	}

	public function getInformationLayouts($information_id) {
		$information_layout_data = array();

		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "information_to_layout WHERE information_id = '" . (int)$information_id . "'");

		foreach ($query->rows as $result) {
			$information_layout_data[$result['store_id']] = $result['layout_id'];
		}

		return $information_layout_data;
	}

	public function getTotalInformations() {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "information");

		return $query->row['total'];
	}

	public function getTotalInformationsByLayoutId($layout_id) {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "information_to_layout WHERE layout_id = '" . (int)$layout_id . "'");

		return $query->row['total'];
	}

	/**
	 * Partial settings save for the landing Page Builder (owned landings).
	 *
	 * Writes visibility, translated meta tags and the default-store SEO
	 * keyword in one place so the builder Page Settings can edit them
	 * without opening Catalog > Information. Mirrors the information form
	 * encoding exactly (Request::clean: trim + htmlspecialchars) so JSON
	 * saves stay byte-identical to form saves.
	 *
	 * @param array<int, array<string, string>> $descriptions meta fields per language
	 */
	public function updateBuilderSettings(int $information_id, int $status, array $descriptions): void {
		$this->db->query("UPDATE `" . DB_PREFIX . "information` SET status = '" . ($status ? 1 : 0) . "' WHERE information_id = '" . (int)$information_id . "'");

		foreach ($descriptions as $language_id => $row) {
			if (!is_array($row)) {
				continue;
			}

			$sets = [];

			foreach (['meta_title', 'meta_description', 'meta_keyword'] as $key) {
				if (array_key_exists($key, $row)) {
					$sets[] = "`" . $key . "` = '" . $this->db->escape($this->builderClean((string)$row[$key])) . "'";
				}
			}

			if ($sets) {
				$this->db->query("UPDATE `" . DB_PREFIX . "information_description` SET " . implode(', ', $sets) . " WHERE information_id = '" . (int)$information_id . "' AND language_id = '" . (int)$language_id . "'");
			}
		}

		$this->cache->delete('information');
	}

	/**
	 * Replaces the SEO keywords of one store (the builder edits the default
	 * store only; other stores keep their keywords).
	 *
	 * @param array<int, string> $keywords keyword per language
	 */
	public function updateBuilderSeoUrls(int $information_id, int $store_id, array $keywords): void {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "seo_url` WHERE query = 'information_id=" . (int)$information_id . "' AND store_id = '" . (int)$store_id . "'");

		foreach ($keywords as $language_id => $keyword) {
			$keyword = $this->builderClean((string)$keyword);

			if ($keyword === '') {
				continue;
			}

			$this->db->query("INSERT INTO `" . DB_PREFIX . "seo_url` SET store_id = '" . (int)$store_id . "', language_id = '" . (int)$language_id . "', query = 'information_id=" . (int)$information_id . "', keyword = '" . $this->db->escape($keyword) . "'");
		}

		$this->load->model('design/seo_url');
		$this->model_design_seo_url->invalidateSeoUrlCache();

		$this->cache->delete('information');
	}

	/**
	 * Request::clean() without a request: trim + htmlspecialchars, exactly
	 * what the information form stores.
	 */
	private function builderClean(string $value): string {
		return trim(htmlspecialchars($value, ENT_COMPAT, 'UTF-8'));
	}

	public function updateInformationField($information_id, $data) {
		$int_fields = array('sort_order', 'status');

		$sets = array();
		foreach ($int_fields as $field) {
			if (isset($data[$field])) {
				$sets[] = "`" . $field . "` = '" . (int)$data[$field] . "'";
			}
		}

		if (!empty($sets)) {
			$this->db->query("UPDATE " . DB_PREFIX . "information SET " . implode(', ', $sets) . " WHERE information_id = '" . (int)$information_id . "'");
		}
	}

	public function updateInformationNames($information_id, $names) {
		foreach ($names as $language_id => $title) {
			$title = trim((string)$title);

			$this->db->query("UPDATE " . DB_PREFIX . "information_description SET title = '" . $this->db->escape($title) . "' WHERE information_id = '" . (int)$information_id . "' AND language_id = '" . (int)$language_id . "'");
		}

		// The storefront caches information rows per language (information.item.*);
		// without this the updated title shows up to an hour late.
		$this->cache->delete('information');
	}

	/**
	 * Instant mode switch (mirrors catalog/product_configurable::setMode).
	 *
	 * Only flips the use_builder flag — turning the builder off keeps the
	 * owned landing intact so it acts as the fallback when re-enabled.
	 */
	public function setBuilderMode($information_id, $use_builder) {
		$this->db->query("UPDATE " . DB_PREFIX . "information SET use_builder = '" . (int)(bool)$use_builder . "' WHERE information_id = '" . (int)$information_id . "'");
	}

	/**
	 * The landing page bound to this information page (0 when the page is
	 * composed in the classic editor or has not been converted yet).
	 */
	public function getBuilderLandingId($information_id) {
		$query = $this->db->query("SELECT landing_id FROM `" . DB_PREFIX . "landing` WHERE information_id = '" . (int)$information_id . "' LIMIT 1");

		return $query->num_rows ? (int)$query->row['landing_id'] : 0;
	}

	/**
	 * Enables builder mode: creates the owned landing on first use and seeds it
	 * with the classic description so nothing is lost (one text block per
	 * language, heading = page title, body = the WYSIWYG copy).
	 *
	 * The classic description itself stays intact: agree() (the checkout terms
	 * checkbox) keeps rendering it and it acts as the fallback when the
	 * builder toggle is switched off again.
	 *
	 * @return int the owned landing_id
	 */
	public function enableBuilder($information_id) {
		$information_id = (int)$information_id;

		$landing_id = $this->getBuilderLandingId($information_id);

		if ($landing_id) {
			return $landing_id;
		}

		$descriptions = $this->getInformationDescriptions($information_id);
		$default_language_id = (int)$this->config->get('config_language_id');

		$default = [];

		if (isset($descriptions[$default_language_id])) {
			$default = $descriptions[$default_language_id];
		} elseif ($descriptions) {
			$default = reset($descriptions);
		}

		$name = trim((string)($default['meta_title'] ?? ''));

		if ($name === '') {
			$name = trim((string)($default['title'] ?? ''));
		}

		if ($name === '') {
			$name = 'Information page';
		}

		$blocks = [];

		if ($descriptions) {
			$content = [];

			foreach ($descriptions as $language_id => $description) {
				$content[$language_id] = [
					'heading' => (string)$description['title'],
					'body'    => (string)$description['description'],
				];
			}

			$blocks[] = [
				'block_id' => 0,
				'code'     => 'text',
				'status'   => 1,
				'settings' => [],
				'content'  => $content,
			];
		}

		$this->load->model('design/landing');

		// Owned landing: status/schedule/slugs are governed by the information
		// page itself, the storefront chrome is always the full store theme.
		return $this->model_design_landing->addLanding([
			'name'           => $name,
			'information_id' => $information_id,
			'status'         => 0,
			'chrome'         => 'full',
			'noindex'        => 0,
			'custom_css'     => '',
			'descriptions'   => [],
			'blocks'         => $blocks,
		]);
	}
}