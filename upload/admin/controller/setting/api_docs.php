<?php
/**
 * DockerCart admin — API documentation (System → API → Documentation).
 *
 * A standalone page (own document, no panel chrome): the Swagger UI is a
 * foreign application with its own stylesheet, so it gets a clean canvas
 * instead of fighting the panel layout. The page still requires the panel
 * session (the `setting/api_docs` access permission), so the API surface is
 * not advertised publicly.
 *
 * The OpenAPI document itself is served to this page by the panel action
 * `setting/api_docs/spec` (session-gated). A public copy also exists at
 * `GET /api/v1/openapi.json`, but only when the API is deployed and enabled —
 * the docs page never depends on it.
 *
 * @package    DockerCart
 * @subpackage Admin
 */

declare(strict_types=1);

class ControllerSettingApiDocs extends Controller {
	public function index() {
		$this->load->language('setting/api_docs');

		$this->document->setTitle($this->language->get('heading_title'));

		$user_token = $this->session->data['user_token'];

		// Serve the spec through the panel (setting/api_docs/spec), not the public
		// /api/v1/openapi.json: the public URL depends on the API application being
		// deployed AND enabled, while the panel always has the same file. The API
		// is disabled by default (config_api_status = 0), so a fresh deployment
		// without an explicit switch would 404 the public URL and the docs page
		// must not depend on it.
		$data['spec_url'] = $this->url->link('setting/api_docs/spec', 'user_token=' . $user_token, true);
		$data['api_base_url'] = rtrim((string)HTTP_CATALOG, '/') . '/api/v1';

		// Absolute URLs: the standalone page has no <base href> of the panel.
		$base = rtrim((string)HTTP_SERVER, '/');

		$data['swagger_css_url'] = $base . '/view/javascript/swagger-ui/swagger-ui.css';
		$data['swagger_js_url'] = $base . '/view/javascript/swagger-ui/swagger-ui-bundle.js';
		$data['tokens_css_url'] = $base . '/view/stylesheet/dockercart_tokens.css';
		$data['docs_css_url'] = $base . '/view/stylesheet/dockercart_api_docs.css';
		$data['lucide_js_url'] = $base . '/view/javascript/lucide.min.js';
		$data['favicon_url'] = $base . '/view/image/logo.png';

		$data['keys'] = $this->url->link('setting/api_key', 'user_token=' . $user_token, true);
		$data['log'] = $this->url->link('setting/api_log', 'user_token=' . $user_token, true);
		$data['back'] = $this->url->link('setting/api_key', 'user_token=' . $user_token, true);

		$data['lang'] = $this->language->get('code');
		$data['direction'] = $this->language->get('direction');

		$data['user_token'] = $user_token;

		$this->response->setOutput($this->load->view('setting/api_docs_page', $data));
	}

	/**
	 * Serves upload/api/openapi.json to the docs page (panel session required).
	 */
	public function spec() {
		$this->load->language('setting/api_docs');

		if (!$this->user->hasPermission('access', 'setting/api_docs')) {
			http_response_code(403);

			$this->response->addHeader('Content-Type: application/problem+json; charset=utf-8');
			$this->response->setOutput(json_encode([
				'type'   => 'urn:dockercart:api:error:forbidden',
				'title'  => 'Forbidden',
				'status' => 403,
				'detail' => $this->language->get('error_permission'),
				'code'   => 'forbidden',
			]));

			return;
		}

		$file = dirname(rtrim(DIR_APPLICATION, '/\\')) . '/api/openapi.json';

		$json = is_file($file) ? file_get_contents($file) : false;

		if ($json === false) {
			http_response_code(500);

			$json = json_encode([
				'type'   => 'urn:dockercart:api:error:server_error',
				'title'  => 'Internal Server Error',
				'status' => 500,
				'detail' => 'upload/api/openapi.json is missing.',
				'code'   => 'server_error',
			]);
		}

		$this->response->addHeader('Content-Type: application/json; charset=utf-8');
		$this->response->addHeader('Cache-Control: no-store');
		$this->response->setOutput($json);
	}
}
