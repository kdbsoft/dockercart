<?php

declare(strict_types=1);

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\SvgWriter;

class InvoiceQrCode {
	public function generate(string $url): string {
		if ($url === '') {
			return '';
		}

		$result = (new Builder(
			writer: new SvgWriter(),
			writerOptions: [
				SvgWriter::WRITER_OPTION_EXCLUDE_XML_DECLARATION => true,
			],
			data: $url,
			errorCorrectionLevel: ErrorCorrectionLevel::High,
			size: 420,
			margin: 20,
		))->build();

		return $result->getDataUri();
	}
}
