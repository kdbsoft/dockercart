<?php
declare(strict_types=1);

namespace Tests\Unit\Library;

use Dockercart\KeyboardLayout;
use PHPUnit\Framework\TestCase;

class KeyboardLayoutTest extends TestCase
{
	protected function setUp(): void
	{
		require_once __DIR__ . '/../../../upload/system/library/dockercart/keyboard_layout.php';
	}

	public function testConvertLatinToCyrillic(): void
	{
		$this->assertSame('привет', KeyboardLayout::convert('ghbdtn'));
	}

	public function testConvertCyrillicToLatin(): void
	{
		$this->assertSame('iphone', KeyboardLayout::convert('шзрщту'));
	}

	public function testConvertKeepsCase(): void
	{
		$this->assertSame('Привет', KeyboardLayout::convert('Ghbdtn'));
		$this->assertSame('Iphone', KeyboardLayout::convert('Шзрщту'));
	}

	public function testConvertKeepsDigitsAndSpaces(): void
	{
		$this->assertSame('авто 123', KeyboardLayout::convert('fdnj 123'));
	}

	public function testConvertMixedScriptReturnsUnchanged(): void
	{
		$this->assertSame('ghbdtn привет', KeyboardLayout::convert('ghbdtn привет'));
	}

	public function testConvertEmptyString(): void
	{
		$this->assertSame('', KeyboardLayout::convert(''));
	}

	public function testConvertNoLettersReturnsUnchanged(): void
	{
		$this->assertSame('12345', KeyboardLayout::convert('12345'));
	}

	public function testConvertPunctuation(): void
	{
		$this->assertSame(',.,', KeyboardLayout::convert('бю,'));
		$this->assertSame(',.,', KeyboardLayout::convert(',.,'));
	}

	public function testConvertDirectMethods(): void
	{
		$this->assertSame('привет', KeyboardLayout::convertToCyrillic('ghbdtn'));
		$this->assertSame('iphone', KeyboardLayout::convertToLatin('шзрщту'));
	}

	public function testConvertSpecialCharacters(): void
	{
		$this->assertSame('ёлка', KeyboardLayout::convert('`krf'));
		$this->assertSame('`krf', KeyboardLayout::convertToLatin('ёлка'));
	}
}
