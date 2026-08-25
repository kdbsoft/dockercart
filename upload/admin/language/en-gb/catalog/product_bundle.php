<?php
// Bundle-specific strings only. Keys shared with catalog/product.php
// (heading_title, entry_name, text_add/edit, error_name, ...) must not be
// defined here: this file is loaded after catalog/product by the product
// form controller and would override them (e.g. the product Name label
// showing "Bundle Name"). The standalone bundle CRUD pages are unused.
$_['text_percentage']       = 'Percentage';
$_['text_fixed']            = 'Fixed Amount';
$_['text_enabled']          = 'Enabled';
$_['text_disabled']         = 'Disabled';
$_['text_no_results']       = 'No results!';
$_['text_no_name']          = '(No Name)';
$_['text_confirm']          = 'Are you sure?';
$_['text_select_product']   = 'Search product by name...';

$_['text_add_product_bundle_subtitle'] = 'Create a new product bundle';

$_['text_edit_product_bundle_subtitle'] = 'Edit product bundle contents';



$_['column_products']       = 'Products';
$_['column_discount']       = 'Discount';
$_['column_status']         = 'Status';
$_['column_date_start']     = 'Date Start';
$_['column_date_end']       = 'Date End';
$_['column_sort_order']     = 'Sort Order';
$_['column_action']         = 'Action';

$_['entry_product']         = 'Products';
$_['entry_discount_type']   = 'Discount Type';
$_['entry_discount_value']  = 'Discount Value';
$_['entry_date_start']      = 'Date Start';
$_['entry_date_end']        = 'Date End';
$_['entry_status']          = 'Status';
$_['entry_sort_order']      = 'Sort Order';
$_['entry_auto_renew']      = 'Auto-renew';
$_['help_auto_renew']       = 'When the bundle expires, a new one will be created automatically with the same duration.';
$_['entry_store']           = 'Stores';

$_['help_name']             = '(Optional) Bundle name for identification';
$_['help_product']          = 'Select at least 2 products for the bundle';
$_['help_discount_type']    = 'Percentage discount or fixed amount off';
$_['help_discount_value']   = 'For percentage enter a number (e.g. 10 for 10%). For fixed enter the amount.';
$_['help_date_start']       = 'Start date for bundle availability';
$_['help_date_end']         = 'End date for bundle availability';

$_['error_warning']         = 'Warning: Please check the form carefully for errors!';
$_['error_products']        = 'You must select at least 2 products!';
$_['error_discount_value']  = 'Discount Value must be greater than 0!';
$_['error_date']            = 'Date End must be after Date Start!';
$_['error_invalid_sort_order'] = 'Invalid sort order value!';
$_['error_invalid_date']      = 'Invalid date format!';
$_['error_copy_no_name']      = 'Copy is only available for bundles with a name.';
