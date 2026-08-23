<?php
// Залишки по складах
$_['heading_title'] = 'Залишки по складах';
$_['text_list'] = 'Матриця залишків';
$_['text_success'] = 'Успішно: Залишки оновлено!';
$_['text_no_results'] = 'Рядки залишків не знайдено';
$_['text_home'] = 'Головна';
$_['text_pagination'] = 'Показано з %d по %d із %d (%d сторінок)';
$_['text_types'] = [];
$_['text_recalculate'] = 'Перерахувати підсумки';
$_['text_recalculated'] = 'Перераховано товарів: %d, виправлено дрейфів: %d.';
$_['text_unlimited'] = 'Безліміт';
$_['button_update'] = 'Оновити';
$_['button_recalculate'] = 'Перерахувати';
$_['column_warehouse'] = 'Склад';
$_['column_product'] = 'Товар';
$_['column_model'] = 'Код товару';
$_['column_variant'] = 'Варіант';
$_['column_quantity'] = 'Кількість';
$_['column_unlimited'] = 'Безліміт';
$_['column_lead_time'] = 'Строк (дн)';
$_['entry_warehouse'] = 'Склад';
$_['entry_product'] = 'Товар';
$_['entry_model'] = 'Код товару';
$_['entry_sku'] = 'Артикул';
$_['entry_quantity'] = 'Кількість';
$_['entry_unlimited'] = 'Безліміт';
$_['entry_recalculate'] = 'Перерахувати';
$_['text_all'] = 'Усі';
$_['text_search_placeholder'] = 'Пошук залишків за назвою або кодом товару...';
$_['text_network_error'] = 'Мережева помилка.';
$_['error_permission'] = 'Увага: У вас немає прав для зміни залишків!';

// Експорт / імпорт CSV
$_['button_export_csv'] = 'Експорт CSV';
$_['button_import_csv'] = 'Імпорт CSV';
$_['heading_import_csv'] = 'Імпорт залишків CSV';
$_['button_import'] = 'Імпортувати';
$_['button_cancel'] = 'Скасувати';
$_['text_import_format'] = 'Імпорт лише оновлює Кількість, Безліміт і Строк. Рядок знаходиться за stock_id або за warehouse_id + product_id + variant_id; відсутні рядки повідомляються і не створюються. Порожня клітинка — поле не змінюється. quantity — число ≥ 0; unlimited — 0/1, yes/no або true/false; lead_time — цілі дні ≥ 0. Якщо хоча б один рядок не пройшов перевірку — імпорт не застосовується.';
$_['text_import_result'] = 'Успішно: CSV залишків імпортовано — оновлено: %d, пропущено (без змін): %d.';
$_['text_import_line'] = 'Рядок %d: %s';
$_['text_import_more'] = '...і ще %d помилок.';
$_['error_import_upload'] = 'Увага: Завантажте коректний CSV-файл!';
$_['error_import_size'] = 'Увага: Файл перевищує %d МБ!';
$_['error_import_header'] = 'Увага: Заголовок CSV має містити stock_id (або warehouse_id, product_id, variant_id), а також quantity, unlimited та/або lead_time!';
$_['error_import_empty'] = 'Увага: У файлі немає рядків з даними!';
$_['error_import_too_many'] = 'Увага: У файлі більше %d рядків з даними!';
$_['error_import_stock_missing'] = 'рядок залишку #%d не існує';
$_['error_import_position_missing'] = 'немає рядка залишку для складу #%d, товару #%d, варіанту #%d';
$_['error_import_quantity'] = 'некоректна кількість "%s" (очікувалося число ≥ 0)';
$_['error_import_unlimited'] = 'некоректний безліміт "%s" (очікувалося 0/1, yes/no, true/false)';
$_['error_import_lead_time'] = 'некоректний строк "%s" (очікувалося ціле число ≥ 0)';
$_['error_import_duplicate'] = 'дублікат рядка залишку #%d';

// Картка складу (модальне вікно)
$_['text_card_address'] = 'Адреса та контакти';
$_['text_card_map'] = 'Відкрити карту';
$_['text_card_pickup'] = 'Самовивіз';
$_['text_card_supplier'] = 'Постачальник (дропшипінг)';
$_['text_card_schedule'] = 'Години роботи';
$_['text_card_closed'] = 'Зачинено';
$_['text_card_stats'] = 'Зведення залишків';
$_['text_card_positions'] = 'Позицій';
$_['text_card_total_quantity'] = 'Всього на складі';
$_['button_edit'] = 'Редагувати';

// Картка товару (модальне вікно)
$_['text_pcard_here'] = 'На цьому складі';
$_['text_pcard_total'] = 'По всіх складах';
$_['text_pcard_reserved'] = 'У резерві';
$_['text_pcard_attributes'] = 'Атрибути';
$_['text_pcard_description'] = 'Опис';