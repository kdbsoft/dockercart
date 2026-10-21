<?php
/**
 * DockerCart admin REST API — language code <-> id mapping.
 *
 * API payloads describe multilingual fields by language *code* ("en-gb"), while
 * every admin model expects a numeric language_id. This class is the single
 * place where the two are translated, and it validates that a code exists and
 * is enabled before anything is written.
 *
 * @package    DockerCart
 * @subpackage Api
 */

declare(strict_types=1);

namespace Dockercart\Api;

final class LanguageMap {
	private array $languages = [];
	private int $default_id = 0;

	public function __construct(\Registry $registry) {
		$db = $registry->get('db');

		$query = $db->query("SELECT `language_id`, `name`, `code`, `locale`, `status`, `sort_order` FROM `" . DB_PREFIX . "language` WHERE `status` = '1' ORDER BY `sort_order`, `name`");

		foreach ($query->rows as $row) {
			$this->languages[(int)$row['language_id']] = [
				'language_id' => (int)$row['language_id'],
				'code'        => (string)$row['code'],
				'name'        => (string)$row['name'],
				'locale'      => (string)$row['locale'],
				'sort_order'  => (int)$row['sort_order'],
			];
		}

		$this->default_id = (int)$registry->get('config')->get('config_language_id');

		if (!isset($this->languages[$this->default_id]) && $this->languages) {
			$this->default_id = (int)array_key_first($this->languages);
		}
	}

	/**
	 * @return array<int, string> language_id => code
	 */
	public function codes(): array {
		$codes = [];

		foreach ($this->languages as $language_id => $language) {
			$codes[$language_id] = $language['code'];
		}

		return $codes;
	}

	/**
	 * @return array<string, array> language code => language row
	 */
	public function byCode(): array {
		$by_code = [];

		foreach ($this->languages as $language) {
			$by_code[$language['code']] = $language;
		}

		return $by_code;
	}

	public function all(): array {
		return array_values($this->languages);
	}

	public function code(int $language_id): string {
		return $this->languages[$language_id]['code'] ?? '';
	}

	public function id(string $code): int {
		foreach ($this->languages as $language_id => $language) {
			if ($language['code'] === $code) {
				return $language_id;
			}
		}

		return 0;
	}

	public function defaultId(): int {
		return $this->default_id;
	}

	public function defaultCode(): string {
		return $this->code($this->default_id);
	}

	public function exists(string $code): bool {
		return $this->id($code) > 0;
	}

	/**
	 * Translates a language code into an id, failing with 422 when unknown.
	 */
	public function requireId(string $code): int {
		$language_id = $this->id($code);

		if (!$language_id) {
			throw new HttpException(Error::VALIDATION_FAILED, [
				'language' => ['unknown language code "' . $code . '"'],
			]);
		}

		return $language_id;
	}
}
