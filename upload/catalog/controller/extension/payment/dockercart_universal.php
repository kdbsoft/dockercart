<?php
class ControllerExtensionPaymentDockercartUniversal extends Controller {
    public function index() {
        $this->load->language('extension/payment/dockercart_universal');

        $description = '';

        if (isset($this->session->data['payment_method']['dockercart_universal_description'])) {
            $description = (string)$this->session->data['payment_method']['dockercart_universal_description'];
        }

        $data['description'] = trim($description) !== '' ? $description : $this->language->get('text_description');

        return $this->load->view('extension/payment/dockercart_universal', $data);
    }

    public function confirm() {
        $json = [];

        $this->load->language('extension/payment/dockercart_universal');
        $payment_code = $this->session->data['payment_method']['code'] ?? '';

        if ($payment_code == 'dockercart_universal' || strpos($payment_code, 'dockercart_universal.') === 0) {
            $this->load->model('checkout/order');

            $order_status_id = (int)$this->config->get('payment_dockercart_universal_order_status_id');

            if ($order_status_id <= 0) {
                $order_status_id = (int)$this->config->get('config_order_status_id');
            }

            $title = $this->session->data['payment_method']['title'] ?? $this->language->get('text_title');
            $description = $this->session->data['payment_method']['dockercart_universal_description'] ?? '';

            $comment = $title;

            if ($description !== '') {
                $comment .= "\n\n" . html_entity_decode(strip_tags((string)$description), ENT_QUOTES, 'UTF-8');
            }

            $this->model_checkout_order->addOrderHistory((int)$this->session->data['order_id'], $order_status_id, $comment, true);

            $json['redirect'] = $this->url->link('checkout/success');
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
}
