<?php
class ControllerExtensionModuleDockercartImportYml extends Controller {

    public function index() {
        $this->cron();
    }

    public function cron() {
        $profile_id = isset($this->request->get['profile_id']) ? (int)$this->request->get['profile_id'] : 0;
        $cron_key = isset($this->request->get['cron_key']) ? (string)$this->request->get['cron_key'] : '';
        $format = isset($this->request->get['format']) ? (string)$this->request->get['format'] : 'json';
        $offset = isset($this->request->get['offset']) ? (int)$this->request->get['offset'] : 0;
        $limit = isset($this->request->get['limit']) ? (int)$this->request->get['limit'] : 0;

        $json = array('success' => false);

        try {
            if ($profile_id <= 0) {
                throw new Exception('Invalid profile_id');
            }

            if (!$this->checkLicense()) {
                throw new Exception('License is invalid');
            }

            $this->load->model('extension/module/dockercart_import_yml');
            $profile = $this->model_extension_module_dockercart_import_yml->getProfile($profile_id);

            if (!$profile) {
                throw new Exception('Profile not found');
            }

            if (empty($profile['cron_key']) || !hash_equals((string)$profile['cron_key'], $cron_key)) {
                throw new Exception('Invalid cron key');
            }

            $summary = $this->model_extension_module_dockercart_import_yml->runImport($profile_id, $offset, $limit);

            $json['success'] = true;
            $json['summary'] = $summary;
        } catch (Exception $e) {
            $json['success'] = false;
            $json['error'] = $e->getMessage();
        }

        if ($format === 'text') {
            $this->response->addHeader('Content-Type: text/plain; charset=utf-8');
            if (!empty($json['success'])) {
                $summary = $json['summary'];
                $lines = array(
                    'OK',
                    'profile_id=' . (int)$summary['profile_id'],
                    'mode=' . $summary['mode'],
                    'total_offers=' . (int)$summary['total_offers'],
                    'added=' . (int)$summary['added'],
                    'updated=' . (int)$summary['updated'],
                    'skipped=' . (int)$summary['skipped'],
                    'errors=' . (int)$summary['errors'],
                    'in_progress=' . (!empty($summary['in_progress']) ? '1' : '0'),
                    'next_offset=' . (isset($summary['next_offset']) ? (int)$summary['next_offset'] : 0)
                );
                $this->response->setOutput(implode("\n", $lines) . "\n");
            } else {
                $this->response->setOutput('ERROR: ' . $json['error'] . "\n");
            }
            return;
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    private function checkLicense() {
        $license_key = $this->config->get('module_dockercart_import_yml_license_key');

        $domain = $_SERVER['HTTP_HOST'] ?? '';
        if (strpos($domain, 'localhost') !== false || strpos($domain, '127.0.0.1') !== false || strpos($domain, '.docker.localhost') !== false) {
            return true;
        }

        if (empty($license_key)) {
            return false;
        }

        if (!file_exists(DIR_SYSTEM . 'library/dockercart_license.php')) {
            return false;
        }

        require_once(DIR_SYSTEM . 'library/dockercart_license.php');

        if (!class_exists('DockercartLicense')) {
            return false;
        }

        try {
            $license = new DockercartLicense($this->registry);
            $result = $license->verify($license_key, 'dockercart_import_yml');
            return !empty($result['valid']);
        } catch (Exception $e) {
            return false;
        }
    }
}
