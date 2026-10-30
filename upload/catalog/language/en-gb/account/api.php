<?php
// Heading
$_['heading_title']  = 'API';
$_['heading_docs']   = 'API documentation';

// Text
$_['text_api']              = 'API keys';
$_['text_api_help']         = 'Create keys to access your orders and returns from an external application.';
$_['text_created']          = 'API key "%s" created.';
$_['text_rotated']          = 'A new token was generated for "%s". The previous token no longer works.';
$_['text_revoked']          = 'API key "%s" revoked.';
$_['text_enabled_success']  = 'API key "%s" enabled.';
$_['text_deleted']          = 'API key "%s" deleted.';
$_['text_token_once']       = 'Copy the token now — it is shown only once';
$_['text_token_help']       = 'Store it in a password manager. Only a hash is kept in the database, so the token cannot be recovered later.';
$_['text_key_limit']        = 'You have reached the limit of %s API keys. Delete a key before creating another.';
$_['text_no_keys']          = 'You have no API keys yet.';
$_['text_enabled']          = 'Enabled';
$_['text_disabled']         = 'Disabled';
$_['text_expired']          = 'Expired';
$_['text_never']            = 'Never';

// Scopes
$_['text_scope_products_read']  = 'Read products';
$_['text_scope_orders_read']    = 'Read orders';
$_['text_scope_returns_read']   = 'Read returns';
$_['text_scope_returns_write']  = 'Create returns';
$_['text_scope_checkout_write'] = 'Place orders';

// Entry
$_['entry_name']            = 'Name';
$_['entry_comment']         = 'Comment';
$_['entry_expires']         = 'Expires at';
$_['entry_ip_allowlist']    = 'IP allowlist';
$_['entry_scopes']          = 'Scopes';

$_['help_scopes']           = 'Leave all unchecked to grant everything your account is allowed to do.';

// Column
$_['column_name']           = 'Name';
$_['column_prefix']         = 'Token prefix';
$_['column_scopes']         = 'Scopes';
$_['column_status']         = 'Status';
$_['column_expires']        = 'Expires';
$_['column_last_used']      = 'Last used';
$_['column_action']         = 'Action';

// Button
$_['button_add']            = 'Create key';
$_['button_rotate']         = 'Rotate';
$_['button_revoke']         = 'Revoke';
$_['button_enable']         = 'Enable';
$_['button_delete']         = 'Delete';
$_['button_copy']           = 'Copy';
$_['button_docs']           = 'Documentation';
$_['button_back']           = 'Back';
$_['button_keys']           = 'API keys';

$_['text_rotate_confirm']   = 'Generate a new token? The current token stops working immediately.';
$_['text_delete_confirm']   = 'Delete this key permanently?';

// Error
$_['error_permission']      = 'API access is not enabled for your account.';
$_['error_key_limit']       = 'You have reached the limit of %s API keys.';
$_['error_name']            = 'Name must be between 1 and 128 characters.';
$_['error_expires']         = 'Expiry date must be a valid date (YYYY-MM-DD) or empty.';
$_['error_ip_allowlist']    = 'The IP allowlist may contain only IP addresses or CIDR blocks.';
$_['error_key']             = 'API key not found.';
