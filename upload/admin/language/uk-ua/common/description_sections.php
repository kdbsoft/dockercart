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
$_['section_text_image']       = 'Текст і зображення';
$_['section_features']         = 'Переваги з іконками';
$_['section_gallery']         = 'Галерея зображень';
$_['section_stats']            = 'Цифри';
$_['section_statement']        = 'Акцентне твердження';

// Fields
$_['field_heading']            = 'Заголовок';
$_['field_body']               = 'Текст';
$_['field_align']              = 'Вирівнювання';
$_['field_image']              = 'Зображення';
$_['field_position']           = 'Позиція зображення';
$_['field_columns']            = 'Колонки';
$_['field_rows']               = 'Рядки';
$_['field_icon']               = 'Іконка';
$_['field_title']              = 'Заголовок';
$_['field_text']               = 'Текст';
$_['field_caption']            = 'Підпис';
$_['field_value']              = 'Значення';
$_['field_label']              = 'Підпис';
$_['field_footnote']           = 'Виноска';

// Field options
$_['option_align_left']        = 'По лівому краю';
$_['option_align_center']      = 'По центру';
$_['option_position_left']     = 'Зображення ліворуч';
$_['option_position_right']    = 'Зображення праворуч';
$_['option_columns_2']         = '2 колонки';
$_['option_columns_3']         = '3 колонки';
$_['option_columns_4']         = '4 колонки';

// Icons (Lucide names, features rows)
$_['icon_sparkles']            = 'Іскри';
$_['icon_shield_check']        = 'Захист';
$_['icon_cpu']                 = 'Процесор';
$_['icon_zap']                 = 'Блискавка';
$_['icon_battery_charging']    = 'Батарея';
$_['icon_feather']             = 'Перо';
$_['icon_ruler']               = 'Розміри';
$_['icon_truck']               = 'Доставка';
$_['icon_package']             = 'Упаковка';
$_['icon_package_check']       = 'У комплекті';
$_['icon_credit_card']         = 'Оплата';
$_['icon_wallet']              = 'Гаманець';
$_['icon_headset']             = 'Підтримка';
$_['icon_gift']                = 'Подарунок';
$_['icon_heart']               = 'Серце';
$_['icon_leaf']                = 'Натуральність';
$_['icon_sun']                 = 'Сонце';
$_['icon_droplets']            = 'Краплі';
$_['icon_thermometer']         = 'Температура';
$_['icon_wrench']              = 'Сервіс';
$_['icon_award']               = 'Нагорода';
$_['icon_globe']               = 'По всьому світу';
$_['icon_smartphone']          = 'Смартфон';
$_['icon_headphones']          = 'Навушники';

// Preset templates
$_['preset_specs_highlights']  = 'Переваги';
$_['preset_product_launch']    = 'Запуск продукту';
$_['preset_visual_story']      = 'Візуальна історія';
$_['preset_lookbook']          = 'Лукбук';
$_['preset_gift_guide']         = 'Подарунковий гід';
$_['preset_campaign']          = 'Кампанія';

// Кнопки редактора
$_['button_templates']                 = 'Шаблони';

// Кнопка показу класичного опису
$_['button_show_classic_description'] = 'Показати класичний опис';
$_['button_hide_classic_description'] = 'Сховати класичний опис';
