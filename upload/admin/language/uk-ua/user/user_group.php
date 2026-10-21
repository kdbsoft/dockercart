<?php
// Heading
$_['heading_title'] = 'Групи користувачів';

// Text
$_['text_success'] = 'Успіх: Ви змінили групи користувачів!';
$_['text_list'] = 'Група користувачів';
$_['text_add'] = 'Додати групу користувачів';
$_['text_edit'] = 'Редагувати групу користувачів';
// Subtitle

$_['text_list_subtitle'] = 'Керуйте групами дозволів користувачів';

$_['text_add_user_group_subtitle'] = 'Створити нову групу дозволів';

$_['text_edit_user_group_subtitle'] = 'Редагувати дозволи групи користувачів';

$_['text_form_subtitle'] = 'Задайте назву та надайте групі потрібні розділи';

$_['text_permissions'] = 'Права доступу';
$_['text_permissions_subtitle'] = 'Доступ відкриває сторінку, Зміна дозволяє зберігати на ній зміни';
$_['text_full_access'] = 'Група Administrator завжди має повний доступ до всіх розділів, включно зі сторінками, що з’являться в майбутніх оновленнях. Обмежити її права неможливо.';
$_['text_custom_group'] = 'Власна група';
$_['text_select_role_template'] = '— Оберіть шаблон ролі —';
$_['text_confirm_create_roles'] = 'Створити стандартні ролі, яких ще немає?';
$_['text_apply_template_confirm'] = 'Застосувати обраний шаблон ролі? Поточний набір прав буде замінено.';

$_['text_allow_all_access'] = 'Дозволити весь доступ';
$_['text_deny_all_access'] = 'Заборонити весь доступ';
$_['text_allow_all_modify'] = 'Дозволити всі зміни';
$_['text_deny_all_modify'] = 'Заборонити всі зміни';
$_['text_allow_access'] = 'доступ';
$_['text_allow_modify'] = 'зміна';
$_['text_deny_section'] = 'зняти';
$_['text_select_all'] = 'Вибрати все';
$_['text_unselect_all'] = 'Зняти все';

$_['text_roles_created'] = 'Успіх: створено ролей — %s (%s)';
$_['text_roles_existing'] = 'Усі стандартні ролі вже існують (%s)';

// Sections
$_['text_section_sale']         = 'Продажі';
$_['text_section_catalog']      = 'Каталог';
$_['text_section_customer']     = 'Клієнти';
$_['text_section_warehouse']    = 'Склад';
$_['text_section_design']       = 'Дизайн';
$_['text_section_marketing']    = 'Маркетинг';
$_['text_section_report']       = 'Звіти';
$_['text_section_extension']    = 'Розширення';
$_['text_section_setting']      = 'Налаштування';
$_['text_section_localisation'] = 'Локалізація';
$_['text_section_tool']         = 'Інструменти';
$_['text_section_user']         = 'Користувачі';
$_['text_section_marketplace']  = 'Магазин розширень';
$_['text_section_common']       = 'Загальні';
$_['text_section_other']        = 'Інше';

// Roles
$_['text_role_sales_manager']    = 'Менеджер з продажу';
$_['text_role_support_operator'] = 'Оператор підтримки';
$_['text_role_content_manager']  = 'Контент-менеджер';
$_['text_role_marketer']         = 'SEO / маркетолог';
$_['text_role_courier']          = 'Кур’єр';
$_['text_role_storekeeper']      = 'Комірник';
$_['text_role_accountant']       = 'Бухгалтер (лише читання)';

// Capabilities (action level permissions)
$_['text_capability_order_status']   = 'Зміна статусу замовлення';
$_['text_capability_order_shipment'] = 'Створення відправлення / трекінг-номер';

// Column
$_['column_name'] = 'Назва групи користувачів';
$_['column_action'] = 'Дія';
$_['column_role'] = 'Роль';
$_['column_section'] = 'Розділ';
$_['column_permission'] = 'Сторінка';
$_['column_access'] = 'Доступ';
$_['column_modify'] = 'Зміна';

// Entry
$_['entry_name'] = 'Назва групи користувачів';
$_['entry_access'] = 'Дозвіл на доступ';
$_['entry_hide'] = 'Приховати доповнення';
$_['entry_modify'] = 'Змінити дозвіл';
$_['entry_role_template'] = 'Шаблон ролі';
$_['entry_capabilities'] = 'Дії';
$_['entry_search_permission'] = 'Пошук сторінки або маршруту…';

// Help
$_['help_role_template'] = 'Заповнює список прав готовим набором стандартної ролі. Далі набір можна скоригувати вручну.';
$_['help_permissions'] = 'Доступ дозволяє групі відкривати сторінку, Зміна — зберігати на ній зміни. Надання Зміни автоматично включає Доступ: сторінку, яку не можна відкрити, не можна й редагувати.';
$_['help_capabilities'] = 'Окремі дії, які зазвичай закриті правом Зміна на всю сторінку. Надавайте їх ролям, яким не можна давати повний контроль над сторінкою (наприклад, кур’єру — лише зміну статусу замовлення).';
$_['help_hide'] = 'Обрані доповнення не будуть показуватись на сторінках модулів, платежів, доставок';

// Button
$_['button_save'] = 'Зберегти';
$_['button_cancel'] = 'Скасувати';
$_['button_add'] = 'Додати';
$_['button_delete'] = 'Видалити';
$_['button_apply_template'] = 'Застосувати';
$_['button_create_roles'] = 'Створити стандартні ролі';

// Error
$_['error_permission'] = 'Увага: У вас немає дозволу на зміну груп користувачів!';
$_['error_name'] = 'Назва групи користувачів має містити від 3 до 64 символів!';
$_['error_user'] = 'Увага: Цю групу користувачів неможливо видалити, оскільки вона наразі призначена %s користувачам!';
$_['error_delete_administrator'] = 'Увага: групу Administrator видалити неможливо!';
