<?php
/**
 * DockerCart Import YML CLI runner
 *
 * Usage:
 *   php /var/www/html/cron/dockercart_import_yml.php --profile_id=1 --cron_key=YOUR_KEY
 *
 * Optional:
 *   --host=http://127.0.0.1
 */

if (php_sapi_name() !== 'cli') {
    fwrite(STDERR, "This script must be run from CLI.\n");
    exit(1);
}

$args = array(
    'profile_id' => 0,
    'cron_key' => '',
    'host' => 'http://127.0.0.1',
    'chunk_size' => 40
);

foreach ($argv as $arg) {
    if (strpos($arg, '--profile_id=') === 0) {
        $args['profile_id'] = (int)substr($arg, 13);
    } elseif (strpos($arg, '--cron_key=') === 0) {
        $args['cron_key'] = (string)substr($arg, 11);
    } elseif (strpos($arg, '--host=') === 0) {
        $args['host'] = rtrim((string)substr($arg, 7), '/');
    } elseif (strpos($arg, '--chunk_size=') === 0) {
        $args['chunk_size'] = (int)substr($arg, 13);
    }
}

if ($args['profile_id'] <= 0 || $args['cron_key'] === '') {
    fwrite(STDERR, "Required arguments: --profile_id and --cron_key\n");
    exit(2);
}

$offset = 0;
$chunk_size = (int)$args['chunk_size'];
if ($chunk_size <= 0) {
    $chunk_size = 0;
}

$summary = array();

while (true) {
    $url = $args['host'] . '/index.php?route=extension/module/dockercart_import_yml/cron'
        . '&profile_id=' . (int)$args['profile_id']
        . '&cron_key=' . urlencode($args['cron_key'])
        . '&format=json';

    if ($chunk_size > 0) {
        $url .= '&offset=' . (int)$offset . '&limit=' . (int)$chunk_size;
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 300);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
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
        $err = isset($json['error']) ? $json['error'] : 'Unknown error';
        fwrite(STDERR, "Import failed: {$err}\n");
        exit(6);
    }

    $summary = isset($json['summary']) && is_array($json['summary']) ? $json['summary'] : array();

    if ($chunk_size <= 0 || empty($summary['in_progress'])) {
        break;
    }

    $next_offset = isset($summary['next_offset']) ? (int)$summary['next_offset'] : ($offset + $chunk_size);
    if ($next_offset <= $offset) {
        break;
    }

    $offset = $next_offset;
}

echo "Import success\n";
echo 'profile_id=' . (isset($summary['profile_id']) ? (int)$summary['profile_id'] : 0) . "\n";
echo 'mode=' . (isset($summary['mode']) ? $summary['mode'] : '') . "\n";
echo 'total_offers=' . (isset($summary['total_offers']) ? (int)$summary['total_offers'] : 0) . "\n";
echo 'added=' . (isset($summary['added']) ? (int)$summary['added'] : 0) . "\n";
echo 'updated=' . (isset($summary['updated']) ? (int)$summary['updated'] : 0) . "\n";
echo 'skipped=' . (isset($summary['skipped']) ? (int)$summary['skipped'] : 0) . "\n";
echo 'errors=' . (isset($summary['errors']) ? (int)$summary['errors'] : 0) . "\n";
echo 'in_progress=' . (!empty($summary['in_progress']) ? '1' : '0') . "\n";

exit(0);
