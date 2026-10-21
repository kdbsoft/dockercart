<?php
// Heading
$_['heading_title']         = 'Users';

// Text
$_['text_success']          = 'Success: You have modified users!';
$_['text_list']             = 'User List';
$_['text_add']              = 'Add User';
$_['text_edit']             = 'Edit User';

// Sidebar
$_['text_status_card']      = 'Status';

// Subtitle

$_['text_list_subtitle'] = 'Manage admin panel user accounts';

$_['text_add_user_subtitle'] = 'Create a new admin user';

$_['text_edit_user_subtitle'] = 'Edit admin user credentials';



// Column
$_['column_username']       = 'Username';
$_['column_status']         = 'Status';
$_['column_date_added']     = 'Date Created';
$_['column_action']         = 'Action';

// Entry
$_['entry_username']        = 'Username';
$_['entry_user_group']      = 'User Group';
$_['entry_password']        = 'Password';
$_['entry_confirm']         = 'Confirm';
$_['entry_firstname']       = 'First Name';
$_['entry_lastname']        = 'Last Name';
$_['entry_email']           = 'E-Mail';
$_['entry_image']           = 'Image';
$_['entry_status']          = 'Status';

// Error
$_['error_permission']      = 'Warning: You do not have permission to modify users!';
$_['error_account']         = 'Warning: You can not delete your own account!';
$_['error_exists_username'] = 'Warning: Username is already in use!';
$_['error_username']        = 'Username must be between 3 and 20 characters!';
$_['error_password']        = 'Password must be between 8 and 40 characters!';
$_['error_confirm']         = 'Password and password confirmation do not match!';
$_['error_firstname']       = 'First Name must be between 1 and 32 characters!';
$_['error_lastname']        = 'Last Name must be between 1 and 32 characters!';
$_['error_email']           = 'E-Mail Address does not appear to be valid!';
$_['error_exists_email']    = 'Warning: E-Mail Address is already registered!';
$_['error_single_user']     = 'Warning: You can not disable your own account!';
// API keys
$_['text_api_keys']                = 'API keys';
$_['text_api_keys_help']           = 'The keys of this user act with the permissions of the current user group of that user.';
$_['text_api_keys_empty']          = 'This user has no API keys yet.';
$_['text_api_enabled']             = 'Enabled';
$_['text_api_disabled']            = 'Disabled';
$_['text_api_expired']             = 'Expired';
$_['text_api_never']               = 'Never';
$_['text_api_limit']               = 'limit';
$_['text_api_minute']              = 'min';
$_['text_api_token_once']          = 'Copy the token now — it is shown only once';
$_['text_api_token_help']          = 'Store it in your password manager or CI secrets. Only a hash is kept in the database, so the token cannot be recovered later.';
$_['text_api_key_created']         = 'API key "%s" created.';
$_['text_api_key_rotated']         = 'A new token was generated for "%s". The previous token no longer works.';
$_['text_api_key_revoked']         = 'API key "%s" revoked.';
$_['text_api_key_enabled']         = 'API key "%s" enabled.';
$_['text_api_key_deleted']         = 'API key "%s" deleted.';

$_['column_api_name']              = 'Name';
$_['column_api_prefix']            = 'Token prefix';
$_['column_api_status']            = 'Status';
$_['column_api_expires']           = 'Expires';
$_['column_api_last_used']         = 'Last used';
$_['column_api_requests']          = 'Requests (24h / total)';
$_['column_api_action']            = 'Action';

$_['entry_api_key_name']           = 'Name';
$_['entry_api_key_comment']        = 'Comment';
$_['entry_api_key_expires']        = 'Expires at';
$_['entry_api_key_rate_limit']     = 'Rate limit';
$_['entry_api_key_ip_allowlist']   = 'IP allowlist';

$_['help_api_key_expires']         = 'Leave empty for a key that never expires.';
$_['help_api_key_rate_limit']      = 'Requests per minute. 0 uses the global default.';
$_['help_api_key_ip_allowlist']    = 'Optional. One IP or CIDR block. Empty = any address.';

$_['button_api_key_add']           = 'Add key';
$_['button_api_key_create']        = 'Create key';
$_['button_api_key_rotate']        = 'Rotate token';
$_['button_api_key_revoke']        = 'Revoke';
$_['button_api_key_enable']        = 'Enable';
$_['button_api_key_delete']        = 'Delete';
$_['button_api_key_copy']          = 'Copy';

$_['text_api_key_rotate_confirm']  = 'Generate a new token? The current token stops working immediately.';
$_['text_api_key_revoke_confirm']  = 'Revoke this key? Requests using it will fail with HTTP 401.';
$_['text_api_key_delete_confirm']  = 'Delete this key permanently? The request journal is kept.';

$_['error_api_permission']         = 'Warning: You do not have permission to modify API keys!';
$_['error_api_user']               = 'Warning: User not found!';
$_['error_api_name']               = 'Name must be between 1 and 128 characters!';
$_['error_api_expires']            = 'Expiry date must be a valid date (YYYY-MM-DD) or empty!';
$_['error_api_ip_allowlist']       = 'The IP allowlist may contain only IP addresses or CIDR blocks!';
$_['error_api_key']                = 'Warning: API key not found!';
