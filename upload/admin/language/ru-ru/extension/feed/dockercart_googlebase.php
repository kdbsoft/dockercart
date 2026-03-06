<?php
/**
 * DockerCart Google Base — Russian language file
 */

// Heading
$_['heading_title']    = 'DockerCart Google Base';

// Text
$_['text_extension']   = 'Расширения';
$_['text_success']     = 'Успех: Вы изменили настройки модуля DockerCart Google Base!';
$_['text_edit']        = 'Изменить DockerCart Google Base';
$_['text_enabled']     = 'Включено';
$_['text_disabled']    = 'Отключено';
$_['text_default']     = '(По умолчанию)';
$_['text_feed_generated'] = 'Фид успешно сгенерирован!';
$_['text_cache_cleared'] = 'Кэш фида успешно очищен!';

// Tabs
$_['tab_general']      = 'Основные параметры';
$_['tab_products']     = 'Параметры товаров';
$_['tab_categories']   = 'Соответствие категорий';
$_['tab_shipping']     = 'Доставка';
$_['tab_custom_labels'] = 'Пользовательские метки';
$_['tab_license']      = 'Лицензия';

// Entry - General
$_['entry_status']     = 'Статус модуля';
$_['entry_cache_hours'] = 'Длительность кэша (часов)';
$_['entry_max_file_size'] = 'Макс. размер файла (МБ)';
$_['entry_max_products'] = 'Макс. товаров на файл';
$_['entry_currency']   = 'Валюта цены';
$_['entry_image_width'] = 'Ширина изображения (px)';
$_['entry_image_height'] = 'Высота изображения (px)';
$_['entry_separate_languages'] = 'Отдельный фид на язык';
$_['entry_separate_stores'] = 'Отдельный фид на магазин';
$_['entry_debug']      = 'Режим отладки';

// Entry - Products
$_['entry_condition']  = 'Состояние по умолчанию';
$_['entry_include_disabled'] = 'Включать отключённые товары';
$_['entry_include_out_of_stock'] = 'Включать товары вне наличия';
$_['entry_brand_source'] = 'Источник бренда';
$_['entry_brand_default'] = 'Бренд по умолчанию';
$_['entry_exclude_products'] = 'Исключить товары';
$_['entry_exclude_categories'] = 'Исключить категории';

// Exclusion UI
$_['text_product_search'] = 'Введите для поиска товаров...';
$_['text_category_search'] = 'Введите для поиска категорий...';
$_['text_no_excluded_products'] = 'Нет исключённых товаров';
$_['text_no_excluded_categories'] = 'Нет исключённых категорий';

// Entry - Shipping
$_['entry_shipping_enabled'] = 'Включить информацию о доставке';
$_['entry_shipping_price'] = 'Цена доставки';
$_['entry_shipping_country'] = 'Страна доставки (ISO)';

// Entry - Categories
$_['entry_category_mapping'] = 'Соответствие категорий Google';

// Entry - Custom Labels
$_['entry_custom_label_0'] = 'Custom Label 0';
$_['entry_custom_label_1'] = 'Custom Label 1';
$_['entry_custom_label_2'] = 'Custom Label 2';
$_['entry_custom_label_3'] = 'Custom Label 3';
$_['entry_custom_label_4'] = 'Custom Label 4';

// Entry - License
$_['entry_license_key'] = 'Ключ лицензии';
$_['entry_public_key'] = 'Открытый ключ';

// Help
$_['help_status']      = 'Включить или отключить генерацию фида Google Base';
$_['help_cache_hours'] = 'Как долго кэшировать фид. По умолчанию: 24 часа.';
$_['help_max_file_size'] = 'Максимальный размер файла перед разбиением. Лимит Google: 50МБ';
$_['help_max_products'] = 'Максимум товаров на файл перед разбиением.';
$_['help_currency']    = 'Выберите валюту для цен в фиде';
$_['help_image_width'] = 'Рекомендуемая ширина изображения: минимум 800px';
$_['help_image_height'] = 'Рекомендуемая высота изображения: минимум 800px';
$_['help_separate_languages'] = 'Создавать отдельный фид для каждого языка (google-base-en.xml, google-base-ru.xml, ...)';
$_['help_separate_stores'] = 'Создавать отдельный фид для каждого магазина';
$_['help_debug']       = 'Включить логирование отладки для устранения проблем';
$_['help_condition']   = 'Состояние товара по умолчанию: new, refurbished или used';
$_['help_include_disabled'] = 'Включить товары со статусом = 0';
$_['help_include_out_of_stock'] = 'Включить товары с quantity = 0';
$_['help_brand_source'] = 'Извлечь бренд от производителя или использовать значение по умолчанию';
$_['help_brand_default'] = 'Имя бренда по умолчанию, если производитель пуст';
$_['help_exclude_products'] = 'Поиск и выбор товаров для исключения из фида';
$_['help_exclude_categories'] = 'Поиск и выбор категорий для исключения из фида';
$_['help_shipping_enabled'] = 'Включить информацию о доставке в фид';
$_['help_shipping_price'] = 'Фиксированная цена доставки (пример: "10.00 USD")';
$_['help_shipping_country'] = 'Двухбуквенный код страны (пример: US, DE, RU)';
$_['help_category_mapping'] = 'Соответствие категорий OpenCart категориям Google. Формат: opencart_id = google_category_id (один в строке).';
$_['help_custom_label'] = 'Пользовательские метки для Google Merchant Center. Поддерживает заполнители: {manufacturer}, {category}, {sku}, {model}';
$_['help_license_key'] = 'Введите ключ лицензии, приобретённый в магазине';
$_['help_public_key']  = 'Открытый ключ RSA для проверки лицензии';

// Condition
$_['text_condition_new'] = 'Новое';
$_['text_condition_refurbished'] = 'Восстановленное';
$_['text_condition_used'] = 'Использованное';

// Brand
$_['text_brand_manufacturer'] = 'От производителя';
$_['text_brand_default'] = 'Использовать по умолчанию';

// Stats
$_['text_statistics']  = 'Статистика фида';
$_['text_total_products'] = 'Всего товаров';
$_['text_enabled_products'] = 'Включённые товары';
$_['text_in_stock_products'] = 'Товары в наличии';
$_['text_last_generated'] = 'Последнее создание';
$_['text_file_size']   = 'Размер файла';
$_['text_feed_url']    = 'URL фида';
$_['text_not_generated'] = 'Ещё не создано';

// Buttons
$_['button_generate']  = 'Создать сейчас';
$_['button_clear_cache'] = 'Очистить кэш';
$_['button_preview']   = 'Предпросмотр';
$_['button_verify_license'] = 'Проверить лицензию';
$_['button_save_license'] = 'Сохранить лицензию';

// Info
$_['text_info']        = '<strong>DockerCart Google Base</strong> генерирует XML фид, совместимый с Google Merchant Center, используя потоковый XML (XMLWriter) для минимального использования памяти.';

// Errors
$_['error_permission'] = 'Предупреждение: У вас нет прав на изменение DockerCart Google Base!';
$_['error_cache_hours'] = 'Длительность кэша должна быть от 1 до 168 часов!';
$_['error_max_file_size'] = 'Максимальный размер файла должен быть от 1 до 50 МБ!';
$_['error_max_products'] = 'Максимум товаров должен быть от 1 000 до 1 000 000!';
$_['error_generation'] = 'Ошибка при создании фида';
$_['error_license_required'] = 'Для использования этого модуля требуется ключ лицензии';
$_['error_license_invalid'] = 'Ключ лицензии недействителен или истёк срок';
