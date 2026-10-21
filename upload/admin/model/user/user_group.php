<?php
class ModelUserUserGroup extends Model {
	public function addUserGroup($data) {
		$template_code = isset($data['template_code']) && $data['template_code'] !== '' ? "'" . $this->db->escape($data['template_code']) . "'" : 'NULL';

		$this->db->query("INSERT INTO " . DB_PREFIX . "user_group SET name = '" . $this->db->escape($data['name']) . "', template_code = " . $template_code . ", permission = '" . (isset($data['permission']) ? $this->db->escape(json_encode($data['permission'])) : '') . "'");
	
		return $this->db->getLastId();
	}

	public function editUserGroup($user_group_id, $data) {
		$sql = "UPDATE " . DB_PREFIX . "user_group SET name = '" . $this->db->escape($data['name']) . "'";

		// A missing "permission" key means "keep the stored document" (the
		// Administrator group is a superuser and its form has no picker).
		if (isset($data['permission'])) {
			$sql .= ", permission = '" . $this->db->escape(json_encode($data['permission'])) . "'";
		}

		$sql .= " WHERE user_group_id = '" . (int)$user_group_id . "'";

		$this->db->query($sql);
	}

	public function deleteUserGroup($user_group_id) {
		$this->db->query("DELETE FROM " . DB_PREFIX . "user_group WHERE user_group_id = '" . (int)$user_group_id . "'");
	}

	public function getUserGroup($user_group_id) {
		$query = $this->db->query("SELECT DISTINCT * FROM " . DB_PREFIX . "user_group WHERE user_group_id = '" . (int)$user_group_id . "'");

		$user_group = array(
			'name'          => $query->row['name'],
			'template_code' => isset($query->row['template_code']) ? (string)$query->row['template_code'] : '',
			'permission'    => json_decode($query->row['permission'], true)
		);

		return $user_group;
	}

	/**
	 * Returns the user group of the given template code (0 rows if none).
	 */
	public function getUserGroupByTemplateCode(string $template_code) {
		$query = $this->db->query("SELECT DISTINCT * FROM " . DB_PREFIX . "user_group WHERE template_code = '" . $this->db->escape($template_code) . "'");

		return $query->row;
	}

	/**
	 * Creates the standard role groups that do not exist yet.
	 *
	 * Existing groups are detected by `template_code`, so renaming a group does
	 * not resurrect a duplicate. Idempotent: a second run creates nothing.
	 *
	 * @param array<string, array{name: string, permission: array}> $templates code => resolved template
	 * @return array{created: string[], existing: string[]} group names
	 */
	public function installTemplates(array $templates): array {
		$created = array();
		$existing = array();

		foreach ($templates as $code => $template) {
			if ($this->getUserGroupByTemplateCode($code)) {
				$existing[] = $template['name'];

				continue;
			}

			$this->addUserGroup(array(
				'name'          => $template['name'],
				'template_code' => $code,
				'permission'    => $template['permission']
			));

			$created[] = $template['name'];
		}

		return array(
			'created'  => $created,
			'existing' => $existing
		);
	}

	public function getUserGroups($data = array()) {
		$sql = "SELECT * FROM " . DB_PREFIX . "user_group";

		$sql .= " ORDER BY name";

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

	public function getTotalUserGroups() {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "user_group");

		return $query->row['total'];
	}

	public function addPermission($user_group_id, $type, $route) {
		$user_group_query = $this->db->query("SELECT DISTINCT * FROM " . DB_PREFIX . "user_group WHERE user_group_id = '" . (int)$user_group_id . "'");

		if ($user_group_query->num_rows) {
			$data = json_decode($user_group_query->row['permission'], true);

			$data[$type][] = $route;

			$this->db->query("UPDATE " . DB_PREFIX . "user_group SET permission = '" . $this->db->escape(json_encode($data)) . "' WHERE user_group_id = '" . (int)$user_group_id . "'");
		}
	}

	public function removePermission($user_group_id, $type, $route) {
		$user_group_query = $this->db->query("SELECT DISTINCT * FROM " . DB_PREFIX . "user_group WHERE user_group_id = '" . (int)$user_group_id . "'");

		if ($user_group_query->num_rows) {
			$data = json_decode($user_group_query->row['permission'], true);

			$data[$type] = array_values(array_diff($data[$type], array($route)));

			$this->db->query("UPDATE " . DB_PREFIX . "user_group SET permission = '" . $this->db->escape(json_encode($data)) . "' WHERE user_group_id = '" . (int)$user_group_id . "'");
		}
	}

	public function removePermissions( string $route ): void {
		$user_groups = $this->getUserGroups();
		foreach ($user_groups as $user_group) {
			$user_group_id = $user_group['user_group_id'];
			$permission = $user_group['permission'];
			if (!empty($permission)) {
				$permission = json_decode($permission,true);
				if (!empty($permission['access'])) {
					$this->removePermission($user_group_id, 'access', $route);
				}
				if (!empty($permission['modify'])) {
					$this->removePermission($user_group_id, 'modify', $route);
				}
			}
		}
	}
}
