<?php
/**
 * Product card presenter.
 *
 * Turns product ids (or a "source" rule) into the array shape that
 * catalog/view/theme/dockercart/template/product/product_card_widget.twig
 * expects: thumb, prices, discount %, tax, rating + distribution, wishlist
 * flag, stock/pre-order text, gift and call-for-price flags, category name.
 *
 * The logic used to live (four times) inside extension/module/featured,
 * bestseller, latest and special. Landing page blocks need the same output, so
 * it lives here once — with the multi-currency rule applied: a product's stored
 * price is denominated in oc_product.currency_id and is normalised to the base
 * currency with Cart\Currency::convertProductPrice() before formatting.
 *
 * @package    DockerCart
 * @subpackage Library
 */

declare(strict_types=1);

namespace Dockercart\Catalog;

final class ProductCards {
	/** Sources a block may pull products from. */
	public const SOURCES = ['manual', 'category', 'special', 'latest', 'bestseller'];

	private const MAX_LIMIT = 24;

	/** @var \Registry */
	private $registry;

	/** @var array<string, mixed> */
	private $texts_cache = [];

	public function __construct(\Registry $registry) {
		$this->registry = $registry;
	}

	/**
	 * Language strings required by product_card_widget.twig.
	 *
	 * @return array<string, string>
	 */
	public function texts(): array {
		if ($this->texts_cache) {
			return $this->texts_cache;
		}

		$load = $this->registry->get('load');
		$language = $this->registry->get('language');

		$load->language('extension/module/featured');
		$load->language('product/product');

		$keys = [
			'text_quick_view',
			'text_instock',
			'text_preorder',
			'text_out_of_stock',
			'text_sale',
			'text_gift_badge',
			'text_call_for_price',
			'text_call_for_price_request',
			'text_all_reviews',
			'text_add_to_wishlist',
			'button_cart',
		];

		$this->texts_cache = [];

		foreach ($keys as $key) {
			$this->texts_cache[$key] = (string)$language->get($key);
		}

		// Global language file fallbacks (button_cart / wishlist labels).
		if ($this->texts_cache['button_cart'] === '') {
			$this->texts_cache['button_cart'] = 'Add to Cart';
		}

		if ($this->texts_cache['text_add_to_wishlist'] === '') {
			$this->texts_cache['text_add_to_wishlist'] = 'Add to wishlist';
		}

		if ($this->texts_cache['text_quick_view'] === '') {
			$this->texts_cache['text_quick_view'] = 'Quick View';
		}

		return $this->texts_cache;
	}

	/**
	 * Resolves a block "source" rule into an ordered list of card arrays.
	 *
	 * @param array{source?: string, product_ids?: array<int, int>, category_id?: int, limit?: int} $source
	 * @param array{width?: int, height?: int, category_names?: bool} $options
	 * @return array<int, array<string, mixed>>
	 */
	public function fromSource(array $source, array $options = []): array {
		$type = isset($source['source']) ? (string)$source['source'] : 'manual';

		if (!in_array($type, self::SOURCES, true)) {
			$type = 'manual';
		}

		$limit = isset($source['limit']) ? (int)$source['limit'] : 8;
		$limit = max(1, min(self::MAX_LIMIT, $limit));

		$load = $this->registry->get('load');
		$load->model('catalog/product');

		$ids = [];

		switch ($type) {
			case 'category':
				$category_id = isset($source['category_id']) ? (int)$source['category_id'] : 0;

				if ($category_id > 0) {
					$results = $this->registry->get('model_catalog_product')->getProducts([
						'filter_category_id' => $category_id,
						'filter_sub_category' => 1,
						'sort' => 'p.sort_order',
						'order' => 'ASC',
						'start' => 0,
						'limit' => $limit,
					]);

					foreach ($results as $result) {
						$ids[] = (int)$result['product_id'];
					}
				}
				break;

			case 'special':
				foreach ($this->registry->get('model_catalog_product')->getProductSpecials([
					'sort' => 'pd.name',
					'order' => 'ASC',
					'start' => 0,
					'limit' => $limit,
				]) as $result) {
					$ids[] = (int)$result['product_id'];
				}
				break;

			case 'latest':
				foreach ($this->registry->get('model_catalog_product')->getLatestProducts($limit) as $result) {
					$ids[] = (int)$result['product_id'];
				}
				break;

			case 'bestseller':
				foreach ($this->registry->get('model_catalog_product')->getBestSellerProducts($limit) as $result) {
					$ids[] = (int)$result['product_id'];
				}
				break;

			case 'manual':
			default:
				$product_ids = isset($source['product_ids']) && is_array($source['product_ids']) ? $source['product_ids'] : [];

				foreach ($product_ids as $product_id) {
					$product_id = (int)$product_id;

					if ($product_id > 0) {
						$ids[] = $product_id;
					}

					if (count($ids) >= $limit) {
						break;
					}
				}
				break;
		}

		return $this->cards($ids, $options);
	}

	/**
	 * Builds card arrays for the given product ids, preserving the given order.
	 *
	 * @param array<int, int|string> $product_ids
	 * @param array{width?: int, height?: int, category_names?: bool} $options
	 * @return array<int, array<string, mixed>>
	 */
	public function cards(array $product_ids, array $options = []): array {
		$ids = [];

		foreach ($product_ids as $product_id) {
			$product_id = (int)$product_id;

			if ($product_id > 0 && !in_array($product_id, $ids, true)) {
				$ids[] = $product_id;
			}
		}

		if (!$ids) {
			return [];
		}

		$load = $this->registry->get('load');
		$load->model('catalog/product');
		$load->model('catalog/category');
		$load->model('tool/image');

		$width = isset($options['width']) ? (int)$options['width'] : 300;
		$height = isset($options['height']) ? (int)$options['height'] : 300;

		if ($width <= 0) {
			$width = 300;
		}

		if ($height <= 0) {
			$height = 300;
		}

		$products_info = $this->registry->get('model_catalog_product')->getProductsByIds($ids);

		if (!$products_info) {
			return [];
		}

		$category_map = [];

		if (!array_key_exists('category_names', $options) || $options['category_names']) {
			$category_map = $this->registry->get('model_catalog_category')->getProductCategoryNames(array_keys($products_info));
		}

		$wishlist_ids = $this->wishlistIds();

		$cards = [];

		foreach ($ids as $product_id) {
			if (!isset($products_info[$product_id])) {
				continue;
			}

			$cards[] = $this->card($products_info[$product_id], $category_map, $wishlist_ids, $width, $height);
		}

		return $cards;
	}

	/**
	 * @param array<string, mixed> $product_info
	 * @param array<int, array<string, mixed>> $category_map
	 * @param array<int, int> $wishlist_ids
	 * @return array<string, mixed>
	 */
	private function card(array $product_info, array $category_map, array $wishlist_ids, int $width, int $height): array {
		$config = $this->registry->get('config');
		$currency = $this->registry->get('currency');
		$tax = $this->registry->get('tax');
		$session = $this->registry->get('session');
		$url = $this->registry->get('url');
		$language = $this->registry->get('language');
		$image = $this->registry->get('model_tool_image');

		$image_path = !empty($product_info['image']) ? (string)$product_info['image'] : 'placeholder.png';
		$thumb = $image->resize($image_path, $width, $height);

		$product_currency_id = isset($product_info['currency_id']) ? (int)$product_info['currency_id'] : 0;
		$base_price = $currency->convertProductPrice((float)$product_info['price'], $product_currency_id);
		$has_special = isset($product_info['special']) && !is_null($product_info['special']) && (float)$product_info['special'] >= 0;
		$base_unit_price = $has_special ? $currency->convertProductPrice((float)$product_info['special'], $product_currency_id) : $base_price;

		$session_currency = isset($session->data['currency']) ? $session->data['currency'] : $config->get('config_currency');

		if ($this->registry->get('customer')->isLogged() || !$config->get('config_customer_price')) {
			$price = $currency->format($tax->calculate($base_price, $product_info['tax_class_id'], $config->get('config_tax')), $session_currency);
		} else {
			$price = false;
		}

		if ($has_special) {
			$special = $currency->format($tax->calculate($base_unit_price, $product_info['tax_class_id'], $config->get('config_tax')), $session_currency);
		} else {
			$special = false;
		}

		$discount_percent = 0;

		if ($has_special && $base_price > 0) {
			$discount_percent = (int)round((1 - ($base_unit_price / $base_price)) * 100);

			if ($discount_percent < 0) {
				$discount_percent = 0;
			}
		}

		if ($config->get('config_tax')) {
			$tax_amount = $currency->format($base_unit_price, $session_currency);
		} else {
			$tax_amount = false;
		}

		$rating = $config->get('config_review_status') ? (isset($product_info['rating']) ? $product_info['rating'] : 0) : false;

		$stock_quantity = (int)$product_info['quantity'];
		$preorder = !empty($product_info['preorder']);
		$texts = $this->texts();

		if ($stock_quantity <= 0) {
			$stock = $preorder ? $texts['text_preorder'] : $texts['text_out_of_stock'];
		} elseif ($config->get('config_stock_display')) {
			$stock = $stock_quantity;
		} else {
			$stock = $texts['text_instock'];
		}

		$category_name = '';
		$category_id = 0;

		if (isset($category_map[(int)$product_info['product_id']])) {
			$category_id = (int)$category_map[(int)$product_info['product_id']]['category_id'];
			$category_name = (string)$category_map[(int)$product_info['product_id']]['name'];
		}

		$description_length = (int)$config->get('theme_' . $config->get('config_theme') . '_product_description_length');

		if ($description_length <= 0) {
			$description_length = 100;
		}

		$description = html_entity_decode((string)$product_info['description'], ENT_QUOTES, 'UTF-8');
		$description = strip_tags($description);

		if (function_exists('utf8_substr')) {
			$description = utf8_substr($description, 0, $description_length) . '..';
		}

		$href = $url->link('product/product', 'product_id=' . (int)$product_info['product_id']);

		return [
			'product_id'          => (int)$product_info['product_id'],
			'thumb'               => $thumb,
			'name'                => $product_info['name'],
			'model'               => isset($product_info['model']) ? $product_info['model'] : '',
			'manufacturer'        => isset($product_info['manufacturer']) ? $product_info['manufacturer'] : '',
			'category'            => $category_name,
			'category_id'         => $category_id,
			'description'         => $description,
			'price'               => $price,
			'price_raw'           => $base_price,
			'special'             => $special,
			'special_raw'         => $has_special ? $base_unit_price : null,
			'discount'            => $discount_percent,
			'tax'                 => $tax_amount,
			'stock'               => $stock,
			'quantity'            => $stock_quantity,
			'is_in_stock'         => ($stock_quantity > 0) || $preorder,
			'is_preorder'         => $stock_quantity <= 0 && $preorder,
			'rating'              => $rating,
			'reviews'             => isset($product_info['reviews']) ? $product_info['reviews'] : 0,
			'rating_distribution' => isset($product_info['rating_distribution']) ? $product_info['rating_distribution'] : [],
			'in_wishlist'         => in_array((int)$product_info['product_id'], $wishlist_ids, true) ? 1 : 0,
			'has_gift'            => !empty($product_info['has_gift']),
			'call_for_price'      => !empty($product_info['call_for_price']),
			'href'                => $href,
			'reviews_url'         => $url->link('product/reviews', 'product_id=' . (int)$product_info['product_id']),
		];
	}

	/**
	 * Wishlist ids of the current visitor (customer account or guest session).
	 *
	 * @return array<int, int>
	 */
	private function wishlistIds(): array {
		$customer = $this->registry->get('customer');
		$session = $this->registry->get('session');

		if ($customer->isLogged()) {
			$this->registry->get('load')->model('account/wishlist');

			$ids = [];

			foreach ($this->registry->get('model_account_wishlist')->getWishlist() as $row) {
				$ids[] = (int)$row['product_id'];
			}

			return $ids;
		}

		if (isset($session->data['wishlist']) && is_array($session->data['wishlist'])) {
			return array_map('intval', $session->data['wishlist']);
		}

		return [];
	}
}
