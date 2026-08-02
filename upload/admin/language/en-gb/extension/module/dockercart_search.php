<?php
// Heading
$_['heading_title']     = 'Search';
$_['heading_title_menu'] = 'Search Index';
$_['heading_mappings']  = 'Search Query Mappings';
$_['heading_mapping']   = 'Search Query Mapping';

// Text
$_['text_extension']    = 'Add-ons';
$_['text_success']      = 'Success: Module settings have been saved!';
$_['text_edit']         = 'Edit Search Settings';
$_['text_enabled']      = 'Enabled';
$_['text_disabled']     = 'Disabled';
$_['text_yes']          = 'Yes';
$_['text_no']           = 'No';

// Tab
$_['tab_general']       = 'General Settings';
$_['tab_connection']    = 'Connection Settings';
$_['tab_morphology']    = 'Language & Morphology';
$_['tab_indexing']      = 'Indexing';
$_['tab_autocomplete']  = 'Autocomplete';
$_['tab_about']          = 'About';

$_['text_tab_general_subtitle'] = 'Core search behavior and result limits';
$_['text_tab_connection_subtitle'] = 'Manticore host and ports configuration';
$_['text_tab_autocomplete_subtitle'] = 'Autocomplete behavior and suggestion limits';
$_['text_tab_indexing_subtitle'] = 'Reindex catalog data into Manticore';
$_['text_tab_about_subtitle'] = 'Module information and support contacts';
$_['text_module_settings'] = 'Module Settings';
$_['text_developer'] = 'Developer';
$_['text_developer_name'] = 'DockerCart Official';
$_['text_contact'] = 'Contact';

// Entry
$_['entry_status']      = 'Status';
$_['entry_host']        = 'Manticore Host';
$_['entry_port']        = 'MySQL Protocol Port';
$_['entry_http_port']   = 'HTTP API Port';
$_['entry_autocomplete'] = 'Enable Autocomplete';
$_['entry_voice_search'] = 'Enable Voice Search';
$_['entry_autocomplete_limit'] = 'Autocomplete Limit';
$_['entry_min_chars']   = 'Minimum Characters';
$_['entry_results_limit'] = 'Search Results Limit';
$_['entry_query_mappings'] = 'Search Query Mappings';
$_['entry_source']      = 'Source Query';
$_['entry_target']      = 'Target Query';
$_['entry_morphology']  = 'Morphology';
$_['entry_ranking']     = 'Ranking Mode';
$_['entry_field_weights'] = 'Field Weights';
$_['entry_weight_title'] = 'Title Weight';
$_['entry_weight_description'] = 'Description Weight';
$_['entry_weight_meta'] = 'Meta Weight';
$_['entry_weight_tags'] = 'Tags Weight';

// Column
$_['column_source']     = 'Source';
$_['column_target']     = 'Target';
$_['column_action']     = 'Action';

// Button
$_['button_save']       = 'Save';
$_['button_cancel']     = 'Cancel';
$_['button_test_connection'] = 'Test Connection';
$_['button_reindex']    = 'Reindex All';
$_['button_add_mapping'] = 'Add Mapping';
$_['button_export_csv'] = 'Export CSV';
$_['button_import_csv'] = 'Import CSV';
$_['button_manage_mappings'] = 'Manage Query Mappings';

// Help
$_['help_status']       = 'Enable or disable Manticore search';
$_['help_host']         = 'Hostname of Manticore Search (default: manticore)';
$_['help_port']         = 'MySQL protocol port (default: 9306)';
$_['help_http_port']    = 'HTTP API port for autocomplete (default: 9308)';
$_['help_autocomplete'] = 'Enable AJAX autocomplete on search input';
$_['help_voice_search'] = 'Add a microphone button to the search input (Web Speech API). Works in Chrome, Edge and Safari over HTTPS; the button is hidden automatically in unsupported browsers. The recognized phrase fills the input without submitting.';
$_['help_autocomplete_limit'] = 'Number of suggestions to show in autocomplete dropdown';
$_['help_min_chars']    = 'Minimum characters to trigger search/autocomplete';
$_['help_results_limit'] = 'Default number of search results per page';
$_['help_query_mappings'] = 'Mappings replace one search query with another before searching. Managed on a dedicated page.';
$_['help_source']      = 'The query as typed by customers (case-insensitive). Cannot contain "=" or newlines.';
$_['help_target']      = 'The query it will be replaced with before searching.';
$_['help_import_csv']  = 'CSV file with two columns: source,target. The first row may be a header. Existing sources are replaced.';
$_['help_query_mappings_page'] = 'Add, edit, delete and import/export mappings.';
$_['help_morphology']   = 'Select ONE morphology processor for this language (stemming or lemmatization). After changing, you must recreate indexes!';
$_['help_ranking']      = 'Ranking algorithm for search results';
$_['help_field_weights'] = 'Importance of each field in search (higher = more important)';
$_['help_reindex']      = 'Rebuild search index for all products, categories, manufacturers, and information pages';

// Error
$_['error_permission']  = 'Warning: You do not have permission to modify this module!';
$_['error_host']        = 'Host is required!';
$_['error_port']        = 'Port must be a number!';
$_['error_autocomplete_limit'] = 'Autocomplete limit must be a number!';
$_['error_min_chars']   = 'Minimum characters must be a number!';
$_['error_results_limit'] = 'Results limit must be a number!';
$_['error_source']      = 'Source is required and must not contain "=" or newlines!';
$_['error_target']      = 'Target is required and must not contain newlines!';
$_['error_upload']      = 'Please select a CSV file to upload!';

// Success
$_['text_connection_success'] = 'Successfully connected to Manticore Search!';
$_['text_connection_failed'] = 'Failed to connect to Manticore Search!';
$_['text_reindex_success'] = 'Reindexing completed: %s products, %s categories, %s manufacturers, %s information pages';
$_['text_reindex_failed'] = 'Reindexing failed!';
$_['text_reindex_warning'] = 'Reindexing may take several minutes for large catalogs (>10,000 products).';
$_['text_reindexing'] = 'Reindexing...';
$_['text_reindex_confirm'] = 'Are you sure you want to reindex all data? This may take several minutes.';
$_['text_error_label'] = 'Error';
$_['text_success_delete'] = 'Success: The mapping has been deleted!';
$_['text_success_mapping'] = 'Success: The mapping has been saved!';
$_['text_confirm_delete'] = 'Delete this mapping?';
$_['text_import_success'] = 'Import completed: %s mappings imported.';
$_['text_import_skipped'] = 'Skipped invalid rows: %s.';

$_['text_reindex_success'] = 'Reindexing completed: %s products, %s categories, %s manufacturers, %s information pages';
$_['text_reindex_failed'] = 'Reindexing failed!';
