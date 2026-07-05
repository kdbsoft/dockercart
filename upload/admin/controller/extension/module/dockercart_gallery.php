<?php
declare(strict_types=1);

class ControllerExtensionModuleDockercartGallery extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('extension/module/dockercart_gallery');
		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');
		$this->load->model('extension/module/dockercart_gallery');
		$this->load->model('tool/image');

		if ($this->request->server['REQUEST_METHOD'] === 'POST' && $this->validateModule()) {
			$this->model_setting_setting->editSetting('module_dockercart_gallery', $this->request->post);

			$this->session->data['success'] = $this->language->get('text_success');
			$this->response->redirect($this->url->link('extension/module/dockercart_gallery', 'user_token=' . $this->session->data['user_token'], true));
		}

		$data = $this->getCommonData();

		if (isset($this->request->post['module_dockercart_gallery_status'])) {
			$data['module_dockercart_gallery_status'] = (int)$this->request->post['module_dockercart_gallery_status'];
		} else {
			$data['module_dockercart_gallery_status'] = (int)$this->config->get('module_dockercart_gallery_status');
		}

		if (isset($this->request->post['module_dockercart_gallery_show_header'])) {
			$data['module_dockercart_gallery_show_header'] = (int)$this->request->post['module_dockercart_gallery_show_header'];
		} else {
			$data['module_dockercart_gallery_show_header'] = (int)$this->config->get('module_dockercart_gallery_show_header');
		}

		$data['add_image_link'] = $this->url->link('extension/module/dockercart_gallery/form', 'user_token=' . $this->session->data['user_token'], true);
		$data['images'] = $this->model_extension_module_dockercart_gallery->getImages(array('sort' => 'sort_order', 'order' => 'ASC'));

		$data['license'] = null;

		if (is_file(DIR_SYSTEM . 'library/dockercart/licensing.php')) {
			require_once DIR_SYSTEM . 'library/dockercart/licensing.php';
			$licensing = new DockercartLicensing($this->registry);

			$data['license'] = $licensing->getLicense('dockercart_gallery');

			if ($data['license'] && !empty($data['license']['license_key'])) {
				$licensing->validate('dockercart_gallery');
				$data['license'] = $licensing->getLicense('dockercart_gallery');
			}
		}

		$data['activate_key_url'] = $this->url->link('extension/store/activateKey', 'user_token=' . $this->session->data['user_token'], true);

		foreach ($data['images'] as &$image) {
			$image['edit_link'] = $this->url->link('extension/module/dockercart_gallery/form', 'user_token=' . $this->session->data['user_token'] . '&gallery_id=' . (int)$image['gallery_id'], true);
			$image['delete_link'] = $this->url->link('extension/module/dockercart_gallery/delete', 'user_token=' . $this->session->data['user_token'] . '&gallery_id=' . (int)$image['gallery_id'], true);
			$image['thumb'] = $this->model_tool_image->resize($image['image'], 200, 200);
		}

		$this->response->setOutput($this->load->view('extension/module/dockercart_gallery', $data));
	}

	public function form() {
		$this->load->language('extension/module/dockercart_gallery');
		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('extension/module/dockercart_gallery');
		$this->load->model('tool/image');

		$gallery_id = isset($this->request->get['gallery_id']) ? (int)$this->request->get['gallery_id'] : 0;
		$image = array();

		if ($gallery_id > 0) {
			$image = $this->model_extension_module_dockercart_gallery->getImage($gallery_id);

			if (!$image) {
				$this->session->data['error_warning'] = $this->language->get('error_image_not_found');
				$this->response->redirect($this->url->link('extension/module/dockercart_gallery', 'user_token=' . $this->session->data['user_token'], true));
				return;
			}
		}

		$data = $this->getCommonData();
		$data['gallery_id'] = $gallery_id;
		$data['action'] = $this->url->link('extension/module/dockercart_gallery/save', 'user_token=' . $this->session->data['user_token'], true);
		$data['cancel'] = $this->url->link('extension/module/dockercart_gallery', 'user_token=' . $this->session->data['user_token'], true);

		$defaults = array(
			'gallery_id' => 0,
			'image' => '',
			'sort_order' => 0,
			'status' => 1
		);

		$data['image'] = array_merge($defaults, is_array($image) ? $image : array());
		$data['thumb'] = $data['image']['image'] ? $this->model_tool_image->resize($data['image']['image'], 200, 200) : $this->model_tool_image->resize('no_image.png', 200, 200);
		$data['placeholder'] = $this->model_tool_image->resize('no_image.png', 200, 200);

		$this->response->setOutput($this->load->view('extension/module/dockercart_gallery_form', $data));
	}

	public function save() {
		$this->load->language('extension/module/dockercart_gallery');
		$this->load->model('extension/module/dockercart_gallery');

		if (!$this->validateForm()) {
			$this->session->data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : $this->language->get('error_form');

			$redirect = $this->url->link('extension/module/dockercart_gallery/form', 'user_token=' . $this->session->data['user_token'] . (isset($this->request->post['gallery_id']) ? '&gallery_id=' . (int)$this->request->post['gallery_id'] : ''), true);
			$this->response->redirect($redirect);
			return;
		}

		$data = $this->request->post;
		$gallery_id = isset($data['gallery_id']) ? (int)$data['gallery_id'] : 0;

		if ($gallery_id > 0) {
			$this->model_extension_module_dockercart_gallery->editImage($gallery_id, $data);
		} else {
			$gallery_id = $this->model_extension_module_dockercart_gallery->addImage($data);
		}

		$this->session->data['success'] = $this->language->get('text_image_saved');
		$this->response->redirect($this->url->link('extension/module/dockercart_gallery/form', 'user_token=' . $this->session->data['user_token'] . '&gallery_id=' . (int)$gallery_id, true));
	}

	public function delete() {
		$this->load->language('extension/module/dockercart_gallery');
		$this->load->model('extension/module/dockercart_gallery');

		if (!$this->user->hasPermission('modify', 'extension/module/dockercart_gallery')) {
			$this->session->data['error_warning'] = $this->language->get('error_permission');
			$this->response->redirect($this->url->link('extension/module/dockercart_gallery', 'user_token=' . $this->session->data['user_token'], true));
			return;
		}

		$gallery_id = isset($this->request->get['gallery_id']) ? (int)$this->request->get['gallery_id'] : 0;
		if ($gallery_id > 0) {
			$this->model_extension_module_dockercart_gallery->deleteImage($gallery_id);
			$this->session->data['success'] = $this->language->get('text_image_deleted');
		}

		$this->response->redirect($this->url->link('extension/module/dockercart_gallery', 'user_token=' . $this->session->data['user_token'], true));
	}

	public function install() {
		$this->load->model('extension/module/dockercart_gallery');
		$this->load->model('setting/setting');
		$this->load->model('setting/event');
		$this->load->model('user/user_group');

		$this->model_extension_module_dockercart_gallery->install();
		$this->model_extension_module_dockercart_gallery->installSeoUrls();
		$this->registerEvents();

		$this->model_setting_setting->editSetting('module_dockercart_gallery', array(
			'module_dockercart_gallery_status' => 1,
			'module_dockercart_gallery_show_header' => 0
		));

		$group_id = (int)$this->user->getGroupId();
		$this->model_user_user_group->addPermission($group_id, 'access', 'extension/module/dockercart_gallery');
		$this->model_user_user_group->addPermission($group_id, 'modify', 'extension/module/dockercart_gallery');
	}

	public function uninstall() {
		$this->load->model('extension/module/dockercart_gallery');
		$this->load->model('setting/setting');

		$this->unregisterEvents();
		$this->model_extension_module_dockercart_gallery->uninstallSeoUrls();
		$this->model_extension_module_dockercart_gallery->uninstall();
		$this->model_setting_setting->deleteSetting('module_dockercart_gallery');

		if (is_file(DIR_SYSTEM . 'library/dockercart/licensing.php')) {
			require_once DIR_SYSTEM . 'library/dockercart/licensing.php';
			$licensing = new DockercartLicensing($this->registry);
			$licensing->removeLicense('dockercart_gallery');
		}

		if (is_file(DIR_SYSTEM . 'library/dockercart/extension_store.php')) {
			require_once DIR_SYSTEM . 'library/dockercart/extension_store.php';
			$store = new DockercartExtensionStore($this->registry);
			$store->removeInstalledMeta('dockercart_gallery');
		}
	}

	private function registerEvents() {
		$this->load->model('setting/event');

		$this->model_setting_event->deleteEventByCode('dockercart_gallery_admin_menu');
		$this->model_setting_event->deleteEventByCode('dockercart_gallery_footer');
		$this->model_setting_event->deleteEventByCode('dockercart_gallery_header');

		$this->model_setting_event->addEvent(
			'dockercart_gallery_admin_menu',
			'admin/view/common/column_left/before',
			'extension/module/dockercart_gallery/eventAdminMenu',
			1,
			0
		);

		$this->model_setting_event->addEvent(
			'dockercart_gallery_footer',
			'catalog/view/common/footer/before',
			'extension/module/dockercart_gallery/eventFooterBefore',
			1,
			0
		);

		$this->model_setting_event->addEvent(
			'dockercart_gallery_header',
			'catalog/view/common/header/before',
			'extension/module/dockercart_gallery/eventHeaderBefore',
			1,
			0
		);
	}

	private function unregisterEvents() {
		$this->load->model('setting/event');
		$this->model_setting_event->deleteEventByCode('dockercart_gallery_admin_menu');
		$this->model_setting_event->deleteEventByCode('dockercart_gallery_footer');
		$this->model_setting_event->deleteEventByCode('dockercart_gallery_header');
	}

	public function eventAdminMenu(&$route, &$data, &$output) {
		$this->load->language('extension/module/dockercart_gallery');

		if (!$this->user->hasPermission('access', 'extension/module/dockercart_gallery')) {
			return;
		}

		$menu = array(
			'name' => $this->language->get('heading_title_menu'),
			'href' => $this->url->link('extension/module/dockercart_gallery', 'user_token=' . $this->session->data['user_token'], true),
			'children' => array()
		);

		if (!isset($data['menus']) || !is_array($data['menus'])) {
			return;
		}

		foreach ($data['menus'] as &$item) {
            if (isset($item['id']) && $item['id'] === 'menu-catalog' && isset($item['children']) && is_array($item['children'])) {
				$faq_index = -1;
				foreach ($item['children'] as $idx => $child) {
					if (isset($child['href']) && strpos($child['href'], 'dockercart_faq') !== false) {
						$faq_index = $idx;
						break;
					}
				}

				if ($faq_index !== -1) {
					array_splice($item['children'], $faq_index + 1, 0, array($menu));
				} else {
					$item['children'][] = $menu;
				}
				return;
			}
		}

		$data['menus'][] = array(
			'id' => 'menu-dockercart-gallery',
			'icon' => 'fa-image',
			'name' => $this->language->get('heading_title_menu'),
			'href' => $this->url->link('extension/module/dockercart_gallery', 'user_token=' . $this->session->data['user_token'], true),
			'children' => array()
		);
	}

	private function getCommonData() {
		$data = array();

		$data['heading_title'] = $this->language->get('heading_title');
		$data['button_save'] = $this->language->get('button_save');
		$data['button_cancel'] = $this->language->get('button_cancel');
		$data['button_add'] = $this->language->get('button_add');
		$data['button_edit'] = $this->language->get('button_edit');
		$data['button_delete'] = $this->language->get('button_delete');

		$data['text_enabled'] = $this->language->get('text_enabled');
		$data['text_disabled'] = $this->language->get('text_disabled');
		$data['text_home'] = $this->language->get('text_home');
		$data['text_extension'] = $this->language->get('text_extension');
		$data['text_edit'] = $this->language->get('text_edit');
		$data['text_no_results'] = $this->language->get('text_no_results');
		$data['text_default'] = $this->language->get('text_default');
		$data['text_add_image'] = $this->language->get('text_add_image');
		$data['text_edit_image'] = $this->language->get('text_edit_image');
		$data['text_gallery_module_subtitle'] = $this->language->get('text_gallery_module_subtitle');
		$data['text_gallery_entries'] = $this->language->get('text_gallery_entries');
		$data['text_confirm_delete_image'] = $this->language->get('text_confirm_delete_image');
		$data['text_show_header'] = $this->language->get('text_show_header');
		$data['tab_about'] = $this->language->get('tab_about');
		$data['tab_about_subtitle'] = $this->language->get('tab_about_subtitle');
		$data['text_developer'] = $this->language->get('text_developer');
		$data['text_developer_name'] = $this->language->get('text_developer_name');
		$data['text_contact'] = $this->language->get('text_contact');
		$data['text_license_status'] = $this->language->get('text_license_status');
		$data['text_license_key_label'] = $this->language->get('text_license_key_label');
		$data['text_license_domain'] = $this->language->get('text_license_domain');
		$data['text_license_expires'] = $this->language->get('text_license_expires');
		$data['text_license_last_verified'] = $this->language->get('text_license_last_verified');
		$data['text_license_activate'] = $this->language->get('text_license_activate');
		$data['text_license_no_license'] = $this->language->get('text_license_no_license');
		$data['text_license_change_key'] = $this->language->get('text_license_change_key');

		$data['entry_status'] = $this->language->get('entry_status');
		$data['entry_show_header'] = $this->language->get('entry_show_header');
		$data['entry_image'] = $this->language->get('entry_image');
		$data['entry_sort_order'] = $this->language->get('entry_sort_order');
		$data['entry_image_status'] = $this->language->get('entry_image_status');

		$data['column_image'] = $this->language->get('column_image');
		$data['column_status'] = $this->language->get('column_status');
		$data['column_sort_order'] = $this->language->get('column_sort_order');
		$data['column_action'] = $this->language->get('column_action');

		$data['button_add_image'] = $this->language->get('button_add_image');

		$data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';
		if (isset($this->session->data['error_warning'])) {
			$data['error_warning'] = $this->session->data['error_warning'];
			unset($this->session->data['error_warning']);
		}

		$data['success'] = '';
		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];
			unset($this->session->data['success']);
		}

		$data['breadcrumbs'] = array();
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)
		);
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/module/dockercart_gallery', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['action'] = $this->url->link('extension/module/dockercart_gallery', 'user_token=' . $this->session->data['user_token'], true);
		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		return $data;
	}

	private function validateModule() {
		if (!$this->user->hasPermission('modify', 'extension/module/dockercart_gallery')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}

	private function validateForm() {
		if (!$this->user->hasPermission('modify', 'extension/module/dockercart_gallery')) {
			$this->error['warning'] = $this->language->get('error_permission');
			return false;
		}

		if ($this->request->server['REQUEST_METHOD'] !== 'POST') {
			$this->error['warning'] = $this->language->get('error_form');
			return false;
		}

		$image = isset($this->request->post['image']) ? trim((string)$this->request->post['image']) : '';
		if ($image === '') {
			$this->error['warning'] = $this->language->get('error_image_required');
			return false;
		}

		return true;
	}
}
