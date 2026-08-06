<?php
declare(strict_types=1);

namespace Tests\Unit\Library;

use PHPUnit\Framework\TestCase;

class DockercartRewardTest extends TestCase
{
	private const PREFIX = 'test_';

	private $db = null;
	private $config = null;
	private $reward = null;

	private static function prefix(): string
	{
		$prefix = getenv('DB_PREFIX') ?: 'oc_';

		return $prefix === 'oc_' ? self::PREFIX : $prefix;
	}

	public static function setUpBeforeClass(): void
	{
		require_once __DIR__ . '/../../../upload/system/engine/registry.php';
		require_once __DIR__ . '/../../../upload/system/library/config.php';
		require_once __DIR__ . '/../../../upload/system/library/db/mysqli.php';
		require_once __DIR__ . '/../../../upload/system/library/dockercart_reward.php';
	}

	protected function setUp(): void
	{
		$host = getenv('DB_HOSTNAME') ?: 'localhost';
		$user = getenv('DB_USERNAME') ?: 'dockercart';
		$pass = getenv('DB_PASSWORD') ?: 'dockercart_password';
		$name = getenv('DB_DATABASE') ?: 'dockercart';
		$port = (int)(getenv('DB_PORT') ?: 3306);

		if (!class_exists(\mysqli::class)) {
			self::markTestSkipped('mysqli extension not available');

			return;
		}

		try {
			$con = new \mysqli($host, $user, $pass, $name, $port);

			if ($con->connect_errno) {
				self::markTestSkipped('Database connection not available: ' . $con->connect_error);

				return;
			}

			$con->close();
		} catch (\mysqli_sql_exception $e) {
			self::markTestSkipped('Database connection not available: ' . $e->getMessage());

			return;
		}

		if (!defined('DB_PREFIX')) {
			define('DB_PREFIX', self::prefix());
		}

		$this->db = new \DB\MySQLi($host, $user, $pass, $name, $port);

		$config = new \Config();
		$config->set('config_reward_auto_award', '1');
		$config->set('config_reward_auto_revoke', '1');

		$registry = new \Registry();
		$registry->set('db', $this->db);
		$registry->set('config', $config);
		$this->config = $config;
		$registry->set('language', new class {
			public function get(string $key): string {
				return 'test';
			}
		});

		$this->reward = new \DockercartReward($registry);

		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "customer_reward` (
			`customer_reward_id` int(11) NOT NULL AUTO_INCREMENT,
			`customer_id` int(11) NOT NULL,
			`order_id` int(11) NOT NULL,
			`description` text NOT NULL,
			`points` int(8) NOT NULL,
			`date_added` datetime NOT NULL,
			PRIMARY KEY (`customer_reward_id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "order` (
			`order_id` int(11) NOT NULL AUTO_INCREMENT,
			`customer_id` int(11) NOT NULL,
			`paid_amount` decimal(15,4) NOT NULL DEFAULT '0.0000',
			`reward_awarded` tinyint(1) NOT NULL DEFAULT '0',
			`reward_revoked_points` int(11) NOT NULL DEFAULT '0',
			`order_status_id` int(11) NOT NULL DEFAULT '0',
			`date_modified` datetime NOT NULL,
			PRIMARY KEY (`order_id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "order_product` (
			`order_product_id` int(11) NOT NULL AUTO_INCREMENT,
			`order_id` int(11) NOT NULL,
			`product_id` int(11) NOT NULL,
			`reward` int(8) NOT NULL DEFAULT '0',
			PRIMARY KEY (`order_product_id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
	}

	protected function tearDown(): void
	{
		if (!$this->db) {
			return;
		}

		$this->db->query("TRUNCATE TABLE `" . DB_PREFIX . "customer_reward`");
		$this->db->query("TRUNCATE TABLE `" . DB_PREFIX . "order`");
		$this->db->query("TRUNCATE TABLE `" . DB_PREFIX . "order_product`");
	}

	private function createOrder(int $customer_id, int $reward_points, int $reward_awarded = 0, int $reward_revoked = 0): int
	{
		$this->db->query("INSERT INTO `" . DB_PREFIX . "order` SET
			customer_id = '" . (int)$customer_id . "',
			paid_amount = '100.0000',
			reward_awarded = '" . (int)$reward_awarded . "',
			reward_revoked_points = '" . (int)$reward_revoked . "',
			order_status_id = '1',
			date_modified = NOW()");

		$order_id = (int)$this->db->getLastId();

		$this->db->query("INSERT INTO `" . DB_PREFIX . "order_product` SET order_id = '" . $order_id . "', product_id = '1', reward = '" . (int)$reward_points . "'");

		// When the order is marked awarded, mirror the positive reward row that
		// awardOrderReward() would have written, so the ledger is consistent.
		if ($reward_awarded) {
			$this->db->query("INSERT INTO `" . DB_PREFIX . "customer_reward` SET
				customer_id = '" . (int)$customer_id . "',
				order_id = '" . $order_id . "',
				description = 'test',
				points = '" . (int)$reward_points . "',
				date_added = NOW()");
		}

		return $order_id;
	}

	private function rewardBalance(int $customer_id): int
	{
		$query = $this->db->query("SELECT COALESCE(SUM(points), 0) AS total FROM `" . DB_PREFIX . "customer_reward` WHERE customer_id = '" . (int)$customer_id . "'");

		return (int)$query->row['total'];
	}

	private function orderRow(int $order_id): array
	{
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "order` WHERE order_id = '" . (int)$order_id . "'");

		return $query->row;
	}

	public function testGetOrderRewardPoints(): void
	{
		$order_id = $this->createOrder(10, 25);

		$this->assertSame(25, $this->reward->getOrderRewardPoints($order_id));
	}

	public function testGetOrderRewardPointsZeroWhenNoProducts(): void
	{
		$order_id = $this->createOrder(10, 0);

		$this->assertSame(0, $this->reward->getOrderRewardPoints($order_id));
	}

	public function testAwardWritesPositiveRowAndFlipsFlag(): void
	{
		$order_id = $this->createOrder(10, 25);

		$this->reward->awardOrderReward($order_id);

		$this->assertSame(25, $this->rewardBalance(10));
		$this->assertSame('1', $this->orderRow($order_id)['reward_awarded']);
	}

	public function testAwardIdempotent(): void
	{
		$order_id = $this->createOrder(10, 25);

		$this->reward->awardOrderReward($order_id);
		$this->reward->awardOrderReward($order_id);

		$this->assertSame(25, $this->rewardBalance(10));
	}

	public function testAwardSkipsGuestOrder(): void
	{
		$order_id = $this->createOrder(0, 25);

		$this->reward->awardOrderReward($order_id);

		$this->assertSame(0, $this->rewardBalance(0));
		$this->assertSame('0', $this->orderRow($order_id)['reward_awarded']);
	}

	public function testAwardSkipsZeroPoints(): void
	{
		$order_id = $this->createOrder(10, 0);

		$this->reward->awardOrderReward($order_id);

		$this->assertSame(0, $this->rewardBalance(10));
		$this->assertSame('0', $this->orderRow($order_id)['reward_awarded']);
	}

	public function testRevokeFullReversal(): void
	{
		$order_id = $this->createOrder(10, 25, 1);

		$this->reward->revokeOrderReward($order_id, 1.0);

		$this->assertSame(0, $this->rewardBalance(10));
		$this->assertSame(25, (int)$this->orderRow($order_id)['reward_revoked_points']);
	}

	public function testRevokeIdempotent(): void
	{
		$order_id = $this->createOrder(10, 25, 1);

		$this->reward->revokeOrderReward($order_id, 1.0);
		$this->reward->revokeOrderReward($order_id, 1.0);

		$this->assertSame(0, $this->rewardBalance(10));
		$this->assertSame(25, (int)$this->orderRow($order_id)['reward_revoked_points']);
	}

	public function testRevokePartialProportional(): void
	{
		$order_id = $this->createOrder(10, 100, 1);

		$this->reward->revokeOrderReward($order_id, 0.3);

		$this->assertSame(70, $this->rewardBalance(10));
		$this->assertSame(30, (int)$this->orderRow($order_id)['reward_revoked_points']);
	}

	public function testRevokeMultiplePartialConvergesToZero(): void
	{
		$order_id = $this->createOrder(10, 100, 1);

		$this->reward->revokeOrderReward($order_id, 0.3);   // revoke 30
		$this->reward->revokeOrderReward($order_id, 0.3);   // revoke min(70, 30) = 30
		$this->reward->revokeOrderReward($order_id, 1.0);   // revoke min(40, 100) = 40

		$this->assertSame(0, $this->rewardBalance(10));
		$this->assertSame(100, (int)$this->orderRow($order_id)['reward_revoked_points']);
	}

	public function testRevokeClampedRatio(): void
	{
		$order_id = $this->createOrder(10, 100, 1);

		$this->reward->revokeOrderReward($order_id, 2.0);

		$this->assertSame(0, $this->rewardBalance(10));
		$this->assertSame(100, (int)$this->orderRow($order_id)['reward_revoked_points']);

		$order_id2 = $this->createOrder(11, 100, 1);
		$this->reward->revokeOrderReward($order_id2, -0.5);

		$this->assertSame(100, $this->rewardBalance(11));
		$this->assertSame(0, (int)$this->orderRow($order_id2)['reward_revoked_points']);
	}

	public function testRevokeFloor(): void
	{
		$order_id = $this->createOrder(10, 100, 1);

		$this->reward->revokeOrderReward($order_id, 0.33);  // floor(33) = 33

		$this->assertSame(67, $this->rewardBalance(10));
		$this->assertSame(33, (int)$this->orderRow($order_id)['reward_revoked_points']);
	}

	public function testRevokeSkipsUnAwardedOrder(): void
	{
		$order_id = $this->createOrder(10, 25, 0);

		$this->reward->revokeOrderReward($order_id, 1.0);

		$this->assertSame(0, $this->rewardBalance(10));
		$this->assertSame(0, (int)$this->orderRow($order_id)['reward_revoked_points']);
	}

	public function testDelayedAwardSkipsYoungOrder(): void
	{
		$order_id = $this->createOrder(10, 25);

		$this->db->query("UPDATE `" . DB_PREFIX . "order` SET date_modified = NOW() WHERE order_id = '" . (int)$order_id . "'");

		$this->config->set('config_reward_delay_days', '14');

		$this->reward->awardOrderReward($order_id);

		$this->assertSame(0, $this->rewardBalance(10));
		$this->assertSame('0', $this->orderRow($order_id)['reward_awarded']);
	}

	public function testDelayedAwardAwardsMatureOrder(): void
	{
		$order_id = $this->createOrder(10, 25);

		$this->db->query("UPDATE `" . DB_PREFIX . "order` SET date_modified = DATE_SUB(NOW(), INTERVAL 15 DAY) WHERE order_id = '" . (int)$order_id . "'");

		$this->reward->awardOrderReward($order_id);

		$this->assertSame(25, $this->rewardBalance(10));
		$this->assertSame('1', $this->orderRow($order_id)['reward_awarded']);
	}

	public function testDelayedAwardUsesConfiguredDays(): void
	{
		$order_id = $this->createOrder(10, 25);

		$this->db->query("UPDATE `" . DB_PREFIX . "order` SET date_modified = DATE_SUB(NOW(), INTERVAL 3 DAY) WHERE order_id = '" . (int)$order_id . "'");

		$this->config->set('config_reward_delay_days', '2');

		$this->reward->awardOrderReward($order_id);

		$this->assertSame(25, $this->rewardBalance(10));
		$this->assertSame('1', $this->orderRow($order_id)['reward_awarded']);
	}

	public function testDelayedAwardDefaultsToImmediateWhenUnset(): void
	{
		$order_id = $this->createOrder(10, 25);

		$this->db->query("UPDATE `" . DB_PREFIX . "order` SET date_modified = DATE_SUB(NOW(), INTERVAL 3 DAY) WHERE order_id = '" . (int)$order_id . "'");

		// config_reward_delay_days not set → award immediately.
		$this->config->set('config_reward_delay_days', null);

		$this->reward->awardOrderReward($order_id);

		$this->assertSame(25, $this->rewardBalance(10));
		$this->assertSame('1', $this->orderRow($order_id)['reward_awarded']);
	}
}
