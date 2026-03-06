<?php
// Heading
$_['heading_title']     = 'DockerCart — Менеджер редиректов';
$_['heading_import']    = 'Импорт перенаправлений';

// Text
$_['text_extension']    = 'Расширения';
$_['text_success_add']  = 'Успешно: перенаправление добавлено!';
$_['text_success_edit'] = 'Успешно: перенаправление обновлено!';
$_['text_success_delete'] = 'Успешно: перенаправления удалены!';
$_['text_success_status'] = 'Успешно: статус обновлён!';
$_['text_success_import'] = 'Успешно: импортировано %s перенаправлений!';
$_['text_success_clear_stats'] = 'Успешно: статистика очищена!';
$_['text_no_duplicates'] = 'Дубликаты перенаправлений не найдены.';
$_['text_list']         = 'Список перенаправлений';
$_['text_form']         = 'Форма перенаправления';
$_['text_add']          = 'Добавить перенаправление';
$_['text_edit']         = 'Редактировать перенаправление';
$_['text_confirm']      = 'Вы уверены, что хотите удалить выбранные перенаправления?';
$_['text_confirm_clear_stats'] = 'Вы уверены, что хотите очистить всю статистику?';
$_['text_enabled']      = 'Включено';
$_['text_disabled']     = 'Отключено';
$_['text_yes']          = 'Да';
$_['text_no']           = 'Нет';
$_['text_all']          = 'Все';
$_['text_home']         = 'Главная';
$_['text_statistics']   = 'Статистика';
$_['text_filters']      = 'Фильтры';
$_['text_total_redirects'] = 'Всего перенаправлений';
$_['text_active_redirects'] = 'Активные перенаправления';
$_['text_regex_redirects'] = 'Перенаправления RegEx';
$_['text_total_hits']   = 'Всего переходов';
$_['text_select_file']  = 'Выберите CSV файл';
$_['text_import_format'] = 'Формат импорта (колонки CSV):';
$_['text_export_csv']   = 'Экспорт в CSV';
$_['text_examples']     = 'Примеры';
$_['text_exact_match']  = 'Примеры точного соответствия';
$_['text_regex_examples'] = 'Примеры регулярных выражений';
$_['text_description']  = 'Описание';
$_['text_example_exact'] = 'Простое перенаправление URL';
$_['text_example_category'] = 'Перенаправление URL категории';
$_['text_example_regex_wildcard'] = 'Перенаправить все URL, начинающиеся с /old-, на /new-';
$_['text_example_regex_numbers'] = 'Перенаправить старые URL продуктов с числами';
$_['text_example_regex_language'] = 'Перенаправление с сохранением языка';

// Redirect codes
$_['text_moved_permanently'] = 'Перемещено навсегда';
$_['text_found']        = 'Найдено (Временно)';
$_['text_see_other']    = 'См. другое';
$_['text_temporary_redirect'] = 'Временное перенаправление';
$_['text_permanent_redirect'] = 'Постоянное перенаправление';

// Column
$_['column_old_url']    = 'Старый URL';
$_['column_new_url']    = 'Новый URL';
$_['column_code']       = 'Код';
$_['column_status']     = 'Статус';
$_['column_is_regex']   = 'RegEx';
$_['column_hits']       = 'Переходы';
$_['column_last_hit']   = 'Последний переход';
$_['column_date_added'] = 'Дата добавления';
$_['column_action']     = 'Действие';

// Entry
$_['entry_old_url']     = 'Старый URL';
$_['entry_new_url']     = 'Новый URL';
$_['entry_code']        = 'Код перенаправления';
$_['entry_status']      = 'Статус';
$_['entry_is_regex']    = 'Регулярное выражение';
$_['entry_preserve_query'] = 'Сохранять строку запроса';
$_['entry_debug']         = 'Режим отладки';

// Help
$_['help_old_url']      = 'Введите путь старого URL (например, /old-product или #^/old-(.*)$# для regex)';
$_['help_new_url']      = 'Введите путь нового URL (например, /new-product или /new-$1 для regex)';
$_['help_code']         = '301 = Постоянно (SEO), 302/307 = Временно, 308 = Постоянно (строго)';
$_['help_is_regex']     = 'Включите, если в поле Старый URL используется регулярное выражение';
$_['help_preserve_query'] = 'Сохранять параметры URL (?param=value) при перенаправлении';
$_['help_debug']          = 'Включить логирование отладки (записывается в dockercart_redirects.log). Отключите в продакшене.';

// Button
$_['button_add']        = 'Добавить';
$_['button_edit']       = 'Редактировать';
$_['button_delete']     = 'Удалить';
$_['button_save']       = 'Сохранить';
$_['button_cancel']     = 'Отмена';
$_['button_filter']     = 'Фильтр';
$_['button_import']     = 'Импорт';
$_['button_export']     = 'Экспорт';
$_['button_clear_stats'] = 'Очистить статистику';
$_['button_check_duplicates'] = 'Проверить дубликаты';
$_['button_back']       = 'Отмена';

// Error
$_['error_permission']  = 'Внимание: у вас нет прав для изменения перенаправлений!';
$_['error_old_url']     = 'Старый URL обязателен!';
$_['error_new_url']     = 'Новый URL обязателен!';
$_['error_invalid_regex'] = 'Неверное регулярное выражение!';
$_['error_select']      = 'Пожалуйста, выберите хотя бы одно перенаправление!';
$_['error_file_type']   = 'Неверный тип файла! Пожалуйста, загрузите CSV файл.';
$_['error_upload']      = 'Не удалось загрузить файл!';

// Warning
$_['warning_duplicates_found'] = 'Внимание: найдено %s дублирующих перенаправлений! Пожалуйста, проверьте и удалите.';

// Date format
$_['date_format']       = 'Y-m-d';
$_['datetime_format']   = 'Y-m-d H:i:s';
