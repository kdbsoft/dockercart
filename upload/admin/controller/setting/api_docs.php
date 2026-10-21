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
 * The OpenAPI document itself is served by the API application
 * (`GET /api/v1/openapi.json`, public and cacheable).
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

		// Public, cacheable document (no session, no user_token) — the same URL an
		// integrator or a code generator would use.
		$data['spec_url'] = rtrim((string)HTTP_CATALOG, '/') . '/api/v1/openapi.json';
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
