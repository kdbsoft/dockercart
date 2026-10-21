<?php
/**
 * DockerCart admin REST API — request payload validator.
 *
 * Small, explicit rule engine: the API validates the REST payload shape (types,
 * bounds, presence) and then hands the normalized model-shaped array to the
 * admin models, which remain the source of truth for business rules.
 * Field rules that are shared with the product form live in
 * DockercartProductRules (see upload/system/library/dockercart/product_rules.php).
 *
 * Rule syntax: array of strings (the field name is the array key), e.g.
 *
 *   $this->validate($body, [
 *       'model'    => ['required', 'string', 'max:64', 'no_html'],
 *       'price'    => ['float', 'min:0'],
 *       'status'   => ['int', 'in:0,1'],
 *       'category' => ['array'],
 *   ]);
 *
 * Unknown input keys are ignored (forward compatible); failed rules are
 * collected per field and reported as 422 validation_failed.
 *
 * @package    DockerCart
 * @subpackage Api
 */

declare(strict_types=1);

namespace Dockercart\Api;

final class Validator {
	private array $errors = [];

	/**
	 * @param array $data  raw input
	 * @param array $rules field => rules[]
	 * @return array normalized values (only keys declared in $rules, in rule order)
	 */
	public function validate(array $data, array $rules): array {
		$this->errors = [];

		$output = [];

		foreach ($rules as $field => $field_rules) {
			if (!array_key_exists($field, $data)) {
				if (in_array('required', $field_rules, true)) {
					$this->addError($field, 'required');
				}

				continue;
			}

			$value = $data[$field];

			if ($value === null) {
				if (in_array('nullable', $field_rules, true) || !in_array('required', $field_rules, true)) {
					$output[$field] = null;

					continue;
				}

				$this->addError($field, 'required');

				continue;
			}

			$normalized = $this->applyRules($field, $value, $field_rules, $failed);

			if ($failed) {
				continue;
			}

			$output[$field] = $normalized;
		}

		if ($this->errors) {
			throw new HttpException(Error::VALIDATION_FAILED, $this->errors);
		}

		return $output;
	}

	/**
	 * Adds an error manually (used for cross-field checks).
	 */
	public function fail(string $field, string $rule): void {
		$this->addError($field, $rule);
	}

	/**
	 * @return array field => list of failed rules/messages
	 */
	public function errors(): array {
		return $this->errors;
	}

	private function applyRules(string $field, $value, array $rules, ?bool &$failed = null) {
		$failed = false;

		$type = '';

		foreach ($rules as $rule) {
			if (strpos($rule, ':') !== false) {
				[$name, $argument] = explode(':', $rule, 2);
			} else {
				$name = $rule;
				$argument = '';
			}

			switch ($name) {
				case 'required':
				case 'nullable':
					break;

				case 'string':
					$type = 'string';

					if (!is_scalar($value) && !is_null($value)) {
						$failed = true;
						$this->addError($field, 'string');

						return null;
					}

					$value = (string)$value;
					break;

				case 'int':
				case 'integer':
					$type = 'int';

					if (is_bool($value) || !is_numeric($value) || (float)$value !== (float)(int)$value) {
						$failed = true;
						$this->addError($field, 'integer');

						return null;
					}

					$value = (int)$value;
					break;

				case 'float':
				case 'number':
					$type = 'float';

					if (is_bool($value) || !is_numeric($value)) {
						$failed = true;
						$this->addError($field, 'number');

						return null;
					}

					$value = (float)$value;
					break;

				case 'bool':
				case 'boolean':
					$type = 'bool';
					$value = $this->toBool($value);

					if ($value === null) {
						$failed = true;
						$this->addError($field, 'boolean');

						return null;
					}
					break;

				case 'array':
				case 'list':
					$type = 'array';

					if (!is_array($value)) {
						$failed = true;
						$this->addError($field, 'array');

						return null;
					}
					break;

				case 'email':
					if (!is_scalar($value) || !filter_var((string)$value, FILTER_VALIDATE_EMAIL)) {
						$failed = true;
						$this->addError($field, 'email');

						return null;
					}

					$value = (string)$value;
					break;

				case 'min':
					if ($type === 'int' || $type === 'float') {
						if ((float)$value < (float)$argument) {
							$failed = true;
							$this->addError($field, 'min:' . $argument);
						}
					} elseif ($this->length((string)$value) < (int)$argument) {
						$failed = true;
						$this->addError($field, 'min:' . $argument);
					}
					break;

				case 'max':
					if ($type === 'int' || $type === 'float') {
						if ((float)$value > (float)$argument) {
							$failed = true;
							$this->addError($field, 'max:' . $argument);
						}
					} elseif ($this->length((string)$value) > (int)$argument) {
						$failed = true;
						$this->addError($field, 'max:' . $argument);
					}
					break;

				case 'in':
					$allowed = explode(',', $argument);

					if (!in_array((string)$value, $allowed, true)) {
						$failed = true;
						$this->addError($field, 'in:' . $argument);
					}
					break;

				case 'date':
					$normalized = $this->normalizeDate($value);

					if ($normalized === null) {
						$failed = true;
						$this->addError($field, 'date');
					} else {
						$value = $normalized;
					}
					break;

				case 'datetime':
					$normalized = $this->normalizeDateTime($value);

					if ($normalized === null) {
						$failed = true;
						$this->addError($field, 'datetime');
					} else {
						$value = $normalized;
					}
					break;

				case 'no_html':
					if (is_scalar($value) && (strpos((string)$value, '<') !== false || strpos((string)$value, '>') !== false)) {
						$failed = true;
						$this->addError($field, 'no_html');
					}
					break;

				case 'regex':
					if (!is_scalar($value) || !preg_match($argument, (string)$value)) {
						$failed = true;
						$this->addError($field, 'format');
					}
					break;

				default:
					// Unknown rule: never silently ignore a typo in a rule name.
					throw new \LogicException('Unknown validation rule "' . $rule . '" for field "' . $field . '"');
			}

			if ($failed) {
				return null;
			}
		}

		return $value;
	}

	private function addError(string $field, string $rule): void {
		$this->errors[$field][] = $rule;
	}

	private function length(string $value): int {
		return function_exists('utf8_strlen') ? utf8_strlen($value) : mb_strlen($value, 'UTF-8');
	}

	/**
	 * @return bool|null null when the value cannot be interpreted as a boolean
	 */
	private function toBool($value): ?bool {
		if (is_bool($value)) {
			return $value;
		}

		if (in_array($value, [1, '1', 'true', 'yes', 'on'], true)) {
			return true;
		}

		if (in_array($value, [0, '0', 'false', 'no', 'off'], true)) {
			return false;
		}

		return null;
	}

	private function normalizeDate($value): ?string {
		if (!is_scalar($value) || trim((string)$value) === '') {
			return null;
		}

		$value = trim((string)$value);

		if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
			return $value;
		}

		$timestamp = strtotime($value);

		return $timestamp === false ? null : date('Y-m-d', $timestamp);
	}

	private function normalizeDateTime($value): ?string {
		if (!is_scalar($value) || trim((string)$value) === '') {
			return null;
		}

		$value = trim((string)$value);

		if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value)) {
			return $value;
		}

		$timestamp = strtotime($value);

		return $timestamp === false ? null : date('Y-m-d H:i:s', $timestamp);
	}
}
