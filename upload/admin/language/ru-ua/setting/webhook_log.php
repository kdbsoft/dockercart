<?php
// Heading
$_['heading_title']              = 'Журнал доставок';

// Text
$_['text_log']                   = 'Доставки';
$_['text_log_help']              = 'Каждая доставка вебхука со статусом, числом попыток и ответом получателя. Неудачные доставки можно повторить с тем же id события.';
$_['text_no_logs']               = 'Доставок пока нет';
$_['text_no_logs_help']          = 'Оформите заказ или отправьте тестовое событие — доставки появятся здесь сразу после постановки в очередь.';
$_['text_pending']               = 'В очереди';
$_['text_sending']               = 'Отправляется';
$_['text_delivered']             = 'Доставлено';
$_['text_failed']                = 'Ошибка';
$_['text_cancelled']             = 'Отменено';
$_['text_disabled']               = 'Отключена';
$_['text_test']                  = 'Тест';
$_['text_summary']               = 'Всего: %s · доставлено %s · ошибок %s · в очереди %s';
$_['text_retry_success']         = 'Доставка снова поставлена в очередь.';
$_['text_info']               = 'Сведения';
$_['text_attempts_used']               = 'Использовано попыток';
$_['text_all']               = 'Все';
$_['text_payload']               = 'Payload';
$_['text_response']              = 'Ответ получателя';
$_['text_attempt']               = 'Попытка %s из %s';
$_['text_next_attempt']          = 'Следующая попытка';
$_['text_locked']                = 'Заблокировано до';
$_['text_subscription_disabled'] = 'Подписка отключена — включите её перед повтором.';

// Column
$_['column_date']                = 'В очереди с';
$_['column_subscription']        = 'Подписка';
$_['column_event']               = 'Событие';
$_['column_event_id']            = 'ID события';
$_['column_status']              = 'Статус';
$_['column_attempts']            = 'Попытки';
$_['column_response']            = 'Ответ';
$_['column_duration']            = 'Длительность';
$_['column_action']              = 'Действие';

// Entry
$_['entry_subscription']         = 'Подписка';
$_['entry_status']               = 'Статус';
$_['entry_event_type']           = 'Тип события';
$_['entry_event_id']             = 'ID события';
$_['entry_date_from']            = 'Дата с';
$_['entry_date_to']              = 'Дата по';

// Button
$_['button_retry']               = 'Повторить';
$_['button_view']                = 'Подробнее';
$_['button_back']                = 'Подписки';
$_['button_filter']              = 'Фильтр';
$_['button_refresh']             = 'Обновить';

// Error
$_['error_permission']           = 'У вас нет прав на изменение вебхуков!';
$_['error_delivery']             = 'Доставка не найдена!';
$_['error_not_retryable']        = 'Повторить можно только неудачные доставки.';
