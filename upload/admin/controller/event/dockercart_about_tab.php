<?php

declare(strict_types=1);

class ControllerEventDockercartAboutTab extends Controller {

	public function index(&$route, &$data, &$output) {
		$module_code = $this->extractModuleCode($route);

		if (!$module_code) {
			return null;
		}

		$meta = $this->getMeta($module_code);

		if (!$meta) {
			return null;
		}

		$license = $this->getLicense($module_code);

		$this->load->language('extension/dockercart_about');

		$tab_nav = $this->buildTabNav($output);
		$tab_pane = $this->buildTabPane($meta, $license, $module_code);

		$this->injectTabNav($output, $tab_nav);
		$this->injectTabPane($output, $tab_pane);
		$this->injectScript($output, $meta, $module_code);

		return null;
	}

	private function extractModuleCode(string $route): ?string {
		if (preg_match('/^extension\/(?:module|feed|payment|shipping|total)\/[^\/]+\/([a-z0-9_]+)/', $route, $matches)) {
			return $matches[1];
		}

		if (preg_match('/^extension\/(?:module|feed|payment|shipping|total)\/([a-z0-9_]+)/', $route, $matches)) {
			return $matches[1];
		}

		return null;
	}

	private function getMeta(string $module_code): ?array {
		$query = $this->db->query(
			"SELECT * FROM `" . DB_PREFIX . "dockercart_extension_meta`
			 WHERE `code` = '" . $this->db->escape($module_code) . "'"
		);

		return $query->num_rows ? $query->row : null;
	}

	private function getLicense(string $module_code): ?array {
		if (!is_file(DIR_SYSTEM . 'library/dockercart/licensing.php')) {
			return null;
		}

		require_once DIR_SYSTEM . 'library/dockercart/licensing.php';

		$licensing = new DockercartLicensing($this->registry);

		return $licensing->getLicense($module_code) ?: null;
	}

	private function buildTabNav(string $output): string {
		$is_dcx = (strpos($output, 'dcx-profile-tabs') !== false || strpos($output, 'dcx-shell') !== false);

		$tab_label = htmlspecialchars((string)$this->language->get('tab_about'), ENT_QUOTES, 'UTF-8');
		$subtitle = htmlspecialchars((string)$this->language->get('tab_about_subtitle'), ENT_QUOTES, 'UTF-8');

		if ($is_dcx) {
			return sprintf(
				'<li><a href="#dc-about-pane" class="js-dc-about-tab" data-tab="dc-about-pane" data-title="%s" data-subtitle="%s" data-icon="fa-info-circle"><i class="fa fa-info-circle"></i> %s</a></li>',
				$tab_label,
				$subtitle,
				$tab_label
			);
		}

		return '<li><a href="#dc-about-pane" data-toggle="tab"><i class="fa fa-info-circle"></i> ' . $tab_label . '</a></li>';
	}

	private function buildTabPane(array $meta, ?array $license, string $module_code): string {
		$html = '<div id="dc-about-pane" class="dcx-tab-pane tab-pane">';

		$html .= $this->buildMetaSection($meta);
		$html .= $this->buildLicenseSection($license);

		$html .= '</div>';

		return $html;
	}

	private function buildMetaSection(array $meta): string {
		$tab_label = (string)$this->language->get('tab_about');
		$text_developer = (string)$this->language->get('text_developer');
		$text_contact = (string)$this->language->get('text_contact');
		$text_version = (string)$this->language->get('text_version');
		$text_license_type = (string)$this->language->get('text_license_type');

		$name = htmlspecialchars((string)($meta['name'] ?: $meta['code']), ENT_QUOTES, 'UTF-8');
		$author = htmlspecialchars((string)($meta['author'] ?: 'DockerCart'), ENT_QUOTES, 'UTF-8');
		$author_email = htmlspecialchars((string)($meta['author_email'] ?: 'support@dockercart.net'), ENT_QUOTES, 'UTF-8');
		$version = htmlspecialchars((string)($meta['installed_version'] ?: '—'), ENT_QUOTES, 'UTF-8');
		$license_type = htmlspecialchars((string)($meta['license_type'] ?: '—'), ENT_QUOTES, 'UTF-8');

		$html = '<div class="dcx-section" style="margin-top:0;padding-top:0;border-top:0;">';
		$html .= '<h4>' . htmlspecialchars($tab_label, ENT_QUOTES, 'UTF-8') . '</h4>';
		$html .= '<table class="table table-bordered dcx-table">';
		$html .= '<tr><td style="width:200px;"><strong>' . $text_developer . '</strong></td><td>' . $author . '</td></tr>';
		$html .= '<tr><td><strong>' . $text_contact . '</strong></td><td><a href="mailto:' . $author_email . '">' . $author_email . '</a></td></tr>';
		$html .= '<tr><td><strong>' . $text_version . '</strong></td><td>' . $version . '</td></tr>';
		$html .= '<tr><td><strong>' . $text_license_type . '</strong></td><td>' . $license_type . '</td></tr>';
		$html .= '</table>';
		$html .= '</div>';

		return $html;
	}

	private function buildLicenseSection(?array $license): string {
		$html = '<div class="dcx-section">';
		$html .= '<h4>' . htmlspecialchars((string)$this->language->get('text_license_status'), ENT_QUOTES, 'UTF-8') . '</h4>';

		if ($license && !empty($license['license_key'])) {
			$html .= $this->buildActiveLicenseTable($license);
		} else {
			$text_no_license = htmlspecialchars((string)$this->language->get('text_license_no_license'), ENT_QUOTES, 'UTF-8');
			$text_set_key = htmlspecialchars((string)$this->language->get('text_license_set_key'), ENT_QUOTES, 'UTF-8');
			$html .= '<p class="text-muted" style="padding: 0 0 12px 0;">' . $text_no_license . '</p>';
			$html .= '<button type="button" class="btn btn-success" onclick="$(\'#dc-license-modal\').modal(\'show\');$(\'#dc-about-key\').val(\'\');">' . $text_set_key . '</button>';
		}

		$html .= $this->buildLicenseModal();
		$html .= '</div>';

		return $html;
	}

	private function buildActiveLicenseTable(array $license): string {
		$text_key = (string)$this->language->get('text_license_key_label');
		$text_domain = (string)$this->language->get('text_license_domain');
		$text_expires = (string)$this->language->get('text_license_expires');
		$text_last = (string)$this->language->get('text_license_last_verified');

		$key_masked = htmlspecialchars((string)$license['license_key'], ENT_QUOTES, 'UTF-8');
		if (mb_strlen($key_masked) > 30) {
			$key_masked = mb_substr($key_masked, 0, 30) . '...';
		}

		$status_human = $this->getStatusLabel($license['status'] ?? 'unknown');
		$status_class = $this->getStatusClass($license['status'] ?? 'unknown');

		$is_test = !empty($license['is_test']) ? ' <small class="text-muted">(test)</small>' : '';
		$domain = htmlspecialchars((string)($license['domain'] ?: '—'), ENT_QUOTES, 'UTF-8');
		$expires = !empty($license['expires_at']) ? htmlspecialchars((string)$license['expires_at'], ENT_QUOTES, 'UTF-8') : 'Lifetime';
		$last_verified = !empty($license['last_verified']) ? htmlspecialchars((string)$license['last_verified'], ENT_QUOTES, 'UTF-8') : '&mdash;';

		$full_key_attr = htmlspecialchars((string)$license['license_key'], ENT_QUOTES, 'UTF-8');

		$html = '<table class="table table-bordered dcx-table">';
		$html .= '<tr><td style="width:200px;"><strong>' . $text_key . '</strong></td><td><code class="dc-license-key" style="cursor:pointer;" data-full-key="' . $full_key_attr . '">' . $key_masked . '</code> <i class="fa fa-pencil dc-license-edit" style="cursor:pointer;margin-left:4px;" data-full-key="' . $full_key_attr . '"></i></td></tr>';
		$html .= '<tr><td><strong>' . htmlspecialchars((string)$this->language->get('text_status'), ENT_QUOTES, 'UTF-8') . '</strong></td><td><span class="label label-' . $status_class . '">' . $status_human . '</span>' . $is_test . '</td></tr>';
		$html .= '<tr><td><strong>' . $text_domain . '</strong></td><td>' . $domain . '</td></tr>';
		$html .= '<tr><td><strong>' . $text_expires . '</strong></td><td>' . $expires . '</td></tr>';
		$html .= '<tr><td><strong>' . $text_last . '</strong></td><td>' . $last_verified . '</td></tr>';
		$html .= '</table>';

		return $html;
	}

	private function buildLicenseModal(): string {
		$label_key = htmlspecialchars((string)$this->language->get('text_license_key_label'), ENT_QUOTES, 'UTF-8');
		$label_activate = htmlspecialchars((string)$this->language->get('text_license_activate'), ENT_QUOTES, 'UTF-8');
		$modal_title = htmlspecialchars((string)$this->language->get('text_license_change_key_modal'), ENT_QUOTES, 'UTF-8');

		$html = '<div id="dc-license-modal" class="modal fade" tabindex="-1">';
		$html .= '<div class="modal-dialog">';
		$html .= '<div class="modal-content">';
		$html .= '<div class="modal-header">';
		$html .= '<button type="button" class="close" data-dismiss="modal">&times;</button>';
		$html .= '<h4 class="modal-title">' . $modal_title . '</h4>';
		$html .= '</div>';
		$html .= '<div class="modal-body">';
		$html .= '<div class="form-group">';
		$html .= '<label class="control-label">' . $label_key . '</label>';
		$html .= '<input type="text" id="dc-about-key" class="form-control" placeholder="DCL-..." />';
		$html .= '</div>';
		$html .= '</div>';
		$html .= '<div class="modal-footer">';
		$html .= '<button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>';
		$html .= '<button type="button" id="dc-about-activate" class="btn btn-primary">' . $label_activate . '</button>';
		$html .= '</div>';
		$html .= '</div>';
		$html .= '</div>';
		$html .= '</div>';

		return $html;
	}

	private function getStatusLabel(string $status): string {
		$key = 'text_license_status_' . $status;
		$label = $this->language->get($key);

		return $label !== $key ? (string)$label : ucfirst($status);
	}

	private function getStatusClass(string $status): string {
		switch ($status) {
			case 'active':
				return 'success';
			case 'grace':
				return 'warning';
			case 'revoked':
			case 'expired':
			case 'invalid':
				return 'danger';
			default:
				return 'default';
		}
	}

	private function injectTabNav(string &$output, string $tab_nav): void {
		$output = preg_replace(
			'/(<ul[^>]*class="[^"]*(?:dcx-profile-tabs|nav-tabs)[^"]*"[^>]*>)(.*?)(<\/ul>)/s',
			'$1$2' . $tab_nav . '$3',
			$output,
			1
		);
	}

	private function injectTabPane(string &$output, string $tab_pane): void {
		$form_pos = strpos($output, '</form>');

		if ($form_pos !== false) {
			$output = substr($output, 0, $form_pos) . $tab_pane . "\n" . substr($output, $form_pos);

			return;
		}

		if (($pos = strrpos($output, '</div>')) !== false) {
			$output = substr($output, 0, $pos) . $tab_pane . "\n" . substr($output, $pos);
		}
	}

	private function injectScript(string &$output, array $meta, string $module_code): void {
		$activate_url = $this->url->link('extension/store/activateKey', 'user_token=' . $this->session->data['user_token'], true);
		$sku = htmlspecialchars((string)($meta['sku'] ?: $module_code), ENT_QUOTES, 'UTF-8');

		$copied_text = htmlspecialchars((string)$this->language->get('text_license_copied'), ENT_QUOTES, 'UTF-8');

		$script = <<<JS
<script type="text/javascript"><!--
\$(document).on('click', '.js-dc-about-tab', function(e) {
	e.preventDefault();
	var \$t = \$(this);
	var tabId = \$t.data('tab') || 'dc-about-pane';
	var \$li = \$t.closest('li');
	var \$ul = \$li.closest('ul');

	\$ul.find('li').removeClass('active');
	\$li.addClass('active');

	\$('.dcx-tab-pane, .tab-pane').removeClass('active');
	\$('#' + tabId).addClass('active');

	var \$head = \$t.closest('.container-fluid').find('.dcx-panel-head');
	if (\$head.length) {
		\$head.find('.dcx-panel-title').text(\$t.data('title') || 'About');
		\$head.find('.dcx-panel-subtitle').text(\$t.data('subtitle') || '');
		var icon = String(\$t.data('icon') || 'fa-info-circle').replace(/[^a-z0-9\\-]/gi, '');
		\$head.find('.dcx-panel-head__icon i').attr('class', 'fa ' + icon);
	}
});

\$(document).on('click', '.dc-license-key', function() {
	var key = \$(this).data('full-key');
	if (!key) return;
	if (navigator.clipboard && navigator.clipboard.writeText) {
		navigator.clipboard.writeText(key).then(function() {
			var \$el = \$(this);
			var orig = \$el.text();
			\$el.text('{$copied_text}');
			setTimeout(function() { \$el.text(orig); }, 2000);
		}.bind(this));
	} else {
		var \$input = \$('<input>');
		\$('body').append(\$input);
		\$input.val(key).select();
		document.execCommand('copy');
		\$input.remove();
		var \$el = \$(this);
		var orig = \$el.text();
		\$el.text('{$copied_text}');
		setTimeout(function() { \$el.text(orig); }, 2000);
	}
});

\$(document).on('click', '.dc-license-edit', function() {
	\$('#dc-about-key').val(\$(this).data('full-key') || '');
	\$('#dc-license-modal').modal('show');
});

\$('#dc-license-modal').on('shown.bs.modal', function() {
	\$('#dc-about-key').focus();
});

\$('#dc-about-activate').on('click', function() {
	var key = \$('#dc-about-key').val().trim();
	if (!key) {
		alert('License key required');
		return;
	}
	var \$btn = \$(this).prop('disabled', true);
	\$.post('{$activate_url}', {sku: '{$sku}', license_key: key}, function(resp) {
		\$btn.prop('disabled', false);
		if (resp.success) {
			\$('#dc-license-modal').modal('hide');
			location.reload();
		} else {
			alert(resp.error || resp.message || 'Done');
		}
	}, 'json').fail(function() {
		\$btn.prop('disabled', false);
		alert('Failed to activate');
	});
});
//--></script>
JS;

		$pos = strrpos($output, '</body>');

		if ($pos !== false) {
			$output = substr($output, 0, $pos) . $script . "\n" . substr($output, $pos);
		} else {
			$output .= $script;
		}
	}
}
