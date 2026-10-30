<?php
/**
 * DockerCart storefront — customer API documentation (Account > API > Docs).
 *
 * A standalone Swagger UI page (own document, no theme chrome) served to a
 * logged-in customer whose API access is enabled. The OpenAPI document is served
 * by the `spec` action (session-gated), so the customer contract is never
 * advertised publicly except through GET /store-api/v1/openapi.json.
 *
 * @package    DockerCart
 * @subpackage Catalog
 */

declare(strict_types=1);

class ControllerAccountApiDocs extends Controller {
	public function index() {
		if (!$this->customer->isLogged()) {
			$this->session->data['redirect'] = $this->url->link('account/api_docs', '', true);

			$this->response->redirect($this->url->link('account/login', '', true));

			return;
		}

		$access = \Dockercart\Api\CustomerAccess::resolve($this->db, (int)$this->customer->getId());

		if (!$access['enabled']) {
			$this->response->redirect($this->url->link('account/account', '', true));

			return;
		}

		$this->load->language('account/api');

		$this->document->setTitle($this->language->get('heading_docs'));

		$base = rtrim((string)HTTP_SERVER, '/');

		$data['spec_url'] = $this->url->link('account/api_docs/spec', '', true);
		$data['api_base_url'] = $base . '/store-api/v1';
		$data['swagger_css_url'] = $base . '/admin/view/javascript/swagger-ui/swagger-ui.css';
		$data['swagger_js_url'] = $base . '/admin/view/javascript/swagger-ui/swagger-ui-bundle.js';
		$data['tokens_css_url'] = $base . '/admin/view/stylesheet/dockercart_tokens.css';
		$data['docs_css_url'] = $base . '/admin/view/stylesheet/dockercart_api_docs.css';
		$data['lucide_js_url'] = $base . '/catalog/view/javascript/lucide.min.js';
		$data['favicon_url'] = $base . '/admin/view/image/logo.png';
		$data['back'] = $this->url->link('account/api', '', true);
		$data['keys'] = $this->url->link('account/api', '', true);
		$data['lang'] = $this->language->get('code');
		$data['direction'] = $this->language->get('direction');

		$this->response->setOutput($this->load->view('account/api_docs_page', $data));
	}

	/**
	 * Serves upload/api/openapi-store.json to the docs page (session required).
	 */
	public function spec() {
		$this->load->language('account/api');

		if (!$this->customer->isLogged()) {
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

		$access = \Dockercart\Api\CustomerAccess::resolve($this->db, (int)$this->customer->getId());

		if (!$access['enabled']) {
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

		$file = dirname(rtrim(DIR_APPLICATION, '/\\')) . '/api/openapi-store.json';

		$json = is_file($file) ? file_get_contents($file) : false;

		if ($json === false) {
			http_response_code(500);

			$json = json_encode([
				'type'   => 'urn:dockercart:api:error:server_error',
				'title'  => 'Internal Server Error',
				'status' => 500,
				'detail' => 'upload/api/openapi-store.json is missing.',
				'code'   => 'server_error',
			]);
		}

		$this->response->addHeader('Content-Type: application/json; charset=utf-8');
		$this->response->addHeader('Cache-Control: no-store');
		$this->response->setOutput($json);
	}
}
