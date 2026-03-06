<?php
// Heading
$_['heading_title'] = 'DockerCart Импорт YML';

// Text
$_['text_extension'] = 'Расширения';
$_['text_success'] = 'Успешно: Вы изменили настройки модуля DockerCart Импорт YML!';
$_['text_edit'] = 'Редактировать DockerCart Импорт YML';
$_['text_enabled'] = 'Включено';
$_['text_disabled'] = 'Отключено';
$_['text_add_profile'] = 'Добавить профиль';
$_['text_edit_profile'] = 'Редактировать профиль';
$_['text_delete_profile'] = 'Удалить профиль';
$_['text_confirm_delete'] = 'Вы уверены, что хотите удалить профиль?';
$_['text_confirm_import'] = 'Запустить импорт для этого профиля сейчас?';
$_['text_import_success'] = 'Импорт успешно завершён';
$_['text_mode_add'] = 'Только добавлять';
$_['text_mode_update'] = 'Добавлять + обновлять';
$_['text_mode_update_only'] = 'Только обновлять (без добавления)';
$_['text_mode_update_price_qty_only'] = 'Обновлять только цены и остатки';
$_['text_mode_replace'] = 'Удалить все и импортировать';
$_['text_license_valid'] = 'Лицензия успешно проверена.';
$_['text_license_invalid'] = 'Проверка лицензии не пройдена.';
$_['text_import_running'] = 'Импорт выполняется...';
$_['text_import_file_processing'] = 'Идёт обработка файла импорта...';
$_['text_processed'] = 'Обработано';
$_['text_added'] = 'Добавлено';
$_['text_updated'] = 'Обновлено';
$_['text_skipped'] = 'Пропущено';
$_['text_errors'] = 'Ошибок';
$_['text_total_offers_label'] = 'Товары в фиде';
$_['text_processed_offers_label'] = 'Обработано товаров';
$_['text_added_label'] = 'Добавлено';
$_['text_updated_label'] = 'Обновлено';
$_['text_skipped_label'] = 'Пропущено';
$_['text_errors_label'] = 'Ошибок';
$_['text_categories_in_feed_label'] = 'Категорий в фиде';
$_['text_categories_mapped_label'] = 'Категорий сопоставлено';
$_['text_categories_created_label'] = 'Категорий создано';
$_['text_categories_skipped_label'] = 'Категорий пропущено';
$_['text_response'] = 'Ответ';
$_['text_no_profiles'] = 'Профили отсутствуют';

// Entry
$_['entry_status'] = 'Статус';
$_['entry_profile_name'] = 'Название профиля';
$_['entry_feed_url'] = 'Ссылка на YML фид';
$_['entry_profile_store'] = 'Магазин';
$_['entry_profile_language'] = 'Язык';
$_['entry_profile_currency'] = 'Валюта';
$_['entry_default_category'] = 'Категория по умолчанию';
$_['entry_load_categories'] = 'Загружать категории из фида';
$_['entry_import_mode'] = 'Режим импорта';
$_['entry_profile_status'] = 'Статус профиля';
$_['entry_cron_command'] = 'Команда cron';
$_['entry_license_key'] = 'Лицензионный ключ';
$_['entry_public_key'] = 'Публичный ключ';

// Help
$_['help_feed_url'] = 'Прямая ссылка на YML-фид (Yandex Market Language)';
$_['help_import_mode'] = 'Только добавлять: создаёт только новые товары; Добавлять + обновлять: обновляет существующие и создаёт новые; Только обновлять: обновляет только существующие и пропускает новые; Обновлять только цены и остатки: обновляет только цену и количество существующих товаров; Удалить все и импортировать: очищает все товары магазина перед импортом.';
$_['help_load_categories'] = 'Если включено, категории из фида будут импортироваться. Если указана Категория по умолчанию, категории из фида будут создаваться внутри неё как дочерние.';

// Buttons
$_['button_import_now'] = 'Импортировать сейчас';
$_['button_save'] = 'Сохранить';
$_['button_cancel'] = 'Отмена';
$_['button_verify_license'] = 'Проверить лицензию';

// Tab
$_['tab_general'] = 'Общие';
$_['tab_license'] = 'Лицензия';
$_['tab_profiles'] = 'Профили импорта';

// Column
$_['column_profile_name'] = 'Профиль';
$_['column_feed_url'] = 'URL фида';
$_['column_store'] = 'Магазин';
$_['column_mode'] = 'Режим';
$_['column_status'] = 'Статус';
$_['column_last_run'] = 'Последний запуск';
$_['column_cron_command'] = 'Команда cron';
$_['column_action'] = 'Действие';

// Error
$_['error_permission'] = 'Внимание: У вас нет прав для изменения настроек DockerCart Импорт YML!';
$_['error_license_invalid'] = 'Лицензия недействительна';
$_['error_profile_name_required'] = 'Требуется название профиля';
$_['error_feed_url_required'] = 'Требуется URL фида';
$_['error_profile_id_invalid'] = 'Некорректный ID профиля';
$_['error_profile_not_found'] = 'Профиль не найден';
$_['error_curl'] = 'Ошибка cURL';
$_['error_http'] = 'Ошибка HTTP';
$_['error_invalid_response'] = 'Некорректный ответ от endpoint импорта';
$_['error_license_key_empty'] = 'Лицензионный ключ пустой';
$_['error_license_library_not_found'] = 'Библиотека лицензии не найдена';
$_['error_license_class_not_found'] = 'Класс DockercartLicense не найден';
$_['error_prefix'] = 'Ошибка';
$_['error_import_failed'] = 'Импорт не выполнен';
$_['error_load_profile_failed'] = 'Не удалось загрузить профиль';
$_['error_save_failed'] = 'Не удалось сохранить';
$_['error_delete_failed'] = 'Не удалось удалить';
