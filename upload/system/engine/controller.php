<?php
/**
 * @package		OpenCart
 * @author		Daniel Kerr
 * @copyright	Copyright (c) 2005 - 2017, OpenCart, Ltd. (https://www.opencart.com/)
 * @license		https://opensource.org/licenses/GPL-3.0
 * @link		https://www.opencart.com
*/

/**
* Controller class
 *
 * @mixin Registry
*/
abstract class Controller {
	protected $registry;

	public function __construct($registry) {
		$this->registry = $registry;
	}

	public function __get($key) {
		return $this->registry->get($key);
	}

	public function __set($key, $value) {
		$this->registry->set($key, $value);
	}

	/**
	 * Shared per-admin saved filter support (Shopify-style tabs).
	 *
	 * Builds the tabs (an "All" tab plus the admin's saved filters) for the
	 * given entity and returns the rendered common/user_filter component.
	 *
	 * @param string $entity    entity key ("order", "customer", ...)
	 * @param array  $counts    callable/array resolving tab counts:
	 *                          array('all' => int, custom_id => int, ...)
	 * @param array  $fields    filter builder fields for the add-filter modal
	 * @param string $route     current controller route for tab links
	 */
	protected function renderUserFilter(string $entity, string $route, array $fields, array $tabCounts = array(), string $activeBuiltin = '', array $extraTabs = array(), ?array $search = null, bool $showAdd = true): string {
		$this->load->language('common/user_filter');
		$this->load->model('user/user_filter');

		$user_id = (int)$this->user->getId();

		if (isset($this->request->get['filter_id'])) {
			$active_filter_id = (int)$this->request->get['filter_id'];
		} else {
			$active_filter_id = 0;
		}

		$saved_filters = $this->model_user_user_filter->getFilters($user_id, $entity);

		$tabs = array_merge($extraTabs, array(
			array(
				'id'    => 'all',
				'name'  => $this->language->get('text_filter_all'),
				'href'  => $this->url->link($route, 'user_token=' . $this->session->data['user_token'], true),
				'count' => isset($tabCounts['all']) ? $tabCounts['all'] : null,
				'is_active' => $activeBuiltin === '' && !$active_filter_id && !$this->hasActiveExtraTab($extraTabs)
			)
		));

		foreach ($saved_filters as $saved) {
			$tabs[] = array(
				'id'          => 'custom_' . $saved['filter_id'],
				'filter_id'   => $saved['filter_id'],
				'name'        => $saved['name'],
				'is_custom'   => true,
				'href'        => $this->url->link($route, 'user_token=' . $this->session->data['user_token'] . '&filter_id=' . $saved['filter_id'], true),
				'count'       => isset($tabCounts['custom_' . $saved['filter_id']]) ? $tabCounts['custom_' . $saved['filter_id']] : null,
				'is_active'   => $active_filter_id === $saved['filter_id']
			);
		}

		return $this->load->controller('common/user_filter', array(
			'entity'           => $entity,
			'tabs'             => $tabs,
			'active_builtin'   => $activeBuiltin,
			'active_filter_id' => $active_filter_id,
			'fields'           => $fields,
			'search'           => $search,
			'show_add'         => $showAdd
		));
	}

	/**
	 * Returns the active saved filter (if any) for the given entity.
	 */
	protected function getActiveUserFilter(string $entity): ?array {
		if (!isset($this->request->get['filter_id'])) {
			return null;
		}

		$this->load->model('user/user_filter');

		return $this->model_user_user_filter->getFilter((int)$this->request->get['filter_id'], (int)$this->user->getId(), $entity);
	}

	private function hasActiveExtraTab(array $extraTabs): bool {
		foreach ($extraTabs as $tab) {
			if (!empty($tab['is_active'])) {
				return true;
			}
		}

		return false;
	}
}