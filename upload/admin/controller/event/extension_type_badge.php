<?php

declare(strict_types=1);

class ControllerEventExtensionTypeBadge extends Controller {
	/**
	 * Injects a localized extension-type badge (Module, Feed, Payment, ...) into
	 * the page header title of every extension edit view.
	 *
	 * Registered for extension views via action_event in admin config,
	 * so no per-template or per-controller edits are needed.
	 *
	 * @param string $route  view route, e.g. `extension/module/banner`
	 * @param array  $data   view data (unused, kept for event signature)
	 * @param string $output rendered HTML, modified in place
	 */
	public function index(&$route, &$data, &$output) {
		$parts = explode('/', (string)$route);

		if (count($parts) < 3 || $parts[0] !== 'extension') {
			return null;
		}

		$type = (string)$parts[1];

		// Marketplace listings (extension/extension/*) already show the type via tabs.
		if ($type === '' || $type === 'extension') {
			return null;
		}

		if (!is_string($output) || $output === '') {
			return null;
		}

		// Idempotency: do not inject twice.
		if (strpos($output, 'data-extension-type=') !== false) {
			return null;
		}

		$this->load->language('extension/extension_type');

		$key = 'text_type_' . $type;
		$label = $this->language->get($key);

		if ($label === $key || $label === '') {
			$label = ucfirst($type);
		}

		$this->injectTypeBadge($output, $type, (string)$label);

		return null;
	}

	private function injectTypeBadge(string &$output, string $type, string $label): void {
		$title_class_pos = strpos($output, 'page-header__title');

		if ($title_class_pos === false) {
			return;
		}

		$tag_start = strrpos(substr($output, 0, $title_class_pos), '<');

		if ($tag_start === false) {
			return;
		}

		$tag = 'span';

		if (substr($output, $tag_start, 3) === '<h1') {
			$tag = 'h1';
		}

		$open_end = strpos($output, '>', $title_class_pos);

		if ($open_end === false) {
			return;
		}

		$close_pos = $this->findMatchingClose($output, $tag, $open_end + 1);

		if ($close_pos === null) {
			return;
		}

		$type_html = '<span class="page-header__type" data-extension-type="'
			. htmlspecialchars($type, ENT_QUOTES, 'UTF-8') . '">· '
			. htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';

		// Place the type right after the heading text, before the status badge (if any).
		$status_pos = strpos($output, 'page-header__badge', $open_end);

		if ($status_pos !== false && $status_pos < $close_pos) {
			$insert_pos = strrpos(substr($output, 0, $status_pos), '<');

			if ($insert_pos === false) {
				return;
			}

			$output = substr($output, 0, $insert_pos) . $type_html . ' ' . substr($output, $insert_pos);

			return;
		}

		$output = substr($output, 0, $close_pos) . ' ' . $type_html . substr($output, $close_pos);
	}

	/**
	 * Finds the position of the closing tag matching the opening tag that ends
	 * before $from, counting nested tags of the same name (the title may contain
	 * a nested status badge <span>).
	 */
	private function findMatchingClose(string $output, string $tag, int $from): ?int {
		$open_tag = '<' . $tag;
		$close_tag = '</' . $tag . '>';
		$open_len = strlen($open_tag);
		$depth = 1;
		$pos = $from;

		while ($depth > 0) {
			$next_open = $this->findOpenTag($output, $open_tag, $open_len, $pos);
			$next_close = strpos($output, $close_tag, $pos);

			if ($next_close === false) {
				return null;
			}

			if ($next_open !== null && $next_open < $next_close) {
				$depth++;
				$pos = $next_open + 1;
			} else {
				$depth--;

				if ($depth === 0) {
					return $next_close;
				}

				$pos = $next_close + 1;
			}
		}

		return null;
	}

	/**
	 * Finds the next opening tag, skipping closing tags (</span>) which also
	 * start with the same prefix.
	 */
	private function findOpenTag(string $output, string $open_tag, int $open_len, int $from): ?int {
		$pos = strpos($output, $open_tag, $from);

		while ($pos !== false) {
			// Skip </tag> — it is a closing tag, not an opening one.
			if (substr($output, $pos, 2) !== '</') {
				$after = $output[$pos + $open_len] ?? '';

				// Valid opening tag continuations: space, >, /, newline, tab.
				if ($after === '' || $after === ' ' || $after === '>' || $after === '/' || $after === "\n" || $after === "\t" || $after === "\r") {
					return $pos;
				}
			}

			$pos = strpos($output, $open_tag, $pos + 1);
		}

		return null;
	}
}
