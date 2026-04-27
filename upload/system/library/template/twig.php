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
            } elseif (defined('DIR_CATALOG') && is_file(DIR_MODIFICATION . 'admin/view/template/' . $filename . '.twig')) {	
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
				throw new \Exception('Error: Could not load template ' . $file . '! Available files in "' . $dir . '": ' . implode(', ', $files));
				// exit not reached, exception will be handled by outer try/catch
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

			return $twig->render($filename . '.twig', $this->data);
		} catch (\Twig\Error\SyntaxError $e) {
			// Syntax errors in template - most common issue
			$msg = $e->getMessage();
			trigger_error('Twig SyntaxError in template "' . $filename . '.twig": ' . $msg . "\n\nFull trace:\n" . $e->getTraceAsString());
			exit();
		} catch (\Twig\Error\LoaderError $e) {
			// Template loading issues
			trigger_error('Twig LoaderError in template "' . $filename . '.twig": ' . $e->getMessage() . "\n\nFull trace:\n" . $e->getTraceAsString());
			exit();
		} catch (\Twig\Error\RuntimeError $e) {
			// Runtime errors during rendering
			trigger_error('Twig RuntimeError in template "' . $filename . '.twig": ' . $e->getMessage() . "\n\nFull trace:\n" . $e->getTraceAsString());
			exit();
		} catch (\Exception $e) {
			// Generic exception
			trigger_error('Error: Could not load template ' . $filename . '! ' . $e->getMessage() . "\n\nFull trace:\n" . $e->getTraceAsString());
			exit();
		}	
	}	
}
