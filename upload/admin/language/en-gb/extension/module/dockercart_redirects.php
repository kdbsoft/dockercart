<?php
// Heading
$_['heading_title']     = 'DockerCart Redirect Manager';
$_['heading_import']    = 'Import Redirects';

// Text
$_['text_extension']    = 'Extensions';
$_['text_success_add']  = 'Success: Redirect has been added!';
$_['text_success_edit'] = 'Success: Redirect has been updated!';
$_['text_success_delete'] = 'Success: Redirect(s) have been deleted!';
$_['text_success_status'] = 'Success: Status has been updated!';
$_['text_success_import'] = 'Success: %s redirect(s) have been imported!';
$_['text_success_clear_stats'] = 'Success: Statistics have been cleared!';
$_['text_no_duplicates'] = 'No duplicate redirects found.';
$_['text_list']         = 'Redirect List';
$_['text_form']         = 'Redirect Form';
$_['text_add']          = 'Add Redirect';
$_['text_edit']         = 'Edit Redirect';
$_['text_confirm']      = 'Are you sure you want to delete selected redirects?';
$_['text_confirm_clear_stats'] = 'Are you sure you want to clear all statistics?';
$_['text_enabled']      = 'Enabled';
$_['text_disabled']     = 'Disabled';
$_['text_yes']          = 'Yes';
$_['text_no']           = 'No';
$_['text_all']          = 'All';
$_['text_home']         = 'Home';
$_['text_statistics']   = 'Statistics';
$_['text_filters']      = 'Filters';
$_['text_total_redirects'] = 'Total Redirects';
$_['text_active_redirects'] = 'Active Redirects';
$_['text_regex_redirects'] = 'RegEx Redirects';
$_['text_total_hits']   = 'Total Hits';
$_['text_select_file']  = 'Select CSV file';
$_['text_import_format'] = 'Import Format (CSV columns):';
$_['text_export_csv']   = 'Export to CSV';
// XLSX export removed; CSV only
$_['text_examples']     = 'Examples';
$_['text_exact_match']  = 'Exact Match Examples';
$_['text_regex_examples'] = 'Regular Expression Examples';
$_['text_description']  = 'Description';
$_['text_example_exact'] = 'Simple URL redirect';
$_['text_example_category'] = 'Category URL redirect';
$_['text_example_regex_wildcard'] = 'Redirect all URLs starting with /old- to /new-';
$_['text_example_regex_numbers'] = 'Redirect old product URLs with numbers';
$_['text_example_regex_language'] = 'Redirect with language preservation';

// Redirect codes
$_['text_moved_permanently'] = 'Moved Permanently';
$_['text_found']        = 'Found (Temporary)';
$_['text_see_other']    = 'See Other';
$_['text_temporary_redirect'] = 'Temporary Redirect';
$_['text_permanent_redirect'] = 'Permanent Redirect';

// Column
$_['column_old_url']    = 'Old URL';
$_['column_new_url']    = 'New URL';
$_['column_code']       = 'Code';
$_['column_status']     = 'Status';
$_['column_is_regex']   = 'RegEx';
$_['column_hits']       = 'Hits';
$_['column_last_hit']   = 'Last Hit';
$_['column_date_added'] = 'Date Added';
$_['column_action']     = 'Action';

// Entry
$_['entry_old_url']     = 'Old URL';
$_['entry_new_url']     = 'New URL';
$_['entry_code']        = 'Redirect Code';
$_['entry_status']      = 'Status';
$_['entry_is_regex']    = 'Regular Expression';
$_['entry_preserve_query'] = 'Preserve Query String';
$_['entry_debug']         = 'Debug Mode';
$_['entry_license_key']   = 'License Key';
$_['entry_public_key']    = 'Public Key (optional)';

// Help
$_['help_old_url']      = 'Enter the old URL path (e.g., /old-product or #^/old-(.*)$# for regex)';
$_['help_new_url']      = 'Enter the new URL path (e.g., /new-product or /new-$1 for regex)';
$_['help_code']         = '301 = Permanent (SEO), 302/307 = Temporary, 308 = Permanent (strict)';
$_['help_is_regex']     = 'Enable if using regular expressions in Old URL field';
$_['help_preserve_query'] = 'Keep URL parameters (?param=value) when redirecting';
$_['help_debug']          = 'Enable debugging logs (store writes to dockercart_redirects.log). Should be off in production.';

// Button
$_['button_add']        = 'Add New';
$_['button_edit']       = 'Edit';
$_['button_delete']     = 'Delete';
$_['button_save']       = 'Save';
$_['button_cancel']     = 'Cancel';
$_['button_filter']     = 'Filter';
$_['button_import']     = 'Import';
$_['button_export']     = 'Export';
$_['button_clear_stats'] = 'Clear Statistics';
$_['button_check_duplicates'] = 'Check Duplicates';
$_['button_back']       = 'Cancel';
$_['button_verify_license'] = 'Verify License';

// Error
$_['error_permission']  = 'Warning: You do not have permission to modify redirects!';
$_['error_old_url']     = 'Old URL is required!';
$_['error_new_url']     = 'New URL is required!';
$_['error_invalid_regex'] = 'Invalid regular expression!';
$_['error_select']      = 'Please select at least one redirect!';
$_['error_file_type']   = 'Invalid file type! Please upload a CSV file.';
$_['error_upload']      = 'File upload failed!';

// Warning
$_['warning_duplicates_found'] = 'Warning: Found %s duplicate redirect(s)! Please review and remove duplicates.';

// Date format
$_['date_format']       = 'Y-m-d';
$_['datetime_format']   = 'Y-m-d H:i:s';
