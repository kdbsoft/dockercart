<?php
// Heading
$_['heading_title'] = 'Группы пользователей';

// Text
$_['text_success'] = 'Успех: Вы изменили группы пользователей!';
$_['text_list'] = 'Группа пользователей';
$_['text_add'] = 'Добавить группу пользователей';
$_['text_edit'] = 'Редактировать группу пользователей';
// Subtitle

$_['text_list_subtitle'] = 'Управляйте группами разрешений пользователей';

$_['text_add_user_group_subtitle'] = 'Создать новую группу разрешений';

$_['text_edit_user_group_subtitle'] = 'Редактировать разрешения группы пользователей';

$_['text_form_subtitle'] = 'Задайте название и выдайте группе нужные разделы';

$_['text_permissions'] = 'Права доступа';
$_['text_permissions_subtitle'] = 'Доступ открывает страницу, Изменение разрешает сохранять на ней изменения';
$_['text_full_access'] = 'Группа Administrator всегда имеет полный доступ ко всем разделам, включая страницы, которые появятся в будущих обновлениях. Ограничить её права нельзя.';
$_['text_custom_group'] = 'Своя группа';
$_['text_select_role_template'] = '— Выберите шаблон роли —';
$_['text_confirm_create_roles'] = 'Создать стандартные роли, которых ещё нет?';
$_['text_apply_template_confirm'] = 'Применить выбранный шаблон роли? Текущий набор прав будет заменён.';

$_['text_allow_all_access'] = 'Разрешить весь доступ';
$_['text_deny_all_access'] = 'Запретить весь доступ';
$_['text_allow_all_modify'] = 'Разрешить все изменения';
$_['text_deny_all_modify'] = 'Запретить все изменения';
$_['text_allow_access'] = 'доступ';
$_['text_allow_modify'] = 'изменение';
$_['text_deny_section'] = 'снять';
$_['text_select_all'] = 'Выбрать всё';
$_['text_unselect_all'] = 'Снять всё';

$_['text_roles_created'] = 'Успех: создано ролей — %s (%s)';
$_['text_roles_existing'] = 'Все стандартные роли уже существуют (%s)';

// Sections
$_['text_section_sale']         = 'Продажи';
$_['text_section_catalog']      = 'Каталог';
$_['text_section_customer']     = 'Клиенты';
$_['text_section_warehouse']    = 'Склад';
$_['text_section_design']       = 'Дизайн';
$_['text_section_marketing']    = 'Маркетинг';
$_['text_section_report']       = 'Отчёты';
$_['text_section_extension']    = 'Расширения';
$_['text_section_setting']      = 'Настройки';
$_['text_section_localisation'] = 'Локализация';
$_['text_section_tool']         = 'Инструменты';
$_['text_section_user']         = 'Пользователи';
$_['text_section_marketplace']  = 'Магазин расширений';
$_['text_section_common']       = 'Общие';
$_['text_section_other']        = 'Прочее';

// Roles
$_['text_role_sales_manager']    = 'Менеджер по продажам';
$_['text_role_support_operator'] = 'Оператор поддержки';
$_['text_role_content_manager']  = 'Контент-менеджер';
$_['text_role_marketer']         = 'SEO / маркетолог';
$_['text_role_courier']          = 'Курьер';
$_['text_role_storekeeper']      = 'Кладовщик';
$_['text_role_accountant']       = 'Бухгалтер (только чтение)';

// Capabilities (action level permissions)
$_['text_capability_order_status']   = 'Смена статуса заказа';
$_['text_capability_order_shipment'] = 'Создание отправки / трекинг-номер';

// Column
$_['column_name'] = 'Название группы пользователей';
$_['column_action'] = 'Действие';
$_['column_role'] = 'Роль';
$_['column_section'] = 'Раздел';
$_['column_permission'] = 'Страница';
$_['column_access'] = 'Доступ';
$_['column_modify'] = 'Изменение';

// Entry
$_['entry_name'] = 'Название группы пользователей';
$_['entry_access'] = 'Разрешение на доступ';
$_['entry_modify'] = 'Изменить права доступа';
$_['entry_role_template'] = 'Шаблон роли';
$_['entry_capabilities'] = 'Действия';
$_['entry_search_permission'] = 'Поиск страницы или маршрута…';

// Help
$_['help_role_template'] = 'Заполняет список прав готовым набором стандартной роли. Дальше набор можно скорректировать вручную.';
$_['help_permissions'] = 'Доступ позволяет группе открывать страницу, Изменение — сохранять на ней изменения. Выдача Изменения автоматически включает Доступ: страницу, которую нельзя открыть, нельзя и редактировать.';
$_['help_capabilities'] = 'Отдельные действия, которые обычно закрыты правом Изменение на всю страницу. Выдавайте их ролям, которым нельзя давать полный контроль над страницей (например, курьеру — только смену статуса заказа).';

// Button
$_['button_save'] = 'Сохранить';
$_['button_cancel'] = 'Отмена';
$_['button_add'] = 'Добавить';
$_['button_delete'] = 'Удалить';
$_['button_apply_template'] = 'Применить';
$_['button_create_roles'] = 'Создать стандартные роли';

// Error
$_['error_permission'] = 'Внимание: у вас нет разрешения на изменение групп пользователей!';
$_['error_name'] = 'Название группы пользователей должно содержать от 3 до 64 символов!';
$_['error_user'] = 'Внимание: эту группу пользователей нельзя удалить, так как в данный момент она назначена пользователям %s!';
$_['error_delete_administrator'] = 'Внимание: группу Administrator удалить нельзя!';
