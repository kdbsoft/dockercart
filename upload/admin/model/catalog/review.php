<?php
class ModelCatalogReview extends Model {
	public function addReview($data) {
		$this->db->query("INSERT INTO " . DB_PREFIX . "review SET author = '" . $this->db->escape($data['author']) . "', product_id = '" . (int)$data['product_id'] . "', text = '" . $this->db->escape(strip_tags($data['text'])) . "', rating = '" . (float)$data['rating'] . "', status = '" . (int)$data['status'] . "', verified = '" . (int)($data['verified'] ?? 0) . "', criteria_group_id = '" . (int)$data['criteria_group_id'] . "', date_added = '" . $this->db->escape($data['date_added']) . "'");

		$review_id = $this->db->getLastId();

		$this->saveCriteriaValues($review_id, $data);
		$this->saveMedia($review_id, $data);

		$this->cache->delete('product');

		$this->recalculateProductRating((int)$data['product_id']);

		return $review_id;
	}

	public function editReview($review_id, $data) {
		$this->db->query("UPDATE " . DB_PREFIX . "review SET author = '" . $this->db->escape($data['author']) . "', product_id = '" . (int)$data['product_id'] . "', text = '" . $this->db->escape(strip_tags($data['text'])) . "', rating = '" . (float)$data['rating'] . "', status = '" . (int)$data['status'] . "', verified = '" . (int)($data['verified'] ?? 0) . "', criteria_group_id = '" . (int)$data['criteria_group_id'] . "', date_added = '" . $this->db->escape($data['date_added']) . "', date_modified = NOW() WHERE review_id = '" . (int)$review_id . "'");

		$this->saveCriteriaValues($review_id, $data);
		$this->saveMedia($review_id, $data);

		$this->cache->delete('product');

		$this->recalculateProductRating((int)$data['product_id']);
	}

	public function deleteReview($review_id) {
		$query = $this->db->query("SELECT product_id FROM " . DB_PREFIX . "review WHERE review_id = '" . (int)$review_id . "' LIMIT 1");
		$product_id = $query->num_rows ? (int)$query->row['product_id'] : 0;

		// Replies are flat (one level deep) and own no media/criteria/votes.
		$this->db->query("DELETE FROM " . DB_PREFIX . "review WHERE parent_id = '" . (int)$review_id . "'");

		$this->db->query("DELETE FROM " . DB_PREFIX . "review_criteria_value WHERE review_id = '" . (int)$review_id . "'");
		$this->db->query("DELETE FROM " . DB_PREFIX . "review_image WHERE review_id = '" . (int)$review_id . "'");
		$this->db->query("DELETE FROM " . DB_PREFIX . "review_video WHERE review_id = '" . (int)$review_id . "'");
		$this->db->query("DELETE FROM " . DB_PREFIX . "review_vote WHERE review_id = '" . (int)$review_id . "'");
		$this->db->query("DELETE FROM " . DB_PREFIX . "review WHERE review_id = '" . (int)$review_id . "'");

		$this->cache->delete('product');

		if ($product_id) {
			$this->recalculateProductRating($product_id);
		}
	}

	public function copyReview($review_id) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "review WHERE review_id = '" . (int)$review_id . "'");

		if (!$query->num_rows) {
			return false;
		}

		$review = $query->row;

		$data = array(
			'product_id'        => $review['product_id'],
			'author'            => $review['author'],
			'text'              => $review['text'],
			'rating'            => $review['rating'],
			'status'            => $review['status'],
			'verified'          => $review['verified'],
			'criteria_group_id' => $review['criteria_group_id'],
			'date_added'        => $review['date_added'],
		);

		$new_review_id = $this->addReview($data);

		$criteria_values = $this->db->query("SELECT criteria_id, value FROM " . DB_PREFIX . "review_criteria_value WHERE review_id = '" . (int)$review_id . "'");

		foreach ($criteria_values->rows as $row) {
			$this->db->query("INSERT INTO " . DB_PREFIX . "review_criteria_value SET review_id = '" . (int)$new_review_id . "', criteria_id = '" . (int)$row['criteria_id'] . "', value = '" . $this->db->escape($row['value']) . "'");
		}

		$images = $this->db->query("SELECT image, sort_order FROM " . DB_PREFIX . "review_image WHERE review_id = '" . (int)$review_id . "'");

		foreach ($images->rows as $row) {
			$this->db->query("INSERT INTO " . DB_PREFIX . "review_image SET review_id = '" . (int)$new_review_id . "', image = '" . $this->db->escape($row['image']) . "', sort_order = '" . (int)$row['sort_order'] . "'");
		}

		$videos = $this->db->query("SELECT video_type, video FROM " . DB_PREFIX . "review_video WHERE review_id = '" . (int)$review_id . "'");

		foreach ($videos->rows as $row) {
			$this->db->query("INSERT INTO " . DB_PREFIX . "review_video SET review_id = '" . (int)$new_review_id . "', video_type = '" . $this->db->escape($row['video_type']) . "', video = '" . $this->db->escape($row['video']) . "', sort_order = '0'");
		}

		return $new_review_id;
	}

	public function getReview($review_id) {
		$query = $this->db->query("SELECT DISTINCT *, (SELECT pd.name FROM " . DB_PREFIX . "product_description pd WHERE pd.product_id = r.product_id AND pd.language_id = '" . (int)$this->config->get('config_language_id') . "') AS product, (SELECT COUNT(*) FROM " . DB_PREFIX . "review_vote v WHERE v.review_id = r.review_id AND v.vote = '1') AS likes, (SELECT COUNT(*) FROM " . DB_PREFIX . "review_vote v WHERE v.review_id = r.review_id AND v.vote = '0') AS dislikes FROM " . DB_PREFIX . "review r WHERE r.review_id = '" . (int)$review_id . "'");

		if (!$query->num_rows) {
			return $query->row;
		}

		$row = $query->row;
		$row['criteria_values'] = array();

		$values = $this->db->query("SELECT criteria_id, value FROM " . DB_PREFIX . "review_criteria_value WHERE review_id = '" . (int)$review_id . "'");

		foreach ($values->rows as $value) {
			$row['criteria_values'][(int)$value['criteria_id']] = $value['value'];
		}

		$row['images'] = array();

		$images = $this->db->query("SELECT review_image_id, image, sort_order FROM " . DB_PREFIX . "review_image WHERE review_id = '" . (int)$review_id . "' ORDER BY sort_order ASC");

		foreach ($images->rows as $image) {
			$row['images'][] = array(
				'review_image_id' => $image['review_image_id'],
				'image'           => $image['image'],
				'sort_order'      => $image['sort_order'],
			);
		}

		$row['videos'] = array();

		$videos = $this->db->query("SELECT review_video_id, video_type, video, sort_order FROM " . DB_PREFIX . "review_video WHERE review_id = '" . (int)$review_id . "' ORDER BY sort_order ASC");

		foreach ($videos->rows as $video) {
			$row['videos'][] = array(
				'review_video_id' => $video['review_video_id'],
				'video_type'      => $video['video_type'],
				'video'           => $video['video'],
				'sort_order'      => $video['sort_order'],
			);
		}

		return $row;
	}

	public function getReviews($data = array()) {
		$sql = "SELECT r.review_id, pd.name, r.author, r.rating, r.status, r.verified, r.date_added, (SELECT COUNT(*) FROM " . DB_PREFIX . "review ri WHERE ri.review_id = r.review_id) AS image_count, (SELECT COUNT(*) FROM " . DB_PREFIX . "review_video rv WHERE rv.review_id = r.review_id) AS video_count, (SELECT COUNT(*) FROM " . DB_PREFIX . "review_vote vl WHERE vl.review_id = r.review_id AND vl.vote = '1') AS likes, (SELECT COUNT(*) FROM " . DB_PREFIX . "review_vote vd WHERE vd.review_id = r.review_id AND vd.vote = '0') AS dislikes, (SELECT COUNT(*) FROM " . DB_PREFIX . "review rr WHERE rr.parent_id = r.review_id) AS reply_count, (SELECT COUNT(*) FROM " . DB_PREFIX . "review rr WHERE rr.parent_id = r.review_id AND rr.status = '0') AS reply_pending_count FROM " . DB_PREFIX . "review r LEFT JOIN " . DB_PREFIX . "product_description pd ON (r.product_id = pd.product_id) WHERE pd.language_id = '" . (int)$this->config->get('config_language_id') . "' AND r.parent_id IS NULL";

		if (!empty($data['filter_product'])) {
			$sql .= " AND pd.name LIKE '" . $this->db->escape($data['filter_product']) . "%'";
		}

		if (!empty($data['filter_author'])) {
			$sql .= " AND r.author LIKE '" . $this->db->escape($data['filter_author']) . "%'";
		}

		if (isset($data['filter_status']) && $data['filter_status'] !== '') {
			$sql .= " AND r.status = '" . (int)$data['filter_status'] . "'";
		}

		if (isset($data['filter_verified']) && $data['filter_verified'] !== '') {
			$sql .= " AND r.verified = '" . (int)$data['filter_verified'] . "'";
		}

		if (!empty($data['filter_date_added'])) {
			$sql .= " AND DATE(r.date_added) = DATE('" . $this->db->escape($data['filter_date_added']) . "')";
		}

		if (isset($data['filter_replies']) && $data['filter_replies'] === 'any') {
			$sql .= " AND EXISTS (SELECT 1 FROM " . DB_PREFIX . "review rr WHERE rr.parent_id = r.review_id)";
		}

		if (isset($data['filter_replies']) && $data['filter_replies'] === 'pending') {
			$sql .= " AND EXISTS (SELECT 1 FROM " . DB_PREFIX . "review rr WHERE rr.parent_id = r.review_id AND rr.status = '0')";
		}

		$sort_data = array(
			'pd.name',
			'r.author',
			'r.rating',
			'r.status',
			'r.date_added'
		);

		if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
			$sql .= " ORDER BY " . $data['sort'];
		} else {
			$sql .= " ORDER BY r.date_added";
		}

		if (isset($data['order']) && ($data['order'] == 'DESC')) {
			$sql .= " DESC";
		} else {
			$sql .= " ASC";
		}

		if (isset($data['start']) || isset($data['limit'])) {
			if ($data['start'] < 0) {
				$data['start'] = 0;
			}

			if ($data['limit'] < 1) {
				$data['limit'] = 20;
			}

			$sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
		}

		$query = $this->db->query($sql);

		return $query->rows;
	}

	public function getTotalReviews($data = array()) {
		$sql = "SELECT COUNT(*) AS total FROM " . DB_PREFIX . "review r LEFT JOIN " . DB_PREFIX . "product_description pd ON (r.product_id = pd.product_id) WHERE pd.language_id = '" . (int)$this->config->get('config_language_id') . "' AND r.parent_id IS NULL";

		if (!empty($data['filter_product'])) {
			$sql .= " AND pd.name LIKE '" . $this->db->escape($data['filter_product']) . "%'";
		}

		if (!empty($data['filter_author'])) {
			$sql .= " AND r.author LIKE '" . $this->db->escape($data['filter_author']) . "%'";
		}

		if (isset($data['filter_status']) && $data['filter_status'] !== '') {
			$sql .= " AND r.status = '" . (int)$data['filter_status'] . "'";
		}

		if (isset($data['filter_verified']) && $data['filter_verified'] !== '') {
			$sql .= " AND r.verified = '" . (int)$data['filter_verified'] . "'";
		}

		if (!empty($data['filter_date_added'])) {
			$sql .= " AND DATE(r.date_added) = DATE('" . $this->db->escape($data['filter_date_added']) . "')";
		}

		if (isset($data['filter_replies']) && $data['filter_replies'] === 'any') {
			$sql .= " AND EXISTS (SELECT 1 FROM " . DB_PREFIX . "review rr WHERE rr.parent_id = r.review_id)";
		}

		if (isset($data['filter_replies']) && $data['filter_replies'] === 'pending') {
			$sql .= " AND EXISTS (SELECT 1 FROM " . DB_PREFIX . "review rr WHERE rr.parent_id = r.review_id AND rr.status = '0')";
		}

		$query = $this->db->query($sql);

		return $query->row['total'];
	}

	public function getTotalReviewsAwaitingApproval() {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "review WHERE status = '0' AND parent_id IS NULL");

		return $query->row['total'];
	}

	public function updateReviewField($review_id, $data) {
		$string_fields = array('author', 'date_added');
		$int_fields = array('rating', 'status', 'verified');

		$sets = array();
		foreach ($string_fields as $field) {
			if (isset($data[$field])) {
				$sets[] = "`" . $field . "` = '" . $this->db->escape($data[$field]) . "'";
			}
		}
		foreach ($int_fields as $field) {
			if (isset($data[$field])) {
				if ($field === 'rating') {
					$sets[] = "`" . $field . "` = '" . (float)$data[$field] . "'";
				} else {
					$sets[] = "`" . $field . "` = '" . (int)$data[$field] . "'";
				}
			}
		}

		if (!empty($sets)) {
			$query = $this->db->query("SELECT product_id FROM " . DB_PREFIX . "review WHERE review_id = '" . (int)$review_id . "' LIMIT 1");
			$product_id = $query->num_rows ? (int)$query->row['product_id'] : 0;

			$this->db->query("UPDATE " . DB_PREFIX . "review SET " . implode(', ', $sets) . ", date_modified = NOW() WHERE review_id = '" . (int)$review_id . "'");

			if ($product_id) {
				$this->recalculateProductRating($product_id);
			}
		}
	}

	/**
	 * Recompute the aggregate rating cache for a product.
	 */
	public function recalculateProductRating($product_id) {
		$product_id = (int)$product_id;

		if ($product_id <= 0) {
			return;
		}

		$query = $this->db->query("SELECT rating FROM " . DB_PREFIX . "review WHERE product_id = '" . $product_id . "' AND status = '1' AND parent_id IS NULL");

		$rows = $query->rows;
		$ratings = array_map('floatval', array_column($rows, 'rating'));

		$rating = ReviewRating::average($ratings);
		$review_count = count($ratings);
		$distribution = ReviewRating::distribution($rows);

		$this->db->query("INSERT INTO " . DB_PREFIX . "product_rating (product_id, rating, review_count, distribution, date_modified) VALUES ('" . $product_id . "', '" . (float)$rating . "', '" . (int)$review_count . "', '" . $this->db->escape(json_encode($distribution)) . "', NOW()) ON DUPLICATE KEY UPDATE rating = VALUES(rating), review_count = VALUES(review_count), distribution = VALUES(distribution), date_modified = NOW()");
	}

	private function saveCriteriaValues($review_id, $data) {
		$this->db->query("DELETE FROM " . DB_PREFIX . "review_criteria_value WHERE review_id = '" . (int)$review_id . "'");

		if (empty($data['criteria']) || !is_array($data['criteria'])) {
			return;
		}

		foreach ($data['criteria'] as $criteria_id => $value) {
			$value = (string)$value;

			if (trim($value) === '') {
				continue;
			}

			$this->db->query("INSERT INTO " . DB_PREFIX . "review_criteria_value SET review_id = '" . (int)$review_id . "', criteria_id = '" . (int)$criteria_id . "', value = '" . $this->db->escape($value) . "'");
		}
	}

	private function saveMedia($review_id, $data) {
		$this->db->query("DELETE FROM " . DB_PREFIX . "review_image WHERE review_id = '" . (int)$review_id . "'");
		$this->db->query("DELETE FROM " . DB_PREFIX . "review_video WHERE review_id = '" . (int)$review_id . "'");

		$sort_order = 0;

		if (!empty($data['review_image']) && is_array($data['review_image'])) {
			foreach ($data['review_image'] as $image) {
				$image = trim((string)$image);

				if ($image === '') {
					continue;
				}

				$this->db->query("INSERT INTO " . DB_PREFIX . "review_image SET review_id = '" . (int)$review_id . "', image = '" . $this->db->escape($image) . "', sort_order = '" . (int)$sort_order . "'");

				$sort_order++;
			}
		}

		$video_type = isset($data['review_video_type']) ? $data['review_video_type'] : '';
		$video = isset($data['review_video']) ? trim((string)$data['review_video']) : '';

		if ($video_type === 'youtube' && $video !== '') {
			$video_id = ReviewMedia::extractYouTubeId($video);

			if ($video_id !== '') {
				$this->db->query("INSERT INTO " . DB_PREFIX . "review_video SET review_id = '" . (int)$review_id . "', video_type = 'youtube', video = '" . $this->db->escape($video_id) . "', sort_order = '0'");
			}
		} elseif ($video_type === 'mp4' && $video !== '') {
			$this->db->query("INSERT INTO " . DB_PREFIX . "review_video SET review_id = '" . (int)$review_id . "', video_type = 'mp4', video = '" . $this->db->escape($video) . "', sort_order = '0'");
		}
	}

	/**
	 * Add an admin reply to a review. Replies are flat (one level deep),
	 * always published, carry no rating/criteria/media and never touch the
	 * product rating cache.
	 *
	 * @return int reply review_id
	 */
	public function addReply($parent_id, $data) {
		$review_info = $this->getReview((int)$parent_id);

		if (!$review_info) {
			return 0;
		}

		$ip = isset($this->request->server['REMOTE_ADDR']) ? (string)$this->request->server['REMOTE_ADDR'] : '';

		$this->db->query("INSERT INTO " . DB_PREFIX . "review SET author = '" . $this->db->escape($data['author']) . "', customer_id = '0', product_id = '" . (int)$review_info['product_id'] . "', parent_id = '" . (int)$parent_id . "', author_is_admin = '1', text = '" . $this->db->escape(strip_tags($data['text'])) . "', rating = '0', status = '1', verified = '0', ip = '" . $this->db->escape($ip) . "', criteria_group_id = NULL, date_added = NOW()");

		return (int)$this->db->getLastId();
	}

	/**
	 * Replies (all statuses) for a review, oldest first.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getReplies($review_id) {
		$query = $this->db->query("SELECT review_id, author, author_is_admin, text, status, date_added FROM " . DB_PREFIX . "review WHERE parent_id = '" . (int)$review_id . "' ORDER BY date_added ASC");

		return $query->rows;
	}

	/**
	 * Number of replies awaiting moderation across all reviews.
	 */
	public function getTotalRepliesPending() {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "review WHERE parent_id IS NOT NULL AND status = '0'");

		return (int)$query->row['total'];
	}

	/**
	 * Reply counts for a review (for AJAX badge updates).
	 *
	 * @return array{reply_count: int, reply_pending_count: int}
	 */
	public function getReplyCounts($review_id) {
		$query = $this->db->query("SELECT COUNT(*) AS reply_count, SUM(status = '0') AS reply_pending_count FROM " . DB_PREFIX . "review WHERE parent_id = '" . (int)$review_id . "'");

		return array(
			'reply_count'        => (int)$query->row['reply_count'],
			'reply_pending_count' => (int)$query->row['reply_pending_count'],
		);
	}

	/**
	 * Update a whitelisted field on a reply.
	 */
	public function updateReplyField($reply_id, $data) {
		$sets = array();

		if (isset($data['status'])) {
			$sets[] = "status = '" . (int)$data['status'] . "'";
		}

		if (!empty($sets)) {
			$this->db->query("UPDATE " . DB_PREFIX . "review SET " . implode(', ', $sets) . ", date_modified = NOW() WHERE review_id = '" . (int)$reply_id . "'");
		}
	}
}
