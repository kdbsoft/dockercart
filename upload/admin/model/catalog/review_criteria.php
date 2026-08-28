<?php
class ModelCatalogReviewCriteria extends Model {
	public function getDefaultGroupId() {
		$query = $this->db->query("SELECT criteria_group_id FROM " . DB_PREFIX . "review_criteria_group WHERE is_default = '1' ORDER BY criteria_group_id ASC LIMIT 1");

		return $query->num_rows ? (int)$query->row['criteria_group_id'] : 0;
	}

	public function getProductCriteriaGroupId($product_id) {
		$candidate_ids = array();

		$main = $this->db->query("SELECT main_category_id FROM " . DB_PREFIX . "product WHERE product_id = '" . (int)$product_id . "' LIMIT 1");

		if ($main->num_rows && (int)$main->row['main_category_id'] > 0) {
			$candidate_ids[] = (int)$main->row['main_category_id'];
		}

		$categories = $this->db->query("SELECT category_id FROM " . DB_PREFIX . "product_to_category WHERE product_id = '" . (int)$product_id . "'");

		foreach ($categories->rows as $row) {
			$candidate_ids[] = (int)$row['category_id'];
		}

		$candidate_ids = array_values(array_unique(array_filter(array_map('intval', $candidate_ids))));

		if ($candidate_ids) {
			$ids_in = implode(',', $candidate_ids);
			// Prioritize main_category_id first, then others by field order: try bulk fetch and return first match in original order
			$q = $this->db->query("SELECT category_id, review_criteria_group_id FROM " . DB_PREFIX . "category WHERE category_id IN (" . $ids_in . ") AND review_criteria_group_id IS NOT NULL AND review_criteria_group_id > 0");
			$map = array();
			foreach ($q->rows as $row) {
				$map[(int)$row['category_id']] = (int)$row['review_criteria_group_id'];
			}
			foreach ($candidate_ids as $cid) {
				if (isset($map[$cid]) && $map[$cid] > 0) {
					return $map[$cid];
				}
			}
		}

		return $this->getDefaultGroupId();
	}

	/**
	 * Criteria of a group with names for the current admin language.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getCriteria($criteria_group_id) {
		$language_id = (int)$this->config->get('config_language_id');

		$query = $this->db->query("SELECT c.criteria_id, c.type, c.is_required, c.sort_order FROM " . DB_PREFIX . "review_criteria c WHERE c.criteria_group_id = '" . (int)$criteria_group_id . "' ORDER BY c.sort_order ASC, c.criteria_id ASC");

		$criteria = $query->rows;

		if (!$criteria) {
			return $criteria;
		}

		$ids = array_map('intval', array_column($criteria, 'criteria_id'));

		$descriptions = $this->db->query("SELECT criteria_id, language_id, name, help FROM " . DB_PREFIX . "review_criteria_description WHERE criteria_id IN (" . implode(',', $ids) . ") ORDER BY language_id ASC");

		$by_id = array();

		foreach ($descriptions->rows as $row) {
			$criteria_id = (int)$row['criteria_id'];

			if (!isset($by_id[$criteria_id])) {
				$by_id[$criteria_id] = array();
			}

			$by_id[$criteria_id][(int)$row['language_id']] = array(
				'name' => (string)$row['name'],
				'help' => (string)$row['help'],
			);
		}

		foreach ($criteria as &$item) {
			$criteria_id = (int)$item['criteria_id'];

			if (isset($by_id[$criteria_id][$language_id])) {
				$item['name'] = $by_id[$criteria_id][$language_id]['name'];
				$item['help'] = $by_id[$criteria_id][$language_id]['help'];
			} elseif (!empty($by_id[$criteria_id])) {
				$fallback = reset($by_id[$criteria_id]);
				$item['name'] = $fallback['name'];
				$item['help'] = $fallback['help'];
			} else {
				$item['name'] = '';
				$item['help'] = '';
			}
		}

		unset($item);

		return $criteria;
	}

	/**
	 * All groups with localized names.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getGroups() {
		$query = $this->db->query("SELECT g.criteria_group_id, g.is_default, g.sort_order FROM " . DB_PREFIX . "review_criteria_group g ORDER BY g.is_default DESC, g.sort_order ASC, g.criteria_group_id ASC");

		$language_id = (int)$this->config->get('config_language_id');

		$groups = array();

		if ($query->num_rows) {
			$group_ids = array_map('intval', array_column($query->rows, 'criteria_group_id'));
			$desc_q = $this->db->query("SELECT criteria_group_id, language_id, name FROM " . DB_PREFIX . "review_criteria_group_description WHERE criteria_group_id IN (" . implode(',', $group_ids) . ")");
			$desc_map = array();
			foreach ($desc_q->rows as $drow) {
				$gid = (int)$drow['criteria_group_id'];
				$lid = (int)$drow['language_id'];
				$desc_map[$gid][$lid] = $drow['name'];
			}

			foreach ($query->rows as $row) {
				$group_id = (int)$row['criteria_group_id'];

				$name = '';
				if (isset($desc_map[$group_id][$language_id])) {
					$name = $desc_map[$group_id][$language_id];
				} elseif (isset($desc_map[$group_id])) {
					$name = reset($desc_map[$group_id]);
				}

				$groups[] = array(
					'criteria_group_id' => $group_id,
					'name'              => $name,
					'is_default'        => (int)$row['is_default'],
					'sort_order'        => (int)$row['sort_order'],
					'criteria_count'    => 0,
				);
			}
		}

		if ($groups) {
			$ids = array_map(function ($group) {
				return $group['criteria_group_id'];
			}, $groups);

			$counts = $this->db->query("SELECT criteria_group_id, COUNT(*) AS total FROM " . DB_PREFIX . "review_criteria WHERE criteria_group_id IN (" . implode(',', $ids) . ") GROUP BY criteria_group_id");

			$by_id = array();

			foreach ($counts->rows as $row) {
				$by_id[(int)$row['criteria_group_id']] = (int)$row['total'];
			}

			foreach ($groups as &$group) {
				$group['criteria_count'] = isset($by_id[$group['criteria_group_id']]) ? $by_id[$group['criteria_group_id']] : 0;
			}

			unset($group);
		}

		return $groups;
	}

	public function addGroup($data) {
		$this->db->query("INSERT INTO " . DB_PREFIX . "review_criteria_group SET is_default = '0', sort_order = '" . (int)$data['sort_order'] . "'");

		$group_id = $this->db->getLastId();

		$this->saveGroupDescriptions($group_id, $data);
		$this->saveCriteria($group_id, $data);

		return $group_id;
	}

	public function editGroup($group_id, $data) {
		$this->db->query("UPDATE " . DB_PREFIX . "review_criteria_group SET sort_order = '" . (int)$data['sort_order'] . "' WHERE criteria_group_id = '" . (int)$group_id . "'");

		$this->saveGroupDescriptions($group_id, $data);
		$this->saveCriteria($group_id, $data);
	}

	public function deleteGroup($group_id) {
		$group_id = (int)$group_id;

		$criteria = $this->db->query("SELECT criteria_id FROM " . DB_PREFIX . "review_criteria WHERE criteria_group_id = '" . $group_id . "'");

		foreach ($criteria->rows as $row) {
			$this->db->query("DELETE FROM " . DB_PREFIX . "review_criteria_value WHERE criteria_id = '" . (int)$row['criteria_id'] . "'");
			$this->db->query("DELETE FROM " . DB_PREFIX . "review_criteria_description WHERE criteria_id = '" . (int)$row['criteria_id'] . "'");
			$this->db->query("DELETE FROM " . DB_PREFIX . "review_criteria WHERE criteria_id = '" . (int)$row['criteria_id'] . "'");
		}

		$this->db->query("UPDATE " . DB_PREFIX . "category SET review_criteria_group_id = NULL WHERE review_criteria_group_id = '" . $group_id . "'");
		$this->db->query("DELETE FROM " . DB_PREFIX . "review_criteria_group_description WHERE criteria_group_id = '" . $group_id . "'");
		$this->db->query("DELETE FROM " . DB_PREFIX . "review_criteria_group WHERE criteria_group_id = '" . $group_id . "'");
	}

	/**
	 * @return array{criteria_group_id: int, is_default: int, sort_order: int, name: string, criteria: array<int, array<string, mixed>>}|array
	 */
	public function getGroup($group_id) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "review_criteria_group WHERE criteria_group_id = '" . (int)$group_id . "'");

		if (!$query->num_rows) {
			return array();
		}

		$group = $query->row;

		$name_query = $this->db->query("SELECT language_id, name FROM " . DB_PREFIX . "review_criteria_group_description WHERE criteria_group_id = '" . (int)$group_id . "'");

		$group['names'] = array();

		foreach ($name_query->rows as $row) {
			$group['names'][(int)$row['language_id']] = $row['name'];
		}

		$criteria_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "review_criteria WHERE criteria_group_id = '" . (int)$group_id . "' ORDER BY sort_order ASC, criteria_id ASC");

		$criteria = $criteria_query->rows;

		$criteria_ids = array_map('intval', array_column($criteria, 'criteria_id'));

		$group['criteria'] = array();

		if ($criteria_ids) {
			$desc_query = $this->db->query("SELECT criteria_id, language_id, name, help FROM " . DB_PREFIX . "review_criteria_description WHERE criteria_id IN (" . implode(',', $criteria_ids) . ")");

			$descriptions = array();

			foreach ($desc_query->rows as $row) {
				$descriptions[(int)$row['criteria_id']][(int)$row['language_id']] = array(
					'name' => $row['name'],
					'help' => $row['help'],
				);
			}

			foreach ($criteria as $item) {
				$item['names'] = isset($descriptions[(int)$item['criteria_id']]) ? $descriptions[(int)$item['criteria_id']] : array();
				$group['criteria'][] = $item;
			}
		}

		return $group;
	}

	public function setDefaultGroup($group_id) {
		$group_id = (int)$group_id;

		if ($group_id <= 0) {
			return;
		}

		$this->db->query("UPDATE " . DB_PREFIX . "review_criteria_group SET is_default = '0'");

		$this->db->query("UPDATE " . DB_PREFIX . "review_criteria_group SET is_default = '1' WHERE criteria_group_id = '" . $group_id . "'");
	}

	private function saveGroupDescriptions($group_id, $data) {
		$this->db->query("DELETE FROM " . DB_PREFIX . "review_criteria_group_description WHERE criteria_group_id = '" . (int)$group_id . "'");

		if (empty($data['group_name']) || !is_array($data['group_name'])) {
			return;
		}

		foreach ($data['group_name'] as $language_id => $name) {
			if (trim((string)$name) === '') {
				continue;
			}

			$this->db->query("INSERT INTO " . DB_PREFIX . "review_criteria_group_description SET criteria_group_id = '" . (int)$group_id . "', language_id = '" . (int)$language_id . "', name = '" . $this->db->escape($name) . "'");
		}
	}

	private function saveCriteria($group_id, $data) {
		$existing = $this->db->query("SELECT criteria_id FROM " . DB_PREFIX . "review_criteria WHERE criteria_group_id = '" . (int)$group_id . "'");

		$existing_ids = array_map('intval', array_column($existing->rows, 'criteria_id'));

		$posted = array();

		if (!empty($data['criteria_name']) && is_array($data['criteria_name'])) {
			$sort_order = 0;

			foreach ($data['criteria_name'] as $row_index => $names_by_language) {
				$type = isset($data['criteria_type'][$row_index]) ? $data['criteria_type'][$row_index] : 'text';
				$is_required = isset($data['criteria_required'][$row_index]) ? (int)$data['criteria_required'][$row_index] : 0;
				$criteria_id = isset($data['criteria_id'][$row_index]) ? (int)$data['criteria_id'][$row_index] : 0;

				$has_name = false;

				if (is_array($names_by_language)) {
					foreach ($names_by_language as $name) {
						if (trim((string)$name) !== '') {
							$has_name = true;
							break;
						}
					}
				}

				if (!$has_name) {
					continue;
				}

				$type = in_array($type, array('rating', 'text'), true) ? $type : 'text';

				if ($criteria_id > 0) {
					$this->db->query("UPDATE " . DB_PREFIX . "review_criteria SET type = '" . $this->db->escape($type) . "', is_required = '" . $is_required . "', sort_order = '" . (int)$sort_order . "' WHERE criteria_id = '" . $criteria_id . "'");
				} else {
					$this->db->query("INSERT INTO " . DB_PREFIX . "review_criteria SET criteria_group_id = '" . (int)$group_id . "', type = '" . $this->db->escape($type) . "', is_required = '" . $is_required . "', sort_order = '" . (int)$sort_order . "'");

					$criteria_id = $this->db->getLastId();
				}

				$this->saveCriteriaDescriptions($criteria_id, $names_by_language, isset($data['criteria_help'][$row_index]) ? $data['criteria_help'][$row_index] : array());

				$posted[] = $criteria_id;
				$sort_order++;
			}
		}

		foreach ($existing_ids as $criteria_id) {
			if (!in_array($criteria_id, $posted, true)) {
				$this->db->query("DELETE FROM " . DB_PREFIX . "review_criteria_value WHERE criteria_id = '" . $criteria_id . "'");
				$this->db->query("DELETE FROM " . DB_PREFIX . "review_criteria_description WHERE criteria_id = '" . $criteria_id . "'");
				$this->db->query("DELETE FROM " . DB_PREFIX . "review_criteria WHERE criteria_id = '" . $criteria_id . "'");
			}
		}
	}

	private function saveCriteriaDescriptions($criteria_id, $names_by_language, $help_by_language) {
		$this->db->query("DELETE FROM " . DB_PREFIX . "review_criteria_description WHERE criteria_id = '" . (int)$criteria_id . "'");

		if (!is_array($names_by_language)) {
			return;
		}

		foreach ($names_by_language as $language_id => $name) {
			if (trim((string)$name) === '') {
				continue;
			}

			$help = isset($help_by_language[$language_id]) ? (string)$help_by_language[$language_id] : '';

			$this->db->query("INSERT INTO " . DB_PREFIX . "review_criteria_description SET criteria_id = '" . (int)$criteria_id . "', language_id = '" . (int)$language_id . "', name = '" . $this->db->escape($name) . "', help = '" . $this->db->escape($help) . "'");
		}
	}
}
