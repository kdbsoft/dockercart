<?php
namespace Cache;
class Mem {
	private $expire;
	private $memcache;
	
	const CACHEDUMP_LIMIT = 9999;

	public function __construct($expire) {
		$this->expire = $expire;

		$this->memcache = new \Memcache();
		$this->memcache->pconnect(CACHE_HOSTNAME, CACHE_PORT);
	}

	public function get($key) {
		return $this->memcache->get(CACHE_PREFIX . $key);
	}

	public function set($key, $value) {
		return $this->memcache->set(CACHE_PREFIX . $key, $value, MEMCACHE_COMPRESSED, $this->expire);
	}

	public function delete($key) {
		$prefix = CACHE_PREFIX . $key;
		$deleted = false;

		$stats = $this->memcache->getExtendedStats('slabs');

		if (is_array($stats)) {
			foreach ($stats as $server_stats) {
				if (!is_array($server_stats)) {
					continue;
				}

				foreach (array_keys($server_stats) as $slab_id) {
					if (!is_numeric($slab_id)) {
						continue;
					}

					$cache_dump = $this->memcache->getExtendedStats('cachedump', (int)$slab_id, self::CACHEDUMP_LIMIT);

					if (!is_array($cache_dump)) {
						continue;
					}

					foreach ($cache_dump as $entries) {
						if (!is_array($entries)) {
							continue;
						}

						foreach (array_keys($entries) as $cached_key) {
							if (strpos($cached_key, $prefix) === 0) {
								$this->memcache->delete($cached_key);
								$deleted = true;
							}
						}
					}
				}
			}
		}

		if (!$deleted) {
			$this->memcache->delete($prefix);
		}
	}

	public function flush() {
		$this->memcache->flush();
	}
}
