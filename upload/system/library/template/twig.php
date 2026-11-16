<?php
namespace Template;

final class Twig {
	private $data = array();

	public function set($key, $value) {
		$this->data[$key] = $value;
	}

	public function render($filename, $code = '') {
		if (!$code) {
			$file = DIR_TEMPLATE . $filename . '.twig';

			if (defined('DIR_CATALOG') && is_file(DIR_MODIFICATION . 'admin/view/template/' . $filename . '.twig')) {
				$code = file_get_contents(DIR_MODIFICATION . 'admin/view/template/' . $filename . '.twig');
			} elseif (is_file(DIR_MODIFICATION . 'catalog/view/theme/' . $filename . '.twig')) {
				$code = file_get_contents(DIR_MODIFICATION . 'catalog/view/theme/' . $filename . '.twig');
			} elseif (is_file($file)) {
				$code = file_get_contents($file);
			} else {
				$dir = dirname($file);
				$files = array();

				if (is_dir($dir)) {
					$files = array_slice(scandir($dir), 0, 50);
				}

				$message = 'Error: Could not load template ' . $file . '! Available files in "' . $dir . '": ' . implode(', ', $files);

				// keep throwing (the caller renders the message) but leave a trace on disk
				$this->log($message);

				throw new \Exception($message);
			}
		}

		// initialize Twig environment
		$config = array(
			'autoescape'  => false,
			'debug'       => true,
			'auto_reload' => true,
			'cache'       => DIR_CACHE . 'template/'
		);

		try {
			$array_loader = new \Twig\Loader\ArrayLoader(array($filename . '.twig' => $code));
			$filesystem_loader = new \Twig\Loader\FilesystemLoader(DIR_TEMPLATE);
			$loader = new \Twig\Loader\ChainLoader(array($array_loader, $filesystem_loader));

			$twig = new \Twig\Environment($loader, $config);

			// enable debug extension when available
			if (class_exists('\\Twig\\Extension\\DebugExtension')) {
				$twig->addExtension(new \Twig\Extension\DebugExtension());
			}

			// review count with language-aware declension: "1 отзыв, 2 отзыва, 5 отзывов"
			$twig->addFunction(new \Twig\TwigFunction('review_count_label', function ($count) {
				require_once(DIR_SYSTEM . 'helper/plural.php');

				return review_count_label((int)$count);
			}));

			return $twig->render($filename . '.twig', $this->data);
		} catch (\Twig\Error\SyntaxError $e) {
			// Syntax errors in template - most common issue
			$this->fail('Twig SyntaxError in template "' . $filename . '.twig": ' . $e->getMessage(), $e);
		} catch (\Twig\Error\LoaderError $e) {
			// Template loading issues (e.g. a missing {% include %} target)
			$this->fail('Twig LoaderError in template "' . $filename . '.twig": ' . $e->getMessage(), $e);
		} catch (\Twig\Error\RuntimeError $e) {
			// Runtime errors during rendering
			$this->fail('Twig RuntimeError in template "' . $filename . '.twig": ' . $e->getMessage(), $e);
		} catch (\Exception $e) {
			// Generic exception
			$this->fail('Error: Could not load template ' . $filename . '! ' . $e->getMessage(), $e);
		}
	}

	/**
	 * Append a render failure to storage/logs/error.log.
	 *
	 * The response is already streaming by the time a template is rendered, so
	 * there is nothing left to recover and the operator gets a blank page. With
	 * `display_errors` off (docker/php.prod.ini) `trigger_error()` alone leaves no
	 * trail: error_log points at stderr, which never reaches storage/logs. Write
	 * the message where `Log` puts its entries instead.
	 *
	 * Must never throw: a logging problem may not mask the template error.
	 */
	private function log($message) {
		if (!defined('DIR_LOGS') || !is_dir(DIR_LOGS) || !is_writable(DIR_LOGS)) {
			return;
		}

		$suffix = defined('DIR_APPLICATION') ? ' [' . DIR_APPLICATION . ']' : '';

		@file_put_contents(DIR_LOGS . 'error.log', date('Y-m-d G:i:s') . ' - ' . $message . $suffix . "\n", FILE_APPEND | LOCK_EX);
	}

	/**
	 * Report a fatal render failure before terminating the request.
	 *
	 * A non-zero exit turns the otherwise mute "0 bytes, HTTP 200" page into a
	 * real 500 whenever nothing has been flushed yet, so monitoring notices it.
	 */
	private function fail($message, \Exception $e) {
		$message .= "\n\nFull trace:\n" . $e->getTraceAsString();

		$this->log($message);

		trigger_error($message);
		exit(1);
	}
}
