<?php
// Heading
$_['heading_title']    = 'DockerCart SEO Generator';

// Text
$_['text_extension']   = 'Extensions';
$_['text_success']     = 'Success: You have modified DockerCart SEO Generator module!';
$_['text_edit']        = 'Edit Module';
$_['text_enabled']     = 'Enabled';
$_['text_disabled']    = 'Disabled';

// Tabs
$_['tab_general']      = 'General Settings';
$_['tab_statistics']   = 'Statistics';
$_['tab_products']     = 'Products';
$_['tab_categories']   = 'Categories';
$_['tab_manufacturers'] = 'Manufacturers';
$_['tab_information']  = 'Information Pages';
$_['tab_controllers']  = 'Controllers';
$_['text_tab_license'] = 'License';
$_['text_tab_about'] = 'About';

// Entry
$_['entry_status']     = 'Auto-generation Status';
$_['entry_debug']      = 'Debug Mode';
$_['entry_batch_size'] = 'Batch Processing Size';
$_['entry_disable_language_prefix'] = 'Disable Language Prefix';
$_['entry_seo_url_template'] = 'SEO URL Template';
$_['entry_meta_title_template'] = 'Meta Title Template';
$_['entry_meta_description_template'] = 'Meta Description Template';
$_['entry_meta_keyword_template'] = 'Meta Keywords Template';

// Help
$_['help_status']      = 'Automatic generation of SEO URLs and meta tags when creating/editing products, categories, manufacturers and information pages via OpenCart event system.';
$_['help_debug']       = 'Enable logging of all generation operations to database for debugging purposes.';
$_['help_batch_size']  = 'Number of records to process per request. Recommended: 50-100. Decrease for large catalogs.';
$_['help_disable_language_prefix'] = 'When enabled, SEO URLs for all languages will be generated without language prefix (e.g. "product-name" instead of "en-gb-product-name").';

// Placeholders
$_['text_placeholders'] = 'Available Placeholders';
$_['text_common_placeholders'] = 'Common placeholders (for all entity types)';
$_['text_product_placeholders'] = 'Product-specific placeholders';

$_['help_placeholder_name'] = 'Name of product/category/manufacturer/page';
$_['help_placeholder_description'] = 'Description (truncated to 150 characters)';
$_['help_placeholder_store'] = 'Store name';
$_['help_placeholder_city'] = 'Store city';
$_['help_placeholder_category'] = 'Product category name';
$_['help_placeholder_manufacturer'] = 'Manufacturer name';
$_['help_placeholder_model'] = 'Product model';
$_['help_placeholder_sku'] = 'Product SKU';
$_['help_placeholder_price'] = 'Product price';
$_['help_placeholder_stock'] = 'Product availability';

// Statistics
$_['text_products_stats'] = 'Products';
$_['text_categories_stats'] = 'Categories';
$_['text_manufacturers_stats'] = 'Manufacturers';
$_['text_information_stats'] = 'Information Pages';
$_['text_controllers_stats'] = 'Controllers';
$_['text_total'] = 'Total';
$_['text_empty_url'] = 'Without SEO URL';
$_['text_empty_meta'] = 'Without meta tags';
$_['text_by_languages'] = 'By Languages:';

// Controllers
$_['text_controller_route'] = 'Route';
$_['text_controller_title'] = 'Title';
$_['text_select_controllers'] = 'Select Controllers';
$_['help_controllers'] = 'Generate SEO URLs for custom controllers with index() method but without SEO URL. Example: common/home, information/contact, etc.';
$_['button_scan_controllers'] = 'Scan Controllers';
$_['button_add_controller'] = 'Add Controller';
$_['button_remove_controller'] = 'Remove';
$_['text_controller_placeholder'] = 'Example: common/home, information/contact';
$_['text_title_placeholder'] = 'Page Title';
$_['error_controller_exists'] = 'This controller already has SEO URL';
$_['error_controller_not_found'] = 'Controller file not found';
$_['text_found_controllers'] = 'Found Controllers:';
$_['button_generate_selected'] = 'Generate Selected';
$_['button_delete_selected'] = 'Delete Selected';
$_['button_select_all'] = 'Select All';
$_['button_deselect_all'] = 'Deselect All';
$_['text_selected_count'] = 'Selected';
$_['help_mass_generation'] = 'Select controllers using checkboxes and click "Generate Selected" to create SEO URLs for all languages at once.';
$_['text_controllers_table_help'] = 'Edit SEO URLs directly in the table and click save. Use Generate URLs to create URLs for all languages (existing URLs will not be overwritten).';
$_['text_controllers_mass_help'] = 'Select controllers using checkboxes and click "Generate Selected" to create SEO URLs for all languages at once.';
$_['text_how_to_use'] = 'How to use:';
$_['text_how_to_use_1'] = 'Click "Scan Controllers" to find all controllers with index() method';
$_['text_how_to_use_2'] = 'Edit SEO URLs directly in the table for any language';
$_['text_how_to_use_3'] = 'Click the save button next to each field to save changes';
$_['text_how_to_use_4'] = 'Or use the Generate URLs button to auto-generate URLs for all languages at once';
$_['text_how_to_use_5'] = 'Note: Existing URLs will NOT be overwritten when generating';
$_['text_module_settings'] = 'Module Settings';
$_['text_module_version'] = 'Module Version';
$_['text_developer'] = 'Developer';
$_['text_developer_name'] = 'DockerCart Team';
$_['text_contact'] = 'Contact';

// Actions
$_['text_template_settings'] = 'Template Settings';
$_['text_seo_url_auto'] = 'SEO URL is automatically generated from entity name. Only meta fields use templates below.';
$_['text_filters'] = 'Generation Options';
$_['text_actions'] = 'Actions';
$_['text_overwrite_url'] = 'Overwrite existing SEO URLs';
$_['text_overwrite_meta'] = 'Overwrite existing meta tags';
$_['help_overwrite_url'] = 'Warning! When enabled, ALL existing SEO URLs will be overwritten. Uncheck to process only empty records.';
$_['help_overwrite_meta'] = 'Warning! When enabled, ALL existing meta tags will be overwritten. Uncheck to process only empty records.';
$_['text_overwrite_warning'] = 'Warning! When enabling overwrite options, ALL existing data will be modified. Uncheck to process only empty fields.';

$_['button_preview'] = 'Preview';
$_['button_generate_url'] = 'Generate URLs';
$_['button_generate_meta'] = 'Generate Meta Tags';
$_['button_generate_all'] = 'Generate All';
$_['button_save'] = 'Save';
$_['button_cancel'] = 'Cancel';

// Progress
$_['text_processing'] = 'Processing data...';
$_['text_items_processed'] = 'items processed';
$_['text_generation_complete'] = 'Generation completed successfully!';
$_['text_generation_error'] = 'Error during generation!';

// Preview
$_['text_preview_title'] = 'Preview (10 random examples)';
$_['column_name'] = 'Name';
$_['column_seo_url'] = 'SEO URL';
$_['column_meta_title'] = 'Meta Title';
$_['column_meta_description'] = 'Meta Description';
$_['column_meta_keyword'] = 'Meta Keywords';

// Errors
$_['error_permission'] = 'Warning: You do not have permission to modify DockerCart SEO Generator module!';
$_['error_warning']    = 'Warning: Please check the form carefully for errors!';
$_['error_license_invalid'] = 'License verification failed';
$_['error_license_required'] = 'License key is required to use generation feature. Please enter and verify your license key in General Settings tab.';

// Success messages
$_['success_url_generated'] = 'SEO URLs successfully generated! Processed records: %s';
$_['success_meta_generated'] = 'Meta tags successfully generated! Processed records: %s';
$_['success_all_generated'] = 'SEO URLs and meta tags successfully generated! Processed records: %s';

// License
$_['text_license']     = 'License Information';
$_['entry_license_key'] = 'License Key';
$_['entry_public_key'] = 'Public Key';
$_['help_license_key'] = 'Enter your license key purchased from marketplace';
$_['help_public_key']  = 'RSA Public Key for license verification. Provided by vendor.';
$_['text_license_domain'] = 'License bound to domain';
$_['text_verify_public_key_required'] = 'Please save the public key first before verifying the license';
$_['button_verify_license'] = 'Verify License';

// Default Templates - Product
$_['template_product_seo_url'] = '{name}';
$_['template_product_meta_title'] = '{name} {manufacturer} - buy in {store} at {price}';
$_['template_product_meta_description'] = 'Buy {name} from {manufacturer}. {description} Price: {price}. Stock: {stock}. Delivery. {store}';
$_['template_product_meta_keyword'] = '{name}, {manufacturer}, buy {name}, {category}, {store}';

// Default Templates - Category
$_['template_category_seo_url'] = '{name}';
$_['template_category_meta_title'] = '{name} - buy in {store}';
$_['template_category_meta_description'] = '{name} in online store {store}. {description} Wide range, affordable prices, delivery.';
$_['template_category_meta_keyword'] = '{name}, buy {name}, catalog {name}, {store}';

// Default Templates - Manufacturer
$_['template_manufacturer_seo_url'] = '{name}';
$_['template_manufacturer_meta_title'] = '{name} - official dealer in {store}';
$_['template_manufacturer_meta_description'] = '{name} products in {store}. {description} Official warranty, affordable prices.';
$_['template_manufacturer_meta_keyword'] = '{name}, {name} products, official {name} dealer';

// Default Templates - Information
$_['template_information_seo_url'] = '{name}';
$_['template_information_meta_title'] = '{name} | {store}';
$_['template_information_meta_description'] = '{description}';
$_['template_information_meta_keyword'] = '{name}, {store}';

// Notices
$_['text_manufacturer_description_missing'] = 'Note: manufacturer_description table is missing in this store. Manufacturer meta will not be saved by the generator.';
