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
$_['section_text']             = 'Text';
$_['section_text_image']       = 'Text & Image';
$_['section_features']         = 'Icon Highlights';
$_['section_gallery']         = 'Image Gallery';
$_['section_stats']            = 'Numbers';
$_['section_statement']        = 'Statement';

// Fields
$_['field_heading']            = 'Heading';
$_['field_body']               = 'Body';
$_['field_align']              = 'Alignment';
$_['field_image']              = 'Image';
$_['field_position']           = 'Image position';
$_['field_columns']            = 'Columns';
$_['field_rows']               = 'Rows';
$_['field_icon']               = 'Icon';
$_['field_title']              = 'Title';
$_['field_text']               = 'Text';
$_['field_caption']            = 'Caption';
$_['field_value']              = 'Value';
$_['field_label']              = 'Label';
$_['field_footnote']           = 'Footnote';

// Field options
$_['option_align_left']        = 'Left';
$_['option_align_center']      = 'Center';
$_['option_position_left']     = 'Image on the left';
$_['option_position_right']    = 'Image on the right';
$_['option_columns_2']         = '2 columns';
$_['option_columns_3']         = '3 columns';
$_['option_columns_4']         = '4 columns';

// Icons (Lucide names, features rows)
$_['icon_sparkles']            = 'Sparkles';
$_['icon_shield_check']        = 'Shield';
$_['icon_cpu']                 = 'Processor';
$_['icon_zap']                 = 'Lightning';
$_['icon_battery_charging']    = 'Battery';
$_['icon_feather']             = 'Feather';
$_['icon_ruler']               = 'Measurements';
$_['icon_truck']               = 'Delivery';
$_['icon_package']             = 'Package';
$_['icon_package_check']       = 'In the box';
$_['icon_credit_card']         = 'Payment';
$_['icon_wallet']              = 'Wallet';
$_['icon_headset']             = 'Support';
$_['icon_gift']                = 'Gift';
$_['icon_heart']               = 'Heart';
$_['icon_leaf']               = 'Natural';
$_['icon_sun']                 = 'Sun';
$_['icon_droplets']            = 'Droplets';
$_['icon_thermometer']         = 'Temperature';
$_['icon_wrench']              = 'Service';
$_['icon_award']               = 'Award';
$_['icon_globe']               = 'Worldwide';
$_['icon_smartphone']          = 'Smartphone';
$_['icon_headphones']          = 'Headphones';

// Preset templates
$_['preset_specs_highlights']  = 'Highlights';
$_['preset_product_launch']    = 'Product Launch';
$_['preset_visual_story']      = 'Visual Story';
$_['preset_lookbook']          = 'Lookbook';
$_['preset_gift_guide']         = 'Gift Guide';
$_['preset_campaign']          = 'Campaign';

// Editor toolbar buttons
$_['button_templates']                 = 'Templates';

// Classic copy toggle in advanced mode
$_['button_show_classic_description'] = 'Show classic description';
$_['button_hide_classic_description'] = 'Hide classic description';
