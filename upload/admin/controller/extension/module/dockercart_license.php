<?php

class ControllerExtensionModuleDockercartLicense extends Controller {

    public function verify() {
        $this->load->language('extension/module/dockercart_filter');

        $this->response->addHeader('Content-Type: application/json');

        if ($this->request->server['REQUEST_METHOD'] !== 'POST') {
            $this->response->setOutput(json_encode(['error' => 'Invalid request']));
            return;
        }

        try {
            $input = file_get_contents('php://input');
            $json = json_decode($input, true);

            if (!is_array($json)) {
                $this->response->setOutput(json_encode([
                    'valid' => false,
                    'error' => 'Invalid JSON input'
                ]));
                return;
            }

            $license_key = isset($json['license_key']) ? trim($json['license_key']) : '';
            $module_code = isset($json['module_code']) ? trim($json['module_code']) : 'dockercart_filter';

            if (empty($license_key)) {
                $this->response->setOutput(json_encode([
                    'valid' => false,
                    'error' => 'License key is empty'
                ]));
                return;
            }

            if (!file_exists(DIR_SYSTEM . 'library/dockercart_license.php')) {
                $this->response->setOutput(json_encode([
                    'valid' => false,
                    'error' => 'License library file not found at: ' . DIR_SYSTEM . 'library/dockercart_license.php'
                ]));
                return;
            }

            require_once(DIR_SYSTEM . 'library/dockercart_license.php');

            if (!class_exists('DockercartLicense')) {
                $this->response->setOutput(json_encode([
                    'valid' => false,
                    'error' => 'DockercartLicense class not defined'
                ]));
                return;
            }

            $license = new DockercartLicense($this->registry);
            $result = $license->verify($license_key, $module_code);

            if (!is_array($result)) {
                $this->response->setOutput(json_encode([
                    'valid' => false,
                    'error' => 'License verify() returned invalid type: ' . gettype($result)
                ]));
                return;
            }

            $response = [
                'valid' => isset($result['valid']) ? (bool)$result['valid'] : false,
                'module' => isset($result['module']) ? $result['module'] : null,
                'domain' => isset($result['domain']) ? $result['domain'] : null
            ];

            if (isset($result['valid']) && $result['valid']) {
                $response['expires'] = isset($result['expires_formatted']) ? $result['expires_formatted'] : 'Lifetime';
                $response['license_id'] = isset($result['license_id']) ? $result['license_id'] : null;
            } else {
                $response['error'] = isset($result['error']) ? $result['error'] : 'Validation failed without error message';
            }

            $this->response->setOutput(json_encode($response));

        } catch (Exception $e) {
            $this->response->setOutput(json_encode([
                'valid' => false,
                'error' => 'Exception: ' . $e->getMessage() . ' (Line ' . $e->getLine() . ')'
            ]));
        } catch (Throwable $e) {
            $this->response->setOutput(json_encode([
                'valid' => false,
                'error' => 'Error: ' . $e->getMessage() . ' (Line ' . $e->getLine() . ')'
            ]));
        }
    }

    public function info() {
        $this->response->addHeader('Content-Type: application/json');

        $license_key = $this->config->get('module_dockercart_filter_license_key');

        if (empty($license_key)) {
            $this->response->setOutput(json_encode([
                'valid' => false,
                'error' => 'No license key configured'
            ]));
            return;
        }

        try {
            require_once(DIR_SYSTEM . 'library/dockercart_license.php');
            $license = new DockercartLicense($this->registry);

            $info = $license->getInfo($license_key, 'dockercart_filter');

            $this->response->setOutput(json_encode($info));

        } catch (Exception $e) {
            $this->response->setOutput(json_encode([
                'valid' => false,
                'error' => 'Error: ' . $e->getMessage()
            ]));
        }
    }
}
