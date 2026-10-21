<?php
// Heading
$_['heading_title']    = 'User Groups';

// Text
$_['text_success']     = 'Success: You have modified user groups!';
$_['text_list']        = 'User Group';
$_['text_add']         = 'Add User Group';
$_['text_edit']        = 'Edit User Group';
// Subtitle

$_['text_list_subtitle'] = 'Manage user permission groups';

$_['text_add_user_group_subtitle'] = 'Create a new user permission group';

$_['text_edit_user_group_subtitle'] = 'Edit user group permissions';

$_['text_form_subtitle'] = 'Name the group and grant it the sections it needs';

$_['text_permissions'] = 'Permissions';
$_['text_permissions_subtitle'] = 'Access opens a page, Modify allows saving changes on it';
$_['text_full_access'] = 'The Administrator group always has full access to every section, including pages added by future updates. Its permissions cannot be restricted.';
$_['text_custom_group'] = 'Custom group';
$_['text_select_role_template'] = '— Select a role template —';
$_['text_confirm_create_roles'] = 'Create the standard role groups that do not exist yet?';
$_['text_apply_template_confirm'] = 'Apply the selected role template? The current permission selection will be replaced.';

$_['text_allow_all_access'] = 'Allow all access';
$_['text_deny_all_access'] = 'Deny all access';
$_['text_allow_all_modify'] = 'Allow all modifications';
$_['text_deny_all_modify'] = 'Deny all modifications';
$_['text_allow_access'] = 'access';
$_['text_allow_modify'] = 'modify';
$_['text_deny_section'] = 'clear';
$_['text_select_all'] = 'Select All';
$_['text_unselect_all'] = 'Unselect All';

$_['text_roles_created'] = 'Success: %s role group(s) created — %s';
$_['text_roles_existing'] = 'All standard role groups already exist (%s)';

// Sections
$_['text_section_sale']         = 'Sales';
$_['text_section_catalog']      = 'Catalog';
$_['text_section_customer']     = 'Customers';
$_['text_section_warehouse']    = 'Warehouse';
$_['text_section_design']       = 'Design';
$_['text_section_marketing']    = 'Marketing';
$_['text_section_report']       = 'Reports';
$_['text_section_extension']    = 'Extensions';
$_['text_section_setting']      = 'Settings';
$_['text_section_localisation'] = 'Localisation';
$_['text_section_tool']         = 'Tools';
$_['text_section_user']         = 'Users';
$_['text_section_marketplace']  = 'Marketplace';
$_['text_section_common']       = 'General';
$_['text_section_other']        = 'Other';

// Roles
$_['text_role_sales_manager']    = 'Sales manager';
$_['text_role_support_operator'] = 'Support operator';
$_['text_role_content_manager']  = 'Content manager';
$_['text_role_marketer']         = 'SEO / marketer';
$_['text_role_courier']          = 'Courier';
$_['text_role_storekeeper']      = 'Storekeeper';
$_['text_role_accountant']       = 'Accountant (read only)';

// Capabilities (action level permissions)
$_['text_capability_order_status']   = 'Change the order status';
$_['text_capability_order_shipment'] = 'Create shipments / set a tracking number';

// Column
$_['column_name']      = 'User Group Name';
$_['column_action']    = 'Action';
$_['column_role']      = 'Role';
$_['column_section']   = 'Section';
$_['column_permission'] = 'Page';
$_['column_access']    = 'Access';
$_['column_modify']    = 'Modify';

// Entry
$_['entry_name']       = 'User Group Name';
$_['entry_access']     = 'Access Permission';
$_['entry_modify']     = 'Modify Permission';
$_['entry_role_template'] = 'Role Template';
$_['entry_capabilities'] = 'Actions';
$_['entry_search_permission'] = 'Search a page or route…';

// Help
$_['help_role_template'] = 'Fills the permissions below with the ready-made set of a standard role. You can adjust them afterwards.';
$_['help_permissions'] = 'Access lets the group open a page, Modify lets it save changes there. Granting Modify also grants Access, since a page that cannot be opened cannot be edited.';
$_['help_capabilities'] = 'Single actions that are normally covered by the Modify permission of their page. Grant them to roles that must not have full control over the page (for example a courier who may only change the order status).';

// Button
$_['button_save']      = 'Save';
$_['button_cancel']    = 'Cancel';
$_['button_add']       = 'Add New';
$_['button_delete']    = 'Delete';
$_['button_apply_template'] = 'Apply';
$_['button_create_roles']   = 'Create standard roles';

// Error
$_['error_permission'] = 'Warning: You do not have permission to modify user groups!';
$_['error_name']       = 'User Group Name must be between 3 and 64 characters!';
$_['error_user']       = 'Warning: This user group cannot be deleted as it is currently assigned to %s users!';
$_['error_delete_administrator'] = 'Warning: The Administrator group cannot be deleted!';
