<?php

$_['heading_title']    = 'DockerCart Filter';

$_['text_extension']   = 'Extensions';
$_['text_success']     = 'Success: You have modified DockerCart Filter module!';
$_['text_edit']        = 'Edit DockerCart Filter Module';
$_['text_module_subtitle'] = 'Configure filtering behavior, license, and catalog display settings';
$_['text_enabled']     = 'Enabled';
$_['text_disabled']    = 'Disabled';
$_['text_cache_cleared'] = 'Filter cache has been cleared successfully!';
$_['text_info']        = '<strong>Usage:</strong><br>1. Go to Design > Layouts<br>2. Add module to content_top/content_bottom for horizontal layout (with select)<br>3. Or add to column_left/column_right for vertical layout (with checkboxes)<br>4. The filter will automatically detect layout position and render accordingly<br><br><strong>Features:</strong><br>✓ Responsive mobile-friendly design<br>✓ SEO-friendly URLs with noindex rules<br>✓ Instant or Button mode filtering<br>✓ Multiple language support<br>✓ Custom CSS styling<br>✓ Dark theme support';

$_['entry_status']     = 'Status';
$_['entry_cache_time'] = 'Cache Time (seconds)';
$_['entry_filter_mode'] = 'Filter Mode';
$_['entry_seo_mode']   = 'SEO Mode (Links & noindex)';
$_['entry_items_limit'] = 'Items Display Limit';
$_['entry_theme']      = 'Filter Theme';
$_['entry_custom_css'] = 'Custom CSS';
$_['entry_mobile_breakpoint'] = 'Mobile Breakpoint (px)';

$_['help_cache_time']  = 'How long to cache filter data. Default: 3600 seconds (1 hour)';
$_['help_filter_mode'] = '<strong>Instant:</strong> Filter applies immediately when option is clicked.<br><strong>Button:</strong> User can select multiple options and then click "Apply Filter" button.';
$_['help_seo_mode']    = 'When enabled: Filter items become SEO-friendly links, noindex rules applied to filtered pages, dynamic H1 based on filters.<br><strong>Indexing rules:</strong><br>• Manufacturer only = INDEX<br>• 1 attribute or option = INDEX<br>• Manufacturer + 1 attribute/option = INDEX<br>• Price filter = ALWAYS noindex<br>• Other combinations = noindex';
$_['help_items_limit'] = 'Number of filter items to show initially. If more items exist, a "Show More" link will appear. Set to 0 to show all items. Default: 10';
$_['help_theme']       = 'Select the color theme for the filter widget. Light theme is recommended for light backgrounds, dark theme for dark backgrounds.';
$_['help_custom_css']  = 'Add your custom CSS styles here. These will be applied to the filter widget on the catalog side. Use this to override default styles or add theme-specific customizations.';
$_['help_mobile_breakpoint'] = 'Screen width (in pixels) at which the filter switches to mobile/responsive layout. Default: 768px. Common values: 480px (phones), 768px (tablets), 1024px (small laptops).';

$_['text_mode_instant'] = 'Instant (auto-apply on click)';
$_['text_mode_button']  = 'Button (apply after selection)';
$_['text_theme_light']  = 'Light';
$_['text_theme_dark']   = 'Dark';

$_['button_clear_cache'] = 'Clear Cache';
$_['button_apply']       = 'Apply';
$_['button_verify_license'] = 'Verify License';

$_['text_license']       = 'License Information';
$_['text_module_settings'] = 'Module Settings';
$_['text_tab_general'] = 'General Settings';
$_['text_tab_license'] = 'License';
$_['text_tab_about'] = 'About';
$_['text_tab_general_subtitle'] = 'Core filter behavior and display configuration';
$_['text_tab_license_subtitle'] = 'License key and cryptographic verification';
$_['text_tab_about_subtitle'] = 'Module info, version details, and developer information';
$_['text_developer'] = 'Developer';
$_['text_developer_name'] = 'DockerCart Team';
$_['text_contact'] = 'Contact';
$_['entry_license_key']  = 'License Key';
$_['entry_public_key']   = 'Public Key';
$_['help_license_key']   = 'Enter your license key purchased from marketplace';
$_['help_public_key']    = 'RSA Public Key for license verification. Provided by vendor.';
$_['text_license_domain'] = 'License bound to domain';
$_['text_verify_public_key_required'] = 'Please save the public key first before verifying the license';
$_['text_license_key_required'] = 'Please enter license key';
$_['text_public_key_required'] = 'Please enter public key';
$_['text_verifying'] = 'Verifying...';
$_['text_valid'] = 'Valid';
$_['text_invalid'] = 'Invalid';
$_['text_license_status_label'] = 'Status';
$_['text_license_active'] = 'Active';
$_['text_license_domain_label'] = 'Domain';
$_['text_license_expires_label'] = 'Expires';
$_['text_license_type_label'] = 'Type';
$_['text_license_lifetime'] = 'Lifetime License';
$_['text_license_id_label'] = 'License ID';
$_['text_error_label'] = 'Error';

$_['entry_attributes']   = 'Filterable Attributes';
$_['entry_options']      = 'Filterable Options';
$_['entry_attribute_separators'] = 'Attribute Value Separators';
$_['entry_debug']        = 'Debug Mode';
$_['help_attributes']    = 'Uncheck attributes that should not appear in the filter';
$_['help_options']       = 'Uncheck options that should not appear in the filter';
$_['help_attribute_separators'] = 'Characters used to split multiple attribute values. Default: ,;| (comma, semicolon, pipe). For example, if attribute value is "Red,Blue;Green|Yellow", it will be split into 4 separate filter values.';
$_['help_debug']         = 'Enable console.log() in JavaScript and error_log() in PHP for debugging. Disable in production for better performance.';

$_['text_no_attributes'] = 'No attributes found';
$_['text_no_options']    = 'No options found';

$_['error_permission']   = 'Warning: You do not have permission to modify DockerCart Filter module!';
$_['error_license_required'] = 'License key is required to use this module';
$_['error_license_invalid']  = 'Invalid or expired license key';
$_['error_license_format']   = 'Invalid license key format';
