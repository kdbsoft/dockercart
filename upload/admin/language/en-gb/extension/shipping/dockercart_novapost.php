<?php
/**
 * DockerCart NovaPost Shipping Module - Admin Language
 */

$_['heading_title']          = 'NovaPost Beta';
$_['text_extension']         = 'Extensions';
$_['text_success']           = 'Settings saved successfully!';
$_['text_edit']              = 'Edit NovaPost Shipping';
$_['text_enabled']           = 'Enabled';
$_['text_disabled']          = 'Disabled';
$_['text_syncing']           = 'Synchronizing...';
$_['text_sync_complete']     = 'Synchronization completed successfully!';
$_['text_sync_error']        = 'Synchronization error!';
$_['text_no_sync']           = 'No synchronization has been performed yet';
$_['text_subtitle']          = 'NovaPost Shipping & Divisions';
$_['text_api_config']        = 'API Configuration';
$_['text_search']            = 'Search';
$_['text_search_placeholder'] = 'Name, address, city...';
$_['text_country']           = 'Country';
$_['text_all']               = 'All';
$_['text_type']              = 'Type';
$_['text_no_divisions']      = 'No divisions loaded. Click "Sync Divisions" to fetch data from NovaPost API.';
$_['text_no_sync_history']   = 'No sync history yet.';

$_['tab_dashboard']          = 'Dashboard';
$_['tab_general']            = 'General';
$_['tab_divisions']          = 'Divisions';
$_['tab_sync_log']           = 'Sync Log';
$_['tab_tariffs']            = 'Tariffs';
$_['tab_region_mapping']     = 'Region Mapping';

$_['entry_api_key']          = 'API Key';
$_['entry_api_key_help']     = 'Your NovaPost API key from your account';
$_['entry_status']           = 'Status';
$_['entry_sandbox']          = 'Sandbox Mode';
$_['entry_sandbox_help']     = 'Use test environment for API requests';
$_['entry_country_codes']    = 'Countries';
$_['entry_country_codes_help'] = 'Select countries to load divisions for';
$_['entry_division_categories'] = 'Division Types';
$_['entry_division_categories_help'] = 'Select types of divisions to load';
$_['entry_sort_order']       = 'Sort Order';
$_['entry_calculation_method'] = 'Calculation Method';
$_['entry_calculation_method_help'] = 'Tariff: use local tariff table. API: live NovaPost API calculation.';
$_['text_tariff']            = 'Tariff';
$_['text_api']               = 'API';

$_['button_save']            = 'Save';
$_['button_cancel']          = 'Cancel';
$_['button_sync']            = 'Sync Divisions';
$_['button_sync_all']        = 'Sync All Countries';
$_['button_filter']          = 'Filter';

$_['column_country']         = 'Country';
$_['column_divisions']       = 'Divisions';
$_['column_updated']         = 'Last Updated';
$_['column_status']          = 'Status';
$_['column_loaded']          = 'Loaded';
$_['column_errors']          = 'Errors';
$_['column_started']         = 'Started';
$_['column_finished']        = 'Finished';
$_['column_name']            = 'Name';
$_['column_city']            = 'City';
$_['column_address']         = 'Address';
$_['column_countries']       = 'Countries';

$_['help_total_divisions']   = 'Total divisions in database';
$_['help_countries_loaded']  = 'Countries with loaded divisions';
$_['help_by_category']       = 'By Category';
$_['help_last_sync']         = 'Last successful synchronization';

$_['error_permission']       = 'Warning: You do not have permission to modify NovaPost shipping!';
$_['error_api_key']          = 'API Key is required!';
$_['error_sync']             = 'Synchronization failed: %s';
$_['error_ajax']             = 'AJAX request failed';

$_['category_cargo_branch']  = 'Cargo Branch';
$_['category_post_branch']   = 'Post Branch';
$_['category_postomat']      = 'Postomat';
$_['category_pudo']          = 'PUDO';

$_['status_success']         = 'Success';
$_['status_failed']          = 'Failed';
$_['status_partial']         = 'Partial';
$_['status_running']         = 'Running';

// Tariff tab
$_['text_add_tariff']        = 'Add Tariff';
$_['text_edit_tariff']       = 'Edit Tariff';
$_['text_no_tariffs']        = 'No tariffs configured yet.';
$_['text_weight_range']      = 'Weight range';
$_['text_kg']                = 'kg';
$_['text_free_shipping']     = 'Free shipping';
$_['text_free_shipping_off'] = 'No';

$_['column_delivery_type']   = 'Delivery Type';
$_['column_weight_from']     = 'Weight From';
$_['column_weight_to']       = 'Weight To';
$_['column_cost']            = 'Cost';
$_['column_free_shipping']   = 'Free From';

$_['entry_delivery_type']    = 'Delivery Type';
$_['entry_weight_from']      = 'Weight From (kg)';
$_['entry_weight_to']        = 'Weight To (kg)';
$_['entry_cost']             = 'Shipping Cost';
$_['entry_free_shipping_from'] = 'Free Shipping From';

$_['button_add_tariff']      = 'Add Tariff';
$_['button_edit_tariff']     = 'Edit';
$_['button_delete_tariff']   = 'Delete';
$_['button_cancel_tariff']   = 'Cancel';

$_['delivery_branch']        = 'Branch (В отделение)';
$_['delivery_locker']        = 'Locker (В почтомат)';
$_['delivery_courier']       = 'Courier (Курьером)';

$_['error_tariff_country']   = 'Country is required!';
$_['error_tariff_delivery']  = 'Delivery type is required!';
$_['error_tariff_weight_from'] = 'Weight from must be a number ≥ 0!';
$_['error_tariff_weight_to'] = 'Weight to must be greater than weight from!';
$_['error_tariff_cost']      = 'Cost must be a number ≥ 0!';
$_['error_tariff_overlap']   = 'A tariff for this country, delivery type and weight range already exists!';
$_['error_tariff_not_found'] = 'Tariff not found!';
$_['confirm_delete_tariff']  = 'Are you sure you want to delete this tariff?';

// Region mapping tab
$_['text_no_region_maps']    = 'No regions discovered yet. Run a sync first to populate NovaPost regions.';
$_['text_mapped']            = 'Mapped';
$_['text_unmapped']          = 'Unmapped';
$_['text_all_status']        = 'All Statuses';
$_['column_np_region']       = 'NovaPost Region';
$_['column_oc_zone']         = 'OC Zone';
$_['column_city']            = 'City';
$_['entry_oc_zone']          = 'OpenCart Zone';
$_['button_map']             = 'Map';
$_['entry_region_map_filter_status'] = 'Status';
