<?php
declare(strict_types=1);

class ControllerExtensionCaptchaDockercart extends Controller {
	public function index($error = array()) {
		$this->load->language('extension/captcha/dockercart');

		if (isset($error['captcha'])) {
			$data['error_captcha'] = $error['captcha'];
		} else {
			$data['error_captcha'] = '';
		}

		$data['route'] = $this->request->get['route'];

		$operations = $this->config->get('captcha_dockercart_operations');
		if (!is_array($operations) || empty($operations)) {
			$operations = array('addition');
		}

		$max_number = (int)$this->config->get('captcha_dockercart_max_number');
		if ($max_number < 5) {
			$max_number = 10;
		}

		$operation = $operations[array_rand($operations)];
		$a = rand(1, $max_number);
		$b = rand(1, $max_number);

		switch ($operation) {
			case 'subtraction':
				if ($a < $b) {
					list($a, $b) = array($b, $a);
				}
				$answer = $a - $b;
				$op_symbol = '−';
				break;
			case 'multiplication':
				$a = rand(1, min(5, $max_number));
				$b = rand(1, min(5, $max_number));
				$answer = $a * $b;
				$op_symbol = '×';
				break;
			default:
				$answer = $a + $b;
				$op_symbol = '+';
				break;
		}

		$slider_max = $this->computeSliderMax($operations, $max_number, $answer);

		$data['question'] = $a . ' ' . $op_symbol . ' ' . $b . ' = ?';
		$data['slider_min'] = 0;
		$data['slider_max'] = $slider_max;

		$this->session->data['dockercart_captcha_answer'] = $answer;

		$html = $this->load->view('extension/captcha/dockercart', $data);
		$this->response->setOutput($html);
		return $html;
	}

	public function validate() {
		$this->load->language('extension/captcha/dockercart');

		if (empty($this->session->data['dockercart_captcha_answer']) || !isset($this->request->post['captcha'])) {
			$this->clearAnswer();
			return $this->language->get('error_captcha');
		}

		$posted = (int)$this->request->post['captcha'];
		$expected = (int)$this->session->data['dockercart_captcha_answer'];

		if ($posted !== $expected) {
			$this->clearAnswer();
			return $this->language->get('error_captcha');
		}

		$this->clearAnswer();
	}

	private function computeSliderMax(array $operations, int $max_number, int $answer): int {
		$max_possible = $max_number * 2;

		if (in_array('multiplication', $operations)) {
			$mult_max = min(5, $max_number) * min(5, $max_number);
			$max_possible = max($max_possible, $mult_max);
		}

		$max_possible = max($max_possible, $answer + 5);
		$max_possible = min($max_possible, 50);

		return $max_possible;
	}

	private function clearAnswer(): void {
		if (isset($this->session->data['dockercart_captcha_answer'])) {
			unset($this->session->data['dockercart_captcha_answer']);
		}
	}
}
