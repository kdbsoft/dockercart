<?php
/**
 * ReviewList
 *
 * Builds the shared review list fragment (cards + sorting + pagination) used
 * by both the full SEO page (product/reviews) and the AJAX tab on the product
 * page. Keeps the display formatting in one place instead of duplicating it
 * across controllers.
 *
 * @mixin Registry
 */
class ReviewList {
	protected $registry;

	public function __construct($registry) {
		$this->registry = $registry;
	}

	public function __get($key) {
		return $this->registry->get($key);
	}

	/**
	 * Build the review list fragment data.
	 *
	 * @param string $link_route Route used for pagination / sort links
	 *                           ('product/reviews' for the full page,
	 *                           'product/product/review' for the AJAX tab).
	 * @return array<string, mixed>
	 */
	public function build(int $product_id, int $page, string $sort, string $link_route = 'product/reviews'): array {
		$this->load->language('product/reviews');
		$this->load->language('product/product');

		$this->load->helper('plural');

		$this->load->model('catalog/review');
		$this->load->model('tool/image');

		if ($page < 1) {
			$page = 1;
		}

		$limit = (int)$this->config->get('config_review_per_page');

		if ($limit < 1) {
			$limit = 10;
		}

		$review_total = $this->model_catalog_review->getTotalReviewsByProductId($product_id);
		$results = $this->model_catalog_review->getReviewsByProductId($product_id, ($page - 1) * $limit, $limit, $sort);

		$reviews = array();

		foreach ($results as $result) {
			$criteria = array();
			$pros = '';
			$cons = '';

			foreach ($result['criteria'] as $criteria_id => $item) {
				$entry = array(
					'name'  => $item['name'],
					'type'  => $item['type'],
					'value' => $item['value'],
				);

				if ($item['type'] === 'rating') {
					$entry['rating'] = ReviewRating::format((float)$item['value']);
					$entry['stars'] = ReviewRating::starComponents((float)$item['value']);
				} else {
					$entry['text'] = $item['value'];

					if (mb_stripos($item['name'], 'pro') !== false || mb_stripos($item['name'], 'досто') !== false) {
						$pros = $item['value'];
					} elseif (mb_stripos($item['name'], 'con') !== false || mb_stripos($item['name'], 'недол') !== false) {
						$cons = $item['value'];
					}
				}

				$criteria[] = $entry;
			}

			$images = array();

			foreach ($result['images'] as $image) {
				$images[] = array(
					'thumb' => $this->model_tool_image->resize($image['image'], 160, 160),
					'popup' => $this->model_tool_image->resize($image['image'], 900, 900),
				);
			}

			$video = null;

			if ($result['video']) {
				$video = array(
					'type'  => $result['video']['type'],
					'value' => $result['video']['value'],
				);

				if ($result['video']['type'] === 'youtube') {
					$video_id = $result['video']['value'];

					if (preg_match('/[A-Za-z0-9_-]{11}/', $video_id, $m)) {
						$video_id = $m[0];
					}

					$video['embed'] = 'https://www.youtube.com/embed/' . urlencode($video_id);
				} else {
					$video['src'] = ($this->request->server['HTTPS'] ? $this->config->get('config_ssl') : $this->config->get('config_url')) . 'image/' . ltrim($result['video']['value'], '/');
				}
			}

			$reviews[] = array(
				'author'        => $result['author'],
				'author_initials' => $this->initials($result['author']),
				'avatar_hue'    => abs(crc32($result['author'])) % 360,
				'rating'        => $result['rating'],
				'rating_value'  => ReviewRating::format($result['rating']),
				'text'          => nl2br($this->clean($result['text'])),
				'verified'      => $result['verified'],
				'date_added'    => date($this->language->get('date_format_short'), strtotime($result['date_added'])),
				'criteria'      => $criteria,
				'pros'          => nl2br($this->clean($pros)),
				'cons'          => nl2br($this->clean($cons)),
				'images'        => $images,
				'video'         => $video,
			);
		}

		$pagination = new Pagination();
		$pagination->total = $review_total;
		$pagination->page = $page;
		$pagination->limit = $limit;
		$pagination->url = $this->url->link($link_route, 'product_id=' . $product_id . '&sort=' . $sort . '&page={page}');

		$sort_labels = array(
			'newest'  => $this->language->get('text_sort_newest'),
			'highest' => $this->language->get('text_sort_highest'),
			'lowest'  => $this->language->get('text_sort_lowest'),
		);

		$sort_urls = array();

		foreach (array('newest', 'highest', 'lowest') as $sort_key) {
			$sort_urls[] = array(
				'sort'   => $sort_key,
				'label'  => isset($sort_labels[$sort_key]) ? $sort_labels[$sort_key] : $sort_key,
				'href'   => $this->url->link($link_route, 'product_id=' . $product_id . '&sort=' . $sort_key),
				'active' => $sort === $sort_key,
			);
		}

		return array(
			'reviews'          => $reviews,
			'total'            => $review_total,
			'total_label'      => review_count_label($review_total, $this->language->get('code')),
			'pagination'       => $pagination->render(),
			'text_pagination'  => sprintf($this->language->get('text_pagination'), ($review_total) ? (($page - 1) * $limit) + 1 : 0, ((($page - 1) * $limit) > ($review_total - $limit)) ? $review_total : ((($page - 1) * $limit) + $limit), $review_total, ceil($review_total / $limit)),
			'sort'             => $sort,
			'sort_urls'        => $sort_urls,
			'text_no_reviews'  => $this->language->get('text_no_reviews'),
		);
	}

	/**
	 * Sanitize review text for safe HTML output.
	 */
	private function clean(string $text): string {
		return htmlspecialchars(trim($text), ENT_QUOTES, 'UTF-8');
	}

	/**
	 * Initials for the author avatar circle.
	 */
	private function initials(string $name): string {
		$parts = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY);

		if (!$parts) {
			return '?';
		}

		$initials = mb_substr($parts[0], 0, 1);

		if (count($parts) > 1) {
			$initials .= mb_substr($parts[count($parts) - 1], 0, 1);
		}

		return mb_strtoupper($initials);
	}
}
