<?php
// Heading
$_['heading_title']              = 'Webhooks';

// Text
$_['text_subscriptions']         = 'Subscriptions';
$_['text_subscriptions_help']    = 'Outbound HTTP endpoints that are notified about store events. Every delivery is signed with the subscription secret (HMAC-SHA256) and retried with exponential backoff.';
$_['text_subscription']          = 'Subscription';
$_['text_subscription_help']     = 'Endpoint, events and filters of this subscription.';
$_['text_total']                 = 'Subscriptions';
$_['text_filter']                = 'Filter';
$_['text_filter_help']           = 'Filter the list by name, endpoint, event or state.';
$_['text_all']                   = 'All';
$_['text_enabled']               = 'Enabled';
$_['text_disabled']              = 'Disabled';
$_['text_add']                   = 'Add subscription';
$_['text_edit']                  = 'Edit subscription';
$_['text_no_subscriptions']      = 'No subscriptions yet';
$_['text_no_subscriptions_help'] = 'Create a subscription to let an integration receive order and stock events instead of polling the REST API.';
$_['text_events']                = 'Events';
$_['text_events_help']           = 'Pick the events to deliver. Wildcards such as "order.*" are allowed.';
$_['text_filters']               = 'Filters';
$_['text_filters_help']          = 'Optional JSON object. Scalar values must match exactly, arrays match any of the values, and the key must be supported by the selected events.';
$_['text_payload']               = 'Payload';
$_['text_payload_help']          = 'Converts events to JSON and stores them in the outbox together with the business change. Nothing is sent inline: the scheduler worker delivers it.';
$_['text_full_payload']          = 'Send full order payload';
$_['text_full_payload_help']     = 'Adds data.object (order header without contact data, products, totals, payments) to order events. Personal data (e-mail, phone, addresses, comments) is never included — use GET /api/v1/orders/{id} for that.';
$_['text_settings']              = 'Webhook settings';
$_['text_settings_help']         = 'Global limits for every subscription.';
$_['text_info']                  = 'Details';
$_['text_status_card']           = 'Status';
$_['text_status_help']           = 'Enabled subscriptions deliver events to the receiver. A disabled subscription stays in the list but nothing is sent to it.';
$_['text_security']              = 'Security';
$_['text_security_help']         = 'Requests carry X-DockerCart-Signature: sha256=<hmac> over "<timestamp>.<body>" and X-DockerCart-Timestamp. Reject stale timestamps and deduplicate by event id.';
$_['text_delivery']              = 'Delivery';
$_['text_delivery_help']         = 'Retries use 1 m / 5 m / 30 m / 2 h / 12 h; 4xx answers other than 408/429 are final, 410 disables the subscription immediately.';
$_['text_created_message']      = 'Subscription "%s" created.';
$_['text_rotated']               = 'A new secret was generated for "%s". The previous secret no longer signs requests.';
$_['text_enabled_success']       = 'Subscription "%s" enabled.';
$_['text_disabled_success']      = 'Subscription "%s" disabled.';
$_['text_deleted']               = 'Subscription "%s" and its delivery journal deleted.';
$_['text_success']               = 'Success: subscription saved.';
$_['text_settings_saved']        = 'Success: webhook settings saved.';
$_['text_test_sent']             = 'Test event queued. The scheduler worker will deliver it within a minute.';
$_['text_secret_once']           = 'Copy the secret now — it is shown only once';
$_['text_secret_help']           = 'The receiver computes sha256=HMAC_SHA256(timestamp + "." + body, secret) and compares it with X-DockerCart-Signature. Only an encrypted copy is stored, so the secret cannot be recovered later — rotate it if it is lost.';
$_['text_secret_hint']           = 'Secret prefix';
$_['text_endpoint']              = 'Endpoint';
$_['text_url_hint']              = 'https://erp.example.com/hooks/dockercart';
$_['text_filters_hint']          = '{"warehouse_id": 2}';
$_['text_events_selected']       = 'selected';
$_['text_wildcard']              = 'Wildcards';
$_['text_wildcard_help']         = 'order.*, stock.*, product.* or * subscribe to every current and future event of that domain.';
$_['text_implemented']           = 'Available';
$_['text_planned']               = 'Planned';
$_['text_disabled_reason']       = 'Disabled automatically';
$_['text_disabled_gone']         = 'The endpoint answered 410 Gone.';
$_['text_disabled_repeated']     = 'Too many deliveries failed in a row.';
$_['text_consecutive_failures']  = 'Consecutive failures';
$_['text_last_success']          = 'Last success';
$_['text_last_failure']          = 'Last failure';
$_['text_deliveries']            = 'Deliveries';
$_['text_never']                 = 'Never';
$_['text_rotate_confirm']         = 'Rotate the secret? Deliveries signed with the old secret will be rejected by the receiver until it is updated.';
$_['text_created']         = 'Created';
$_['text_docs']                  = 'Documentation';
$_['text_delete_confirm']         = 'Delete this subscription and its delivery journal? This cannot be undone.';
$_['text_kill_switch_off']       = 'Webhook delivery is switched off globally: events are still queued but nothing is sent until it is enabled again.';
$_['text_retention_help']        = 'Delivered and failed deliveries older than this are removed. 0 keeps the journal forever.';
$_['text_timeout_help']          = 'Total HTTP timeout per attempt (seconds).';
$_['text_max_attempts_help']     = 'Attempts per delivery including the first one (1 + retries).';
$_['text_disable_after_help']    = 'Consecutive failed deliveries after which a subscription is disabled automatically.';

// Column
$_['column_name']                = 'Name';
$_['column_url']                 = 'Endpoint';
$_['column_events']              = 'Events';
$_['column_status']              = 'Status';
$_['column_deliveries']          = 'Deliveries (pending / failed)';
$_['column_last_success']        = 'Last success';
$_['column_action']              = 'Action';

// Entry
$_['entry_name']                 = 'Name';
$_['entry_url']                  = 'Endpoint URL';
$_['entry_events']               = 'Events';
$_['entry_filters']              = 'Filters (JSON)';
$_['entry_full_payload']         = 'Full payload';
$_['entry_status']               = 'Status';
$_['entry_subscription']         = 'Subscription';
$_['entry_event']                = 'Event';
$_['entry_webhook_status']       = 'Delivery enabled';
$_['entry_webhook_timeout']      = 'Request timeout (s)';
$_['entry_webhook_max_attempts'] = 'Attempts per delivery';
$_['entry_webhook_disable_after'] = 'Disable after failures';
$_['entry_webhook_retention']    = 'Journal retention (days)';

// Button
$_['button_test']                = 'Send test event';
$_['button_rotate']              = 'Rotate secret';
$_['button_log']                 = 'Delivery journal';
$_['button_keys']                = 'API keys';
$_['button_enable']              = 'Enable';
$_['button_disable']             = 'Disable';
$_['button_copy']                = 'Copy';
$_['button_add']                 = 'Add subscription';
$_['button_save']                = 'Save';
$_['button_cancel']              = 'Back';
$_['button_filter']              = 'Filter';
$_['button_settings']            = 'Save settings';

// Error
$_['error_permission']           = 'Warning: You do not have permission to modify webhooks!';
$_['error_name']                 = 'Name must be between 1 and 128 characters!';
$_['error_url']                  = 'The endpoint URL must be an absolute http(s) URL on port 80 or 443 without credentials.';
$_['error_url_invalid_url']      = 'The endpoint URL is not a valid URL.';
$_['error_url_invalid_scheme']   = 'Only http and https endpoints are allowed.';
$_['error_url_invalid_port']     = 'Only ports 80 and 443 are allowed.';
$_['error_url_invalid_host']     = 'The endpoint host is not a valid hostname.';
$_['error_url_credentials_not_allowed'] = 'Credentials in the URL are not allowed.';
$_['error_url_ssrf_blocked']     = 'The endpoint points to a private or loopback address, which is not allowed.';
$_['error_events']               = 'Select at least one event.';
$_['error_events_unknown']       = 'Unknown or not yet available event: %s';
$_['error_filters']              = 'Filters must be a JSON object of scalar values or arrays of scalar values.';
$_['error_filters_unsupported']  = 'Filter keys not supported by the selected events: %s';
$_['error_subscription']         = 'Warning: The subscription could not be found!';
$_['error_test']                 = 'Warning: The test event could not be queued. Enable the subscription first.';

// Help
$_['help_name']                  = 'Visible only in the panel, e.g. "ERP order sync".';
$_['help_url']                   = 'Absolute https:// URL of the receiving endpoint. Private, loopback and link-local addresses are rejected.';

// Event catalogue descriptions (rendered in the panel).
$_['event_order_created'] = 'A new order was created at checkout.';
$_['event_order_status_changed'] = 'An order moved to another status through a validated transition (or a payment callback).';
$_['event_stock_changed'] = 'A warehouse stock movement was journaled (order, return, transfer, manual adjustment, API batch).';
$_['event_stock_low'] = 'A warehouse row dropped to or below its low-stock threshold.';
$_['event_order_payment_received'] = 'A payment was recorded in the order payment journal.';
$_['event_order_payment_refunded'] = 'A payment reversal or refund was recorded in the order payment journal.';
$_['event_order_shipment_created'] = 'A tracking number (full or partial shipment) was added to an order.';
$_['event_product_created'] = 'A product was created in the catalog.';
$_['event_product_updated'] = 'A product was updated (including API PATCH).';
$_['event_product_deleted'] = 'A product was deleted.';
$_['event_return_created'] = 'A return request was created.';
$_['event_return_completed'] = 'A return reached the Complete status (stock and money already moved).';
$_['event_customer_created'] = 'A customer account was created.';
$_['event_customer_updated'] = 'A customer account was updated.';
$_['event_transfer_completed'] = 'A stock transfer between warehouses was completed.';
$_['event_supplier_status_changed'] = 'The supplier status of a dropshipping order line changed.';
$_['event_review_created'] = 'A product review was submitted.';
$_['event_price_changed'] = 'A product or variant price changed (separate from product.updated).';
$_['event_config_changed'] = 'Store settings were saved (keys only, never values).';
$_['text_test_message'] = 'Test event sent from the panel';
