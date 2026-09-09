<?php
/**
 * DockerCart dropship supplier profit report: per-supplier revenue, purchase
 * cost and gross margin over order lines with a known supplier_cost.
 */

declare(strict_types=1);

class ControllerExtensionReportSupplierProfit extends Controller {

	public function index() {
		// The settings form was removed: reports are managed on the
		// Reports page (report/report). Keep the route alive for old
		// bookmarks by redirecting there.
		$this->response->redirect($this->url->link('report/report', 'user_token=' . $this->session->data['user_token'] . '&code=supplier_profit', true));
	}

	public function report() {
		$this->load->language('extension/report/supplier_profit');

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

		$status_keys = ['pending', 'ordered', 'shipped'];

		if (isset($this->request->get['filter_status']) && in_array($this->request->get['filter_status'], $status_keys, true)) {
			$filter_status = $this->request->get['filter_status'];
		} else {
			$filter_status = '';
		}

		if (isset($this->request->get['page'])) {
			$page = (int)$this->request->get['page'];
		} else {
			$page = 1;
		}

		$this->load->model('extension/report/supplier_profit');

		$filter_data = array(
			'filter_date_start' => $filter_date_start,
			'filter_date_end'   => $filter_date_end,
			'filter_status'     => $filter_status,
			'start'             => ($page - 1) * $this->config->get('config_limit_admin'),
			'limit'             => $this->config->get('config_limit_admin'),
		);

		$supplier_total = $this->model_extension_report_supplier_profit->getTotalSupplierProfit($filter_data);
		$results = $this->model_extension_report_supplier_profit->getSupplierProfit($filter_data);
		$totals = $this->model_extension_report_supplier_profit->getProfitTotals($filter_data);

		$currency = $this->config->get('config_currency');

		$data['suppliers'] = array();

		foreach ($results as $result) {
			$data['suppliers'][] = $this->buildRow($result, $currency);
		}

		$data['totals'] = $this->buildRow($totals, $currency);

		$url = '';

		if ($filter_date_start !== '') {
			$url .= '&filter_date_start=' . urlencode($filter_date_start);
		}

		if ($filter_date_end !== '') {
			$url .= '&filter_date_end=' . urlencode($filter_date_end);
		}

		if ($filter_status !== '') {
			$url .= '&filter_status=' . urlencode($filter_status);
		}

		$pagination = new Pagination();
		$pagination->total = $supplier_total;
		$pagination->page = $page;
		$pagination->limit = $this->config->get('config_limit_admin');
		$pagination->url = $this->url->link('report/report', 'user_token=' . $this->session->data['user_token'] . '&code=supplier_profit' . $url . '&page={page}', true);

		$data['pagination'] = $pagination->render();
		$data['results'] = $pagination->renderResults($this->language->get('text_pagination'));

		$data['filter_date_start'] = $filter_date_start;
		$data['filter_date_end'] = $filter_date_end;
		$data['filter_status'] = $filter_status;

		$data['statuses'] = array();

		foreach ($status_keys as $status_key) {
			$data['statuses'][] = array(
				'text'  => $this->language->get('text_line_' . $status_key),
				'value' => $status_key,
			);
		}

		$data['user_token'] = $this->session->data['user_token'];

		return $this->load->view('extension/report/supplier_profit_info', $data);
	}

	/**
	 * Formats one grouped row (a supplier or the grand total).
	 */
	private function buildRow(array $result, string $currency): array {
		$revenue_known = (float)($result['revenue_known'] ?? 0);
		$purchase = (float)($result['purchase'] ?? 0);
		$profit = $revenue_known - $purchase;

		return array(
			'supplier'    => (string)($result['supplier'] ?? ''),
			'lines_total' => (int)($result['lines_total'] ?? 0),
			'units'       => rtrim(rtrim(number_format((float)($result['units'] ?? 0), 2, '.', ''), '0'), '.'),
			'revenue'     => $this->currency->format((float)($result['revenue'] ?? 0), $currency),
			'purchase'    => $this->currency->format($purchase, $currency),
			'profit'      => $this->currency->format($profit, $currency),
			'profit_negative' => $profit < 0,
			'margin'      => $revenue_known > 0 ? number_format($profit / $revenue_known * 100, 1) . '%' : '—',
			'no_cost'     => (int)($result['lines_unknown_cost'] ?? 0),
		);
	}
}
