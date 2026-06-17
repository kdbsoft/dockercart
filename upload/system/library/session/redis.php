<?php
namespace Session;
class Redis {
	private $redis;
	private $ttl = 86400;

	public function __construct() {
		$this->redis = new \Redis();
		$hostname = defined('REDIS_HOSTNAME') ? REDIS_HOSTNAME : 'redis';
		$port = defined('REDIS_PORT') ? (int) REDIS_PORT : 6379;
		$this->redis->pconnect($hostname, $port);

		if (defined('REDIS_PASSWORD') && REDIS_PASSWORD) {
			$this->redis->auth(REDIS_PASSWORD);
		}
	}

	public function read($session_id) {
		$data = $this->redis->get('session_' . $session_id);
		return $data !== false ? json_decode($data, true) : array();
	}

	public function write($session_id, $data) {
		if ($session_id) {
			$this->redis->setex('session_' . $session_id, $this->ttl, json_encode($data));
		}
	}

	public function destroy($session_id) {
		$this->redis->del('session_' . $session_id);
	}
}
