<?php
/**
 * DockerCart Auto Reward Points
 *
 * Awards the order's reward points (SUM(oc_order_product.reward)) to the
 * customer when the order enters a complete status, and revokes them
 * proportionally when the order is refunded or leaves the complete status.
 *
 * Awarding writes a positive oc_customer_reward row; revoking writes negative
 * reversal rows. Both are idempotent:
 *   - oc_order.reward_awarded flips to 1 on award and is never reset, so an
 *     order can only be awarded once (a status bounce back to complete cannot
 *     double-award).
 *   - oc_order.reward_revoked_points accumulates every revoked amount, so
 *     repeated partial refunds converge to zero (min(remaining, target)).
 *
 * Unlike spent-point rows written by extension/total/reward::confirm, reversal
 * rows are never deleted by unconfirm() (it only removes points < 0 for the
 * order — reversals are negative). To keep that contract, callers must run
 * revokeOrderReward() AFTER the unconfirm cycle of a status reversal.
 *
 * Direct SQL only (no ModelCustomerCustomer::addReward) so the optional
 * admin_mail_reward event is not fired inside the status-change transaction
 * (Mail::send may throw and roll back the whole transition).
 */

declare(strict_types=1);

/** @property \DB $db
 * @property \Config $config
 * @property \Language $language */
class DockercartReward {
	/** @var \Registry */
	private $registry;

	public function __construct($registry) {
		$this->registry = $registry;
	}

	public function __get($key) {
		return $this->registry->get($key);
	}

	/**
	 * Total reward points the order's products are worth (0 when none).
	 */
	public function getOrderRewardPoints(int $order_id): int {
		$query = $this->db->query("SELECT COALESCE(SUM(reward), 0) AS total FROM `" . DB_PREFIX . "order_product` WHERE order_id = '" . (int)$order_id . "'");

		return (int)$query->row['total'];
	}

	/**
	 * Award the order's reward points to the customer. No-op unless
	 * config_reward_auto_award is enabled and the order is a real customer
	 * order with reward points that has not been awarded yet.
	 *
	 * Runs inside the caller's transaction; throws on failure so the status
	 * transition rolls back atomically with the award.
	 */
	public function awardOrderReward(int $order_id): void {
		if (!$this->config->get('config_reward_auto_award')) {
			return;
		}

		$order_query = $this->db->query("SELECT customer_id, reward_awarded, date_modified FROM `" . DB_PREFIX . "order` WHERE order_id = '" . (int)$order_id . "' FOR UPDATE");

		if (!$order_query->num_rows || (int)$order_query->row['reward_awarded']) {
			return;
		}

		$customer_id = (int)$order_query->row['customer_id'];
		$points = $this->getOrderRewardPoints($order_id);

		if ($customer_id <= 0 || $points <= 0) {
			return;
		}

		// Delayed award: when config_reward_delay_days is set (> 0), the order
		// must have been in its (complete) status for at least that many days
		// before points are granted. 0/unset = award immediately. date_modified
		// is bumped by every status change, so it tracks when the order entered
		// the current status.
		$delay_days = (int)$this->config->get('config_reward_delay_days');

		if ($delay_days > 0) {
			$check = $this->db->query("SELECT order_id FROM `" . DB_PREFIX . "order` WHERE order_id = '" . (int)$order_id . "' AND date_modified < DATE_SUB(NOW(), INTERVAL " . (int)$delay_days . " DAY)");

			if (!$check->num_rows) {
				return;
			}
		}

		$this->db->query("INSERT INTO `" . DB_PREFIX . "customer_reward` SET
			customer_id = '" . (int)$customer_id . "',
			order_id = '" . (int)$order_id . "',
			points = '" . (int)$points . "',
			description = '" . $this->db->escape($this->language->get('text_order_id') . ' #' . $order_id) . "',
			date_added = NOW()");

		$this->db->query("UPDATE `" . DB_PREFIX . "order` SET reward_awarded = '1', date_modified = NOW() WHERE order_id = '" . (int)$order_id . "'");
	}

	/**
	 * Revoke awarded reward points. $ratio is clamped to [0, 1] and the
	 * revoked amount never exceeds what is still outstanding
	 * (awarded - already revoked), so the customer balance never goes
	 * negative because of this order.
	 *
	 * Runs inside the caller's transaction; throws on failure.
	 */
	public function revokeOrderReward(int $order_id, float $ratio = 1.0): void {
		if (!$this->config->get('config_reward_auto_revoke')) {
			return;
		}

		$order_query = $this->db->query("SELECT customer_id, reward_awarded, reward_revoked_points FROM `" . DB_PREFIX . "order` WHERE order_id = '" . (int)$order_id . "' FOR UPDATE");

		if (!$order_query->num_rows || !(int)$order_query->row['reward_awarded']) {
			return;
		}

		$customer_id = (int)$order_query->row['customer_id'];

		if ($customer_id <= 0) {
			return;
		}

		$awarded = $this->getOrderRewardPoints($order_id);

		if ($awarded <= 0) {
			return;
		}

		$ratio = max(0.0, min(1.0, (float)$ratio));
		$remaining = $awarded - (int)$order_query->row['reward_revoked_points'];
		$target = (int)floor($awarded * $ratio);
		$revoke = min($remaining, $target);

		if ($revoke <= 0) {
			return;
		}

		$this->db->query("INSERT INTO `" . DB_PREFIX . "customer_reward` SET
			customer_id = '" . (int)$customer_id . "',
			order_id = '" . (int)$order_id . "',
			points = '" . (int)-$revoke . "',
			description = '" . $this->db->escape($this->language->get('text_reward_refunded') . ' #' . $order_id) . "',
			date_added = NOW()");

		$this->db->query("UPDATE `" . DB_PREFIX . "order` SET reward_revoked_points = reward_revoked_points + '" . (int)$revoke . "', date_modified = NOW() WHERE order_id = '" . (int)$order_id . "'");
	}
}
