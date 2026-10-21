<?php
// Heading
$_['heading_title']              = 'Delivery Journal';

// Text
$_['text_log']                   = 'Deliveries';
$_['text_log_help']              = 'Every webhook delivery with its status, attempt count and the receiver response. Failed deliveries can be re-queued with the same event id.';
$_['text_no_logs']               = 'No deliveries recorded yet';
$_['text_no_logs_help']          = 'Place an order or send a test event — deliveries appear here as soon as they are queued.';
$_['text_pending']               = 'Pending';
$_['text_sending']               = 'Sending';
$_['text_delivered']             = 'Delivered';
$_['text_failed']                = 'Failed';
$_['text_cancelled']             = 'Cancelled';
$_['text_disabled']               = 'Disabled';
$_['text_test']                  = 'Test';
$_['text_summary']               = 'Total: %s · delivered %s · failed %s · pending %s';
$_['text_retry_success']         = 'Success: the delivery was queued again.';
$_['text_info']               = 'Details';
$_['text_attempts_used']               = 'Attempts used';
$_['text_all']               = 'All';
$_['text_payload']               = 'Payload';
$_['text_response']              = 'Receiver response';
$_['text_attempt']               = 'Attempt %s of %s';
$_['text_next_attempt']          = 'Next attempt';
$_['text_locked']                = 'Locked until';
$_['text_subscription_disabled'] = 'The subscription is disabled — enable it before retrying.';

// Column
$_['column_date']                = 'Queued';
$_['column_subscription']        = 'Subscription';
$_['column_event']               = 'Event';
$_['column_event_id']            = 'Event ID';
$_['column_status']              = 'Status';
$_['column_attempts']            = 'Attempts';
$_['column_response']            = 'Response';
$_['column_duration']            = 'Duration';
$_['column_action']              = 'Action';

// Entry
$_['entry_subscription']         = 'Subscription';
$_['entry_status']               = 'Status';
$_['entry_event_type']           = 'Event type';
$_['entry_event_id']             = 'Event ID';
$_['entry_date_from']            = 'Date from';
$_['entry_date_to']              = 'Date to';

// Button
$_['button_retry']               = 'Retry';
$_['button_view']                = 'Details';
$_['button_back']                = 'Subscriptions';
$_['button_filter']              = 'Filter';
$_['button_refresh']             = 'Refresh';

// Error
$_['error_permission']           = 'Warning: You do not have permission to modify webhooks!';
$_['error_delivery']             = 'Warning: The delivery could not be found!';
$_['error_not_retryable']        = 'Warning: Only failed deliveries can be retried.';
