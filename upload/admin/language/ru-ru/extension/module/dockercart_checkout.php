<?php
// Heading
$_['heading_title']         = 'DockerCart Checkout';

// Text
$_['text_extension']        = 'Модули';
$_['text_success']          = 'Настройки модуля DockerCart Checkout успешно сохранены!';
$_['text_edit']             = 'Редактирование DockerCart Checkout';
$_['text_enabled']          = 'Включено';
$_['text_disabled']         = 'Отключено';
$_['text_yes']              = 'Да';
$_['text_no']               = 'Нет';

// Tab titles
$_['tab_general']           = 'Основные';
$_['tab_blocks']            = 'Блоки оформления';
$_['tab_design']            = 'Дизайн и тема';
$_['tab_fields']            = 'Поля формы';
$_['tab_advanced']          = 'Расширенные';
$_['tab_license']           = 'Лицензия';

// General Settings
$_['entry_status']          = 'Статус модуля';
$_['help_status']           = 'Включить или отключить модуль DockerCart Checkout';

$_['entry_redirect_standard'] = 'Перенаправлять стандартный чекаут';
$_['help_redirect_standard']  = 'Автоматически перенаправлять пользователей со стандартного чекаута OpenCart (checkout/checkout) на DockerCart Checkout';

$_['entry_show_progress']   = 'Показывать прогресс-бар';
$_['help_show_progress']    = 'Отображать визуальный индикатор прогресса вверху страницы оформления заказа';

$_['entry_geo_detect']      = 'Автоопределение местоположения';
$_['help_geo_detect']       = 'Автоматически определять город/регион клиента по IP-адресу';

$_['entry_guest_create_account'] = 'Создание аккаунта для гостей';
$_['help_guest_create_account']  = 'Разрешить гостевым покупателям создать аккаунт во время оформления заказа';

// Theme Settings
$_['entry_theme']           = 'Тема оформления';
$_['help_theme']            = 'Выберите визуальную тему для страницы оформления заказа';
$_['text_theme_light']      = 'Светлая';
$_['text_theme_dark']       = 'Тёмная';
$_['text_theme_custom']     = 'Пользовательская (используйте CSS ниже)';

$_['entry_custom_css']      = 'Пользовательский CSS';
$_['help_custom_css']       = 'Добавьте свои CSS-стили для настройки внешнего вида чекаута';

$_['entry_custom_js']       = 'Пользовательский JavaScript';
$_['help_custom_js']        = 'Добавьте свой JavaScript-код (ES6+) для дополнительной функциональности';

$_['entry_journal3_compat'] = 'Совместимость с Journal 3';
$_['help_journal3_compat']  = 'Включить специальные стили для темы Journal 3';

// Form Fields
$_['entry_require_telephone'] = 'Обязательный телефон';
$_['entry_require_address2']  = 'Обязательный адрес (строка 2)';
$_['entry_require_postcode']  = 'Обязательный почтовый индекс';
$_['entry_require_company']   = 'Обязательная компания';
$_['entry_show_company']      = 'Показывать поле "Компания"';
$_['entry_show_tax_id']       = 'Показывать поле "ИНН"';

$_['help_required_fields']    = 'Настройте, какие поля обязательны для заполнения при оформлении заказа';

// Blocks
$_['text_blocks_title']       = 'Управление блоками оформления';
$_['text_blocks_info']        = 'Перетаскивайте блоки для изменения порядка. Нажмите "Настроить" для управления полями в каждом блоке.';
$_['text_configure']          = 'Настроить';
$_['text_settings']           = 'Параметры';
$_['column_block_name']       = 'Название блока';
$_['column_block_enabled']    = 'Включён';
$_['column_block_sort']       = 'Порядок сортировки';
$_['column_block_collapsible'] = 'Сворачиваемый';
$_['text_field_list']         = 'Список полей';
$_['text_no_fields']          = 'Поля не настроены';
$_['text_settings_saved']     = 'Параметры успешно сохранены';
$_['text_cancel']             = 'Отменить';
$_['text_save']               = 'Сохранить';

$_['block_cart']              = 'Корзина (итого)';
$_['block_shipping_address']  = 'Адрес доставки';
$_['block_payment_address']   = 'Платёжный адрес';
$_['block_shipping_method']   = 'Способ доставки';
$_['block_payment_method']    = 'Способ оплаты';
$_['block_coupon']            = 'Купон / Сертификат / Бонусы';
$_['block_comment']           = 'Комментарий к заказу';
$_['block_agree']             = 'Согласие с условиями';
$_['block_custom_fields']     = 'Дополнительные поля';
$_['block_recommended']       = 'Рекомендуемые товары';
$_['block_store_info']        = 'Информация о магазине';
$_['block_custom_html']       = 'Произвольный HTML-блок';

// Advanced Settings
$_['entry_cache_ttl']         = 'TTL кэша шаблона';
$_['help_cache_ttl']          = 'Время жизни кэша в секундах (0 = без кэша, для разработки). Макс: 86400';

$_['entry_recaptcha_enabled'] = 'Включить reCAPTCHA';
$_['help_recaptcha']          = 'Включить Google reCAPTCHA v3 для защиты от спама';

$_['entry_recaptcha_site_key']   = 'Site Key reCAPTCHA';
$_['entry_recaptcha_secret_key'] = 'Secret Key reCAPTCHA';

// Method Overrides
$_['tab_method_overrides']    = 'Переопределение методов';
$_['text_method_overrides']   = 'Переопределение названий и описаний методов доставки и оплаты';
$_['text_method_overrides_help'] = 'Включите и настройте названия и описания для конкретных методов доставки и оплаты. Оставьте поля пустыми для использования значений по умолчанию.';
$_['text_shipping_methods']   = 'Методы доставки';
$_['text_payment_methods']    = 'Методы оплаты';
$_['text_method_code']        = 'Код метода';
$_['text_method_enabled']     = 'Переопределение включено';
$_['text_custom_title']       = 'Пользовательское название';
$_['text_custom_description'] = 'Пользовательское описание';
$_['text_default_title']      = 'Название по умолчанию';
$_['text_no_methods_available'] = 'Методы недоступны. Пожалуйста, убедитесь, что расширения доставки/оплаты установлены и включены.';
$_['help_method_overrides']   = 'Включите переопределение для конкретного метода и введите пользовательское название/описание. Если переопределение не включено, будут использоваться оригинальные название и описание метода.';

$_['entry_debug']             = 'Режим отладки';
$_['help_debug']              = 'Включить логирование для диагностики проблем';

// License
$_['text_license']            = 'Информация о лицензии';
$_['entry_license_key']       = 'Лицензионный ключ';
$_['help_license_key']        = 'Введите лицензионный ключ, приобретённый на маркетплейсе';
$_['entry_public_key']        = 'Публичный ключ';
$_['help_public_key']         = 'RSA публичный ключ для проверки лицензии (предоставляется продавцом)';
$_['text_license_domain']     = 'Лицензия привязана к домену';
$_['button_verify_license']   = 'Проверить лицензию';
$_['button_save_license']     = 'Сохранить лицензию';

$_['text_license_valid']      = 'Лицензия действительна';
$_['text_license_invalid']    = 'Лицензия недействительна или истекла';
// Errors & messages used by AJAX endpoints
$_['error_invalid_blocks_data'] = 'Недействительные данные блоков';
$_['error_license_key_empty']   = 'Ключ лицензии пуст';
$_['error_license_lib_not_found'] = 'Библиотека лицензии не найдена';
$_['error_license_class_not_found'] = 'Класс DockercartLicense не найден';
$_['error_exception']           = 'Ошибка: %s';
$_['error_missing_block_index_or_fields'] = 'Отсутствует block_index или fields.';
$_['error_block_index_not_found'] = 'Индекс блока не найден';
$_['text_block_fields_saved']   = 'Поля блока успешно сохранены';
$_['text_layout_name']          = 'DockerCart Checkout';
// Modal / UI strings
$_['text_block_settings'] = 'Настройки блока';
$_['text_modal_instructions'] = 'Перемещайте поля для перестановки • Переключатели — показать/скрыть или сделать обязательным';
// Rows / Modal UI
$_['text_block_not_found'] = 'Блок не найден.';
$_['button_add_row'] = 'Добавить строку';
$_['text_rows_configuration'] = 'Конфигурация строк';
$_['text_row'] = 'Строка';
$_['text_columns'] = 'Колонки:';
$_['text_visible'] = 'Отображать';
$_['text_required'] = 'Обязательно';
$_['text_no_fields_in_row'] = 'В этой строке нет полей';
$_['text_no_rows_configured'] = 'Строки не настроены. Нажмите «Добавить строку», чтобы начать.';
// JS/UX messages
$_['error_remove_non_empty_row'] = 'Нельзя удалить непустую строку. Сначала удалите все поля.';
$_['confirm_are_you_sure'] = 'Вы уверены?';
// Admin placeholders & block labels
$_['block_customer_details']    = 'Данные покупателя';
$_['placeholder_email']         = 'you@example.com';
$_['placeholder_firstname']     = 'Имя';
$_['placeholder_lastname']      = 'Фамилия';
$_['placeholder_telephone']     = '+7 (9xx) xxx-xx-xx';
$_['placeholder_fax']           = 'Факс';
$_['placeholder_company']       = 'Название компании';
$_['placeholder_address_1']     = 'Улица, дом, квартира';
$_['placeholder_address_2']     = 'Квартира, офис и т.д.';
$_['placeholder_city']          = 'Город';
$_['placeholder_postcode']      = '100000';
$_['placeholder_country']       = 'Выберите страну';
$_['placeholder_zone']          = 'Выберите регион/область';
$_['placeholder_payment_firstname'] = 'Имя';
$_['placeholder_payment_lastname']  = 'Фамилия';
$_['placeholder_payment_company']   = 'Компания';
$_['placeholder_payment_address_1'] = 'Улица, дом, квартира';
$_['placeholder_payment_address_2'] = 'Квартира, офис и т.д.';
$_['placeholder_payment_city']      = 'Город';
$_['placeholder_payment_postcode']  = '100000';
$_['text_license_checking']   = 'Проверка лицензии...';

// Buttons
$_['button_save']             = 'Сохранить';
$_['button_cancel']           = 'Отмена';
$_['button_apply']            = 'Применить';

// Info
$_['text_info']               = '<strong>DockerCart Checkout</strong> — премиум-модуль одностраничного оформления заказа для OpenCart 3.x.<br><br>' .
                                '<strong>Возможности:</strong><br>' .
                                '✓ Современный, быстрый одностраничный чекаут<br>' .
                                '✓ Адаптивный дизайн (mobile-first)<br>' .
                                '✓ Работа через AJAX (без перезагрузки страницы)<br>' .
                                '✓ Drag & Drop настройка блоков<br>' .
                                '✓ Гостевой чекаут с опциональной регистрацией<br>' .
                                '✓ Поддержка всех методов доставки/оплаты<br>' .
                                '✓ Купоны, сертификаты, бонусные баллы<br>' .
                                '✓ Маска телефона и валидация в реальном времени<br>' .
                                '✓ Светлая/Тёмная темы + произвольный CSS<br>' .
                                '✓ Совместимость с Journal 3<br>' .
                                '✓ Без OCMOD — установка через систему событий';

// Errors
$_['error_permission']        = 'Внимание: У вас нет прав на изменение модуля DockerCart Checkout!';
$_['error_cache_ttl']         = 'TTL кэша должен быть от 0 до 86400 секунд!';
$_['error_license_required']  = 'Для использования модуля на продакшене требуется действующий лицензионный ключ';
$_['error_license_invalid']   = 'Недействительный или истёкший лицензионный ключ';
