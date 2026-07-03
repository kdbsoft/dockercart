<?php

declare(strict_types=1);

class DockercartExtensionStore {
	const YML_ENDPOINT = 'http://licensing.docker.localhost:8080/api/v1/yml/';
	const STORE_DOMAIN = 'https://dockercart.net/extensions';
	const CACHE_TTL = 1800;

	private $registry;
	private $cache_dir;

	public function __construct($registry) {
		$this->registry = $registry;
		$this->cache_dir = DIR_STORAGE . 'dockercart/extension_store/';
	}

	public function resolveLanguage(): string {
		$session = $this->registry->get('session');
		$config = $this->registry->get('config');

		$code = $session->data['language'] ?? $config->get('config_admin_language') ?? 'en-gb';

		$map = array(
			'en-gb' => 'en',
			'ru-ua' => 'ru',
			'uk-ua' => 'uk',
		);

		return $map[$code] ?? 'en';
	}

	public function getYml(string $lang): SimpleXMLElement {
		$lang = preg_replace('/[^a-z]/', '', $lang) ?: 'en';
		$cache_file = $this->cache_dir . 'yml_' . $lang . '.xml';

		if (is_file($cache_file) && (time() - filemtime($cache_file)) < self::CACHE_TTL) {
			$xml = simplexml_load_file($cache_file);

			if ($xml !== false) {
				return $xml;
			}
		}

		$url = self::YML_ENDPOINT . $lang;

		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_ENCODING, '');

		$parsed = parse_url($url);
		$gateway_ip = @gethostbyname('host.docker.internal');

		if ($gateway_ip !== 'host.docker.internal') {
			curl_setopt($ch, CURLOPT_RESOLVE, array(
				$parsed['host'] . ':' . ($parsed['port'] ?? 80) . ':' . $gateway_ip
			));
		}

		$response = curl_exec($ch);
		$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if ($http_code !== 200 || empty($response)) {
			if ($lang !== 'en') {
				return $this->getYml('en');
			}

			throw new RuntimeException('Failed to fetch YML catalog. HTTP ' . $http_code);
		}

		if (!is_dir($this->cache_dir)) {
			mkdir($this->cache_dir, 0755, true);
		}

		file_put_contents($cache_file, $response);

		$xml = simplexml_load_string($response);

		if ($xml === false) {
			throw new RuntimeException('Failed to parse YML catalog XML');
		}

		return $xml;
	}

	public function clearCache(): void {
		if (!is_dir($this->cache_dir)) {
			return;
		}

		$files = glob($this->cache_dir . 'yml_*.xml');

		if ($files === false) {
			return;
		}

		foreach ($files as $file) {
			unlink($file);
		}
	}

	public function buildCategoriesTree(SimpleXMLElement $yml): array {
		$categories = array();
		$children = array();

		foreach ($yml->shop->categories->category as $cat) {
			$id = (string) $cat['id'];
			$name = (string) $cat;
			$parent = isset($cat['parentId']) ? (string) $cat['parentId'] : null;

			$categories[$id] = array(
				'id' => $id,
				'name' => $name,
				'parent_id' => $parent,
				'children' => array(),
			);

			if ($parent !== null) {
				$children[$parent][] = $id;
			}
		}

		$tree = array();

		foreach ($categories as $id => &$cat) {
			if ($cat['parent_id'] === null) {
				$tree[] = &$cat;
			} elseif (isset($categories[$cat['parent_id']])) {
				$categories[$cat['parent_id']]['children'][] = &$cat;
			} else {
				$tree[] = &$cat;
			}
		}
		unset($cat);

		return $tree;
	}

	public function getCategoryDescendants(SimpleXMLElement $yml, string $category_id): array {
		$ids = array($category_id);
		$all_categories = array();

		foreach ($yml->shop->categories->category as $cat) {
			$all_categories[(string) $cat['id']] = array(
				'id' => (string) $cat['id'],
				'parent_id' => isset($cat['parentId']) ? (string) $cat['parentId'] : null,
			);
		}

		$stack = array($category_id);

		while (!empty($stack)) {
			$current = array_pop($stack);

			foreach ($all_categories as $id => $cat) {
				if ($cat['parent_id'] === $current && !in_array($id, $ids, true)) {
					$ids[] = $id;
					$stack[] = $id;
				}
			}
		}

		return $ids;
	}

	public function getOffers(SimpleXMLElement $yml, ?array $category_ids = null): array {
		$offers = array();

		foreach ($yml->shop->offers->offer as $offer) {
			$offer_category = (string) $offer->categoryId;

			if ($category_ids !== null && !in_array($offer_category, $category_ids, true)) {
				continue;
			}

			$pictures = array();

			foreach ($offer->picture as $pic) {
				$pictures[] = (string) $pic;
			}

			$params = array();

			foreach ($offer->param as $param) {
				$params[(string) $param['code']] = array(
					'name' => (string) $param['name'],
					'value' => (string) $param,
				);
			}

			$buy_url = '';

			if (!empty((string) $offer->url)) {
				$raw_url = (string) $offer->url;

				if (preg_match('#^https?://#', $raw_url)) {
					$buy_url = $raw_url;
				} else {
					$buy_url = self::STORE_DOMAIN . '/' . ltrim($raw_url, '/');
				}
			}

			if (empty($buy_url)) {
				$buy_url = self::STORE_DOMAIN;
			}

			$name = (string) $offer->name;

			if (empty($name)) {
				$name = (string) $offer->model;
			}

			if (empty($name)) {
				$name = 'Extension #' . (string) $offer['id'];
			}

			$description = '';

			if (isset($offer->description)) {
				$description = (string) $offer->description;
			}

			$offers[] = array(
				'id' => (string) $offer['id'],
				'name' => $name,
				'description' => $description,
				'price' => (float) $offer->price,
				'currency' => (string) $offer->currencyId,
				'category_id' => $offer_category,
				'pictures' => $pictures,
				'params' => $params,
				'buy_url' => $buy_url,
				'available' => ((string) $offer['available']) === 'true',
				'version' => $params['version']['value'] ?? '',
				'price_type' => $params['price_type']['value'] ?? '',
			);
		}

		return $offers;
	}
}
