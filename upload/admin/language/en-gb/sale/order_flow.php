<?php
// Heading
$_['heading_title']                  = 'Order Flow';

// Text
$_['text_success']                   = 'Success: You have modified the order flow!';

$_['text_flow_steps']                = 'Flow Steps';
$_['text_flow_steps_subtitle']       = 'Ordered chain of statuses. A new order starts at the first step and moves forward one step at a time.';
$_['text_flow_transitions']          = 'Extra Transitions';
$_['text_flow_transitions_subtitle'] = 'Additional allowed transitions (cancellation, refund, etc.) in the form "from status -> to status".';
$_['text_flow_rules']                = 'How it works';
$_['text_flow_rule_forward']         = 'An order always moves forward to the next step of the chain.';
$_['text_flow_rule_transitions']     = 'Extra transitions below are allowed in addition to moving forward.';
$_['text_flow_rule_terminal']        = 'A status with no outgoing transitions (e.g. Cancelled, Refunded) ends the flow.';
$_['text_flow_rule_override']        = 'Operators can still force any status from the order page by enabling "Force" - flow validation is skipped.';

// Buttons
$_['button_flow_add_step']           = 'Add step';
$_['button_flow_add_transition']     = 'Add transition';
$_['button_flow_move_up']            = 'Move up';
$_['button_flow_move_down']          = 'Move down';
$_['button_flow_remove']             = 'Remove';
$_['button_save']                    = 'Save';
$_['button_cancel']                  = 'Cancel';

// Errors
$_['error_permission']               = 'Warning: You do not have permission to modify the order flow!';

// Confirmations
$_['text_flow_confirm_remove']       = 'Remove this step from the flow?';
