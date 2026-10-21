<?php
/**
 * DockerCart admin REST API — order statuses.
 *
 *   GET /v1/order-statuses
 *
 * Read-only helper so an integrator can map the numeric order_status_id it
 * receives on orders to the labels configured in the panel (localized to the
 * request language).
 *
 * @package    DockerCart
 * @subpackage Api
 */

declare(strict_types=1);

class ControllerApiSaleOrderStatuses extends \Dockercart\Api\Controller {
	public function index() {
		$this->load->model('localisation/order_status');

		$rows = [];

		foreach ($this->model_localisation_order_status->getOrderStatuses() as $status) {
			$rows[] = [
				'order_status_id' => (int)$status['order_status_id'],
				'name'            => (string)$status['name'],
			];
		}

		$this->respond($rows);
	}
}
