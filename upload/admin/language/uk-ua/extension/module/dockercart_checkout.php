<?php
// Heading
$_["heading_title"] = "Оформлення замовлення";

// Text
$_["text_extension"] = "Доповнення";
$_["text_success"] = "Успіх: Ви змінили налаштування оформлення замовлення!";
$_["text_edit"] = "Редагувати оформлення замовлення";
$_["text_enabled"] = "Увімкнено";
$_["text_disabled"] = "Вимкнено";
$_["text_yes"] = "Так";
$_["text_no"] = "Ні";

// Tab titles
$_["tab_general"] = "Основні";
$_["tab_blocks"] = "Блоки оформлення замовлення";
$_["tab_design"] = "Дизайн і Тема";
$_["tab_fields"] = "Поля форми";
$_["tab_advanced"] = "Розширені";
$_["tab_license"] = "Ліцензія (GPL-3.0)";

// General Settings
$_["entry_status"] = "Стан модуля";
$_["help_status"] = "Увімкнення або вимкнення модуля оформлення замовлення DockerCart";
$_["text_checkout_settings"] = "Налаштування оформлення замовлення";
$_["text_select"] = "--- Немає ---";
$_["text_select_country_first"] = "Спочатку виберіть країну, потім регіон";
$_["column_block_fields"] = "Поля";
$_["text_country_region_always_visible"] = "Країна та регіон завжди відображаються";

$_["entry_redirect_standard"] = "Перенаправлення стандартного оформлення замовлення";
$_["help_redirect_standard"] =
    "Автоматично перенаправляти користувачів зі стандартного оформлення замовлення OpenCart (checkout/checkout) на сторінку оформлення замовлення";

$_["entry_show_progress"] = "Показати індикатор виконання";
$_["help_show_progress"] =
    "Відображати візуальний індикатор прогресу у верхній частині оформлення замовлення";

$_["entry_geo_detect"] = "Автоматичне визначення місцезнаходження";
$_["help_geo_detect"] =
    "Автоматично визначати місто/регіон клієнта на основі IP-адреси";

$_["entry_guest_create_account"] = "Варіант створення облікового запису гостя";
$_["help_guest_create_account"] =
    "Дозволити гостьовим клієнтам створювати обліковий запис під час оформлення замовлення";
$_["entry_comment"] = "Коментар до замовлення";

$_["entry_default_country"] = "Країна за замовчуванням";
$_["help_default_country"] =
    "Попередньо вибрана країна, коли клієнт ще не вибрав свою. Це дозволяє одразу показувати способи доставки при завантаженні сторінки.";
$_["entry_default_zone"] = "Регіон / Область за замовчуванням";
$_["help_default_zone"] =
    "Попередньо вибраний регіон/область, коли клієнт ще не вибрав свій.";

// Theme Settings
$_["entry_theme"] = "Тема оформлення замовлення";
$_["help_theme"] = "Виберіть візуальну тему для сторінки оформлення замовлення";
$_["text_theme_light"] = "Світла";
$_["text_theme_dark"] = "Темна";
$_["text_theme_custom"] = "Користувацька (використовуйте CSS нижче)";

$_["entry_custom_css"] = "Користувацький CSS";
$_["help_custom_css"] =
    "Додайте власні стилі CSS для налаштування зовнішнього вигляду оформлення замовлення";

$_["entry_custom_js"] = "Користувацький JavaScript";
$_["help_custom_js"] =
    "Додайте власний код JavaScript (ES6+) для додаткової функціональності";

$_["entry_journal3_compat"] = "Сумісність із Journal 3";
$_["help_journal3_compat"] =
    "Увімкнути спеціальні стилі для сумісності з темою Journal 3";

// Form Fields
$_["entry_require_telephone"] = "Обов'язковий телефон";
$_["entry_require_address2"] = "Обов'язковий рядок адреси 2";
$_["entry_require_postcode"] = "Обов'язковий поштовий індекс";
$_["entry_require_company"] = "Обов'язкова компанія";
$_["entry_show_company"] = "Показати поле компанії";
$_["entry_show_tax_id"] = "Показати поле ідентифікаційного номера податку";

$_["help_required_fields"] =
    "Налаштуйте, які поля є обов'язковими для заповнення при оформленні замовлення";

// Blocks
$_["text_blocks_title"] = "Керування блоками оформлення замовлення";
$_["text_blocks_info"] =
    "Перетягуйте блоки для зміни порядку. Натисніть «Налаштувати» для керування полями в кожному блоку.";
$_["text_configure"] = "Налаштувати";
$_["text_settings"] = "Налаштування";
$_["column_block_name"] = "Назва блоку";
$_["column_block_enabled"] = "Увімкнено";
$_["error_block_index_not_found"] = "Індекс блоку не знайдено";
$_["error_cache_ttl"] = "Значення TTL кешу має бути від 0 до 86400 секунд!";
$_["error_exception"] = "Помилка: %s";
$_["error_invalid_blocks_data"] = "Недійсні дані блоків";
$_["error_license_class_not_found"] = "Клас DockercartLicense не знайдено";
$_["error_license_invalid"] =
    "Перевірку ліцензійного ключа вимкнено в GPL-версії";
$_["error_license_key_empty"] = "Ліцензійний ключ порожній";
$_["error_license_lib_not_found"] = "Бібліотеку ліцензій не знайдено";
$_["error_license_required"] = "У GPL-версії ліцензійний ключ не потрібен";
$_["error_missing_block_index_or_fields"] = "Відсутній block_index або поля.";
$_["error_permission"] =
    "Увага: У вас немає дозволу на зміну модуля оформлення замовлення!";
$_["error_remove_non_empty_row"] =
    "Неможливо видалити непорожній рядок. Спочатку видаліть усі поля.";
$_["help_cache_ttl"] =
    "Час життя кешу в секундах (0 = кеш відсутній, корисно для розробки). Макс.: 86400";
    "Увімкнути спеціальні налаштування стилю для теми Journal 3";
$_["help_method_overrides"] =
$_["placeholder_address_1"] = "вул. Шевченка, 123";
$_["placeholder_address_2"] = "Квартира, люкс тощо.";
$_["placeholder_city"] = "Місто";
$_["placeholder_company"] = "Назва компанії";
$_["placeholder_country"] = "Виберіть країну";
$_["placeholder_email"] = "you@example.com";
$_["placeholder_fax"] = "Телефон 2";
$_["placeholder_firstname"] = 'Ім\'я';
$_["placeholder_lastname"] = "Прізвище";
$_["placeholder_payment_address_1"] = "вул. Шевченка, 123";
$_["placeholder_payment_address_2"] = "Квартира, люкс тощо.";
$_["placeholder_payment_city"] = "Місто";
$_["placeholder_payment_company"] = "Компанія";
$_["placeholder_payment_firstname"] = 'Ім\'я';
$_["placeholder_payment_lastname"] = "Прізвище";
$_["placeholder_payment_postcode"] = "10001";
$_["placeholder_postcode"] = "10001";
$_["placeholder_telephone"] = "+1 (555) 000-0000";
$_["placeholder_zone"] = "Виберіть регіон / область";

// Block names
$_["block_cart"] = "Підсумок кошика";
$_["block_shipping_address"] = "Адреса доставки";
$_["block_payment_address"] = "Адреса для оплати";
$_["block_shipping_method"] = "Спосіб доставки";
$_["block_payment_method"] = "Спосіб оплати";
$_["block_coupon"] = "Купон / Ваучер / Бонусні бали";
$_["block_comment"] = "Коментар до замовлення";
$_["block_agree"] = "Умови та положення";
$_["block_custom_fields"] = "Налаштовувані поля";
$_["block_recommended"] = "Рекомендовані товари";
$_["block_store_info"] = "Інформація про магазин";
$_["block_custom_html"] = "Користувацький блок HTML";
$_["block_customer_details"] = "Відомості про клієнта";

// Column headers
$_["column_block_collapsible"] = "Складаний";
$_["column_block_sort"] = "Порядок сортування";

// Buttons
$_["button_save"] = "Зберегти";
$_["button_cancel"] = "Скасувати";
$_["button_apply"] = "Застосувати";
$_["button_add_row"] = "Додати рядок";
$_["button_verify_license"] = "Перевірити ліцензію";
$_["button_save_license"] = "Зберегти ліцензію";

// Advanced
$_["entry_cache_ttl"] = "TTL кешу шаблонів";
$_["entry_debug"] = "Режим налагодження";
$_["help_debug"] = "Увімкнути налагодження для діагностики";

// License
$_["text_license"] = "Ліцензія GNU GPL v3.0";
$_["entry_license_key"] = "Ліцензійний ключ";
$_["help_license_key"] = "Ліцензійний ключ не потрібен у GPL-версії.";
$_["entry_public_key"] = "Відкритий ключ";
$_["help_public_key"] = "Перевірка відкритого ключа не використовується в GPL-версії.";
$_["text_license_domain"] = "Тип ліцензії";

// Common UI
$_["text_active"] = "Активно";
$_["text_inactive"] = "Неактивно";
$_["text_module"] = "Модуль";
$_["text_module_description"] = "Налаштування односторінкового оформлення замовлення для DockerCart";
$_["confirm_are_you_sure"] = "Ви впевнені?";
$_["text_req_abbr"] = "обов";
$_["help_shipping_required"] = "Позначте поля адреси як обов'язкові для цього способу доставки. Біля обов'язкових полів буде показана червона зірочка.";

// Method Overrides
$_["tab_method_overrides"] = "Перевизначення методів";
$_["text_method_overrides"] = "Назва/опис способу доставки та оплати (заміни)";
$_["text_method_overrides_help"] = "Увімкніть та налаштуйте назви й описи для певних способів доставки та оплати. Залиште поля порожніми, щоб використовувати значення за замовчуванням.";
$_["text_custom_title"] = "Власна назва";
$_["text_custom_description"] = "Власний опис";
$_["text_default_title"] = "Назва за замовчуванням";


// Missing keys from English
$_["text_info"] =
    "<strong>DockerCart Checkout</strong> — безкоштовне односторінкове рішення для оформлення замовлення в DockerCart.<br><strong>Ліцензія:</strong> GNU GPL v3.0<br><br> <strong>Особливості:</strong><br> ✓ Сучасне, швидке односторінкове оформлення замовлення<br> ✓ Адаптивний дизайн, орієнтований на мобільні пристрої<br> ✓ На базі AJAX (без перезавантаження сторінок)<br> ✓ Налаштування блоку перетягуванням<br> ✓ Гостьове оформлення замовлення з реєстрацією за бажанням<br> ✓ Підтримуються всі стандартні способи доставки/оплати<br> ✓ Купони, ваучери, бонусні бали<br> ✓ Маскування номера телефону та перевірка в режимі реального часу<br> ✓ Світлі/темні теми + користувацький CSS<br> ✓ Сумісність із Journal 3<br> ✓ Без OCMOD — встановлення лише системи подій";
$_["text_layout_name"] = "Оформлення замовлення";
$_["text_license_checking"] =
    "Перевірку ліцензійного ключа вимкнено в GPL-версії";
$_["text_license_invalid"] = "Ліцензійний ключ не потрібен у GPL-версії";
$_["text_license_valid"] = "GPL-3.0 (Free)";
$_["text_method_code"] = "Код методу";
$_["text_method_enabled"] = "Перевизначення ввімкнено";
$_["text_modal_instructions"] =
    "Перетягуйте поля для зміни порядку • Перемикачі — показати/приховати або встановити як обов'язкові";
$_["text_no_fields"] = "Немає налаштованих полів";
$_["text_no_fields_in_row"] = "У цьому рядку немає полів";
$_["text_no_methods_available"] =
    "Немає доступних методів. Переконайтеся, що доповнення доставки/оплати встановлено та ввімкнено.";
$_["text_no_rows_configured"] =
    "Рядки не налаштовано. Натисніть «Додати рядок», щоб розпочати.";
$_["text_payment_methods"] = "Способи оплати";
$_["text_required"] = "Обов'язково";
$_["text_row"] = "Рядок";
$_["text_rows_configuration"] = "Конфігурація рядків";
$_["text_save"] = "Зберегти";
$_["text_settings_saved"] = "Налаштування успішно збережено";
$_["text_shipping_methods"] = "Способи доставки";
$_["text_visible"] = "Видимий";
$_["text_address_fields"] = "Поля адреси";
$_["text_field_company"] = "Компанія";
$_["text_field_address_1"] = "Адреса (рядок 1)";
$_["text_field_address_2"] = "Адреса (рядок 2)";
$_["text_field_city"] = "Місто";
$_["text_field_postcode"] = "Індекс";
$_["text_field_country"] = "Країна";
$_["text_field_zone"] = "Регіон / Область";
$_["help_shipping_fields"] =
    "Виберіть, які поля адреси мають відображатися при виборі цього способу доставки. Приховані поля будуть автоматично заповнені значеннями за замовчуванням. Приклад: для самовивозу приховайте адресу та індекс; для Нової Пошти приховайте лише індекс.";
$_["text_cancel"] = "Скасувати";
$_["text_columns"] = "Колонки:";
$_["text_field_list"] = "Список полів";
$_["text_block_fields_saved"] = "Поля блоку успішно збережено";
$_["text_block_not_found"] = "Блок не знайдено.";
$_["text_block_settings"] = "Налаштування блоку";


$_["text_comment_placeholder"] = "Примітки до замовлення, наприклад, особливі побажання щодо доставки.";


// Поля форми
$_["entry_firstname"] = "Ім'я";
$_["entry_lastname"] = "Прізвище";
$_["entry_email"] = "Email";
$_["entry_telephone"] = "Телефон";
$_["entry_fax"] = "Факс";
$_["entry_company"] = "Компанія";
$_["entry_address_1"] = "Адреса (рядок 1)";
$_["entry_address_2"] = "Адреса (рядок 2)";
$_["entry_city"] = "Місто";
$_["entry_postcode"] = "Індекс";
$_["entry_country"] = "Країна";
$_["entry_zone"] = "Регіон / Область";
$_["text_step"] = "Крок";
$_["text_payment_method"] = "Спосіб оплати";
