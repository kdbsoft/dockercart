<?php
// Heading
$_['heading_title']    = 'DockerCart — Генератор SEO';

// Text
$_['text_extension']   = 'Расширения';
$_['text_success']     = 'Успешно: вы изменили модуль генерации SEO DockerCart!';
$_['text_edit']        = 'Редактировать модуль';
$_['text_enabled']     = 'Включено';
$_['text_disabled']    = 'Отключено';

// Tabs
$_['tab_general']      = 'Основные настройки';
$_['tab_statistics']   = 'Статистика';
$_['tab_products']     = 'Товары';
$_['tab_categories']   = 'Категории';
$_['tab_manufacturers'] = 'Производители';
$_['tab_information']  = 'Информационные страницы';

// Entry
$_['entry_status']     = 'Статус автогенерации';
$_['entry_debug']      = 'Режим отладки';
$_['entry_batch_size'] = 'Размер партии обработки';
$_['entry_disable_language_prefix'] = 'Отключить префикс языка';
$_['entry_seo_url_template'] = 'Шаблон SEO URL';
$_['entry_meta_title_template'] = 'Шаблон Meta Title';
$_['entry_meta_description_template'] = 'Шаблон Meta Description';
$_['entry_meta_keyword_template'] = 'Шаблон Meta Keywords';

// Help
$_['help_status']      = 'Автоматическая генерация SEO URL и meta-тегов при создании/редактировании товаров, категорий, производителей и информационных страниц через систему событий OpenCart.';
$_['help_debug']       = 'Включить запись всех операций генерации в базу данных для отладки.';
$_['help_batch_size']  = 'Количество записей, обрабатываемых за один запрос. Рекомендуется: 50-100. Уменьшите для больших каталогов.';
$_['help_disable_language_prefix'] = 'При включении SEO URL для всех языков будут генерироваться без префикса языка (например, "product-name" вместо "en-gb-product-name").';

// Placeholders
$_['text_placeholders'] = 'Доступные плейсхолдеры';
$_['text_common_placeholders'] = 'Общие плейсхолдеры (для всех типов сущностей)';
$_['text_product_placeholders'] = 'Плейсхолдеры, специфичные для товара';

$_['help_placeholder_name'] = 'Название товара/категории/производителя/страницы';
$_['help_placeholder_description'] = 'Описание (усечено до 150 символов)';
$_['help_placeholder_store'] = 'Название магазина';
$_['help_placeholder_city'] = 'Город магазина';
$_['help_placeholder_category'] = 'Название категории товара';
$_['help_placeholder_manufacturer'] = 'Название производителя';
$_['help_placeholder_model'] = 'Модель товара';
$_['help_placeholder_sku'] = 'Артикул (SKU)';
$_['help_placeholder_price'] = 'Цена товара';
$_['help_placeholder_stock'] = 'Наличие товара';

// Statistics
$_['text_products_stats'] = 'Товары';
$_['text_categories_stats'] = 'Категории';
$_['text_manufacturers_stats'] = 'Производители';
$_['text_information_stats'] = 'Информационные страницы';
$_['text_controllers_stats'] = 'Контроллеры';
$_['text_total'] = 'Всего';
$_['text_empty_url'] = 'Без SEO URL';
$_['text_empty_meta'] = 'Без meta-тегов';
$_['text_by_languages'] = 'По языкам:';

// Controllers
$_['text_controller_route'] = 'Маршрут';
$_['text_controller_title'] = 'Название';
$_['text_select_controllers'] = 'Выберите контроллеры';
$_['help_controllers'] = 'Генерация SEO URL для кастомных контроллеров с методом index(), у которых нет SEO URL. Пример: common/home, information/contact и т.д.';
$_['button_scan_controllers'] = 'Сканировать контроллеры';
$_['button_add_controller'] = 'Добавить контроллер';
$_['button_remove_controller'] = 'Удалить';
$_['text_controller_placeholder'] = 'Пример: common/home, information/contact';
$_['text_title_placeholder'] = 'Название страницы';
$_['error_controller_exists'] = 'У этого контроллера уже есть SEO URL';
$_['error_controller_not_found'] = 'Файл контроллера не найден';
$_['text_found_controllers'] = 'Найденные контроллеры:';
$_['button_generate_selected'] = 'Сгенерировать выбранные';
$_['button_select_all'] = 'Выбрать все';
$_['button_deselect_all'] = 'Снять выделение';
$_['text_selected_count'] = 'Выбрано';
$_['help_mass_generation'] = 'Выберите контроллеры с помощью чекбоксов и нажмите "Сгенерировать выбранные" для создания SEO URL для всех языков одновременно.';

// Actions
$_['text_template_settings'] = 'Настройки шаблонов';
$_['text_seo_url_auto'] = 'SEO URL автоматически генерируется из названия сущности. Только meta-поля используют шаблоны ниже.';
$_['text_filters'] = 'Опции генерации';
$_['text_actions'] = 'Действия';
$_['text_overwrite_url'] = 'Перезаписать существующие SEO URL';
$_['text_overwrite_meta'] = 'Перезаписать существующие meta-теги';
$_['help_overwrite_url'] = 'Внимание! При включении ВСЕ существующие SEO URL будут перезаписаны. Снимите флажок, чтобы обрабатывать только пустые записи.';
$_['help_overwrite_meta'] = 'Внимание! При включении ВСЕ существующие meta-теги будут перезаписаны. Снимите флажок, чтобы обрабатывать только пустые поля.';
$_['text_overwrite_warning'] = 'Внимание! При включении опций перезаписи ВСЕ существующие данные будут изменены. Снимите флажок, чтобы обрабатывать только пустые поля.';

$_['button_preview'] = 'Предпросмотр';
$_['button_generate_url'] = 'Генерировать URL';
$_['button_generate_meta'] = 'Генерировать meta';
$_['button_generate_all'] = 'Генерировать всё';
$_['button_save'] = 'Сохранить';
$_['button_cancel'] = 'Отмена';

// Progress
$_['text_processing'] = 'Обработка данных...';
$_['text_items_processed'] = 'элементов обработано';
$_['text_generation_complete'] = 'Генерация успешно завершена!';
$_['text_generation_error'] = 'Ошибка во время генерации!';

// Preview
$_['text_preview_title'] = 'Предпросмотр (10 случайных примеров)';
$_['column_name'] = 'Название';
$_['column_seo_url'] = 'SEO URL';
$_['column_meta_title'] = 'Meta Title';
$_['column_meta_description'] = 'Meta Description';
$_['column_meta_keyword'] = 'Meta Keywords';

// Errors
$_['error_permission'] = 'Внимание: у вас нет прав для изменения модуля генератора SEO DockerCart!';
$_['error_warning']    = 'Внимание: Пожалуйста, внимательно проверьте форму на наличие ошибок!';
$_['error_license_invalid'] = 'Проверка лицензии не прошла';
$_['error_license_required'] = 'Ключ лицензии требуется для использования функции генерации. Пожалуйста, введите и проверьте ключ лицензии на вкладке Общие настройки.';

// Success messages
$_['success_url_generated'] = 'SEO URL успешно сгенерированы! Обработано записей: %s';
$_['success_meta_generated'] = 'Meta-теги успешно сгенерированы! Обработано записей: %s';
$_['success_all_generated'] = 'SEO URL и meta-теги успешно сгенерированы! Обработано записей: %s';

// License
$_['text_license']     = 'Информация о лицензии';
$_['entry_license_key'] = 'Ключ лицензии';
$_['entry_public_key'] = 'Публичный ключ';
$_['help_license_key'] = 'Введите ключ лицензии, приобретённый на маркетплейсе';
$_['help_public_key']  = 'RSA публичный ключ для проверки лицензии. Предоставляется поставщиком.';
$_['text_license_domain'] = 'Лицензия привязана к домену';
$_['text_verify_public_key_required'] = 'Пожалуйста, сначала сохраните публичный ключ перед проверкой лицензии';
$_['button_verify_license'] = 'Проверить лицензию';

// Default Templates - Product
$_['template_product_seo_url'] = '{name}';
$_['template_product_meta_title'] = '{name} {manufacturer} - купить в {store} по цене {price}';
$_['template_product_meta_description'] = 'Купите {name} от {manufacturer}. {description} Цена: {price}. Наличие: {stock}. Доставка. {store}';
$_['template_product_meta_keyword'] = '{name}, {manufacturer}, купить {name}, {category}, {store}';

// Default Templates - Category
$_['template_category_seo_url'] = '{name}';
$_['template_category_meta_title'] = '{name} - купить в {store}';
$_['template_category_meta_description'] = '{name} в интернет-магазине {store}. {description} Широкий ассортимент, доступные цены, доставка.';
$_['template_category_meta_keyword'] = '{name}, купить {name}, каталог {name}, {store}';

// Default Templates - Manufacturer
$_['template_manufacturer_seo_url'] = '{name}';
$_['template_manufacturer_meta_title'] = '{name} - официальный дилер в {store}';
$_['template_manufacturer_meta_description'] = '{name} товары в {store}. {description} Официальная гарантия, доступные цены.';
$_['template_manufacturer_meta_keyword'] = '{name}, {name} товары, официальный дилер {name}';

// Default Templates - Information
$_['template_information_seo_url'] = '{name}';
$_['template_information_meta_title'] = '{name} | {store}';
$_['template_information_meta_description'] = '{description}';
$_['template_information_meta_keyword'] = '{name}, {store}';

// Notices
$_['text_manufacturer_description_missing'] = 'Примечание: таблица manufacturer_description отсутствует в этом магазине. Описание производителя не будет сохранено генератором.';


$_['text_enabled']     = 'Включено';
$_['text_disabled']    = 'Выключено';

// Tabs
$_['tab_general']      = 'Общие настройки';
$_['tab_statistics']   = 'Статистика';
$_['tab_products']     = 'Товары';
$_['tab_categories']   = 'Категории';
$_['tab_controllers']  = 'Контроллеры';
$_['tab_manufacturers'] = 'Производители';
$_['tab_information']  = 'Информационные страницы';

// Entry
$_['entry_status']     = 'Статус автогенерации';
$_['entry_debug']      = 'Режим отладки';
$_['entry_batch_size'] = 'Размер пакета обработки';
$_['entry_disable_language_prefix'] = 'Отключить языковой префикс';
$_['entry_seo_url_template'] = 'Шаблон SEO URL';
$_['entry_meta_title_template'] = 'Шаблон мета-заголовка';
$_['entry_meta_description_template'] = 'Шаблон мета-описания';
$_['entry_meta_keyword_template'] = 'Шаблон мета-ключевых слов';

// Help
$_['help_status']      = 'Автоматическая генерация SEO URL и мета-тегов при создании/редактировании товаров, категорий, производителей и информационных страниц через систему событий OpenCart.';
$_['help_debug']       = 'Включить логирование всех операций генерации в базу данных для отладки.';
$_['help_batch_size']  = 'Количество записей для обработки за один запрос. Рекомендуется: 50-100. Уменьшите для больших каталогов.';
$_['help_disable_language_prefix'] = 'При включении SEO URL для всех языков будут генерироваться без языкового префикса (например, "product-name" вместо "en-gb-product-name").';

// Placeholders
$_['text_placeholders'] = 'Доступные плейсхолдеры';
$_['text_common_placeholders'] = 'Общие плейсхолдеры (для всех типов сущностей)';
$_['text_product_placeholders'] = 'Плейсхолдеры для товаров';

$_['help_placeholder_name'] = 'Название товара/категории/производителя/страницы';
$_['help_placeholder_description'] = 'Описание (обрезается до 150 символов)';
$_['help_placeholder_store'] = 'Название магазина';
$_['help_placeholder_city'] = 'Город магазина';
$_['help_placeholder_category'] = 'Название категории товара';
$_['help_placeholder_manufacturer'] = 'Название производителя';
$_['help_placeholder_model'] = 'Модель товара';
$_['help_placeholder_sku'] = 'Артикул товара';
$_['help_placeholder_price'] = 'Цена товара';
$_['help_placeholder_stock'] = 'Наличие товара';

// Statistics
$_['text_products_stats'] = 'Товары';
$_['text_categories_stats'] = 'Категории';
$_['text_manufacturers_stats'] = 'Производители';
$_['text_information_stats'] = 'Информационные страницы';
$_['text_total'] = 'Всего';
$_['text_empty_url'] = 'Без SEO URL';
$_['text_empty_meta'] = 'Без мета-тегов';
$_['text_by_languages'] = 'По языкам:';

// Actions
$_['text_template_settings'] = 'Настройки шаблонов';
$_['text_seo_url_auto'] = 'SEO URL автоматически генерируется из названия. Шаблоны используются только для мета-полей.';
$_['text_filters'] = 'Параметры генерации';
$_['text_actions'] = 'Действия';
$_['text_overwrite_url'] = 'Перезаписать существующие SEO URL';
$_['text_overwrite_meta'] = 'Перезаписать существующие мета-теги';
$_['help_overwrite_url'] = 'Внимание! При включении ВСЕ существующие SEO URL будут перезаписаны. Отключите для обработки только пустых записей.';
$_['help_overwrite_meta'] = 'Внимание! При включении ВСЕ существующие мета-теги будут перезаписаны. Отключите для обработки только пустых записей.';
$_['text_overwrite_warning'] = 'Внимание! При включении опций перезаписи будут изменены ВСЕ существующие данные. Снимите галочки для обработки только пустых полей.';

$_['button_preview'] = 'Предпросмотр';
$_['button_generate_url'] = 'Генерировать URL';
$_['button_generate_meta'] = 'Генерировать мета-теги';
$_['button_generate_all'] = 'Генерировать всё';
$_['button_save'] = 'Сохранить';
$_['button_cancel'] = 'Отмена';

// Progress
$_['text_processing'] = 'Обработка данных...';
$_['text_items_processed'] = 'записей обработано';
$_['text_generation_complete'] = 'Генерация успешно завершена!';
$_['text_generation_error'] = 'Ошибка при генерации!';

// Preview
$_['text_preview_title'] = 'Предпросмотр (10 случайных примеров)';
$_['column_name'] = 'Название';
$_['column_seo_url'] = 'SEO URL';
$_['column_meta_title'] = 'Мета-заголовок';
$_['column_meta_description'] = 'Мета-описание';
$_['column_meta_keyword'] = 'Мета-ключевые слова';

// Errors
$_['error_permission'] = 'Внимание: У вас нет прав для изменения модуля DockerCart SEO Генератор!';
$_['error_warning']    = 'Внимание: Пожалуйста, внимательно проверьте форму на наличие ошибок!';
$_['error_license_invalid'] = 'Проверка лицензии не удалась';
$_['error_license_required'] = 'Для использования функции генерации требуется лицензионный ключ. Пожалуйста, введите и проверьте ваш лицензионный ключ во вкладке Общие настройки.';

// Success messages
$_['success_url_generated'] = 'SEO URL успешно сгенерированы! Обработано записей: %s';
$_['success_meta_generated'] = 'Мета-теги успешно сгенерированы! Обработано записей: %s';
$_['success_all_generated'] = 'SEO URL и мета-теги успешно сгенерированы! Обработано записей: %s';

// License
$_['text_license']     = 'Информация о лицензии';
$_['entry_license_key'] = 'Ключ лицензии';
$_['entry_public_key'] = 'Публичный ключ';
$_['help_license_key'] = 'Введите ключ лицензии, приобретённый в маркетплейсе';
$_['help_public_key']  = 'RSA публичный ключ для проверки лицензии. Предоставляется продавцом.';
$_['text_license_domain'] = 'Лицензия привязана к домену';
$_['text_verify_public_key_required'] = 'Пожалуйста, сначала сохраните публичный ключ перед проверкой лицензии';
$_['button_verify_license'] = 'Проверить лицензию';

// Default Templates - Product
$_['template_product_seo_url'] = '{name}';
$_['template_product_meta_title'] = '{name} {manufacturer} - купить в {store} по цене {price}';
$_['template_product_meta_description'] = 'Купить {name} от {manufacturer}. {description} Цена: {price}. Наличие: {stock}. Доставка по всей России. {store}';
$_['template_product_meta_keyword'] = '{name}, {manufacturer}, купить {name}, {category}, {store}';

// Default Templates - Category
$_['template_category_seo_url'] = '{name}';
$_['template_category_meta_title'] = '{name} - купить в {store}';
$_['template_category_meta_description'] = '{name} в интернет-магазине {store}. {description} Широкий ассортимент, доступные цены, доставка.';
$_['template_category_meta_keyword'] = '{name}, купить {name}, каталог {name}, {store}';

// Default Templates - Manufacturer
$_['template_manufacturer_seo_url'] = '{name}';
$_['template_manufacturer_meta_title'] = '{name} - официальный дилер в {store}';
$_['template_manufacturer_meta_description'] = 'Товары {name} в {store}. {description} Официальная гарантия, доступные цены.';
$_['template_manufacturer_meta_keyword'] = '{name}, товары {name}, официальный дилер {name}';

// Default Templates - Information
$_['template_information_seo_url'] = '{name}';
$_['template_information_meta_title'] = '{name} | {store}';
$_['template_information_meta_description'] = '{description}';
$_['template_information_meta_keyword'] = '{name}, {store}';

// Уведомления
$_['text_manufacturer_description_missing'] = 'Внимание: таблица manufacturer_description отсутствует в базе. Мета для производителей сохраняться не будут.';
