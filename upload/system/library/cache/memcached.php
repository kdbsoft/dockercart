<?php
namespace Cache;
class Memcached {
	private $expire;
	private $memcached;

	const CACHEDUMP_LIMIT = 9999;

	public function __construct($expire) {
		$this->expire = $expire;
		$this->memcached = new \Memcached();

		$this->memcached->addServer(CACHE_HOSTNAME, CACHE_PORT);
	}

	public function get($key) {
		return $this->memcached->get(CACHE_PREFIX . $key);
	}

	public function set($key, $value) {
		return $this->memcached->set(CACHE_PREFIX . $key, $value, $this->expire);
	}

	public function delete($key) {
		$prefix = CACHE_PREFIX . $key;

		if (method_exists($this->memcached, 'getAllKeys')) {
			$keys = $this->memcached->getAllKeys();

			if (is_array($keys)) {
				foreach ($keys as $cached_key) {
					if (strpos($cached_key, $prefix) === 0) {
						$this->memcached->delete($cached_key);
					}
				}

				return;
			}
		}

		$this->memcached->delete($prefix);
	}

	public function flush() {
		$this->memcached->flush();
	}
}
