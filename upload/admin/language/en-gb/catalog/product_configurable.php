<?php
$_['heading_title'] = 'Configurable Products';

$_['text_success'] = 'Success: You have modified configurable products!';
$_['text_success_variant'] = 'Variant saved successfully.';
$_['text_success_default'] = 'Default variant updated.';
$_['text_success_axes'] = 'Configurable options updated.';
$_['text_success_generate'] = 'Generated %s variants.';
$_['text_variants'] = 'Variants';
$_['text_save_product_first'] = 'Save the product first to add variants.';
$_['text_no_axes'] = 'Select configurable options (e.g. Size, Color) above to define variant axes.';
$_['text_select'] = '--- Select ---';
$_['text_confirm_delete_variant'] = 'Are you sure you want to delete this variant?';
$_['text_confirm_generate'] = 'This will generate all possible combinations of the configured options. Existing variants with the same combination will not be duplicated. Are you sure?';
$_['text_load_error'] = 'Failed to load variant data. Please try again.';

$_['entry_configurable_axes'] = 'Configurable Options (variant axes)';
$_['entry_select_option'] = 'Search option...';

$_['column_sku'] = 'SKU';
$_['column_price'] = 'Price';
$_['column_quantity'] = 'Quantity';
$_['column_image'] = 'Image';
$_['column_default'] = 'Default';
$_['column_status'] = 'Status';
$_['column_action'] = 'Action';

$_['button_add_variant'] = 'Add Variant';
$_['button_generate'] = 'Generate Combinations';
$_['button_save'] = 'Save';


$_['help_generate'] = 'Auto-generate all possible combinations from the configured options';

$_['entry_option_mode'] = 'Option Mode';
$_['text_mode_simple'] = 'Simple Options';
$_['text_mode_combined'] = 'Combined Variants';
$_['text_confirm_switch_to_simple'] = 'Switching to Simple Options will delete all variants and configured axes. Continue?';
$_['text_switch_to_simple'] = 'Switch to Simple Options';
$_['text_switch_to_combined'] = 'Switch to Combined Variants';

$_['error_permission'] = 'Warning: You do not have permission to modify configurable products!';
$_['error_product_id'] = 'Product ID is required!';
$_['error_variant_id'] = 'Variant ID is required!';
$_['error_variant_values'] = 'Please select values for all option axes.';
$_['error_no_axes'] = 'Please configure at least one option axis before generating variants.';
$_['error_variant_duplicate'] = 'A variant with this combination already exists.';
$_['error_variant_axes_mismatch'] = 'The number of selected values does not match the configured axes.';
$_['error_variant_price_numeric'] = 'Variant price must be a valid number.';
$_['error_variant_quantity_numeric'] = 'Variant quantity must be a valid number.';
$_['error_axis_is_simple_option'] = 'This option is already used as a simple option with price or stock. Remove it from simple options first.';
$_['error_variant_value_unknown_axis'] = 'One of the values belongs to an option that is not configured as an axis.';
$_['error_invalid_mode'] = 'Invalid option mode.';
$_['text_success_mode'] = 'Option mode updated.';
