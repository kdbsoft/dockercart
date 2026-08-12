<?php
declare(strict_types=1);

/**
 * Catalog-side review model.
 *
 * Supports the flexible criteria system (rating dimensions + text fields),
 * fractional aggregate ratings, verified-purchase badge, media attachments
 * (up to 3 images + 1 video) and a per-product rating cache
 * (oc_product_rating) that backs the storefront summary and schema markup.
 */
class ModelCatalogReview extends Model {
	/**
	 * Insert a review (status 0 by default; auto-published when the
	 * config_review_auto_approve setting is enabled) and its criteria
	 * values. Media is persisted afterwards via addReviewMedia().
	 *
	 * @return int review_id
	 */
	public function addReview(int $product_id, array $data): int {
		$customer_id = (int)$this->customer->getId();

		$status = (int)$this->config->get('config_review_auto_approve') ? 1 : 0;
		$verified = $this->config->get('config_review_verify_purchase') ? $this->isVerifiedPurchase($product_id, $customer_id) : false;

		$criteria_group_id = isset($data['criteria_group_id']) ? (int)$data['criteria_group_id'] : 0;

		if ($criteria_group_id <= 0) {
			$this->load->model('catalog/review_criteria');
			$criteria_group_id = $this->model_catalog_review_criteria->getProductCriteriaGroupId($product_id);
		}

		$rating = isset($data['rating']) ? (float)$data['rating'] : 0.0;
		$text = isset($data['text']) ? (string)$data['text'] : '';
		$author = isset($data['name']) ? (string)$data['name'] : '';
		$ip = isset($this->request->server['REMOTE_ADDR']) ? (string)$this->request->server['REMOTE_ADDR'] : '';

		$this->db->query("INSERT INTO " . DB_PREFIX . "review SET author = '" . $this->db->escape($author) . "', customer_id = '" . $customer_id . "', product_id = '" . (int)$product_id . "', text = '" . $this->db->escape($text) . "', rating = '" . (float)$rating . "', status = '" . $status . "', verified = '" . (int)$verified . "', ip = '" . $this->db->escape($ip) . "', criteria_group_id = '" . (int)$criteria_group_id . "', date_added = NOW()");

		$review_id = $this->db->getLastId();

		if (!empty($data['criteria_values']) && is_array($data['criteria_values'])) {
			foreach ($data['criteria_values'] as $criteria_id => $value) {
				$value = (string)$value;

				if (trim($value) === '') {
					continue;
				}

				$this->db->query("INSERT INTO " . DB_PREFIX . "review_criteria_value SET review_id = '" . (int)$review_id . "', criteria_id = '" . (int)$criteria_id . "', value = '" . $this->db->escape($value) . "'");
			}
		}

		$this->addReviewMedia($review_id, $data);

		if ($status) {
			$this->recalculateProductRating($product_id);
		}

		$this->sendAdminNotification($product_id, $data, $rating);

		return $review_id;
	}

	/**
	 * Whether a customer has an order with a completed status containing
	 * the given product (verified purchase badge).
	 */
	public function isVerifiedPurchase(int $product_id, int $customer_id): bool {
		if ($product_id <= 0 || $customer_id <= 0) {
			return false;
		}

		$complete_status = $this->config->get('config_complete_status');

		if (empty($complete_status) || !is_array($complete_status)) {
			return false;
		}

		$statuses = array_filter(array_map('intval', $complete_status));

		if (!$statuses) {
			return false;
		}

		$query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "order o LEFT JOIN " . DB_PREFIX . "order_product op ON (o.order_id = op.order_id) WHERE o.customer_id = '" . $customer_id . "' AND op.product_id = '" . $product_id . "' AND o.order_status_id IN (" . implode(',', $statuses) . ")");

		return (int)$query->row['total'] > 0;
	}

	/**
	 * Count reviews submitted recently by an IP / customer for rate limiting.
	 */
	public function getRecentReviewCount(string $ip, int $customer_id, int $minutes = 60): int {
		if ($minutes < 1) {
			$minutes = 60;
		}

		$sql = "SELECT COUNT(*) AS total FROM " . DB_PREFIX . "review WHERE date_added > DATE_SUB(NOW(), INTERVAL " . (int)$minutes . " MINUTE)";

		if ($ip !== '') {
			$sql .= " AND ip = '" . $this->db->escape($ip) . "'";
		}

		if ($customer_id > 0) {
			$sql .= " AND customer_id = '" . $customer_id . "'";
		}

		$query = $this->db->query($sql);

		return (int)$query->row['total'];
	}

	/**
	 * Persist review media: up to 3 images and 1 video.
	 *
	 * Images come from $data['images'] (one or more $_FILES-style arrays).
	 * Video comes from $data['video'] (['type' => 'youtube', 'value' => url])
	 * or from $data['video_file'] (an uploaded mp4 $_FILES entry).
	 */
	public function addReviewMedia(int $review_id, array $data): void {
		$dir = ReviewMedia::reviewImageDirectory($review_id);
		$this->createMediaDirectory($dir);

		$sort_order = 0;

		foreach ($this->normalizeImageFiles($data) as $file) {
			$result = ReviewMedia::validateImage($file, array('max_size' => (int)$this->config->get('config_review_image_max_size'), 'check_uploaded' => false));

			if (!$result['ok']) {
				continue;
			}

			$path = $this->storeReviewImage($file, $result, $dir);

			if ($path === '') {
				continue;
			}

			$this->db->query("INSERT INTO " . DB_PREFIX . "review_image SET review_id = '" . (int)$review_id . "', image = '" . $this->db->escape($path) . "', sort_order = '" . (int)$sort_order . "'");

			$sort_order++;
		}

		$video = $this->resolveVideoData($data, $dir);

		if ($video !== null) {
			$this->db->query("INSERT INTO " . DB_PREFIX . "review_video SET review_id = '" . (int)$review_id . "', video_type = '" . $this->db->escape($video['type']) . "', video = '" . $this->db->escape($video['value']) . "', sort_order = '0'");
		}
	}

	/**
	 * Approved reviews for a product with criteria values, images and videos.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getReviewsByProductId(int $product_id, int $start = 0, int $limit = 10, string $sort = 'newest'): array {
		if ($start < 0) {
			$start = 0;
		}

		if ($limit < 1) {
			$limit = 10;
		}

		$order = 'r.date_added DESC';

		if ($sort === 'highest') {
			$order = 'r.rating DESC, r.date_added DESC';
		} elseif ($sort === 'lowest') {
			$order = 'r.rating ASC, r.date_added DESC';
		}

		$query = $this->db->query("SELECT r.review_id, r.author, r.rating, r.text, r.verified, r.criteria_group_id, r.date_added FROM " . DB_PREFIX . "review r LEFT JOIN " . DB_PREFIX . "product p ON (r.product_id = p.product_id) WHERE p.product_id = '" . (int)$product_id . "' AND p.date_available <= NOW() AND p.status = '1' AND r.status = '1' AND r.parent_id IS NULL ORDER BY " . $order . " LIMIT " . (int)$start . "," . (int)$limit);

		if (!$query->rows) {
			return array();
		}

		$review_ids = array_map('intval', array_column($query->rows, 'review_id'));
		$criteria = $this->getCriteriaMapForReviews($review_ids);
		$images = $this->getImagesForReviews($review_ids);
		$videos = $this->getVideosForReviews($review_ids);

		$reviews = array();

		foreach ($query->rows as $row) {
			$review_id = (int)$row['review_id'];

			$reviews[] = array(
				'review_id'        => $review_id,
				'author'           => $row['author'],
				'rating'           => (float)$row['rating'],
				'text'             => $row['text'],
				'verified'         => (int)$row['verified'] === 1,
				'date_added'       => $row['date_added'],
				'criteria_group_id'=> $row['criteria_group_id'] !== null ? (int)$row['criteria_group_id'] : 0,
				'criteria'         => isset($criteria[$review_id]) ? $criteria[$review_id] : array(),
				'images'           => isset($images[$review_id]) ? $images[$review_id] : array(),
				'video'            => isset($videos[$review_id][0]) ? $videos[$review_id][0] : null,
			);
		}

		return $reviews;
	}

	/**
	 * Total number of approved reviews for a product.
	 */
	public function getTotalReviewsByProductId(int $product_id): int {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "review r LEFT JOIN " . DB_PREFIX . "product p ON (r.product_id = p.product_id) WHERE p.product_id = '" . (int)$product_id . "' AND p.date_available <= NOW() AND p.status = '1' AND r.status = '1' AND r.parent_id IS NULL");

		return (int)$query->row['total'];
	}

	/**
	 * Whether a review exists (approved or not).
	 */
	public function reviewExists(int $review_id): bool {
		$query = $this->db->query("SELECT review_id FROM " . DB_PREFIX . "review WHERE review_id = '" . (int)$review_id . "' LIMIT 1");

		return (bool)$query->num_rows;
	}

	/**
	 * Add a one-level-deep reply to a published top-level review.
	 *
	 * Replies are plain text only: no rating, verified flag, criteria or
	 * media, and they never touch the product rating cache. Moderation is
	 * controlled by config_review_reply_auto_approve.
	 *
	 * @return int reply review_id
	 */
	public function addReply(int $product_id, int $parent_id, array $data): int {
		$parent = $this->db->query("SELECT review_id FROM " . DB_PREFIX . "review WHERE review_id = '" . (int)$parent_id . "' AND product_id = '" . (int)$product_id . "' AND parent_id IS NULL AND status = '1' LIMIT 1");

		if (!$parent->num_rows) {
			return 0;
		}

		$customer_id = (int)$this->customer->getId();
		$author = isset($data['name']) ? (string)$data['name'] : '';
		$text = isset($data['text']) ? (string)$data['text'] : '';
		$ip = isset($this->request->server['REMOTE_ADDR']) ? (string)$this->request->server['REMOTE_ADDR'] : '';

		$status = (int)$this->config->get('config_review_reply_auto_approve') ? 1 : 0;

		$this->db->query("INSERT INTO " . DB_PREFIX . "review SET author = '" . $this->db->escape($author) . "', customer_id = '" . $customer_id . "', product_id = '" . (int)$product_id . "', parent_id = '" . (int)$parent_id . "', author_is_admin = '0', text = '" . $this->db->escape($text) . "', rating = '0', status = '" . $status . "', verified = '0', ip = '" . $this->db->escape($ip) . "', criteria_group_id = NULL, date_added = NOW()");

		return (int)$this->db->getLastId();
	}

	/**
	 * Approved replies for a set of review ids, grouped by parent review id.
	 *
	 * @param array<int, int> $review_ids
	 * @return array<int, array<int, array<string, mixed>>> parent_id => [reply]
	 */
	public function getRepliesForReviews(array $review_ids): array {
		if (!$review_ids) {
			return array();
		}

		$query = $this->db->query("SELECT parent_id, review_id, author, author_is_admin, text, date_added FROM " . DB_PREFIX . "review WHERE parent_id IN (" . implode(',', $review_ids) . ") AND status = '1' ORDER BY parent_id ASC, date_added ASC");

		$replies = array();

		foreach ($query->rows as $row) {
			$parent_id = (int)$row['parent_id'];

			$replies[$parent_id][] = array(
				'reply_id'        => (int)$row['review_id'],
				'author'          => $row['author'],
				'author_is_admin' => (int)$row['author_is_admin'] === 1,
				'text'            => $row['text'],
				'date_added'      => $row['date_added'],
			);
		}

		return $replies;
	}

	/**
	 * Cast a customer vote on a review (toggle + switch semantics).
	 *
	 * Passing the same vote the customer already cast removes it; passing the
	 * other vote switches it. A single active vote per customer/review pair.
	 *
	 * @return array{likes: int, dislikes: int, my_vote: string}
	 */
	public function voteReview(int $review_id, int $customer_id, string $vote): array {
		$vote_value = $vote === 'like' ? 1 : 0;

		$query = $this->db->query("SELECT vote FROM " . DB_PREFIX . "review_vote WHERE review_id = '" . (int)$review_id . "' AND customer_id = '" . (int)$customer_id . "' LIMIT 1");

		if ($query->num_rows) {
			if ((int)$query->row['vote'] === $vote_value) {
				// Same vote again — remove it (toggle off).
				$this->db->query("DELETE FROM " . DB_PREFIX . "review_vote WHERE review_id = '" . (int)$review_id . "' AND customer_id = '" . (int)$customer_id . "'");
			} else {
				// Switch to the other vote.
				$this->db->query("UPDATE " . DB_PREFIX . "review_vote SET vote = '" . $vote_value . "', date_added = NOW() WHERE review_id = '" . (int)$review_id . "' AND customer_id = '" . (int)$customer_id . "'");
			}
		} else {
			// Replies cannot be voted on: refuse when the review is a reply.
			$top_level = $this->db->query("SELECT review_id FROM " . DB_PREFIX . "review WHERE review_id = '" . (int)$review_id . "' AND parent_id IS NULL LIMIT 1");

			if ($top_level->num_rows) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "review_vote SET review_id = '" . (int)$review_id . "', customer_id = '" . (int)$customer_id . "', vote = '" . $vote_value . "', date_added = NOW()");
			}
		}

		$counts = $this->getVoteCounts($review_id);

		return array(
			'likes'    => $counts['likes'],
			'dislikes' => $counts['dislikes'],
			'my_vote'  => $this->getCustomerVote($review_id, $customer_id),
		);
	}

	/**
	 * Like/dislike totals for a single review.
	 *
	 * @return array{likes: int, dislikes: int}
	 */
	public function getVoteCounts(int $review_id): array {
		$query = $this->db->query("SELECT SUM(vote = 1) AS likes, SUM(vote = 0) AS dislikes FROM " . DB_PREFIX . "review_vote WHERE review_id = '" . (int)$review_id . "'");

		return array(
			'likes'    => $query->num_rows ? (int)$query->row['likes'] : 0,
			'dislikes' => $query->num_rows ? (int)$query->row['dislikes'] : 0,
		);
	}

	/**
	 * Like/dislike totals for a set of review ids (list rendering).
	 *
	 * @param array<int, int> $review_ids
	 * @return array<int, array{likes: int, dislikes: int}>
	 */
	public function getVotesForReviews(array $review_ids): array {
		if (!$review_ids) {
			return array();
		}

		$query = $this->db->query("SELECT review_id, SUM(vote = 1) AS likes, SUM(vote = 0) AS dislikes FROM " . DB_PREFIX . "review_vote WHERE review_id IN (" . implode(',', $review_ids) . ") GROUP BY review_id");

		$votes = array();

		foreach ($query->rows as $row) {
			$votes[(int)$row['review_id']] = array(
				'likes'    => (int)$row['likes'],
				'dislikes' => (int)$row['dislikes'],
			);
		}

		return $votes;
	}

	/**
	 * The current customer's vote per review id ('like', 'dislike' or '').
	 *
	 * @param array<int, int> $review_ids
	 * @return array<int, string>
	 */
	public function getCustomerVotes(array $review_ids, int $customer_id): array {
		if (!$review_ids || $customer_id <= 0) {
			return array();
		}

		$query = $this->db->query("SELECT review_id, vote FROM " . DB_PREFIX . "review_vote WHERE review_id IN (" . implode(',', $review_ids) . ") AND customer_id = '" . (int)$customer_id . "'");

		$votes = array();

		foreach ($query->rows as $row) {
			$votes[(int)$row['review_id']] = (int)$row['vote'] === 1 ? 'like' : 'dislike';
		}

		return $votes;
	}

	/**
	 * A single customer's vote for one review ('like', 'dislike' or '').
	 */
	public function getCustomerVote(int $review_id, int $customer_id): string {
		if ($customer_id <= 0) {
			return '';
		}

		$query = $this->db->query("SELECT vote FROM " . DB_PREFIX . "review_vote WHERE review_id = '" . (int)$review_id . "' AND customer_id = '" . (int)$customer_id . "' LIMIT 1");

		if (!$query->num_rows) {
			return '';
		}

		return (int)$query->row['vote'] === 1 ? 'like' : 'dislike';
	}

	/**
	 * Aggregate rating summary from the oc_product_rating cache, with a
	 * lazy recompute fallback when no cache row exists yet.
	 *
	 * @return array{rating: float, review_count: int, distribution: array<int, int>}
	 */
	public function getProductRatingSummary(int $product_id): array {
		$query = $this->db->query("SELECT rating, review_count, distribution FROM " . DB_PREFIX . "product_rating WHERE product_id = '" . (int)$product_id . "' LIMIT 1");

		if ($query->num_rows && (int)$query->row['review_count'] > 0) {
			$distribution = json_decode((string)$query->row['distribution'], true);

			return array(
				'rating'       => (float)$query->row['rating'],
				'review_count' => (int)$query->row['review_count'],
				'distribution' => is_array($distribution) ? $distribution : array(),
			);
		}

		return $this->recalculateProductRating($product_id);
	}

	/**
	 * Recompute and store the aggregate rating cache for a product.
	 *
	 * @return array{rating: float, review_count: int, distribution: array<int, int>}
	 */
	public function recalculateProductRating(int $product_id): array {
		$query = $this->db->query("SELECT rating FROM " . DB_PREFIX . "review WHERE product_id = '" . (int)$product_id . "' AND status = '1' AND parent_id IS NULL");

		$rows = $query->rows;
		$ratings = array_map('floatval', array_column($rows, 'rating'));

		$rating = ReviewRating::average($ratings);
		$review_count = count($ratings);
		$distribution = ReviewRating::distribution($rows);

		$this->db->query("INSERT INTO " . DB_PREFIX . "product_rating (product_id, rating, review_count, distribution, date_modified) VALUES ('" . (int)$product_id . "', '" . (float)$rating . "', '" . (int)$review_count . "', '" . $this->db->escape(json_encode($distribution)) . "', NOW()) ON DUPLICATE KEY UPDATE rating = VALUES(rating), review_count = VALUES(review_count), distribution = VALUES(distribution), date_modified = NOW()");

		return array(
			'rating'       => $rating,
			'review_count' => $review_count,
			'distribution' => $distribution,
		);
	}

	/**
	 * Approved reviews for schema.org markup (JSON-LD).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getReviewsForSchema(int $product_id, int $limit = 5): array {
		if ($limit < 1) {
			$limit = 5;
		}

		$query = $this->db->query("SELECT review_id, author, rating, text, date_added FROM " . DB_PREFIX . "review WHERE product_id = '" . (int)$product_id . "' AND status = '1' AND parent_id IS NULL ORDER BY date_added DESC LIMIT " . (int)$limit);

		$reviews = array();

		foreach ($query->rows as $row) {
			$reviews[] = array(
				'author'        => $row['author'],
				'rating'        => (float)$row['rating'],
				'text'          => $row['text'],
				'date_added'    => $row['date_added'],
			);
		}

		return $reviews;
	}

	/**
	 * Criteria values (with localized names) for a set of review ids.
	 *
	 * @param array<int, int> $review_ids
	 * @return array<int, array<int, array<string, mixed>>> review_id => [criteria_id => ['name','type','value']]
	 */
	private function getCriteriaMapForReviews(array $review_ids): array {
		$values = $this->db->query("SELECT review_id, criteria_id, value FROM " . DB_PREFIX . "review_criteria_value WHERE review_id IN (" . implode(',', $review_ids) . ")");

		if (!$values->rows) {
			return array();
		}

		$criteria_ids = array_map('intval', array_column($values->rows, 'criteria_id'));
		$language_id = (int)$this->config->get('config_language_id');

		$names = $this->db->query("SELECT cd.criteria_id, cd.language_id, cd.name, c.type FROM " . DB_PREFIX . "review_criteria_description cd LEFT JOIN " . DB_PREFIX . "review_criteria c ON (cd.criteria_id = c.criteria_id) WHERE cd.criteria_id IN (" . implode(',', $criteria_ids) . ") ORDER BY cd.language_id ASC");

		$name_by_id = array();

		foreach ($names->rows as $row) {
			$criteria_id = (int)$row['criteria_id'];

			if (!isset($name_by_id[$criteria_id])) {
				$name_by_id[$criteria_id] = array('name' => '', 'type' => $row['type']);
			}

			if ((int)$row['language_id'] === $language_id && $row['name'] !== '') {
				$name_by_id[$criteria_id]['name'] = $row['name'];
			} elseif ($name_by_id[$criteria_id]['name'] === '') {
				$name_by_id[$criteria_id]['name'] = $row['name'];
			}
		}

		$map = array();

		foreach ($values->rows as $row) {
			$review_id = (int)$row['review_id'];
			$criteria_id = (int)$row['criteria_id'];

			$map[$review_id][$criteria_id] = array(
				'name'  => isset($name_by_id[$criteria_id]) ? $name_by_id[$criteria_id]['name'] : '',
				'type'  => isset($name_by_id[$criteria_id]) ? $name_by_id[$criteria_id]['type'] : 'text',
				'value' => $row['value'],
			);
		}

		return $map;
	}

	/**
	 * @param array<int, int> $review_ids
	 * @return array<int, array<int, array<string, mixed>>>
	 */
	private function getImagesForReviews(array $review_ids): array {
		$query = $this->db->query("SELECT review_id, image, sort_order FROM " . DB_PREFIX . "review_image WHERE review_id IN (" . implode(',', $review_ids) . ") ORDER BY sort_order ASC");

		$images = array();

		foreach ($query->rows as $row) {
			$images[(int)$row['review_id']][] = array('image' => $row['image']);
		}

		return $images;
	}

	/**
	 * @param array<int, int> $review_ids
	 * @return array<int, array<int, array<string, string>>>
	 */
	private function getVideosForReviews(array $review_ids): array {
		$query = $this->db->query("SELECT review_id, video_type, video FROM " . DB_PREFIX . "review_video WHERE review_id IN (" . implode(',', $review_ids) . ") ORDER BY sort_order ASC");

		$videos = array();

		foreach ($query->rows as $row) {
			$videos[(int)$row['review_id']][] = array(
				'type'  => $row['video_type'],
				'value' => $row['video'],
			);
		}

		return $videos;
	}

	/**
	 * Normalize the $_FILES structure for review images into a flat list.
	 * Accepts a raw multi-file $_FILES entry, a single $_FILES entry, or an
	 * already-normalized list of individual file entries.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function normalizeImageFiles(array $data): array {
		$files = array();

		if (empty($data['images']) || !is_array($data['images'])) {
			return $files;
		}

		$tmp = $data['images'];

		if (isset($tmp['name']) && is_array($tmp['name'])) {
			$count = count($tmp['name']);

			for ($i = 0; $i < $count; $i++) {
				$files[] = array(
					'name'     => isset($tmp['name'][$i]) ? $tmp['name'][$i] : '',
					'tmp_name' => isset($tmp['tmp_name'][$i]) ? $tmp['tmp_name'][$i] : '',
					'error'    => isset($tmp['error'][$i]) ? (int)$tmp['error'][$i] : UPLOAD_ERR_NO_FILE,
					'size'     => isset($tmp['size'][$i]) ? (int)$tmp['size'][$i] : 0,
				);
			}
		} elseif (isset($tmp['name']) && is_string($tmp['name'])) {
			$files[] = array(
				'name'     => $tmp['name'],
				'tmp_name' => isset($tmp['tmp_name']) ? $tmp['tmp_name'] : '',
				'error'    => isset($tmp['error']) ? (int)$tmp['error'] : UPLOAD_ERR_NO_FILE,
				'size'     => isset($tmp['size']) ? (int)$tmp['size'] : 0,
			);
		} else {
			foreach ($tmp as $entry) {
				if (!is_array($entry) || !isset($entry['tmp_name'])) {
					continue;
				}

				$files[] = array(
					'name'     => isset($entry['name']) ? $entry['name'] : '',
					'tmp_name' => $entry['tmp_name'],
					'error'    => isset($entry['error']) ? (int)$entry['error'] : UPLOAD_ERR_NO_FILE,
					'size'     => isset($entry['size']) ? (int)$entry['size'] : 0,
				);
			}
		}

		return $files;
	}

	/**
	 * Resolve the review video into ['type' => 'youtube'|'mp4', 'value' => string]
	 * or null. Stored mp4 files are persisted into the review media directory.
	 *
	 * @return array{type: string, value: string}|null
	 */
	private function resolveVideoData(array $data, string $dir): ?array {
		if (!empty($data['video']) && is_array($data['video']) && isset($data['video']['type'])) {
			$type = $data['video']['type'];
			$value = isset($data['video']['value']) ? trim((string)$data['video']['value']) : '';

			if ($type === 'youtube') {
				$id = ReviewMedia::extractYouTubeId($value);

				return $id !== '' ? array('type' => 'youtube', 'value' => $id) : null;
			}

			if ($type === 'mp4' && $value !== '') {
				return array('type' => 'mp4', 'value' => $value);
			}

			return null;
		}

		if (!empty($data['video_file']) && is_array($data['video_file'])) {
			$file = $data['video_file'];
			$result = ReviewMedia::validateVideo($file, array('max_size' => (int)$this->config->get('config_review_video_max_size'), 'check_uploaded' => false));

			if ($result['ok']) {
				$this->createMediaDirectory($dir);

				$ext = $result['ext'] !== '' ? $result['ext'] : 'mp4';
				$filename = token(24) . '.' . $ext;
				$path = $dir . '/' . $filename;

				if (move_uploaded_file((string)$file['tmp_name'], DIR_IMAGE . $path)) {
					return array('type' => 'mp4', 'value' => $path);
				}
			}
		}

		return null;
	}

	/**
	 * Re-encode an uploaded image through GD (strips embedded payloads),
	 * downscale oversized images and persist it to the review directory.
	 */
	private function storeReviewImage(array $file, array $result, string $dir): string {
		$extension = '';

		if ($result['mime'] === 'image/png') {
			$extension = 'png';
		} elseif ($result['mime'] === 'image/webp') {
			$extension = 'webp';
		} else {
			$extension = 'jpg';
		}

		$source = ReviewMedia::loadImage((string)$file['tmp_name'], $result['mime']);

		if ($source === null) {
			return '';
		}

		$max_dimension = (int)$this->config->get('config_review_image_dimension');

		if ($max_dimension > 0 && ($result['width'] > $max_dimension || $result['height'] > $max_dimension)) {
			$scale = $max_dimension / max($result['width'], $result['height']);
			$new_width = max(1, (int)round($result['width'] * $scale));
			$new_height = max(1, (int)round($result['height'] * $scale));
			$resized = imagecreatetruecolor($new_width, $new_height);

			if ($resized === false) {
				imagedestroy($source);

				return '';
			}

			if ($result['mime'] === 'image/png') {
				imagealphablending($resized, false);
				imagesavealpha($resized, true);
			}

			imagecopyresampled($resized, $source, 0, 0, 0, 0, $new_width, $new_height, $result['width'], $result['height']);
			imagedestroy($source);
			$source = $resized;
		}

		$filename = token(24) . '.' . $extension;
		$path = $dir . '/' . $filename;

		if ($extension === 'png') {
			imagepng($source, DIR_IMAGE . $path);
		} elseif ($extension === 'webp') {
			imagewebp($source, DIR_IMAGE . $path, 85);
		} else {
			imagejpeg($source, DIR_IMAGE . $path, 85);
		}

		imagedestroy($source);

		return $path;
	}

	/**
	 * Create the review media directory (relative to DIR_IMAGE).
	 */
	private function createMediaDirectory(string $dir): void {
		if (!is_dir(DIR_IMAGE . $dir)) {
			@mkdir(DIR_IMAGE . $dir, 0777, true);
		}
	}

	/**
	 * Notify the configured admin alert e-mails about a new review.
	 */
	private function sendAdminNotification(int $product_id, array $data, float $rating): void {
		if (!in_array('review', (array)$this->config->get('config_mail_alert'), true)) {
			return;
		}

		$this->load->language('mail/review');
		$this->load->model('catalog/product');

		$product_info = $this->model_catalog_product->getProduct($product_id);

		$subject = sprintf($this->language->get('text_subject'), html_entity_decode($this->config->get('config_name'), ENT_QUOTES, 'UTF-8'));

		$message = $this->language->get('text_waiting') . "\n";
		$message .= sprintf($this->language->get('text_product'), html_entity_decode($product_info['name'] ?? '', ENT_QUOTES, 'UTF-8')) . "\n";
		$message .= sprintf($this->language->get('text_reviewer'), html_entity_decode($data['name'] ?? '', ENT_QUOTES, 'UTF-8')) . "\n";
		$message .= sprintf($this->language->get('text_rating'), ReviewRating::format($rating)) . "\n";
		$message .= $this->language->get('text_review') . "\n";
		$message .= html_entity_decode($data['text'] ?? '', ENT_QUOTES, 'UTF-8') . "\n\n";

		$this->sendMail($subject, $message);
	}

	/**
	 * Send a plain-text mail to the store alert addresses.
	 */
	private function sendMail(string $subject, string $message): void {
		$mail = new Mail($this->config->get('config_mail_engine'));
		$mail->smtp_hostname = $this->config->get('config_mail_smtp_hostname');
		$mail->smtp_username = $this->config->get('config_mail_smtp_username');
		$mail->smtp_password = html_entity_decode($this->config->get('config_mail_smtp_password'), ENT_QUOTES, 'UTF-8');
		$mail->smtp_port = $this->config->get('config_mail_smtp_port');
		$mail->smtp_timeout = $this->config->get('config_mail_smtp_timeout');
		$mail->smtp_auth_method = $this->config->get('config_mail_smtp_auth_method');
		$mail->smtp_oauth_token = $this->config->get('config_mail_smtp_oauth_token');
		$mail->smtp_oauth_refresh_token = $this->config->get('config_mail_smtp_oauth_refresh_token');
		$mail->smtp_oauth_client_id = $this->config->get('config_mail_smtp_oauth_client_id');
		$mail->smtp_oauth_client_secret = $this->config->get('config_mail_smtp_oauth_client_secret');

		$mail->setTo($this->config->get('config_email'));
		$mail->setFrom($this->config->get('config_email'));
		$mail->setSender(html_entity_decode($this->config->get('config_name'), ENT_QUOTES, 'UTF-8'));
		$mail->setSubject($subject);
		$mail->setText($message);
		$mail->on_token_refresh = function ($token) {
			$this->db->query("UPDATE " . DB_PREFIX . "setting SET `value` = '" . $this->db->escape($token) . "' WHERE `key` = 'config_mail_smtp_oauth_token' AND `store_id` = '" . (int)$this->config->get('config_store_id') . "'");
		};

		$mail->send();

		$emails = explode(',', $this->config->get('config_mail_alert_email'));

		foreach ($emails as $email) {
			if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
				$mail->setTo($email);
				$mail->on_token_refresh = function ($token) {
					$this->db->query("UPDATE " . DB_PREFIX . "setting SET `value` = '" . $this->db->escape($token) . "' WHERE `key` = 'config_mail_smtp_oauth_token' AND `store_id` = '" . (int)$this->config->get('config_store_id') . "'");
				};

				$mail->send();
			}
		}
	}
}
