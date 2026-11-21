<?php
/**
 * Landing page renderer (storefront).
 *
 * Resolves a landing page by landing_id (SEO decode sets it) or slug, gates it
 * on status/publish window (an HMAC preview token from the admin builder
 * bypasses the gate), and renders its blocks as real storefront sections.
 *
 * Builder requests (`lp_builder=1` + valid token) additionally get the
 * in-iframe editing script, and `only_block=<id>` returns a single re-rendered
 * section, which is how the builder patches one block without a reload.
 *
 * @package    DockerCart
 * @subpackage Controller
 */

declare(strict_types=1);

class ControllerLandingPage extends Controller {
	private $default_language_id = 0;

	public function index() {
		$this->load->language('landing/landing');
		$this->load->model('design/landing');

		$language_id = (int)$this->config->get('config_language_id');
		$this->default_language_id = $this->resolveDefaultLanguageId();

		$landing = false;

		if (isset($this->request->get['landing_id'])) {
			$landing = $this->model_design_landing->getLanding((int)$this->request->get['landing_id']);
		} elseif (isset($this->request->get['slug'])) {
			$slug = \Dockercart\Landing\Url::normalizeSlug((string)$this->request->get['slug']);

			if ($slug !== '') {
				$landing = $this->model_design_landing->getLandingBySlug($slug, $language_id);
			}
		}

		// A landing owned by an information page is that page's composed body:
		// only the information route (which plants information_id next to
		// landing_id) may render it — direct landing_id / lp-<id> hits, valid
		// preview tokens included, stay a 404. Its URL, meta and visibility
		// belong to the information page.
		$owner_information_id = $landing ? (int)($landing['information_id'] ?? 0) : 0;

		if ($owner_information_id > 0
			&& (!isset($this->request->get['information_id']) || (int)$this->request->get['information_id'] !== $owner_information_id)) {
			return $this->respond($this->notFound());
		}

		$preview_token = isset($this->request->get['preview_token']) ? (string)$this->request->get['preview_token'] : '';
		$preview = false;

		if ($landing && $preview_token !== '') {
			$preview = \Dockercart\Landing\Url::verifyPreviewToken(
				(int)$landing['landing_id'],
				$preview_token,
				(string)$this->config->get('config_encryption')
			);
		}

		$builder = $preview
			&& isset($this->request->get['lp_builder'])
			&& (string)$this->request->get['lp_builder'] === '1';

		$information_info = false;

		if ($owner_information_id > 0) {
			// Owned pages take their visibility from the information page
			// (status + store + language); a preview token bypasses the gate
			// so the builder canvas keeps working on disabled pages.
			$this->load->model('catalog/information');

			$information_info = $this->model_catalog_information->getInformation($owner_information_id);

			if (!$preview && !$information_info) {
				return $this->respond($this->notFound());
			}
		} elseif (!$landing || (!$preview && !$this->model_design_landing->isLive($landing))) {
			return $this->respond($this->notFound());
		}

		$landing_id = (int)$landing['landing_id'];

		if ($preview) {
			$this->response->addHeader('X-Robots-Tag: noindex');
		}

		// Single block re-render (builder property change). A widget inside a
		// section column re-renders as the inner markup (no section wrapper);
		// a section re-renders with its columns' widgets.
		if ($builder && isset($this->request->get['only_block'])) {
			$block = $this->model_design_landing->getBlock((int)$this->request->get['only_block'], $landing_id);

			$this->response->addHeader('Content-Type: text/html; charset=utf-8');

			if (!$block) {
				return $this->respond('');
			}

			if ((int)$block['parent_id'] > 0) {
				return $this->respond($this->renderBlockInner($block, $language_id, true));
			}

			$children = [];

			if (\Dockercart\Landing\Blocks::isContainer((string)$block['code'])) {
				foreach ($this->model_design_landing->getBlockChildren((int)$block['block_id']) as $child) {
					$children[(int)$block['block_id']][(int)$child['slot']][] = $child;
				}
			}

			return $this->respond($this->renderBlock($block, $children, $language_id, true));
		}

		// Unsaved live preview (builder typing). Same renderers as above, but
		// the block comes from the POSTed JSON payload instead of the DB, so
		// typing never writes, bumps the version or reloads the iframe: the
		// parent swaps just this node (lp:replace). Read-only by construction.
		if ($builder && isset($this->request->get['preview_block'])) {
			$this->response->addHeader('Content-Type: text/html; charset=utf-8');

			return $this->respond($this->previewBlock($language_id));
		}

		if (!$preview && !$owner_information_id) {
			$this->model_design_landing->incrementViewed($landing_id);
		}

		$descriptions = $this->model_design_landing->getLandingDescriptions($landing_id);
		$description = $descriptions[$language_id] ?? $descriptions[$this->default_language_id] ?? [];

		if ($owner_information_id > 0) {
			// Title, meta and canonical belong to the information page; the
			// chrome is always the full store theme (never bare).
			$name = $information_info ? (string)$information_info['title'] : (string)$landing['name'];
			$meta_title = $information_info ? trim((string)$information_info['meta_title']) : '';
			$meta_description = $information_info ? trim((string)$information_info['meta_description']) : '';
			$meta_keyword = $information_info ? trim((string)$information_info['meta_keyword']) : '';
			$page_href = $this->url->link('information/information', 'information_id=' . $owner_information_id);
			$chrome = 'full';
		} else {
			$name = (string)$landing['name'];
			$meta_title = trim((string)($description['meta_title'] ?? ''));
			$meta_description = trim((string)($description['meta_description'] ?? ''));
			$meta_keyword = trim((string)($description['meta_keyword'] ?? ''));
			$page_href = $this->url->link('landing/page', 'landing_id=' . $landing_id);
			$chrome = ((string)$landing['chrome'] === 'bare') ? 'bare' : 'full';
		}

		$this->document->setTitle($meta_title !== '' ? $meta_title : $name);
		$this->document->setDescription($meta_description !== '' ? $meta_description : $name);
		$this->document->setKeywords($meta_keyword);

		$data['breadcrumbs'] = [
			[
				'text' => $this->language->get('text_home'),
				'href' => $this->url->link('common/home'),
			],
			[
				'text' => $name,
				'href' => $page_href,
			],
		];

		if (!$preview) {
			$this->document->addLink($page_href, 'canonical');
		}

		if (!empty($landing['noindex'])) {
			$this->response->addHeader('X-Robots-Tag: noindex');
		}

		$language_texts = [
			'text_home' => $this->language->get('text_home'),
			'text_empty_page' => $this->language->get('text_empty_page'),
			'text_builder_empty' => $this->language->get('text_builder_empty'),
			'text_view_all' => $this->language->get('text_view_all'),
		];

		// The external Preview tab (preview_token without lp_builder) gets a
		// fixed notice with a way back into the builder. The URL arrives
		// encrypted so the admin token is not exposed to whoever has the link.
		$preview_bar = null;

		if ($preview && !$builder && isset($this->request->get['builder_back']) && is_string($this->request->get['builder_back'])) {
			$back_url = '';

			try {
				$decrypted = (new \Encryption())->decrypt(
					(string)$this->config->get('config_encryption'),
					(string)$this->request->get['builder_back']
				);

				if (is_string($decrypted) && $this->isBuilderBackUrl($decrypted)) {
					$back_url = $decrypted;
				}
			} catch (\Throwable $e) {
				$back_url = '';
			}

			if ($back_url !== '') {
				$preview_bar = ['back_url' => $back_url];
			}
		}

		// Two-level tree: top-level rows render as full-width sections, the
		// children of a `section` container render inside its columns.
		$children = [];
		$top = [];

		foreach ($this->model_design_landing->getBlocks($landing_id) as $block) {
			if (!$builder && !(int)$block['status']) {
				continue;
			}

			if ((int)$block['parent_id'] > 0) {
				$children[(int)$block['parent_id']][(int)$block['slot']][] = $block;
			} else {
				$top[] = $block;
			}
		}

		$data['blocks'] = [];

		foreach ($top as $block) {
			$data['blocks'][] = [
				'block_id' => (int)$block['block_id'],
				'html'     => $this->renderBlock($block, $children, $language_id, $builder),
			];
		}

		$data['landing'] = [
			'landing_id' => $landing_id,
			'name'       => $name,
			'chrome'     => $chrome,
			'custom_css' => (string)$landing['custom_css'],
			'noindex'    => (int)$landing['noindex'],
		];

		$data['builder'] = $builder;
		$data['heading_title'] = $name;
		$data['chrome'] = $data['landing']['chrome'];
		$data['custom_css'] = $data['landing']['custom_css'];
		// Global landing tokens from the builder's Site Settings panel. Block-level
		// styles no longer exist, so every landing inherits the same variables.
		$data['design_style'] = \Dockercart\Landing\Design::styleAttribute(
			\Dockercart\Landing\Design::fromConfig($this->config)
		);
		$data['text_builder_empty'] = $language_texts['text_builder_empty'];
		$data['text_empty_page'] = $language_texts['text_empty_page'];
		$data['landing_texts'] = $language_texts;
		$data['preview_bar'] = $preview_bar;
		$data['text_preview_mode'] = $this->language->get('text_preview_mode');
		$data['text_back_to_builder'] = $this->language->get('text_back_to_builder');

		if ($builder) {
			$this->document->addScript('catalog/view/javascript/sortable.min.js');
			$this->document->addScript('catalog/view/javascript/dockercart_landing_builder.js?ts=' . (string)@filemtime(DIR_APPLICATION . 'view/javascript/dockercart_landing_builder.js'));
		}

		// Styles for the native editor blocks (features/steps/faq/statement).
		// Loaded here, not from a theme template, so any store theme gets them.
		$this->document->addStyle(
			'catalog/view/stylesheet/landing/landing-blocks.css?v=' . (int)@filemtime(DIR_APPLICATION . 'view/stylesheet/landing/landing-blocks.css')
		);
		// Demo-page editorial styling is scoped to blocks carrying .lp-story,
		// so it never leaks into other landing pages and needs no page CSS.
		$this->document->addStyle(
			'catalog/view/stylesheet/landing/landing-editorial.css?v=' . (int)@filemtime(DIR_APPLICATION . 'view/stylesheet/landing/landing-editorial.css')
		);

		$this->document->addScript('catalog/view/javascript/dockercart_landing.js');

		$data['column_left'] = '';
		$data['column_right'] = '';
		$data['content_top'] = '';
		$data['content_bottom'] = '';
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		return $this->respond($this->load->view('landing/page', $data));
	}

	/**
	 * Only accept a decrypted back link to this host's admin builder route.
	 */
	private function isBuilderBackUrl(string $url): bool {
		$parts = parse_url($url);

		if (!is_array($parts) || !in_array((string)($parts['scheme'] ?? ''), ['http', 'https'], true)) {
			return false;
		}

		$host = (string)($this->request->server['HTTP_HOST'] ?? '');
		$host = preg_replace('/:\d+$/', '', $host);

		if (strtolower((string)($parts['host'] ?? '')) !== strtolower((string)$host)) {
			return false;
		}

		if (substr((string)($parts['path'] ?? ''), -strlen('/admin/index.php')) !== '/admin/index.php') {
			return false;
		}

		parse_str((string)($parts['query'] ?? ''), $query);

		return ($query['route'] ?? '') === 'design/landing/builder';
	}

	/**
	 * Emits the HTML and returns it, so the page can also be rendered as a
	 * sub-controller (common/home delegates here when a landing is the home page).
	 */
	private function respond(string $html): string {
		$this->response->setOutput($html);

		return $html;
	}

	/**
	 * Renders one top-level block wrapped in its section shell.
	 *
	 * A `section` container renders its columns' widgets instead of a panel
	 * (the children come grouped by block_id/slot from the caller).
	 *
	 * @param array<string, mixed> $block
	 * @param array<int, array<int, array<int, array<string, mixed>>>> $children block_id => slot => ordered widgets
	 */
	private function renderBlock(array $block, array $children, int $language_id, bool $builder): string {
		$code = (string)$block['code'];

		if (!\Dockercart\Landing\Blocks::exists($code)) {
			return '';
		}

		$block_data = $this->blockContext($block, $language_id, $builder);
		$settings = $block_data['settings'];

		if (\Dockercart\Landing\Blocks::isContainer($code)) {
			// A section renders its columns; each column carries the widgets
			// saved for its slot, rendered as inner (wrapper-less) blocks.
			$structure = (string)($settings['structure'] ?? '2');
			$column_count = \Dockercart\Landing\Blocks::columnCount($structure);
			$block_data['grid_class'] = \Dockercart\Landing\Blocks::gridClass($structure);
			$block_data['gap_class'] = \Dockercart\Landing\Blocks::gapClass((string)($settings['gap'] ?? 'md'));
			$block_data['columns'] = [];

			for ($slot = 0; $slot < $column_count; $slot++) {
				$html = '';

				foreach ($children[(int)$block['block_id']][$slot] ?? [] as $child) {
					$html .= $this->renderBlockInner($child, $language_id, $builder);
				}

				$block_data['columns'][] = [
					'slot'  => $slot,
					'class' => \Dockercart\Landing\Blocks::columnClass($structure, $slot),
					'html'  => $html,
				];
			}

			$body = $this->load->view('landing/blocks/section', $block_data);
		} else {
			$block_data = $this->enrichBlock($block_data);

			$body = $this->load->view('landing/blocks/' . $code, $block_data);
		}

		$container = ((string)($settings['container'] ?? 'boxed') === 'full')
			? 'w-full'
			: 'mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8';

		$body_classes = $container . ' ' . $this->alignClass($settings['align'] ?? 'left');

		if ($block_data['has_wrapper_bg']) {
			$body_classes .= ' ' . ($block_data['wrapper_is_dark'] ? 'text-white' : 'text-gray-900');
		}

		$classes = \Dockercart\Landing\Blocks::wrapperClasses($code, $settings, $builder);

		if ($this->hasOverlay($settings)) {
			$classes .= ' lp-block--bg-image';
		}

		if ($this->hasMobileBackground($settings)) {
			$classes .= ' lp-block--bg-mobile';
		}

		$style = $this->overlayStyleAttribute($code, $settings);

		$attributes = ' class="' . htmlspecialchars($classes, ENT_QUOTES, 'UTF-8') . '"';

		if ($style !== '') {
			$attributes .= ' style="' . htmlspecialchars($style, ENT_QUOTES, 'UTF-8') . '"';
		}

		if (!empty($settings['anchor'])) {
			$anchor = preg_replace('/[^A-Za-z0-9_-]/', '', (string)$settings['anchor']);

			if ($anchor !== '') {
				$attributes .= ' id="' . htmlspecialchars($anchor, ENT_QUOTES, 'UTF-8') . '"';
			}
		}

		$attributes .= ' data-landing-block="' . (int)$block['block_id'] . '" data-block-code="' . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . '"';

		if (\Dockercart\Landing\Blocks::isContainer($code)) {
			$attributes .= ' data-lp-container="1"';
		}

		$attributes .= $this->builderStateAttributes($block, $settings, $builder);

		return '<section' . $attributes . '><div class="lp-block__body ' . $body_classes . '">' . $body . '</div></section>';
	}

	/**
	 * Shared render context of a block: normalised settings, per-language
	 * content and the wrapper-background palette both shells agree on.
	 *
	 * Blocks that bring their own panel (hero, cta, text) use the palette to
	 * skip their panel when the wrapper already paints a background, and to
	 * inherit its text colour (derived from the background luminance) instead
	 * of rendering a frame inside a frame with unreadable text.
	 *
	 * @param array<string, mixed> $block
	 * @return array<string, mixed>
	 */
	private function blockContext(array $block, int $language_id, bool $builder): array {
		$code = (string)$block['code'];

		$settings = \Dockercart\Landing\Blocks::normalizeSettings($code, (array)$block['settings']);
		$content = $this->blockContent($code, (array)$block['content'], $language_id, !$builder);

		// Links are stored as canonical routes so the storefront can resolve
		// SEO keywords, the language prefix and the scheme at request time.
		[$settings, $content] = $this->resolveLinkFields($code, $settings, $content);

		$bg_type = (string)($settings['bg_type'] ?? 'none');
		$has_wrapper_bg = in_array($bg_type, ['color', 'image'], true);
		$wrapper_is_dark = true;

		if ($bg_type === 'color') {
			$wrapper_is_dark = !$this->isLightColor((string)($settings['bg_color'] ?? ''));
		}

		return [
			'block_id'  => (int)$block['block_id'],
			'code'      => $code,
			'settings'  => $settings,
			'content'   => $content,
			'builder'   => $builder,
			'texts'     => [
				'text_view_all' => $this->language->get('text_view_all'),
			],
			'has_wrapper_bg'       => $has_wrapper_bg,
			'wrapper_is_dark'     => $wrapper_is_dark,
			'wrapper_text_class'  => $has_wrapper_bg ? ($wrapper_is_dark ? 'text-white' : 'text-gray-900') : '',
			'wrapper_muted_class' => $has_wrapper_bg ? ($wrapper_is_dark ? 'text-white/80' : 'text-gray-600') : '',
			'wrapper_prose_class' => $has_wrapper_bg && $wrapper_is_dark ? 'prose-invert' : '',
		];
	}

	/**
	 * Text alignment class of the `align` wrapper setting.
	 *
	 * @param mixed $value
	 */
	private function alignClass($value): string {
		return [
			'left' => 'text-left',
			'center' => 'text-center',
			'right' => 'text-right',
		][(string)($value ?? 'left')] ?? 'text-left';
	}

	/**
	 * Builder-mode state attributes (a disabled block, hidden devices) shared
	 * by the section shell and the widget shell.
	 *
	 * @param array<string, mixed> $block
	 * @param array<string, mixed> $settings
	 */
	private function builderStateAttributes(array $block, array $settings, bool $builder): string {
		if (!$builder) {
			return '';
		}

		$attributes = '';

		if (empty($block['status'])) {
			$attributes .= ' data-lp-hidden="1"';
		}

		$hidden_devices = [];

		foreach (['desktop', 'tablet', 'mobile'] as $device) {
			if (!empty($settings['hide_' . $device])) {
				$hidden_devices[] = $device;
			}
		}

		if ($hidden_devices) {
			$attributes .= ' data-lp-hide="' . implode(',', $hidden_devices) . '"';
		}

		return $attributes;
	}

	/**
	 * Whether the wrapper paints a background image overlay (0 never does).
	 *
	 * @param array<string, mixed> $settings
	 */
	private function hasOverlay(array $settings): bool {
		return \Dockercart\Landing\Blocks::overlay($settings) > 0;
	}

	/**
	 * @param array<string, mixed> $settings
	 */
	private function hasMobileBackground(array $settings): bool {
		return (string)($settings['bg_type'] ?? 'none') === 'image' && !empty($settings['bg_image_mobile']);
	}

	/**
	 * The wrapper style attribute with the overlay CSS variable folded in.
	 *
	 * @param array<string, mixed> $settings
	 */
	private function overlayStyleAttribute(string $code, array $settings): string {
		$style = \Dockercart\Landing\Blocks::wrapperStyleAttribute($code, $settings);

		$overlay = \Dockercart\Landing\Blocks::overlay($settings);

		if ($overlay > 0) {
			$style .= ($style !== '' ? ';' : '') . '--lp-overlay:' . rtrim(rtrim(number_format($overlay / 100, 2, '.', ''), '0'), '.');
		}

		return $style;
	}

	/**
	 * Renders a widget inside a section column: the panel template without the
	 * section shell - the container, padding and background belong to the
	 * section, the widget keeps its own panel, background colour, padding,
	 * alignment and per-device visibility.
	 *
	 * @param array<string, mixed> $block
	 */
	private function renderBlockInner(array $block, int $language_id, bool $builder): string {
		$code = (string)$block['code'];

		if (!\Dockercart\Landing\Blocks::exists($code)) {
			return '';
		}

		$block_data = $this->blockContext($block, $language_id, $builder);
		$settings = $block_data['settings'];

		$block_data = $this->enrichBlock($block_data);

		$body = $this->load->view('landing/blocks/' . $code, $block_data);

		$classes = ['lp-widget', 'lp-widget--' . $code];

		if (!$builder) {
			$visibility = \Dockercart\Landing\Blocks::visibilityClass($settings);

			if ($visibility !== '') {
				$classes[] = $visibility;
			}
		}

		if (!empty($settings['custom_class'])) {
			$classes[] = (string)$settings['custom_class'];
		}

		// Widgets inside a column: an untouched default padding ('lg', no
		// background of its own) is dropped so columns stay Elementor-tight -
		// the section owns the vertical rhythm. A painted widget (bg colour /
		// image) keeps its padding, and any explicit padding choice applies.
		if ((string)($settings['padding'] ?? 'lg') === 'lg'
			&& (string)($settings['bg_type'] ?? 'none') === 'none') {
			$settings['padding'] = 'none';
		}

		$align = $this->alignClass($settings['align'] ?? 'left');

		if ($align !== 'text-left') {
			$classes[] = $align;
		}

		if ($this->hasOverlay($settings)) {
			$classes[] = 'lp-block--bg-image';
		}

		if ($this->hasMobileBackground($settings)) {
			$classes[] = 'lp-block--bg-mobile';
		}

		$style = $this->overlayStyleAttribute($code, $settings);

		$attributes = ' class="' . htmlspecialchars(implode(' ', array_filter($classes)), ENT_QUOTES, 'UTF-8') . '"';

		if ($style !== '') {
			$attributes .= ' style="' . htmlspecialchars($style, ENT_QUOTES, 'UTF-8') . '"';
		}

		$attributes .= ' data-landing-block="' . (int)$block['block_id'] . '" data-block-code="' . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . '"';
		$attributes .= $this->builderStateAttributes($block, $settings, $builder);

		return '<div' . $attributes . '>' . $body . '</div>';
	}

	/**
	 * Renders one block from an explicit payload instead of the DB (unsaved
	 * live preview for the builder, see the preview_block branch in index()).
	 *
	 * Everything the renderers need travels in the POSTed JSON: block code,
	 * settings, the edited language's content and, for sections, their current
	 * children grouped by slot. Values pass through the same
	 * Blocks::normalize*() pipeline as saved blocks, so the preview shows
	 * exactly what a save would render. No queries that write.
	 */
	private function previewBlock(int $language_id): string {
		$raw = file_get_contents('php://input');
		$data = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;

		if (!is_array($data)) {
			return '';
		}

		$code = (string)($data['code'] ?? '');

		if (!\Dockercart\Landing\Blocks::exists($code)) {
			return '';
		}

		$preview_language_id = isset($data['language_id']) ? (int)$data['language_id'] : $language_id;

		if ($preview_language_id <= 0) {
			$preview_language_id = $language_id;
		}

		$block = [
			'block_id' => (int)($data['block_id'] ?? 0),
			'code'     => $code,
			'status'   => !empty($data['status']) ? 1 : 0,
			'settings' => \Dockercart\Landing\Blocks::normalizeSettings($code, (array)($data['settings'] ?? [])),
			'content'  => [$preview_language_id => \Dockercart\Landing\Blocks::normalizeContent($code, (array)($data['content'] ?? []))],
		];

		if (\Dockercart\Landing\Blocks::isContainer($code)) {
			$grouped = [];

			foreach ((array)($data['children'] ?? []) as $child) {
				if (!is_array($child)) {
					continue;
				}

				$child_code = (string)($child['code'] ?? '');

				if (!\Dockercart\Landing\Blocks::exists($child_code) || \Dockercart\Landing\Blocks::isContainer($child_code)) {
					continue;
				}

				$slot = max(0, (int)($child['slot'] ?? 0));

				$grouped[$slot][] = [
					'block_id' => (int)($child['block_id'] ?? 0),
					'code'     => $child_code,
					'status'   => !empty($child['status']) ? 1 : 0,
					'settings' => \Dockercart\Landing\Blocks::normalizeSettings($child_code, (array)($child['settings'] ?? [])),
					'content'  => [$preview_language_id => \Dockercart\Landing\Blocks::normalizeContent($child_code, (array)($child['content'] ?? []))],
				];
			}

			return $this->renderBlock($block, [(int)$block['block_id'] => $grouped], $preview_language_id, true);
		}

		$block['parent_id'] = (int)($data['parent_id'] ?? 0);

		if ((int)$block['parent_id'] > 0) {
			return $this->renderBlockInner($block, $preview_language_id, true);
		}

		return $this->renderBlock($block, [], $preview_language_id, true);
	}

	/**
	 * Rewrites stored link fields to the URLs the storefront actually uses.
	 *
	 * The builder stores a canonical relative route ("index.php?route=…" or
	 * the shorter "route=…" used by banners). Resolving it here — instead of
	 * baking a keyword into the saved block — keeps SEO URLs, the language
	 * prefix and HTTPS correct for every request, and fixes links saved before
	 * the builder had an entity picker. Custom URLs pass through untouched.
	 *
	 * @param array<string, mixed> $settings
	 * @param array<string, mixed> $content
	 * @return array{0: array<string, mixed>, 1: array<string, mixed>}
	 */
	private function resolveLinkFields(string $code, array $settings, array $content): array {
		foreach (\Dockercart\Landing\Blocks::fields($code) as $field) {
			$key = (string)$field['key'];

			if (!empty($field['localized'])) {
				$target =& $content;
			} else {
				$target =& $settings;
			}

			if ($field['type'] === 'link') {
				$target[$key] = $this->resolveLinkUrl((string)($target[$key] ?? ''));
			} elseif ($field['type'] === 'repeater' && isset($target[$key]) && is_array($target[$key])) {
				foreach ($target[$key] as $index => $row) {
					if (!is_array($row)) {
						continue;
					}

					foreach ((array)($field['fields'] ?? []) as $sub) {
						if (($sub['type'] ?? '') !== 'link') {
							continue;
						}

						$sub_key = (string)$sub['key'];
						$target[$key][$index][$sub_key] = $this->resolveLinkUrl((string)($row[$sub_key] ?? ''));
					}
				}
			}

			unset($target);
		}

		return [$settings, $content];
	}

	/**
	 * Turns a stored route link into a storefront URL (SEO-aware when enabled).
	 */
	private function resolveLinkUrl(string $link): string {
		if ($link === '') {
			return '';
		}

		// Defensive: storage is sanitised, but imported data must never become
		// an executable href even if it reaches this path.
		if (preg_match('/^\s*(javascript|vbscript|data\s*:\s*text\/html)/i', $link)) {
			return '';
		}

		$clean = ltrim(html_entity_decode($link, ENT_QUOTES, 'UTF-8'), '/');

		if (preg_match('#^(?:index\.php)?\?route=([^&]+)&(.+)$#i', $clean, $matches)
			|| preg_match('#^route=([^&]+)&(.+)$#i', $clean, $matches)) {
			return $this->url->link(urldecode($matches[1]), $matches[2], true);
		}

		return $link;
	}

	/**
	 * Block specific data (products, categories, banners, images…).
	 *
	 * @param array<string, mixed> $block_data
	 * @return array<string, mixed>
	 */
	private function enrichBlock(array $block_data): array {
		$code = (string)$block_data['code'];
		$settings = $block_data['settings'];
		$content = $block_data['content'];

		$this->load->model('tool/image');

		switch ($code) {
			case 'hero':
				$block_data['text_color'] = ((string)($settings['text_color'] ?? 'light') === 'dark') ? 'dark' : 'light';
				$block_data['height_class'] = $this->heightClass((string)($settings['height'] ?? 'lg'));
				$block_data['position_class'] = [
					'left' => 'items-start text-left',
					'center' => 'items-center text-center',
					'right' => 'items-end text-right',
				][(string)($settings['content_position'] ?? 'left')] ?? 'items-start text-left';
				break;

			case 'image':
				$block_data['image'] = $this->imageUrl((string)($settings['image'] ?? ''), 1400, 900);
				$block_data['image_mobile'] = $this->imageUrl((string)($settings['image_mobile'] ?? ''), 900, 900);
				$block_data['ratio_class'] = [
					'auto' => '',
					'square' => 'aspect-square',
					'video' => 'aspect-video',
					'wide' => 'aspect-[21/9]',
				][(string)($settings['ratio'] ?? 'auto')] ?? '';
				break;

			case 'image_text':
				$block_data['image'] = $this->imageUrl((string)($settings['image'] ?? ''), 900, 900);
				$block_data['ratio_class'] = [
					'auto' => '',
					'square' => 'aspect-square',
					'video' => 'aspect-video',
				][(string)($settings['ratio'] ?? 'square')] ?? 'aspect-square';
				$block_data['bullets'] = $this->bulletLines((string)($content['bullets'] ?? ''));
				break;

			case 'cta':
				break;

			case 'products':
			case 'products_carousel':
				[$block_data, $cards] = $this->productBlockContext($block_data, (string)($settings['columns'] ?? '4'));

				$source = [
					'source' => (string)($settings['source'] ?? 'manual'),
					'product_ids' => (array)($settings['product_ids'] ?? []),
					'category_id' => (int)($settings['category_id'] ?? 0),
					'limit' => (int)($settings['limit'] ?? 8),
				];

				if ($source['source'] === 'manual' && !$source['product_ids']) {
					break;
				}

				if ($source['source'] === 'category' && $source['category_id'] <= 0) {
					break;
				}

				$block_data['products'] = $cards->fromSource($source, ['width' => 420, 'height' => 420]);

				if ($code === 'products_carousel') {
					$per_view = max(1, min(6, (int)($settings['per_view'] ?? 4)));
					$block_data['per_view'] = $per_view;
					$block_data['item_width_class'] = $this->carouselItemClass($per_view);
				}
				break;

			case 'categories':
				$this->load->model('catalog/category');

				$category_ids = array_values(array_map('intval', (array)($settings['category_ids'] ?? [])));
				$limit = max(1, (int)($settings['limit'] ?? 6));

				if ((string)($settings['source'] ?? 'manual') === 'parent') {
					$category_ids = [];

					foreach ($this->model_catalog_category->getCategories((int)($settings['parent_category_id'] ?? 0)) as $category) {
						$category_ids[] = (int)$category['category_id'];

						if (count($category_ids) >= $limit) {
							break;
						}
					}
				}

				$block_data['categories'] = [];

				$categories = $category_ids ? $this->model_catalog_category->getCategoriesByIds($category_ids) : [];

				foreach ($category_ids as $category_id) {
					if (!isset($categories[$category_id])) {
						continue;
					}

					$category = $categories[$category_id];

					$block_data['categories'][] = [
						'category_id' => (int)$category['category_id'],
						// Names are stored html-encoded (the classic form path) and the
						// theme templates render them passthrough - keep that here too.
						'name'        => (string)$category['name'],
						'image'       => $this->imageUrl((string)$category['image'], 500, 380),
						'href'        => $this->url->link('product/category', 'path=' . (int)$category['category_id']),
					];
				}

				$block_data['columns_class'] = $this->columnsClass((string)($settings['columns'] ?? '3'), 6);
				$block_data['image_height_class'] = [
					'sm' => 'h-40',
					'md' => 'h-56',
					'lg' => 'h-72',
				][(string)($settings['image_height'] ?? 'md')] ?? 'h-56';
				break;

			case 'banner':
				$block_data['slides'] = $this->bannerSlides((int)($settings['banner_id'] ?? 0), (string)($settings['height'] ?? 'md'));
				$block_data['autoplay'] = max(0, min(30, (int)($settings['autoplay'] ?? 6)));
				$block_data['show_arrows'] = !empty($settings['show_arrows']);
				break;

			case 'spacer':
				$block_data['height_class'] = [
					'xs' => 'h-4',
					'sm' => 'h-8',
					'md' => 'h-16',
					'lg' => 'h-24',
					'xl' => 'h-40',
				][(string)($settings['height'] ?? 'md')] ?? 'h-16';
				break;
		}

		return $block_data;
	}

	/**
	 * The product-card context shared by the `products` and `products_carousel`
	 * blocks: the flags product_card_widget.twig expects from a module
	 * controller, the resolved column classes and the ProductCards instance
	 * that resolves the source.
	 *
	 * @param array<string, mixed> $block_data
	 * @return array{0: array<string, mixed>, 1: \Dockercart\Catalog\ProductCards}
	 */
	private function productBlockContext(array $block_data, string $columns): array {
		$cards = new \Dockercart\Catalog\ProductCards($this->registry);

		$block_data['products'] = [];
		$block_data['accent_color'] = 'blue-600';
		$block_data['btn_cart_classes'] = 'bg-blue-600 text-white hover:bg-blue-700';
		$block_data['btn_quick_hover'] = 'hover:bg-blue-600';
		$block_data['show_distribution'] = (int)$this->config->get('config_review_show_distribution');
		$block_data['call_for_price_status'] = (int)$this->config->get('dockercart_theme_call_for_price_status');
		$block_data['call_for_price_phone'] = (string)$this->config->get('config_telephone');
		$block_data['call_for_price_mode'] = ($this->config->get('dockercart_theme_call_for_price_mode') === 'call') ? 'call' : 'request';
		$block_data['columns_class'] = $this->columnsClass($columns, 5);
		$block_data['texts'] = array_merge($block_data['texts'], $cards->texts());

		return [$block_data, $cards];
	}

	/**
	 * Per-language content with a fallback to the store language for empty fields.
	 *
	 * The fallback is a public-page convenience (a partially translated page
	 * still shows something). In builder mode it is OFF: the canvas must show
	 * exactly what the properties panel edits for the selected language, or an
	 * empty field looks like it contains the default language's copy.
	 *
	 * @param array<int, array<string, mixed>> $content
	 * @return array<string, mixed>
	 */
	private function blockContent(string $code, array $content, int $language_id, bool $use_fallback = true): array {
		$current = \Dockercart\Landing\Blocks::normalizeContent($code, (array)($content[$language_id] ?? []));
		$default = $use_fallback
			? \Dockercart\Landing\Blocks::normalizeContent($code, (array)($content[$this->default_language_id] ?? []))
			: [];

		foreach (\Dockercart\Landing\Blocks::localizedFields($code) as $field) {
			$key = $field['key'];

			if ($this->isEmptyValue($current[$key] ?? null) && !$this->isEmptyValue($default[$key] ?? null)) {
				$current[$key] = $default[$key];
			}
		}

		return $current;
	}

	/**
	 * @param mixed $value
	 */
	private function isEmptyValue($value): bool {
		if (is_array($value)) {
			return $value === [];
		}

		return $value === null || $value === '' || $value === 0 || $value === '0';
	}

	private function resolveDefaultLanguageId(): int {
		$this->load->model('localisation/language');

		$default_code = (string)$this->config->get('config_language');

		foreach ($this->model_localisation_language->getLanguages() as $language) {
			if ((string)$language['code'] === $default_code) {
				return (int)$language['language_id'];
			}
		}

		return (int)$this->config->get('config_language_id');
	}

	private function imageUrl(string $path, int $width, int $height): string {
		$path = trim($path);

		if ($path === '') {
			return '';
		}

		return (string)$this->model_tool_image->resize($path, $width, $height);
	}

	private function heightClass(string $height): string {
		return [
			'sm' => 'min-h-[320px]',
			'md' => 'min-h-[440px]',
			'lg' => 'min-h-[560px]',
			'full' => 'min-h-screen',
		][$height] ?? 'min-h-[560px]';
	}

	private function columnsClass(string $columns, int $max): string {
		$columns = (int)$columns;

		$map = [
			2 => 'sm:grid-cols-2',
			3 => 'sm:grid-cols-2 lg:grid-cols-3',
			4 => 'sm:grid-cols-2 lg:grid-cols-4',
			5 => 'sm:grid-cols-2 lg:grid-cols-5',
			6 => 'sm:grid-cols-3 lg:grid-cols-6',
		];

		if (!isset($map[$columns]) || $columns > $max) {
			return $map[3];
		}

		return $map[$columns];
	}

	private function carouselItemClass(int $per_view): string {
		return [
			1 => 'w-full',
			2 => 'w-1/2',
			3 => 'w-1/3',
			4 => 'w-1/2 sm:w-1/3 lg:w-1/4',
			5 => 'w-1/2 sm:w-1/3 lg:w-1/5',
			6 => 'w-1/2 sm:w-1/4 lg:w-1/6',
		][$per_view] ?? 'w-1/2 sm:w-1/3 lg:w-1/4';
	}

	/**
	 * @return array<int, string>
	 */
	private function bulletLines(string $bullets): array {
		$lines = preg_split('/\r\n|\r|\n/', $bullets);

		$lines = array_map(static function ($line) {
			return trim($line);
		}, $lines ?: []);

		return array_values(array_filter($lines, static function ($line) {
			return $line !== '';
		}));
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function bannerSlides(int $banner_id, string $height): array {
		if ($banner_id <= 0) {
			return [];
		}

		$this->load->model('design/banner');

		$sizes = [
			'sm' => [1400, 380],
			'md' => [1600, 520],
			'lg' => [1800, 760],
		];

		[$width, $image_height] = $sizes[$height] ?? $sizes['md'];

		$slides = [];

		foreach ($this->model_design_banner->getBanner($banner_id) as $row) {
			$image = '';

			if (!empty($row['image']) && is_file(DIR_IMAGE . $row['image'])) {
				$image = (string)$this->model_tool_image->resize($row['image'], $width, $image_height);
			}

			$portrait = '';

			if (!empty($row['image_portrait']) && is_file(DIR_IMAGE . $row['image_portrait'])) {
				$portrait = (string)$this->model_tool_image->resize($row['image_portrait'], 900, 1200);
			}

			// The colour lands in a style attribute: hex only, anything else
			// (including CSS fragments a broken banner row might carry) falls
			// back to the default accent.
			$color = (string)($row['primary_btn_color'] ?? '');

			if (!preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $color)) {
				$color = '#2563eb';
			}

			$slides[] = [
				// Titles/subtitles are stored html-encoded by the banner form and
				// rendered passthrough (exactly like the banner module itself):
				// decoding them back would turn a saved "&lt;script&gt;" into a
				// live tag on landing pages.
				'title'             => (string)$row['title'],
				'subtitle'          => isset($row['subtitle']) ? (string)$row['subtitle'] : '',
				'image'             => $image,
				'image_portrait'    => $portrait,
				'primary_btn_text'  => isset($row['primary_btn_text']) ? (string)$row['primary_btn_text'] : '',
				'primary_btn_color' => $color,
				'btn_text_color'    => $this->contrastColor($color),
				'link'              => $this->resolveBannerLink(isset($row['link']) ? (string)$row['link'] : ''),
				'video_type'        => isset($row['video_type']) ? (string)$row['video_type'] : '',
				'video_url'         => $this->bannerVideoUrl(isset($row['video_type']) ? (string)$row['video_type'] : '', isset($row['video']) ? (string)$row['video'] : ''),
				'content_position'  => isset($row['content_position']) ? (string)$row['content_position'] : 'left',
			];
		}

		return $slides;
	}

	private function resolveBannerLink(string $link): string {
		return $this->resolveLinkUrl($link);
	}

	private function bannerVideoUrl(string $type, string $video): string {
		if ($video === '') {
			return '';
		}

		if ($type === 'youtube') {
			return 'https://www.youtube.com/embed/' . urlencode($video) . '?autoplay=1&mute=1&loop=1&playlist=' . urlencode($video) . '&controls=0&showinfo=0&rel=0';
		}

		if ($type === 'mp4') {
			// filter_var(FILTER_VALIDATE_URL) also accepts javascript:// URLs, so
			// the scheme is pinned to http(s) here.
			if (preg_match('#^https?://#i', $video)) {
				return $video;
			}

			if (is_file(DIR_IMAGE . $video)) {
				return HTTP_SERVER . 'image/' . ltrim($video, '/');
			}
		}

		return '';
	}

	/**
	 * Whether a hex colour is light enough to need dark text on it.
	 */
	private function isLightColor(string $hex): bool {
		return $this->contrastColor($hex) === '#000000';
	}

	private function contrastColor(string $hex): string {
		$hex = ltrim($hex, '#');

		if (preg_match('/^[0-9a-fA-F]{3}$/', $hex)) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}

		if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
			return '#ffffff';
		}

		$r = hexdec(substr($hex, 0, 2));
		$g = hexdec(substr($hex, 2, 2));
		$b = hexdec(substr($hex, 4, 2));

		return ((0.299 * $r + 0.587 * $g + 0.114 * $b) / 255) > 0.5 ? '#000000' : '#ffffff';
	}

	private function notFound(): string {
		$this->document->setTitle($this->language->get('text_error'));

		$this->response->addHeader($this->request->server['SERVER_PROTOCOL'] . ' 404 Not Found');

		$data['breadcrumbs'] = [
			[
				'text' => $this->language->get('text_home'),
				'href' => $this->url->link('common/home'),
			],
			[
				'text' => $this->language->get('text_error'),
				'href' => $this->url->link('landing/page'),
			],
		];

		$data['heading_title'] = $this->language->get('text_error');
		$data['text_error'] = $this->language->get('text_error');
		$data['continue'] = $this->url->link('common/home');

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		return (string)$this->load->view('error/not_found', $data);
	}
}
