<?php
// Heading
$_['heading_title']                  = 'Поток заказа';

// Text
$_['text_success']                   = 'Настройки order flow сохранены!';

$_['text_flow_steps']                = 'Шаги flow';
$_['text_flow_steps_subtitle']       = 'Упорядоченная цепочка статусов. Новый заказ начинается с первого шага и движется вперёд по одному шагу за раз.';
$_['text_flow_transitions']          = 'Дополнительные переходы';
$_['text_flow_transitions_subtitle'] = 'Дополнительно разрешённые переходы (отмена, возврат и т.п.) в виде «из статуса -> в статус».';
$_['text_flow_rules']                = 'Как это работает';
$_['text_flow_rule_forward']         = 'Заказ всегда движется вперёд к следующему шагу цепочки.';
$_['text_flow_rule_transitions']     = 'Дополнительные переходы ниже разрешены помимо движения вперёд.';
$_['text_flow_rule_terminal']        = 'Статус без исходящих переходов (например, Отменён, Возврат средств) завершает flow.';
$_['text_flow_rule_override']        = 'Оператор по-прежнему может принудительно выставить любой статус со страницы заказа, включив «Принудительно» — проверка flow пропускается.';

$_['text_flow_shipping_status']      = 'Статус отправки (требует номер ТТН)';
$_['text_flow_shipping_status_hint'] = 'При переходе заказа в этот статус модальное окно запросит номер ТТН и создаст отгрузку с количеством по товарам (частичные отправки).';
$_['text_none']                      = 'Нет';

// Buttons
$_['button_flow_add_step']           = 'Добавить шаг';
$_['button_flow_add_transition']     = 'Добавить переход';
$_['button_flow_move_up']            = 'Вверх';
$_['button_flow_move_down']          = 'Вниз';
$_['button_flow_remove']             = 'Удалить';
$_['button_save']                    = 'Сохранить';
$_['button_cancel']                  = 'Отмена';

// Errors
$_['error_permission']               = 'У вас нет прав на изменение order flow!';

// Confirmations
$_['text_flow_confirm_remove']       = 'Удалить этот шаг из flow?';
