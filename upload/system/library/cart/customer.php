<?php
namespace Cart;
class Customer {
	private $config;
	private $db;
	private $request;
	private $session;

	private $customer_id;
	private $firstname;
	private $lastname;
	private $customer_group_id;
	private $email;
	private $telephone;
	private $newsletter;
	private $address_id;

	public function __construct($registry) {
		$this->config = $registry->get('config');
		$this->db = $registry->get('db');
		$this->request = $registry->get('request');
		$this->session = $registry->get('session');

		if (isset($this->session->data['customer_id'])) {
			$customer_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "customer WHERE customer_id = '" . (int)$this->session->data['customer_id'] . "' AND status = '1'");

			if ($customer_query->num_rows) {
				$this->customer_id = $customer_query->row['customer_id'];
				$this->firstname = $customer_query->row['firstname'];
				$this->lastname = $customer_query->row['lastname'];
				$this->customer_group_id = $customer_query->row['customer_group_id'];
				$this->email = $customer_query->row['email'];
				$this->telephone = $customer_query->row['telephone'];
				$this->newsletter = $customer_query->row['newsletter'];
				$this->address_id = $customer_query->row['address_id'];

				$this->db->query("UPDATE " . DB_PREFIX . "customer SET language_id = '" . (int)$this->config->get('config_language_id') . "', ip = '" . $this->db->escape($this->request->server['REMOTE_ADDR']) . "' WHERE customer_id = '" . (int)$this->customer_id . "'");

				$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "customer_ip WHERE customer_id = '" . (int)$this->session->data['customer_id'] . "' AND ip = '" . $this->db->escape($this->request->server['REMOTE_ADDR']) . "'");

				if (!$query->num_rows) {
					$this->db->query("INSERT INTO " . DB_PREFIX . "customer_ip SET customer_id = '" . (int)$this->session->data['customer_id'] . "', ip = '" . $this->db->escape($this->request->server['REMOTE_ADDR']) . "', date_added = NOW()");
				}
			} else {
				$this->logout();
			}
		}
		else {
			// Attempt "remember me" login via persistent cookie
			if (isset($this->request->cookie['remember_customer']) && $this->request->cookie['remember_customer']) {
				$token = $this->db->escape($this->request->cookie['remember_customer']);
				$customer_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "customer WHERE remember_token = '" . $token . "' AND status = '1'");

				if ($customer_query->num_rows) {
					// Set session and load customer data
					$this->session->data['customer_id'] = $customer_query->row['customer_id'];

					$this->customer_id = $customer_query->row['customer_id'];
					$this->firstname = $customer_query->row['firstname'];
					$this->lastname = $customer_query->row['lastname'];
					$this->customer_group_id = $customer_query->row['customer_group_id'];
					$this->email = $customer_query->row['email'];
					$this->telephone = $customer_query->row['telephone'];
					$this->newsletter = $customer_query->row['newsletter'];
					$this->address_id = $customer_query->row['address_id'];

					$this->db->query("UPDATE " . DB_PREFIX . "customer SET language_id = '" . (int)$this->config->get('config_language_id') . "', ip = '" . $this->db->escape($this->request->server['REMOTE_ADDR']) . "' WHERE customer_id = '" . (int)$this->customer_id . "'");

					// Rotate token to prevent reuse
					try {
						$newToken = bin2hex(random_bytes(32));
					} catch (\Exception $e) {
						$newToken = bin2hex(openssl_random_pseudo_bytes(32));
					}

					try {
						$this->db->query("UPDATE " . DB_PREFIX . "customer SET remember_token = '" . $this->db->escape($newToken) . "' WHERE customer_id = '" . (int)$this->customer_id . "'");
					} catch (\Exception $e) {
						// If column does not exist, try to add it and retry
						$this->db->query("ALTER TABLE " . DB_PREFIX . "customer ADD COLUMN remember_token varchar(128) NOT NULL DEFAULT ''");
						$this->db->query("UPDATE " . DB_PREFIX . "customer SET remember_token = '" . $this->db->escape($newToken) . "' WHERE customer_id = '" . (int)$this->customer_id . "'");
					}

					// Set cookie for 30 days (HttpOnly, Secure when applicable, SameSite=Lax)
					$secure = (!empty($this->request->server['HTTPS']) && ($this->request->server['HTTPS'] == 'on' || $this->request->server['HTTPS'] == '1'));
					setcookie('remember_customer', $newToken, ['expires' => time() + 60 * 60 * 24 * 30, 'path' => '/', 'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax']);
					$_COOKIE['remember_customer'] = $newToken;
				}
			}
		}
	}

  public function login($email, $password, $override = false) {
		if ($override) {
			$customer_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "customer WHERE LOWER(email) = '" . $this->db->escape(utf8_strtolower($email)) . "' AND status = '1'");
		} else {
			$customer_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "customer WHERE LOWER(email) = '" . $this->db->escape(utf8_strtolower($email)) . "' AND (password = SHA1(CONCAT(salt, SHA1(CONCAT(salt, SHA1('" . $this->db->escape($password) . "'))))) OR password = '" . $this->db->escape(md5($password)) . "') AND status = '1'");
		}

		if ($customer_query->num_rows) {
			$this->session->data['customer_id'] = $customer_query->row['customer_id'];

			$this->customer_id = $customer_query->row['customer_id'];
			$this->firstname = $customer_query->row['firstname'];
			$this->lastname = $customer_query->row['lastname'];
			$this->customer_group_id = $customer_query->row['customer_group_id'];
			$this->email = $customer_query->row['email'];
			$this->telephone = $customer_query->row['telephone'];
			$this->newsletter = $customer_query->row['newsletter'];
			$this->address_id = $customer_query->row['address_id'];
		
			$this->db->query("UPDATE " . DB_PREFIX . "customer SET language_id = '" . (int)$this->config->get('config_language_id') . "', ip = '" . $this->db->escape($this->request->server['REMOTE_ADDR']) . "' WHERE customer_id = '" . (int)$this->customer_id . "'");

			// Generate remember token and set cookie for persistent login
			try {
				$token = bin2hex(random_bytes(32));
			} catch (\Exception $e) {
				$token = bin2hex(openssl_random_pseudo_bytes(32));
			}

			try {
				$this->db->query("UPDATE " . DB_PREFIX . "customer SET remember_token = '" . $this->db->escape($token) . "' WHERE customer_id = '" . (int)$this->customer_id . "'");
			} catch (\Exception $e) {
				$this->db->query("ALTER TABLE " . DB_PREFIX . "customer ADD COLUMN remember_token varchar(128) NOT NULL DEFAULT ''");
				$this->db->query("UPDATE " . DB_PREFIX . "customer SET remember_token = '" . $this->db->escape($token) . "' WHERE customer_id = '" . (int)$this->customer_id . "'");
			}

			$secure = (!empty($this->request->server['HTTPS']) && ($this->request->server['HTTPS'] == 'on' || $this->request->server['HTTPS'] == '1'));
			setcookie('remember_customer', $token, ['expires' => time() + 60 * 60 * 24 * 30, 'path' => '/', 'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax']);
			$_COOKIE['remember_customer'] = $token;

			return true;
		} else {
			return false;
		}
	}

	public function logout() {
		// Remove session and invalidate remember token/cookie
		unset($this->session->data['customer_id']);

		if (isset($this->request->cookie['remember_customer']) && $this->request->cookie['remember_customer']) {
			$token = $this->db->escape($this->request->cookie['remember_customer']);
			try {
				$this->db->query("UPDATE " . DB_PREFIX . "customer SET remember_token = '' WHERE remember_token = '" . $token . "'");
			} catch (\Exception $e) {
				$this->db->query("ALTER TABLE " . DB_PREFIX . "customer ADD COLUMN remember_token varchar(128) NOT NULL DEFAULT ''");
				$this->db->query("UPDATE " . DB_PREFIX . "customer SET remember_token = '' WHERE remember_token = '" . $token . "'");
			}
			setcookie('remember_customer', '', ['expires' => time() - 3600, 'path' => '/', 'httponly' => true]);
			unset($_COOKIE['remember_customer']);
		}

		$this->customer_id = '';
		$this->firstname = '';
		$this->lastname = '';
		$this->customer_group_id = '';
		$this->email = '';
		$this->telephone = '';
		$this->newsletter = '';
		$this->address_id = '';
	}

	public function isLogged() {
		return $this->customer_id;
	}

	public function getId() {
		return $this->customer_id;
	}

	public function getFirstName() {
		return $this->firstname;
	}

	public function getLastName() {
		return $this->lastname;
	}

	public function getGroupId() {
		return $this->customer_group_id;
	}

	public function getEmail() {
		return $this->email;
	}

	public function getTelephone() {
		return $this->telephone;
	}

	public function getNewsletter() {
		return $this->newsletter;
	}

	public function getAddressId() {
		return $this->address_id;
	}

	public function getBalance() {
		$query = $this->db->query("SELECT SUM(amount) AS total FROM " . DB_PREFIX . "customer_transaction WHERE customer_id = '" . (int)$this->customer_id . "'");

		return $query->row['total'];
	}

	public function getRewardPoints() {
		$query = $this->db->query("SELECT SUM(points) AS total FROM " . DB_PREFIX . "customer_reward WHERE customer_id = '" . (int)$this->customer_id . "'");

		return $query->row['total'];
	}
}
