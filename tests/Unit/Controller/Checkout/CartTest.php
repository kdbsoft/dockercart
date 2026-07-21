<?php
declare(strict_types=1);

namespace Tests\Unit\Controller\Checkout;

use PHPUnit\Framework\TestCase;

class CartTest extends TestCase
{
	public static function localeProvider(): array
	{
		return [
			'en-gb' => ['en-gb'],
			'ru-ua' => ['ru-ua'],
			'uk-ua' => ['uk-ua'],
		];
	}

	private function loadLanguage(string $locale): array
	{
		$file = __DIR__ . '/../../../../upload/catalog/language/' . $locale . '/checkout/cart.php';

		$this->assertFileExists($file);

		$_ = [];
		require $file;

		return $_;
	}

	/**
	 * @dataProvider localeProvider
	 */
	public function testErrorVariantRequiredKeyExists(string $locale): void
	{
		$lang = $this->loadLanguage($locale);

		$this->assertArrayHasKey('error_variant_required', $lang);
		$this->assertNotEmpty($lang['error_variant_required']);
		$this->assertIsString($lang['error_variant_required']);
	}

	/**
	 * @dataProvider localeProvider
	 */
	public function testErrorVariantInvalidKeyExists(string $locale): void
	{
		$lang = $this->loadLanguage($locale);

		$this->assertArrayHasKey('error_variant_invalid', $lang);
		$this->assertNotEmpty($lang['error_variant_invalid']);
		$this->assertIsString($lang['error_variant_invalid']);
	}

	public function testAllLocalesHaveSameKeys(): void
	{
		$locales = ['en-gb', 'ru-ua', 'uk-ua'];
		$keysByLocale = [];

		foreach ($locales as $locale) {
			$keysByLocale[$locale] = array_keys($this->loadLanguage($locale));
		}

		$enKeys = $keysByLocale['en-gb'];

		foreach ($locales as $locale) {
			if ($locale === 'en-gb') {
				continue;
			}

			$missing = array_diff($enKeys, $keysByLocale[$locale]);

			$this->assertSame([], array_values($missing), "Locale $locale is missing keys: " . implode(', ', $missing));
		}
	}
}
