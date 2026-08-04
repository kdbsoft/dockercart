<?php
// Heading
$_['heading_title']     = 'System Events';

// Text
$_['text_success']      = 'Success: You have modified events!';
$_['text_list']         = 'System Event List';
$_['text_event']        = 'This page lists system events — internal store hooks. An event links a store action (trigger, e.g. "customer added") with a handler function (action) registered by an extension or the core. Handlers are registered automatically when extensions are installed and keep the store working. This is NOT a calendar and NOT a list of activities — there is nothing to configure here in the usual sense. Disabling, deleting or editing events without a good reason can break the store (errors, broken pages or missing functionality). Only disable or delete an event if you are absolutely sure it belongs to a previously removed extension. If in doubt, do not touch anything on this page — contact a developer.';
$_['text_info']         = 'Event Information';
$_['text_trigger']      = 'Trigger';
$_['text_action']       = 'Action';
// Subtitle

$_['text_event_list_subtitle'] = 'View registered system event handlers';

// Column
$_['column_code']       = 'Event Code';
$_['column_status']     = 'Status';
$_['column_sort_order'] = 'Sort Order';
$_['column_action']     = 'Action';

// Error
$_['error_permission']  = 'Warning: You do not have permission to modify extensions!';
