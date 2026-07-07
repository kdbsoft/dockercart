<?php

declare(strict_types=1);

require_once DIR_SYSTEM . 'library/dockercart/install_helper.php';

class ControllerExtensionStore extends Controller {
	private function loadStore(): DockercartExtensionStore {
		require_once DIR_SYSTEM . 'library/dockercart/extension_store.php';

		return new DockercartExtensionStore($this->registry);
	}

	public function index() {
		$this->load->language('extension/store');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->document->addStyle('view/stylesheet/dockercart_store.css');

		if (!$this->user->hasPermission('access', 'extension/store')) {
			$this->response->redirect($this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true));

			return;
		}

		$data['user_token'] = $this->session->data['user_token'];
		$data['breadcrumbs'] = array();
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true),
		);
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/store', 'user_token=' . $this->session->data['user_token'], true),
		);

		if (!is_file(DIR_SYSTEM . 'library/dockercart/extension_store.php')) {
			$data['error'] = 'Extension Store library not found.';
			$data['categories_tree'] = array();
			$data['offers'] = array();
			$data['licenses'] = array();
			$data['selected_category'] = '';
			$data['header'] = $this->load->controller('common/header');
			$data['column_left'] = $this->load->controller('common/column_left');
			$data['footer'] = $this->load->controller('common/footer');
			$this->response->setOutput($this->load->view('extension/store', $data));

			return;
		}

		$store = $this->loadStore();

		$yml = null;

		try {
			$lang = $store->resolveLanguage();
			$yml = $store->getYml($lang);
		} catch (Exception $e) {
			$data['error'] = $this->language->get('error_fetch');
		}

		$categories_tree = array();

		if ($yml !== null) {
			$categories_tree = $store->buildCategoriesTree($yml);
		}

		// Count offers per category from raw YML
		$category_counts = array();

		if ($yml !== null) {
			foreach ($yml->shop->offers->offer as $offer) {
				$cat_id = (string) $offer->categoryId;
				$category_counts[$cat_id] = ($category_counts[$cat_id] ?? 0) + 1;
			}
		}

		// Recursively enrich categories with cumulative counts
		$enrichCount = function (&$cats) use ($category_counts, &$enrichCount) {
			foreach ($cats as &$cat) {
				$count = $category_counts[$cat['id']] ?? 0;

				if (!empty($cat['children'])) {
					$enrichCount($cat['children']);

					foreach ($cat['children'] as $child) {
						$count += $child['count'];
					}
				}

				$cat['count'] = $count;
			}
			unset($cat);
		};

		$enrichCount($categories_tree);

		$selected_category = $this->request->get['category_id'] ?? '';
		$selected_category = (string) $selected_category;
		$offer_category_ids = null;

		if ($yml !== null && $selected_category !== '') {
			$offer_category_ids = $store->getCategoryDescendants($yml, $selected_category);
		}

		$offers = array();

		if ($yml !== null) {
			$offers = $store->getMergedOffers($yml, $offer_category_ids);
		}

		$data['categories_tree'] = $categories_tree;
		$data['offers'] = $offers;
		$data['selected_category'] = $selected_category;
		$data['licenses'] = $this->getLicensesData($yml);

		$data['detail_url'] = $this->url->link('extension/store/detail', 'user_token=' . $this->session->data['user_token'], true);
		$data['refresh_url'] = $this->url->link('extension/store/refresh', 'user_token=' . $this->session->data['user_token'], true);
		$data['install_url'] = $this->url->link('extension/store/install', 'user_token=' . $this->session->data['user_token'], true);
		$data['update_url'] = $this->url->link('extension/store/update', 'user_token=' . $this->session->data['user_token'], true);
		$data['activate_key_url'] = $this->url->link('extension/store/activateKey', 'user_token=' . $this->session->data['user_token'], true);
		$data['set_license_key_url'] = $this->url->link('extension/store/setLicenseKey', 'user_token=' . $this->session->data['user_token'], true);
		$data['uninstall_url'] = $this->url->link('extension/store/uninstall', 'user_token=' . $this->session->data['user_token'], true);
		$data['base_url'] = $this->url->link('extension/store', 'user_token=' . $this->session->data['user_token'], true);

		$data['total_offers'] = count($offers);
		$data['total_categories_count'] = array_sum($category_counts);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'], true),
		);

		if (isset($this->session->data['error'])) {
			$data['error_warning'] = $this->session->data['error'];
			unset($this->session->data['error']);
		} else {
			$data['error_warning'] = '';
		}

		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];
			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/store', $data));
	}

	public function detail() {
		$this->load->language('extension/store');
		$this->response->addHeader('Content-Type: application/json');

		$json = array();

		if ($this->request->server['REQUEST_METHOD'] !== 'GET') {
			$json['error'] = 'Invalid request method';
			$this->response->setOutput(json_encode($json));

			return;
		}

		if (!$this->user->hasPermission('access', 'extension/store')) {
			$json['error'] = $this->language->get('error_permission');
			$this->response->setOutput(json_encode($json));

			return;
		}

		$sku = $this->request->get['sku'] ?? '';

		if (empty($sku)) {
			$json['error'] = 'Missing SKU';
			$this->response->setOutput(json_encode($json));

			return;
		}

		if (!is_file(DIR_SYSTEM . 'library/dockercart/extension_store.php')) {
			$json['error'] = 'Library not found';
			$this->response->setOutput(json_encode($json));

			return;
		}

		try {
			$store = $this->loadStore();
			$yml = $store->getYml($store->resolveLanguage());

			foreach ($yml->shop->offers->offer as $offer) {
				if ((string) $offer['id'] === $sku) {
					$pictures = array();

					foreach ($offer->picture as $pic) {
						$pictures[] = (string) $pic;
					}

					$params = array();
					$yml_changelog = '';

					foreach ($offer->param as $param) {
						$code = (string) $param['code'];

						if ($code === 'changelog') {
							$yml_changelog = strip_tags((string) $param, '<p><br><strong><em><b><i><u><ul><ol><li><a><code><pre><blockquote><h1><h2><h3><h4>');
							continue;
						}

						$params[] = array(
							'name' => (string) $param['name'],
							'code' => $code,
							'value' => (string) $param,
						);
					}

					$name = (string) $offer->name;

					if (empty($name)) {
						$name = (string) $offer->model;
					}

					if (empty($name)) {
						$name = 'Extension #' . $sku;
					}

				$description = '';

				if (isset($offer->description)) {
					$description = strip_tags((string) $offer->description, '<p><br><strong><em><b><i><u><ul><ol><li><a><code><pre><blockquote><h1><h2><h3><h4><h5><h6><hr><table><thead><tbody><tr><th><td><span><div><img><figure><figcaption>');
				}

					$buy_url = '';

					if (!empty((string) $offer->url)) {
						$raw_url = (string) $offer->url;

						if (preg_match('#^https?://#', $raw_url)) {
							$buy_url = $raw_url;
						} else {
							$buy_url = DockercartExtensionStore::STORE_DOMAIN . '/' . ltrim($raw_url, '/');
						}
					}

					if (empty($buy_url)) {
						$buy_url = DockercartExtensionStore::STORE_DOMAIN;
					}

					$yml_version = '';

					foreach ($offer->param as $param) {
						if ((string) $param['code'] === 'version') {
							$yml_version = (string) $param;
							break;
						}
					}

					$offer_data = array(
						'id' => $sku,
						'sku' => $sku,
						'name' => $name,
						'description' => $description,
						'price' => (float) $offer->price,
						'currency' => (string) $offer->currencyId,
						'pictures' => $pictures,
						'params' => $params,
						'changelog' => $yml_changelog,
						'buy_url' => $buy_url,
						'available' => ((string) $offer['available']) === 'true',
						'version' => $yml_version,
						'installed_version' => null,
						'is_licensed' => false,
						'license_key' => null,
						'license_status' => null,
						'state' => 'buy',
						'update_available' => false,
					);

					$licensed_modules = $store->getLicensedModules();

					foreach ($licensed_modules as $mod) {
						if (!empty($mod['sku']) && $mod['sku'] === $sku) {
							$offer_data['is_licensed'] = true;
							$offer_data['license_key'] = $mod['licenseKey'] ?? null;
							$offer_data['license_status'] = $mod['licenseStatus'] ?? null;
							break;
						}
					}

					$meta = $store->getInstalledMeta($sku);

					if ($meta) {
						$offer_data['installed_version'] = $meta['installed_version'];
					}

					if ($offer_data['installed_version'] && $offer_data['version']) {
						$offer_data['update_available'] = version_compare($offer_data['version'], $offer_data['installed_version'], '>');
					}

					$offer_data['state'] = $store->resolveState(array_merge($offer_data, [
						'update_available' => $offer_data['update_available'],
						'license_status' => $offer_data['license_status'],
					]));

					$versions = $store->getModuleVersions($sku);

					if (empty($versions)) {
						$versions = array();

						if ($offer_data['version']) {
							$versions[] = array(
								'version' => $offer_data['version'],
								'changelog' => $yml_changelog,
								'isCurrent' => true,
							);
						}
					}

					$offer_data['versions'] = $versions;

					if ($offer_data['installed_version'] && $offer_data['update_available']) {
						$offer_data['update_changelog'] = $store->getChangelogHtml($versions, $offer_data['installed_version']);
					}

					$json = $offer_data;

					break;
				}
			}

			if (!isset($json['id'])) {
				$json['error'] = 'Extension not found';
			}
		} catch (Exception $e) {
			$this->log->write('Extension Store detail error: ' . $e->getMessage());
			$json['error'] = $this->language->get('text_error_detail');
		}

		$this->response->setOutput(json_encode($json));
	}

	public function install() {
		$this->load->language('extension/store');
		$this->response->addHeader('Content-Type: application/json');

		$json = array();

		if ($this->request->server['REQUEST_METHOD'] !== 'POST') {
			$json['error'] = 'Invalid request method';
			$this->response->setOutput(json_encode($json));

			return;
		}

		if (!$this->user->hasPermission('modify', 'extension/store')) {
			$json['error'] = $this->language->get('error_permission');
			$this->response->setOutput(json_encode($json));

			return;
		}

		$sku = $this->request->post['sku'] ?? '';

		if (empty($sku)) {
			$json['error'] = 'Missing SKU';
			$this->response->setOutput(json_encode($json));

			return;
		}

		$license_key = $this->request->post['license_key'] ?? '';

		try {
			$store = $this->loadStore();

			$meta = $store->getInstalledMeta($sku);

			if ($meta) {
				$json['error'] = $this->language->get('error_already_installed');
				$this->response->setOutput(json_encode($json));

				return;
			}

			$versions = $store->getModuleVersions($sku);

			if (empty($versions)) {
				$json['error'] = 'No versions available for this extension';
				$this->response->setOutput(json_encode($json));

				return;
			}

			$current_version = null;
			$version_id = null;

			foreach ($versions as $v) {
				if (!empty($v['isCurrent'])) {
					$current_version = $v;
					$version_id = $v['id'] ?? null;
					break;
				}
			}

			if ($current_version === null) {
				$current_version = $versions[0];
				$version_id = $versions[0]['id'] ?? null;
			}

			if ($version_id === null) {
				$json['error'] = 'No downloadable version found';
				$this->response->setOutput(json_encode($json));

				return;
			}

			$download_data = $store->getDownloadUrl($sku, $version_id, $license_key);

			if ($download_data === null || empty($download_data['downloadUrl'])) {
				$json['error'] = $this->language->get('error_download_failed');
				$this->response->setOutput(json_encode($json));

				return;
			}

			if (!empty($download_data['licenseKey'])) {
				$license_key = $download_data['licenseKey'];
			}

			$zip_content = $this->downloadFile($download_data['downloadUrl']);

			if ($zip_content === false) {
				$json['error'] = $this->language->get('error_download_failed');
				$this->response->setOutput(json_encode($json));

				return;
			}

			$code = 'dockercart_' . preg_replace('/^dockercart_/', '', $sku);

			if (!preg_match('/^[a-zA-Z0-9_]+$/', $code)) {
				throw new \Exception('Invalid extension code');
			}

			$token = token(10);
			$tmp_file = DIR_UPLOAD . $token . '.tmp';

			file_put_contents($tmp_file, $zip_content);

			$this->session->data['store_install_token'] = $token;

			$this->load->model('setting/extension');

			$extension_install_id = $this->model_setting_extension->addExtensionInstall($sku . '_' . $current_version['version'] . '.ocmod.zip');

			try {
				$install_result = $this->runOcmodInstall($token, $extension_install_id);

				if (!empty($install_result['error'])) {
					$this->model_setting_extension->deleteExtensionInstall($extension_install_id);
					$json['error'] = $install_result['error'];
					$this->response->setOutput(json_encode($json));

					return;
				}
			} catch (\Throwable $e) {
				$this->model_setting_extension->deleteExtensionInstall($extension_install_id);
				throw $e;
			}

			$this->clearModificationCache();

			// Re-check installation status (TOCTOU guard)
			if ($store->getInstalledMeta($sku)) {
				$this->model_setting_extension->deleteExtensionInstall($extension_install_id);
				$json['error'] = $this->language->get('error_already_installed');
				$this->response->setOutput(json_encode($json));

				return;
			}

			$this->model_setting_extension->install('module', $code);

			$this->load->model('user/user_group');
			$this->model_user_user_group->addPermission($this->user->getGroupId(), 'access', 'extension/module/' . $code);
			$this->model_user_user_group->addPermission($this->user->getGroupId(), 'modify', 'extension/module/' . $code);

			$this->load->controller('extension/module/' . $code . '/install');

			$module_info = $store->getModuleInfo($sku);

			$store->setInstalledMeta(
				$code, $sku, $current_version['version'], 'store', $extension_install_id,
				name:        $module_info['name'] ?? null,
				author:      $module_info['author'] ?? null,
				author_email: $module_info['authorEmail'] ?? null,
				license_type: $module_info['licenseType'] ?? null,
				link:        $module_info['link'] ?? null
			);

			$this->load->library('dockercart/licensing');
			$licensing = new DockercartLicensing($this->registry);

			if (!empty($license_key)) {
				$licensing->setLicenseKey($code, $sku, $license_key);
				$licensing->activate($code, $license_key);
			}

			$this->db->query("COMMIT");

			$json['success'] = true;
			$json['message'] = sprintf($this->language->get('text_install_success'), $current_version['version']);
		} catch (\Throwable $e) {
			$this->db->query("ROLLBACK");
			$this->log->write('Extension Store install EXCEPTION: ' . get_class($e) . ' ' . $e->getMessage() . ' file=' . $e->getFile() . ' line=' . $e->getLine());
			$json['error'] = $this->language->get('error_download_failed');
		}

		$this->response->setOutput(json_encode($json));
	}

	public function update() {
		$this->load->language('extension/store');
		$this->response->addHeader('Content-Type: application/json');

		$json = array();

		if ($this->request->server['REQUEST_METHOD'] !== 'POST') {
			$json['error'] = 'Invalid request method';
			$this->response->setOutput(json_encode($json));

			return;
		}

		if (!$this->user->hasPermission('modify', 'extension/store')) {
			$json['error'] = $this->language->get('error_permission');
			$this->response->setOutput(json_encode($json));

			return;
		}

		$sku = $this->request->post['sku'] ?? '';

		if (empty($sku)) {
			$json['error'] = 'Missing SKU';
			$this->response->setOutput(json_encode($json));

			return;
		}

		$license_key = $this->request->post['license_key'] ?? '';

		try {
			$this->db->query("START TRANSACTION");

			$store = $this->loadStore();

			$meta = $store->getInstalledMeta($sku);

			if (!$meta) {
				$this->db->query("ROLLBACK");
				$json['error'] = $this->language->get('error_not_installed');
				$this->response->setOutput(json_encode($json));

				return;
			}

			$versions = $store->getModuleVersions($sku);

			if (empty($versions)) {
				$json['error'] = 'No versions available';
				$this->response->setOutput(json_encode($json));

				return;
			}

			$current_version = null;
			$version_id = null;

			foreach ($versions as $v) {
				if (!empty($v['isCurrent'])) {
					$current_version = $v;
					$version_id = $v['id'] ?? null;
					break;
				}
			}

			if ($current_version === null) {
				$current_version = $versions[0];
				$version_id = $versions[0]['id'] ?? null;
			}

			if (!version_compare($current_version['version'], $meta['installed_version'], '>')) {
				$json['error'] = $this->language->get('error_up_to_date');
				$this->response->setOutput(json_encode($json));

				return;
			}

			if ($version_id === null) {
				$json['error'] = 'No downloadable version found';
				$this->response->setOutput(json_encode($json));

				return;
			}

			if (empty($license_key)) {
				$this->load->library('dockercart/licensing');
				$licensing = new DockercartLicensing($this->registry);
				$license = $licensing->getLicense($meta['code']);
				$license_key = $license['license_key'] ?? '';

				if (!empty($license_key)) {
					$validation = $licensing->validate($meta['code']);
					if (empty($validation['valid'])) {
						$json['error'] = $this->language->get('error_license_expired');
						$this->response->setOutput(json_encode($json));

						return;
					}
				}
			}

			$download_data = $store->getDownloadUrl($sku, $version_id, $license_key);

			if ($download_data === null || empty($download_data['downloadUrl'])) {
				$json['error'] = $this->language->get('error_download_failed');
				$this->response->setOutput(json_encode($json));

				return;
			}

			$zip_content = $this->downloadFile($download_data['downloadUrl']);

			if ($zip_content === false) {
				$json['error'] = $this->language->get('error_download_failed');
				$this->response->setOutput(json_encode($json));

				return;
			}

			$token = token(10);
			$tmp_file = DIR_UPLOAD . $token . '.tmp';

			file_put_contents($tmp_file, $zip_content);

			$this->load->model('setting/extension');

			$extension_install_id = $this->model_setting_extension->addExtensionInstall($sku . '_' . $current_version['version'] . '_update.ocmod.zip');

			try {
				$install_result = $this->runOcmodInstall($token, $extension_install_id);

				if (!empty($install_result['error'])) {
					$this->model_setting_extension->deleteExtensionInstall($extension_install_id);
					$json['error'] = $install_result['error'];
					$this->response->setOutput(json_encode($json));

					return;
				}
			} catch (\Throwable $e) {
				$this->model_setting_extension->deleteExtensionInstall($extension_install_id);
				throw $e;
			}

			$this->clearModificationCache();

			$code = $meta['code'];
			$from_version = $meta['installed_version'];

			$update_result = $this->load->controller('extension/module/' . $code . '/update', [
				'from' => $from_version,
				'to' => $current_version['version'],
				'token' => $token,
			]);

			// Consolidate: replace the previous install record with this update's record.
			// processInstallXml already removed the old modification row (by code),
			// and the new files have overwritten the old ones on disk. We only need to
			// drop the stale extension_install + extension_path rows and repoint the meta.
			$old_extension_install_id = (int)($meta['extension_install_id'] ?? 0);

			if ($old_extension_install_id && $old_extension_install_id !== $extension_install_id) {
				$this->model_setting_extension->deleteExtensionPathsByExtensionInstallId($old_extension_install_id);
				$this->model_setting_extension->deleteExtensionInstall($old_extension_install_id);

				$store->updateInstalledMetaExtensionInstallId($code, $extension_install_id);
			}

			$store->updateInstalledMetaVersion($code, $current_version['version']);

			$this->db->query("COMMIT");

			$json['success'] = true;
			$json['message'] = sprintf($this->language->get('text_update_success'), $from_version, $current_version['version']);
			$json['new_version'] = $current_version['version'];
		} catch (Exception $e) {
			$this->db->query("ROLLBACK");
			$this->log->write('Extension Store update error: ' . $e->getMessage());
			$json['error'] = $this->language->get('error_download_failed');
		}

		$this->response->setOutput(json_encode($json));
	}

	public function uninstall() {
		$this->load->language('extension/store');
		$this->response->addHeader('Content-Type: application/json');

		$json = array();

		if ($this->request->server['REQUEST_METHOD'] !== 'POST') {
			$json['error'] = 'Invalid request method';
			$this->response->setOutput(json_encode($json));

			return;
		}

		if (!$this->user->hasPermission('modify', 'extension/store')) {
			$json['error'] = $this->language->get('error_permission');
			$this->response->setOutput(json_encode($json));

			return;
		}

		$sku = $this->request->post['sku'] ?? '';

		if (empty($sku)) {
			$json['error'] = 'Missing SKU';
			$this->response->setOutput(json_encode($json));

			return;
		}

		try {
			$this->db->query("START TRANSACTION");

			$store = $this->loadStore();

			$meta = $store->getInstalledMeta($sku);

			if (!$meta) {
				$json['error'] = $this->language->get('error_not_installed');
				$this->response->setOutput(json_encode($json));

				return;
			}

			$code = $meta['code'];

			$this->load->model('setting/extension');
			$this->load->model('setting/module');

			$extension_install_id = $meta['extension_install_id'];

			// Call module uninstall script
			$this->load->controller('extension/module/' . $code . '/uninstall');

			// Remove files
			if ($extension_install_id) {
				$paths = $this->model_setting_extension->getExtensionPathsByExtensionInstallId($extension_install_id);

				DockercartInstallHelper::syncGitExclude(array_column($paths, 'path'), 'remove');

				rsort($paths);

				foreach ($paths as $result) {
					$source = '';

					if (substr($result['path'], 0, 5) == 'admin') {
						$source = DIR_APPLICATION . substr($result['path'], 6);
					} elseif (substr($result['path'], 0, 7) == 'catalog') {
						$source = DIR_CATALOG . substr($result['path'], 8);
					} elseif (substr($result['path'], 0, 5) == 'image') {
						$source = DIR_IMAGE . substr($result['path'], 6);
					} elseif (substr($result['path'], 0, 6) == 'system') {
						$source = DIR_SYSTEM . substr($result['path'], 7);
					}

					if ($source) {
						if (is_file($source)) {
							if (!unlink($source)) {
								$this->log->write('Extension Store uninstall: failed to unlink ' . $source);
							}
						} elseif (is_dir($source)) {
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
								if (is_file($file)) {
									if (!unlink($file)) {
										$this->log->write('Extension Store uninstall: failed to unlink ' . $file);
									}
								} elseif (is_dir($file) && DockercartInstallHelper::isDirEmpty($file)) {
									if (!rmdir($file)) {
										$this->log->write('Extension Store uninstall: failed to rmdir ' . $file);
									}
								}
							}

							if (DockercartInstallHelper::isDirEmpty($source)) {
								if (!rmdir($source)) {
									$this->log->write('Extension Store uninstall: failed to rmdir ' . $source);
								}
							}
						}
					}

					$this->model_setting_extension->deleteExtensionPath($result['extension_path_id']);
				}

				// Remove modification
				$this->load->model('setting/modification');
				$this->model_setting_modification->deleteModificationsByExtensionInstallId($extension_install_id);

				// Remove install record
				$this->model_setting_extension->deleteExtensionInstall($extension_install_id);
			}

			// Uninstall extension
			$this->model_setting_extension->uninstall('module', $code);
			$this->model_setting_module->deleteModulesByCode($code);

			// Remove permissions
			$this->load->model('user/user_group');
			$this->model_user_user_group->removePermissions('extension/module/' . $code);

			// Remove meta
			$store->removeInstalledMeta($code);

			// Remove license
			if (is_file(DIR_SYSTEM . 'library/dockercart/licensing.php')) {
				require_once DIR_SYSTEM . 'library/dockercart/licensing.php';
				$licensing = new DockercartLicensing($this->registry);
				$licensing->removeLicense($code);
			}

			$this->clearModificationCache();

			$this->db->query("COMMIT");

			$json['success'] = true;
			$json['message'] = $this->language->get('text_uninstall_success');
		} catch (Exception $e) {
			$this->db->query("ROLLBACK");
			$this->log->write('Extension Store uninstall error: ' . $e->getMessage());
			$json['error'] = $this->language->get('text_error');
		}

		$this->response->setOutput(json_encode($json));
	}

	public function activateKey() {
		$this->load->language('extension/store');
		$this->response->addHeader('Content-Type: application/json');

		$json = array();

		if ($this->request->server['REQUEST_METHOD'] !== 'POST') {
			$json['error'] = 'Invalid request method';
			$this->response->setOutput(json_encode($json));

			return;
		}

		if (!$this->user->hasPermission('modify', 'extension/store')) {
			$json['error'] = $this->language->get('error_permission');
			$this->response->setOutput(json_encode($json));

			return;
		}

		$sku = $this->request->post['sku'] ?? '';
		$license_key = $this->request->post['license_key'] ?? '';

		if (empty($sku) || empty($license_key)) {
			$json['error'] = 'SKU and license key are required';
			$this->response->setOutput(json_encode($json));

			return;
		}

		if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $sku)) {
			$json['error'] = 'Invalid SKU format';
			$this->response->setOutput(json_encode($json));

			return;
		}

		if (strlen($license_key) < 10 || strlen($license_key) > 255) {
			$json['error'] = 'Invalid license key format';
			$this->response->setOutput(json_encode($json));

			return;
		}

		if (!is_file(DIR_SYSTEM . 'library/dockercart/licensing.php')) {
			$json['error'] = 'Licensing library not found';
			$this->response->setOutput(json_encode($json));

			return;
		}

		require_once DIR_SYSTEM . 'library/dockercart/licensing.php';

		$code = 'dockercart_' . preg_replace('/^dockercart_/', '', $sku);

		// Check if this key is already used by another module
		$licensing = new DockercartLicensing($this->registry);
		$existing_key = $licensing->getLicenseByKey($license_key);

		if ($existing_key && $existing_key['module_code'] !== $code) {
			$json['error'] = 'This license key is already assigned to another module';
			$this->response->setOutput(json_encode($json));

			return;
		}
		$licensing = new DockercartLicensing($this->registry);
		$licensing->setLicenseKey($code, $sku, $license_key);

		$result = $licensing->activate($code, $license_key);

		if (!empty($result['success'])) {
			$validate_result = $licensing->validate($code, true);

			$json['success'] = true;
			$json['message'] = $this->language->get('text_license_activated');
			$json['valid'] = !empty($validate_result['valid']);
		} else {
			$json['error'] = $result['error'] ?? $this->language->get('error_license_activation');
		}

		$this->response->setOutput(json_encode($json));
	}

	public function setLicenseKey() {
		$this->load->language('extension/store');
		$this->response->addHeader('Content-Type: application/json');

		$json = array();

		if ($this->request->server['REQUEST_METHOD'] !== 'POST') {
			$json['error'] = 'Invalid request method';
			$this->response->setOutput(json_encode($json));

			return;
		}

		if (!$this->user->hasPermission('modify', 'extension/store')) {
			$json['error'] = $this->language->get('error_permission');
			$this->response->setOutput(json_encode($json));

			return;
		}

		$module_code = $this->request->post['module_code'] ?? '';
		$license_key = $this->request->post['license_key'] ?? '';

		if (empty($module_code) || empty($license_key)) {
			$json['error'] = 'Module code and license key are required';
			$this->response->setOutput(json_encode($json));

			return;
		}

		if (!preg_match('/^[a-zA-Z0-9_]+$/', $module_code)) {
			$json['error'] = 'Invalid module code format';
			$this->response->setOutput(json_encode($json));

			return;
		}

		if (strlen($license_key) < 10 || strlen($license_key) > 255) {
			$json['error'] = 'Invalid license key format';
			$this->response->setOutput(json_encode($json));

			return;
		}

		if (!is_file(DIR_SYSTEM . 'library/dockercart/licensing.php')) {
			$json['error'] = 'Licensing library not found';
			$this->response->setOutput(json_encode($json));

			return;
		}

		require_once DIR_SYSTEM . 'library/dockercart/licensing.php';
		$licensing = new DockercartLicensing($this->registry);
		$licensing->setLicenseKey($module_code, '', $license_key);

		$json['success'] = true;
		$json['message'] = $this->language->get('text_license_key_updated');

		$this->response->setOutput(json_encode($json));
	}

	public function refresh() {
		$this->load->language('extension/store');

		if (!$this->user->hasPermission('modify', 'extension/store')) {
			$this->session->data['error'] = $this->language->get('error_permission');
			$this->response->redirect($this->url->link('extension/store', 'user_token=' . $this->session->data['user_token'], true));

			return;
		}

		if (!is_file(DIR_SYSTEM . 'library/dockercart/extension_store.php')) {
			$this->response->redirect($this->url->link('extension/store', 'user_token=' . $this->session->data['user_token'], true));

			return;
		}

		$store = $this->loadStore();
		$store->clearCache();

		if (is_file(DIR_SYSTEM . 'library/dockercart/licensing.php')) {
			require_once DIR_SYSTEM . 'library/dockercart/licensing.php';
			$licensing = new DockercartLicensing($this->registry);
			$licensing->autoPopulate();
		}

		$this->session->data['success'] = $this->language->get('text_cache_cleared');
		$this->response->redirect($this->url->link('extension/store', 'user_token=' . $this->session->data['user_token'], true));
	}

	private function getLicensesData($yml = null): array {
		if (!is_file(DIR_SYSTEM . 'library/dockercart/licensing.php')) {
			return array();
		}

		require_once DIR_SYSTEM . 'library/dockercart/licensing.php';
		$licensing = new DockercartLicensing($this->registry);

		$licenses = $licensing->getAllLicenses();

		$name_map = array();
		$version_map = array();

		if ($yml !== null) {
			foreach ($yml->shop->offers->offer as $offer) {
				$sku = (string)$offer['id'];
				$name = (string)$offer->name;

				if (empty($name)) {
					$name = (string)$offer->model;
				}

				if (!empty($sku) && !empty($name)) {
					$name_map[$sku] = $name;
				}

				if (!empty($sku)) {
					$yml_version = '';
					foreach ($offer->param as $param) {
						if ((string)$param['code'] === 'version') {
							$yml_version = (string)$param;
							break;
						}
					}
					if ($yml_version !== '') {
						$version_map[$sku] = $yml_version;
					}
				}
			}
		}

		foreach ($licenses as &$license) {
			$license['is_installed'] = false;
			$license['installed_version'] = null;
			$license['latest_version'] = null;
			$license['update_available'] = false;

			if (!empty($license['sku']) && isset($name_map[$license['sku']])) {
				$license['ext_name'] = $name_map[$license['sku']];
				$license['ext_sku'] = $license['sku'];
			}

			if (!empty($license['sku'])) {
				$meta = $this->db->query(
					"SELECT `name`, `sku`, `installed_version` FROM `" . DB_PREFIX . "dockercart_extension_meta`
					 WHERE `sku` = '" . $this->db->escape($license['sku']) . "'
					 LIMIT 1"
				);
				if ($meta->num_rows) {
					$license['is_installed'] = true;
					$license['installed_version'] = $meta->row['installed_version'];

					if (empty($license['ext_name'])) {
						$license['ext_name'] = $meta->row['name'];
						$license['ext_sku'] = $meta->row['sku'];
					}
				}

				if (isset($version_map[$license['sku']])) {
					$license['latest_version'] = $version_map[$license['sku']];
				}
			}

			if ($license['installed_version'] && $license['latest_version']) {
				if (version_compare($license['latest_version'], $license['installed_version'], '>')) {
					$license['update_available'] = true;
				}
			}

			if (empty($license['ext_name'])) {
				$license['ext_name'] = $license['module_code'];
			}
			if (empty($license['ext_sku'])) {
				$license['ext_sku'] = $license['sku'] ?: $license['module_code'];
			}
		}
		unset($license);

		return $licenses;
	}

	private function downloadFile(string $url) {
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 300);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_ENCODING, '');
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

		$gateway_ip = @gethostbyname('host.docker.internal');

		if ($gateway_ip !== 'host.docker.internal') {
			$parsed = parse_url($url);
			$port = $parsed['port'] ?? (strtolower($parsed['scheme'] ?? 'http') === 'https' ? 443 : 80);
			curl_setopt($ch, CURLOPT_RESOLVE, array(
				$parsed['host'] . ':' . $port . ':' . $gateway_ip
			));
		}

		$response = curl_exec($ch);
		$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$curl_error = curl_error($ch);
		curl_close($ch);

		if ($http_code !== 200 || $response === false) {
			return false;
		}

		return $response;
	}

	private function runOcmodInstall(string $token, int $extension_install_id): array {
		$this->session->data['install_token'] = $token;

		$tmp_dir = DIR_UPLOAD . 'tmp-' . $token;

		$zip = new \ZipArchive();

		if ($zip->open(DIR_UPLOAD . $token . '.tmp') !== true) {
			return array('error' => 'Failed to open zip file');
		}

		if (!is_dir($tmp_dir)) {
			mkdir($tmp_dir, 0755, true);
		}

		$zip->extractTo($tmp_dir);
		$zip->close();

		unlink(DIR_UPLOAD . $token . '.tmp');

		$upload_dir = $tmp_dir . '/upload';

		if (is_dir($upload_dir)) {
			$not_writable = DockercartInstallHelper::checkWritable($upload_dir);

			if (!empty($not_writable)) {
				$this->cleanupTemp($tmp_dir);
				return array('error' => sprintf($this->language->get('error_writable'), implode('<br>', $not_writable)));
			}

			$this->moveFiles($upload_dir, $extension_install_id);

			$this->load->model('setting/extension');
			$paths = $this->model_setting_extension->getExtensionPathsByExtensionInstallId($extension_install_id);
			DockercartInstallHelper::syncGitExclude(array_column($paths, 'path'), 'add');
		}

		$install_xml = $tmp_dir . '/install.xml';

		if (file_exists($install_xml)) {
			$this->processInstallXml($install_xml, $extension_install_id);
		}

		$this->cleanupTemp($tmp_dir);

		return array('success' => true);
	}

	private function moveFiles(string $source, int $extension_install_id): void {
		$this->load->model('setting/extension');

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
			\RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ($iterator as $item) {
			$relative = substr($item->getPathname(), strlen($source) + 1);

			if ($item->isDir()) {
				continue;
			}

			$parts = explode('/', $relative, 2);

			if (count($parts) < 2) {
				continue;
			}

			$prefix = $parts[0];
			$sub_path = $parts[1];

			$dest = $this->resolveDestination($prefix, $sub_path);

			if ($dest === null) {
				continue;
			}

			$dest_dir = dirname($dest);

			if (!is_dir($dest_dir)) {
				mkdir($dest_dir, 0755, true);
			}

			if (!copy($item->getPathname(), $dest)) {
				continue;
			}

			unlink($item->getPathname());

			$this->model_setting_extension->addExtensionPath($extension_install_id, $prefix . '/' . $sub_path);
		}
	}

	private function resolveDestination(string $prefix, string $sub_path): ?string {
		$allowed = [
			'admin' => DIR_APPLICATION,
			'catalog' => DIR_CATALOG,
			'image' => DIR_IMAGE,
			'system' => DIR_SYSTEM,
		];

		if (!isset($allowed[$prefix])) {
			return null;
		}

		if (preg_match('#(?:^|/)\.\.(?:/|$)#', $sub_path)) {
			return null;
		}

		return $allowed[$prefix] . $sub_path;
	}

	private function processInstallXml(string $xml_path, int $extension_install_id): void {
		$this->load->model('setting/modification');

		$xml_content = file_get_contents($xml_path);

		if ($xml_content === false || $xml_content === '') {
			return;
		}

		$xml = new \DOMDocument();
		$xml->loadXML($xml_content, LIBXML_NONET);

		$code = '';
		$name = '';
		$author = '';
		$version = '';
		$link = '';

		$name_nodes = $xml->getElementsByTagName('name');

		if ($name_nodes->length > 0) {
			$name = $name_nodes->item(0)->nodeValue;
		}

		$code_nodes = $xml->getElementsByTagName('code');

		if ($code_nodes->length > 0) {
			$code = $code_nodes->item(0)->nodeValue;
		}

		$author_nodes = $xml->getElementsByTagName('author');

		if ($author_nodes->length > 0) {
			$author = $author_nodes->item(0)->nodeValue;
		}

		$version_nodes = $xml->getElementsByTagName('version');

		if ($version_nodes->length > 0) {
			$version = $version_nodes->item(0)->nodeValue;
		}

		$link_nodes = $xml->getElementsByTagName('link');

		if ($link_nodes->length > 0) {
			$link = $link_nodes->item(0)->nodeValue;
		}

		if (!empty($code)) {
			$this->db->query("DELETE FROM `" . DB_PREFIX . "modification` WHERE `code` = '" . $this->db->escape($code) . "'");
		}

		$this->db->query("INSERT INTO `" . DB_PREFIX . "modification` SET `extension_install_id` = '" . (int)$extension_install_id . "', `name` = '" . $this->db->escape($name) . "', `code` = '" . $this->db->escape($code) . "', `author` = '" . $this->db->escape($author) . "', `version` = '" . $this->db->escape($version) . "', `link` = '" . $this->db->escape($link) . "', `xml` = '" . $this->db->escape(file_get_contents($xml_path)) . "', `status` = 1, `date_added` = NOW()");
	}

	private function cleanupTemp(string $dir): void {
		$this->recursiveRmdir($dir);
	}

	private function recursiveRmdir(string $dir): void {
		if (!is_dir($dir)) {
			return;
		}

		$items = array_diff(scandir($dir), array('.', '..'));

		foreach ($items as $item) {
			$path = $dir . '/' . $item;

			if (is_dir($path)) {
				$this->recursiveRmdir($path);
			} else {
				unlink($path);
			}
		}

		rmdir($dir);
	}

	private function clearModificationCache(): void {
		$files = glob(DIR_MODIFICATION . '*');

		foreach ($files as $file) {
			if (is_file($file) && basename($file) !== 'index.html') {
				unlink($file);
			}
		}
	}
}
