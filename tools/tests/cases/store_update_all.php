<?php
/**
 * Add-ons Store "Update all" — queue building, locale parity and wiring.
 *
 * The batch update is browser-driven: the store page renders a queue of
 * installed add-ons whose catalog version is newer, and the page posts every
 * SKU to the existing single-extension `update` action in turn. That keeps one
 * transaction/rollback per extension and lets a failure be reported without
 * stopping the rest. This case guards:
 *
 *   - DockercartExtensionStore::getAvailableUpdates() (highest catalog version,
 *     version_compare semantics, name fallback, fail-soft behaviour) and the
 *     countAvailableUpdates() badge that shares it;
 *   - the page data + template anchors (button, queue JSON, sequential posts);
 *   - the labels exist in en-gb, ru-ua and uk-ua.
 *
 * @package    DockerCart
 * @subpackage Tests
 */

declare(strict_types=1);

if (!defined('DIR_STORAGE')) {
	define('DIR_STORAGE', sys_get_temp_dir() . '/dockercart-store-tests/');
}

const STORE_LOCALES = ['en-gb', 'ru-ua', 'uk-ua'];

const STORE_LANGUAGE = 'upload/admin/language/{locale}/extension/store.php';

if (!class_exists('DockercartExtensionStore')) {
	require_once DIR_SYSTEM . 'library/dockercart/extension_store.php';
}

/**
 * @return array<string, mixed>
 */
function store_language(string $locale): array {
	return read_language_file(REPO_ROOT . '/' . str_replace('{locale}', $locale, STORE_LANGUAGE));
}

/**
 * Builds the store library on top of a fake DB and the given catalog XML.
 */
function store_library(FakeDb $db): DockercartExtensionStore {
	$registry = new Registry();
	$registry->set('db', $db);

	return new DockercartExtensionStore($registry);
}

function store_catalog(): string {
	return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<yml_catalog>
	<shop>
		<offers>
			<offer id="alpha"><name>Alpha Add-on</name><param code="version">1.2.3</param></offer>
			<offer id="alpha"><name>Alpha Add-on</name><param code="version">1.10.0</param></offer>
			<offer id="beta"><model>Beta model</model><name></name><param code="version">2.0.0</param></offer>
			<offer id="gamma"><name>Gamma Add-on</name><param code="version">1.1.0</param></offer>
			<offer id="delta"><name>Delta Add-on</name><param code="version">3.0.0</param></offer>
		</offers>
	</shop>
</yml_catalog>
XML;
}

/**
 * Alpha: 1.9.0 -> 1.10.0 (upgrade, numeric compare not string compare)
 * Beta:  2.0.0 -> 2.0.0 (up to date, row name must fall back to the catalog)
 * Gamma: 1.1.0 -> 1.1.0 (up to date)
 * Delta: no installed row at all (never listed)
 */
function store_meta_rows(): array {
	return [
		['sku' => 'alpha', 'code' => 'dockercart_alpha', 'name' => 'Alpha (installed)', 'installed_version' => '1.9.0'],
		['sku' => 'beta', 'code' => 'dockercart_beta', 'name' => '', 'installed_version' => '2.0.0'],
		['sku' => 'gamma', 'code' => 'dockercart_gamma', 'name' => 'Gamma Add-on', 'installed_version' => '1.1.0'],
	];
}

return [
	'queue: lists only newer versions, highest catalog version wins' => static function (): void {
		$db = new FakeDb();
		$db->pushResult(store_meta_rows());

		$store = store_library($db);

		$updates = $store->getAvailableUpdates(simplexml_load_string(store_catalog()));

		Assert::count(1, $updates, 'only Alpha has a newer catalog version');
		Assert::same('alpha', $updates[0]['sku']);
		Assert::same('dockercart_alpha', $updates[0]['code']);
		Assert::same('Alpha (installed)', $updates[0]['name'], 'the installed meta name wins');
		Assert::same('1.9.0', $updates[0]['installed_version']);
		Assert::same('1.10.0', $updates[0]['version'], 'version_compare must pick 1.10.0 over 1.2.3, not the string maximum');
	},

	'queue: catalog name and SKU fill in a missing meta name' => static function (): void {
		$db = new FakeDb();
		$db->pushResult([
			['sku' => 'beta', 'code' => 'dockercart_beta', 'name' => '', 'installed_version' => '1.0.0'],
			['sku' => 'unknown', 'code' => 'dockercart_unknown', 'name' => '', 'installed_version' => '0.1.0'],
		]);

		$store = store_library($db);

		$updates = $store->getAvailableUpdates(simplexml_load_string(store_catalog()));

		Assert::count(1, $updates, 'a SKU missing from the catalog is not updatable');
		Assert::same('beta', $updates[0]['sku']);
		Assert::same('Beta model', $updates[0]['name'], 'catalog <model> is the fallback display name');
	},

	'queue: sorted by name so the progress log is stable' => static function (): void {
		$db = new FakeDb();
		$db->pushResult([
			['sku' => 'zeta', 'code' => 'dockercart_zeta', 'name' => 'Zeta', 'installed_version' => '1.0.0'],
			['sku' => 'alpha', 'code' => 'dockercart_alpha', 'name' => 'Alpha', 'installed_version' => '1.0.0'],
		]);

		$catalog = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<yml_catalog><shop><offers>
	<offer id="zeta"><name>Zeta</name><param code="version">2.0.0</param></offer>
	<offer id="alpha"><name>Alpha</name><param code="version">2.0.0</param></offer>
</offers></shop></yml_catalog>
XML;

		$updates = store_library($db)->getAvailableUpdates(simplexml_load_string($catalog));

		Assert::same(['alpha', 'zeta'], array_column($updates, 'sku'));
	},

	'queue: a broken DB or empty catalog yields an empty list, never an exception' => static function (): void {
		$db = new FakeDb();
		$db->failOn('dockercart_extension_meta');

		Assert::same([], store_library($db)->getAvailableUpdates(simplexml_load_string(store_catalog())));

		$empty = new FakeDb();
		$empty->pushResult([]);

		Assert::same([], store_library($empty)->getAvailableUpdates(simplexml_load_string(store_catalog())));
	},

	'badge: countAvailableUpdates() reuses the same queue (cache-only)' => static function (): void {
		$cache_dir = DIR_STORAGE . 'dockercart/extension_store/';

		if (!is_dir($cache_dir)) {
			mkdir($cache_dir, 0755, true);
		}

		$cache_file = $cache_dir . 'yml_en.xml';
		$created = !is_file($cache_file);

		file_put_contents($cache_file, store_catalog());

		try {
			$db = new FakeDb();
			$db->pushResult(store_meta_rows());

			$store = store_library($db);

			Assert::same(1, $store->countAvailableUpdates(), 'the sidebar badge must agree with the batch queue');

			$db->pushResult(store_meta_rows());

			$from_cache = $store->getAvailableUpdates();

			Assert::count(1, $from_cache, 'without an argument the queue is built from the cache');
			Assert::same('1.10.0', $from_cache[0]['version']);
		} finally {
			if ($created) {
				unlink($cache_file);
			}
		}
	},

	'page: the controller exposes the queue and the modify gate' => static function (): void {
		$controller = repo_file('upload/admin/controller/extension/store.php');

		Assert::contains('$data[\'update_queue\']', $controller);
		Assert::contains('$store->getAvailableUpdates($yml)', $controller);
		Assert::contains("\$data['can_modify'] = \$this->user->hasPermission('modify', 'extension/store');", $controller);
	},

	'template: the button, the progress modal and the sequential posts are wired' => static function (): void {
		$template = repo_file('upload/admin/view/template/extension/store.twig');

		Assert::contains('{% if can_modify and update_queue %}', $template, 'the button must be hidden for read-only groups');
		Assert::contains('id="store-update-all-btn"', $template);

		// The batch button belongs to the installed/purchased panel at the bottom,
		// not to the catalog panel head at the top of the page.
		$licenses_head = strpos($template, '{{ text_license_management }}');
		$update_all_button = strpos($template, 'id="store-update-all-btn"');

		Assert::true($licenses_head !== false && $update_all_button !== false && $update_all_button > $licenses_head, 'the batch button must sit in the licenses panel head');
		Assert::contains('var updateQueue = {{ update_queue|json_encode|raw }};', $template);
		Assert::contains('id="store-update-all-modal"', $template);
		Assert::contains('id="store-update-all-bar"', $template);
		Assert::contains('id="store-update-all-log"', $template);
		Assert::contains("$.post('{{ update_url }}', { sku: item.sku }", $template, 'each item must reuse the single-update endpoint');
		Assert::contains('var percent = Math.round(position / total * 100);', $template);
		Assert::contains('if (resp.error) {', $template, 'a failed item must be logged without aborting the queue');
	},

	'locales: the batch labels exist in en-gb, ru-ua and uk-ua' => static function (): void {
		$keys = [
			'text_update_all', 'text_update_all_hint', 'text_confirm_update_all',
			'text_update_all_progress', 'text_update_all_done', 'text_update_all_partial',
			'text_reload',
		];

		$english = store_language('en-gb');

		foreach ($keys as $key) {
			Assert::true(isset($english[$key]) && trim((string)$english[$key]) !== '', 'en-gb misses ' . $key);
		}

		Assert::contains('%s', (string)$english['text_confirm_update_all'], 'the confirm label needs the count slot');
		Assert::contains('%1$s', (string)$english['text_update_all_progress'], 'positional slots keep the JS replace() unambiguous');
		Assert::contains('%3$s', (string)$english['text_update_all_progress']);

		foreach (STORE_LOCALES as $locale) {
			$localized = store_language($locale);

			foreach ($keys as $key) {
				Assert::true(isset($localized[$key]) && trim((string)$localized[$key]) !== '', $locale . ' misses ' . $key);
			}
		}

		$english_keys = array_keys($english);

		foreach (STORE_LOCALES as $locale) {
			$localized_keys = array_keys(store_language($locale));

			Assert::same([], array_values(array_diff($english_keys, $localized_keys)), $locale . ' misses store keys');
			Assert::same([], array_values(array_diff($localized_keys, $english_keys)), $locale . ' has unknown store keys');
		}
	},
];
