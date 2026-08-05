<?php

function plural_form($count, $one, $few, $many) {
	$n = abs((int)$count);
	$n10 = $n % 10;
	$n100 = $n % 100;

	if ($n10 == 1 && $n100 != 11) {
		return $one;
	}

	if ($n10 >= 2 && $n10 <= 4 && ($n100 < 10 || $n100 >= 20)) {
		return $few;
	}

	return $many;
}

function product_count_label($count, $language_code) {
	$c = (int)$count;

	if (strpos($language_code, 'ru') === 0) {
		return $c . ' ' . plural_form($c, 'товар', 'товара', 'товаров');
	}

	if (strpos($language_code, 'uk') === 0) {
		return $c . ' ' . plural_form($c, 'товар', 'товари', 'товарів');
	}

	return $c . ' ' . ($c == 1 ? 'product' : 'products');
}

function review_count_label($count, $language_code = '') {
	$c = (int)$count;

	if ($language_code === '' && class_exists('Language')) {
		$language_code = Language::$code;
	}

	if (strpos($language_code, 'ru') === 0) {
		return $c . ' ' . plural_form($c, 'отзыв', 'отзыва', 'отзывов');
	}

	if (strpos($language_code, 'uk') === 0) {
		return $c . ' ' . plural_form($c, 'відгук', 'відгуки', 'відгуків');
	}

	return $c . ' ' . ($c == 1 ? 'review' : 'reviews');
}

function brand_count_label($count, $language_code = '') {
	$c = (int)$count;

	if ($language_code === '' && class_exists('Language')) {
		$language_code = Language::$code;
	}

	if (strpos($language_code, 'ru') === 0) {
		return $c . ' ' . plural_form($c, 'бренд', 'бренда', 'брендов');
	}

	if (strpos($language_code, 'uk') === 0) {
		return $c . ' ' . plural_form($c, 'бренд', 'бренди', 'брендів');
	}

	return $c . ' ' . ($c == 1 ? 'brand' : 'brands');
}
