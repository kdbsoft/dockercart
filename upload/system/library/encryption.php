<?php
/**
 * @package		DockerCart
 * @author		DockerCart Team
 * @license		https://opensource.org/licenses/GPL-3.0
 * @link		https://dockercart.net
 */

/**
* Encryption class
*/
final class Encryption {
	private function deriveKey($key) {
		return hash('sha256', $key, true);
	}

	public function encrypt($key, $value) {
		$iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-gcm'));
		$tag = '';
		$ciphertext = openssl_encrypt($value, 'aes-256-gcm', $this->deriveKey($key), OPENSSL_RAW_DATA, $iv, $tag);
		return strtr(base64_encode($iv . $tag . $ciphertext), '+/=', '-_,');
	}

	public function decrypt($key, $value) {
		$data = base64_decode(strtr($value, '-_,', '+/='));

		if ($data === false || strlen($data) < 28) {
			return $this->decryptLegacy($key, $value);
		}

		$ivlen = openssl_cipher_iv_length('aes-256-gcm');
		$iv = substr($data, 0, $ivlen);
		$tag = substr($data, $ivlen, 16);
		$ciphertext = substr($data, $ivlen + 16);

		$result = openssl_decrypt($ciphertext, 'aes-256-gcm', $this->deriveKey($key), OPENSSL_RAW_DATA, $iv, $tag);

		if ($result === false) {
			return $this->decryptLegacy($key, $value);
		}

		return $result;
	}

	private function decryptLegacy($key, $value) {
		return trim(openssl_decrypt(base64_decode(strtr($value, '-_,', '+/=')), 'aes-128-cbc', $this->deriveKey($key)));
	}
}
