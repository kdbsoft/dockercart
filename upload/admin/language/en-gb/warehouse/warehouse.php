<?php
// Warehouse
$_['heading_title'] = 'Warehouses';
$_['text_add'] = 'Add Warehouse';
$_['text_edit'] = 'Edit Warehouse';
$_['text_list'] = 'Warehouses';
$_['text_success'] = 'Success: You have modified warehouses!';
$_['text_no_results'] = 'No warehouses found';
$_['text_home'] = 'Home';
$_['text_pagination'] = 'Showing %d to %d of %d (%d pages)';
$_['text_confirm'] = 'Are you sure?';
$_['text_default'] = 'Default';
$_['text_enabled'] = 'Enabled';
$_['text_disabled'] = 'Disabled';
$_['text_active'] = 'Active';
$_['text_inactive'] = 'Inactive';
$_['text_type_physical'] = 'Physical';
$_['text_type_virtual'] = 'Virtual';
$_['text_type_dropship'] = 'Dropship';
$_['text_monday'] = 'Monday';
$_['text_tuesday'] = 'Tuesday';
$_['text_wednesday'] = 'Wednesday';
$_['text_thursday'] = 'Thursday';
$_['text_friday'] = 'Friday';
$_['text_saturday'] = 'Saturday';
$_['text_sunday'] = 'Sunday';
$_['text_copy_shared'] = 'Copy shared holidays';

// Buttons
$_['button_add'] = 'Add New';
$_['button_remove'] = 'Remove';
$_['button_delete'] = 'Delete';
$_['button_save'] = 'Save';
$_['button_cancel'] = 'Cancel';
$_['button_apply_all'] = 'Apply first day to all';
$_['button_clear_intervals'] = 'Clear intervals';
$_['text_none'] = 'None';

// Columns
$_['column_name'] = 'Name';
$_['column_type'] = 'Type';
$_['column_priority'] = 'Priority';
$_['column_default'] = 'Default';
$_['column_status'] = 'Status';

// Tabs
$_['tab_warehouse'] = 'Warehouse';
$_['tab_address'] = 'Address & Location';
$_['tab_schedule'] = 'Schedule';
$_['tab_holidays'] = 'Holidays';
$_['tab_pickup'] = 'Pickup';
$_['tab_dropship'] = 'Dropship';

// Entries
$_['entry_name'] = 'Name';
$_['entry_type'] = 'Type';
$_['entry_priority'] = 'Priority';
$_['entry_default'] = 'Default warehouse';
$_['entry_status'] = 'Status';
$_['entry_sort_order'] = 'Sort Order';
$_['entry_address_1'] = 'Address 1';
$_['entry_address_2'] = 'Address 2';
$_['entry_city'] = 'City';
$_['entry_postcode'] = 'Postcode';
$_['entry_country'] = 'Country';
$_['entry_zone'] = 'Region / Zone';
$_['entry_latitude'] = 'Latitude';
$_['entry_longitude'] = 'Longitude';
$_['entry_phone'] = 'Phone';
$_['entry_email'] = 'Email';
$_['entry_map_url'] = 'Map URL';
$_['entry_prepare_days'] = 'Prepare days';
$_['entry_low_stock'] = 'Low stock threshold';
$_['entry_allow_pickup'] = 'Allow self-pickup';
$_['entry_pickup_cost'] = 'Pickup cost';
$_['entry_pickup_note'] = 'Pickup note';
$_['entry_supplier_name'] = 'Supplier name';
$_['entry_supplier_phone'] = 'Supplier phone';
$_['entry_supplier_email'] = 'Supplier email';
$_['entry_supplier_lead_time'] = 'Supplier lead time (days)';
$_['entry_supplier_note'] = 'Supplier note';
$_['entry_holiday_date'] = 'Date';
$_['entry_holiday_name'] = 'Name';
$_['entry_working_holiday'] = 'Working day';

// Help
$_['help_priority'] = 'Higher priority warehouses are allocated first.';
$_['help_default'] = 'Used when allocation cannot determine a warehouse and for legacy rates.';
$_['help_prepare_days'] = 'Working days added to the delivery estimate for this warehouse.';
$_['help_low_stock'] = 'Show a low-stock warning when a position drops below this quantity.';
$_['help_pickup'] = 'Customer can collect the order from this warehouse.';
$_['help_supplier_lead_time'] = 'Default dispatch lead time in days for dropship lines from this supplier.';

// Errors
$_['error_warning'] = 'Warning: Please check the form carefully for errors!';
$_['error_permission'] = 'Warning: You do not have permission to modify warehouses!';
$_['error_name'] = 'Warehouse name must be between 1 and 255 characters!';
$_['error_default_exists'] = 'Warning: A default warehouse already exists. Unset it first.';
$_['error_stock'] = 'Warning: Cannot delete the last remaining warehouse.';