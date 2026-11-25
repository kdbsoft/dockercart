<?php
/**
 * Shared labels of the Advanced Description section editor.
 *
 * Loaded by the product, category and manufacturer forms (their controllers
 * call $this->language->load('common/description_sections')), so the section
 * registry, its fields, icons and preset templates stay spelled the same in
 * every entity form.
 *
 * Keys must mirror Dockercart\Description\Sections (schema() label keys).
 */

// Section types
$_['section_text']             = 'Текст';
$_['section_text_image']       = 'Текст и изображение';
$_['section_features']         = 'Преимущества с иконками';
$_['section_gallery']         = 'Галерея изображений';
$_['section_stats']            = 'Цифры';
$_['section_statement']        = 'Акцентное заявление';

// Fields
$_['field_heading']            = 'Заголовок';
$_['field_body']               = 'Текст';
$_['field_align']              = 'Выравнивание';
$_['field_image']              = 'Изображение';
$_['field_position']           = 'Позиция изображения';
$_['field_columns']            = 'Колонки';
$_['field_rows']               = 'Строки';
$_['field_icon']               = 'Иконка';
$_['field_title']             = 'Заголовок';
$_['field_text']               = 'Текст';
$_['field_caption']            = 'Подпись';
$_['field_value']              = 'Значение';
$_['field_label']              = 'Подпись';
$_['field_footnote']           = 'Сноска';

// Field options
$_['option_align_left']        = 'По левому краю';
$_['option_align_center']      = 'По центру';
$_['option_position_left']     = 'Изображение слева';
$_['option_position_right']    = 'Изображение справа';
$_['option_columns_2']         = '2 колонки';
$_['option_columns_3']         = '3 колонки';
$_['option_columns_4']         = '4 колонки';

// Icons (Lucide names, features rows)
$_['icon_sparkles']            = 'Искры';
$_['icon_shield_check']        = 'Защита';
$_['icon_cpu']                 = 'Процессор';
$_['icon_zap']                 = 'Молния';
$_['icon_battery_charging']    = 'Батарея';
$_['icon_feather']             = 'Перо';
$_['icon_ruler']               = 'Размеры';
$_['icon_truck']               = 'Доставка';
$_['icon_package']             = 'Упаковка';
$_['icon_package_check']       = 'В комплекте';
$_['icon_credit_card']         = 'Оплата';
$_['icon_wallet']              = 'Кошелёк';
$_['icon_headset']             = 'Поддержка';
$_['icon_gift']                = 'Подарок';
$_['icon_heart']               = 'Сердце';
$_['icon_leaf']               = 'Натуральность';
$_['icon_sun']                 = 'Солнце';
$_['icon_droplets']            = 'Капли';
$_['icon_thermometer']         = 'Температура';
$_['icon_wrench']              = 'Сервис';
$_['icon_award']               = 'Награда';
$_['icon_globe']               = 'По всему миру';
$_['icon_smartphone']          = 'Смартфон';
$_['icon_headphones']          = 'Наушники';

// Preset templates
$_['preset_specs_highlights']  = 'Преимущества';
$_['preset_product_launch']    = 'Запуск продукта';
$_['preset_visual_story']      = 'Визуальная история';
$_['preset_lookbook']          = 'Лукбук';
$_['preset_gift_guide']         = 'Подарочный гид';
$_['preset_campaign']          = 'Кампания';

// Кнопки редактора
$_['button_templates']                 = 'Шаблоны';

// Кнопка показа классического описания
$_['button_show_classic_description'] = 'Показать классическое описание';
$_['button_hide_classic_description'] = 'Скрыть классическое описание';
