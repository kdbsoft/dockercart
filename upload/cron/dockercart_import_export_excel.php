<?php
/**
 * DockerCart Import/Export Excel CLI runner
 *
 * Usage:
 *   php /var/www/html/cron/dockercart_import_export_excel.php --action=import --profile_id=1 --cron_key=YOUR_KEY
 *   php /var/www/html/cron/dockercart_import_export_excel.php --action=export --profile_id=1 --cron_key=YOUR_KEY --file_format=xlsx
 *
 * Optional:
 *   --host=http://127.0.0.1
 */

if (php_sapi_name() !== 'cli') {
    fwrite(STDERR, "This script must be run from CLI.\n");
    exit(1);
}

$args = array(
    'action' => 'import',
    'profile_id' => 0,
    'cron_key' => '',
    'file_format' => 'xlsx',
    'host' => 'http://127.0.0.1'
);

foreach ($argv as $arg) {
    if (strpos($arg, '--action=') === 0) {
        $args['action'] = (string)substr($arg, 9);
    } elseif (strpos($arg, '--profile_id=') === 0) {
        $args['profile_id'] = (int)substr($arg, 13);
    } elseif (strpos($arg, '--cron_key=') === 0) {
        $args['cron_key'] = (string)substr($arg, 11);
    } elseif (strpos($arg, '--file_format=') === 0) {
        $args['file_format'] = (string)substr($arg, 14);
    } elseif (strpos($arg, '--host=') === 0) {
        $args['host'] = rtrim((string)substr($arg, 7), '/');
    }
}

if (!in_array($args['action'], array('import', 'export'))) {
    fwrite(STDERR, "Invalid --action. Allowed: import, export\n");
    exit(2);
}

if (!in_array($args['file_format'], array('xlsx', 'csv'))) {
    $args['file_format'] = 'xlsx';
}

if ($args['profile_id'] <= 0 || $args['cron_key'] === '') {
    fwrite(STDERR, "Required arguments: --profile_id and --cron_key\n");
    exit(2);
}

$url = $args['host'] . '/index.php?route=extension/module/dockercart_import_export_excel/cron'
    . '&profile_id=' . (int)$args['profile_id']
    . '&cron_key=' . urlencode($args['cron_key'])
    . '&action=' . urlencode($args['action'])
    . '&file_format=' . urlencode($args['file_format'])
    . '&format=json';

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 1800);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$response = curl_exec($ch);
$errno = curl_errno($ch);
$error = curl_error($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($errno) {
    fwrite(STDERR, "cURL error: {$error}\n");
    exit(3);
}

if ($code !== 200) {
    fwrite(STDERR, "HTTP error: {$code}\n{$response}\n");
    exit(4);
}

$json = json_decode((string)$response, true);
if (!is_array($json)) {
    fwrite(STDERR, "Invalid JSON response\n{$response}\n");
    exit(5);
}

if (empty($json['success'])) {
    $error = isset($json['error']) ? $json['error'] : 'Unknown error';
    fwrite(STDERR, "Action failed: {$error}\n");
    exit(6);
}

echo "Action success\n";
echo 'action=' . $args['action'] . "\n";

if (!empty($json['summary']) && is_array($json['summary'])) {
    foreach ($json['summary'] as $key => $value) {
        if (is_scalar($value)) {
            echo $key . '=' . $value . "\n";
        }
    }
}

if (!empty($json['download_url'])) {
    echo 'download_url=' . $json['download_url'] . "\n";
}

exit(0);
