<?php
declare(strict_types=1);

/**
 * Generic per-admin saved filters UI (Shopify-style filter tabs) for any
 * admin list page.
 *
 * Each list controller builds its tabs (builtin + saved) and passes them
 * together with the entity key and the field definitions to index():
 *   $data['user_filter'] = $this->load->controller('common/user_filter', array(
 *       'entity'          => 'order',
 *       'tabs'            => $filter_tabs,
 *       'active_builtin'  => $active_builtin,
 *       'active_filter_id'=> $active_filter_id,
 *       'fields'          => array(
 *           array('key' => 'name', 'label' => ..., 'type' => 'text'),
 *           array('key' => 'status', 'label' => ..., 'type' => 'select', 'options' => array(...)),
 *           array('key' => 'order_status', 'label' => ..., 'type' => 'multi', 'options' => array(...)),
 *           array('key' => 'date_added', 'label' => ..., 'type' => 'date'),
 *           array('key' => 'total', 'label' => ..., 'type' => 'number'),
 *       )
 *   ));
 *
 * The controller then renders the tabs bar, the "add filter" modal and the
 * shared JS. Conditions are stored as JSON [{field, operator, value}] and are
 * interpreted by each list controller when applying the active filter.
 */
class ControllerCommonUserFilter extends Controller {
	public function index(array $args = array()): string {
		$this->load->language('common/user_filter');

		$operators = array(
			'eq'   => $this->language->get('text_operator_eq'),
			'ne'   => $this->language->get('text_operator_ne'),
			'gt'   => $this->language->get('text_operator_gt'),
			'gte'  => $this->language->get('text_operator_gte'),
			'lt'   => $this->language->get('text_operator_lt'),
			'lte'  => $this->language->get('text_operator_lte'),
			'contains' => $this->language->get('text_operator_contains'),
			'in'   => $this->language->get('text_operator_in'),
			'not_in' => $this->language->get('text_operator_not_in')
		);

		$data = array(
			'uf_entity'           => (string)($args['entity'] ?? ''),
			'uf_tabs'             => $args['tabs'] ?? array(),
			'uf_active_builtin'   => (string)($args['active_builtin'] ?? ''),
			'uf_active_filter_id' => (int)($args['active_filter_id'] ?? 0),
			'uf_fields'           => $args['fields'] ?? array(),
			'uf_fields_json'      => json_encode($args['fields'] ?? array()),
			'uf_search'           => $args['search'] ?? null,
			'uf_show_add'         => (bool)($args['show_add'] ?? true),
			'uf_text_add_filter'  => $this->language->get('text_add_filter'),
			'uf_text_filter_name' => $this->language->get('entry_filter_name'),
			'uf_text_add_condition' => $this->language->get('text_add_condition'),
			'uf_text_confirm_delete' => $this->language->get('text_confirm_delete_filter'),
			'uf_text_no_results'  => $this->language->get('text_no_results'),
			'uf_error_filter_name' => $this->language->get('error_filter_name'),
			'uf_text_save'        => $this->language->get('button_save'),
			'uf_text_cancel'      => $this->language->get('button_cancel'),
			'uf_text_remove'      => $this->language->get('button_remove'),
			'uf_text_clear'       => $this->language->get('button_clear_search'),
			'uf_operators'        => $operators,
			'uf_operators_json'   => json_encode($operators),
			'uf_save_url'         => $this->url->link('common/user_filter/save', 'user_token=' . $this->session->data['user_token'], true),
			'uf_delete_url'       => $this->url->link('common/user_filter/delete', 'user_token=' . $this->session->data['user_token'], true),
			'uf_user_token'       => $this->session->data['user_token']
		);

		return $this->load->view('common/user_filter', $data);
	}

	public function save(): void {
		$this->load->language('common/user_filter');
		$this->load->model('user/user_filter');

		$json = array();

		$entity = (string)($this->request->post['entity'] ?? '');

		if (!$this->user->hasPermission('modify', $this->permissionRoute($entity))) {
			$json['error'] = $this->language->get('error_permission');
		} else {
			$name = trim((string)($this->request->post['name'] ?? ''));
			$conditions = $this->request->post['conditions'] ?? array();

			if ($name === '') {
				$json['error'] = $this->language->get('error_filter_name');
			} elseif (!is_array($conditions)) {
				$json['error'] = $this->language->get('error_action');
			} else {
				$clean = array();

				foreach ($conditions as $condition) {
					$field = (string)($condition['field'] ?? '');
					$operator = (string)($condition['operator'] ?? 'eq');

					if ($field === '') {
						continue;
					}

					$condition_entry = array(
						'field'    => $field,
						'operator' => $operator
					);

					// Range conditions carry value_min / value_max instead of value.
					if (isset($condition['value_min']) || isset($condition['value_max'])) {
						$condition_entry['value_min'] = trim((string)($condition['value_min'] ?? ''));
						$condition_entry['value_max'] = trim((string)($condition['value_max'] ?? ''));

						if ($condition_entry['value_min'] === '' && $condition_entry['value_max'] === '') {
							continue;
						}
					} else {
						$value = $condition['value'] ?? '';

						if (is_array($value)) {
							$value = array_map('strval', array_values(array_filter($value, 'strlen')));
						} else {
							$value = trim((string)$value);
						}

						if ($value === '' || (is_array($value) && !$value)) {
							continue;
						}

						$condition_entry['value'] = $value;
					}

					$clean[] = $condition_entry;
				}

				$filter_id = $this->model_user_user_filter->addFilter((int)$this->user->getId(), $entity, $name, $clean);

				$json['success'] = $this->language->get('text_success');
				$json['filter_id'] = $filter_id;
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function delete(): void {
		$this->load->language('common/user_filter');
		$this->load->model('user/user_filter');

		$json = array();

		$entity = (string)($this->request->post['entity'] ?? '');

		if (!$this->user->hasPermission('modify', $this->permissionRoute($entity))) {
			$json['error'] = $this->language->get('error_permission');
		} else {
			$filter_id = (int)($this->request->post['filter_id'] ?? 0);

			if ($filter_id) {
				$this->model_user_user_filter->deleteFilter($filter_id, (int)$this->user->getId());
				$json['success'] = $this->language->get('text_success');
			} else {
				$json['error'] = $this->language->get('error_action');
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Permission route per entity, used by save/delete AJAX handlers.
	 */
	private function permissionRoute(string $entity): string {
		$map = array(
			'order'             => 'sale/order',
			'return'            => 'sale/return',
			'customer'          => 'customer/customer',
			'customer_approval' => 'customer/customer',
			'product'           => 'catalog/product',
			'review'            => 'catalog/review',
			'zone'              => 'localisation/zone',
			'country'           => 'localisation/country',
			'upload'            => 'tool/upload',
			'marketing'         => 'marketing/marketing',
			'seo_url'           => 'design/seo_url',
			'blog_comment'      => 'extension/module/dockercart_blog',
			'abandoned_cart'    => 'sale/order',
			'warehouse_stock'   => 'warehouse/stock',
			'warehouse_movement' => 'warehouse/movement',
			'warehouse_transfer' => 'warehouse/transfer',
			'warehouse_supplier_orders' => 'warehouse/supplier_orders'
		);

		return $map[$entity] ?? '';
	}
}
