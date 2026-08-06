<?php

declare(strict_types=1);

namespace Tests\Unit\Library;

use InvoiceQrCode;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../upload/system/library/invoice_qr_code.php';

class InvoiceQrCodeTest extends TestCase
{
	public function testEmptyUrlReturnsNoQrCode(): void
	{
		$this->assertSame('', (new InvoiceQrCode())->generate(''));
	}

	public function testUrlReturnsSvgDataUri(): void
	{
		$uri = (new InvoiceQrCode())->generate('https://example.test/index.php?route=account/order/public_invoice&token=' . str_repeat('a', 64));

		$this->assertStringStartsWith('data:image/svg+xml;base64,', $uri);
		$this->assertStringContainsString('<svg', (string)base64_decode(substr($uri, strpos($uri, ',') + 1), true));
	}

	public function testDifferentUrlsProduceDifferentQrCodes(): void
	{
		$generator = new InvoiceQrCode();

		$this->assertNotSame(
			$generator->generate('https://example.test/invoice/a'),
			$generator->generate('https://example.test/invoice/b')
		);
	}
}
