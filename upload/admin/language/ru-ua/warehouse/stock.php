<?php
// Остатки по складам
$_['heading_title'] = 'Остатки по складам';
$_['text_list'] = 'Матрица остатков';
$_['text_success'] = 'Успешно: Остатки обновлены!';
$_['text_no_results'] = 'Строки остатков не найдены';
$_['text_home'] = 'Главная';
$_['text_pagination'] = 'Показано с %d по %d из %d (%d страниц)';
$_['text_types'] = [];
$_['text_recalculate'] = 'Пересчитать итоги';
$_['text_recalculated'] = 'Пересчитано товаров: %d, исправлено дрейфов: %d.';
$_['text_unlimited'] = 'Безлимит';
$_['button_update'] = 'Обновить';
$_['button_recalculate'] = 'Пересчитать';
$_['column_warehouse'] = 'Склад';
$_['column_product'] = 'Товар';
$_['column_model'] = 'Код товара';
$_['column_variant'] = 'Вариант';
$_['column_quantity'] = 'Количество';
$_['column_reserved'] = 'Зарезервировано';
$_['column_available'] = 'Доступно';
$_['column_unlimited'] = 'Безлимит';
$_['column_lead_time'] = 'Срок (дн)';
$_['entry_warehouse'] = 'Склад';
$_['entry_product'] = 'Товар';
$_['entry_model'] = 'Код товара';
$_['entry_sku'] = 'Артикул';
$_['entry_quantity'] = 'Количество';
$_['entry_unlimited'] = 'Безлимит';
$_['entry_recalculate'] = 'Пересчитать';
$_['text_all'] = 'Все';
$_['text_search_placeholder'] = 'Поиск остатков по названию или коду товара...';
$_['text_network_error'] = 'Сетевая ошибка.';
$_['error_permission'] = 'Внимание: У вас нет прав для изменения остатков!';

// Экспорт / импорт CSV
$_['button_export_csv'] = 'Экспорт CSV';
$_['button_import_csv'] = 'Импорт CSV';
$_['heading_import_csv'] = 'Импорт остатков CSV';
$_['button_import'] = 'Импортировать';
$_['button_cancel'] = 'Отмена';
$_['text_import_format'] = 'Импорт только обновляет Количество, Безлимит и Срок. Строка находится по stock_id либо по warehouse_id + product_id + variant_id; отсутствующие строки сообщаются и не создаются. Пустая ячейка — поле не меняется. quantity — число ≥ 0; unlimited — 0/1, yes/no или true/false; lead_time — целые дни ≥ 0. Если хотя бы одна строка не прошла проверку — импорт не применяется.';
$_['text_import_result'] = 'Успешно: CSV остатков импортирован — обновлено: %d, пропущено (без изменений): %d.';
$_['text_import_line'] = 'Строка %d: %s';
$_['text_import_more'] = '...и ещё %d ошибок.';
$_['error_import_upload'] = 'Внимание: Загрузите корректный CSV-файл!';
$_['error_import_size'] = 'Внимание: Файл превышает %d МБ!';
$_['error_import_header'] = 'Внимание: Заголовок CSV должен содержать stock_id (или warehouse_id, product_id, variant_id), а также quantity, unlimited и/или lead_time!';
$_['error_import_empty'] = 'Внимание: В файле нет строк с данными!';
$_['error_import_too_many'] = 'Внимание: В файле больше %d строк с данными!';
$_['error_import_stock_missing'] = 'строка остатка #%d не существует';
$_['error_import_position_missing'] = 'нет строки остатка для склада #%d, товара #%d, варианта #%d';
$_['error_import_quantity'] = 'некорректное количество "%s" (ожидалось число ≥ 0)';
$_['error_import_unlimited'] = 'некорректный безлимит "%s" (ожидалось 0/1, yes/no, true/false)';
$_['error_import_lead_time'] = 'некорректный срок "%s" (ожидалось целое число ≥ 0)';
$_['error_import_duplicate'] = 'дубликат строки остатка #%d';

// Карточка склада (модальное окно)
$_['text_card_address'] = 'Адрес и контакты';
$_['text_card_map'] = 'Открыть карту';
$_['text_card_pickup'] = 'Самовывоз';
$_['text_card_supplier'] = 'Поставщик (дропшиппинг)';
$_['text_card_schedule'] = 'Часы работы';
$_['text_card_closed'] = 'Закрыто';
$_['text_card_stats'] = 'Сводка по остаткам';
$_['text_card_positions'] = 'Позиций';
$_['text_card_total_quantity'] = 'Всего на складе';
$_['button_edit'] = 'Редактировать';

// Карточка товара (модальное окно)
$_['text_pcard_here'] = 'На этом складе';
$_['text_pcard_total'] = 'По всем складам';
$_['text_pcard_reserved'] = 'В резерве';
$_['text_pcard_available'] = 'Доступно';
$_['text_pcard_attributes'] = 'Атрибуты';
$_['text_pcard_description'] = 'Описание';