<?php
namespace Cache;
class Redis {
	private $expire;
	private $cache;

	public function __construct($expire) {
		$this->expire = $expire;

		$this->cache = new \Redis();
		$this->cache->pconnect(CACHE_HOSTNAME, CACHE_PORT);
	}

	public function get($key) {
		$data = $this->cache->get(CACHE_PREFIX . $key);

		return json_decode($data, true);
	}

	public function set($key, $value) {
		$status = $this->cache->set(CACHE_PREFIX . $key, json_encode($value));

		if ($status) {
			$this->cache->expire(CACHE_PREFIX . $key, $this->expire);
		}

		return $status;
	}

	public function delete($key) {
		$prefix = CACHE_PREFIX . $key;
		$pattern = $prefix . '*';

		$iterator = null;
		$keys = array();

		while ($scan_keys = $this->cache->scan($iterator, $pattern, 1000)) {
			foreach ($scan_keys as $scan_key) {
				$keys[] = $scan_key;
			}
		}

		if ($keys) {
			$this->cache->del($keys);
		} else {
			$this->cache->del($prefix);
		}
	}

	public function flush() {
		$this->cache->flushDb();
	}
}