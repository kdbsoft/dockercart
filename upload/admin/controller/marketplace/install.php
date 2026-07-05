<?php

require_once DIR_SYSTEM . 'library/dockercart/install_helper.php';

class ControllerMarketplaceInstall extends Controller {
	public function install() {
		$this->load->language('marketplace/install');

		$json = array();

		if ($this->request->server['REQUEST_METHOD'] !== 'POST') {
			$json['error'] = $this->language->get('error_permission');
			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode($json));

			return;
		}

		if (isset($this->request->get['extension_install_id'])) {
			$extension_install_id = $this->request->get['extension_install_id'];
		} else {
			$extension_install_id = 0;
		}

		if (!$this->user->hasPermission('modify', 'marketplace/install')) {
			$json['error'] = $this->language->get('error_permission');
			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode($json));

			return;
		}

		// Make sure the file name is stored in the session.
		if (!isset($this->session->data['install'])) {
			$json['error'] = $this->language->get('error_file');
		} elseif (!is_file(DIR_UPLOAD . $this->session->data['install'] . '.tmp')) {
			$json['error'] = $this->language->get('error_file');
		}

		if (!$json) {
			$json['text'] = $this->language->get('text_unzip');

			$json['next'] = str_replace('&amp;', '&', $this->url->link('marketplace/install/unzip', 'user_token=' . $this->session->data['user_token'] . '&extension_install_id=' . $extension_install_id, true));
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function unzip() {
		$this->load->language('marketplace/install');

		$json = array();

		if ($this->request->server['REQUEST_METHOD'] !== 'POST') {
			$json['error'] = $this->language->get('error_permission');
			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode($json));

			return;
		}

		if (isset($this->request->get['extension_install_id'])) {
			$extension_install_id = $this->request->get['extension_install_id'];
		} else {
			$extension_install_id = 0;
		}

		if (!$this->user->hasPermission('modify', 'marketplace/install')) {
			$json['error'] = $this->language->get('error_permission');
			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode($json));

			return;
		}

		if (!isset($this->session->data['install'])) {
			$json['error'] = $this->language->get('error_file');
		} elseif (!is_file(DIR_UPLOAD . $this->session->data['install'] . '.tmp')) {
			$json['error'] = $this->language->get('error_file');
		}

		if (!$json) {
			$file = DIR_UPLOAD . $this->session->data['install'] . '.tmp';

			$zip = new ZipArchive();

			if ($zip->open($file)) {
				// Validate zip entries against path traversal
				$valid = true;
				for ($i = 0; $i < $zip->numFiles; $i++) {
					$entry = $zip->getNameIndex($i);
					if (strpos($entry, '..') !== false) {
						$valid = false;
						break;
					}
				}

				if ($valid) {
					$zip->extractTo(DIR_UPLOAD . 'tmp-' . $this->session->data['install']);
				} else {
					$json['error'] = $this->language->get('error_allowed');
				}

				$zip->close();
			} else {
				$json['error'] = $this->language->get('error_unzip');
			}

			// Remove Zip
			if (is_file($file)) {
				unlink($file);
			}

			if (!$json) {
				$json['text'] = $this->language->get('text_move');
				$json['next'] = str_replace('&amp;', '&', $this->url->link('marketplace/install/move', 'user_token=' . $this->session->data['user_token'] . '&extension_install_id=' . $extension_install_id, true));
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function move() {
		$this->load->language('marketplace/install');

		$json = array();

		if ($this->request->server['REQUEST_METHOD'] !== 'POST') {
			$json['error'] = $this->language->get('error_permission');
			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode($json));

			return;
		}

		if (isset($this->request->get['extension_install_id'])) {
			$extension_install_id = $this->request->get['extension_install_id'];
		} else {
			$extension_install_id = 0;
		}

		if (!$this->user->hasPermission('modify', 'marketplace/install')) {
			$json['error'] = $this->language->get('error_permission');
			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode($json));

			return;
		}

		if (!isset($this->session->data['install'])) {
			$json['error'] = $this->language->get('error_directory');
		} elseif (!is_dir(DIR_UPLOAD . 'tmp-' . $this->session->data['install'] . '/')) {
			$json['error'] = $this->language->get('error_directory');
		}

		if (!$json) {
			$directory = DIR_UPLOAD . 'tmp-' . $this->session->data['install'] . '/';

			if (is_dir($directory . 'upload/')) {
				$files = array();

				// Get a list of files ready to upload
				$path = array($directory . 'upload/*');

				while (count($path) != 0) {
					$next = array_shift($path);

					foreach ((array)glob($next) as $file) {
						if (is_dir($file)) {
							$path[] = $file . '/*';
						}

						$files[] = $file;
					}
				}

				// A list of allowed directories to be written to
				$allowed = array(
					'admin/',
					'catalog/',
					'system/',
					'image/'
				);

				// First we need to do some checks
				foreach ($files as $file) {
					$destination = str_replace('\\', '/', substr($file, strlen($directory . 'upload/')));

					$safe = false;

					foreach ($allowed as $value) {
						$normalized = $destination;
						if (strpos($normalized, '..') === false) {
							if (strpos($normalized . '/', $value) === 0) {
								$safe = true;
								break;
							}
						}
					}

					if ($safe) {
						// Check if the copy location exists or not
						if (substr($destination, 0, 5) == 'admin') {
							$destination = DIR_APPLICATION . substr($destination, 6);
						}

						if (substr($destination, 0, 7) == 'catalog') {
							$destination = DIR_CATALOG . substr($destination, 8);
						}

						if (substr($destination, 0, 5) == 'image') {
							$destination = DIR_IMAGE . substr($destination, 6);
						}

						if (substr($destination, 0, 6) == 'system') {
							$destination = DIR_SYSTEM . substr($destination, 7);
						}
					} else {
						$json['error'] = sprintf($this->language->get('error_allowed'), $destination);

						break;
					}
				}

				if (!$json) {
					$this->load->model('setting/extension');

					foreach ($files as $file) {
						$destination = str_replace('\\', '/', substr($file, strlen($directory . 'upload/')));

						$path = '';

						if (substr($destination, 0, 5) == 'admin') {
							$path = DIR_APPLICATION . substr($destination, 6);
						}

						if (substr($destination, 0, 7) == 'catalog') {
							$path = DIR_CATALOG . substr($destination, 8);
						}

						if (substr($destination, 0, 5) == 'image') {
							$path = DIR_IMAGE . substr($destination, 6);
						}

						if (substr($destination, 0, 6) == 'system') {
							$path = DIR_SYSTEM . substr($destination, 7);
						}

						if (is_dir($file) && !is_dir($path)) {
							if (mkdir($path, 0755, true)) {
								$this->model_setting_extension->addExtensionPath($extension_install_id, $destination);
							}
						}

						if (is_file($file)) {
							if (!is_dir(dirname($path))) {
								mkdir(dirname($path), 0755, true);
							}

							if (rename($file, $path)) {
								$this->model_setting_extension->addExtensionPath($extension_install_id, $destination);
							}
						}
					}

					$paths = $this->model_setting_extension->getExtensionPathsByExtensionInstallId($extension_install_id);
					DockercartInstallHelper::syncGitExclude(array_column($paths, 'path'), 'add');
				}
			}
		}

		if (!$json) {
			$json['text'] = $this->language->get('text_xml');

			$json['next'] = str_replace('&amp;', '&', $this->url->link('marketplace/install/xml', 'user_token=' . $this->session->data['user_token'] . '&extension_install_id=' . $extension_install_id, true));
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function xml() {
		$this->load->language('marketplace/install');

		$json = array();

		if ($this->request->server['REQUEST_METHOD'] !== 'POST') {
			$json['error'] = $this->language->get('error_permission');
			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode($json));

			return;
		}

		if (isset($this->request->get['extension_install_id'])) {
			$extension_install_id = $this->request->get['extension_install_id'];
		} else {
			$extension_install_id = 0;
		}

		if (!$this->user->hasPermission('modify', 'marketplace/install')) {
			$json['error'] = $this->language->get('error_permission');
			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode($json));

			return;
		}

		if (!isset($this->session->data['install'])) {
			$json['error'] = $this->language->get('error_directory');
		} elseif (!is_dir(DIR_UPLOAD . 'tmp-' . $this->session->data['install'] . '/')) {
			$json['error'] = $this->language->get('error_directory');
		}

		if (!$json) {
			$file = DIR_UPLOAD . 'tmp-' . $this->session->data['install'] . '/install.xml';

			if (is_file($file)) {
				$this->load->model('setting/modification');

				$xml = file_get_contents($file);

				if ($xml) {
					try {
						$dom = new DOMDocument('1.0', 'UTF-8');
						$old = libxml_disable_entity_loader(true);
						$dom->loadXml($xml, LIBXML_NONET);
						libxml_disable_entity_loader($old);

						$name = $dom->getElementsByTagName('name')->item(0);

						if ($name) {
							$name = $name->nodeValue;
						} else {
							$name = '';
						}

						$code_node = $dom->getElementsByTagName('code')->item(0);

						if ($code_node) {
							$code = trim($code_node->nodeValue);

							if ($code === '') {
								$json['error'] = $this->language->get('error_code');
							}

							if (!$json) {
								$modification_info = $this->model_setting_modification->getModificationByCode($code);

								if ($modification_info) {
									$this->model_setting_modification->deleteModification($modification_info['modification_id']);
								}
							}
						} else {
							$json['error'] = $this->language->get('error_code');
						}

						$author = $dom->getElementsByTagName('author')->item(0);

						if ($author) {
							$author = $author->nodeValue;
						} else {
							$author = '';
						}

						$version = $dom->getElementsByTagName('version')->item(0);

						if ($version) {
							$version = $version->nodeValue;
						} else {
							$version = '';
						}

						$link = $dom->getElementsByTagName('link')->item(0);

						if ($link) {
							$link = $link->nodeValue;
						} else {
							$link = '';
						}

						if (!$json) {
							$modification_data = array(
								'extension_install_id' => $extension_install_id,
								'name'                 => $name,
								'code'                 => $code,
								'author'               => $author,
								'version'              => $version,
								'link'                 => $link,
								'xml'                  => $xml,
								'status'               => 1
							);

							$this->model_setting_modification->addModification($modification_data);
						}
					} catch (Exception $exception) {
						$json['error'] = $this->language->get('error_unzip');
					}
				}
			}
		}

		if (!$json) {
			$json['text'] = $this->language->get('text_remove');

			$json['next'] = str_replace('&amp;', '&', $this->url->link('marketplace/install/remove', 'user_token=' . $this->session->data['user_token'], true));
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function remove() {
		$this->load->language('marketplace/install');

		$json = array();

		if ($this->request->server['REQUEST_METHOD'] !== 'POST') {
			$json['error'] = $this->language->get('error_permission');
			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode($json));

			return;
		}

		if (!$this->user->hasPermission('modify', 'marketplace/install')) {
			$json['error'] = $this->language->get('error_permission');
			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode($json));

			return;
		}

		if (!isset($this->session->data['install'])) {
			$json['error'] = $this->language->get('error_directory');
		}

		if (!$json) {
			$directory = DIR_UPLOAD . 'tmp-' . $this->session->data['install'] . '/';

			if (is_dir($directory)) {
				$files = array();

				$path = array($directory);

				while (count($path) != 0) {
					$next = array_shift($path);

					foreach (array_diff(scandir($next), array('.', '..')) as $file) {
						$file = $next . '/' . $file;

						if (is_dir($file)) {
							$path[] = $file;
						}

						$files[] = $file;
					}
				}

				rsort($files);

				foreach ($files as $file) {
					if (is_file($file)) {
						unlink($file);
					} elseif (is_dir($file)) {
						rmdir($file);
					}
				}

				if (is_dir($directory)) {
					rmdir($directory);
				}
			}

			$file = DIR_UPLOAD . $this->session->data['install'] . '.tmp';

			if (is_file($file)) {
				unlink($file);
			}

			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function uninstall() {
		$this->load->language('marketplace/install');

		$json = array();

		if ($this->request->server['REQUEST_METHOD'] !== 'POST') {
			$json['error'] = $this->language->get('error_permission');
			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode($json));

			return;
		}

		if (isset($this->request->get['extension_install_id'])) {
			$extension_install_id = $this->request->get['extension_install_id'];
		} else {
			$extension_install_id = 0;
		}

		if (!$this->user->hasPermission('modify', 'marketplace/install')) {
			$json['error'] = $this->language->get('error_permission');
			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode($json));

			return;
		}

		if (!$json) {
			$this->load->model('setting/extension');

			$results = $this->model_setting_extension->getExtensionPathsByExtensionInstallId($extension_install_id);

			rsort($results);

			DockercartInstallHelper::syncGitExclude(array_column($results, 'path'), 'remove');

			foreach ($results as $result) {
				$source = DockercartInstallHelper::getSourcePath($result['path']);

				if (is_file($source)) {
					unlink($source);
				}

				if (is_dir($source)) {
					$files = array();

					$path = array($source);

					while (count($path) != 0) {
						$next = array_shift($path);

						foreach (array_diff(scandir($next), array('.', '..')) as $file) {
							$file = $next . '/' . $file;

							if (is_dir($file)) {
								$path[] = $file;
							}

							$files[] = $file;
						}
					}

					rsort($files);

					foreach ($files as $file) {
						if (is_dir($file)) {
							if (DockercartInstallHelper::isDirEmpty($file)) {
								rmdir($file);
							}
						}
					}

					if (is_file($source)) {
						unlink($source);
					}

					if (is_dir($source)) {
						if (DockercartInstallHelper::isDirEmpty($source)) {
							rmdir($source);
						}
					}
				}

				$this->model_setting_extension->deleteExtensionPath($result['extension_path_id']);
			}

			// Remove the install
			$this->model_setting_extension->deleteExtensionInstall($extension_install_id);

			// Remove any xml modifications
			$this->load->model('setting/modification');

			$this->model_setting_modification->deleteModificationsByExtensionInstallId($extension_install_id);

			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
