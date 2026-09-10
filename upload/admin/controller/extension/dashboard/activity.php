<?php
class ControllerExtensionDashboardActivity extends Controller {
	public function dashboard() {
		if (!$this->userHasAccess('extension/dashboard/activity')) {
			return '';
		}
		$this->load->language('extension/dashboard/activity');

		$data['text_activity_subtitle'] = $this->language->get('text_activity_subtitle');
		$data['user_token'] = $this->session->data['user_token'];
		$data['view_all'] = $this->url->link('report/report', 'user_token=' . $this->session->data['user_token'] . '&code=customer_activity', true);

		$data['activities'] = array();

		$this->load->model('extension/dashboard/activity');

		$results = $this->model_extension_dashboard_activity->getActivities();

		foreach ($results as $result) {
			$comment = vsprintf($this->language->get('text_activity_' . $result['key']), json_decode($result['data'], true));

			$find = array(
				'customer_id=',
				'order_id=',
				'return_id='
			);

			$replace = array(
				$this->url->link('customer/customer/edit', 'user_token=' . $this->session->data['user_token'] . '&customer_id=', true),
				$this->url->link('sale/order/info', 'user_token=' . $this->session->data['user_token'] . '&order_id=', true),
				$this->url->link('sale/return/edit', 'user_token=' . $this->session->data['user_token'] . '&return_id=', true)
			);

			$data['activities'][] = array(
				'comment'    => str_replace($find, $replace, $comment),
				'date_added' => date($this->language->get('datetime_format'), strtotime($result['date_added']))
			);
		}

		return $this->load->view('extension/dashboard/activity_info', $data);
	}
}