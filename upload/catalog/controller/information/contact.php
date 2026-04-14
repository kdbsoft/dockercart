<?php
class ControllerInformationContact extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('information/contact');

		$this->document->setTitle($this->language->get('heading_title'));

		$contact_form_status = (bool)$this->config->get('config_contact_form_status');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $contact_form_status && $this->validate()) {
			$mail = new Mail($this->config->get('config_mail_engine'));
			$mail->parameter = $this->config->get('config_mail_parameter');
			$mail->smtp_hostname = $this->config->get('config_mail_smtp_hostname');
			$mail->smtp_username = $this->config->get('config_mail_smtp_username');
			$mail->smtp_password = html_entity_decode($this->config->get('config_mail_smtp_password'), ENT_QUOTES, 'UTF-8');
			$mail->smtp_port = $this->config->get('config_mail_smtp_port');
			$mail->smtp_timeout = $this->config->get('config_mail_smtp_timeout');

			$mail->setTo($this->config->get('config_email'));
			$mail->setFrom($this->config->get('config_email'));
			$mail->setReplyTo($this->request->post['email']);
			$mail->setSender(html_entity_decode($this->request->post['name'], ENT_QUOTES, 'UTF-8'));
			$mail->setSubject(html_entity_decode(sprintf($this->language->get('email_subject'), $this->request->post['name']), ENT_QUOTES, 'UTF-8'));
			$mail->setText($this->request->post['enquiry']);
			$mail->send();

			$this->response->redirect($this->url->link('information/contact/success'));
		}

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/home')
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('information/contact')
		);

		if (isset($this->error['name'])) {
			$data['error_name'] = $this->error['name'];
		} else {
			$data['error_name'] = '';
		}

		if (isset($this->error['email'])) {
			$data['error_email'] = $this->error['email'];
		} else {
			$data['error_email'] = '';
		}

		if (isset($this->error['enquiry'])) {
			$data['error_enquiry'] = $this->error['enquiry'];
		} else {
			$data['error_enquiry'] = '';
		}

		$data['button_submit'] = $this->language->get('button_submit');

		$data['action'] = $this->url->link('information/contact', '', true);

		$this->load->model('tool/image');

		$server = $this->request->server['HTTPS'] ? HTTPS_SERVER : HTTP_SERVER;

		$data['image'] = false;

		$data['store_images'] = array();

		$config_images = $this->normalizeStoreImages($this->config->get('config_images'));

		if (!$config_images && $this->config->get('config_image')) {
			$config_images = array($this->config->get('config_image'));
		}

		foreach ($config_images as $config_image) {
			$image_path = ltrim((string)$config_image, '/');

			if (!is_file(DIR_IMAGE . $image_path)) {
				continue;
			}

			$image_size = @getimagesize(DIR_IMAGE . $image_path);
			$ratio_class = 'dc-ratio-landscape';

			if ($image_size && !empty($image_size[0]) && !empty($image_size[1])) {
				$ratio = (float)$image_size[0] / (float)$image_size[1];

				if ($ratio <= 0.9) {
					$ratio_class = 'dc-ratio-portrait';
				} elseif ($ratio >= 1.6) {
					$ratio_class = 'dc-ratio-wide';
				}
			}

			$data['store_images'][] = array(
				'preview'     => $server . 'image/' . $image_path,
				'popup'       => $server . 'image/' . $image_path,
				'ratio_class' => $ratio_class
			);
		}

		$data['store'] = $this->config->get('config_name');
		$data['address'] = nl2br($this->config->get('config_address'));
		$data['geocode'] = $this->config->get('config_geocode');
		$data['geocode_hl'] = $this->config->get('config_language');
		$data['geocode_url'] = $this->buildMapUrl($data['geocode'], $data['geocode_hl']);
		$data['telephone'] = $this->config->get('config_telephone');
		$data['fax'] = $this->config->get('config_fax');
		$data['open'] = nl2br($this->config->get('config_open'));
		$data['comment'] = $this->config->get('config_comment');

		$data['social_links'] = array();
		for ($i = 1; $i <= 10; $i++) {
			$image = (string)$this->config->get('dockercart_theme_social_' . $i . '_image');
			$link  = trim((string)$this->config->get('dockercart_theme_social_' . $i . '_link'));
			$image_path = ltrim($image, '/');

			if ($image_path !== '') {
				$data['social_links'][] = array(
					'image' => $server . 'image/' . $image_path,
					'link'  => $link,
				);
			}
		}

		$data['locations'] = array();

		$this->load->model('localisation/location');

		foreach((array)$this->config->get('config_location') as $location_id) {
			$location_info = $this->model_localisation_location->getLocation($location_id);

			if ($location_info) {
				if ($location_info['image']) {
					$image = $this->model_tool_image->resize($location_info['image'], $this->config->get('theme_' . $this->config->get('config_theme') . '_image_location_width'), $this->config->get('theme_' . $this->config->get('config_theme') . '_image_location_height'));
				} else {
					$image = false;
				}

				$data['locations'][] = array(
					'location_id' => $location_info['location_id'],
					'name'        => $location_info['name'],
					'address'     => nl2br($location_info['address']),
					'geocode'     => $location_info['geocode'],
					'geocode_url' => $this->buildMapUrl($location_info['geocode'], $data['geocode_hl']),
					'telephone'   => $location_info['telephone'],
					'fax'         => $location_info['fax'],
					'image'       => $image,
					'open'        => nl2br($location_info['open']),
					'comment'     => $location_info['comment']
				);
			}
		}

		if (isset($this->request->post['name'])) {
			$data['name'] = $this->request->post['name'];
		} else {
			$data['name'] = $this->customer->getFirstName();
		}

		if (isset($this->request->post['email'])) {
			$data['email'] = $this->request->post['email'];
		} else {
			$data['email'] = $this->customer->getEmail();
		}

		if (isset($this->request->post['enquiry'])) {
			$data['enquiry'] = $this->request->post['enquiry'];
		} else {
			$data['enquiry'] = '';
		}

		$data['contact_form_status'] = $contact_form_status;

		// Captcha
		if ($contact_form_status && $this->config->get('captcha_' . $this->config->get('config_captcha') . '_status') && in_array('contact', (array)$this->config->get('config_captcha_page'))) {
			$data['captcha'] = $this->load->controller('extension/captcha/' . $this->config->get('config_captcha'), $this->error);
		} else {
			$data['captcha'] = '';
		}

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('information/contact', $data));
	}

	protected function validate() {
		if (!empty($this->request->post['name'])) {
			if ((utf8_strlen($this->request->post['name']) < 3) || (utf8_strlen($this->request->post['name']) > 32)) {
				$this->error['name'] = $this->language->get('error_name');
			}
		} else {
			$this->error['name'] = $this->language->get('error_name');
		}

		if (!empty($this->request->post['email'])) {
			if (!filter_var($this->request->post['email'], FILTER_VALIDATE_EMAIL)) {
				$this->error['email'] = $this->language->get('error_email');
			}
		} else {
			$this->error['email'] = $this->language->get('error_email');
		}

		if (!empty($this->request->post['enquiry'])) {
			if ((utf8_strlen($this->request->post['enquiry']) < 10) || (utf8_strlen($this->request->post['enquiry']) > 3000)) {
				$this->error['enquiry'] = $this->language->get('error_enquiry');
			}
		} else {
			$this->error['enquiry'] = $this->language->get('error_enquiry');
		}

		// Captcha
		if ($this->config->get('captcha_' . $this->config->get('config_captcha') . '_status') && in_array('contact', (array)$this->config->get('config_captcha_page'))) {
			$captcha = $this->load->controller('extension/captcha/' . $this->config->get('config_captcha') . '/validate');

			if ($captcha) {
				$this->error['captcha'] = $captcha;
			}
		}

		return !$this->error;
	}

	private function normalizeStoreImages($images) {
		$result = array();

		if (!is_array($images)) {
			$images = array();
		}

		foreach ($images as $image) {
			$image = trim((string)$image);

			if ($image === '' || in_array($image, $result)) {
				continue;
			}

			$result[] = $image;

			if (count($result) >= 5) {
				break;
			}
		}

		return $result;
	}

	private function buildMapUrl($geocode, $language_code = 'en') {
		$geocode = html_entity_decode((string)$geocode, ENT_QUOTES, 'UTF-8');
		$geocode = preg_replace('/\x{00A0}/u', ' ', $geocode);
		$geocode = trim($geocode);

		if ($geocode === '') {
			return '';
		}

		$map_base = 'https://www.google.com/maps?q=';
		$map_suffix = '&hl=' . rawurlencode((string)$language_code) . '&t=m&z=15';

		if (filter_var($geocode, FILTER_VALIDATE_URL)) {
			$parts = parse_url($geocode);
			$host = isset($parts['host']) ? strtolower($parts['host']) : '';

			if ($host && $this->isGoogleMapsHost($host)) {
				return $geocode;
			}

			return $map_base . rawurlencode($geocode) . $map_suffix;
		}

		$decimal_pair = $this->parseDecimalCoordinatePair($geocode);

		if ($decimal_pair) {
			$query = $this->formatCoordinate($decimal_pair['lat']) . ',' . $this->formatCoordinate($decimal_pair['lng']);

			return $map_base . rawurlencode($query) . $map_suffix;
		}

		$dms_pair = $this->parseDmsCoordinatePair($geocode);

		if ($dms_pair) {
			$query = $this->formatCoordinate($dms_pair['lat']) . ',' . $this->formatCoordinate($dms_pair['lng']);

			return $map_base . rawurlencode($query) . $map_suffix;
		}

		return $map_base . rawurlencode($geocode) . $map_suffix;
	}

	private function isGoogleMapsHost($host) {
		$google_hosts = array(
			'maps.app.goo.gl',
			'goo.gl',
			'maps.google.com',
			'google.com',
			'www.google.com',
			'm.google.com'
		);

		if (in_array($host, $google_hosts)) {
			return true;
		}

		return (substr($host, -11) === '.google.com');
	}

	private function parseDecimalCoordinatePair($value) {
		if (preg_match('/^\s*([+-]?\d{1,2}(?:\.\d+)?)\s*[, ]\s*([+-]?\d{1,3}(?:\.\d+)?)\s*$/u', $value, $matches)) {
			$lat = (float)$matches[1];
			$lng = (float)$matches[2];

			if ($lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180) {
				return array('lat' => $lat, 'lng' => $lng);
			}
		}

		return null;
	}

	private function parseDmsCoordinatePair($value) {
		if (!preg_match('/^\s*(.+?[NSns])\s*[, ]+\s*(.+?[EWew])\s*$/u', $value, $parts)) {
			return null;
		}

		$lat = $this->parseSingleDmsCoordinate($parts[1]);
		$lng = $this->parseSingleDmsCoordinate($parts[2]);

		if ($lat === null || $lng === null) {
			return null;
		}

		if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
			return null;
		}

		return array('lat' => $lat, 'lng' => $lng);
	}

	private function parseSingleDmsCoordinate($part) {
		$normalized = html_entity_decode((string)$part, ENT_QUOTES, 'UTF-8');
		$normalized = str_replace(array('’', '′', '“', '”', '″', '`'), array("'", "'", '"', '"', '"', "'"), trim($normalized));

		if (!preg_match('/^\s*(\d{1,3})(?:\s*°\s*|\s+)(\d{1,2})\s*[\'\’\′]?\s*(\d{1,2}(?:\.\d+)?)\s*[\"\”\″]?\s*([NSEWnsew])\s*$/u', $normalized, $m)) {
			if (!preg_match('/^\s*([+-]?\d{1,3}(?:\.\d+)?)\s*([NSEWnsew])\s*$/u', $normalized, $m2)) {
				return null;
			}

			$decimal = abs((float)$m2[1]);
			$direction = strtoupper($m2[2]);

			if ($direction === 'S' || $direction === 'W') {
				$decimal *= -1;
			}

			return $decimal;
		}

		$degrees = (float)$m[1];
		$minutes = (float)$m[2];
		$seconds = (float)$m[3];
		$direction = strtoupper($m[4]);

		$decimal = $degrees + ($minutes / 60) + ($seconds / 3600);

		if ($direction === 'S' || $direction === 'W') {
			$decimal *= -1;
		}

		return $decimal;
	}

	private function formatCoordinate($value) {
		return rtrim(rtrim(number_format((float)$value, 6, '.', ''), '0'), '.');
	}

	public function success() {
		$this->load->language('information/contact');

		$this->document->setTitle($this->language->get('heading_title'));

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/home')
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('information/contact')
		);

 		$data['text_message'] = $this->language->get('text_message'); 

		$data['continue'] = '/';

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('common/success', $data));
	}
}
