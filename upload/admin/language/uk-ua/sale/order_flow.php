<?php
// Heading
$_['heading_title']                  = 'Order flow';

// Text
$_['text_success']                   = 'Налаштування order flow збережено!';

$_['text_flow_steps']                = 'Кроки flow';
$_['text_flow_steps_subtitle']       = 'Впорядкований ланцюжок статусів. Нове замовлення починається з першого кроку та рухається вперед на один крок за раз.';
$_['text_flow_transitions']          = 'Додаткові переходи';
$_['text_flow_transitions_subtitle'] = 'Додатково дозволені переходи (скасування, повернення тощо) у вигляді «зі статусу -> у статус».';
$_['text_flow_rules']                = 'Як це працює';
$_['text_flow_rule_forward']         = 'Замовлення завжди рухається вперед до наступного кроку ланцюжка.';
$_['text_flow_rule_transitions']     = 'Додаткові переходи нижче дозволені, окрім руху вперед.';
$_['text_flow_rule_terminal']        = 'Статус без вихідних переходів (наприклад, Скасовано, Повернення коштів) завершує flow.';
$_['text_flow_rule_override']        = 'Оператор може примусово виставити будь-який статус зі сторінки замовлення, увімкнувши «Примусово» — перевірка flow пропускається.';

$_['text_flow_shipping_status']      = 'Статус відправлення (вимагає номер ТТН)';
$_['text_flow_shipping_status_hint'] = 'Під час переходу замовлення у цей статус модальне вікно запитає номер ТТН та створить відвантаження з кількістю по товарах (часткові відвантаження).';
$_['text_none']                      = 'Немає';

// Buttons
$_['button_flow_add_step']           = 'Додати крок';
$_['button_flow_add_transition']     = 'Додати перехід';
$_['button_flow_move_up']            = 'Вгору';
$_['button_flow_move_down']          = 'Вниз';
$_['button_flow_remove']             = 'Видалити';
$_['button_save']                    = 'Зберегти';
$_['button_cancel']                  = 'Скасувати';

// Errors
$_['error_permission']               = 'У вас немає прав на зміну order flow!';

// Confirmations
$_['text_flow_confirm_remove']       = 'Видалити цей крок з flow?';
