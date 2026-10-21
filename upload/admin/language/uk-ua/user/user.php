<?php

$_['column_action'] = 'Дія';
$_['column_date_added'] = 'Дата створення';
$_['column_status'] = 'Статус';
$_['column_username'] = 'Ім\'я користувача';
$_['entry_confirm'] = 'Підтвердити';
$_['entry_email'] = 'Електронна пошта';
$_['entry_firstname'] = 'Ім\'я';
$_['entry_image'] = 'Зображення';
$_['entry_lastname'] = 'Прізвище';
$_['entry_password'] = 'Пароль';
$_['entry_status'] = 'Статус';
$_['entry_user_group'] = 'Група користувачів';
$_['entry_username'] = 'Ім\'я користувача';
$_['error_account'] = 'Увага: Ви не можете видалити свій обліковий запис самостійно!';
$_['error_confirm'] = 'Пароль та підтвердження пароля не збігаються!';
$_['error_email'] = 'Адреса електронної пошти, схоже, недійсна!';
$_['error_exists'] = 'Ім’я користувача вже використовується';
$_['error_exists_email'] = 'Увага: Адреса електронної пошти вже зареєстрована!';
$_['error_exists_username'] = 'Увага: Ім\'я користувача вже використовується!';
$_['error_firstname'] = 'Ім\'я має містити від 1 до 32 символів!';
$_['error_lastname'] = 'Прізвище має містити від 1 до 32 символів!';
$_['error_password'] = 'Пароль має містити від 8 до 40 символів!';
$_['error_permission'] = 'Увага: У вас немає дозволу на зміну користувачів!';
$_['error_single_user'] = 'Увага: Ви не можете самостійно деактивувати свій обліковий запис!';
$_['error_username'] = 'Ім\'я користувача має містити від 3 до 20 символів!';
$_['heading_title'] = 'Користувачі';
$_['text_add'] = 'Додати користувача';
$_['text_edit'] = 'Редагувати користувача';
$_['text_list'] = 'Список користувачів';
$_['text_status_card'] = 'Статус';
$_['text_success'] = 'Успіх: Ви змінили користувачів!';
// Subtitle

$_['text_list_subtitle'] = 'Керуйте обліковими записами адміністраторів';

$_['text_add_user_subtitle'] = 'Створити нового адміністратора';

$_['text_edit_user_subtitle'] = 'Редагувати дані адміністратора';



// API-ключі
$_['text_api_keys']                = 'API-ключі';
$_['text_api_keys_help']           = 'Ключі користувача діють з правами поточної групи цього користувача.';
$_['text_api_keys_empty']          = 'У користувача ще немає API-ключів.';
$_['text_api_enabled']             = 'Увімкнено';
$_['text_api_disabled']            = 'Вимкнено';
$_['text_api_expired']             = 'Прострочений';
$_['text_api_never']               = 'Ніколи';
$_['text_api_limit']               = 'ліміт';
$_['text_api_minute']              = 'хв';
$_['text_api_token_once']          = 'Скопіюйте токен зараз — він показується лише один раз';
$_['text_api_token_help']          = 'Збережіть його в менеджері паролів або секретах CI. У базі зберігається лише хеш, відновити токен пізніше неможливо.';
$_['text_api_key_created']         = 'API-ключ «%s» створено.';
$_['text_api_key_rotated']         = 'Для «%s» згенеровано новий токен. Попередній токен більше не працює.';
$_['text_api_key_revoked']         = 'API-ключ «%s» відкликано.';
$_['text_api_key_enabled']         = 'API-ключ «%s» увімкнено.';
$_['text_api_key_deleted']         = 'API-ключ «%s» видалено.';

$_['column_api_name']              = 'Назва';
$_['column_api_prefix']            = 'Префікс токена';
$_['column_api_status']            = 'Статус';
$_['column_api_expires']           = 'Діє до';
$_['column_api_last_used']         = 'Останнє використання';
$_['column_api_requests']          = 'Запити (24 год / усього)';
$_['column_api_action']            = 'Дія';

$_['entry_api_key_name']           = 'Назва';
$_['entry_api_key_comment']        = 'Коментар';
$_['entry_api_key_expires']        = 'Діє до';
$_['entry_api_key_rate_limit']     = 'Ліміт запитів';
$_['entry_api_key_ip_allowlist']   = 'Список дозволених IP';

$_['help_api_key_expires']         = 'Залиште порожнім, якщо ключ не має прострочуватися.';
$_['help_api_key_rate_limit']      = 'Запитів за хвилину. 0 — використати глобальне значення за замовчуванням.';
$_['help_api_key_ip_allowlist']    = 'Необов\'язково. IP-адреса або CIDR-блок. Порожньо — будь-яка адреса.';

$_['button_api_key_add']           = 'Додати ключ';
$_['button_api_key_create']        = 'Створити ключ';
$_['button_api_key_rotate']        = 'Перегенерувати токен';
$_['button_api_key_revoke']        = 'Відкликати';
$_['button_api_key_enable']        = 'Увімкнути';
$_['button_api_key_delete']        = 'Видалити';
$_['button_api_key_copy']          = 'Копіювати';

$_['text_api_key_rotate_confirm']  = 'Згенерувати новий токен? Поточний токен одразу перестане працювати.';
$_['text_api_key_revoke_confirm']  = 'Відкликати ключ? Запити з ним відхилятимуться з HTTP 401.';
$_['text_api_key_delete_confirm']  = 'Видалити ключ безповоротно? Журнал запитів збережеться.';

$_['error_api_permission']         = 'Увага: у вас немає прав на зміну API-ключів!';
$_['error_api_user']               = 'Увага: користувача не знайдено!';
$_['error_api_name']               = 'Назва має містити від 1 до 128 символів!';
$_['error_api_expires']            = 'Дата завершення має бути коректною (РРРР-ММ-ДД) або порожньою!';
$_['error_api_ip_allowlist']       = 'У списку IP дозволені лише IP-адреси або CIDR-блоки!';
$_['error_api_key']                = 'Увага: API-ключ не знайдено!';
