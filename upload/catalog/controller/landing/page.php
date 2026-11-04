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

		if (!$landing || (!$preview && !$this->model_design_landing->isLive($landing))) {
			return $this->respond($this->notFound());
		}

		$landing_id = (int)$landing['landing_id'];

		if ($preview) {
			$this->response->addHeader('X-Robots-Tag: noindex');
		}

		// Single block re-render (builder property change).
		if ($builder && isset($this->request->get['only_block'])) {
			$block = $this->model_design_landing->getBlock((int)$this->request->get['only_block'], $landing_id);

			$this->response->addHeader('Content-Type: text/html; charset=utf-8');

			return $this->respond($block ? $this->renderBlock($block, $language_id, true) : '');
		}

		if (!$preview) {
			$this->model_design_landing->incrementViewed($landing_id);
		}

		$descriptions = $this->model_design_landing->getLandingDescriptions($landing_id);
		$description = $descriptions[$language_id] ?? $descriptions[$this->default_language_id] ?? [];

		$name = (string)$landing['name'];
		$meta_title = trim((string)($description['meta_title'] ?? ''));
		$meta_description = trim((string)($description['meta_description'] ?? ''));
		$meta_keyword = trim((string)($description['meta_keyword'] ?? ''));

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
				'href' => $this->url->link('landing/page', 'landing_id=' . $landing_id),
			],
		];

		if (!$preview) {
			$this->document->addLink($this->url->link('landing/page', 'landing_id=' . $landing_id), 'canonical');
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

		$data['blocks'] = [];

		foreach ($this->model_design_landing->getBlocks($landing_id) as $block) {
			if (!$builder && !(int)$block['status']) {
				continue;
			}

			$data['blocks'][] = [
				'block_id' => (int)$block['block_id'],
				'html'     => $this->renderBlock($block, $language_id, $builder),
			];
		}

		$data['landing'] = [
			'landing_id' => $landing_id,
			'name'       => $name,
			'chrome'     => ((string)$landing['chrome'] === 'bare') ? 'bare' : 'full',
			'custom_css' => (string)$landing['custom_css'],
			'noindex'    => (int)$landing['noindex'],
		];

		$data['builder'] = $builder;
		$data['heading_title'] = $name;
		$data['chrome'] = $data['landing']['chrome'];
		$data['custom_css'] = $data['landing']['custom_css'];
		$data['text_builder_empty'] = $language_texts['text_builder_empty'];
		$data['text_empty_page'] = $language_texts['text_empty_page'];
		$data['landing_texts'] = $language_texts;

		if ($builder) {
			$this->document->addScript('catalog/view/javascript/sortable.min.js');
			$this->document->addScript('catalog/view/javascript/dockercart_landing_builder.js');
		}

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
	 * Emits the HTML and returns it, so the page can also be rendered as a
	 * sub-controller (common/home delegates here when a landing is the home page).
	 */
	private function respond(string $html): string {
		$this->response->setOutput($html);

		return $html;
	}

	/**
	 * Renders one block (inner body) wrapped in its section shell.
	 *
	 * @param array<string, mixed> $block
	 */
	private function renderBlock(array $block, int $language_id, bool $builder): string {
		$code = (string)$block['code'];

		if (!\Dockercart\Landing\Blocks::exists($code)) {
			return '';
		}

		$settings = \Dockercart\Landing\Blocks::normalizeSettings($code, (array)$block['settings']);
		$content = $this->blockContent($code, (array)$block['content'], $language_id);

		$block_data = [
			'block_id'    => (int)$block['block_id'],
			'code'        => $code,
			'settings'    => $settings,
			'content'     => $content,
			'builder'     => $builder,
			'texts'       => [
				'text_view_all' => $this->language->get('text_view_all'),
			],
		];

		// Does the section wrapper paint a background? Blocks that bring their own
		// panel (hero, cta, text) then skip theirs, otherwise they render a
		// "frame inside a frame" and can end up with unreadable text.
		$bg_type = (string)($settings['bg_type'] ?? 'none');
		$has_wrapper_bg = in_array($bg_type, ['color', 'image'], true);
		$wrapper_is_dark = true;

		if ($bg_type === 'color') {
			$wrapper_is_dark = !$this->isLightColor((string)($settings['bg_color'] ?? ''));
		}

		$block_data['has_wrapper_bg'] = $has_wrapper_bg;
		$block_data['wrapper_is_dark'] = $wrapper_is_dark;
		$block_data['wrapper_text_class'] = $has_wrapper_bg ? ($wrapper_is_dark ? 'text-white' : 'text-gray-900') : '';
		$block_data['wrapper_muted_class'] = $has_wrapper_bg ? ($wrapper_is_dark ? 'text-white/80' : 'text-gray-600') : '';
		$block_data['wrapper_prose_class'] = $has_wrapper_bg && $wrapper_is_dark ? 'prose-invert' : '';

		$block_data = $this->enrichBlock($block_data);

		$body = $this->load->view('landing/blocks/' . $code, $block_data);

		$container = ((string)($settings['container'] ?? 'boxed') === 'full')
			? 'w-full'
			: 'mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8';

		$align = [
			'left' => 'text-left',
			'center' => 'text-center',
			'right' => 'text-right',
		][(string)($settings['align'] ?? 'left')] ?? 'text-left';

		$body_classes = $container . ' ' . $align;

		if ($has_wrapper_bg) {
			$body_classes .= ' ' . ($wrapper_is_dark ? 'text-white' : 'text-gray-900');
		}

		$classes = \Dockercart\Landing\Blocks::wrapperClasses($code, $settings, $builder);
		$style = \Dockercart\Landing\Blocks::wrapperStyleAttribute($code, $settings);

		$overlay = \Dockercart\Landing\Blocks::overlay($settings);

		if ($overlay > 0) {
			$classes .= ' lp-block--bg-image';
			$style .= ($style !== '' ? ';' : '') . '--lp-overlay:' . rtrim(rtrim(number_format($overlay / 100, 2, '.', ''), '0'), '.');
		}

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

		if ($builder) {
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
		}

		return '<section' . $attributes . '><div class="lp-block__body ' . $body_classes . '">' . $body . '</div></section>';
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
				$block_data['image'] = $this->imageUrl((string)($settings['image'] ?? ''), 1600, 900);
				$block_data['image_mobile'] = $this->imageUrl((string)($settings['image_mobile'] ?? ''), 900, 1200);
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
				$block_data['image'] = $this->imageUrl((string)($settings['image'] ?? ''), 800, 600);
				break;

			case 'products':
			case 'products_carousel':
				$cards = new \Dockercart\Catalog\ProductCards($this->registry);

				// Flags product_card_widget.twig expects from a module controller.
				$block_data['products'] = [];
				$block_data['accent_color'] = 'blue-600';
				$block_data['btn_cart_classes'] = 'bg-blue-600 text-white hover:bg-blue-700';
				$block_data['btn_quick_hover'] = 'hover:bg-blue-600';
				$block_data['show_distribution'] = (int)$this->config->get('config_review_show_distribution');
				$block_data['call_for_price_status'] = (int)$this->config->get('dockercart_theme_call_for_price_status');
				$block_data['call_for_price_phone'] = (string)$this->config->get('config_telephone');
				$block_data['call_for_price_mode'] = ($this->config->get('dockercart_theme_call_for_price_mode') === 'call') ? 'call' : 'request';
				$block_data['columns_class'] = $this->columnsClass((string)($settings['columns'] ?? '4'), 5);
				$block_data['texts'] = array_merge($block_data['texts'], $cards->texts());

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
						'name'        => html_entity_decode((string)$category['name'], ENT_QUOTES, 'UTF-8'),
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
	 * Per-language content with a fallback to the store language for empty fields.
	 *
	 * @param array<int, array<string, mixed>> $content
	 * @return array<string, mixed>
	 */
	private function blockContent(string $code, array $content, int $language_id): array {
		$current = \Dockercart\Landing\Blocks::normalizeContent($code, (array)($content[$language_id] ?? []));
		$default = \Dockercart\Landing\Blocks::normalizeContent($code, (array)($content[$this->default_language_id] ?? []));

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

			$color = !empty($row['primary_btn_color']) ? (string)$row['primary_btn_color'] : '#2563eb';

			$slides[] = [
				'title'             => html_entity_decode((string)$row['title'], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
				'subtitle'          => isset($row['subtitle']) ? html_entity_decode((string)$row['subtitle'], ENT_QUOTES | ENT_HTML5, 'UTF-8') : '',
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
		if ($link !== '' && preg_match('/^route=([^&]+)&(.+)$/', $link, $matches)) {
			return $this->url->link($matches[1], $matches[2], true);
		}

		return $link;
	}

	private function bannerVideoUrl(string $type, string $video): string {
		if ($video === '') {
			return '';
		}

		if ($type === 'youtube') {
			return 'https://www.youtube.com/embed/' . urlencode($video) . '?autoplay=1&mute=1&loop=1&playlist=' . urlencode($video) . '&controls=0&showinfo=0&rel=0';
		}

		if ($type === 'mp4') {
			if (filter_var($video, FILTER_VALIDATE_URL)) {
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
