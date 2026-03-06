<?php
// *	@copyright		Pavel Kravchenko; https://opencartforum.com/profile/711752-paulkravchenko/

// Heading
$_['heading_title']           = 'Webmoney WMV';

// Button
$_['button_clear']            = 'Очистити журнал';

// Tab 
$_['tab_log']                 = 'Журнал';

// Text 
$_['text_extension']          = 'Платежі';
$_['text_success']            = 'Налаштування змінені';
$_['text_webmoney_wmb']       = '<a style="cursor: pointer;" onclick="window.open(’https://webmoney.ru’);"><img src="view/image/payment/webmoney_wmb.gif" alt="Webmoney" title="Webmoney" style="border: 1px solid #EEEEEE;" height="25px" /></a>';
$_['text_clear_log_success']  = 'Журнал запитів від сервера платіжної системи';
$_['text_confirm']            = 'Видалення журналу неможливо скасувати. Ви впевнені, щто хочете це зробити?';
$_['text_log_off']            = 'Виключений';
$_['text_log_short']          = 'Частковий (тільки результати операцій)';
$_['text_log_full']           = 'Повний (всі запити)';

// Entry
$_['entry_merch_r']               = 'Номер V-гаманця';
$_['entry_secret_key']            = 'Secret Key';
$_['entry_secret_key_x20']        = 'Secret Key X20';
$_['entry_result_url']            = 'Result URL';
$_['entry_success_url']           = 'Success URL';
$_['entry_fail_url']              = 'Fail URL';
$_['entry_order_confirm_status']  = 'Статус замовлення після оформлення замовлення';
$_['entry_order_status']          = 'Статус замовлення після оплати';
$_['entry_order_fail_status']     = 'Статус замовлення після невдалої оплати';
$_['entry_hide_mode']             = 'Показувати модуль тільки адміністраторам';
$_['entry_minimal_order']         = 'Мінімальна вартість замовлення';
$_['entry_maximal_order']         = 'Максимальна вартість замовлення';
$_['entry_geo_zone']              = 'Гео Зона';
$_['entry_status']                = 'Стан';
$_['entry_sort_order']            = 'Сортування';

$_['entry_log']                   = 'Журнал';
$_['entry_log_file']              = 'Файл журналу';

// Help
$_['help_merch_r']              = 'наприклад: V000000000000';
$_['help_order_confirm_status'] = 'При натисканні на кнопку "Підтвердити" на останньому етапі оформлення замовлення, йому буде встановлений обраний статус';
$_['help_order_status']         = 'Після успішної оплати замовлення, йому буде встановлений обраний статус';
$_['help_order_fail_status']    = 'Якщо платіжна система поверне покупця після невдалого платежу, замовленню буде встановлений обраний статус.';
$_['help_hide_mode']            = 'Якщо опцію включено, модуль буде доступний при оформленні замовлення тільки авторизованому адміністратору. Це зручно коли є потреба не показувати модуль покупцям на час тестування і налаштування.';
$_['help_minimal_order']        = 'Якщо сума замовлення менше вказаної суми, і сума не пуста і не дорівнює нулю, цей метод оплати не буде доступний, при оформленні замовлення.<br />Наприклад: 190.90';
$_['help_maximal_order']        = 'Якщо сума замовлення більше вказаної суми, і сума не пуста і не дорівнює нулю, цей метод оплати не буде доступний, при оформленні замовлення.<br />Наприклад: 5000.01';

$_['help_log']                  = 'Журнал запитів від сервера платіжної системи зберігається у файлі: /system/storage/logs/%s';
$_['help_log_file']             = 'Останні %d строк з файлу журнала';

// Error
$_['error_permission']      = 'У вас недостатньо прав для внесення змін';
$_['error_merch_r']         = 'Невірно вказаний номер гаманця';
$_['error_secret_key']      = 'Вкажіть Secret Key';
$_['error_secret_key_x20']  = 'Вкажіть Secret Key X20';
?>