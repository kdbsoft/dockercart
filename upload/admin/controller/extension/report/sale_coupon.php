<?php
class ControllerExtensionReportSaleCoupon extends Controller {

	public function index() {
		// The settings form was removed: reports are managed on the
		// Reports page (report/report). Keep the route alive for old
		// bookmarks by redirecting there.
		$this->response->redirect($this->url->link('report/report', 'user_token=' . $this->session->data['user_token'] . '&code=sale_coupon', true));
	}
	
		
	public function report() {
		$this->load->language('extension/report/sale_coupon');

		if (isset($this->request->get['filter_date_start'])) {
			$filter_date_start = $this->request->get['filter_date_start'];
		} else {
			$filter_date_start = '';
		}

		if (isset($this->request->get['filter_date_end'])) {
			$filter_date_end = $this->request->get['filter_date_end'];
		} else {
			$filter_date_end = '';
		}

		if (isset($this->request->get['page'])) {
			$page = (int)$this->request->get['page'];
		} else {
			$page = 1;
		}

		$this->load->model('extension/report/coupon');

		$data['coupons'] = array();

		$filter_data = array(
			'filter_date_start'	=> $filter_date_start,
			'filter_date_end'	=> $filter_date_end,
			'start'             => ($page - 1) * $this->config->get('config_limit_admin'),
			'limit'             => $this->config->get('config_limit_admin')
		);

		$coupon_total = $this->model_extension_report_coupon->getTotalCoupons($filter_data);

		$results = $this->model_extension_report_coupon->getCoupons($filter_data);

		foreach ($results as $result) {
			$data['coupons'][] = array(
				'name'   => $result['name'],
				'code'   => $result['code'],
				'orders' => $result['orders'],
				'total'  => $this->currency->format($result['total'], $this->config->get('config_currency')),
				'edit'   => $this->url->link('marketing/coupon/edit', 'user_token=' . $this->session->data['user_token'] . '&coupon_id=' . $result['coupon_id'], true)
			);
		}

		$data['user_token'] = $this->session->data['user_token'];

		$url = '';

		if (isset($this->request->get['filter_date_start'])) {
			$url .= '&filter_date_start=' . $this->request->get['filter_date_start'];
		}

		if (isset($this->request->get['filter_date_end'])) {
			$url .= '&filter_date_end=' . $this->request->get['filter_date_end'];
		}

		$pagination = new Pagination();
		$pagination->total = $coupon_total;
		$pagination->page = $page;
		$pagination->limit = $this->config->get('config_limit_admin');
		$pagination->url = $this->url->link('report/report', 'user_token=' . $this->session->data['user_token'] . '&code=sale_coupon' . $url . '&page={page}', true);

		$data['pagination'] = $pagination->render();

		$data['results'] = $pagination->renderResults($this->language->get('text_pagination'));

		$data['filter_date_start'] = $filter_date_start;
		$data['filter_date_end'] = $filter_date_end;

		return $this->load->view('extension/report/sale_coupon_info', $data);
	}
}