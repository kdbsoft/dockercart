<?php
/**
 * Landing page model (admin side).
 *
 * Storage layout (see docker/mysql/migrations/20261101_landing_builder.sql):
 *   oc_landing / oc_landing_description          page + per-language slug & meta
 *   oc_landing_block / oc_landing_block_description   flat ordered sections
 *   oc_landing_template                         reusable section sets
 *
 * Every block passes through Dockercart\Landing\Blocks::normalize*(), so an
 * unknown block code or field can never reach the storefront (the builder is a
 * trusted admin surface, but payloads are still untrusted input).
 *
 * The per-language slug is mirrored into oc_seo_url (query = landing_id=<id>,
 * store_id = 0) on every save, which is what makes /black-friday resolve.
 *
 * @package    DockerCart
 * @subpackage Model
 */

declare(strict_types=1);

class ModelDesignLanding extends Model {
	private const MAX_BLOCKS = 200;

	// ---------------------------------------------------------------- pages

	/**
	 * @param array<string, mixed> $data
	 * @return int new landing id
	 */
	public function addLanding(array $data): int {
		$now = date('Y-m-d H:i:s');

		$this->db->query("INSERT INTO `" . DB_PREFIX . "landing` SET
			name = '" . $this->db->escape((string)($data['name'] ?? '')) . "',
			status = '" . (!empty($data['status']) ? 1 : 0) . "',
			chrome = '" . ((($data['chrome'] ?? 'full') === 'bare') ? 'bare' : 'full') . "',
			is_home = '0',
			noindex = '" . (!empty($data['noindex']) ? 1 : 0) . "',
			custom_css = '" . $this->db->escape((string)($data['custom_css'] ?? '')) . "',
			viewed = '0',
			version = '1',
			date_publish = " . $this->dateSql($data['date_publish'] ?? null) . ",
			date_unpublish = " . $this->dateSql($data['date_unpublish'] ?? null) . ",
			date_added = '" . $now . "',
			date_modified = '" . $now . "'");

		$landing_id = (int)$this->db->getLastId();

		$this->saveDescriptions($landing_id, (array)($data['descriptions'] ?? []));

		if (isset($data['blocks']) && is_array($data['blocks'])) {
			$this->saveBlocks($landing_id, $data['blocks']);
		}

		if (!empty($data['is_home'])) {
			$this->setHomePage($landing_id);
		}

		return $landing_id;
	}

	/**
	 * Saves page settings + the complete block list (builder save endpoint).
	 *
	 * @param array<string, mixed> $data
	 * @return array{success: bool, conflict?: bool, version?: int}
	 */
	public function saveLanding(int $landing_id, array $data): array {
		$this->db->query("START TRANSACTION");

		try {
			$query = $this->db->query("SELECT version FROM `" . DB_PREFIX . "landing` WHERE landing_id = '" . (int)$landing_id . "' FOR UPDATE");

			if (!$query->num_rows) {
				$this->db->query("ROLLBACK");

				return ['success' => false, 'conflict' => true, 'version' => 0];
			}

			$current_version = (int)$query->row['version'];
			$sent_version = isset($data['version']) ? (int)$data['version'] : $current_version;

			if ($sent_version > 0 && $sent_version !== $current_version) {
				$this->db->query("ROLLBACK");

				return ['success' => false, 'conflict' => true, 'version' => $current_version];
			}

			$now = date('Y-m-d H:i:s');
			$new_version = $current_version + 1;

			$set = [
				"name = '" . $this->db->escape((string)($data['name'] ?? '')) . "'",
				"status = '" . (!empty($data['status']) ? 1 : 0) . "'",
				"chrome = '" . ((($data['chrome'] ?? 'full') === 'bare') ? 'bare' : 'full') . "'",
				"noindex = '" . (!empty($data['noindex']) ? 1 : 0) . "'",
				"custom_css = '" . $this->db->escape((string)($data['custom_css'] ?? '')) . "'",
				"date_publish = " . $this->dateSql($data['date_publish'] ?? null),
				"date_unpublish = " . $this->dateSql($data['date_unpublish'] ?? null),
				"version = '" . (int)$new_version . "'",
				"date_modified = '" . $now . "'",
			];

			$this->db->query("UPDATE `" . DB_PREFIX . "landing` SET " . implode(', ', $set) . " WHERE landing_id = '" . (int)$landing_id . "'");

			$this->saveDescriptions($landing_id, (array)($data['descriptions'] ?? []));

			if (isset($data['blocks']) && is_array($data['blocks'])) {
				$this->saveBlocks($landing_id, $data['blocks']);
			}

			if (!empty($data['is_home'])) {
				$this->setHomePage($landing_id);
			} else {
				$this->clearHomePage($landing_id);
			}

			$this->db->query("COMMIT");
		} catch (\Throwable $e) {
			$this->db->query("ROLLBACK");

			$this->log->write('Landing save failed: ' . $e->getMessage());

			return ['success' => false, 'conflict' => false, 'version' => 0];
		}

		return ['success' => true, 'version' => (int)$new_version];
	}

	public function deleteLanding(int $landing_id): void {
		$landing_id = (int)$landing_id;

		$this->db->query("START TRANSACTION");

		$block_query = $this->db->query("SELECT block_id FROM `" . DB_PREFIX . "landing_block` WHERE landing_id = '" . $landing_id . "'");

		foreach ($block_query->rows as $row) {
			$this->db->query("DELETE FROM `" . DB_PREFIX . "landing_block_description` WHERE block_id = '" . (int)$row['block_id'] . "'");
		}

		$this->db->query("DELETE FROM `" . DB_PREFIX . "landing_block` WHERE landing_id = '" . $landing_id . "'");
		$this->db->query("DELETE FROM `" . DB_PREFIX . "landing_description` WHERE landing_id = '" . $landing_id . "'");
		$this->db->query("DELETE FROM `" . DB_PREFIX . "landing` WHERE landing_id = '" . $landing_id . "'");

		$this->db->query("COMMIT");

		$this->load->model('design/seo_url');
		$this->model_design_seo_url->deleteSeoUrlsByQuery('landing_id=' . $landing_id);
		$this->model_design_seo_url->invalidateSeoUrlCache();

		$this->clearHomePage($landing_id);
	}

	/**
	 * Duplicates a page with all blocks; slugs get a -copy suffix, status draft.
	 */
	public function duplicateLanding(int $landing_id): int {
		$landing = $this->getLanding($landing_id);

		if (!$landing) {
			return 0;
		}

		$descriptions = $this->getLandingDescriptions($landing_id);
		$taken = $this->getTakenSlugs();

		foreach ($descriptions as $language_id => $description) {
			$descriptions[$language_id]['slug'] = Dockercart\Landing\Url::uniqueSlug((string)$description['slug'], $taken);
			$taken[] = $descriptions[$language_id]['slug'];
		}

		$blocks = [];

		foreach ($this->getBlocks($landing_id) as $block) {
			$blocks[] = [
				'block_id' => 0,
				'code'     => $block['code'],
				'status'   => $block['status'],
				'settings' => $block['settings'],
				'content'  => $block['content'],
			];
		}

		$language_id = (int)$this->config->get('config_language_id');
		$name = $landing['name'] . ' (copy)';

		return $this->addLanding([
			'name'         => $name,
			'status'       => 0,
			'chrome'       => $landing['chrome'],
			'noindex'      => 1,
			'custom_css'   => $landing['custom_css'],
			'date_publish' => null,
			'date_unpublish' => null,
			'descriptions' => $descriptions,
			'blocks'       => $blocks,
			'is_home'      => 0,
		]);
	}

	/**
	 * @return array<string, mixed>|false
	 */
	public function getLanding(int $landing_id) {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "landing` WHERE landing_id = '" . (int)$landing_id . "'");

		return $query->num_rows ? $query->row : false;
	}

	/**
	 * @param array<string, mixed> $data
	 * @return array<int, array<string, mixed>>
	 */
	public function getLandings(array $data = []): array {
		$language_id = (int)$this->config->get('config_language_id');

		$sql = "SELECT l.*,
			(SELECT COUNT(*) FROM `" . DB_PREFIX . "landing_block` b WHERE b.landing_id = l.landing_id) AS blocks,
			(SELECT d.slug FROM `" . DB_PREFIX . "landing_description` d WHERE d.landing_id = l.landing_id AND d.language_id = '" . $language_id . "') AS slug
			FROM `" . DB_PREFIX . "landing` l";

		$where = [];

		if (!empty($data['filter_name'])) {
			$where[] = "l.name LIKE '" . $this->db->escape($data['filter_name']) . "%'";
		}

		if (isset($data['filter_status']) && $data['filter_status'] !== '') {
			$where[] = "l.status = '" . (int)$data['filter_status'] . "'";
		}

		if ($where) {
			$sql .= " WHERE " . implode(' AND ', $where);
		}

		$sort = isset($data['sort']) && in_array($data['sort'], ['name', 'status', 'viewed', 'date_modified', 'date_added'], true) ? $data['sort'] : 'date_modified';
		$order = (isset($data['order']) && strtoupper((string)$data['order']) === 'ASC') ? 'ASC' : 'DESC';

		$sql .= " ORDER BY l." . $sort . " " . $order;

		if (isset($data['start']) || isset($data['limit'])) {
			$start = isset($data['start']) ? max(0, (int)$data['start']) : 0;
			$limit = isset($data['limit']) ? max(1, (int)$data['limit']) : 20;

			$sql .= " LIMIT " . $start . "," . $limit;
		}

		$query = $this->db->query($sql);

		return $query->rows;
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public function getTotalLandings(array $data = []): int {
		$sql = "SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "landing` l";

		$where = [];

		if (!empty($data['filter_name'])) {
			$where[] = "l.name LIKE '" . $this->db->escape($data['filter_name']) . "%'";
		}

		if (isset($data['filter_status']) && $data['filter_status'] !== '') {
			$where[] = "l.status = '" . (int)$data['filter_status'] . "'";
		}

		if ($where) {
			$sql .= " WHERE " . implode(' AND ', $where);
		}

		$query = $this->db->query($sql);

		return (int)$query->row['total'];
	}

	/**
	 * @return array<int, array<string, mixed>> keyed by language_id
	 */
	public function getLandingDescriptions(int $landing_id): array {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "landing_description` WHERE landing_id = '" . (int)$landing_id . "'");

		$descriptions = [];

		foreach ($query->rows as $row) {
			$descriptions[(int)$row['language_id']] = $row;
		}

		return $descriptions;
	}

	/**
	 * Slugs already used by *other* landing pages (any language).
	 *
	 * @return array<int, string>
	 */
	public function getTakenSlugs(int $exclude_landing_id = 0): array {
		$query = $this->db->query("SELECT slug FROM `" . DB_PREFIX . "landing_description` WHERE slug <> '' AND landing_id <> '" . (int)$exclude_landing_id . "'");

		$slugs = [];

		foreach ($query->rows as $row) {
			$slugs[] = (string)$row['slug'];
		}

		return $slugs;
	}

	/**
	 * Validation of page settings.
	 *
	 * Errors are messages (resolved from design/landing) so the controller can
	 * merge them straight into its error bag.
	 *
	 * @param array<string, mixed> $data
	 * @return array<string, mixed>
	 */
	public function validateLanding(array $data, int $landing_id = 0): array {
		$errors = [];

		$name = trim((string)($data['name'] ?? ''));

		if ($this->length($name) < 3 || $this->length($name) > 255) {
			$errors['name'] = $this->text('error_name', 'Landing page name must be between 3 and 255 characters!');
		}

		$descriptions = (array)($data['descriptions'] ?? []);
		$default_language_id = (int)$this->config->get('config_language_id');
		$language_ids = $this->languageIds();

		$default_slug = '';

		if (isset($descriptions[$default_language_id]['slug'])) {
			$default_slug = Dockercart\Landing\Url::normalizeSlug((string)$descriptions[$default_language_id]['slug']);
		}

		if ($default_slug === '') {
			$errors['slug'][$default_language_id] = $this->text('error_slug', 'SEO keyword is required!');
		}

		foreach ($language_ids as $language_id) {
			$slug = isset($descriptions[$language_id]['slug']) ? Dockercart\Landing\Url::normalizeSlug((string)$descriptions[$language_id]['slug']) : '';
			$slug = $slug === '' ? $default_slug : $slug;

			if ($slug === '') {
				continue;
			}

			if (!Dockercart\Landing\Url::isValidSlug($slug)) {
				$errors['slug'][$language_id] = $this->text('error_slug_invalid', 'SEO keyword must be 3-255 characters (a-z, 0-9, -, .) and must not start with a reserved segment!');
				continue;
			}

			if ($this->slugIsTaken($slug, $language_id, $landing_id)) {
				$errors['slug'][$language_id] = $this->text('error_slug_used', 'SEO keyword is already in use!');
			}
		}

		$publish = $this->normalizeDateTime($data['date_publish'] ?? null);
		$unpublish = $this->normalizeDateTime($data['date_unpublish'] ?? null);

		if ($publish !== null && $unpublish !== null && strtotime($unpublish) <= strtotime($publish)) {
			$errors['date'] = $this->text('error_dates', 'The unpublish date must be later than the publish date!');
		}

		if (!empty($data['is_home']) && empty($data['status'])) {
			$errors['is_home'] = $this->text('error_home_status', 'A page must be published before it can be used as the store home page!');
		}

		return $errors;
	}

	/**
	 * Whether another page already owns this keyword in the given language.
	 *
	 * Looked up with the (store_id, language_id, keyword) index instead of
	 * loading the whole seo_url table — saves run on every builder autosave.
	 */
	private function slugIsTaken(string $slug, int $language_id, int $landing_id): bool {
		$query = $this->db->query("SELECT seo_url_id FROM `" . DB_PREFIX . "seo_url`
			WHERE store_id = '0' AND language_id = '" . (int)$language_id . "'
			AND keyword = '" . $this->db->escape($slug) . "'
			AND query <> 'landing_id=" . (int)$landing_id . "'
			LIMIT 1");

		return (bool)$query->num_rows;
	}

	// --------------------------------------------------------------- blocks

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function getBlocks(int $landing_id): array {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "landing_block` WHERE landing_id = '" . (int)$landing_id . "' ORDER BY sort_order ASC, block_id ASC");

		$blocks = [];

		foreach ($query->rows as $row) {
			$content_query = $this->db->query("SELECT language_id, content FROM `" . DB_PREFIX . "landing_block_description` WHERE block_id = '" . (int)$row['block_id'] . "'");

			$content = [];

			foreach ($content_query->rows as $content_row) {
				$decoded = json_decode((string)$content_row['content'], true);

				$content[(int)$content_row['language_id']] = is_array($decoded) ? $decoded : [];
			}

			$settings = json_decode((string)$row['settings'], true);

			$blocks[] = [
				'block_id'   => (int)$row['block_id'],
				'landing_id' => (int)$row['landing_id'],
				'code'       => (string)$row['code'],
				'status'     => (int)$row['status'],
				'sort_order' => (int)$row['sort_order'],
				'settings'   => is_array($settings) ? $settings : [],
				'content'    => $content,
			];
		}

		return $blocks;
	}

	/**
	 * Upserts every block of a page and deletes the ones that were removed.
	 *
	 * @param array<int, array<string, mixed>> $blocks
	 * @return array<int, array<string, mixed>> saved blocks (with real ids)
	 */
	public function saveBlocks(int $landing_id, array $blocks): array {
		$landing_id = (int)$landing_id;
		$now = date('Y-m-d H:i:s');
		$language_ids = $this->languageIds();

		$existing = [];

		foreach ($this->db->query("SELECT block_id FROM `" . DB_PREFIX . "landing_block` WHERE landing_id = '" . $landing_id . "'")->rows as $row) {
			$existing[(int)$row['block_id']] = true;
		}

		$kept = [];
		$sort_order = 0;

		foreach (array_slice($blocks, 0, self::MAX_BLOCKS) as $block) {
			$code = isset($block['code']) ? (string)$block['code'] : '';

			if (!Dockercart\Landing\Blocks::exists($code)) {
				continue;
			}

			$settings = Dockercart\Landing\Blocks::normalizeSettings($code, (array)($block['settings'] ?? []));
			$content = [];

			foreach ($language_ids as $language_id) {
				$content[$language_id] = Dockercart\Landing\Blocks::normalizeContent(
					$code,
					(array)($block['content'][$language_id] ?? $block['content'][(string)$language_id] ?? [])
				);
			}

			$block_id = isset($block['block_id']) ? (int)$block['block_id'] : 0;
			$status = isset($block['status']) && !$block['status'] ? 0 : 1;

			if ($block_id > 0 && isset($existing[$block_id])) {
				$this->db->query("UPDATE `" . DB_PREFIX . "landing_block` SET
					code = '" . $this->db->escape($code) . "',
					sort_order = '" . (int)$sort_order . "',
					status = '" . (int)$status . "',
					settings = '" . $this->db->escape((string)json_encode($settings, JSON_UNESCAPED_UNICODE)) . "',
					date_modified = '" . $now . "'
					WHERE block_id = '" . (int)$block_id . "' AND landing_id = '" . $landing_id . "'");
			} else {
				$this->db->query("INSERT INTO `" . DB_PREFIX . "landing_block` SET
					landing_id = '" . $landing_id . "',
					code = '" . $this->db->escape($code) . "',
					sort_order = '" . (int)$sort_order . "',
					status = '" . (int)$status . "',
					settings = '" . $this->db->escape((string)json_encode($settings, JSON_UNESCAPED_UNICODE)) . "',
					date_added = '" . $now . "',
					date_modified = '" . $now . "'");

				$block_id = (int)$this->db->getLastId();
			}

			$this->db->query("DELETE FROM `" . DB_PREFIX . "landing_block_description` WHERE block_id = '" . (int)$block_id . "'");

			foreach ($content as $language_id => $translated) {
				$this->db->query("INSERT INTO `" . DB_PREFIX . "landing_block_description` SET
					block_id = '" . (int)$block_id . "',
					language_id = '" . (int)$language_id . "',
					content = '" . $this->db->escape((string)json_encode($translated, JSON_UNESCAPED_UNICODE)) . "'");
			}

			$kept[$block_id] = true;
			$sort_order++;
		}

		foreach (array_keys($existing) as $block_id) {
			if (isset($kept[$block_id])) {
				continue;
			}

			$this->db->query("DELETE FROM `" . DB_PREFIX . "landing_block_description` WHERE block_id = '" . (int)$block_id . "'");
			$this->db->query("DELETE FROM `" . DB_PREFIX . "landing_block` WHERE block_id = '" . (int)$block_id . "'");
		}

		return $this->getBlocks($landing_id);
	}

	// ------------------------------------------------------------ templates

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function getTemplates(): array {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "landing_template` ORDER BY date_modified DESC, template_id DESC");

		$templates = [];

		foreach ($query->rows as $row) {
			$blocks = json_decode((string)$row['blocks'], true);

			$templates[] = [
				'template_id' => (int)$row['template_id'],
				'name'        => (string)$row['name'],
				'blocks'      => is_array($blocks) ? $blocks : [],
				'date_modified' => $row['date_modified'],
			];
		}

		return $templates;
	}

	/**
	 * @param array<int, array<string, mixed>> $blocks
	 */
	public function addTemplate(string $name, array $blocks): int {
		$now = date('Y-m-d H:i:s');
		$language_ids = $this->languageIds();
		$normalized = [];

		foreach (array_slice($blocks, 0, self::MAX_BLOCKS) as $block) {
			$code = isset($block['code']) ? (string)$block['code'] : '';

			if (!Dockercart\Landing\Blocks::exists($code)) {
				continue;
			}

			$content = [];

			foreach ($language_ids as $language_id) {
				$content[$language_id] = Dockercart\Landing\Blocks::normalizeContent(
					$code,
					(array)($block['content'][$language_id] ?? $block['content'][(string)$language_id] ?? [])
				);
			}

			$normalized[] = [
				'code'     => $code,
				'status'   => 1,
				'settings' => Dockercart\Landing\Blocks::normalizeSettings($code, (array)($block['settings'] ?? [])),
				'content'  => $content,
			];
		}

		$this->db->query("INSERT INTO `" . DB_PREFIX . "landing_template` SET
			name = '" . $this->db->escape($name) . "',
			blocks = '" . $this->db->escape((string)json_encode($normalized, JSON_UNESCAPED_UNICODE)) . "',
			date_added = '" . $now . "',
			date_modified = '" . $now . "'");

		return (int)$this->db->getLastId();
	}

	public function deleteTemplate(int $template_id): void {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "landing_template` WHERE template_id = '" . (int)$template_id . "'");
	}

	// ------------------------------------------------------------- home page

	public function setHomePage(int $landing_id): void {
		$this->load->model('setting/setting');

		$this->model_setting_setting->updateSetting('config', ['config_landing_home_id' => (int)$landing_id], 0);

		$this->db->query("UPDATE `" . DB_PREFIX . "landing` SET is_home = IF(landing_id = '" . (int)$landing_id . "', 1, 0)");
	}

	public function clearHomePage(int $landing_id): void {
		$query = $this->db->query("SELECT value FROM `" . DB_PREFIX . "setting` WHERE store_id = '0' AND `code` = 'config' AND `key` = 'config_landing_home_id'");

		if ($query->num_rows && (int)$query->row['value'] === (int)$landing_id) {
			$this->load->model('setting/setting');

			$this->model_setting_setting->updateSetting('config', ['config_landing_home_id' => 0], 0);
		}

		$this->db->query("UPDATE `" . DB_PREFIX . "landing` SET is_home = '0' WHERE landing_id = '" . (int)$landing_id . "'");
	}

	// -------------------------------------------------------------- helpers

	/**
	 * Persists the per-language slug/meta and re-syncs oc_seo_url.
	 *
	 * @param array<int, array<string, mixed>> $descriptions
	 */
	private function saveDescriptions(int $landing_id, array $descriptions): void {
		$landing_id = (int)$landing_id;
		$language_ids = $this->languageIds();
		$default_language_id = (int)$this->config->get('config_language_id');

		$default_slug = '';

		if (isset($descriptions[$default_language_id]['slug'])) {
			$default_slug = Dockercart\Landing\Url::normalizeSlug((string)$descriptions[$default_language_id]['slug']);
		}

		$this->db->query("DELETE FROM `" . DB_PREFIX . "landing_description` WHERE landing_id = '" . $landing_id . "'");

		$slugs = [];

		foreach ($language_ids as $language_id) {
			$row = isset($descriptions[$language_id]) ? (array)$descriptions[$language_id] : [];

			$slug = Dockercart\Landing\Url::normalizeSlug((string)($row['slug'] ?? ''));

			if ($slug === '') {
				$slug = $default_slug;
			}

			$slugs[$language_id] = $slug;

			$this->db->query("INSERT INTO `" . DB_PREFIX . "landing_description` SET
				landing_id = '" . $landing_id . "',
				language_id = '" . (int)$language_id . "',
				slug = '" . $this->db->escape($slug) . "',
				meta_title = '" . $this->db->escape($this->truncate((string)($row['meta_title'] ?? ''), 255)) . "',
				meta_description = '" . $this->db->escape($this->truncate((string)($row['meta_description'] ?? ''), 255)) . "',
				meta_keyword = '" . $this->db->escape($this->truncate((string)($row['meta_keyword'] ?? ''), 255)) . "'");
		}

		$this->syncSeoUrls($landing_id, $slugs);
	}

	/**
	 * @param array<int, string> $slugs language_id => keyword
	 */
	private function syncSeoUrls(int $landing_id, array $slugs): void {
		$this->load->model('design/seo_url');

		$this->model_design_seo_url->deleteSeoUrlsByQuery('landing_id=' . (int)$landing_id);

		foreach ($slugs as $language_id => $slug) {
			if ($slug === '') {
				continue;
			}

			$this->model_design_seo_url->addSeoUrl([
				'store_id'    => 0,
				'language_id' => (int)$language_id,
				'query'       => 'landing_id=' . (int)$landing_id,
				'keyword'     => $slug,
			]);
		}
	}

	/**
	 * @return array<int, int>
	 */
	private function languageIds(): array {
		$this->load->model('localisation/language');

		$ids = [];

		foreach ($this->model_localisation_language->getLanguages() as $language) {
			$ids[] = (int)$language['language_id'];
		}

		return $ids;
	}

	/**
	 * @param mixed $value
	 */
	private function normalizeDateTime($value): ?string {
		$value = trim((string)$value);

		if ($value === '' || $value === '0000-00-00 00:00:00') {
			return null;
		}

		$timestamp = strtotime(str_replace('T', ' ', $value));

		if ($timestamp === false || $timestamp <= 0) {
			return null;
		}

		return date('Y-m-d H:i:s', $timestamp);
	}

	/**
	 * @param mixed $value
	 */
	private function dateSql($value): string {
		$date = $this->normalizeDateTime($value);

		return $date === null ? 'NULL' : "'" . $date . "'";
	}

	private function text(string $key, string $fallback): string {
		if (isset($this->language)) {
			$value = $this->language->get($key);

			if ($value !== $key && $value !== '') {
				return (string)$value;
			}
		}

		return $fallback;
	}

	private function length(string $value): int {
		return function_exists('utf8_strlen') ? utf8_strlen($value) : strlen($value);
	}

	private function truncate(string $value, int $length): string {
		if (function_exists('utf8_strlen') && function_exists('utf8_substr')) {
			return utf8_strlen($value) > $length ? utf8_substr($value, 0, $length) : $value;
		}

		return strlen($value) > $length ? substr($value, 0, $length) : $value;
	}
}
