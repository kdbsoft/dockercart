<?php
/**
 * Modifcation XML Documentation can be found here:
 *
 * https://github.com/opencart/opencart/wiki/Modification-System
 */
class ControllerMarketplaceModification extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('marketplace/modification');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/modification');

		$this->getList();
	}

	public function delete() {
		$this->load->language('marketplace/modification');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/modification');

		if (isset($this->request->post['selected']) && $this->validate()) {
			foreach ($this->request->post['selected'] as $modification_id) {
				$this->model_setting_modification->deleteModification($modification_id);
			}

			$this->session->data['success'] = $this->language->get('text_success');

			$url = '';

			if (isset($this->request->get['sort'])) {
				$url .= '&sort=' . $this->request->get['sort'];
			}

			if (isset($this->request->get['order'])) {
				$url .= '&order=' . $this->request->get['order'];
			}

			if (isset($this->request->get['page'])) {
				$url .= '&page=' . $this->request->get['page'];
			}

			$this->response->redirect($this->url->link('marketplace/modification', 'user_token=' . $this->session->data['user_token'] . $url, true));
		}

		$this->getList();
	}

	public function refresh($data = array()) {
		$this->load->language('marketplace/modification');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/modification');

		if ($this->validate()) {
			//Log
			$log = array();

			// Clear all modification files
			$files = array();

			// Make path into an array
			$path = array(DIR_MODIFICATION . '*');

			// While the path array is still populated keep looping through
			while (count($path) != 0) {
				$next = array_shift($path);

				foreach (glob($next) as $file) {
					// If directory add to path array
					if (is_dir($file)) {
						$path[] = $file . '/*';
					}

					// Add the file to the files to be deleted array
					$files[] = $file;
				}
			}

			// Reverse sort the file array
			rsort($files);

			// Clear all modification files
			foreach ($files as $file) {
				if ($file != DIR_MODIFICATION . 'index.html') {
					// If file just delete
					if (is_file($file)) {
						unlink($file);

					// If directory use the remove directory function
					} elseif (is_dir($file)) {
						rmdir($file);
					}
				}
			}

			// Begin
			$xml = array();

			// This is purly for developers so they can run mods directly and have them run without upload after each change.
			$files = glob(DIR_SYSTEM . '*.ocmod.xml');

			if ($files) {
				foreach ($files as $file) {
					$xml[] = file_get_contents($file);
				}
			}

			// Get the default modification file
			$results = $this->model_setting_modification->getModifications();

			foreach ($results as $result) {
				if ($result['status']) {
					$xml[] = $result['xml'];
				}
			}

			$modification = array();

			foreach ($xml as $xml) {
				if (empty($xml)){
					continue;
				}
				
				$dom = new DOMDocument('1.0', 'UTF-8');
				$dom->preserveWhiteSpace = false;
				$dom->loadXml($xml);

				// Log
				$log[] = 'MOD: ' . $dom->getElementsByTagName('name')->item(0)->textContent;

				// Wipe the past modification store in the backup array
				$recovery = array();

				// Set the a recovery of the modification code in case we need to use it if an abort attribute is used.
				if ($modification) {
					$recovery = $modification;
				}

				$files = $dom->getElementsByTagName('modification')->item(0)->getElementsByTagName('file');

				foreach ($files as $file) {
					$operations = $file->getElementsByTagName('operation');

					$files = explode('|', str_replace("\\", '/', $file->getAttribute('path')));

					foreach ($files as $file) {
						$path = '';

						// Get the full path of the files that are going to be used for modification
						if ((substr($file, 0, 7) == 'catalog')) {
							$path = DIR_CATALOG . substr($file, 8);
						}

						if ((substr($file, 0, 5) == 'admin')) {
							$path = DIR_APPLICATION . substr($file, 6);
						}

						if ((substr($file, 0, 6) == 'system')) {
							$path = DIR_SYSTEM . substr($file, 7);
						}

						if ($path) {
							$files = glob($path, GLOB_BRACE);

							if ($files) {
								foreach ($files as $file) {
									// Get the key to be used for the modification cache filename.
									if (substr($file, 0, strlen(DIR_CATALOG)) == DIR_CATALOG) {
										$key = 'catalog/' . substr($file, strlen(DIR_CATALOG));
									}

									if (substr($file, 0, strlen(DIR_APPLICATION)) == DIR_APPLICATION) {
										$key = 'admin/' . substr($file, strlen(DIR_APPLICATION));
									}

									if (substr($file, 0, strlen(DIR_SYSTEM)) == DIR_SYSTEM) {
										$key = 'system/' . substr($file, strlen(DIR_SYSTEM));
									}

									// If file contents is not already in the modification array we need to load it.
									if (!isset($modification[$key])) {
										$content = file_get_contents($file);

										$modification[$key] = preg_replace('~\r?\n~', "\n", $content);
										$original[$key] = preg_replace('~\r?\n~', "\n", $content);

										// Log
										$log[] = PHP_EOL . 'FILE: ' . $key;

									} else {
										// Log
										$log[] = PHP_EOL . 'FILE: (sub modification) ' . $key;
									
									}

									foreach ($operations as $operation) {
										$error = $operation->getAttribute('error');

										// Ignoreif
										$ignoreif = $operation->getElementsByTagName('ignoreif')->item(0);

										if ($ignoreif) {
											if ($ignoreif->getAttribute('regex') != 'true') {
												if (strpos($modification[$key], $ignoreif->textContent) !== false) {
													continue;
												}
											} else {
												if (preg_match($ignoreif->textContent, $modification[$key])) {
													continue;
												}
											}
										}

										$status = false;

										// Search and replace
										if ($operation->getElementsByTagName('search')->item(0)->getAttribute('regex') != 'true') {
											// Search
											$search = $operation->getElementsByTagName('search')->item(0)->textContent;
											$trim = $operation->getElementsByTagName('search')->item(0)->getAttribute('trim');
											$index = $operation->getElementsByTagName('search')->item(0)->getAttribute('index');

											// Trim line if no trim attribute is set or is set to true.
											if (!$trim || $trim == 'true') {
												$search = trim($search);
											}

											// Add
											$add = $operation->getElementsByTagName('add')->item(0)->textContent;
											$trim = $operation->getElementsByTagName('add')->item(0)->getAttribute('trim');
											$position = $operation->getElementsByTagName('add')->item(0)->getAttribute('position');
											$offset = $operation->getElementsByTagName('add')->item(0)->getAttribute('offset');

											if ($offset == '') {
												$offset = 0;
											}

											// Trim line if is set to true.
											if ($trim == 'true') {
												$add = trim($add);
											}

											// Log
											$log[] = 'CODE: ' . $search;

											// Check if using indexes
											if ($index !== '') {
												$indexes = explode(',', $index);
											} else {
												$indexes = array();
											}

											// Get all the matches
											$i = 0;

											$lines = explode("\n", $modification[$key]);

											for ($line_id = 0; $line_id < count($lines); $line_id++) {
												$line = $lines[$line_id];

												// Status
												$match = false;

												// Check to see if the line matches the search code.
												if (stripos($line, $search) !== false) {
													// If indexes are not used then just set the found status to true.
													if (!$indexes) {
														$match = true;
													} elseif (in_array($i, $indexes)) {
														$match = true;
													}

													$i++;
												}

												// Now for replacing or adding to the matched elements
												if ($match) {
													switch ($position) {
														default:
														case 'replace':
															$new_lines = explode("\n", $add);

															if ($offset < 0) {
																array_splice($lines, $line_id + $offset, abs($offset) + 1, array(str_replace($search, $add, $line)));

																$line_id -= $offset;
															} else {
																array_splice($lines, $line_id, $offset + 1, array(str_replace($search, $add, $line)));
															}
															break;
														case 'before':
															$new_lines = explode("\n", $add);

															array_splice($lines, $line_id - $offset, 0, $new_lines);

															$line_id += count($new_lines);
															break;
														case 'after':
															$new_lines = explode("\n", $add);

															array_splice($lines, ($line_id + 1) + $offset, 0, $new_lines);

															$line_id += count($new_lines);
															break;
													}

													// Log
													$log[] = 'LINE: ' . $line_id;

													$status = true;
												}
											}

											$modification[$key] = implode("\n", $lines);
										} else {
											$search = trim($operation->getElementsByTagName('search')->item(0)->textContent);
											$limit = $operation->getElementsByTagName('search')->item(0)->getAttribute('limit');
											$replace = trim($operation->getElementsByTagName('add')->item(0)->textContent);

											// Limit
											if (!$limit) {
												$limit = -1;
											}

											// Log
											$match = array();

											preg_match_all($search, $modification[$key], $match, PREG_OFFSET_CAPTURE);

											// Remove part of the the result if a limit is set.
											if ($limit > 0) {
												$match[0] = array_slice($match[0], 0, $limit);
											}

											if ($match[0]) {
												$log[] = 'REGEX: ' . $search;

												for ($i = 0; $i < count($match[0]); $i++) {
													$log[] = 'LINE: ' . (substr_count(substr($modification[$key], 0, $match[0][$i][1]), "\n") + 1);
												}

												$status = true;
											}

											// Make the modification
											$modification[$key] = preg_replace($search, $replace, $modification[$key], $limit);
										}

										if (!$status) {
											// Abort applying this modification completely.
											if ($error == 'abort') {
												$modification = $recovery;
												// Log
												$log[] = 'NOT FOUND - ABORTING!';
												break 5;
											}
											// Skip current operation or break
											elseif ($error == 'skip') {
												// Log
												$log[] = 'NOT FOUND - OPERATION SKIPPED!';
												continue;
											}
											// Break current operations
											else {
												// Log
												$log[] = 'NOT FOUND - OPERATIONS ABORTED!';
											 	break;
											}
										}
									}
								}
							}
						}
					}
				}

				// Log
				$log[] = '----------------------------------------------------------------';
			}

			// Log
			$ocmod = new Log('ocmod.log');
			$ocmod->write(implode("\n", $log));

			// Write all modification files
			foreach ($modification as $key => $value) {
				// Only create a file if there are changes
				if ($original[$key] != $value) {
					$path = '';

					$directories = explode('/', dirname($key));

					foreach ($directories as $directory) {
						$path = $path . '/' . $directory;

						if (!is_dir(DIR_MODIFICATION . $path)) {
							@mkdir(DIR_MODIFICATION . $path, 0777);
						}
					}

					$handle = fopen(DIR_MODIFICATION . $key, 'w');

					fwrite($handle, $value);

					fclose($handle);
				}
			}

			// Do not return success message if refresh() was called with $data
			$this->session->data['success'] = $this->language->get('text_success');

			$url = '';

			if (isset($this->request->get['sort'])) {
				$url .= '&sort=' . $this->request->get['sort'];
			}

			if (isset($this->request->get['order'])) {
				$url .= '&order=' . $this->request->get['order'];
			}

			if (isset($this->request->get['page'])) {
				$url .= '&page=' . $this->request->get['page'];
			}

			$this->response->redirect($this->url->link(!empty($data['redirect']) ? $data['redirect'] : 'marketplace/modification', 'user_token=' . $this->session->data['user_token'] . $url, true));
		}

		$this->getList();
	}

	public function clear() {
		$this->load->language('marketplace/modification');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/modification');

		if ($this->validate()) {
			$files = array();

			// Make path into an array
			$path = array(DIR_MODIFICATION . '*');

			// While the path array is still populated keep looping through
			while (count($path) != 0) {
				$next = array_shift($path);

				foreach (glob($next) as $file) {
					// If directory add to path array
					if (is_dir($file)) {
						$path[] = $file . '/*';
					}

					// Add the file to the files to be deleted array
					$files[] = $file;
				}
			}

			// Reverse sort the file array
			rsort($files);

			// Clear all modification files
			foreach ($files as $file) {
				if ($file != DIR_MODIFICATION . 'index.html') {
					// If file just delete
					if (is_file($file)) {
						unlink($file);

					// If directory use the remove directory function
					} elseif (is_dir($file)) {
						rmdir($file);
					}
				}
			}

			$this->session->data['success'] = $this->language->get('text_success');

			$url = '';

			if (isset($this->request->get['sort'])) {
				$url .= '&sort=' . $this->request->get['sort'];
			}

			if (isset($this->request->get['order'])) {
				$url .= '&order=' . $this->request->get['order'];
			}

			if (isset($this->request->get['page'])) {
				$url .= '&page=' . $this->request->get['page'];
			}

			$this->response->redirect($this->url->link('marketplace/modification', 'user_token=' . $this->session->data['user_token'] . $url, true));
		}

		$this->getList();
	}

	public function enable() {
		$this->load->language('marketplace/modification');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/modification');

		if (isset($this->request->get['modification_id']) && $this->validate()) {
			$this->model_setting_modification->enableModification($this->request->get['modification_id']);

			$this->session->data['success'] = $this->language->get('text_success');

			$url = '';

			if (isset($this->request->get['sort'])) {
				$url .= '&sort=' . $this->request->get['sort'];
			}

			if (isset($this->request->get['order'])) {
				$url .= '&order=' . $this->request->get['order'];
			}

			if (isset($this->request->get['page'])) {
				$url .= '&page=' . $this->request->get['page'];
			}

			$this->response->redirect($this->url->link('marketplace/modification', 'user_token=' . $this->session->data['user_token'] . $url, true));
		}

		$this->getList();
	}

	public function disable() {
		$this->load->language('marketplace/modification');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/modification');

		if (isset($this->request->get['modification_id']) && $this->validate()) {
			$this->model_setting_modification->disableModification($this->request->get['modification_id']);

			$this->session->data['success'] = $this->language->get('text_success');

			$url = '';

			if (isset($this->request->get['sort'])) {
				$url .= '&sort=' . $this->request->get['sort'];
			}

			if (isset($this->request->get['order'])) {
				$url .= '&order=' . $this->request->get['order'];
			}

			if (isset($this->request->get['page'])) {
				$url .= '&page=' . $this->request->get['page'];
			}

			$this->response->redirect($this->url->link('marketplace/modification', 'user_token=' . $this->session->data['user_token'] . $url, true));
		}

		$this->getList();
	}

	public function clearlog() {
		$this->load->language('marketplace/modification');
		
		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/modification');
		
		if ($this->validate()) {
			$handle = fopen(DIR_LOGS . 'ocmod.log', 'w+');

			fclose($handle);

			$this->session->data['success'] = $this->language->get('text_success');

			$url = '';

			if (isset($this->request->get['sort'])) {
				$url .= '&sort=' . $this->request->get['sort'];
			}

			if (isset($this->request->get['order'])) {
				$url .= '&order=' . $this->request->get['order'];
			}

			if (isset($this->request->get['page'])) {
				$url .= '&page=' . $this->request->get['page'];
			}

			$this->response->redirect($this->url->link('marketplace/modification', 'user_token=' . $this->session->data['user_token'] . $url, true));
		}

		$this->getList();
	}

	public function add() {
		$this->load->language('marketplace/modification');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/modification');

		$this->decodePostFields();

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {
			$this->model_setting_modification->addModification(array(
				'extension_install_id' => 0,
				'name'                 => $this->request->post['name'],
				'code'                 => $this->request->post['code'],
				'author'               => $this->request->post['author'],
				'version'              => $this->request->post['version'],
				'link'                 => $this->request->post['link'],
				'xml'                  => $this->request->post['xml'],
				'status'               => $this->request->post['status']
			));

			$this->session->data['success'] = $this->language->get('text_success');

			$url = '';

			if (isset($this->request->get['sort'])) {
				$url .= '&sort=' . $this->request->get['sort'];
			}

			if (isset($this->request->get['order'])) {
				$url .= '&order=' . $this->request->get['order'];
			}

			if (isset($this->request->get['page'])) {
				$url .= '&page=' . $this->request->get['page'];
			}

			$this->response->redirect($this->url->link('marketplace/modification', 'user_token=' . $this->session->data['user_token'] . $url, true));
		}

		$this->getForm();
	}

	public function edit() {
		$this->load->language('marketplace/modification');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/modification');

		$this->decodePostFields();

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {
			$this->model_setting_modification->editModification($this->request->get['modification_id'], array(
				'name'    => $this->request->post['name'],
				'code'    => $this->request->post['code'],
				'author'  => $this->request->post['author'],
				'version' => $this->request->post['version'],
				'link'    => $this->request->post['link'],
				'xml'     => $this->request->post['xml'],
				'status'  => $this->request->post['status']
			));

			$this->session->data['success'] = $this->language->get('text_success');

			$url = '';

			if (isset($this->request->get['sort'])) {
				$url .= '&sort=' . $this->request->get['sort'];
			}

			if (isset($this->request->get['order'])) {
				$url .= '&order=' . $this->request->get['order'];
			}

			if (isset($this->request->get['page'])) {
				$url .= '&page=' . $this->request->get['page'];
			}

			$this->response->redirect($this->url->link('marketplace/modification', 'user_token=' . $this->session->data['user_token'] . $url, true));
		}

		$this->getForm();
	}

	public function download() {
		$this->load->language('marketplace/modification');

		$this->load->model('setting/modification');

		if (isset($this->request->get['modification_id']) && $this->validate()) {
			$modification_info = $this->model_setting_modification->getModification($this->request->get['modification_id']);

			if ($modification_info) {
				$filename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $modification_info['code']) . '.ocmod.xml';

				$this->response->addHeader('Content-Type: application/xml');
				$this->response->addHeader('Content-Disposition: attachment; filename="' . $filename . '"');
				$this->response->addHeader('Content-Length: ' . strlen($modification_info['xml']));
				$this->response->setOutput($modification_info['xml']);
				return;
			}
		}

		$this->response->redirect($this->url->link('marketplace/modification', 'user_token=' . $this->session->data['user_token'], true));
	}

	public function test() {
		$this->load->language('marketplace/modification');

		$this->load->model('setting/modification');

		if (!$this->validate()) {
			$this->getList();
			return;
		}

		// Determine XML source: from existing modification or from POST
		$xml_content = '';
		$source_name = '';

		if (isset($this->request->get['modification_id'])) {
			$modification_info = $this->model_setting_modification->getModification($this->request->get['modification_id']);

			if (!$modification_info) {
				$this->response->redirect($this->url->link('marketplace/modification', 'user_token=' . $this->session->data['user_token'], true));
				return;
			}

			$xml_content = $modification_info['xml'];
			$source_name = $modification_info['name'];
		} elseif (isset($this->request->post['xml'])) {
			$xml_content = html_entity_decode($this->request->post['xml'], ENT_QUOTES, 'UTF-8');
			$source_name = isset($this->request->post['name']) ? $this->request->post['name'] : 'Unsaved';
		}

		$test_results = $this->runDryTest($xml_content);

		// If called from form, render form with test results
		if (isset($this->request->post['xml'])) {
			$data = $this->getFormData();
			$data['test_results'] = $test_results;

			$data['header'] = $this->load->controller('common/header');
			$data['column_left'] = $this->load->controller('common/column_left');
			$data['footer'] = $this->load->controller('common/footer');

			$this->response->setOutput($this->load->view('marketplace/modification_form', $data));
			return;
		}

		// Called from list page - show results in a dedicated view
		$this->document->setTitle($this->language->get('heading_title'));

		$url = '';

		if (isset($this->request->get['sort'])) {
			$url .= '&sort=' . $this->request->get['sort'];
		}

		if (isset($this->request->get['order'])) {
			$url .= '&order=' . $this->request->get['order'];
		}

		if (isset($this->request->get['page'])) {
			$url .= '&page=' . $this->request->get['page'];
		}

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('marketplace/modification', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_test'),
			'href' => $this->url->link('marketplace/modification/test', 'user_token=' . $this->session->data['user_token'] . '&modification_id=' . $this->request->get['modification_id'], true)
		);

		$data['source_name'] = $source_name;
		$data['test_results'] = $test_results;
		$data['cancel'] = $this->url->link('marketplace/modification', 'user_token=' . $this->session->data['user_token'] . $url, true);
		$data['user_token'] = $this->session->data['user_token'];

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('marketplace/modification_test', $data));
	}

	protected function getForm() {
		$data = $this->getFormData();

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('marketplace/modification_form', $data));
	}

	protected function getFormData() {
		$data['text_form'] = !isset($this->request->get['modification_id']) ? $this->language->get('text_add') : $this->language->get('text_edit');

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		if (isset($this->error['name'])) {
			$data['error_name'] = $this->error['name'];
		} else {
			$data['error_name'] = '';
		}

		if (isset($this->error['code'])) {
			$data['error_code'] = $this->error['code'];
		} else {
			$data['error_code'] = '';
		}

		if (isset($this->error['xml'])) {
			$data['error_xml'] = $this->error['xml'];
		} else {
			$data['error_xml'] = '';
		}

		$url = '';

		if (isset($this->request->get['sort'])) {
			$url .= '&sort=' . $this->request->get['sort'];
		}

		if (isset($this->request->get['order'])) {
			$url .= '&order=' . $this->request->get['order'];
		}

		if (isset($this->request->get['page'])) {
			$url .= '&page=' . $this->request->get['page'];
		}

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('marketplace/modification', 'user_token=' . $this->session->data['user_token'] . $url, true)
		);

		$modification_id = $this->request->get['modification_id'] ?? $this->request->post['modification_id'] ?? null;

		if ($modification_id) {
			$data['action'] = $this->url->link('marketplace/modification/edit', 'user_token=' . $this->session->data['user_token'] . '&modification_id=' . $modification_id . $url, true);
		} else {
			$data['action'] = $this->url->link('marketplace/modification/add', 'user_token=' . $this->session->data['user_token'] . $url, true);
		}

		$data['cancel'] = $this->url->link('marketplace/modification', 'user_token=' . $this->session->data['user_token'] . $url, true);
		$data['test_action'] = $this->url->link('marketplace/modification/test', 'user_token=' . $this->session->data['user_token'], true);
		$data['user_token'] = $this->session->data['user_token'];
		$data['modification_id'] = $modification_id;

		if ($modification_id && ($this->request->server['REQUEST_METHOD'] != 'POST')) {
			$modification_info = $this->model_setting_modification->getModification($modification_id);
		}

		if (isset($this->request->post['name'])) {
			$data['name'] = htmlspecialchars(html_entity_decode($this->request->post['name'], ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8');
		} elseif (!empty($modification_info)) {
			$data['name'] = htmlspecialchars($modification_info['name'], ENT_QUOTES, 'UTF-8');
		} else {
			$data['name'] = '';
		}

		if (isset($this->request->post['code'])) {
			$data['code'] = htmlspecialchars(html_entity_decode($this->request->post['code'], ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8');
		} elseif (!empty($modification_info)) {
			$data['code'] = htmlspecialchars($modification_info['code'], ENT_QUOTES, 'UTF-8');
		} else {
			$data['code'] = '';
		}

		if (isset($this->request->post['author'])) {
			$data['author'] = htmlspecialchars(html_entity_decode($this->request->post['author'], ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8');
		} elseif (!empty($modification_info)) {
			$data['author'] = htmlspecialchars($modification_info['author'], ENT_QUOTES, 'UTF-8');
		} else {
			$data['author'] = '';
		}

		if (isset($this->request->post['version'])) {
			$data['version'] = htmlspecialchars(html_entity_decode($this->request->post['version'], ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8');
		} elseif (!empty($modification_info)) {
			$data['version'] = htmlspecialchars($modification_info['version'], ENT_QUOTES, 'UTF-8');
		} else {
			$data['version'] = '';
		}

		if (isset($this->request->post['link'])) {
			$data['link'] = htmlspecialchars(html_entity_decode($this->request->post['link'], ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8');
		} elseif (!empty($modification_info)) {
			$data['link'] = htmlspecialchars($modification_info['link'], ENT_QUOTES, 'UTF-8');
		} else {
			$data['link'] = '';
		}

		if (isset($this->request->post['xml'])) {
			$data['xml'] = htmlspecialchars(html_entity_decode($this->request->post['xml'], ENT_QUOTES, 'UTF-8'), ENT_XML1, 'UTF-8');
		} elseif (!empty($modification_info)) {
			$data['xml'] = htmlspecialchars($modification_info['xml'], ENT_XML1, 'UTF-8');
		} else {
			$data['xml'] = '';
		}

		if (isset($this->request->post['status'])) {
			$data['status'] = $this->request->post['status'];
		} elseif (!empty($modification_info)) {
			$data['status'] = $modification_info['status'];
		} else {
			$data['status'] = 1;
		}

		$data['test_results'] = isset($this->request->post['test_results']) ? $this->request->post['test_results'] : null;

		return $data;
	}

	protected function validateForm() {
		if (!$this->user->hasPermission('modify', 'marketplace/modification')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		if ((utf8_strlen(trim($this->request->post['name'])) < 1) || (utf8_strlen(trim($this->request->post['name'])) > 64)) {
			$this->error['name'] = $this->language->get('error_name');
		}

		$code = trim($this->request->post['code']);

		if ((utf8_strlen($code) < 1) || (utf8_strlen($code) > 64)) {
			$this->error['code'] = $this->language->get('error_code');
		} else {
			// Check for duplicate code
			$modification_info = $this->model_setting_modification->getModificationByCode($code);

			if ($modification_info) {
				if (!isset($this->request->get['modification_id']) || ($modification_info['modification_id'] != $this->request->get['modification_id'])) {
					$this->error['code'] = sprintf($this->language->get('error_exists'), $code);
				}
			}
		}

		$xml = trim($this->request->post['xml']);

		if (utf8_strlen($xml) < 1) {
			$this->error['xml'] = $this->language->get('error_xml');
		} else {
			// Validate XML syntax
			$dom = new \DOMDocument('1.0', 'UTF-8');
			$dom->preserveWhiteSpace = false;

			libxml_use_internal_errors(true);

			if (!$dom->loadXml($xml)) {
				$errors = libxml_get_errors();
				$error_msg = $this->language->get('error_xml_syntax');

				if (!empty($errors)) {
					$error_msg .= ' ' . $errors[0]->message;
				}

				$this->error['xml'] = $error_msg;
				libxml_clear_errors();
			}

			libxml_use_internal_errors(false);
		}

		return !$this->error;
	}

	protected function runDryTest($xml_content) {
		$results = array(
			'status'     => 'success',
			'messages'   => array(),
			'files'      => array(),
			'operations' => array()
		);

		if (empty(trim($xml_content))) {
			$results['status'] = 'error';
			$results['messages'][] = array('type' => 'error', 'text' => 'XML content is empty');
			return $results;
		}

		// Validate XML syntax
		$dom = new \DOMDocument('1.0', 'UTF-8');
		$dom->preserveWhiteSpace = false;

		libxml_use_internal_errors(true);

		if (!$dom->loadXml($xml_content)) {
			$errors = libxml_get_errors();
			$results['status'] = 'error';

			foreach ($errors as $error) {
				$results['messages'][] = array(
					'type' => 'error',
					'text' => 'XML Parse Error: ' . trim($error->message) . ' (line ' . $error->line . ')'
				);
			}

			libxml_clear_errors();
			libxml_use_internal_errors(false);
			return $results;
		}

		libxml_use_internal_errors(false);

		// Validate structure
		$name_nodes = $dom->getElementsByTagName('name');
		$code_nodes = $dom->getElementsByTagName('code');
		$file_nodes = $dom->getElementsByTagName('file');

		if ($name_nodes->length == 0) {
			$results['messages'][] = array('type' => 'error', 'text' => 'Missing required element: <name>');
			$results['status'] = 'error';
		} else {
			$mod_name = $name_nodes->item(0)->textContent;
			$results['messages'][] = array('type' => 'info', 'text' => 'Modification: ' . $mod_name);
		}

		if ($code_nodes->length == 0) {
			$results['messages'][] = array('type' => 'error', 'text' => 'Missing required element: <code>');
			$results['status'] = 'error';
		} else {
			$results['messages'][] = array('type' => 'info', 'text' => 'Code: ' . $code_nodes->item(0)->textContent);
		}

		if ($file_nodes->length == 0) {
			$results['messages'][] = array('type' => 'error', 'text' => 'Missing required element: <file>');
			$results['status'] = 'error';
			return $results;
		}

		if ($results['status'] == 'error') {
			return $results;
		}

		$results['messages'][] = array('type' => 'info', 'text' => 'Files to modify: ' . $file_nodes->length);

		// Load original files and simulate modifications (dry-run, no writing)
		$modification = array();
		$original = array();

		foreach ($file_nodes as $file) {
			$operations = $file->getElementsByTagName('operation');

			$paths = explode('|', str_replace("\\", '/', $file->getAttribute('path')));

			foreach ($paths as $path_pattern) {
				$path = '';

				if ((substr($path_pattern, 0, 7) == 'catalog')) {
					$path = DIR_CATALOG . substr($path_pattern, 8);
				}

				if ((substr($path_pattern, 0, 5) == 'admin')) {
					$path = DIR_APPLICATION . substr($path_pattern, 6);
				}

				if ((substr($path_pattern, 0, 6) == 'system')) {
					$path = DIR_SYSTEM . substr($path_pattern, 7);
				}

				if ($path) {
					$matched_files = glob($path, GLOB_BRACE);

					if ($matched_files) {
						foreach ($matched_files as $matched_file) {
							if (substr($matched_file, 0, strlen(DIR_CATALOG)) == DIR_CATALOG) {
								$key = 'catalog/' . substr($matched_file, strlen(DIR_CATALOG));
							} elseif (substr($matched_file, 0, strlen(DIR_APPLICATION)) == DIR_APPLICATION) {
								$key = 'admin/' . substr($matched_file, strlen(DIR_APPLICATION));
							} elseif (substr($matched_file, 0, strlen(DIR_SYSTEM)) == DIR_SYSTEM) {
								$key = 'system/' . substr($matched_file, strlen(DIR_SYSTEM));
							} else {
								continue;
							}

							$file_entry = array(
								'key'        => $key,
								'modified'   => false,
								'operations' => array()
							);

							if (!isset($modification[$key])) {
								$content = file_get_contents($matched_file);
								$modification[$key] = preg_replace('~\r?\n~', "\n", $content);
								$original[$key] = preg_replace('~\r?\n~', "\n", $content);

								$file_entry['status'] = 'found';
							} else {
								$file_entry['status'] = 'sub';
							}

							foreach ($operations as $operation) {
								$op_result = array(
									'status' => 'ok'
								);

								$error_attr = $operation->getAttribute('error');

								// Check ignoreif
								$ignoreif = $operation->getElementsByTagName('ignoreif')->item(0);

								if ($ignoreif) {
									$op_result['ignoreif'] = true;

									if ($ignoreif->getAttribute('regex') != 'true') {
										if (strpos($modification[$key], $ignoreif->textContent) !== false) {
											$op_result['ignored'] = true;
											$op_result['status'] = 'skipped';
											$file_entry['operations'][] = $op_result;
											continue;
										}
									} else {
										if (@preg_match($ignoreif->textContent, $modification[$key])) {
											$op_result['ignored'] = true;
											$op_result['status'] = 'skipped';
											$file_entry['operations'][] = $op_result;
											continue;
										}
									}
								}

								$status = false;

								if ($operation->getElementsByTagName('search')->item(0)->getAttribute('regex') != 'true') {
									$search = $operation->getElementsByTagName('search')->item(0)->textContent;
									$trim_attr = $operation->getElementsByTagName('search')->item(0)->getAttribute('trim');
									$index = $operation->getElementsByTagName('search')->item(0)->getAttribute('index');

									if (!$trim_attr || $trim_attr == 'true') {
										$search = trim($search);
									}

									$add = $operation->getElementsByTagName('add')->item(0)->textContent;
									$trim_add = $operation->getElementsByTagName('add')->item(0)->getAttribute('trim');
									$position = $operation->getElementsByTagName('add')->item(0)->getAttribute('position');
									$offset = $operation->getElementsByTagName('add')->item(0)->getAttribute('offset');

									if ($offset == '') {
										$offset = 0;
									}

									if ($trim_add == 'true') {
										$add = trim($add);
									}

									$op_result['search'] = mb_strimwidth($search, 0, 80, '...');
									$op_result['type'] = $position ? $position : 'replace';
									$op_result['regex'] = false;

									if ($index !== '') {
										$indexes = explode(',', $index);
									} else {
										$indexes = array();
									}

									$i = 0;
									$lines = explode("\n", $modification[$key]);

									for ($line_id = 0; $line_id < count($lines); $line_id++) {
										$line = $lines[$line_id];

										$match = false;

										if (stripos($line, $search) !== false) {
											if (!$indexes) {
												$match = true;
											} elseif (in_array($i, $indexes)) {
												$match = true;
											}

											$i++;
										}

										if ($match) {
											switch ($position) {
												default:
												case 'replace':
													$new_lines = explode("\n", $add);

													if ($offset < 0) {
														array_splice($lines, $line_id + $offset, abs($offset) + 1, array(str_replace($search, $add, $line)));
														$line_id -= $offset;
													} else {
														array_splice($lines, $line_id, $offset + 1, array(str_replace($search, $add, $line)));
													}
													break;
												case 'before':
													$new_lines = explode("\n", $add);
													array_splice($lines, $line_id - $offset, 0, $new_lines);
													$line_id += count($new_lines);
													break;
												case 'after':
													$new_lines = explode("\n", $add);
													array_splice($lines, ($line_id + 1) + $offset, 0, $new_lines);
													$line_id += count($new_lines);
													break;
											}

											$status = true;
										}
									}

									$modification[$key] = implode("\n", $lines);
								} else {
									$search = trim($operation->getElementsByTagName('search')->item(0)->textContent);
									$limit = $operation->getElementsByTagName('search')->item(0)->getAttribute('limit');
									$replace = trim($operation->getElementsByTagName('add')->item(0)->textContent);

									if (!$limit) {
										$limit = -1;
									}

									$op_result['search'] = mb_strimwidth($search, 0, 80, '...');
									$op_result['type'] = 'regex';
									$op_result['regex'] = true;

									// Validate regex
									$regex_valid = @preg_match($search, '') !== false;

									if (!$regex_valid) {
										$op_result['status'] = 'error';
										$op_result['text'] = 'Invalid regex pattern';
										$file_entry['operations'][] = $op_result;
										$results['status'] = 'warning';
										continue;
									}

									$match = array();

									@preg_match_all($search, $modification[$key], $match, PREG_OFFSET_CAPTURE);

									if ($limit > 0) {
										$match[0] = array_slice($match[0], 0, $limit);
									}

									if ($match[0]) {
										$op_result['match_count'] = count($match[0]);
										$status = true;
									}

									$modification[$key] = @preg_replace($search, $replace, $modification[$key], $limit);
								}

								if (!$status) {
									if ($error_attr == 'abort') {
										$op_result['status'] = 'abort';
										$op_result['text'] = 'NOT FOUND - ABORTING modification';
										$file_entry['operations'][] = $op_result;

										if ($results['status'] != 'error') {
											$results['status'] = 'warning';
										}
										break 2;
									} elseif ($error_attr == 'skip') {
										$op_result['status'] = 'skip';
										$op_result['text'] = 'NOT FOUND - operation skipped';
										$results['status'] = 'warning';
									} else {
										$op_result['status'] = 'break';
										$op_result['text'] = 'NOT FOUND - breaking file operations';
										$file_entry['operations'][] = $op_result;
										$results['status'] = 'warning';
										break;
									}
								}

								$file_entry['operations'][] = $op_result;
								$file_entry['modified'] = true;
							}

							$results['files'][] = $file_entry;
						}
					} else {
						$results['messages'][] = array(
							'type' => 'warning',
							'text' => 'No files matched pattern: ' . $path_pattern
						);
						$results['status'] = 'warning';
					}
				}
			}
		}

		// Count modified files
		$modified_count = 0;

		foreach ($results['files'] as $f) {
			if ($f['modified']) {
				$modified_count++;
			}
		}

		if ($results['status'] == 'success') {
			$results['messages'][] = array(
				'type' => 'success',
				'text' => sprintf('Test passed: %d file(s) will be modified', $modified_count)
			);
		}

		return $results;
	}

	protected function decodePostFields() {
		$fields = array('name', 'code', 'author', 'version', 'link', 'xml');

		foreach ($fields as $field) {
			if (isset($this->request->post[$field])) {
				$this->request->post[$field] = html_entity_decode($this->request->post[$field], ENT_QUOTES, 'UTF-8');
			}
		}
	}

	protected function getList() {
		if (isset($this->request->get['sort'])) {
			$sort = $this->request->get['sort'];
		} else {
			$sort = 'name';
		}

		if (isset($this->request->get['order'])) {
			$order = $this->request->get['order'];
		} else {
			$order = 'ASC';
		}

		if (isset($this->request->get['page'])) {
			$page = (int)$this->request->get['page'];
		} else {
			$page = 1;
		}

		$url = '';

		if (isset($this->request->get['sort'])) {
			$url .= '&sort=' . $this->request->get['sort'];
		}

		if (isset($this->request->get['order'])) {
			$url .= '&order=' . $this->request->get['order'];
		}

		if (isset($this->request->get['page'])) {
			$url .= '&page=' . $this->request->get['page'];
		}

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('marketplace/modification', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['add'] = $this->url->link('marketplace/modification/add', 'user_token=' . $this->session->data['user_token'] . $url, true);
		$data['refresh'] = $this->url->link('marketplace/modification/refresh', 'user_token=' . $this->session->data['user_token'] . $url, true);
		$data['clear'] = $this->url->link('marketplace/modification/clear', 'user_token=' . $this->session->data['user_token'] . $url, true);
		$data['delete'] = $this->url->link('marketplace/modification/delete', 'user_token=' . $this->session->data['user_token'] . $url, true);

		$data['modifications'] = array();

		$filter_data = array(
			'sort'  => $sort,
			'order' => $order,
			'start' => ($page - 1) * $this->config->get('config_limit_admin'),
			'limit' => $this->config->get('config_limit_admin')
		);

		$modification_total = $this->model_setting_modification->getTotalModifications();

		$results = $this->model_setting_modification->getModifications($filter_data);

		foreach ($results as $result) {
			$data['modifications'][] = array(
				'modification_id' => $result['modification_id'],
				'name'            => $result['name'],
				'code'            => $result['code'],
				'author'          => $result['author'],
				'version'         => $result['version'],
				'status'          => $result['status'] ? $this->language->get('text_enabled') : $this->language->get('text_disabled'),
				'date_added'      => date($this->language->get('date_format_short'), strtotime($result['date_added'])),
				'link'            => $result['link'],
				'enable'          => $this->url->link('marketplace/modification/enable', 'user_token=' . $this->session->data['user_token'] . '&modification_id=' . $result['modification_id'], true),
				'disable'         => $this->url->link('marketplace/modification/disable', 'user_token=' . $this->session->data['user_token'] . '&modification_id=' . $result['modification_id'], true),
				'enabled'         => $result['status'],
				'edit'            => $this->url->link('marketplace/modification/edit', 'user_token=' . $this->session->data['user_token'] . '&modification_id=' . $result['modification_id'], true),
				'download'        => $this->url->link('marketplace/modification/download', 'user_token=' . $this->session->data['user_token'] . '&modification_id=' . $result['modification_id'], true),
				'test'            => $this->url->link('marketplace/modification/test', 'user_token=' . $this->session->data['user_token'] . '&modification_id=' . $result['modification_id'], true)
			);
		}

		$data['user_token'] = $this->session->data['user_token'];

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];

			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}

		if (isset($this->request->post['selected'])) {
			$data['selected'] = (array)$this->request->post['selected'];
		} else {
			$data['selected'] = array();
		}

		$url = '';

		if ($order == 'ASC') {
			$url .= '&order=DESC';
		} else {
			$url .= '&order=ASC';
		}

		if (isset($this->request->get['page'])) {
			$url .= '&page=' . $this->request->get['page'];
		}

		$data['sort_name'] = $this->url->link('marketplace/modification', 'user_token=' . $this->session->data['user_token'] . '&sort=name' . $url, true);
		$data['sort_author'] = $this->url->link('marketplace/modification', 'user_token=' . $this->session->data['user_token'] . '&sort=author' . $url, true);
		$data['sort_version'] = $this->url->link('marketplace/modification', 'user_token=' . $this->session->data['user_token'] . '&sort=version' . $url, true);
		$data['sort_status'] = $this->url->link('marketplace/modification', 'user_token=' . $this->session->data['user_token'] . '&sort=status' . $url, true);
		$data['sort_date_added'] = $this->url->link('marketplace/modification', 'user_token=' . $this->session->data['user_token'] . '&sort=date_added' . $url, true);

		$url = '';

		if (isset($this->request->get['sort'])) {
			$url .= '&sort=' . $this->request->get['sort'];
		}

		if (isset($this->request->get['order'])) {
			$url .= '&order=' . $this->request->get['order'];
		}

		$pagination = new Pagination();
		$pagination->total = $modification_total;
		$pagination->page = $page;
		$pagination->limit = $this->config->get('config_limit_admin');
		$pagination->url = $this->url->link('marketplace/modification', 'user_token=' . $this->session->data['user_token'] . $url . '&page={page}', true);

		$data['pagination'] = $pagination->render();

		$data['results'] = sprintf($this->language->get('text_pagination'), ($modification_total) ? (($page - 1) * $this->config->get('config_limit_admin')) + 1 : 0, ((($page - 1) * $this->config->get('config_limit_admin')) > ($modification_total - $this->config->get('config_limit_admin'))) ? $modification_total : ((($page - 1) * $this->config->get('config_limit_admin')) + $this->config->get('config_limit_admin')), $modification_total, ceil($modification_total / $this->config->get('config_limit_admin')));

		$data['sort'] = $sort;
		$data['order'] = $order;

		// Log
		$file = DIR_LOGS . 'ocmod.log';

		if (file_exists($file)) {
			$data['log'] = htmlentities(file_get_contents($file, FILE_USE_INCLUDE_PATH, null));
		} else {
			$data['log'] = '';
		}

		$data['clear_log'] = $this->url->link('marketplace/modification/clearlog', 'user_token=' . $this->session->data['user_token'], true);

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('marketplace/modification', $data));
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'marketplace/modification')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}
}
