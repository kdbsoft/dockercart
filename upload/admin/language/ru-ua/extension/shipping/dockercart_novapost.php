<?php
/**
 * DockerCart NovaPost Shipping Module - Admin Language (Russian)
 */

$_['heading_title']          = 'Новая Почта';
$_['text_dockercart_novapost'] = '<a href="https://novapost.com/" target="_blank"><img src="view/image/shipping/novapost.png" alt="NovaPost" title="NovaPost" style="border: 1px solid #EEEEEE;" width="150" /></a>';
$_['text_extension']         = 'Дополнения';
$_['text_success']           = 'Настройки успешно сохранены!';
$_['text_edit']              = 'Редактировать NovaPost доставку';
$_['text_enabled']           = 'Включено';
$_['text_disabled']          = 'Отключено';
$_['text_syncing']           = 'Синхронизация...';
$_['text_sync_complete']     = 'Синхронизация успешно завершена!';
$_['text_sync_error']        = 'Ошибка синхронизации!';
$_['text_no_sync']           = 'Синхронизация ещё не выполнялась';
$_['text_subtitle']          = 'NovaPost Доставка и отделения';
$_['text_api_config']        = 'API Конфигурация';
$_['text_search']            = 'Поиск';
$_['text_search_placeholder'] = 'Название, адрес, город...';
$_['text_country']           = 'Страна';
$_['text_all']               = 'Все';
$_['text_type']              = 'Тип';
$_['text_no_divisions']      = 'Отделения не загружены. Нажмите "Синхронизировать отделения" для получения данных из NovaPost API.';
$_['text_no_sync_history']   = 'История синхронизации пуста.';

$_['tab_dashboard']          = 'Панель';
$_['tab_general']            = 'Общие';
$_['tab_divisions']          = 'Отделения';
$_['tab_sync_log']           = 'Журнал синхронизации';
$_['tab_tariffs']            = 'Тарифы';
$_['tab_region_mapping']     = 'Маппинг регионов';

$_['entry_api_key']          = 'API ключ';
$_['entry_api_key_help']     = 'Ваш NovaPost API ключ из аккаунта';
$_['entry_status']           = 'Статус';
$_['entry_sandbox']          = 'Тестовый режим';
$_['entry_sandbox_help']     = 'Использовать тестовое окружение для API запросов';
$_['entry_country_codes']    = 'Страны';
$_['entry_country_codes_help'] = 'Выберите страны для загрузки отделений';
$_['entry_division_categories'] = 'Типы отделений';
$_['entry_division_categories_help'] = 'Выберите типы отделений для загрузки';
$_['entry_sort_order']       = 'Сортировка';
$_['entry_calculation_method'] = 'Метод расчёта';
$_['entry_calculation_method_help'] = 'Тариф: локальная таблица тарифов. API: расчёт через API NovaPost.';
$_['text_tariff']            = 'Тариф';
$_['text_api']               = 'API';

$_['button_save']            = 'Сохранить';
$_['button_cancel']          = 'Отмена';
$_['button_sync']            = 'Синхронизировать отделения';
$_['button_sync_all']        = 'Синхронизировать все страны';
$_['button_filter']          = 'Фильтр';

$_['column_country']         = 'Страна';
$_['column_divisions']       = 'Отделения';
$_['column_updated']         = 'Последнее обновление';
$_['column_status']          = 'Статус';
$_['column_loaded']          = 'Загружено';
$_['column_errors']          = 'Ошибки';
$_['column_started']         = 'Начало';
$_['column_finished']        = 'Завершение';
$_['column_name']            = 'Название';
$_['column_city']            = 'Город';
$_['column_address']         = 'Адрес';
$_['column_countries']       = 'Страны';

$_['help_total_divisions']   = 'Всего отделений в базе';
$_['help_countries_loaded']  = 'Страны с загруженными отделениями';
$_['help_by_category']       = 'По категориям';
$_['help_last_sync']         = 'Последняя успешная синхронизация';

$_['error_permission']       = 'Внимание: У вас нет прав для изменения NovaPost доставки!';
$_['error_api_key']          = 'API ключ обязателен!';
$_['error_sync']             = 'Синхронизация не удалась: %s';
$_['error_ajax']             = 'Ошибка AJAX запроса';

$_['category_cargo_branch']  = 'Грузовое отделение';
$_['category_post_branch']   = 'Почтовое отделение';
$_['category_postomat']      = 'Почтомат';
$_['category_pudo']          = 'PUDO';

$_['status_success']         = 'Успех';
$_['status_failed']          = 'Ошибка';
$_['status_partial']         = 'Частично';
$_['status_running']         = 'Выполняется';

// Tariff tab
$_['text_add_tariff']        = 'Добавить тариф';
$_['text_edit_tariff']       = 'Редактировать тариф';
$_['text_no_tariffs']        = 'Тарифы не настроены.';
$_['text_weight_range']      = 'Диапазон веса';
$_['text_kg']                = 'кг';
$_['text_free_shipping']     = 'Бесплатная доставка';
$_['text_free_shipping_off'] = 'Нет';

$_['column_delivery_type']   = 'Тип доставки';
$_['column_weight_from']     = 'Вес от';
$_['column_weight_to']       = 'Вес до';
$_['column_cost']            = 'Стоимость';
$_['column_free_shipping']   = 'Бесплатно от';

$_['entry_delivery_type']    = 'Тип доставки';
$_['entry_weight_from']      = 'Вес от (кг)';
$_['entry_weight_to']        = 'Вес до (кг)';
$_['entry_cost']             = 'Стоимость доставки';
$_['entry_free_shipping_from'] = 'Бесплатно от суммы';

$_['button_add_tariff']      = 'Добавить тариф';
$_['button_edit_tariff']     = 'Изменить';
$_['button_delete_tariff']   = 'Удалить';
$_['button_cancel_tariff']   = 'Отмена';

$_['delivery_branch']        = 'В отделение';
$_['delivery_locker']        = 'В почтомат';
$_['delivery_courier']       = 'Курьером';

$_['error_tariff_country']   = 'Страна обязательна!';
$_['error_tariff_delivery']  = 'Тип доставки обязателен!';
$_['error_tariff_weight_from'] = 'Вес от должен быть числом ≥ 0!';
$_['error_tariff_weight_to'] = 'Вес до должен быть больше веса от!';
$_['error_tariff_cost']      = 'Стоимость должна быть числом ≥ 0!';
$_['error_tariff_overlap']   = 'Тариф для этой страны, типа доставки и диапазона веса уже существует!';
$_['error_tariff_not_found'] = 'Тариф не найден!';
$_['confirm_delete_tariff']  = 'Вы уверены, что хотите удалить этот тариф?';

// Region mapping tab
$_['text_no_region_maps']    = 'Регионы ещё не обнаружены. Запустите синхронизацию для наполнения регионов NovaPost.';
$_['text_mapped']            = 'Сопоставлено';
$_['text_unmapped']          = 'Не сопоставлено';
$_['text_all_status']        = 'Все статусы';
$_['column_np_region']       = 'Регион NovaPost';
$_['column_oc_zone']         = 'Зона OC';
$_['entry_oc_zone']          = 'Зона OpenCart';
$_['button_map']             = 'Сопоставить';
$_['entry_region_map_filter_status'] = 'Статус';

// Scheduler
$_['entry_sync_schedule']    = 'Расписание автосинхронизации';
$_['help_sync_schedule']     = 'Расписание автоматической синхронизации отделений через CLI';
$_['text_cron_disabled']     = 'Отключено';
$_['text_every_15m']         = 'Каждые 15 мин';
$_['text_every_30m']         = 'Каждые 30 мин';
$_['text_hourly']            = 'Каждый час';
$_['text_every_6h']          = 'Каждые 6 часов';
$_['text_every_12h']         = 'Каждые 12 часов';
$_['text_daily']             = 'Ежедневно';
