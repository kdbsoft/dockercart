<?php
/**
 * Localization: every runtime locale must expose the same keys.
 *
 * The panel is trilingual (en-gb, ru-ua, uk-ua). A key that only exists in
 * English silently renders as an empty label, so this case fails the build when
 * a new string is added to one locale only.
 *
 * @package    DockerCart
 * @subpackage Tests
 */

declare(strict_types=1);

use Dockercart\Webhook\Events;

const WEBHOOK_LOCALES = ['en-gb', 'ru-ua', 'uk-ua'];

const WEBHOOK_PAGE_LANGUAGES = [
	'setting/webhook'     => 'upload/admin/language/{locale}/setting/webhook.php',
	'setting/webhook_log' => 'upload/admin/language/{locale}/setting/webhook_log.php',
];

/**
 * @return array<string, string>
 */
function webhook_language_keys(string $relative, string $locale): array {
	$path = str_replace('{locale}', $locale, $relative);

	if (!is_file(REPO_ROOT . '/' . $path)) {
		return [];
	}

	return read_language_file(REPO_ROOT . '/' . $path);
}

/**
 * @return array<int, string> language keys referenced by a Twig template
 */
function webhook_template_keys(string $template): array {
	$source = repo_file('upload/admin/view/template/' . $template);

	// Loop variables and Twig keywords are not language keys.
	$locals = [];

	if (preg_match_all('/\{%\s*for\s+([^%]+?)\s+in\s+/', $source, $matches)) {
		foreach ($matches[1] as $list) {
			foreach (explode(',', $list) as $name) {
				$locals[] = trim($name);
			}
		}
	}

	$keys = [];

	if (preg_match_all('/\{\{\s*([a-z][a-z0-9_]*)\b/', $source, $matches)) {
		$keys = array_merge($keys, $matches[1]);
	}

	if (preg_match_all('/\{%\s*(?:if|elseif)\s+(?:not\s+)?([a-z][a-z0-9_]*)\b/', $source, $matches)) {
		$keys = array_merge($keys, $matches[1]);
	}

	return array_values(array_diff(array_unique($keys), $locals, ['not', 'is', 'same', 'as', 'in']));
}

/**
 * Variables that the controller supplies instead of a language file.
 *
 * @return array<int, string>
 */
function webhook_template_data_keys(string $controller): array {
	$source = repo_file('upload/admin/controller/' . $controller . '.php');

	preg_match_all("/\\\$data\\['([a-z0-9_]+)'\\]\\s*=/", $source, $matches);

	return array_merge($matches[1], ['header', 'column_left', 'footer', 'user_token']);
}

return [
	'localization: webhook pages define the same keys in every locale' => static function (): void {
		foreach (WEBHOOK_PAGE_LANGUAGES as $page => $path) {
			$english = webhook_language_keys($path, 'en-gb');

			Assert::true(count($english) > 0, $page . ' language file is empty');

			foreach (WEBHOOK_LOCALES as $locale) {
				$translated = webhook_language_keys($path, $locale);

				Assert::count(count($english), $translated, $page . ' [' . $locale . '] must define the same number of keys');

				foreach (array_keys($english) as $key) {
					Assert::true(
						array_key_exists($key, $translated),
						$page . ' [' . $locale . '] misses "' . $key . '"'
					);
					Assert::true(
						(string)$translated[$key] !== '',
						$page . ' [' . $locale . '] has an empty value for "' . $key . '"'
					);
				}
			}
		}
	},

	'localization: placeholder counts match the English source' => static function (): void {
		foreach (WEBHOOK_PAGE_LANGUAGES as $page => $path) {
			$english = webhook_language_keys($path, 'en-gb');

			foreach (WEBHOOK_LOCALES as $locale) {
				$translated = webhook_language_keys($path, $locale);

				foreach ($english as $key => $value) {
					preg_match_all('/%(?:\d+\$)?[sd]/', (string)$value, $en);
					preg_match_all('/%(?:\d+\$)?[sd]/', (string)($translated[$key] ?? ''), $loc);

					sort($en[0]);
					sort($loc[0]);

					Assert::same($en[0], $loc[0], $page . ' [' . $locale . '] "%s" placeholders differ for "' . $key . '"');
				}
			}
		}
	},

	'localization: sidebar entries exist in every locale' => static function (): void {
		foreach (WEBHOOK_LOCALES as $locale) {
			$keys = webhook_language_keys('upload/admin/language/{locale}/common/column_left.php', $locale);

			foreach (['text_webhooks', 'text_webhook_log'] as $key) {
				Assert::true(isset($keys[$key]) && $keys[$key] !== '', 'common/column_left [' . $locale . '] misses "' . $key . '"');
			}
		}
	},

	'localization: every key a webhook controller reads exists in all locales' => static function (): void {
		$global = [];

		foreach (WEBHOOK_LOCALES as $locale) {
			$global[$locale] = webhook_language_keys('upload/admin/language/{locale}/{locale}.php', $locale);
		}

		foreach (['setting/webhook', 'setting/webhook_log'] as $page) {
			$source = repo_file('upload/admin/controller/' . $page . '.php');

			preg_match_all("/language->get\\(\\s*'([a-z0-9_]+)'/", $source, $matches);

			Assert::true(count($matches[1]) > 0, $page . ' uses no language strings?');

			foreach (WEBHOOK_LOCALES as $locale) {
				$page_keys = webhook_language_keys('upload/admin/language/{locale}/' . $page . '.php', $locale);

				foreach (array_unique($matches[1]) as $key) {
					Assert::true(
						array_key_exists($key, $page_keys) || array_key_exists($key, $global[$locale]),
						$page . ' [' . $locale . '] reads "' . $key . '" which is not defined'
					);
				}
			}
		}
	},

	'localization: every language key used by a template is defined' => static function (): void {
		$templates = [
			'setting/webhook_list.twig'      => 'setting/webhook',
			'setting/webhook_form.twig'      => 'setting/webhook',
			'setting/webhook_log.twig'       => 'setting/webhook_log',
			'setting/webhook_log_info.twig'  => 'setting/webhook_log',
		];

		foreach ($templates as $template => $page) {
			$data_keys = webhook_template_data_keys($page);

			foreach (WEBHOOK_LOCALES as $locale) {
				$available = webhook_language_keys('upload/admin/language/{locale}/' . $page . '.php', $locale)
					+ webhook_language_keys('upload/admin/language/{locale}/{locale}.php', $locale);

				foreach (webhook_template_keys($template) as $key) {
					if (in_array($key, $data_keys, true)) {
						continue;
					}

					Assert::true(
						array_key_exists($key, $available),
						$template . ' [' . $locale . '] renders "' . $key . '" which is neither a data key nor a language string'
					);
				}
			}
		}
	},

	'localization: templates do not fall back to hardcoded English' => static function (): void {
		foreach (['setting/webhook_list.twig', 'setting/webhook_form.twig', 'setting/webhook_log.twig', 'setting/webhook_log_info.twig'] as $template) {
			Assert::notContains('|default(', repo_file('upload/admin/view/template/' . $template), $template . ' must use a translated key instead of an English default');
		}
	},

	'localization: every event description is a language key translated everywhere' => static function (): void {
		$catalogue = Events::catalogue();

		Assert::count(19, $catalogue, 'the catalogue size changed — extend the event translations');

		foreach ($catalogue as $event) {
			Assert::true(($event['description_key'] ?? '') !== '', $event['type'] . ' has no description_key');

			foreach (WEBHOOK_LOCALES as $locale) {
				$keys = webhook_language_keys('upload/admin/language/{locale}/setting/webhook.php', $locale);

				Assert::true(
					isset($keys[$event['description_key']]) && $keys[$event['description_key']] !== '',
					'setting/webhook [' . $locale . '] misses "' . $event['description_key'] . '" for ' . $event['type']
				);
			}

			// The English texts in the library are the API contract, not the panel copy.
			Assert::true($event['description'] !== '', $event['type'] . ' has no English description for the API');
		}
	},

	'localization: the panel catalogue is rendered through the translator' => static function (): void {
		$catalogue = Events::catalogueTranslated(static function (string $key): string {
			return 'de:' . $key;
		});

		foreach ($catalogue as $event) {
			Assert::same('de:' . $event['description_key'], $event['description'], $event['type'] . ' description bypassed the translator');
		}

		Assert::contains('$this->eventCatalogue()', repo_file('upload/admin/controller/setting/webhook.php'), 'the panel must use the translated catalogue');
	},

	'localization: the panel test event carries a localized message' => static function (): void {
		foreach (WEBHOOK_LOCALES as $locale) {
			$keys = webhook_language_keys('upload/admin/language/{locale}/setting/webhook.php', $locale);

			Assert::true(isset($keys['text_test_message']) && $keys['text_test_message'] !== '', $locale . ' misses text_test_message');
		}

		Assert::contains("get('text_test_message')", repo_file('upload/admin/controller/setting/webhook.php'));
	},

	'localization: the three locales exist for the webhook pages' => static function (): void {
		foreach (WEBHOOK_LOCALES as $locale) {
			foreach (array_values(WEBHOOK_PAGE_LANGUAGES) as $path) {
				Assert::true(is_file(REPO_ROOT . '/' . str_replace('{locale}', $locale, $path)), str_replace('{locale}', $locale, $path) . ' is missing');
			}
		}
	},
];
