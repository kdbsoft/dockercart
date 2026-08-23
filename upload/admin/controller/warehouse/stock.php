<?php
/**
 * DockerCart Warehouse Stock admin controller: the product x warehouse matrix
 * with AJAX cell editing and a "recalculate totals" drift report.
 */

declare(strict_types=1);

class ControllerWarehouseStock extends Controller {
	public function index() {
		$this->load->language('warehouse/stock');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('warehouse/stock');
		$this->getList();
	}

	/**
	 * AJAX: update one matrix cell (quantity / unlimited / lead_time).
	 */
	public function updateCell() {
		$this->load->language('warehouse/stock');
		$this->load->model('warehouse/stock');

		$json = ['success' => false];

		if ($this->user->hasPermission('modify', 'warehouse/stock')) {
			$input = $this->request->post;
			$stock_id = (int)($input['stock_id'] ?? 0);
			$mode = (string)($input['mode'] ?? 'quantity');

			// Read the current row so only the edited field changes.
			$current = $this->db->query("SELECT quantity, unlimited, lead_time FROM `" . DB_PREFIX . "warehouse_stock` WHERE `stock_id` = '" . (int)$stock_id . "'");

			if ($current->num_rows) {
				$quantity = (float)$current->row['quantity'];
				$unlimited = (bool)$current->row['unlimited'];
				$lead_time = (int)$current->row['lead_time'];

				if ($mode === 'unlimited') {
					$unlimited = (bool)(int)($input['value'] ?? 0);
				} elseif ($mode === 'lead_time') {
					$lead_time = (int)($input['value'] ?? 0);
				} else {
					$quantity = (float)($input['value'] ?? 0);
				}

				$this->model_warehouse_stock->setCell($stock_id, $quantity, $unlimited, $lead_time);
			}

			$json['success'] = true;
		} else {
			$json['error'] = $this->language->get('error_permission');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * AJAX: add a missing product row to a warehouse (from the product picker).
	 */
	public function addProduct() {
		$this->load->language('warehouse/stock');
		$this->load->model('warehouse/stock');

		$json = ['success' => false];

		if ($this->user->hasPermission('modify', 'warehouse/stock')) {
			$input = $this->request->post;

			$stock_id = $this->model_warehouse_stock->ensureRow(
				(int)($input['warehouse_id'] ?? 0),
				(int)($input['product_id'] ?? 0),
				(int)($input['variant_id'] ?? 0)
			);

			$json['stock_id'] = $stock_id;
			$json['success'] = true;
		} else {
			$json['error'] = $this->language->get('error_permission');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * AJAX: recompute all denormalised caches and report drift.
	 */
	public function recalculate() {
		$this->load->language('warehouse/stock');
		$this->load->model('warehouse/stock');

		$json = ['success' => false];

		if ($this->user->hasPermission('modify', 'warehouse/stock')) {
			$result = $this->model_warehouse_stock->recalculate();
			$json = [
				'success' => true,
				'total' => $result['total'],
				'drifted' => $result['drifted'],
				'totals_message' => sprintf($this->language->get('text_recalculated'), $result['total'], $result['drifted']),
			];
		} else {
			$json['error'] = $this->language->get('error_permission');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * GET: export the current filtered matrix as CSV. Identification columns
	 * (stock_id + warehouse/product/variant) plus the inline-editable fields,
	 * so the file round-trips through importCsv().
	 */
	public function exportCsv(): void {
		$this->load->language('warehouse/stock');
		$this->load->model('warehouse/stock');

		if (!$this->user->hasPermission('access', 'warehouse/stock')) {
			return;
		}

		$filter_data = [];

		foreach (['filter_warehouse_id', 'filter_product_id', 'filter_name', 'filter_model', 'filter_sku', 'filter_quantity_min', 'filter_quantity_max', 'filter_unlimited'] as $key) {
			if (isset($this->request->get[$key])) {
				$filter_data[$key] = (string)$this->request->get[$key];
			}
		}

		// Saved-filter tab conditions override the URL filters (mirrors getList).
		$active_filter = $this->getActiveUserFilter('warehouse_stock');

		if ($active_filter) {
			foreach ($this->buildStockFilterData($active_filter['conditions']) as $key => $value) {
				$filter_data[$key] = $value;
			}
		}

		$rows = $this->model_warehouse_stock->getStockMatrix($filter_data);

		header('Content-Type: text/csv; charset=utf-8');
		header('Content-Disposition: attachment; filename="warehouse-stock-' . date('Ymd-His') . '.csv"');

		$out = fopen('php://output', 'w');
		fputs($out, "\xEF\xBB\xBF"); // UTF-8 BOM

		fputcsv($out, ['stock_id', 'warehouse_id', 'warehouse_name', 'product_id', 'product_name', 'model', 'variant_sku', 'quantity', 'unlimited', 'lead_time']);

		foreach ($rows as $row) {
			fputcsv($out, [
				$row['stock_id'],
				$row['warehouse_id'],
				$row['warehouse_name'],
				$row['product_id'],
				$row['product_name'],
				$row['product_model'],
				$row['variant_sku'],
				$row['quantity'],
				(int)$row['unlimited'],
				$row['lead_time'],
			]);
		}

		fclose($out);
	}

	/**
	 * AJAX POST (multipart file): update-only CSV import for the
	 * inline-editable fields (quantity / unlimited / lead_time). Validates
	 * every row first; applies nothing while any row fails.
	 */
	public function importCsv(): void {
		$this->load->language('warehouse/stock');
		$this->load->model('warehouse/stock');

		if (!$this->user->hasPermission('modify', 'warehouse/stock')) {
			$json = ['success' => false, 'errors' => [$this->language->get('error_permission')]];
		} else {
			$json = $this->processImport();
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	private function processImport(): array {
		$file = $_FILES['file'] ?? null;

		if (!is_array($file) || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string)($file['tmp_name'] ?? ''))) {
			return ['success' => false, 'errors' => [$this->language->get('error_import_upload')]];
		}

		if ((int)$file['size'] > 10485760) {
			return ['success' => false, 'errors' => [sprintf($this->language->get('error_import_size'), 10)]];
		}

		$handle = fopen((string)$file['tmp_name'], 'r');

		if ($handle === false) {
			return ['success' => false, 'errors' => [$this->language->get('error_import_upload')]];
		}

		$header = null;
		$errors = [];
		$updates = [];
		$seen = [];
		$data_rows = 0;
		$skipped = 0;
		$line = 0;

		while (($cells = fgetcsv($handle, 0, ',')) !== false) {
			$line++;

			if ($header === null) {
				$header = $this->mapImportHeader($cells);

				if (!$this->hasImportColumns($header)) {
					fclose($handle);

					return ['success' => false, 'errors' => [$this->language->get('error_import_header')]];
				}

				continue;
			}

			// Skip blank lines.
			if (trim(implode('', array_map('strval', $cells))) === '') {
				continue;
			}

			$data_rows++;

			if ($data_rows > 50000) {
				fclose($handle);

				return ['success' => false, 'errors' => [sprintf($this->language->get('error_import_too_many'), 50000)]];
			}

			$cell = function(string $name) use ($header, $cells): string {
				$index = $header[$name] ?? null;

				return $index === null || !isset($cells[$index]) ? '' : trim((string)$cells[$index]);
			};

			// Resolve the target row: stock_id wins, else the unique triple.
			// Missing rows are reported, never created (update-only import).
			$stock_id = (int)$cell('stock_id');

			if ($stock_id && !$this->stockRowExists($stock_id)) {
				$errors[] = [$line, sprintf($this->language->get('error_import_stock_missing'), $stock_id)];
				continue;
			}

			if (!$stock_id) {
				$stock_id = $this->model_warehouse_stock->findStockId((int)$cell('warehouse_id'), (int)$cell('product_id'), (int)$cell('variant_id'));

				if (!$stock_id) {
					$errors[] = [$line, sprintf($this->language->get('error_import_position_missing'), (int)$cell('warehouse_id'), (int)$cell('product_id'), (int)$cell('variant_id'))];
					continue;
				}
			}

			if (isset($seen[$stock_id])) {
				$errors[] = [$line, sprintf($this->language->get('error_import_duplicate'), $stock_id)];
				continue;
			}

			$quantity = $this->parseQuantityCell($cell('quantity'));
			$unlimited = $this->parseUnlimitedCell($cell('unlimited'));
			$lead_time = $this->parseLeadTimeCell($cell('lead_time'));
			$row_errors = [];

			if ($quantity === false) {
				$row_errors[] = sprintf($this->language->get('error_import_quantity'), $cell('quantity'));
			}

			if ($unlimited === -1) {
				$row_errors[] = sprintf($this->language->get('error_import_unlimited'), $cell('unlimited'));
			}

			if ($lead_time === -1) {
				$row_errors[] = sprintf($this->language->get('error_import_lead_time'), $cell('lead_time'));
			}

			if ($row_errors) {
				$errors[] = [$line, implode('; ', $row_errors)];
				continue;
			}

			// All editable cells empty -> nothing to change on this row.
			if ($quantity === null && $unlimited === null && $lead_time === null) {
				$skipped++;
				continue;
			}

			$current = $this->db->query("SELECT `quantity`, `unlimited`, `lead_time` FROM `" . DB_PREFIX . "warehouse_stock` WHERE `stock_id` = '" . $stock_id . "'");

			$updates[$stock_id] = [
				'stock_id' => $stock_id,
				'quantity' => $quantity === null ? (float)$current->row['quantity'] : $quantity,
				'unlimited' => $unlimited === null ? (bool)$current->row['unlimited'] : (bool)$unlimited,
				'lead_time' => $lead_time === null ? (int)$current->row['lead_time'] : $lead_time,
			];

			$seen[$stock_id] = true;
		}

		fclose($handle);

		if (!$data_rows) {
			return ['success' => false, 'errors' => [$this->language->get('error_import_empty')]];
		}

		if ($errors) {
			$list = [];

			foreach (array_slice($errors, 0, 100) as $error) {
				$list[] = sprintf($this->language->get('text_import_line'), $error[0], $error[1]);
			}

			if (count($errors) > 100) {
				$list[] = sprintf($this->language->get('text_import_more'), count($errors) - 100);
			}

			return ['success' => false, 'total_errors' => count($errors), 'errors' => $list];
		}

		$updated = $this->model_warehouse_stock->applyCellUpdates(array_values($updates));

		return ['success' => true, 'updated' => $updated, 'skipped' => $skipped, 'message' => sprintf($this->language->get('text_import_result'), $updated, $skipped)];
	}

	/**
	 * Lowercased header-name => column-index map (UTF-8 BOM tolerant).
	 *
	 * @param array<int, string|null> $cells
	 * @return array<string, int>
	 */
	private function mapImportHeader(array $cells): array {
		$map = [];

		foreach ($cells as $index => $cell) {
			$name = strtolower(ltrim(trim((string)$cell), "\xEF\xBB\xBF"));

			if ($name !== '') {
				$map[$name] = $index;
			}
		}

		return $map;
	}

	private function hasImportColumns(array $header): bool {
		$editable = (bool)array_intersect(['quantity', 'unlimited', 'lead_time'], array_keys($header));
		$identity = isset($header['stock_id']) || (isset($header['warehouse_id'], $header['product_id'], $header['variant_id']));

		return $editable && $identity;
	}

	private function stockRowExists(int $stock_id): bool {
		return (bool)$this->db->query("SELECT 1 FROM `" . DB_PREFIX . "warehouse_stock` WHERE `stock_id` = '" . $stock_id . "'")->num_rows;
	}

	/**
	 * @return float|bool|null false = invalid, null = leave unchanged
	 */
	private function parseQuantityCell(string $raw): float|bool|null {
		if ($raw === '') {
			return null;
		}

		if (!is_numeric($raw)) {
			return false;
		}

		$value = (float)$raw;

		if (!is_finite($value) || $value < 0 || $value > 10000000000) {
			return false;
		}

		return $value;
	}

	/**
	 * @return int|null -1 = invalid, null = leave unchanged
	 */
	private function parseUnlimitedCell(string $raw): ?int {
		if ($raw === '') {
			return null;
		}

		$value = strtolower($raw);

		if (in_array($value, ['1', 'true', 'yes', 'y', 'on'], true)) {
			return 1;
		}

		if (in_array($value, ['0', 'false', 'no', 'n', 'off'], true)) {
			return 0;
		}

		return -1;
	}

	/**
	 * @return int|null -1 = invalid, null = leave unchanged
	 */
	private function parseLeadTimeCell(string $raw): ?int {
		if ($raw === '') {
			return null;
		}

		// FILTER_VALIDATE_INT rejects zero-padded values ("07"), so allow a
		// digits-only fallback.
		$value = filter_var($raw, FILTER_VALIDATE_INT);

		if ($value === false && preg_match('/^\d+$/', $raw)) {
			$value = (int)$raw;
		}

		if ($value === false || $value < 0 || $value > 1000000) {
			return -1;
		}

		return $value;
	}

	/**
	 * AJAX: read-only warehouse card for the Stock by Warehouse matrix
	 * (modal, mirrors sale/order_detail/getProductCard).
	 */
	public function getWarehouseCard(): void {
		$this->load->language('warehouse/stock');
		$this->load->language('warehouse/warehouse');

		$json = [];

		if (!$this->user->hasPermission('access', 'warehouse/stock')) {
			$json['error'] = $this->language->get('error_permission');
		} else {
			$warehouse_id = (int)($this->request->get['warehouse_id'] ?? 0);

			if ($warehouse_id) {
				$this->load->model('warehouse/warehouse');
				$warehouse_info = $this->model_warehouse_warehouse->getWarehouse($warehouse_id);

				if ($warehouse_info) {
					$language_id = (int)$this->config->get('config_language_id');

					// Localised name/address, denormalised base columns as fallback.
					$descriptions = $this->model_warehouse_warehouse->getDescriptions($warehouse_id);
					$description = $descriptions[$language_id] ?? [];

					$name = trim((string)($description['name'] ?? '')) !== '' ? (string)$description['name'] : (string)($warehouse_info['name'] ?? '');

					$address_parts = [];

					foreach (['address_1', 'address_2'] as $key) {
						$value = trim((string)($description[$key] ?? '')) !== '' ? (string)$description[$key] : (string)($warehouse_info[$key] ?? '');

						if ($value !== '') {
							$address_parts[] = $value;
						}
					}

					$city = trim((string)($description['city'] ?? '')) !== '' ? (string)$description['city'] : (string)($warehouse_info['city'] ?? '');
					$postcode = (string)($warehouse_info['postcode'] ?? '');

					if ($city !== '') {
						$address_parts[] = $postcode !== '' ? $city . ', ' . $postcode : $city;
					} elseif ($postcode !== '') {
						$address_parts[] = $postcode;
					}

					// Working hours: one row per weekday with joined windows.
					$day_keys = [
						1 => 'text_monday',
						2 => 'text_tuesday',
						3 => 'text_wednesday',
						4 => 'text_thursday',
						5 => 'text_friday',
						6 => 'text_saturday',
						7 => 'text_sunday',
					];

					$schedule = [];

					foreach ($this->model_warehouse_warehouse->getScheduleRows($warehouse_id) as $day => $row) {
						$windows = [];

						foreach ($row['windows'] as $window) {
							$windows[] = $window['time_from'] . '–' . $window['time_to'];
						}

						$schedule[] = [
							'day' => $this->language->get($day_keys[(int)$day]),
							'open' => !empty($row['is_open']),
							'time' => implode(', ', $windows),
						];
					}

					// Stock summary for the card.
					$stats = $this->db->query("SELECT COUNT(*) AS positions, COALESCE(SUM(`quantity`), 0) AS total_quantity FROM `" . DB_PREFIX . "warehouse_stock` WHERE `warehouse_id` = '" . (int)$warehouse_id . "'");

					$data = [
						'name' => $name,
						'type_code' => (string)($warehouse_info['type'] ?? ''),
						'type' => (string)($warehouse_info['type'] ?? '') !== '' ? $this->language->get('text_type_' . $warehouse_info['type']) : '',
						'status' => !empty($warehouse_info['status']),
						'is_default' => !empty($warehouse_info['is_default']),
						'priority' => (int)($warehouse_info['priority'] ?? 0),
						'address' => implode(', ', $address_parts),
						'phone' => (string)($warehouse_info['phone'] ?? ''),
						'email' => (string)($warehouse_info['email'] ?? ''),
						'map_url' => (string)($warehouse_info['map_url'] ?? ''),
						'allow_pickup' => !empty($warehouse_info['allow_pickup']),
						'pickup_cost' => $this->currency->format((float)($warehouse_info['pickup_cost'] ?? 0), $this->config->get('config_currency')),
						'pickup_note' => (string)($warehouse_info['pickup_note'] ?? ''),
						'prepare_days' => (int)($warehouse_info['prepare_days'] ?? 0),
						'supplier_name' => (string)($warehouse_info['supplier_name'] ?? ''),
						'supplier_phone' => (string)($warehouse_info['supplier_phone'] ?? ''),
						'supplier_email' => (string)($warehouse_info['supplier_email'] ?? ''),
						'supplier_lead_time' => (int)($warehouse_info['supplier_lead_time'] ?? 0),
						'supplier_note' => (string)($warehouse_info['supplier_note'] ?? ''),
						'schedule' => $schedule,
						'positions' => (int)$stats->row['positions'],
						'total_quantity' => (float)$stats->row['total_quantity'],
						'href' => $this->url->link('warehouse/warehouse/edit', 'user_token=' . $this->session->data['user_token'] . '&warehouse_id=' . $warehouse_id, true),
					];

					$json['success'] = true;
					$json['html'] = $this->load->view('warehouse/warehouse_card_modal', $data);
				}
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * AJAX: read-only product card for the Stock by Warehouse matrix
	 * (modal, mirrors sale/order_detail/getProductCard but stock-focused).
	 */
	public function getProductCard(): void {
		$this->load->language('warehouse/stock');

		$json = [];

		if (!$this->user->hasPermission('access', 'warehouse/stock')) {
			$json['error'] = $this->language->get('error_permission');
		} else {
			$product_id = (int)($this->request->get['product_id'] ?? 0);
			$variant_id = (int)($this->request->get['variant_id'] ?? 0);
			$warehouse_id = (int)($this->request->get['warehouse_id'] ?? 0);

			if ($product_id) {
				$this->load->model('catalog/product');
				$this->load->model('tool/image');

				$product_info = $this->model_catalog_product->getProduct($product_id);

				if ($product_info) {
					$language_id = (int)$this->config->get('config_language_id');

					// Gallery: main image + extras, thumbs 80px / full 300px.
					$main_image = $product_info['image'] ?? '';

					if (!empty($main_image) && is_file(DIR_IMAGE . $main_image)) {
						$image = $this->model_tool_image->resize($main_image, 300, 300);
						$image_full = HTTP_CATALOG . 'image/' . $main_image;
					} else {
						$image = $this->model_tool_image->resize('no_image.png', 300, 300);
						$image_full = '';
					}

					$gallery = [];

					if ($image_full) {
						$gallery[] = ['thumb' => $image, 'full' => $image_full];
					}

					foreach ($this->model_catalog_product->getProductImages($product_id) as $img) {
						if (!empty($img['image']) && is_file(DIR_IMAGE . $img['image'])) {
							$gallery[] = [
								'thumb' => $this->model_tool_image->resize($img['image'], 80, 80),
								'full' => $this->model_tool_image->resize($img['image'], 300, 300),
							];
						}
					}

					// Attributes for the current admin language.
					$attr_data = [];
					$this->load->model('catalog/attribute');

					foreach ($this->model_catalog_product->getProductAttributes($product_id) as $attr) {
						$descs = $this->model_catalog_attribute->getAttributeDescriptions($attr['attribute_id']);
						$name = $descs[$language_id]['name'] ?? '';
						$text = strip_tags($attr['product_attribute_description'][$language_id]['text'] ?? '');

						if ($name) {
							$attr_data[] = ['name' => $name, 'text' => $text];
						}
					}

					// Universal product codes (+ variant codes when set).
					$codes = [];

					foreach (['sku' => 'SKU', 'upc' => 'UPC', 'ean' => 'EAN', 'jan' => 'JAN', 'isbn' => 'ISBN', 'mpn' => 'MPN'] as $key => $label) {
						if (!empty($product_info[$key])) {
							$codes[] = ['label' => $label, 'value' => $product_info[$key]];
						}
					}

					if ($variant_id) {
						$variant_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_variant WHERE variant_id = '" . (int)$variant_id . "' AND product_id = '" . (int)$product_id . "'");

						if ($variant_query->row) {
							$v = $variant_query->row;

							foreach ([['model', 'Variant Model'], ['sku', 'Variant SKU'], ['upc', 'Variant UPC'], ['ean', 'Variant EAN'], ['mpn', 'Variant MPN']] as [$key, $label]) {
								if (!empty($v[$key])) {
									$codes[] = ['label' => $label, 'value' => $v[$key]];
								}
							}
						}
					}

					// Stock in this warehouse (the clicked matrix row).
					$qty_here = 0.0;
					$unlimited = false;

					if ($warehouse_id) {
						$here = $this->db->query("SELECT `quantity`, `unlimited` FROM `" . DB_PREFIX . "warehouse_stock` WHERE `warehouse_id` = '" . (int)$warehouse_id . "' AND `product_id` = '" . (int)$product_id . "' AND `variant_id` = '" . (int)$variant_id . "'");

						if ($here->num_rows) {
							$qty_here = (float)$here->row['quantity'];
							$unlimited = (bool)$here->row['unlimited'];
						}
					}

					// Total across all warehouses + reserved by active checkouts.
					$total = $this->db->query("SELECT COALESCE(SUM(`quantity`), 0) AS total_quantity FROM `" . DB_PREFIX . "warehouse_stock` WHERE `product_id` = '" . (int)$product_id . "' AND `variant_id` = '" . (int)$variant_id . "'");

					$reservation = new \DockercartStockReservation($this->registry);
					$reserved_map = $reservation->getReservedByProductIds([$product_id], null, false);
					$reserved = $reserved_map[(int)$product_id . ':' . $variant_id] ?? 0.0;

					$description = strip_tags(htmlspecialchars_decode($product_info['description'] ?? '', ENT_QUOTES));
					$description = trim(preg_replace('/\s+/', ' ', $description));

					$data = [
						'name' => $product_info['name'] ?? '',
						'model' => $product_info['model'] ?? '',
						'image' => $image,
						'gallery' => $gallery,
						'codes' => $codes,
						'status' => !empty($product_info['status']),
						'qty_here' => $qty_here,
						'unlimited' => $unlimited,
						'qty_total' => (float)$total->row['total_quantity'],
						'reserved' => (float)$reserved,
						'attributes' => $attr_data,
						'description' => $description,
						'href' => $this->url->link('catalog/product/edit', 'user_token=' . $this->session->data['user_token'] . '&product_id=' . $product_id, true),
					];

					$json['success'] = true;
					$json['html'] = $this->load->view('warehouse/warehouse_product_card_modal', $data);
				}
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * AJAX: search-as-you-type for the toolbar (filters the matrix on pick).
	 */
	public function autocomplete() {
		$this->load->language('warehouse/stock');
		$this->load->model('warehouse/stock');

		$json = [];

		if ($this->user->hasPermission('access', 'warehouse/stock')) {
			$filter_search = trim((string)($this->request->get['filter_search'] ?? ''));

			if ($filter_search !== '') {
				foreach ($this->model_warehouse_stock->autocompleteProducts($filter_search, 8) as $result) {
					$json[] = [
						'id' => $result['product_id'],
						'name' => $result['name'],
						'subtitle' => $result['model'],
						'href' => $this->url->link('warehouse/stock', 'user_token=' . $this->session->data['user_token'] . '&filter_product_id=' . (int)$result['product_id'], true),
					];
				}
			}
		} else {
			$json['error'] = $this->language->get('error_permission');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	protected function getList() {
		$page = isset($this->request->get['page']) ? (int)$this->request->get['page'] : 1;

		// URL filters (also produced by saved-filter tab conditions).
		$filter_keys = ['filter_warehouse_id', 'filter_product_id', 'filter_name', 'filter_model', 'filter_sku', 'filter_quantity_min', 'filter_quantity_max', 'filter_unlimited'];
		$filters = [];

		foreach ($filter_keys as $key) {
			$filters[$key] = isset($this->request->get[$key]) ? (string)$this->request->get[$key] : '';
		}

		$data['breadcrumbs'] = [];
		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true),
		];
		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('warehouse/stock', 'user_token=' . $this->session->data['user_token'], true),
		];

		$data['recalculate'] = $this->url->link('warehouse/stock/recalculate', 'user_token=' . $this->session->data['user_token'], true);
		$data['user_token'] = $this->session->data['user_token'];
		$data['product_edit_url'] = $this->url->link('catalog/product/edit', 'user_token=' . $this->session->data['user_token'], true);
		$data['warehouse_edit_url'] = $this->url->link('warehouse/warehouse/edit', 'user_token=' . $this->session->data['user_token'], true);

		$this->load->model('warehouse/warehouse');
		$warehouses = $this->model_warehouse_warehouse->getWarehouses(['sort' => 'priority', 'order' => 'DESC', 'limit' => 1000]);

		// Per-admin saved filters (Shopify-style tabs).
		$active_filter = $this->getActiveUserFilter('warehouse_stock');

		$this->load->model('user/user_filter');

		$user_id = (int)$this->user->getId();
		$saved_filters = $this->model_user_user_filter->getFilters($user_id, 'warehouse_stock');

		$tab_counts = [
			'all' => $this->model_warehouse_stock->getTotalStock([]),
		];

		foreach ($saved_filters as $saved) {
			$tab_counts['custom_' . $saved['filter_id']] = $this->model_warehouse_stock->getTotalStock($this->buildStockFilterData($saved['conditions']));
		}

		$warehouse_options = [
			['value' => '0', 'label' => $this->language->get('text_all')],
		];

		foreach ($warehouses as $warehouse) {
			$warehouse_options[] = ['value' => (string)$warehouse['warehouse_id'], 'label' => $warehouse['name']];
		}

		$unlimited_options = [
			['value' => '1', 'label' => $this->language->get('text_yes')],
			['value' => '0', 'label' => $this->language->get('text_no')],
		];

		$search = [
			'placeholder' => $this->language->get('text_search_placeholder'),
			'url' => $this->url->link('warehouse/stock/autocomplete', 'user_token=' . $this->session->data['user_token'], true),
		];

		// Active product filter (from a search pick): restore the query in the
		// search box and provide a one-click reset.
		if ($filters['filter_product_id'] !== '') {
			$product = $this->model_warehouse_stock->getStockProduct((int)$filters['filter_product_id']);

			$clear_url = '';

			foreach ($filter_keys as $key) {
				if ($key !== 'filter_product_id' && $filters[$key] !== '') {
					$clear_url .= '&' . $key . '=' . urlencode(html_entity_decode($filters[$key], ENT_QUOTES, 'UTF-8'));
				}
			}

			$search['value'] = $product ? (string)$product['name'] : '#' . $filters['filter_product_id'];
			$search['clear_url'] = $this->url->link('warehouse/stock', 'user_token=' . $this->session->data['user_token'] . $clear_url, true);
		}

		$data['user_filter'] = $this->renderUserFilter('warehouse_stock', 'warehouse/stock', [
			['key' => 'warehouse', 'label' => $this->language->get('entry_warehouse'), 'type' => 'select', 'options' => $warehouse_options],
			['key' => 'name', 'label' => $this->language->get('entry_product'), 'type' => 'text'],
			['key' => 'model', 'label' => $this->language->get('entry_model'), 'type' => 'text'],
			['key' => 'sku', 'label' => $this->language->get('entry_sku'), 'type' => 'text'],
			['key' => 'quantity', 'label' => $this->language->get('entry_quantity'), 'type' => 'number'],
			['key' => 'unlimited', 'label' => $this->language->get('entry_unlimited'), 'type' => 'select', 'options' => $unlimited_options],
		], $tab_counts, '', [], $search);

		$filter_data = array_merge($filters, [
			'start' => ($page - 1) * $this->config->get('config_limit_admin'),
			'limit' => $this->config->get('config_limit_admin'),
		]);

		if ($active_filter) {
			foreach ($this->buildStockFilterData($active_filter['conditions']) as $key => $value) {
				$filter_data[$key] = $value;
			}
		}

		$stock_total = $this->model_warehouse_stock->getTotalStock($filter_data);
		$results = $this->model_warehouse_stock->getStockMatrix($filter_data);

		foreach ($results as $result) {
			$data['rows'][] = [
				'stock_id' => $result['stock_id'],
				'warehouse_id' => $result['warehouse_id'],
				'warehouse_name' => $result['warehouse_name'],
				'product_id' => $result['product_id'],
				'product_name' => $result['product_name'],
				'product_model' => $result['product_model'],
				'variant_id' => $result['variant_id'],
				'variant_sku' => $result['variant_sku'],
				'quantity' => $result['quantity'],
				'unlimited' => $result['unlimited'],
				'lead_time' => $result['lead_time'],
			];
		}

		$url = '';

		foreach ($filter_keys as $key) {
			if ($filters[$key] !== '') {
				$url .= '&' . $key . '=' . urlencode(html_entity_decode($filters[$key], ENT_QUOTES, 'UTF-8'));
			}
		}

		$pagination = new Pagination();
		$pagination->total = $stock_total;
		$pagination->page = $page;
		$pagination->limit = $this->config->get('config_limit_admin');
		$pagination->url = $this->url->link('warehouse/stock', 'user_token=' . $this->session->data['user_token'] . $url . '&page={page}', true);

		$data['pagination'] = $pagination->render();
		$data['results'] = $pagination->renderResults($this->language->get('text_pagination'));

		// Export keeps the active filters (incl. the saved-filter tab).
		$data['export_url'] = str_replace('&amp;', '&', $this->url->link('warehouse/stock/exportCsv', 'user_token=' . $this->session->data['user_token'] . $url, true));
		$data['import_url'] = str_replace('&amp;', '&', $this->url->link('warehouse/stock/importCsv', 'user_token=' . $this->session->data['user_token'], true));

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('warehouse/stock_list', $data));
	}

	/**
	 * Maps saved-filter conditions onto the filter_* keys used by the model.
	 *
	 * Mirrors buildProductFilterData() in catalog/product and buildFilterData()
	 * in sale/order: number fields may carry value_min/value_max ranges or a
	 * single value with an eq/gt/gte/lt/lte operator.
	 */
	private function buildStockFilterData(array $conditions): array {
		$data = [];

		foreach ($conditions as $condition) {
			$field = (string)($condition['field'] ?? '');
			$operator = (string)($condition['operator'] ?? 'eq');
			$value = $condition['value'] ?? '';

			switch ($field) {
				case 'warehouse':
					if ((string)$value !== '') {
						$data['filter_warehouse_id'] = (string)(int)$value;
					}
					break;

				case 'name':
					$data['filter_name'] = (string)$value;
					break;

				case 'model':
					$data['filter_model'] = (string)$value;
					break;

				case 'sku':
					$data['filter_sku'] = (string)$value;
					break;

				case 'quantity':
					if (isset($condition['value_min']) || isset($condition['value_max'])) {
						if (isset($condition['value_min']) && $condition['value_min'] !== '') {
							$data['filter_quantity_min'] = (string)$condition['value_min'];
						}

						if (isset($condition['value_max']) && $condition['value_max'] !== '') {
							$data['filter_quantity_max'] = (string)$condition['value_max'];
						}
					} elseif ((string)$value !== '') {
						if ($operator === 'gt' || $operator === 'gte') {
							$data['filter_quantity_min'] = (string)$value;
						} elseif ($operator === 'lt' || $operator === 'lte') {
							$data['filter_quantity_max'] = (string)$value;
						} else {
							$data['filter_quantity_min'] = (string)$value;
							$data['filter_quantity_max'] = (string)$value;
						}
					}
					break;

				case 'unlimited':
					if ((string)$value !== '') {
						$data['filter_unlimited'] = (string)(int)$value;
					}
					break;
			}
		}

		return $data;
	}
}
