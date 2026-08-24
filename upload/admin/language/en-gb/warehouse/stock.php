<?php
// Warehouse Stock
$_['heading_title'] = 'Stock by Warehouse';
$_['text_list'] = 'Stock Matrix';
$_['text_success'] = 'Success: Stock updated!';
$_['text_no_results'] = 'No stock rows found';
$_['text_home'] = 'Home';
$_['text_pagination'] = 'Showing %d to %d of %d (%d pages)';
$_['text_types'] = [];
$_['text_recalculate'] = 'Recalculate totals';
$_['text_recalculated'] = 'Recalculated %d product(s), %d drift(s) corrected.';
$_['text_unlimited'] = 'Unlimited';
$_['button_update'] = 'Update';
$_['button_recalculate'] = 'Recalculate';
$_['column_warehouse'] = 'Warehouse';
$_['column_product'] = 'Product';
$_['column_model'] = 'Product Code';
$_['column_variant'] = 'Variant';
$_['column_quantity'] = 'Quantity';
$_['column_reserved'] = 'Reserved';
$_['column_available'] = 'Available';
$_['column_unlimited'] = 'Unlimited';
$_['column_lead_time'] = 'Lead time (d)';
$_['entry_warehouse'] = 'Warehouse';
$_['entry_product'] = 'Product';
$_['entry_model'] = 'Product Code';
$_['entry_sku'] = 'SKU';
$_['entry_quantity'] = 'Quantity';
$_['entry_unlimited'] = 'Unlimited';
$_['entry_recalculate'] = 'Recalculate';
$_['text_all'] = 'All';
$_['text_search_placeholder'] = 'Search stock by product name or code...';
$_['text_network_error'] = 'Network error.';
$_['error_permission'] = 'Warning: You do not have permission to modify stock!';

// CSV export / import
$_['button_export_csv'] = 'Export CSV';
$_['button_import_csv'] = 'Import CSV';
$_['heading_import_csv'] = 'Import Stock CSV';
$_['button_import'] = 'Import';
$_['button_cancel'] = 'Cancel';
$_['text_import_format'] = 'Update-only import for Quantity, Unlimited and Lead time. A row is found by stock_id, or by warehouse_id + product_id + variant_id; missing rows are reported and never created. An empty cell leaves the field unchanged. quantity — number ≥ 0; unlimited — 0/1, yes/no or true/false; lead_time — whole days ≥ 0. If any row fails validation nothing is imported.';
$_['text_import_result'] = 'Success: Stock CSV imported — updated: %d, skipped (no changes): %d.';
$_['text_import_line'] = 'Line %d: %s';
$_['text_import_more'] = '...and %d more error(s).';
$_['error_import_upload'] = 'Warning: Please upload a valid CSV file!';
$_['error_import_size'] = 'Warning: The file exceeds %d MB!';
$_['error_import_header'] = 'Warning: The CSV header must contain stock_id (or warehouse_id, product_id, variant_id) plus quantity, unlimited and/or lead_time!';
$_['error_import_empty'] = 'Warning: No data rows found in the file!';
$_['error_import_too_many'] = 'Warning: The file contains more than %d data rows!';
$_['error_import_stock_missing'] = 'stock row #%d does not exist';
$_['error_import_position_missing'] = 'no stock row for warehouse #%d, product #%d, variant #%d';
$_['error_import_quantity'] = 'invalid quantity "%s" (number ≥ 0 expected)';
$_['error_import_unlimited'] = 'invalid unlimited "%s" (0/1, yes/no, true/false expected)';
$_['error_import_lead_time'] = 'invalid lead time "%s" (whole number ≥ 0 expected)';
$_['error_import_duplicate'] = 'duplicate row for stock row #%d';

// Warehouse card modal
$_['text_card_address'] = 'Address & Contacts';
$_['text_card_map'] = 'Open map';
$_['text_card_pickup'] = 'Self-Pickup';
$_['text_card_supplier'] = 'Dropship Supplier';
$_['text_card_schedule'] = 'Working Hours';
$_['text_card_closed'] = 'Closed';
$_['text_card_stats'] = 'Stock Summary';
$_['text_card_positions'] = 'Positions';
$_['text_card_total_quantity'] = 'Total Quantity';
$_['button_edit'] = 'Edit';

// Product card modal
$_['text_pcard_here'] = 'In this warehouse';
$_['text_pcard_total'] = 'All warehouses';
$_['text_pcard_reserved'] = 'Reserved';
$_['text_pcard_available'] = 'Available';
$_['text_pcard_attributes'] = 'Attributes';
$_['text_pcard_description'] = 'Description';