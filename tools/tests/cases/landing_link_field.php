<?php
/**
 * Landing builder link fields: type picker + entity autocomplete.
 *
 * The link field copies the banner form's workflow (custom URL or a product,
 * category, manufacturer, information page / blog post chosen from search)
 * without changing what is stored: the field still holds one URL string, so
 * storage normalisation and the storefront templates keep working unchanged.
 * These static checks guard the three files that have to agree for that to
 * hold: the block registry, the builder script and the autocomplete endpoint.
 *
 * @package    DockerCart
 * @subpackage Tests
 */

declare(strict_types=1);

use Dockercart\Landing\Blocks;

const LINK_FIELD_CONTROLLER = '/upload/admin/controller/design/landing.php';

const LINK_FIELD_BUILDER_JS = '/upload/admin/view/javascript/dockercart_landing_builder.js';

const LINK_FIELD_PAGE = '/upload/catalog/controller/landing/page.php';

const LINK_FIELD_LOCALES = ['en-gb', 'ru-ua', 'uk-ua'];

const LINK_FIELD_TYPES = ['custom', 'product', 'category', 'manufacturer', 'information', 'blog', 'contact'];

const LINK_FIELD_I18N_KEYS = [
	'text_link_custom', 'text_link_product', 'text_link_category',
	'text_link_manufacturer', 'text_link_information', 'text_link_blog',
	'text_link_contact', 'text_link_search',
];

/**
 * @return array<string, string>
 */
function link_field_sources(): array {
	return [
		'controller' => (string)file_get_contents(REPO_ROOT . LINK_FIELD_CONTROLLER),
		'builder' => (string)file_get_contents(REPO_ROOT . LINK_FIELD_BUILDER_JS),
	];
}

return [
	'link: every stored link field keeps the plain URL contract' => static function (): void {
		$links = 0;

		foreach (Blocks::all() as $block) {
			$collect = static function (array $fields) use (&$links): void {
				foreach ($fields as $field) {
					if ($field['type'] === 'link') {
						$links++;
						Assert::same('', $field['default'], 'a link must default to an empty URL');
					}
				}
			};

			$collect($block['fields']);

			foreach ($block['fields'] as $field) {
				if ($field['type'] === 'repeater') {
					foreach ($field['fields'] as $sub) {
						if ($sub['type'] === 'link') {
							$links++;
							Assert::same('', $sub['default'], 'a repeater link must default to an empty URL');
						}
					}
				}
			}
		}

		Assert::true($links >= 6, 'expected the canonical set of link fields');
	},

	'link: the builder offers the same types as the banner form' => static function (): void {
		$js = link_field_sources()['builder'];

		Assert::contains('function parseLinkUrl(value)', $js);
		Assert::contains('function buildLinkUrl(type, value)', $js);
		Assert::contains('function renderLinkControl(value, inputAttrs, id)', $js);
		Assert::contains('function bindLinkControl(control, readUrl, writeUrl)', $js);
		Assert::contains('function hydrateLinkLabels(root)', $js);

		foreach (LINK_FIELD_TYPES as $type) {
			Assert::contains("{ type: '" . $type . "'", $js, 'missing link type ' . $type);
		}

		Assert::contains("case 'link':", $js);
		Assert::contains("html += renderLinkControl(value, 'data-input', id);", $js);
		Assert::contains("html += renderLinkControl(value, 'data-repeater-key=\"' + esc(sub.key) + '\"', '');", $js, 'hero buttons must get the same control');
		Assert::contains("'route=' + def.route + '&' + def.param + '='", $js, 'entity links must be stored without index.php');
		Assert::notContains("return 'index.php?route='", $js, 'the picker must never write an index.php link');
		Assert::contains("contact:      { route: 'information/contact', param: '' }", $js, 'the built-in contact page has no entity id');
		Assert::contains("if (def.param === '') return 'route=' + def.route;", $js, 'route-only links need no id');

		foreach (['data-link-type', 'data-link-url', 'data-link-search'] as $attribute) {
			Assert::contains($attribute, $js, 'missing link control attribute ' . $attribute);
		}

		Assert::notContains('absolute z-30 mt-1 w-56', $js, 'suggestions must not float over the panel');
		Assert::contains('relative z-30 mt-1 w-full', $js, 'suggestions must span the field width');
		Assert::contains("insertAdjacentElement('afterend', list)", $js, 'suggestions must sit directly after the field');
		Assert::contains('data-link-clear title=', $js, 'the clear control needs an accessible hint');
		Assert::contains('data-lucide="x" class="w-3 h-3"', $js, 'clear must be a cross icon, not a text button');
		Assert::contains('absolute right-2 top-1/2 -translate-y-1/2', $js, 'the cross sits on the input');
		Assert::contains('class="lp-input pr-7"', $js, 'the input leaves room for the cross');
	},

	'link: the panel shows the SEO URL but persists the canonical route' => static function (): void {
		$js = link_field_sources()['builder'];
		$php = link_field_sources()['controller'];

		Assert::contains('data-link-stored=', $js, 'the displayed URL must not become the stored one');
		Assert::contains("setUrl(buildLinkUrl(type, item.id), false, item.url)", $js);
		Assert::contains('showLinkSeoUrl(', $js, 'saved links must show their SEO URL too');
		Assert::contains("'&language_id=' + encodeURIComponent(lang)", $js, 'keywords are language-specific');
		Assert::contains("type + ':' + lang + ':' + item.id", $js, 'the label/URL cache is per language');
		Assert::contains('private function linkSeoUrl(', $php);
		Assert::contains('private function linkSeoKeyword(', $php);
		Assert::contains('getProductSeoUrls(', $php);
		Assert::contains('$item[\'url\'] = $this->linkSeoUrl(', $php, 'search and id lookups must return the display URL');
		Assert::contains("return '/' . \$prefix . \$keyword;", $php, 'the panel shows a root-relative SEO path, not a full URL');
	},

	'link: SEO URLs are resolved at render time, not baked into the block' => static function (): void {
		$page = (string)file_get_contents(REPO_ROOT . LINK_FIELD_PAGE);

		Assert::contains('private function resolveLinkFields(', $page);
		Assert::contains('$this->resolveLinkFields($code, $settings, $content)', $page, 'blockContext must resolve links before rendering');
		Assert::contains('private function resolveLinkUrl(', $page);
		Assert::contains('$this->url->link(urldecode($matches[1]), $matches[2], true)', $page, 'use the SEO-aware catalog Url');
		Assert::contains('(?:index\\.php)?\\?route=', $page, 'both the builder and the banner route form must be recognised');
		Assert::contains('^route=([^&]+)&(.+)$', $page);
		Assert::contains('#^route=([^&]+)$#i', $page, 'route-only links (contact page) must resolve too');
	},

	'link: the autocomplete endpoint resolves every type and stored ids' => static function (): void {
		$php = link_field_sources()['controller'];

		Assert::contains("'manufacturer'", $php);
		Assert::contains("'information'", $php);
		Assert::contains("'blog'", $php);
		Assert::contains("isset(\$this->request->get['ids'])", $php, 'stored links need their labels restored');
		Assert::contains('private function linkEntityItems(', $php);
		Assert::contains('private function searchLinkEntities(', $php);
	},

	'link: type labels are translated in all three locales' => static function (): void {
		$php = link_field_sources()['controller'];

		foreach (LINK_FIELD_I18N_KEYS as $key) {
			Assert::contains("'" . $key . "'", $php, 'the builder bootstrap must export ' . $key);

			foreach (LINK_FIELD_LOCALES as $locale) {
				$source = (string)file_get_contents(REPO_ROOT . '/upload/admin/language/' . $locale . '/design/landing.php');
				Assert::contains("['" . $key . "']", $source, $locale . ' misses ' . $key);
			}
		}
	},
];
