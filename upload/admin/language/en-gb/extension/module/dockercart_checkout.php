<?php
// Heading
$_["heading_title"] = "DockerCart Checkout";

// Text
$_["text_extension"] = "Extensions";
$_["text_success"] = "Success: You have modified DockerCart Checkout settings!";
$_["text_edit"] = "Edit DockerCart Checkout";
$_["text_enabled"] = "Enabled";
$_["text_disabled"] = "Disabled";
$_["text_yes"] = "Yes";
$_["text_no"] = "No";

// Tab titles
$_["tab_general"] = "General";
$_["tab_blocks"] = "Checkout Blocks";
$_["tab_design"] = "Design & Theme";
$_["tab_fields"] = "Form Fields";
$_["tab_advanced"] = "Advanced";
$_["tab_license"] = "License (GPL-3.0)";

// General Settings
$_["entry_status"] = "Module Status";
$_["help_status"] = "Enable or disable the DockerCart Checkout module";

$_["entry_redirect_standard"] = "Redirect Standard Checkout";
$_["help_redirect_standard"] =
    "Automatically redirect users from the standard OpenCart checkout (checkout/checkout) to DockerCart Checkout";

$_["entry_show_progress"] = "Show Progress Bar";
$_["help_show_progress"] =
    "Display a visual progress indicator at the top of the checkout";

$_["entry_geo_detect"] = "Auto-detect Location";
$_["help_geo_detect"] =
    "Automatically detect customer city/region based on IP address";

$_["entry_guest_create_account"] = "Guest Create Account Option";
$_["help_guest_create_account"] =
    "Allow guest customers to optionally create an account during checkout";
$_["entry_comment"] = "Order Comment";

$_["entry_default_country"] = "Default Country";
$_["help_default_country"] =
    "Pre-selected country when the customer has not chosen one yet. This allows shipping methods to be displayed immediately on page load.";
$_["entry_default_zone"] = "Default Region / State";
$_["help_default_zone"] =
    "Pre-selected region/state when the customer has not chosen one yet.";

// Theme Settings
$_["entry_theme"] = "Checkout Theme";
$_["help_theme"] = "Select the visual theme for the checkout page";
$_["text_theme_light"] = "Light";
$_["text_theme_dark"] = "Dark";
$_["text_theme_custom"] = "Custom (use CSS below)";

$_["entry_custom_css"] = "Custom CSS";
$_["help_custom_css"] =
    "Add custom CSS styles to customize the checkout appearance";

$_["entry_custom_js"] = "Custom JavaScript";
$_["help_custom_js"] =
    "Add custom JavaScript code (ES6+) for additional functionality";

$_["entry_journal3_compat"] = "Journal 3 Compatibility";
$_["help_journal3_compat"] =
    "Enable special styling adjustments for Journal 3 theme";

// Form Fields
$_["entry_require_telephone"] = "Require Telephone";
$_["entry_require_address2"] = "Require Address Line 2";
$_["entry_require_postcode"] = "Require Postcode";
$_["entry_require_company"] = "Require Company";
$_["entry_show_company"] = "Show Company Field";
$_["entry_show_tax_id"] = "Show Tax ID Field";

$_["help_required_fields"] =
    "Configure which fields are required during checkout";

// Blocks
$_["text_blocks_title"] = "Manage Checkout Blocks";
$_["text_blocks_info"] =
    "Drag and drop to reorder checkout blocks. Click Configure to manage fields in each block.";
$_["text_configure"] = "Configure";
$_["text_settings"] = "Settings";
$_["column_block_name"] = "Block Name";
$_["column_block_enabled"] = "Enabled";
$_["column_block_sort"] = "Sort Order";
$_["column_block_collapsible"] = "Collapsible";
$_["text_field_list"] = "Field List";
$_["text_no_fields"] = "No fields configured";
$_["text_settings_saved"] = "Settings saved successfully";
$_["text_cancel"] = "Cancel";
$_["text_save"] = "Save";

$_["block_cart"] = "Cart Summary";
$_["block_shipping_address"] = "Shipping Address";
$_["block_payment_address"] = "Payment Address";
$_["block_shipping_method"] = "Shipping Method";
$_["block_payment_method"] = "Payment Method";
$_["block_coupon"] = "Coupon / Voucher / Reward Points";
$_["block_comment"] = "Order Comment";
$_["block_agree"] = "Terms & Conditions";
$_["block_custom_fields"] = "Custom Fields";
$_["block_recommended"] = "Recommended Products";
$_["block_store_info"] = "Store Information";
$_["block_custom_html"] = "Custom HTML Block";

// Admin placeholders & block labels
$_["block_customer_details"] = "Customer Details";
$_["placeholder_email"] = "you@example.com";
$_["placeholder_firstname"] = "First Name";
$_["placeholder_lastname"] = "Last Name";
$_["placeholder_telephone"] = "+1 (555) 000-0000";
$_["placeholder_fax"] = "Fax";
$_["placeholder_company"] = "Company Name";
$_["placeholder_address_1"] = "123 Main Street";
$_["placeholder_address_2"] = "Apartment, suite, etc.";
$_["placeholder_city"] = "City";
$_["placeholder_postcode"] = "10001";
$_["placeholder_country"] = "Select Country";
$_["placeholder_zone"] = "Select State/Province";
$_["placeholder_payment_firstname"] = "First Name";
$_["placeholder_payment_lastname"] = "Last Name";
$_["placeholder_payment_company"] = "Company";
$_["placeholder_payment_address_1"] = "123 Main Street";
$_["placeholder_payment_address_2"] = "Apartment, suite, etc.";
$_["placeholder_payment_city"] = "City";
$_["placeholder_payment_postcode"] = "10001";
$_["text_comment_placeholder"] =
    "Notes about your order, e.g. special notes for delivery.";

// Advanced Settings
$_["entry_cache_ttl"] = "Template Cache TTL";
$_["help_cache_ttl"] =
    "Cache lifetime in seconds (0 = no cache, useful for development). Max: 86400";

$_["entry_recaptcha_enabled"] = "Enable reCAPTCHA";
$_["help_recaptcha"] = "Enable Google reCAPTCHA v3 for spam protection";

$_["entry_recaptcha_site_key"] = "reCAPTCHA Site Key";
$_["entry_recaptcha_secret_key"] = "reCAPTCHA Secret Key";

// Method Overrides
$_["tab_method_overrides"] = "Method Overrides";
$_["text_method_overrides"] =
    "Shipping & Payment Method Name/Description Overrides";
$_["text_method_overrides_help"] =
    "Enable and customize names and descriptions for specific shipping and payment methods. Leave fields empty to use default values.";
$_["text_shipping_methods"] = "Shipping Methods";
$_["text_payment_methods"] = "Payment Methods";
$_["text_method_code"] = "Method Code";
$_["text_module"] = "Module";
$_["text_method_enabled"] = "Override Enabled";
$_["text_custom_title"] = "Custom Title";
$_["text_custom_description"] = "Custom Description";
$_["text_default_title"] = "Default Title";
$_["text_no_methods_available"] =
    "No methods available. Please ensure shipping/payment extensions are installed and enabled.";
$_["text_address_fields"] = "Address Fields";
$_["text_field_company"] = "Company";
$_["text_field_address_1"] = "Address Line 1";
$_["text_field_address_2"] = "Address Line 2";
$_["text_field_city"] = "City";
$_["text_field_postcode"] = "Postcode";
$_["text_field_country"] = "Country";
$_["text_field_zone"] = "Region / State";
$_["help_shipping_fields"] =
    "Select which address fields should be visible when this shipping method is selected. Hidden fields will be auto-filled with default values. Example: for self-pickup, hide address and postcode; for Nova Poshta, hide postcode only.";
$_["text_req_abbr"] = "req";
$_["help_shipping_required"] =
    "Mark address fields as required for this shipping method. A red asterisk will be shown next to required fields.";
$_["help_method_overrides"] =
    "Enable override for a specific method and enter custom title/description. If override is not enabled, the original method name and description will be used.";

$_["entry_debug"] = "Debug Mode";
$_["help_debug"] = "Enable debug logging for troubleshooting";

// License
$_["text_license"] = "GNU GPL v3 License";
$_["entry_license_key"] = "License Key";
$_["help_license_key"] = "License key is not required in the GPL version.";
$_["entry_public_key"] = "Public Key";
$_["help_public_key"] =
    "Public key verification is not used in the GPL version.";
$_["text_license_domain"] = "License type";
$_["button_verify_license"] = "Verify License";
$_["button_save_license"] = "Save License";

$_["text_license_valid"] = "GPL-3.0 (Free)";
$_["text_license_invalid"] = "License key is not required in the GPL version";
$_["text_license_checking"] =
    "License key verification is disabled in the GPL version";

// Buttons
$_["button_save"] = "Save";
$_["button_cancel"] = "Cancel";
$_["button_apply"] = "Apply";

// Info
$_["text_info"] =
    "<strong>DockerCart Checkout</strong> is a free one-page checkout solution for DockerCart.<br>" .
    "<strong>License:</strong> GNU GPL v3.0<br><br>" .
    "<strong>Features:</strong><br>" .
    "✓ Modern, fast, single-page checkout<br>" .
    "✓ Mobile-first responsive design<br>" .
    "✓ AJAX-powered (no page reloads)<br>" .
    "✓ Drag & Drop block configuration<br>" .
    "✓ Guest checkout with optional registration<br>" .
    "✓ All standard shipping/payment methods supported<br>" .
    "✓ Coupons, vouchers, reward points<br>" .
    "✓ Phone number masking & real-time validation<br>" .
    "✓ Light/Dark themes + custom CSS<br>" .
    "✓ Journal 3 compatibility<br>" .
    "✓ No OCMOD — pure Event System installation";

// Errors
$_["error_permission"] =
    "Warning: You do not have permission to modify DockerCart Checkout!";
$_["error_cache_ttl"] = "Cache TTL must be between 0 and 86400 seconds!";
$_["error_license_required"] = "License key is not required in the GPL version";
$_["error_license_invalid"] =
    "License key validation is disabled in the GPL version";

// Errors & messages used by AJAX endpoints
$_["error_invalid_blocks_data"] = "Invalid blocks data";
$_["error_license_key_empty"] = "License key is empty";
$_["error_license_lib_not_found"] = "License library not found";
$_["error_license_class_not_found"] = "DockercartLicense class not found";
$_["error_exception"] = "Error: %s";
$_["error_missing_block_index_or_fields"] = "Missing block_index or fields.";
$_["error_block_index_not_found"] = "Block index not found";
$_["text_block_fields_saved"] = "Block fields saved successfully";
$_["text_layout_name"] = "DockerCart Checkout";
// Modal / UI strings
$_["text_block_settings"] = "Block Settings";
$_["text_modal_instructions"] =
    "Drag fields to reorder • Toggle switches to show/hide or set as required";

// Rows / Modal UI
$_["text_block_not_found"] = "Block not found.";
$_["button_add_row"] = "Add Row";
$_["text_rows_configuration"] = "Rows Configuration";
$_["text_row"] = "Row";
$_["text_columns"] = "Columns:";
$_["text_visible"] = "Visible";
$_["text_required"] = "Required";
$_["text_no_fields_in_row"] = "No fields in this row";
$_["text_no_rows_configured"] = 'No rows configured. Click "Add Row" to start.';
// JS/UX messages
$_["error_remove_non_empty_row"] =
    "Cannot remove a non-empty row. Remove all fields first.";
$_["confirm_are_you_sure"] = "Are you sure?";
