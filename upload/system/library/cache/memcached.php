<?php
namespace Cache;
class Memcached {
	private $expire;
	private $memcached;

	const CACHEDUMP_LIMIT = 9999;

	public function __construct($expire) {
		$this->expire = $expire;

		$hostname = defined('MEMCACHED_HOSTNAME') ? MEMCACHED_HOSTNAME : (defined('CACHE_HOSTNAME') ? CACHE_HOSTNAME : 'memcached');
		$port = defined('MEMCACHED_PORT') ? (int) MEMCACHED_PORT : (defined('CACHE_PORT') ? (int) CACHE_PORT : 11211);

		$this->memcached = new \Memcached();
		$this->memcached->addServer($hostname, $port);
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
