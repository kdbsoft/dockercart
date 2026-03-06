<?php
/**
 * DockerCart Google Base — English language file
 */

// Heading
$_['heading_title']    = 'DockerCart Google Base';

// Text
$_['text_extension']   = 'Extensions';
$_['text_success']     = 'Success: You have modified DockerCart Google Base module settings!';
$_['text_edit']        = 'Edit DockerCart Google Base';
$_['text_enabled']     = 'Enabled';
$_['text_disabled']    = 'Disabled';
$_['text_default']     = '(Default)';
$_['text_feed_generated'] = 'Feed generated successfully!';
$_['text_cache_cleared'] = 'Feed cache cleared successfully!';

// Tabs
$_['tab_general']      = 'General Settings';
$_['tab_products']     = 'Product Settings';
$_['tab_categories']   = 'Category Mapping';
$_['tab_shipping']     = 'Shipping';
$_['tab_custom_labels'] = 'Custom Labels';
$_['tab_license']      = 'License';

// Entry - General
$_['entry_status']     = 'Module Status';
$_['entry_cache_hours'] = 'Cache Duration (Hours)';
$_['entry_max_file_size'] = 'Max File Size (MB)';
$_['entry_max_products'] = 'Max Products per File';
$_['entry_currency']   = 'Price Currency';
$_['entry_image_width'] = 'Image Width (px)';
$_['entry_image_height'] = 'Image Height (px)';
$_['entry_separate_languages'] = 'Separate Feed per Language';
$_['entry_separate_stores'] = 'Separate Feed per Store';
$_['entry_debug']      = 'Debug Mode';

// Entry - Products
$_['entry_condition']  = 'Default Condition';
$_['entry_include_disabled'] = 'Include Disabled Products';
$_['entry_include_out_of_stock'] = 'Include Out of Stock Products';
$_['entry_brand_source'] = 'Brand Source';
$_['entry_brand_default'] = 'Default Brand';
$_['entry_exclude_products'] = 'Exclude Products';
$_['entry_exclude_categories'] = 'Exclude Categories';

// Exclusion UI
$_['text_product_search'] = 'Type to search products...';
$_['text_category_search'] = 'Type to search categories...';
$_['text_no_excluded_products'] = 'No excluded products';
$_['text_no_excluded_categories'] = 'No excluded categories';

// Entry - Shipping
$_['entry_shipping_enabled'] = 'Include Shipping Information';
$_['entry_shipping_price'] = 'Shipping Price';
$_['entry_shipping_country'] = 'Shipping Country (ISO)';

// Entry - Categories
$_['entry_category_mapping'] = 'Google Category Mapping';

// Entry - Custom Labels
$_['entry_custom_label_0'] = 'Custom Label 0';
$_['entry_custom_label_1'] = 'Custom Label 1';
$_['entry_custom_label_2'] = 'Custom Label 2';
$_['entry_custom_label_3'] = 'Custom Label 3';
$_['entry_custom_label_4'] = 'Custom Label 4';

// Entry - License
$_['entry_license_key'] = 'License Key';
$_['entry_public_key'] = 'Public Key';

// Help
$_['help_status']      = 'Enable or disable Google Base feed generation';
$_['help_cache_hours'] = 'How long to cache the feed. Default: 24 hours.';
$_['help_max_file_size'] = 'Maximum file size before splitting. Google limit: 50MB';
$_['help_max_products'] = 'Maximum products per file before splitting.';
$_['help_currency']    = 'Select currency for prices in the feed';
$_['help_image_width'] = 'Recommended image width: minimum 800px';
$_['help_image_height'] = 'Recommended image height: minimum 800px';
$_['help_separate_languages'] = 'Create separate feed for each language (google-base-en.xml, google-base-ru.xml, ...)';
$_['help_separate_stores'] = 'Create separate feed for each store';
$_['help_debug']       = 'Enable debug logging for troubleshooting';
$_['help_condition']   = 'Default product condition: new, refurbished, or used';
$_['help_include_disabled'] = 'Include products with status = 0';
$_['help_include_out_of_stock'] = 'Include products with quantity = 0';
$_['help_brand_source'] = 'Extract brand from manufacturer or use default value';
$_['help_brand_default'] = 'Default brand name if manufacturer is empty';
$_['help_exclude_products'] = 'Search and select products to exclude from the feed';
$_['help_exclude_categories'] = 'Search and select categories to exclude from the feed';
$_['help_shipping_enabled'] = 'Include shipping information in the feed';
$_['help_shipping_price'] = 'Fixed shipping price (example: "10.00 USD")';
$_['help_shipping_country'] = 'Two-letter country code (example: US, DE, RU)';
$_['help_category_mapping'] = 'Map OpenCart categories to Google categories. Format: opencart_id = google_category_id (one per line).';
$_['help_custom_label'] = 'Custom labels for Google Merchant Center. Supports placeholders: {manufacturer}, {category}, {sku}, {model}';
$_['help_license_key'] = 'Enter the license key you purchased from the store';
$_['help_public_key']  = 'RSA public key for license verification';

// Condition
$_['text_condition_new'] = 'New';
$_['text_condition_refurbished'] = 'Refurbished';
$_['text_condition_used'] = 'Used';

// Brand
$_['text_brand_manufacturer'] = 'From Manufacturer';
$_['text_brand_default'] = 'Use Default';

// Stats
$_['text_statistics']  = 'Feed Statistics';
$_['text_total_products'] = 'Total Products';
$_['text_enabled_products'] = 'Enabled Products';
$_['text_in_stock_products'] = 'In Stock Products';
$_['text_last_generated'] = 'Last Generated';
$_['text_file_size']   = 'File Size';
$_['text_feed_url']    = 'Feed URL';
$_['text_not_generated'] = 'Not generated yet';

// Buttons
$_['button_generate']  = 'Generate Now';
$_['button_clear_cache'] = 'Clear Cache';
$_['button_preview']   = 'Preview';
$_['button_verify_license'] = 'Verify License';
$_['button_save_license'] = 'Save License';

// Info
$_['text_info']        = '<strong>DockerCart Google Base</strong> generates Google Merchant Center compatible XML feed using streaming XML (XMLWriter) for minimal memory consumption.';

// Errors
$_['error_permission'] = 'Warning: You do not have permission to modify DockerCart Google Base!';
$_['error_cache_hours'] = 'Cache duration must be between 1 and 168 hours!';
$_['error_max_file_size'] = 'Max file size must be between 1 and 50 MB!';
$_['error_max_products'] = 'Max products must be between 1,000 and 1,000,000!';
$_['error_generation'] = 'Error generating feed';
$_['error_license_required'] = 'License key is required to use this module';
$_['error_license_invalid'] = 'License key is invalid or expired';
