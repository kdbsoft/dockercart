<?php
// Heading
$_["heading_title"] = "Оформление заказа";

// Text
$_["text_extension"] = "Дополнения";
$_["text_success"] = "Настройки модуля оформления заказа успешно сохранены!";
$_["text_edit"] = "Редактировать оформление заказа";
$_["text_enabled"] = "Включено";
$_["text_disabled"] = "Отключено";
$_["text_yes"] = "Да";
$_["text_no"] = "Нет";

// Tab titles
$_["tab_general"] = "Основные";
$_["tab_blocks"] = "Блоки оформления заказа";
$_["tab_design"] = "Дизайн и Тема";
$_["tab_fields"] = "Поля формы";
$_["tab_advanced"] = "Расширенные";
$_["tab_license"] = "Лицензия (GPL-3.0)";

// General Settings
$_["entry_status"] = "Статус модуля";
$_["help_status"] = "Включить или отключить модуль оформления заказа DockerCart";
$_["text_checkout_settings"] = "Настройки оформления заказа";
$_["text_select"] = "--- Нет ---";
$_["text_select_country_first"] = "Сначала выберите страну, затем регион";
$_["column_block_fields"] = "Поля";
$_["text_country_region_always_visible"] = "Страна и регион всегда отображаются";

$_["entry_redirect_standard"] = "Перенаправлять стандартное оформление заказа";
$_["help_redirect_standard"] =
    "Автоматически перенаправлять пользователей со стандартного оформления заказа OpenCart (checkout/checkout) на страницу оформления заказа";

$_["entry_show_progress"] = "Показывать прогресс-бар";
$_["help_show_progress"] =
    "Отображать визуальный индикатор прогресса в верхней части оформления заказа";

$_["entry_geo_detect"] = "Автоопределение местоположения";
$_["help_geo_detect"] =
    "Автоматически определять город/регион клиента на основе IP-адреса";

$_["entry_guest_create_account"] = "Создание аккаунта для гостей";
$_["help_guest_create_account"] =
    "Разрешить гостевым клиентам создавать аккаунт во время оформления заказа";
$_["entry_comment"] = "Комментарий к заказу";

$_["entry_default_country"] = "Страна по умолчанию";
$_["help_default_country"] =
    "Заранее выбранная страна, когда клиент ещё не выбрал свою. Это позволяет сразу показывать способы доставки при загрузке страницы.";
$_["entry_default_zone"] = "Регион / Область по умолчанию";
$_["help_default_zone"] =
    "Заранее выбранный регион/область, когда клиент ещё не выбрал свой.";

// Theme Settings
$_["entry_theme"] = "Тема оформления";
$_["help_theme"] = "Выберите визуальную тему для страницы оформления заказа";
$_["text_theme_light"] = "Светлая";
$_["text_theme_dark"] = "Тёмная";
$_["text_theme_custom"] = "Пользовательская (используйте CSS ниже)";

$_["entry_custom_css"] = "Пользовательский CSS";
$_["help_custom_css"] =
    "Добавьте собственные CSS-стили для настройки внешнего вида оформления заказа";

$_["entry_custom_js"] = "Пользовательский JavaScript";
$_["help_custom_js"] =
    "Добавьте собственный JavaScript-код (ES6+) для дополнительной функциональности";

$_["entry_journal3_compat"] = "Совместимость с Journal 3";
$_["help_journal3_compat"] =
    "Включить специальные стили для совместимости с темой Journal 3";

// Form Fields
$_["entry_require_telephone"] = "Обязательный телефон";
$_["entry_require_address2"] = "Обязательный адрес (строка 2)";
$_["entry_require_postcode"] = "Обязательный почтовый индекс";
$_["entry_require_company"] = "Обязательная компания";
$_["entry_show_company"] = 'Показывать поле "Компания"';
$_["entry_show_tax_id"] = 'Показывать поле "ИНН"';

$_["help_required_fields"] =
    "Настройте, какие поля обязательны для заполнения при оформлении заказа";

// Blocks
$_["text_blocks_title"] = "Управление блоками оформления заказа";
$_["text_blocks_info"] =
    "Перетаскивайте блоки для изменения порядка. Нажмите «Настроить» для управления полями в каждом блоке.";
$_["text_configure"] = "Настроить";
$_["text_settings"] = "Параметры";
$_["column_block_name"] = "Название блока";
$_["column_block_enabled"] = "Включён";
$_["error_block_index_not_found"] = "Индекс блока не найден";
$_["error_cache_ttl"] = "TTL кэша должен быть от 0 до 86400 секунд!";
$_["error_exception"] = "Ошибка: %s";
$_["error_invalid_blocks_data"] = "Недействительные данные блоков";
$_["error_license_class_not_found"] = "Класс DockercartLicense не найден";
$_["error_license_invalid"] =
    "Проверка лицензионного ключа отключена в GPL-версии";
$_["error_license_key_empty"] = "Ключ лицензии пуст";
$_["error_license_lib_not_found"] = "Библиотека лицензии не найдена";
$_["error_license_required"] = "В GPL-версии лицензионный ключ не требуется";
$_["error_missing_block_index_or_fields"] =
    "Отсутствует block_index или fields.";
$_["error_permission"] =
    "Внимание: У вас нет прав на изменение модуля оформления заказа!";
$_["error_remove_non_empty_row"] =
    "Нельзя удалить непустую строку. Сначала удалите все поля.";
$_["help_cache_ttl"] =
    "Время жизни кэша в секундах (0 = без кэша, для разработки). Макс: 86400";
$_["help_method_overrides"] =
$_["placeholder_address_2"] = "Квартира, офис и т.д.";
$_["placeholder_city"] = "Город";
$_["placeholder_company"] = "Название компании";
$_["placeholder_country"] = "Выберите страну";
$_["placeholder_email"] = "you@example.com";
$_["placeholder_fax"] = "Телефон 2";
$_["placeholder_firstname"] = "Имя";
$_["placeholder_lastname"] = "Фамилия";
$_["placeholder_payment_address_1"] = "Улица, дом, квартира";
$_["placeholder_payment_address_2"] = "Квартира, офис и т.д.";
$_["placeholder_payment_city"] = "Город";
$_["placeholder_payment_company"] = "Компания";
$_["placeholder_payment_firstname"] = "Имя";
$_["placeholder_payment_lastname"] = "Фамилия";
$_["placeholder_payment_postcode"] = "100000";
$_["placeholder_postcode"] = "100000";
$_["placeholder_telephone"] = "+7 (9xx) xxx-xx-xx";
$_["placeholder_zone"] = "Выберите регион/область";
$_["block_cart"] = "Корзина (итого)";
$_["block_shipping_address"] = "Адрес доставки";
$_["block_payment_address"] = "Платёжный адрес";
$_["block_shipping_method"] = "Способ доставки";
$_["block_payment_method"] = "Способ оплаты";
$_["block_coupon"] = "Купон / Сертификат / Бонусы";
$_["block_comment"] = "Комментарий к заказу";
$_["block_agree"] = "Согласие с условиями";
$_["block_custom_fields"] = "Дополнительные поля";
$_["block_recommended"] = "Рекомендуемые товары";
$_["block_store_info"] = "Информация о магазине";
$_["block_custom_html"] = "Произвольный HTML-блок";
$_["block_customer_details"] = "Данные покупателя";

// Column headers
$_["column_block_collapsible"] = "Сворачиваемый";
$_["column_block_sort"] = "Порядок сортировки";

// Buttons
$_["button_save"] = "Сохранить";
$_["button_cancel"] = "Отмена";
$_["button_apply"] = "Применить";
$_["button_add_row"] = "Добавить строку";
$_["button_verify_license"] = "Проверить лицензию";
$_["button_save_license"] = "Сохранить лицензию";

// Advanced
$_["entry_cache_ttl"] = "TTL кэша шаблона";
$_["entry_debug"] = "Режим отладки";
$_["help_debug"] = "Включить отладку для диагностики";

// License
$_["text_license"] = "Лицензия GNU GPL v3.0";
$_["entry_license_key"] = "Лицензионный ключ";
$_["help_license_key"] = "Лицензионный ключ не требуется в GPL-версии.";
$_["entry_public_key"] = "Публичный ключ";
$_["help_public_key"] = "Проверка публичного ключа не используется в GPL-версии.";
$_["text_license_domain"] = "Тип лицензии";

// Common UI
$_["text_active"] = "Активно";
$_["text_inactive"] = "Неактивно";
$_["text_module"] = "Модуль";
$_["text_module_description"] = "Настройка одностраничного оформления заказа для DockerCart";
$_["confirm_are_you_sure"] = "Вы уверены?";
$_["text_req_abbr"] = "обяз";
$_["text_comment_placeholder"] = "Примечания к заказу, например особые пожелания по доставке.";
$_["help_shipping_required"] = "Отметьте поля адреса как обязательные для этого способа доставки. Рядом с обязательными полями будет отображаться красная звёздочка.";

// Method Overrides
$_["tab_method_overrides"] = "Переопределение методов";
$_["text_method_overrides"] = "Переопределение названий и описаний методов доставки и оплаты";
$_["text_method_overrides_help"] = "Включите и настройте названия и описания для конкретных методов доставки и оплаты. Оставьте поля пустыми для использования значений по умолчанию.";
$_["text_custom_title"] = "Своё название";
$_["text_custom_description"] = "Своё описание";
$_["text_default_title"] = "Название по умолчанию";


// Missing keys from English
$_["text_info"] =
    "<strong>DockerCart Checkout</strong> — бесплатный модуль одностраничного оформления заказа для DockerCart.<br><strong>Лицензия:</strong> GNU GPL v3.0<br><br><strong>Возможности:</strong><br>✓ Современное, быстрое одностраничное оформление заказа<br>✓ Адаптивный дизайн (mobile-first)<br>✓ Работа через AJAX (без перезагрузки страницы)<br>✓ Drag & Drop настройка блоков<br>✓ Гостевое оформление заказа с опциональной регистрацией<br>✓ Поддержка всех методов доставки/оплаты<br>✓ Купоны, сертификаты, бонусные баллы<br>✓ Маска телефона и валидация в реальном времени<br>✓ Светлая/Тёмная темы + произвольный CSS<br>✓ Совместимость с Journal 3<br>✓ Без OCMOD — установка через систему событий";
$_["text_layout_name"] = "Оформление заказа";
$_["text_license_checking"] =
    "Проверка лицензионного ключа отключена в GPL-версии";
$_["text_license_invalid"] = "Лицензионный ключ не требуется в GPL-версии";
$_["text_license_valid"] = "GPL-3.0 (Free)";
$_["text_method_code"] = "Код метода";
$_["text_method_enabled"] = "Переопределение включено";
$_["text_modal_instructions"] =
    "Перемещайте поля для перестановки • Переключатели — показать/скрыть или сделать обязательным";
$_["text_no_fields"] = "Поля не настроены";
$_["text_no_fields_in_row"] = "В этой строке нет полей";
$_["text_no_methods_available"] =
    "Методы недоступны. Пожалуйста, убедитесь, что дополнения доставки/оплаты установлены и включены.";
$_["text_no_rows_configured"] =
    "Строки не настроены. Нажмите «Добавить строку», чтобы начать.";
$_["text_payment_methods"] = "Методы оплаты";
$_["text_required"] = "Обязательно";
$_["text_row"] = "Строка";
$_["text_rows_configuration"] = "Конфигурация строк";
$_["text_save"] = "Сохранить";
$_["text_settings_saved"] = "Настройки успешно сохранены";
$_["text_shipping_methods"] = "Методы доставки";
$_["text_visible"] = "Отображать";
$_["text_address_fields"] = "Поля адреса";
$_["text_field_company"] = "Компания";
$_["text_field_address_1"] = "Адрес (строка 1)";
$_["text_field_address_2"] = "Адрес (строка 2)";
$_["text_field_city"] = "Город";
$_["text_field_postcode"] = "Индекс";
$_["text_field_country"] = "Страна";
$_["text_field_zone"] = "Регион / Область";
$_["help_shipping_fields"] =
    "Выберите, какие поля адреса должны отображаться при выборе этого способа доставки. Скрытые поля будут автоматически заполнены значениями по умолчанию. Пример: для самовывоза скройте адрес и индекс; для Новой Почты скройте только индекс.";
$_["text_cancel"] = "Отмена";
$_["text_columns"] = "Колонки:";
$_["text_field_list"] = "Список полей";
$_["text_block_fields_saved"] = "Поля блока успешно сохранены";
$_["text_block_not_found"] = "Блок не найден.";
$_["text_block_settings"] = "Настройки блока";


// Поля формы
$_["entry_firstname"] = "Имя";
$_["entry_lastname"] = "Фамилия";
$_["entry_email"] = "Email";
$_["entry_telephone"] = "Телефон";
$_["entry_fax"] = "Факс";
$_["entry_company"] = "Компания";
$_["entry_address_1"] = "Адрес (строка 1)";
$_["entry_address_2"] = "Адрес (строка 2)";
$_["entry_city"] = "Город";
$_["entry_postcode"] = "Индекс";
$_["entry_country"] = "Страна";
$_["entry_zone"] = "Регион / Область";
$_["text_step"] = "Шаг";
$_["text_payment_method"] = "Способ оплаты";
