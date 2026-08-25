<?php
// Heading
$_['heading_title']    = 'SEO URL';
$_['heading_import']   = 'Import SEO URLs';
$_['text_search_seo_url'] = 'Search SEO URLs by keyword or query';

// Button
$_['button_cancel']     = 'Cancel';
$_['button_export_csv'] = 'Export CSV';
$_['button_import']     = 'Import';
$_['button_import_csv'] = 'Import CSV';

// Text
$_['text_success']     = 'Success: You have modified SEO URL!';
$_['text_list']        = 'SEO URL List';
$_['text_add']         = 'Add SEO URL';
$_['text_edit']        = 'Edit SEO URL';
$_['text_filter']      = 'Filter';
$_['text_default']     = 'Default';
$_['text_seo_card']    = 'Links';
$_['text_keyword']     = 'Do not use spaces, instead replace spaces with - and make sure the SEO URL is globally unique.';
$_['text_seo_preview'] = 'Preview';
$_['text_seo_url_base'] = 'yoursite.com/';
$_['text_none'] = 'None';
$_['text_select_file'] = 'CSV file';
$_['text_import_format'] = 'Columns: store_id, language (code), query, keyword. Store 0 is the default store. Existing aliases are updated, new ones are added. If any row is invalid, the whole import is rejected.';
$_['text_import_success'] = 'Success: Imported %d SEO URL entries (%d added, %d updated)!';
// Subtitle

$_['text_list_subtitle'] = 'Manage SEO-friendly URL aliases';

$_['text_add_seo_url_subtitle'] = 'Create a new SEO URL alias';

$_['text_edit_seo_url_subtitle'] = 'Edit SEO URL mapping';

// Column
$_['column_query']     = 'Query';
$_['column_keyword']   = 'Keyword';
$_['column_store']     = 'Store';
$_['column_language']  = 'Language';
$_['column_action']    = 'Action';

// Entry
$_['entry_query']        = 'Query';
$_['entry_store']        = 'Store';
$_['entry_keyword']      = 'Keyword';

// Error
$_['error_permission']   = 'Warning: You do not have permission to modify SEO URL!';
$_['error_query']        = 'Query must be between 3 and 64 characters!';
$_['error_keyword']      = 'Keyword must be between 3 and 64 characters!';
$_['error_exists']       = 'Keyword already in use!';
$_['error_query_exists'] = 'Query already in use!';
$_['error_upload']       = 'Warning: Could not upload the file!';
$_['error_csv_empty']    = 'CSV file contains no data rows!';
$_['error_csv_columns']  = 'Line %s: expected 4 columns, got %s!';
$_['error_csv_query']    = 'Line %s: query is empty!';
$_['error_csv_query_length'] = 'Line %s: query exceeds 255 characters!';
$_['error_csv_keyword']  = 'Line %s: keyword is empty!';
$_['error_csv_keyword_length'] = 'Line %s: keyword exceeds 255 characters!';
$_['error_csv_language'] = 'Line %s: unknown language code "%s"!';
$_['error_csv_store']    = 'Line %s: unknown store ID "%s"!';
$_['error_csv_duplicate'] = 'Line %s: duplicate entry for query "%s" (store %s, language %s) — already listed on line %s!';
$_['error_csv_keyword_conflict'] = 'Line %s: keyword "%s" is already used by query "%s" (store %s, language %s)!';
