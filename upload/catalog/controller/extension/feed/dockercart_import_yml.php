<?php
class ControllerExtensionFeedDockercartImportYml extends Controller {

    public function index() {
        $this->cron();
    }

    public function cron() {
        $profile_id = isset($this->request->get['profile_id']) ? (int)$this->request->get['profile_id'] : 0;
        $cron_key = isset($this->request->get['cron_key']) ? (string)$this->request->get['cron_key'] : '';
        $format = isset($this->request->get['format']) ? (string)$this->request->get['format'] : 'json';

        $json = array('success' => false);

        try {
            if ($profile_id <= 0) {
                throw new Exception('Invalid profile_id');
            }

            $this->load->model('extension/module/dockercart_import_yml');
            $profile = $this->model_extension_module_dockercart_import_yml->getProfile($profile_id);

            if (!$profile) {
                throw new Exception('Profile not found');
            }

            if (empty($profile['cron_key']) || !hash_equals((string)$profile['cron_key'], $cron_key)) {
                throw new Exception('Invalid cron key');
            }

            $summary = $this->model_extension_module_dockercart_import_yml->runImport($profile_id);

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
                    'errors=' . (int)$summary['errors']
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
}
