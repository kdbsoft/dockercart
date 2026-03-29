<?php
// Heading
$_['heading_title'] = 'DockerCart Import YML';

// Text
$_['text_extension'] = 'Extensions';
$_['text_success'] = 'Success: You have modified DockerCart Import YML module!';
$_['text_edit'] = 'Edit DockerCart Import YML';
$_['text_module_subtitle'] = 'Manage YML import settings, license, and profile execution workflows';
$_['text_enabled'] = 'Enabled';
$_['text_disabled'] = 'Disabled';
$_['text_add_profile'] = 'Add Profile';
$_['text_edit_profile'] = 'Edit Profile';
$_['text_delete_profile'] = 'Delete Profile';
$_['text_confirm_delete'] = 'Are you sure you want to delete this profile?';
$_['text_confirm_import'] = 'Run import for this profile now?';
$_['text_import_success'] = 'Import completed successfully';
$_['text_mode_add'] = 'Add only';
$_['text_mode_update'] = 'Add + Update';
$_['text_mode_update_only'] = 'Update only (no add)';
$_['text_mode_update_price_qty_only'] = 'Update prices and stock only';
$_['text_mode_replace'] = 'Delete all then import';
$_['text_license_valid'] = 'License verified successfully.';
$_['text_license_invalid'] = 'License verification failed.';
$_['text_import_running'] = 'Import is running...';
$_['text_import_file_processing'] = 'Processing import file...';
$_['text_processed'] = 'Processed';
$_['text_added'] = 'Added';
$_['text_updated'] = 'Updated';
$_['text_skipped'] = 'Skipped';
$_['text_errors'] = 'Errors';
$_['text_total_offers_label'] = 'Products in feed';
$_['text_processed_offers_label'] = 'Processed products';
$_['text_added_label'] = 'Added';
$_['text_updated_label'] = 'Updated';
$_['text_skipped_label'] = 'Skipped';
$_['text_errors_label'] = 'Errors';
$_['text_categories_in_feed_label'] = 'Categories in feed';
$_['text_categories_mapped_label'] = 'Categories mapped';
$_['text_categories_created_label'] = 'Categories created';
$_['text_categories_skipped_label'] = 'Categories skipped';
$_['text_response'] = 'Response';
$_['text_no_profiles'] = 'No profiles';

// Entry
$_['entry_status'] = 'Status';
$_['entry_profile_name'] = 'Profile Name';
$_['entry_feed_url'] = 'Feed URL';
$_['entry_profile_store'] = 'Store';
$_['entry_profile_language'] = 'Language';
$_['entry_profile_currency'] = 'Currency';
$_['entry_default_category'] = 'Default Category';
$_['entry_load_categories'] = 'Load categories from feed';
$_['entry_allow_zero_price'] = 'Import zero-price products';
$_['entry_import_mode'] = 'Import Mode';
$_['entry_profile_status'] = 'Status';
$_['entry_cron_command'] = 'Cron command';
$_['entry_license_key'] = 'License key';
$_['entry_public_key'] = 'Public key';

// Help
$_['help_feed_url'] = 'Direct URL to YML feed (Yandex Market Language)';
$_['help_import_mode'] = 'Add only: creates new products; Add + Update: updates existing and creates new; Update only: updates existing products and skips new ones; Update prices and stock only: updates only price and quantity for existing products; Delete all then import: clears all store products before import.';
$_['help_load_categories'] = 'If enabled, categories from feed will be imported. If Default Category is set, imported feed categories will be created under it as child categories.';
$_['help_allow_zero_price'] = 'If enabled, products with a price of 0 will be imported. Disabled by default.';

// Buttons
$_['button_import_now'] = 'Import now';
$_['button_save'] = 'Save';
$_['button_cancel'] = 'Cancel';
$_['button_verify_license'] = 'Verify license';

// Tab
$_['tab_general'] = 'General';
$_['tab_license'] = 'License';
$_['tab_profiles'] = 'Import Profiles';
$_['tab_about'] = 'About';
$_['text_tab_general_subtitle'] = 'General module status and runtime configuration';
$_['text_tab_license_subtitle'] = 'License key and signature verification settings';
$_['text_tab_profiles_subtitle'] = 'Manage import profiles and execute feed imports';
$_['text_tab_about_subtitle'] = 'Module information, version and developer details';
$_['text_section_module_settings'] = 'Module Settings';
$_['text_section_profiles'] = 'Import Profiles';
$_['text_section_license_info'] = 'License Information';
$_['text_module_version'] = 'Module version';
$_['text_developer'] = 'Developer';
$_['text_developer_name'] = 'DockerCart Team';
$_['text_contact'] = 'Contact';

// Column
$_['column_profile_name'] = 'Profile Name';
$_['column_feed_url'] = 'Feed URL';
$_['column_store'] = 'Store';
$_['column_mode'] = 'Mode';
$_['column_status'] = 'Status';
$_['column_last_run'] = 'Last Run';
$_['column_cron_command'] = 'Cron Command';
$_['column_action'] = 'Action';

// Error
$_['error_permission'] = 'Warning: You do not have permission to modify DockerCart Import YML module!';
$_['error_license_invalid'] = 'License is invalid';
$_['error_profile_name_required'] = 'Profile name is required';
$_['error_feed_url_required'] = 'Feed URL is required';
$_['error_profile_id_invalid'] = 'Invalid profile ID';
$_['error_profile_not_found'] = 'Profile not found';
$_['error_curl'] = 'cURL error';
$_['error_http'] = 'HTTP error';
$_['error_invalid_response'] = 'Invalid response from import endpoint';
$_['error_license_key_empty'] = 'License key is empty';
$_['error_license_library_not_found'] = 'License library not found';
$_['error_license_class_not_found'] = 'DockercartLicense class not found';
$_['error_prefix'] = 'Error';
$_['error_import_failed'] = 'Import failed';
$_['error_load_profile_failed'] = 'Failed to load profile';
$_['error_save_failed'] = 'Save failed';
$_['error_delete_failed'] = 'Delete failed';
