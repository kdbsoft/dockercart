<?php
// Heading
$_['heading_title']                = 'API Keys';

// Text
$_['text_keys']                    = 'API keys';
$_['text_keys_help']               = 'Bearer tokens for the admin REST API. A key inherits the permissions of the user group it is bound to: "access" routes allow reading, "modify" routes allow writing. The plain token is displayed only once.';
$_['text_shared']                  = 'Shared';
$_['text_shared_key']              = 'Shared key (no owner)';
$_['text_total_keys']              = 'Total keys';
$_['text_filter']                  = 'Filter';
$_['text_filter_help']             = 'Show the keys of one admin user only.';
$_['text_filter_owner']            = 'Owner';
$_['text_all_owners']              = 'All owners';
$_['text_no_keys_title']           = 'No API keys yet';
$_['text_settings']                = 'API settings';
$_['text_settings_help']            = 'Global switches of the REST API.';
$_['text_key']                     = 'Key';
$_['text_key_help']                = 'Name, owner and limits of the key.';
$_['text_info']                    = 'Details';
$_['text_permissions']             = 'Permissions';
$_['text_permissions_help']        = 'The key may call GET endpoints covered by the group\'s "access" permissions and POST/PUT/PATCH/DELETE endpoints covered by its "modify" permissions.';
$_['text_no_keys']                 = 'Create a key to let an integration work with the store through the REST API. The plain token is displayed only once.';
$_['text_add']                     = 'Add API key';
$_['text_edit']                    = 'Edit API key';
$_['text_enabled']                 = 'Enabled';
$_['text_disabled']                = 'Disabled';
$_['text_expired']                 = 'Expired';
$_['text_never']                   = 'Never';
$_['text_days']                    = 'days';
$_['text_minute']                  = 'min';
$_['text_limit']                   = 'limit';
$_['text_unknown_group']           = 'Unknown group';
$_['text_created']                 = 'API key "%s" created.';
$_['text_rotated']                 = 'A new token was generated for "%s". The previous token no longer works.';
$_['text_revoked']                 = 'API key "%s" revoked.';
$_['text_enabled_success']         = 'API key "%s" enabled.';
$_['text_deleted']                 = 'API key "%s" deleted.';
$_['text_success']                 = 'Success: API key saved.';
$_['text_settings_saved']          = 'Success: API settings saved.';
$_['text_token_once']              = 'Copy the token now — it is shown only once';
$_['text_token_help']              = 'Store it in your password manager or CI secrets. Only a hash is kept in the database, so the token cannot be recovered later.';
$_['text_prefix']                  = 'Prefix';
$_['text_last_used']               = 'Last used';
$_['text_base_url']                = 'API base URL';
$_['text_group_superuser']         = 'Administrator: full access to every endpoint.';
$_['text_group_counts']            = '%s read routes, %s write routes.';
$_['text_group_from_owner']        = 'Taken from the current group of the owner.';

// Column
$_['column_name']                  = 'Name';
$_['column_prefix']                = 'Token prefix';
$_['column_group']                 = 'User group';
$_['column_owner']                 = 'Owner';
$_['column_status']                = 'Status';
$_['column_expires']               = 'Expires';
$_['column_last_used']             = 'Last used';
$_['column_requests']              = 'Requests (24h / total)';
$_['column_action']                = 'Action';

// Entry
$_['entry_name']                   = 'Name';
$_['entry_comment']                = 'Comment';
$_['entry_group']                  = 'Permissions (user group)';
$_['entry_owner']                  = 'Owner (admin user)';
$_['entry_owner_filter']           = 'Owner';
$_['entry_status']                 = 'Status';
$_['entry_expires']                = 'Expires at';
$_['entry_rate_limit']             = 'Rate limit';
$_['entry_ip_allowlist']           = 'IP allowlist';
$_['entry_api_status']             = 'API enabled';
$_['entry_api_rate_limit']         = 'Default rate limit';
$_['entry_api_anon_rate_limit']    = 'Unknown client requests (per minute, per IP)';
$_['entry_api_log_retention_days'] = 'Journal retention (days)';
$_['entry_api_customer_status']    = 'Customer API enabled';
$_['entry_api_customer_max_keys']  = 'Max keys per customer';
$_['entry_api_require_if_match'] = 'Require If-Match on updates';

// Help
$_['help_comment']                 = 'Why the key exists / who uses it (visible only in the panel).';
$_['help_group']                   = 'The key can only do what this group is allowed to do in the panel.';
$_['help_owner']                   = 'The key acts with the permissions of the current user group of its owner. Without an owner the key is shared and uses the group selected next to it.';
$_['help_owner_filter']            = 'Leave the field empty to show every key.';
$_['help_expires']                 = 'Leave empty for a key that never expires. Expired keys are rejected with HTTP 401.';
$_['help_rate_limit']              = 'Requests per minute for this key. 0 uses the default of';
$_['help_ip_allowlist']            = 'Optional. One IP or CIDR block per line (IPv4 or IPv6). Empty = any address.';
$_['help_api_status']              = 'Global switch. When disabled, every API request answers HTTP 503.';
$_['help_api_customer_status']     = 'Global switch for the storefront API (/store-api/v1). When disabled, every customer API request answers HTTP 503.';
$_['help_api_customer_max_keys']   = 'How many API keys a customer may create on their account.';
$_['help_api_rate_limit']          = 'Requests per minute per key, unless the key sets its own limit.';
$_['help_api_anon_rate_limit']     = 'Budget for requests without a valid key, applied before the key lookup. Protects the key table and the journal from unauthenticated flooding. 0 uses the default of 60.';
$_['help_api_log_retention_days']  = 'Journal rows older than this are purged by the daily scheduler task. 0 disables cleanup.';
$_['help_api_require_if_match']  = 'When enabled, PUT/PATCH/DELETE without an If-Match header are rejected with HTTP 428, so two clients can never overwrite each other. Leave it off for simple one-way integrations: a provided If-Match is always validated (HTTP 412 on a stale copy).';

// Button
$_['button_add']                   = 'Add key';
$_['button_edit']                  = 'Edit';
$_['button_save']                  = 'Save';
$_['button_cancel']                = 'Back';
$_['button_delete']                = 'Delete';
$_['button_rotate']                = 'Rotate token';
$_['button_revoke']                = 'Revoke';
$_['button_enable']                = 'Enable';
$_['button_log']                   = 'Request log';
$_['button_docs']                  = 'API documentation';
$_['button_copy']                  = 'Copy';
$_['button_filter']                = 'Filter';

// Confirm
$_['text_rotate_confirm']          = 'Generate a new token? The current token stops working immediately.';
$_['text_revoke_confirm']          = 'Revoke this key? Requests using it will fail with HTTP 401.';
$_['text_delete_confirm']          = 'Delete this key permanently? The request journal is kept.';

// Error
$_['error_permission']             = 'Warning: You do not have permission to modify API keys!';
$_['error_name']                   = 'Name must be between 1 and 128 characters!';
$_['error_group']                  = 'Select an existing user group!';
$_['error_owner']                  = 'Select an existing admin user!';
$_['error_expires']                = 'Expiry date must be a valid date (YYYY-MM-DD) or empty!';
$_['error_ip_allowlist']           = 'IP allowlist may contain only IP addresses or CIDR blocks!';
$_['error_rate_limit']             = 'Rate limit cannot be negative!';
$_['error_key']                    = 'Warning: API key not found!';
$_['error_warning']                = 'Warning: Please check the form carefully for errors!';
