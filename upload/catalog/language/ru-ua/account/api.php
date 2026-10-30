<?php
// Heading
$_['heading_title']  = 'API';
$_['heading_docs']   = 'Документация API';

// Text
$_['text_api']              = 'API-ключи';
$_['text_api_help']         = 'Создавайте ключи для доступа к своим заказам и возвратам из внешнего приложения.';
$_['text_created']          = 'API-ключ "%s" создан.';
$_['text_rotated']          = 'Для "%s" создан новый токен. Прежний токен больше не работает.';
$_['text_revoked']          = 'API-ключ "%s" отозван.';
$_['text_enabled_success']  = 'API-ключ "%s" включён.';
$_['text_deleted']          = 'API-ключ "%s" удалён.';
$_['text_token_once']       = 'Скопируйте токен сейчас — он показывается только один раз';
$_['text_token_help']       = 'Сохраните его в менеджере паролей. В базе хранится только хеш, поэтому восстановить токен позже невозможно.';
$_['text_key_limit']        = 'Вы достигли лимита в %s API-ключей. Удалите ключ, прежде чем создавать новый.';
$_['text_no_keys']          = 'У вас пока нет API-ключей.';
$_['text_enabled']          = 'Включён';
$_['text_disabled']         = 'Отключён';
$_['text_expired']          = 'Истёк';
$_['text_never']            = 'Никогда';

// Scopes
$_['text_scope_products_read']  = 'Просмотр товаров';
$_['text_scope_orders_read']    = 'Просмотр заказов';
$_['text_scope_returns_read']   = 'Просмотр возвратов';
$_['text_scope_returns_write']  = 'Создание возвратов';
$_['text_scope_checkout_write'] = 'Оформление заказов';

// Entry
$_['entry_name']            = 'Название';
$_['entry_comment']         = 'Комментарий';
$_['entry_expires']         = 'Истекает';
$_['entry_ip_allowlist']    = 'Список IP';
$_['entry_scopes']          = 'Области';

$_['help_scopes']           = 'Оставьте все флажки снятыми, чтобы выдать всё, что разрешено вашему аккаунту.';

// Column
$_['column_name']           = 'Название';
$_['column_prefix']         = 'Префикс токена';
$_['column_scopes']         = 'Области';
$_['column_status']         = 'Статус';
$_['column_expires']        = 'Истекает';
$_['column_last_used']      = 'Последнее использование';
$_['column_action']         = 'Действие';

// Button
$_['button_add']            = 'Создать ключ';
$_['button_rotate']         = 'Обновить';
$_['button_revoke']         = 'Отозвать';
$_['button_enable']         = 'Включить';
$_['button_delete']         = 'Удалить';
$_['button_copy']           = 'Копировать';
$_['button_docs']           = 'Документация';
$_['button_back']           = 'Назад';
$_['button_keys']           = 'API-ключи';

$_['text_rotate_confirm']   = 'Создать новый токен? Текущий токен перестанет работать немедленно.';
$_['text_delete_confirm']   = 'Удалить ключ безвозвратно?';

// Error
$_['error_permission']      = 'Доступ к API не включён для вашего аккаунта.';
$_['error_key_limit']       = 'Вы достигли лимита в %s API-ключей.';
$_['error_name']            = 'Название должно содержать от 1 до 128 символов.';
$_['error_expires']         = 'Дата истечения должна быть корректной (ГГГГ-ММ-ДД) или пустой.';
$_['error_ip_allowlist']    = 'Список IP может содержать только IP-адреса или CIDR-блоки.';
$_['error_key']             = 'API-ключ не найден.';
