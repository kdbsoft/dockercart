<?php
// Heading
$_['heading_title']  = 'API';
$_['heading_docs']   = 'Документація API';

// Text
$_['text_api']              = 'API-ключі';
$_['text_api_help']         = 'Створюйте ключі для доступу до своїх замовлень і повернень із зовнішнього застосунку.';
$_['text_created']          = 'API-ключ "%s" створено.';
$_['text_rotated']          = 'Для "%s" створено новий токен. Попередній токен більше не працює.';
$_['text_revoked']          = 'API-ключ "%s" відкликано.';
$_['text_enabled_success']  = 'API-ключ "%s" увімкнено.';
$_['text_deleted']          = 'API-ключ "%s" видалено.';
$_['text_token_once']       = 'Скопіюйте токен зараз — він показується лише один раз';
$_['text_token_help']       = 'Збережіть його в менеджері паролів. У базі зберігається лише хеш, тому відновити токен пізніше неможливо.';
$_['text_key_limit']        = 'Ви досягли ліміту в %s API-ключів. Видаліть ключ, перш ніж створювати новий.';
$_['text_no_keys']          = 'У вас ще немає API-ключів.';
$_['text_enabled']          = 'Увімкнено';
$_['text_disabled']         = 'Вимкнено';
$_['text_expired']          = 'Прострочено';
$_['text_never']            = 'Ніколи';

// Scopes
$_['text_scope_products_read']  = 'Перегляд товарів';
$_['text_scope_orders_read']    = 'Перегляд замовлень';
$_['text_scope_returns_read']   = 'Перегляд повернень';
$_['text_scope_returns_write']  = 'Створення повернень';
$_['text_scope_checkout_write'] = 'Оформлення замовлень';

// Entry
$_['entry_name']            = 'Назва';
$_['entry_comment']         = 'Коментар';
$_['entry_expires']         = 'Спливає';
$_['entry_ip_allowlist']    = 'Список IP';
$_['entry_scopes']          = 'Області';

$_['help_scopes']           = 'Залиште всі прапорці знятими, щоб надати все, що дозволено вашому акаунту.';

// Column
$_['column_name']           = 'Назва';
$_['column_prefix']         = 'Префікс токена';
$_['column_scopes']         = 'Області';
$_['column_status']         = 'Статус';
$_['column_expires']        = 'Спливає';
$_['column_last_used']      = 'Останнє використання';
$_['column_action']         = 'Дія';

// Button
$_['button_add']            = 'Створити ключ';
$_['button_rotate']         = 'Оновити';
$_['button_revoke']         = 'Відкликати';
$_['button_enable']         = 'Увімкнути';
$_['button_delete']         = 'Видалити';
$_['button_copy']           = 'Копіювати';
$_['button_docs']           = 'Документація';
$_['button_back']           = 'Назад';
$_['button_keys']           = 'API-ключі';

$_['text_rotate_confirm']   = 'Створити новий токен? Поточний токен перестане працювати негайно.';
$_['text_delete_confirm']   = 'Видалити ключ назавжди?';

// Error
$_['error_permission']      = 'Доступ до API не увімкнено для вашого акаунта.';
$_['error_key_limit']       = 'Ви досягли ліміту в %s API-ключів.';
$_['error_name']            = 'Назва має містити від 1 до 128 символів.';
$_['error_expires']         = 'Дата спливання має бути коректною (РРРР-ММ-ДД) або порожньою.';
$_['error_ip_allowlist']    = 'Список IP може містити лише IP-адреси або CIDR-блоки.';
$_['error_key']             = 'API-ключ не знайдено.';
