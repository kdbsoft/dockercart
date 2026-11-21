<?php
/**
 * Landing display-field encoding and render escaping.
 *
 * Twig runs with autoescape disabled project-wide, so landing content is
 * protected by two explicit layers: storage normalisation (Blocks) and
 * |escape on the plain-text outputs. The page display fields (name, meta_*)
 * follow the classic form convention instead - encoded at the JSON save
 * boundary, decoded for the builder inputs. These guards pin that contract:
 *
 *   1. the JSON save encodes display fields before the model write;
 *   2. the builder receives decoded values and the twig escapes them;
 *   3. block templates escape every plain-text output, richtext stays raw;
 *   4. the iframe bridge only trusts its own parent window.
 *
 * @package    DockerCart
 * @subpackage Tests
 */

declare(strict_types=1);

const LANDING_ENCODING_FILES = [
	'controller' => 'upload/admin/controller/design/landing.php',
	'builder_twig' => 'upload/admin/view/template/design/landing_builder.twig',
	'iframe_js' => 'upload/catalog/view/javascript/dockercart_landing_builder.js',
];

const LANDING_ENCODING_BLOCK_TEMPLATES = [
	'hero' => 'upload/catalog/view/theme/dockercart/template/landing/blocks/hero.twig',
	'text' => 'upload/catalog/view/theme/dockercart/template/landing/blocks/text.twig',
	'cta' => 'upload/catalog/view/theme/dockercart/template/landing/blocks/cta.twig',
	'features' => 'upload/catalog/view/theme/dockercart/template/landing/blocks/features.twig',
	'faq' => 'upload/catalog/view/theme/dockercart/template/landing/blocks/faq.twig',
	'image' => 'upload/catalog/view/theme/dockercart/template/landing/blocks/image.twig',
];

return [
	'save: the JSON payload encodes display fields at the boundary' => static function (): void {
		$src = repo_file(LANDING_ENCODING_FILES['controller']);

		Assert::contains('$payload = $this->encodeDisplayPayload($payload);', $src, 'the JSON save must encode before the model write');

		$save = substr($src, strpos($src, 'public function save()'), strpos($src, 'public function saveSiteDesign') - strpos($src, 'public function save()'));

		Assert::true(strpos($save, 'encodeDisplayPayload($payload)') < strpos($save, 'saveLanding($landing_id'), 'encoding happens before saveLanding');
		Assert::contains('trim(htmlspecialchars($value, ENT_COMPAT, \'UTF-8\'))', $src, 'the encoding matches Request::clean/validateOwner');
	},

	'builder: display values arrive decoded and the shell escapes them' => static function (): void {
		$src = repo_file(LANDING_ENCODING_FILES['controller']);
		$twig = repo_file(LANDING_ENCODING_FILES['builder_twig']);

		Assert::contains("html_entity_decode((string)\$landing['name']", $src, 'the builder name is decoded for display');
		Assert::contains("html_entity_decode((string)(\$row[\$key] ?? '')", $src, 'the builder meta fields are decoded for display');

		Assert::contains('{{ landing.name|escape }}', $twig, 'the top-bar name input escapes the decoded value');
		Assert::contains("value=\"{{ landing.name|escape }}\"", $twig, 'the input attribute escapes the decoded value');
	},

	'templates: plain text is escaped, richtext stays raw' => static function (): void {
		$hero = repo_file(LANDING_ENCODING_BLOCK_TEMPLATES['hero']);
		$text = repo_file(LANDING_ENCODING_BLOCK_TEMPLATES['text']);
		$cta = repo_file(LANDING_ENCODING_BLOCK_TEMPLATES['cta']);
		$features = repo_file(LANDING_ENCODING_BLOCK_TEMPLATES['features']);
		$faq = repo_file(LANDING_ENCODING_BLOCK_TEMPLATES['faq']);
		$image = repo_file(LANDING_ENCODING_BLOCK_TEMPLATES['image']);

		Assert::contains('{{ content.heading|escape }}', $hero, 'hero heading is escaped');
		Assert::contains('{{ content.badge|escape }}', $hero, 'hero badge is escaped');
		Assert::contains('{{ content.subheading|escape|nl2br }}', $hero, 'hero subheading escapes before nl2br');

		Assert::contains('{{ content.heading|escape }}', $text, 'text heading is escaped');
		Assert::contains('{{ content.body|raw }}', $text, 'richtext bodies keep their (sanitised) markup');

		Assert::contains('{{ content.body|escape|nl2br }}', $cta, 'cta body is a textarea: escaped');
		Assert::contains('{{ item.text|escape|nl2br }}', $features, 'repeater texts are escaped');
		Assert::contains('{{ item.question|escape }}', $faq, 'faq questions are escaped');
		Assert::contains('{{ item.answer|escape|nl2br }}', $faq, 'faq answers are escaped');
		Assert::contains('alt="{{ content.alt|escape }}"', $image, 'attribute outputs are escaped');
	},

	'bridge: the canvas only trusts its own parent' => static function (): void {
		$js = repo_file(LANDING_ENCODING_FILES['iframe_js']);

		Assert::contains('event.source !== window.parent', $js, 'the iframe bridge must refuse foreign windows');
	},
];
