<?php

class DockercartGoogleCloudTranslation {
    private $api_key;
    private $timeout;

    public function __construct($api_key, $timeout = 30) {
        $this->api_key = (string)$api_key;
        $this->timeout = (int)$timeout;
    }

    public function translateBatch(array $texts, $source_language, $target_language, array $protected_terms = array()) {
        if (empty($texts)) {
            return array();
        }

        if (empty($this->api_key)) {
            throw new Exception('Google Cloud Translation API key is empty.');
        }

        $payload_texts = array();
        $placeholder_maps = array();

        foreach ($texts as $text) {
            if (is_array($text)) {
                $text = implode(' ', array_map('strval', $text));
            } elseif (!is_scalar($text)) {
                $text = '';
            }

            $prepared = $this->applyProtection($text, $protected_terms);
            $payload_texts[] = $prepared['text'];
            $placeholder_maps[] = $prepared['map'];
        }

        $response = $this->requestTranslate($payload_texts, $source_language, $target_language);

        if (!isset($response['data']['translations']) || !is_array($response['data']['translations'])) {
            throw new Exception('Unexpected Google Translation response format.');
        }

        $translated = array();

        foreach ($response['data']['translations'] as $index => $row) {
            $value = isset($row['translatedText']) ? html_entity_decode($row['translatedText'], ENT_QUOTES, 'UTF-8') : '';
            $value = $this->restoreProtection($value, isset($placeholder_maps[$index]) ? $placeholder_maps[$index] : array());
            $translated[] = $value;
        }

        return $translated;
    }

    private function requestTranslate(array $texts, $source_language, $target_language) {
        $url = 'https://translation.googleapis.com/language/translate/v2?key=' . rawurlencode($this->api_key);

        $post_data = array(
            'source' => (string)$source_language,
            'target' => (string)$target_language,
            'format' => 'html',
            'model' => 'nmt',
            'q' => array_values($texts)
        );

        if (!function_exists('curl_init')) {
            throw new Exception('cURL extension is required for Google Cloud Translation requests.');
        }

        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

        $raw_response = curl_exec($ch);

        if ($raw_response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception('Google Translation request failed: ' . $error);
        }

        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = json_decode($raw_response, true);

        if ($http_code >= 400) {
            $message = 'HTTP ' . $http_code;

            if (isset($decoded['error']['message'])) {
                $message .= ': ' . $decoded['error']['message'];
            }

            throw new Exception('Google Translation API error: ' . $message);
        }

        if (!is_array($decoded)) {
            throw new Exception('Invalid JSON from Google Translation API.');
        }

        return $decoded;
    }

    private function applyProtection($text, array $protected_terms) {
        $map = array();
        $prepared = (string)$text;
        $all_terms = array_unique(array_filter(array_map('trim', $protected_terms)));

        foreach ($all_terms as $i => $term) {
            if ($term === '') {
                continue;
            }

            $placeholder = '__DCTR_PROTECTED_' . $i . '__';

            if (preg_match('/^[A-Za-z0-9\s\-\+&\.\/]+$/u', $term)) {
                $pattern = '/\b' . preg_quote($term, '/') . '\b/u';
                $prepared = preg_replace($pattern, $placeholder, $prepared);
            } else {
                $prepared = str_replace($term, $placeholder, $prepared);
            }

            $map[$placeholder] = $term;
        }

        return array('text' => $prepared, 'map' => $map);
    }

    private function restoreProtection($text, array $map) {
        if (empty($map)) {
            return $text;
        }

        return str_replace(array_keys($map), array_values($map), $text);
    }
}
