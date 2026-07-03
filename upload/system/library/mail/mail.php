<?php
namespace Mail;
class Mail extends \stdClass {
	public function send() {
		throw new \Exception('PHP mail() is disabled. Use SMTP.');
	}
}
