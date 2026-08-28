<?php
class ModelAccountCustomField extends Model {
	public function getCustomField($custom_field_id) {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "custom_field` cf LEFT JOIN `" . DB_PREFIX . "custom_field_description` cfd ON (cf.custom_field_id = cfd.custom_field_id) WHERE cf.status = '1' AND cf.custom_field_id = '" . (int)$custom_field_id . "' AND cfd.language_id = '" . (int)$this->config->get('config_language_id') . "'");

		return $query->row;
	}

	public function getCustomFields($customer_group_id = 0) {
		$custom_field_data = array();

		if (!$customer_group_id) {
			$custom_field_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "custom_field` cf LEFT JOIN `" . DB_PREFIX . "custom_field_description` cfd ON (cf.custom_field_id = cfd.custom_field_id) WHERE cf.status = '1' AND cfd.language_id = '" . (int)$this->config->get('config_language_id') . "' AND cf.status = '1' ORDER BY cf.sort_order ASC");
		} else {
			$custom_field_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "custom_field_customer_group` cfcg LEFT JOIN `" . DB_PREFIX . "custom_field` cf ON (cfcg.custom_field_id = cf.custom_field_id) LEFT JOIN `" . DB_PREFIX . "custom_field_description` cfd ON (cf.custom_field_id = cfd.custom_field_id) WHERE cf.status = '1' AND cfd.language_id = '" . (int)$this->config->get('config_language_id') . "' AND cfcg.customer_group_id = '" . (int)$customer_group_id . "' ORDER BY cf.sort_order ASC");
		}

		$custom_field_ids = array();
		foreach ($custom_field_query->rows as $cf) {
			if ($cf['type'] == 'select' || $cf['type'] == 'radio' || $cf['type'] == 'checkbox') {
				$custom_field_ids[] = (int)$cf['custom_field_id'];
			}
		}

		$custom_field_values_map = array();
		if ($custom_field_ids) {
			$ids_in = implode(',', array_unique($custom_field_ids));
			$value_query = $this->db->query("SELECT cfv.custom_field_id, cfv.custom_field_value_id, cfvd.name, cfv.sort_order FROM " . DB_PREFIX . "custom_field_value cfv LEFT JOIN " . DB_PREFIX . "custom_field_value_description cfvd ON (cfv.custom_field_value_id = cfvd.custom_field_value_id) WHERE cfv.custom_field_id IN (" . $ids_in . ") AND cfvd.language_id = '" . (int)$this->config->get('config_language_id') . "' ORDER BY cfv.custom_field_id ASC, cfv.sort_order ASC");
			foreach ($value_query->rows as $row) {
				$cid = (int)$row['custom_field_id'];
				$custom_field_values_map[$cid][] = array(
					'custom_field_value_id' => $row['custom_field_value_id'],
					'name'                  => $row['name']
				);
			}
		}

		foreach ($custom_field_query->rows as $custom_field) {
			if ($custom_field['type'] == 'select' || $custom_field['type'] == 'radio' || $custom_field['type'] == 'checkbox') {
				$custom_field_value_data = isset($custom_field_values_map[(int)$custom_field['custom_field_id']]) ? $custom_field_values_map[(int)$custom_field['custom_field_id']] : array();
			} else {
				$custom_field_value_data = array();
			}

			$custom_field_data[] = array(
				'custom_field_id'    => $custom_field['custom_field_id'],
				'custom_field_value' => $custom_field_value_data,
				'name'               => $custom_field['name'],
				'type'               => $custom_field['type'],
				'value'              => $custom_field['value'],
				'validation'         => $custom_field['validation'],
				'location'           => $custom_field['location'],
				'required'           => empty($custom_field['required']) || $custom_field['required'] == 0 ? false : true,
				'sort_order'         => $custom_field['sort_order']
			);
		}

		return $custom_field_data;
	}
}