<?php
class ControllerExtensionModuleDockercartImportExportExcel extends Controller {

    public function index() {
        $this->cron();
    }

    public function cron() {
        $profile_id = isset($this->request->get['profile_id']) ? (int)$this->request->get['profile_id'] : 0;
        $cron_key = isset($this->request->get['cron_key']) ? (string)$this->request->get['cron_key'] : '';
        $format = isset($this->request->get['format']) ? (string)$this->request->get['format'] : 'json';
        $action = isset($this->request->get['action']) ? (string)$this->request->get['action'] : 'import';
        $file_format = isset($this->request->get['file_format']) ? (string)$this->request->get['file_format'] : 'xlsx';

        $json = array('success' => false);

        try {
            if ($profile_id <= 0) {
                throw new Exception('Invalid profile_id');
            }

            $this->load->model('extension/module/dockercart_import_export_excel');
            $profile = $this->model_extension_module_dockercart_import_export_excel->getProfile($profile_id);
            if (!$profile) {
                throw new Exception('Profile not found');
            }

            if (empty($profile['cron_key']) || !hash_equals((string)$profile['cron_key'], $cron_key)) {
                throw new Exception('Invalid cron key');
            }

            if ($action === 'download') {
                $filename = isset($this->request->get['file']) ? (string)$this->request->get['file'] : '';
                $this->sendFile($filename);
                return;
            }

            if ($action === 'export') {
                $summary = $this->model_extension_module_dockercart_import_export_excel->runExport($profile_id, $file_format);
                $json['success'] = true;
                $json['summary'] = $summary;
                $json['download_url'] = $this->getCatalogBaseUrl() . 'index.php?route=extension/module/dockercart_import_export_excel/cron'
                    . '&profile_id=' . (int)$profile_id
                    . '&cron_key=' . urlencode((string)$profile['cron_key'])
                    . '&action=download'
                    . '&file=' . urlencode((string)$summary['filename']);
            } else {
                $summary = $this->model_extension_module_dockercart_import_export_excel->runImport($profile_id);
                $json['success'] = true;
                $json['summary'] = $summary;
            }
        } catch (Exception $e) {
            $json['success'] = false;
            $json['error'] = $e->getMessage();
        }

        if ($format === 'text') {
            $this->response->addHeader('Content-Type: text/plain; charset=utf-8');
            if (!empty($json['success'])) {
                $lines = array('OK', 'action=' . $action, 'profile_id=' . $profile_id);
                if (!empty($json['summary']) && is_array($json['summary'])) {
                    foreach ($json['summary'] as $k => $v) {
                        $lines[] = $k . '=' . (is_scalar($v) ? (string)$v : json_encode($v));
                    }
                }

                $this->response->setOutput(implode("\n", $lines) . "\n");
            } else {
                $this->response->setOutput('ERROR: ' . $json['error'] . "\n");
            }

            return;
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    private function sendFile($filename) {
        $this->load->model('extension/module/dockercart_import_export_excel');

        $path = $this->model_extension_module_dockercart_import_export_excel->getExportFileByName($filename);
        if (!$path || !is_file($path)) {
            $this->response->addHeader('HTTP/1.1 404 Not Found');
            $this->response->setOutput('File not found');
            return;
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($ext === 'csv') {
            $content_type = 'text/csv; charset=utf-8';
        } else {
            $content_type = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        }

        $this->response->addHeader('Content-Type: ' . $content_type);
        $this->response->addHeader('Content-Disposition: attachment; filename="' . basename($path) . '"');
        $this->response->addHeader('Content-Length: ' . filesize($path));
        $this->response->setOutput(file_get_contents($path));
    }

    private function getCatalogBaseUrl() {
        $is_https = !empty($this->request->server['HTTPS']) && $this->request->server['HTTPS'] !== 'off';

        if ($is_https && defined('HTTPS_SERVER') && HTTPS_SERVER) {
            return rtrim((string)HTTPS_SERVER, '/') . '/';
        }

        if (defined('HTTP_SERVER') && HTTP_SERVER) {
            return rtrim((string)HTTP_SERVER, '/') . '/';
        }

        if (defined('HTTPS_SERVER') && HTTPS_SERVER) {
            return rtrim((string)HTTPS_SERVER, '/') . '/';
        }

        $config_ssl = (string)$this->config->get('config_ssl');
        if ($config_ssl !== '') {
            return rtrim($config_ssl, '/') . '/';
        }

        $config_url = (string)$this->config->get('config_url');
        if ($config_url !== '') {
            return rtrim($config_url, '/') . '/';
        }

        return '/';
    }
}
