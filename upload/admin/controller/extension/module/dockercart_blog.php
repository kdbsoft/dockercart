<?php
/**
 * DockerCart Blog - Main Admin Controller
 * 
 * @package    DockerCart Blog
 * @version    1.0.0
 * @author     DockerCart Team
 * @copyright  2025 DockerCart
 * @license    Proprietary
 * 
 * Description: Main module controller for DockerCart Blog extension.
 *              Handles module installation, uninstallation, event registration,
 *              and module settings management using OpenCart Event System.
 * 
 * Features:
 * - Event-based integration (no OCMOD)
 * - Automatic database schema installation
 * - Event registration for admin menu, routes, sitemap
 * - Multi-store and multi-language support
 * - Clean uninstallation with data preservation option
 * 
 * Compatible: OpenCart 3.0.0 - 3.0.3.8+
 */

class ControllerExtensionModuleDockercartBlog extends Controller {
	
	/**
	 * Module error container
	 * @var array
	 */
	private $error = array();

	/**
	 * Main module settings page
	 * 
	 * Displays module configuration form with tabs for:
	 * - General settings
	 * - Display options
	 * - Comment settings
	 * - SEO/Sitemap settings
	 */
	public function index() {
		// Load dependencies
		$this->load->language('extension/module/dockercart_blog');
		$this->load->model('setting/setting');
		$this->load->model('setting/store');

		$this->document->setTitle($this->language->get('heading_title'));

		// Save settings
		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			// Build settings array containing only module keys (exclude user_token and other unrelated fields)
			$settings_data = array();
			$prefix = 'module_dockercart_blog';
			foreach ($this->request->post as $key => $value) {
				if ($key === 'user_token') {
					continue;
				}
				if (substr($key, 0, strlen($prefix)) === $prefix) {
					$settings_data[$key] = $value;
				}
			}
			$this->model_setting_setting->editSetting('module_dockercart_blog', $settings_data);

			// If main module disabled, also disable submodules
			if (isset($settings_data['module_dockercart_blog_status']) && (string)$settings_data['module_dockercart_blog_status'] === '0') {
				// Ensure submodules statuses are set to 0
				$this->model_setting_setting->editSettingValue('module_dockercart_blog_post', 'module_dockercart_blog_post_status', '0');
				$this->model_setting_setting->editSettingValue('module_dockercart_blog_author', 'module_dockercart_blog_author_status', '0');
				$this->model_setting_setting->editSettingValue('module_dockercart_blog_category', 'module_dockercart_blog_category_status', '0');
				$this->model_setting_setting->editSettingValue('module_dockercart_blog_comment', 'module_dockercart_blog_comment_status', '0');
			}
			$this->session->data['success'] = $this->language->get('text_success');
			$this->response->redirect($this->url->link('extension/module/dockercart_blog', 'user_token=' . $this->session->data['user_token'], true));
		}

		// Prepare breadcrumbs
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
			'href' => $this->url->link('extension/module/dockercart_blog', 'user_token=' . $this->session->data['user_token'], true)
		);

		// Prepare form data
		$data['action'] = $this->url->link('extension/module/dockercart_blog', 'user_token=' . $this->session->data['user_token'], true);
		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);
		$data['user_token'] = $this->session->data['user_token'];

		// Links to management pages
		$data['link_posts'] = $this->url->link('extension/module/dockercart_blog_post', 'user_token=' . $this->session->data['user_token'], true);
		$data['link_categories'] = $this->url->link('extension/module/dockercart_blog_category', 'user_token=' . $this->session->data['user_token'], true);
		$data['link_authors'] = $this->url->link('extension/module/dockercart_blog_author', 'user_token=' . $this->session->data['user_token'], true);
		$data['link_comments'] = $this->url->link('extension/module/dockercart_blog_comment', 'user_token=' . $this->session->data['user_token'], true);

		// Settings
		$settings = array(
			'module_dockercart_blog_status',
			'module_dockercart_blog_posts_per_page',
			'module_dockercart_blog_allow_comments',
			'module_dockercart_blog_moderate_comments',
			'module_dockercart_blog_captcha',
			'module_dockercart_blog_show_author',
			'module_dockercart_blog_show_date',
			'module_dockercart_blog_show_views',
			'module_dockercart_blog_latest_limit',
			'module_dockercart_blog_sitemap'
		);

		foreach ($settings as $setting) {
			if (isset($this->request->post[$setting])) {
				$data[$setting] = $this->request->post[$setting];
			} else {
				$data[$setting] = $this->config->get($setting);
			}
		}

		// Error handling
		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		// Field-specific errors
		if (isset($this->error['posts_per_page'])) {
			$data['error_posts_per_page'] = $this->error['posts_per_page'];
		} else {
			$data['error_posts_per_page'] = '';
		}

		if (isset($this->error['latest_limit'])) {
			$data['error_latest_limit'] = $this->error['latest_limit'];
		} else {
			$data['error_latest_limit'] = '';
		}

		// Success message (from session after save)
		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];
			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}

		// Render template
		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/dockercart_blog', $data));
	}

	/**
	 * Install module
	 * 
	 * Performs:
	 * 1. Database schema installation
	 * 2. Event registration for admin menu, routes, sitemap
	 * 3. Initial settings configuration
	 * 4. Permission setup
	 */
	public function install() {
		$this->load->model('extension/module/dockercart_blog');
		$this->load->model('setting/event');
		$this->load->model('setting/setting');
		$this->load->model('user/user_group');

		try {
			// Install database schema
			$this->model_extension_module_dockercart_blog->install();

			// Register events for integration
			$this->registerEvents();

			// Set default permissions
			$this->model_user_user_group->addPermission($this->user->getGroupId(), 'access', 'extension/module/dockercart_blog');
			$this->model_user_user_group->addPermission($this->user->getGroupId(), 'modify', 'extension/module/dockercart_blog');
			$this->model_user_user_group->addPermission($this->user->getGroupId(), 'access', 'extension/module/dockercart_blog_post');
			$this->model_user_user_group->addPermission($this->user->getGroupId(), 'modify', 'extension/module/dockercart_blog_post');
			$this->model_user_user_group->addPermission($this->user->getGroupId(), 'access', 'extension/module/dockercart_blog_category');
			$this->model_user_user_group->addPermission($this->user->getGroupId(), 'modify', 'extension/module_dockercart_blog_category');
			$this->model_user_user_group->addPermission($this->user->getGroupId(), 'access', 'extension/module/dockercart_blog_author');
			$this->model_user_user_group->addPermission($this->user->getGroupId(), 'modify', 'extension/module/dockercart_blog_author');
			$this->model_user_user_group->addPermission($this->user->getGroupId(), 'access', 'extension/module/dockercart_blog_comment');
			$this->model_user_user_group->addPermission($this->user->getGroupId(), 'modify', 'extension/module/dockercart_blog_comment');

			// Enable submodules by default (store_id = 0)
			$this->model_setting_setting->editSetting('module_dockercart_blog_post', array(
				'module_dockercart_blog_post_status' => 1
			));

			$this->model_setting_setting->editSetting('module_dockercart_blog_author', array(
				'module_dockercart_blog_author_status' => 1
			));

			$this->model_setting_setting->editSetting('module_dockercart_blog_category', array(
				'module_dockercart_blog_category_status' => 1
			));

			$this->model_setting_setting->editSetting('module_dockercart_blog_comment', array(
				'module_dockercart_blog_comment_status' => 1
			));

			// Setup default SEO URLs for blog routes
			$this->setupDefaultSeoUrls();

		} catch (Exception $e) {
			$this->log->write('DockerCart Blog Install Error: ' . $e->getMessage());
			throw $e;
		}
	}

	/**
	 * Setup default SEO URLs for blog routes
	 * Creates SEO URLs for main blog pages during installation
	 */
	private function setupDefaultSeoUrls() {
		$this->load->model('localisation/language');
		$languages = $this->model_localisation_language->getLanguages();

		// Default SEO URLs for blog routes with language-specific keywords
		// Format: route => [language_code => keyword]
		$default_seo_urls = array(
			'blog/category' => array(
				'en-gb' => 'blog',
				'ru-ru' => 'blog',
				'default' => 'blog'
			),
			'blog/search' => array(
				'en-gb' => 'blog-search',
				'ru-ru' => 'blog-poisk',
				'default' => 'blog-search'
			),
			'blog/author' => array(
				'en-gb' => 'blog-author',
				'ru-ru' => 'blog-avtor',
				'default' => 'blog-author'
			),
			'blog/archive' => array(
				'en-gb' => 'blog-archive',
				'ru-ru' => 'blog-arhiv',
				'default' => 'blog-archive'
			)
		);

		foreach ($default_seo_urls as $route => $keywords) {
			foreach ($languages as $language) {
				// Get keyword for specific language or use default
				$keyword = isset($keywords[$language['code']]) ? $keywords[$language['code']] : $keywords['default'];

				// Check if SEO URL already exists
				$check = $this->db->query(
					"SELECT * FROM " . DB_PREFIX . "seo_url 
					WHERE query = '" . $this->db->escape($route) . "' 
					AND store_id = '0' 
					AND language_id = '" . (int)$language['language_id'] . "'"
				);

				if (!$check->num_rows) {
					// Create SEO URL
					$this->db->query(
						"INSERT INTO " . DB_PREFIX . "seo_url 
						SET store_id = '0', 
						language_id = '" . (int)$language['language_id'] . "', 
						query = '" . $this->db->escape($route) . "', 
						keyword = '" . $this->db->escape($keyword) . "'"
					);
					
					$this->log->write('DockerCart Blog: SEO URL created - ' . $route . ' => ' . $keyword . ' (lang: ' . $language['code'] . ')');
				}
			}
		}

		$this->log->write('DockerCart Blog: Default SEO URLs setup completed');
	}

	/**
	 * Uninstall module
	 * 
	 * Performs:
	 * 1. Event unregistration
	 * 2. Optional: database cleanup (preserves data by default)
	 * 3. Settings removal
	 */
	public function uninstall() {
		$this->load->model('extension/module/dockercart_blog');
		$this->load->model('setting/event');
		$this->load->model('setting/setting');

		try {
			// Unregister events
			$this->unregisterEvents();

			// Optional: Uncomment to remove all data
			// $this->model_extension_module_dockercart_blog->uninstall();

			// Remove module settings
			$this->model_setting_setting->deleteSetting('module_dockercart_blog');
			// Remove submodule settings as well
			$this->model_setting_setting->deleteSetting('module_dockercart_blog_post');
			$this->model_setting_setting->deleteSetting('module_dockercart_blog_author');
			$this->model_setting_setting->deleteSetting('module_dockercart_blog_category');
			$this->model_setting_setting->deleteSetting('module_dockercart_blog_comment');

		} catch (Exception $e) {
			$this->log->write('DockerCart Blog Uninstall Error: ' . $e->getMessage());
			throw $e;
		}
	}

	/**
	 * Reinstall SEO URLs
	 * Public method to recreate default SEO URLs for blog routes
	 * Can be called manually if SEO URLs were accidentally deleted
	 */
	public function reinstallSeoUrls() {
		$this->load->language('extension/module/dockercart_blog');

		$json = array();

		// Check permission
		if (!$this->user->hasPermission('modify', 'extension/module/dockercart_blog')) {
			$json['error'] = $this->language->get('error_permission');
		} else {
			try {
				$this->setupDefaultSeoUrls();
				$json['success'] = $this->language->get('text_seo_urls_reinstalled');
			} catch (Exception $e) {
				$json['error'] = 'Error: ' . $e->getMessage();
				$this->log->write('DockerCart Blog SEO URLs Reinstall Error: ' . $e->getMessage());
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Register OpenCart events for module integration
	 * 
	 * Events registered:
	 * - admin/view/common/column_left/before: Add blog menu to admin
	 * - catalog/controller/startup/router/before: Register blog routes
	 * - catalog/controller/feed/google_sitemap/before: Add blog to sitemap
	 * - admin/model/catalog/product/deleteProduct/after: Cleanup related data
	 */
	private function registerEvents() {
		$this->load->model('setting/event');

		// Delete old events first
		$this->model_setting_event->deleteEventByCode('dockercart_blog');

		// Admin menu integration
		$this->model_setting_event->addEvent(
			'dockercart_blog_admin_menu',
			'admin/view/common/column_left/before',
			'extension/module/dockercart_blog/eventAdminMenu',
			1,
			0
		);

		// Catalog routes
		$this->model_setting_event->addEvent(
			'dockercart_blog_routes',
			'catalog/controller/startup/router/before',
			'extension/module/dockercart_blog/eventRegisterRoutes',
			1,
			0
		);

		// Sitemap integration
		$this->model_setting_event->addEvent(
			'dockercart_blog_sitemap',
			'catalog/controller/feed/google_sitemap/before',
			'extension/module/dockercart_blog/eventSitemap',
			1,
			0
		);

		// SEO URL rewrite
		$this->model_setting_event->addEvent(
			'dockercart_blog_seo_url',
			'catalog/controller/startup/seo_url/after',
			'extension/module/dockercart_blog/eventSeoUrl',
			1,
			0
		);
	}

	/**
	 * Unregister all module events
	 */
	private function unregisterEvents() {
		$this->load->model('setting/event');
		
		$this->model_setting_event->deleteEventByCode('dockercart_blog_admin_menu');
		$this->model_setting_event->deleteEventByCode('dockercart_blog_routes');
		$this->model_setting_event->deleteEventByCode('dockercart_blog_sitemap');
		$this->model_setting_event->deleteEventByCode('dockercart_blog_seo_url');
	}

	/**
	 * Event handler: Add blog menu to admin sidebar
	 * 
	 * @param string $route
	 * @param array $data
	 * @param string $output
	 */
	public function eventAdminMenu(&$route, &$data, &$output) {
		$this->load->language('extension/module/dockercart_blog');

		// Check if user has permission
		if (!$this->user->hasPermission('access', 'extension/module/dockercart_blog')) {
			return;
		}

		// Prepare blog menu item
		$blog_menu = array(
			'id'       => 'menu-dockercart-blog',
			'icon'     => 'fa-newspaper-o',
			'name'     => $this->language->get('text_blog'),
			'href'     => '',
			'children' => array(
				array(
					'name' => $this->language->get('text_posts'),
					'href' => $this->url->link('extension/module/dockercart_blog_post', 'user_token=' . $this->session->data['user_token'], true),
					'children' => array()
				),
				array(
					'name' => $this->language->get('text_categories'),
					'href' => $this->url->link('extension/module/dockercart_blog_category', 'user_token=' . $this->session->data['user_token'], true),
					'children' => array()
				),
				array(
					'name' => $this->language->get('text_authors'),
					'href' => $this->url->link('extension/module/dockercart_blog_author', 'user_token=' . $this->session->data['user_token'], true),
					'children' => array()
				),
				array(
					'name' => $this->language->get('text_comments'),
					'href' => $this->url->link('extension/module/dockercart_blog_comment', 'user_token=' . $this->session->data['user_token'], true),
					'children' => array()
				),
				array(
					'name' => $this->language->get('text_settings'),
					'href' => $this->url->link('extension/module/dockercart_blog', 'user_token=' . $this->session->data['user_token'], true),
					'children' => array()
				)
			)
		);

		// Find the catalog menu position
		if (isset($data['menus'])) {
			$position = 0;
			foreach ($data['menus'] as $key => $menu) {
				if ($menu['id'] == 'menu-catalog') {
					$position = $key + 1;
					break;
				}
			}

			// Insert blog menu after catalog
			array_splice($data['menus'], $position, 0, array($blog_menu));
		}
	}

	/**
	 * Validate form data
	 * 
	 * @return bool
	 */
	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/module/dockercart_blog')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		if (isset($this->request->post['module_dockercart_blog_posts_per_page'])) {
			if ((int)$this->request->post['module_dockercart_blog_posts_per_page'] < 1 || (int)$this->request->post['module_dockercart_blog_posts_per_page'] > 100) {
				$this->error['posts_per_page'] = $this->language->get('error_posts_per_page');
			}
		}

		if (isset($this->request->post['module_dockercart_blog_latest_limit'])) {
			if ((int)$this->request->post['module_dockercart_blog_latest_limit'] < 1 || (int)$this->request->post['module_dockercart_blog_latest_limit'] > 20) {
				$this->error['latest_limit'] = $this->language->get('error_latest_limit');
			}
		}

		return !$this->error;
	}
}
