<?php
class ControllerExtensionReportProductViewed extends Controller {

	public function index() {
		// The settings form was removed: reports are managed on the
		// Reports page (report/report). Keep the route alive for old
		// bookmarks by redirecting there.
		$this->response->redirect($this->url->link('report/report', 'user_token=' . $this->session->data['user_token'] . '&code=product_viewed', true));
	}
	
		
	public function report() {
		$this->load->language('extension/report/product_viewed');

		if (isset($this->request->get['page'])) {
			$page = (int)$this->request->get['page'];
		} else {
			$page = 1;
		}
		
		$data['reset'] = $this->url->link('extension/report/product_viewed/reset', 'user_token=' . $this->session->data['user_token'], true);

		$this->load->model('extension/report/product');

		$filter_data = array(
			'start' => ($page - 1) * $this->config->get('config_limit_admin'),
			'limit' => $this->config->get('config_limit_admin')
		);

		$data['products'] = array();

		$product_viewed_total = $this->model_extension_report_product->getTotalProductViews();

		$product_total = $this->model_extension_report_product->getTotalProductsViewed();

		$results = $this->model_extension_report_product->getProductsViewed($filter_data);

		foreach ($results as $result) {
			if ($result['viewed']) {
				$percent = round($result['viewed'] / $product_viewed_total * 100, 2);
			} else {
				$percent = 0;
			}

			$data['products'][] = array(
				'name'    => $result['name'],
				'model'   => $result['model'],
				'viewed'  => $result['viewed'],
				'percent' => $percent . '%'
			);
		}
		
		$data['user_token'] = $this->session->data['user_token'];

		$url = '';

		if (isset($this->request->get['page'])) {
			$url .= '&page=' . $this->request->get['page'];
		}

		$pagination = new Pagination();
		$pagination->total = $product_total;
		$pagination->page = $page;
		$pagination->limit = $this->config->get('config_limit_admin');
		$pagination->url = $this->url->link('report/report', 'user_token=' . $this->session->data['user_token'] . '&code=product_viewed&page={page}', true);

		$data['pagination'] = $pagination->render();

		$data['results'] = $pagination->renderResults($this->language->get('text_pagination'));
		
		return $this->load->view('extension/report/product_viewed_info', $data);
	}

	public function reset() {
		$this->load->language('extension/report/product_viewed');

		if (!$this->user->hasPermission('modify', 'extension/report/product_viewed')) {
			$this->session->data['error'] = $this->language->get('error_permission');
		} else {
			$this->load->model('extension/report/product');

			$this->model_extension_report_product->reset();

			$this->cache->delete('dash_viewed_product_ajax');
			$this->cache->delete('dash_viewed_product_spark');

			$this->session->data['success'] = $this->language->get('text_success');
		}

		$this->response->redirect($this->url->link('report/report', 'user_token=' . $this->session->data['user_token'] . '&code=product_viewed', true));
	}
}