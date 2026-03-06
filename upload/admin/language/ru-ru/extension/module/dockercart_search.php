<?php
// Заголовок
$_['heading_title']     = 'DockerCart Search (Manticore)';

// Текст
$_['text_extension']    = 'Расширения';
$_['text_success']      = 'Успех: Настройки модуля сохранены!';
$_['text_edit']         = 'Редактирование настроек DockerCart Search';
$_['text_enabled']      = 'Включено';
$_['text_disabled']     = 'Выключено';
$_['text_yes']          = 'Да';
$_['text_no']           = 'Нет';

// Вкладки
$_['tab_general']       = 'Основные настройки';
$_['tab_connection']    = 'Настройки подключения';
$_['tab_morphology']    = 'Язык и морфология';
$_['tab_indexing']      = 'Индексация';
$_['tab_autocomplete']  = 'Автодополнение';

// Поля
$_['entry_status']      = 'Статус';
$_['entry_host']        = 'Хост Manticore';
$_['entry_port']        = 'Порт MySQL протокола';
$_['entry_http_port']   = 'Порт HTTP API';
$_['entry_autocomplete'] = 'Включить автодополнение';
$_['entry_autocomplete_limit'] = 'Лимит автодополнения';
$_['entry_min_chars']   = 'Минимум символов';
$_['entry_results_limit'] = 'Лимит результатов поиска';
$_['entry_morphology']  = 'Морфология';
$_['entry_ranking']     = 'Режим ранжирования';
$_['entry_field_weights'] = 'Веса полей';
$_['entry_weight_title'] = 'Вес названия';
$_['entry_weight_description'] = 'Вес описания';
$_['entry_weight_meta'] = 'Вес мета-данных';
$_['entry_weight_tags'] = 'Вес тегов';

// Кнопки
$_['button_save']       = 'Сохранить';
$_['button_cancel']     = 'Отмена';
$_['button_test_connection'] = 'Проверить соединение';
$_['button_reindex']    = 'Переиндексировать всё';

// Помощь
$_['help_status']       = 'Включить или выключить поиск Manticore';
$_['help_host']         = 'Hostname Manticore Search (по умолчанию: manticore)';
$_['help_port']         = 'Порт MySQL протокола (по умолчанию: 9306)';
$_['help_http_port']    = 'Порт HTTP API для автодополнения (по умолчанию: 9308)';
$_['help_autocomplete'] = 'Включить AJAX автодополнение в поле поиска';
$_['help_autocomplete_limit'] = 'Количество подсказок в выпадающем списке';
$_['help_min_chars']    = 'Минимальное количество символов для поиска/автодополнения';
$_['help_results_limit'] = 'Количество результатов поиска на странице по умолчанию';
$_['help_morphology']   = 'Выберите ОДИН морфологический процессор для этого языка (стемминг или лемматизация). После изменения необходимо пересоздать индексы!';
$_['help_ranking']      = 'Алгоритм ранжирования результатов поиска';
$_['help_field_weights'] = 'Важность каждого поля при поиске (больше = важнее)';
$_['help_reindex']      = 'Перестроить поисковый индекс для всех товаров, категорий, производителей и информационных страниц';

// Ошибки
$_['error_permission']  = 'Предупреждение: У вас нет прав для изменения этого модуля!';
$_['error_host']        = 'Хост обязателен!';
$_['error_port']        = 'Порт должен быть числом!';
$_['error_autocomplete_limit'] = 'Лимит автодополнения должен быть числом!';
$_['error_min_chars']   = 'Минимум символов должен быть числом!';
$_['error_results_limit'] = 'Лимит результатов должен быть числом!';

// Успех
$_['text_connection_success'] = 'Успешно подключено к Manticore Search!';
$_['text_connection_failed'] = 'Не удалось подключиться к Manticore Search!';
$_['text_reindex_success'] = 'Индексация завершена: %s товаров, %s категорий, %s производителей, %s информационных страниц';
$_['text_reindex_failed'] = 'Индексация не удалась!';
