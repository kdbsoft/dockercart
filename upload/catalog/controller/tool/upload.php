<?php
class ControllerToolUpload extends Controller {
	public function index() {
		$this->load->language('tool/upload');

		$json = array();

		if (($this->request->server['REQUEST_METHOD'] != 'POST') || !$this->validateCsrf()) {
			$json['error'] = $this->language->get('error_csrf');
		}

		if (!$json && (!empty($this->request->files['file']['name']) && is_file($this->request->files['file']['tmp_name']))) {
			$max_upload_size = (int)$this->config->get('config_file_max_size');

			if ($max_upload_size < 2097152) {
				$max_upload_size = 10485760;
			}

			// Sanitize the filename
			$filename = basename(preg_replace('/[^a-zA-Z0-9\.\-\s+]/', '', html_entity_decode($this->request->files['file']['name'], ENT_QUOTES, 'UTF-8')));

			// Validate the filename length
			if ((utf8_strlen($filename) < 3) || (utf8_strlen($filename) > 64)) {
				$json['error'] = $this->language->get('error_filename');
			}

			// Allowed file extension types
			$allowed = array();

			$extension_allowed = preg_replace('~\r?\n~', "\n", $this->config->get('config_file_ext_allowed'));

			$filetypes = explode("\n", $extension_allowed);

			foreach ($filetypes as $filetype) {
				$allowed[] = trim($filetype);
			}

			if (!in_array(strtolower(substr(strrchr($filename, '.'), 1)), $allowed)) {
				$json['error'] = $this->language->get('error_filetype');
			}

			// Allowed file mime types
			$allowed = array();

			$mime_allowed = preg_replace('~\r?\n~', "\n", $this->config->get('config_file_mime_allowed'));

			$filetypes = explode("\n", $mime_allowed);

			foreach ($filetypes as $filetype) {
				$allowed[] = trim($filetype);
			}

			// Verify the actual content MIME type (client-supplied type is not trusted)
			if (!class_exists('finfo')) {
				if (!in_array($this->request->files['file']['type'], $allowed)) {
					$json['error'] = $this->language->get('error_filetype');
				}
			} else {
				$finfo = new finfo(FILEINFO_MIME_TYPE);

				$mime = $finfo->file($this->request->files['file']['tmp_name']);

				if (!in_array($mime, $allowed)) {
					$json['error'] = $this->language->get('error_filetype');
				}
			}

			// Check to see if any PHP files are trying to be uploaded
			$content = file_get_contents($this->request->files['file']['tmp_name']);

			if (preg_match('/\<\?php/i', $content)) {
				$json['error'] = $this->language->get('error_filetype');
			}

			if ($this->request->files['file']['size'] > $max_upload_size) {
				$json['error'] = $this->language->get('error_upload_2');
			}

			// Return any upload error
			if ($this->request->files['file']['error'] != UPLOAD_ERR_OK) {
				$json['error'] = $this->language->get('error_upload_' . $this->request->files['file']['error']);
			}
		} elseif (!$json) {
			$json['error'] = $this->language->get('error_upload');
		}

		if (!$json) {
			// Store under a random name so the original cannot be linked to directly.
			$extension = strtolower(substr(strrchr($filename, '.'), 1));

			$file = token(32) . '.' . $extension;

			move_uploaded_file($this->request->files['file']['tmp_name'], DIR_UPLOAD . $file);

			// Hide the uploaded file name so people can not link to it directly.
			$this->load->model('tool/upload');

			$json['code'] = $this->model_tool_upload->addUpload($filename, $file);

			$json['success'] = $this->language->get('text_upload');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}