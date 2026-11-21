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

	/** @var array<int, int>|null cached language ids (see languageIds()) */
	private $language_ids;

	// ---------------------------------------------------------------- pages

	/**
	 * @param array<string, mixed> $data
	 * @return int new landing id
	 */
	public function addLanding(array $data): int {
		$now = date('Y-m-d H:i:s');

		$information_id = (int)($data['information_id'] ?? 0);

		$this->db->query("INSERT INTO `" . DB_PREFIX . "landing` SET
			name = '" . $this->db->escape((string)($data['name'] ?? '')) . "',
			information_id = '" . $information_id . "',
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

		// An owned landing is the composed body of an information page: no slugs,
		// no own visibility, never the store home page.
		$this->saveDescriptions($landing_id, (array)($data['descriptions'] ?? []), $information_id > 0);

		if (isset($data['blocks']) && is_array($data['blocks'])) {
			$this->saveBlocks($landing_id, $data['blocks']);
		}

		if (empty($data['information_id']) && !empty($data['is_home'])) {
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
			$query = $this->db->query("SELECT version, information_id FROM `" . DB_PREFIX . "landing` WHERE landing_id = '" . (int)$landing_id . "' FOR UPDATE");

			if (!$query->num_rows) {
				$this->db->query("ROLLBACK");

				return ['success' => false, 'conflict' => true, 'version' => 0];
			}

			// A landing owned by an information page carries only its blocks,
			// noindex and custom CSS: visibility lives on oc_information.status,
			// the chrome is always the full store theme and slugs are never
			// mirrored into oc_seo_url.
			$owned = (int)$query->row['information_id'] > 0;

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
				"chrome = '" . ($owned ? 'full' : ((($data['chrome'] ?? 'full') === 'bare') ? 'bare' : 'full')) . "'",
				"noindex = '" . (!empty($data['noindex']) ? 1 : 0) . "'",
				"custom_css = '" . $this->db->escape((string)($data['custom_css'] ?? '')) . "'",
			];

			if (!$owned) {
				$set[] = "status = '" . (!empty($data['status']) ? 1 : 0) . "'";
				$set[] = "date_publish = " . $this->dateSql($data['date_publish'] ?? null);
				$set[] = "date_unpublish = " . $this->dateSql($data['date_unpublish'] ?? null);
			}

			$set[] = "version = '" . (int)$new_version . "'";
			$set[] = "date_modified = '" . $now . "'";

			$this->db->query("UPDATE `" . DB_PREFIX . "landing` SET " . implode(', ', $set) . " WHERE landing_id = '" . (int)$landing_id . "'");

			$this->saveDescriptions($landing_id, (array)($data['descriptions'] ?? []), $owned);

			$saved_blocks = [];

			if (isset($data['blocks']) && is_array($data['blocks'])) {
				$saved_blocks = $this->saveBlocks($landing_id, $data['blocks']);
			}

			if (!$owned) {
				if (!empty($data['is_home'])) {
					$this->setHomePage($landing_id);
				} else {
					$this->clearHomePage($landing_id);
				}
			}

			$this->db->query("COMMIT");
		} catch (\Throwable $e) {
			$this->db->query("ROLLBACK");

			$this->log->write('Landing save failed: ' . $e->getMessage());

			return ['success' => false, 'conflict' => false, 'version' => 0];
		}

		// blocks = the saved list in payload order (client_id -> real block_id),
		// so the builder can sync its in-memory ids after an autosave.
		return ['success' => true, 'version' => (int)$new_version, 'blocks' => $saved_blocks];
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
	public function duplicateLanding(int $landing_id, int $new_information_id = 0): int {
		$landing = $this->getLanding($landing_id);

		if (!$landing) {
			return 0;
		}

		$owned = ((int)($landing['information_id'] ?? 0) > 0) || $new_information_id > 0;

		$descriptions = $this->getLandingDescriptions($landing_id);
		$taken = $this->getTakenSlugs();

		foreach ($descriptions as $language_id => $description) {
			// An owned landing has no slugs of its own (the information page
			// owns the SEO keyword), so there is nothing to uniquify.
			$slug = $owned ? '' : Dockercart\Landing\Url::uniqueSlug((string)$description['slug'], $taken);
			$descriptions[$language_id]['slug'] = $slug;
			$taken[] = $slug;
		}

		$blocks = [];

		foreach ($this->getBlocks($landing_id) as $block) {
			// block_id keeps the source id on purpose: saveBlocks() remaps it to
			// the freshly inserted rows, so children point at the copied section.
			$blocks[] = [
				'block_id'  => (int)$block['block_id'],
				'parent_id' => (int)$block['parent_id'],
				'slot'      => (int)$block['slot'],
				'sort'      => (int)$block['sort'],
				'code'      => $block['code'],
				'status'    => $block['status'],
				'settings'  => $block['settings'],
				'content'   => $block['content'],
			];
		}

		$language_id = (int)$this->config->get('config_language_id');
		$name = $owned ? (string)$landing['name'] : $landing['name'] . ' (copy)';

		return $this->addLanding([
			'name'            => $name,
			'information_id'  => $new_information_id,
			'status'          => 0,
			'chrome'          => $landing['chrome'],
			'noindex'         => $owned ? (int)$landing['noindex'] : 1,
			'custom_css'      => $landing['custom_css'],
			'date_publish'    => null,
			'date_unpublish'  => null,
			'descriptions'    => $descriptions,
			'blocks'          => $blocks,
			'is_home'         => 0,
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
			FROM `" . DB_PREFIX . "landing` l
			WHERE " . implode(' AND ', $this->landingListWhere($data));

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
		$sql = "SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "landing` l WHERE " . implode(' AND ', $this->landingListWhere($data));

		$query = $this->db->query($sql);

		return (int)$query->row['total'];
	}

	/**
	 * WHERE fragments shared by getLandings() and getTotalLandings(): owned
	 * landings never show up in the list, name/status filters apply to both.
	 *
	 * @param array<string, mixed> $data
	 * @return array<int, string>
	 */
	private function landingListWhere(array $data): array {
		// Landings owned by an information page are managed from
		// Catalog > Information and never show up in this list.
		$where = ["l.information_id IS NULL"];

		if (!empty($data['filter_name'])) {
			$where[] = "l.name LIKE '" . $this->db->escape($data['filter_name']) . "%'";
		}

		if (isset($data['filter_status']) && $data['filter_status'] !== '') {
			$where[] = "l.status = '" . (int)$data['filter_status'] . "'";
		}

		return $where;
	}

	/**
	 * Publish toggle from the page list. Bumps version so an open builder
	 * reports a conflict instead of silently overwriting the change.
	 */
	public function setStatus(int $landing_id, bool $status): void {
		$this->db->query("UPDATE `" . DB_PREFIX . "landing` SET status = '" . ($status ? 1 : 0) . "', version = version + 1, date_modified = '" . date('Y-m-d H:i:s') . "' WHERE landing_id = '" . (int)$landing_id . "'");
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
		$query = $this->db->query("SELECT d.slug FROM `" . DB_PREFIX . "landing_description` d
			INNER JOIN `" . DB_PREFIX . "landing` l ON (l.landing_id = d.landing_id)
			WHERE d.slug <> '' AND l.information_id IS NULL AND d.landing_id <> '" . (int)$exclude_landing_id . "'");

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
	 * merge them straight into its error bag. An owned landing only validates
	 * its name: slugs, schedule and the home-page flag do not apply to it.
	 *
	 * @param array<string, mixed> $data
	 * @return array<string, mixed>
	 */
	public function validateLanding(array $data, int $landing_id = 0, bool $owned = false): array {
		$errors = [];

		$name = trim((string)($data['name'] ?? ''));

		if ($this->length($name) < 3 || $this->length($name) > 255) {
			$errors['name'] = $this->text('error_name', 'Landing page name must be between 3 and 255 characters!');
		}

		// An owned landing (the composed body of an information page) has no
		// slugs, no schedule and can never be the home page: its meta lives in
		// oc_information_description and its visibility on oc_information.status.
		if ($owned) {
			return $errors;
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
	 * Validates the information-page fields edited from the builder
	 * Page Settings (owned landings only): translated meta tags and the
	 * default-store SEO keyword. Flat field => message map, same as
	 * validateLanding(). Lengths mirror the information form
	 * (utf8_strlen on the Request::clean encoding).
	 *
	 * @param array{status?: mixed, descriptions?: mixed} $owner
	 * @return array<string, string>
	 */
	public function validateOwner(array $owner, int $information_id): array {
		$errors = [];

		$descriptions = isset($owner['descriptions']) && is_array($owner['descriptions']) ? $owner['descriptions'] : [];

		foreach ($this->languageIds() as $language_id) {
			$row = isset($descriptions[$language_id]) && is_array($descriptions[$language_id]) ? $descriptions[$language_id] : [];

			foreach (['meta_title', 'meta_description', 'meta_keyword'] as $key) {
				$value = trim(htmlspecialchars((string)($row[$key] ?? ''), ENT_COMPAT, 'UTF-8'));

				if ($this->length($value) > 255) {
					$errors['owner.' . $key . '.' . $language_id] = $this->text('error_meta_length', 'Meta title, description and keywords must not exceed 255 characters!');
				}
			}

			$keyword = trim(htmlspecialchars((string)($row['slug'] ?? ''), ENT_COMPAT, 'UTF-8'));

			if ($keyword !== '' && $this->ownerKeywordUsed($keyword, $language_id, $information_id)) {
				$errors['owner.slug.' . $language_id] = $this->text('error_slug_used', 'SEO keyword is already in use!');
			}
		}

		return $errors;
	}

	/**
	 * Whether an information SEO keyword is taken on the default store by
	 * anything but this information page (mirrors the information form
	 * uniqueness check).
	 */
	private function ownerKeywordUsed(string $keyword, int $language_id, int $information_id): bool {
		$this->load->model('design/seo_url');

		foreach ($this->model_design_seo_url->getSeoUrlsByKeyword($keyword, $language_id) as $row) {
			if ((int)($row['store_id'] ?? 0) === 0 && (string)($row['query'] ?? '') !== 'information_id=' . (int)$information_id) {
				return true;
			}
		}

		return false;
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

		if (!$query->num_rows) {
			return [];
		}

		// One descriptions query instead of one per block (the builder calls
		// this after every save).
		$block_ids = array_map(static function ($row): int {
			return (int)$row['block_id'];
		}, $query->rows);

		$content_query = $this->db->query("SELECT block_id, language_id, content FROM `" . DB_PREFIX . "landing_block_description` WHERE block_id IN (" . implode(',', $block_ids) . ")");

		$blocks = [];

		foreach (Dockercart\Landing\Blocks::hydrate($query->rows, $content_query->rows) as $block) {
			$block['landing_id'] = (int)$landing_id;
			$block['sort'] = $block['sort_order'];
			unset($block['sort_order']);

			$blocks[] = $block;
		}

		return $blocks;
	}

	/**
	 * Upserts every block of a page and deletes the ones that were removed.
	 *
	 * Hierarchy protocol (the builder payload):
	 *   * `block_id` > 0 keeps/updates that row; `block_id` <= 0 inserts a new
	 *     row (the builder sends unique negative temp ids for new blocks so
	 *     children can reference them; 0 stays the legacy "anonymous new block").
	 *   * `parent_id` points at the owning section: a submitted temp id resolves
	 *     through this payload, a kept existing id passes through. Containers
	 *     are forced to the top level; a child whose parent is not part of the
	 *     saved page is PROMOTED to the top level instead of dropped, so user
	 *     content never disappears over a malformed payload. Sections must be
	 *     submitted before their children (the builder always does).
	 *   * `slot` is clamped to the parent structure's column count, `sort` to
	 *     0-999 (the position inside the own parent/slot group).
	 *
	 * @param array<int, array<string, mixed>> $blocks
	 * @return array<int, array<string, mixed>> saved blocks (with real ids)
	 */
	public function saveBlocks(int $landing_id, array $blocks): array {
		$landing_id = (int)$landing_id;
		$now = date('Y-m-d H:i:s');

		$existing = [];

		foreach ($this->db->query("SELECT block_id FROM `" . DB_PREFIX . "landing_block` WHERE landing_id = '" . $landing_id . "'")->rows as $row) {
			$existing[(int)$row['block_id']] = true;
		}

		$kept = [];
		$id_map = [];
		$container_columns = [];
		$saved = [];

		foreach (array_slice($blocks, 0, self::MAX_BLOCKS) as $block) {
			$normalized = $this->normalizeBlockPayload((array)$block);

			if ($normalized === null) {
				continue;
			}

			$code = $normalized['code'];
			$settings = $normalized['settings'];
			$content = $normalized['content'];

			$client_id = (int)($block['block_id'] ?? 0);
			$status = isset($block['status']) && !$block['status'] ? 0 : 1;

			// Hierarchy: containers stay top level, children must resolve to a
			// container kept by this payload.
			$parent_id = (int)($block['parent_id'] ?? 0);

			if (Dockercart\Landing\Blocks::isContainer($code)) {
				$parent_id = 0;
			} elseif ($parent_id !== 0 && isset($id_map[$parent_id])) {
				$parent_id = (int)$id_map[$parent_id];
			} else {
				$parent_id = 0;
			}

			$slot = 0;

			if ($parent_id > 0) {
				$columns = $container_columns[$parent_id] ?? 1;
				$slot = max(0, min($columns - 1, (int)($block['slot'] ?? 0)));
			}

			$sort_order = max(0, min(999, (int)($block['sort'] ?? 0)));
			$block_id = 0;

			if ($client_id > 0 && isset($existing[$client_id])) {
				$block_id = $client_id;

				$this->db->query("UPDATE `" . DB_PREFIX . "landing_block` SET
					code = '" . $this->db->escape($code) . "',
					parent_id = '" . (int)$parent_id . "',
					slot = '" . (int)$slot . "',
					sort_order = '" . (int)$sort_order . "',
					status = '" . (int)$status . "',
					settings = '" . $this->db->escape((string)json_encode($settings, JSON_UNESCAPED_UNICODE)) . "',
					date_modified = '" . $now . "'
					WHERE block_id = '" . (int)$block_id . "' AND landing_id = '" . $landing_id . "'");
			} else {
				$this->db->query("INSERT INTO `" . DB_PREFIX . "landing_block` SET
					landing_id = '" . $landing_id . "',
					parent_id = '" . (int)$parent_id . "',
					slot = '" . (int)$slot . "',
					code = '" . $this->db->escape($code) . "',
					sort_order = '" . (int)$sort_order . "',
					status = '" . (int)$status . "',
					settings = '" . $this->db->escape((string)json_encode($settings, JSON_UNESCAPED_UNICODE)) . "',
					date_added = '" . $now . "',
					date_modified = '" . $now . "'");

				$block_id = (int)$this->db->getLastId();
			}

			if ($client_id !== 0) {
				$id_map[$client_id] = $block_id;
			}

			if (Dockercart\Landing\Blocks::isContainer($code)) {
				$container_columns[$block_id] = Dockercart\Landing\Blocks::columnCount((string)$settings['structure']);
			}

			$this->db->query("DELETE FROM `" . DB_PREFIX . "landing_block_description` WHERE block_id = '" . (int)$block_id . "'");

			foreach ($content as $language_id => $translated) {
				$this->db->query("INSERT INTO `" . DB_PREFIX . "landing_block_description` SET
					block_id = '" . (int)$block_id . "',
					language_id = '" . (int)$language_id . "',
					content = '" . $this->db->escape((string)json_encode($translated, JSON_UNESCAPED_UNICODE)) . "'");
			}

			$kept[$block_id] = true;

			$saved[] = [
				'block_id'  => (int)$block_id,
				'client_id' => $client_id,
				'parent_id' => (int)$parent_id,
				'slot'      => (int)$slot,
				'sort'      => (int)$sort_order,
				'code'      => $code,
				'status'    => (int)$status,
			];
		}

		foreach (array_keys($existing) as $block_id) {
			if (isset($kept[$block_id])) {
				continue;
			}

			$this->db->query("DELETE FROM `" . DB_PREFIX . "landing_block_description` WHERE block_id = '" . (int)$block_id . "'");
			$this->db->query("DELETE FROM `" . DB_PREFIX . "landing_block` WHERE block_id = '" . (int)$block_id . "'");
		}

		return $saved;
	}

	/**
	 * Shared builder-payload normalisation for saveBlocks() and addTemplate():
	 * a known block code plus cast settings and per-language content. Unknown
	 * codes (and anything else the registry does not declare) never pass.
	 *
	 * @param array<string, mixed> $block
	 * @return array{code: string, settings: array<string, mixed>, content: array<int, array<string, mixed>>}|null
	 */
	private function normalizeBlockPayload(array $block): ?array {
		$code = isset($block['code']) ? (string)$block['code'] : '';

		if (!Dockercart\Landing\Blocks::exists($code)) {
			return null;
		}

		$content = [];

		foreach ($this->languageIds() as $language_id) {
			$content[$language_id] = Dockercart\Landing\Blocks::normalizeContent(
				$code,
				(array)($block['content'][$language_id] ?? $block['content'][(string)$language_id] ?? [])
			);
		}

		return [
			'code'     => $code,
			'settings' => Dockercart\Landing\Blocks::normalizeSettings($code, (array)($block['settings'] ?? [])),
			'content'  => $content,
		];
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
		$rows = [];

		foreach (array_slice($blocks, 0, self::MAX_BLOCKS) as $block) {
			$normalized = $this->normalizeBlockPayload((array)$block);

			if ($normalized === null) {
				continue;
			}

			$parent_id = (int)($block['parent_id'] ?? 0);

			if (Dockercart\Landing\Blocks::isContainer($normalized['code'])) {
				$parent_id = 0;
			}

			// block_id stays in the stored JSON as the template-local reference a
			// child's parent_id points at; insertTemplate() remaps both to fresh
			// temp ids when the set is inserted into a page.
			$rows[] = [
				'block_id'  => (int)($block['block_id'] ?? 0),
				'parent_id' => $parent_id,
				'slot'      => max(0, (int)($block['slot'] ?? 0)),
				'sort'      => max(0, min(999, (int)($block['sort'] ?? 0))),
				'code'      => $normalized['code'],
				'status'   => 1,
				'settings' => $normalized['settings'],
				'content'  => $normalized['content'],
			];
		}

		$this->db->query("INSERT INTO `" . DB_PREFIX . "landing_template` SET
			name = '" . $this->db->escape($name) . "',
			blocks = '" . $this->db->escape((string)json_encode($rows, JSON_UNESCAPED_UNICODE)) . "',
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

	// ---------------------------------------------------------- site design

	/**
	 * Global landing style shared by every page (separate setting code so a
	 * partial editSetting('config', …) can never wipe it).
	 *
	 * @return array<string, string>
	 */
	public function getSiteDesign(): array {
		$values = \Dockercart\Landing\Design::defaults();

		$query = $this->db->query("SELECT `key`, `value` FROM `" . DB_PREFIX . "setting` WHERE `store_id` = '0' AND `code` = '" . \Dockercart\Landing\Design::SETTING_CODE . "'");

		foreach ($query->rows as $row) {
			$key = (string)$row['key'];

			if (isset($values[$key])) {
				$values[$key] = (string)$row['value'];
			}
		}

		return \Dockercart\Landing\Design::normalize($values);
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public function saveSiteDesign(array $data): void {
		$design = \Dockercart\Landing\Design::normalize($data);
		$code = \Dockercart\Landing\Design::SETTING_CODE;

		$this->db->query("DELETE FROM `" . DB_PREFIX . "setting` WHERE `store_id` = '0' AND `code` = '" . $code . "'");

		foreach ($design as $key => $value) {
			$this->db->query("INSERT INTO `" . DB_PREFIX . "setting` (`store_id`, `code`, `key`, `value`, `serialized`) VALUES ('0', '" . $code . "', '" . $this->db->escape(\Dockercart\Landing\Design::PREFIX . $key) . "', '" . $this->db->escape($value) . "', '0')");
		}
	}

	// -------------------------------------------------------------- helpers

	/**
	 * Persists the per-language slug/meta and re-syncs oc_seo_url.
	 *
	 * @param array<int, array<string, mixed>> $descriptions
	 */
	private function saveDescriptions(int $landing_id, array $descriptions, bool $owned = false): void {
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

			$slug = $owned ? '' : Dockercart\Landing\Url::normalizeSlug((string)($row['slug'] ?? ''));

			if ($slug === '') {
				$slug = $owned ? '' : $default_slug;
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

		if (!$owned) {
			$this->syncSeoUrls($landing_id, $slugs);
		}
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
	 * Installed language ids, cached per model instance (saveBlocks(),
	 * saveDescriptions() and addTemplate() all ask for them).
	 *
	 * @return array<int, int>
	 */
	private function languageIds(): array {
		if ($this->language_ids !== null) {
			return $this->language_ids;
		}

		$this->load->model('localisation/language');

		$ids = [];

		foreach ($this->model_localisation_language->getLanguages() as $language) {
			$ids[] = (int)$language['language_id'];
		}

		return $this->language_ids = $ids;
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
		return Dockercart\Landing\Text::length($value);
	}

	private function truncate(string $value, int $length): string {
		return Dockercart\Landing\Text::truncate($value, $length);
	}
}
