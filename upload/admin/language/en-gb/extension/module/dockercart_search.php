<?php
// Heading
$_['heading_title']     = 'DockerCart Search (Manticore)';

// Text
$_['text_extension']    = 'Extensions';
$_['text_success']      = 'Success: Module settings have been saved!';
$_['text_edit']         = 'Edit DockerCart Search Settings';
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
$_['text_module_version'] = 'Module Version';
$_['text_developer'] = 'Developer';
$_['text_developer_name'] = 'DockerCart Team';
$_['text_contact'] = 'Contact';

// Entry
$_['entry_status']      = 'Status';
$_['entry_host']        = 'Manticore Host';
$_['entry_port']        = 'MySQL Protocol Port';
$_['entry_http_port']   = 'HTTP API Port';
$_['entry_autocomplete'] = 'Enable Autocomplete';
$_['entry_autocomplete_limit'] = 'Autocomplete Limit';
$_['entry_min_chars']   = 'Minimum Characters';
$_['entry_results_limit'] = 'Search Results Limit';
$_['entry_morphology']  = 'Morphology';
$_['entry_ranking']     = 'Ranking Mode';
$_['entry_field_weights'] = 'Field Weights';
$_['entry_weight_title'] = 'Title Weight';
$_['entry_weight_description'] = 'Description Weight';
$_['entry_weight_meta'] = 'Meta Weight';
$_['entry_weight_tags'] = 'Tags Weight';

// Button
$_['button_save']       = 'Save';
$_['button_cancel']     = 'Cancel';
$_['button_test_connection'] = 'Test Connection';
$_['button_reindex']    = 'Reindex All';

// Help
$_['help_status']       = 'Enable or disable Manticore search';
$_['help_host']         = 'Hostname of Manticore Search (default: manticore)';
$_['help_port']         = 'MySQL protocol port (default: 9306)';
$_['help_http_port']    = 'HTTP API port for autocomplete (default: 9308)';
$_['help_autocomplete'] = 'Enable AJAX autocomplete on search input';
$_['help_autocomplete_limit'] = 'Number of suggestions to show in autocomplete dropdown';
$_['help_min_chars']    = 'Minimum characters to trigger search/autocomplete';
$_['help_results_limit'] = 'Default number of search results per page';
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

// Success
$_['text_connection_success'] = 'Successfully connected to Manticore Search!';
$_['text_connection_failed'] = 'Failed to connect to Manticore Search!';
$_['text_reindex_success'] = 'Reindexing completed: %s products, %s categories, %s manufacturers, %s information pages';
$_['text_reindex_failed'] = 'Reindexing failed!';
