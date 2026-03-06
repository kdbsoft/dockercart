<?php
class ControllerExtensionModuleDockercartNewsletter extends Controller {
    public function index($setting) {
        if (!isset($setting['status']) || !(int)$setting['status']) {
            return '';
        }

        $this->load->language('extension/module/dockercart_newsletter');

        $data['module_id'] = 'dc-newsletter-' . mt_rand(1000, 999999);

        // Support multilingual module settings: if the setting value is an array keyed by language_id, pick the current language
        $language_id = (int)$this->config->get('config_language_id');

        // helper to resolve a setting that may be language-specific
        $resolve = function($key, $default) use ($setting, $language_id) {
            if (isset($setting['module_settings']) && is_array($setting['module_settings'])) {
                if (isset($setting['module_settings'][$language_id][$key]) && $setting['module_settings'][$language_id][$key] !== '') {
                    return $setting['module_settings'][$language_id][$key];
                }

                foreach ($setting['module_settings'] as $language_settings) {
                    if (is_array($language_settings) && isset($language_settings[$key]) && $language_settings[$key] !== '') {
                        return $language_settings[$key];
                    }
                }
            }

            if (!isset($setting[$key])) {
                return $default;
            }

            $val = $setting[$key];
            if (is_array($val)) {
                if (isset($val[$language_id]) && $val[$language_id] !== '') {
                    return $val[$language_id];
                }

                foreach ($val as $candidate) {
                    if ($candidate !== '') {
                        return $candidate;
                    }
                }

                return $default;
            }

            return $val !== '' ? $val : $default;
        };

        $data['title'] = $resolve('title', $this->language->get('text_default_title'));
        $data['subtitle'] = $resolve('subtitle', $this->language->get('text_default_subtitle'));
        $data['placeholder'] = $resolve('placeholder', $this->language->get('text_default_placeholder'));
        $data['button_text'] = $resolve('button_text', $this->language->get('text_default_button'));
        $data['privacy_text'] = $resolve('privacy_text', $this->language->get('text_default_privacy'));
        $data['success_text'] = $resolve('success_text', $this->language->get('text_default_success'));
        $data['already_text'] = $resolve('already_text', $this->language->get('text_default_already'));

        $data['is_subscribed'] = false;
        $data['prefill_email'] = '';

        if ($this->customer->isLogged()) {
            $email = (string)$this->customer->getEmail();
            $data['prefill_email'] = $email;

            if ((int)$this->customer->getNewsletter() === 1) {
                $data['is_subscribed'] = true;
            }
        }

        $data['subscribe_url'] = $this->url->link('extension/module/dockercart_newsletter/subscribe', '', true);

        return $this->load->view('extension/module/dockercart_newsletter', $data);
    }

    public function subscribe() {
        $this->load->language('extension/module/dockercart_newsletter');
        $this->load->model('extension/module/dockercart_newsletter');

        $json = array(
            'success' => false
        );

        if ($this->request->server['REQUEST_METHOD'] !== 'POST') {
            $json['message'] = $this->language->get('error_method');
        } else {
            $email = isset($this->request->post['email']) ? trim((string)$this->request->post['email']) : '';
            $custom_success_text = isset($this->request->post['success_text']) ? trim((string)$this->request->post['success_text']) : '';
            $custom_already_text = isset($this->request->post['already_text']) ? trim((string)$this->request->post['already_text']) : '';

            if (utf8_strlen($custom_success_text) > 255) {
                $custom_success_text = utf8_substr($custom_success_text, 0, 255);
            }

            if (utf8_strlen($custom_already_text) > 255) {
                $custom_already_text = utf8_substr($custom_already_text, 0, 255);
            }

            $already_message = $custom_already_text !== '' ? $custom_already_text : $this->language->get('text_default_already');
            $success_message = $custom_success_text !== '' ? $custom_success_text : $this->language->get('text_default_success');

            if ($email === '' && $this->customer->isLogged()) {
                $email = (string)$this->customer->getEmail();
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $json['message'] = $this->language->get('error_email');
            } else {
                if ($this->model_extension_module_dockercart_newsletter->isSubscribed($email)) {
                    $json['success'] = true;
                    $json['already'] = true;
                    $json['message'] = $already_message;
                } else {
                    $status = $this->model_extension_module_dockercart_newsletter->subscribeEmail($email);

                    if ($status === 'invalid') {
                        $json['message'] = $this->language->get('error_email');
                    } elseif ($status === 'already') {
                        $json['success'] = true;
                        $json['already'] = true;
                        $json['message'] = $already_message;
                    } else {
                        $json['success'] = true;
                        $json['already'] = false;
                        $json['message'] = $success_message;
                    }
                }
            }
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
}
