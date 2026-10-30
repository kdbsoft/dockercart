<?php
// Heading
$_['heading_title']     = 'Customer Groups';

// Text
$_['text_success']      = 'Success: You have modified customer groups!';
$_['text_list']         = 'Customer Group List';
$_['text_add']          = 'Add Customer Group';
$_['text_edit']         = 'Edit Customer Group';
// Subtitle

$_['text_list_subtitle'] = 'Manage customer grouping rules';

$_['text_add_customer_group_subtitle'] = 'Create a new customer group';

$_['text_edit_customer_group_subtitle'] = 'Edit customer group settings';

// Panel / Card titles
$_['text_group_card'] = 'Customer Group';
$_['text_description_card'] = 'Description';
$_['text_settings_card'] = 'Settings';
$_['text_api_card'] = 'API access';
$_['text_api_card_help'] = 'Grant the storefront API to every customer of this group.';

// Customer API scopes
$_['text_scope_products_read'] = 'Read products';
$_['text_scope_orders_read'] = 'Read orders';
$_['text_scope_returns_read'] = 'Read returns';
$_['text_scope_returns_write'] = 'Create returns';
$_['text_scope_checkout_write'] = 'Creating orders';

// Column
$_['column_name']       = 'Customer Group Name';
$_['column_discount_percent'] = 'Discount (%)';
$_['column_markup_percent'] = 'Markup (%)';
$_['column_sort_order'] = 'Sort Order';
$_['column_action']     = 'Action';

// Entry
$_['entry_name']        = 'Customer Group Name';
$_['entry_description'] = 'Description';
$_['entry_approval']    = 'Approve New Customers';
$_['entry_discount_percent'] = 'Discount (%)';
$_['entry_markup_percent'] = 'Markup (%)';
$_['entry_sort_order']  = 'Sort Order';
$_['entry_api_status']  = 'API access';
$_['entry_api_scopes']  = 'Allowed scopes';

// Help
$_['help_approval']     = 'Customers must be approved by an administrator before they can login.';
$_['help_api_status']   = 'Enable the customer API for the group. A customer can override this individually.';
$_['help_api_scopes']   = 'What a customer of this group may reach. Individual API keys can be narrowed further.';
$_['text_api_global_disabled'] = 'The storefront API is disabled globally: this group gets no API access, the "API" section is hidden in the account and requests to /store-api/v1 answer HTTP 503.';
$_['button_api_settings'] = 'Open API settings';

// Error
$_['error_permission']  = 'Warning: You do not have permission to modify customer groups!';
$_['error_name']        = 'Customer Group Name must be between 3 and 32 characters!';
$_['error_discount_percent'] = 'Discount must be a number between 0 and 100!';
$_['error_markup_percent'] = 'Markup must be a number between 0 and 100!';
$_['error_discount_markup_exclusive'] = 'Only one value can be set: either Discount or Markup.';
$_['error_default']     = 'Warning: This customer group cannot be deleted as it is currently assigned as the default store customer group!';
$_['error_store']       = 'Warning: This customer group cannot be deleted as it is currently assigned to %s stores!';
$_['error_customer']    = 'Warning: This customer group cannot be deleted as it is currently assigned to %s customers!';

// Empty state
$_['text_empty_title']       = 'No customer groups yet';
$_['text_empty_description'] = 'Group customers for pricing, discounts and access rules. Create your first group.';
