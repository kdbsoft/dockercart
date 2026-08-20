<?php
namespace Cart;
class Currency {
	private $db;
	private $language;
	private $currencies = array();
	private $symbol_left_space = false;
	private $symbol_right_space = false;

	public function getCurrencies() {
		return $this->currencies;
	}

	private $defaultCurrency = null;

	public function __construct($registry) {
		$this->db = $registry->get('db');
		$this->language = $registry->get('language');
		$this->symbol_left_space = (bool)$registry->get('config')->get('config_symbol_left_space');
		$this->symbol_right_space = (bool)$registry->get('config')->get('config_symbol_right_space');
		$this->defaultCurrency = (string)$registry->get('config')->get('config_currency');

		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "currency");

		foreach ($query->rows as $result) {
			$this->currencies[$result['code']] = array(
				'currency_id'   => $result['currency_id'],
				'code'          => $result['code'],
				'title'         => $result['title'],
				'symbol_left'   => $result['symbol_left'],
				'symbol_right'  => $result['symbol_right'],
				'decimal_place' => $result['decimal_place'],
				'value'         => $result['value']
			);
		}
	}

	public function format($number, $currency, $value = '', $format = true) {
		$symbol_left = $this->currencies[$currency]['symbol_left'];
		$symbol_right = $this->currencies[$currency]['symbol_right'];
		$decimal_place = $this->currencies[$currency]['decimal_place'];

		if (!$value) {
			$value = $this->currencies[$currency]['value'];
		}

		$amount = $value ? (float)$number * $value : (float)$number;
		
		$amount = round($amount, (int)$decimal_place);
		
		if (!$format) {
			return $amount;
		}

		$string = '';

		if ($symbol_left) {
			$string .= $symbol_left;

			if ($this->symbol_left_space) {
				$string .= ' ';
			}
		}

		$thousand_point = ($currency === 'UAH') ? ' ' : $this->language->get('thousand_point');

		$string .= number_format($amount, (int)$decimal_place, $this->language->get('decimal_point'), $thousand_point);

		if ($symbol_right) {
			if (!$this->symbol_right_space) {
				$string .= ' ';
			}

			$string .= $symbol_right;
		}

		return $string;
	}

	public function convert($value, $from, $to) {
		if (isset($this->currencies[$from])) {
			$from = $this->currencies[$from]['value'];
		} else {
			$from = 1;
		}

		if (isset($this->currencies[$to])) {
			$to = $this->currencies[$to]['value'];
		} else {
			$to = 1;
		}

		return $value * ($to / $from);
	}
	
	public function getId($currency) {
		if (isset($this->currencies[$currency])) {
			return $this->currencies[$currency]['currency_id'];
		} else {
			return 0;
		}
	}

	public function getSymbolLeft($currency) {
		if (isset($this->currencies[$currency])) {
			return $this->currencies[$currency]['symbol_left'];
		} else {
			return '';
		}
	}

	public function getSymbolRight($currency) {
		if (isset($this->currencies[$currency])) {
			return $this->currencies[$currency]['symbol_right'];
		} else {
			return '';
		}
	}

	public function getDecimalPlace($currency) {
		if (isset($this->currencies[$currency])) {
			return $this->currencies[$currency]['decimal_place'];
		} else {
			return 0;
		}
	}

	public function getValue($currency) {
		if (isset($this->currencies[$currency])) {
			return $this->currencies[$currency]['value'];
		} else {
			return 0;
		}
	}

	/**
	 * Convert a price denominated in a product's currency into the store's
	 * default (base) currency. Currency::format() always treats its input as
	 * base-currency, so product prices must be normalised to the base currency
	 * before formatting. When the product has no explicit currency (NULL => 0),
	 * the price is already in the base currency and is returned unchanged.
	 *
	 * @param float $amount price in the product's own currency
	 * @param int   $currencyId oc_product.currency_id (0/NULL => store default)
	 * @return float price converted to the store base currency
	 */
	public function convertProductPrice($amount, $productCurrencyId) {
		$amount = (float)$amount;
		$productCurrencyId = (int)$productCurrencyId;

		if ($productCurrencyId <= 0) {
			return $amount;
		}

		// The base currency (rate 1.0) is the store default currency.
		$baseCurrencyValue = isset($this->currencies[$this->defaultCurrency])
			? (float)$this->currencies[$this->defaultCurrency]['value']
			: 1.0;

		$productCurrencyValue = 1.0;

		foreach ($this->currencies as $c) {
			if ((int)$c['currency_id'] === $productCurrencyId) {
				$productCurrencyValue = (float)$c['value'];
				break;
			}
		}

		if ($productCurrencyValue <= 0 || $baseCurrencyValue <= 0) {
			return $amount;
		}

		// Convert product-currency -> base-currency (product value relative to base).
		return $amount * ($baseCurrencyValue / $productCurrencyValue);
	}

	public function has($currency) {
		return isset($this->currencies[$currency]);
	}
}
