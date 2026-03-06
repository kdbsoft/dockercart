<?php
/**
 * DockerCart Blog - Admin Model
 * 
 * @package    DockerCart Blog
 * @version    1.0.0
 * @author     DockerCart Team
 * 
 * Description: Main model for database schema management and core blog operations.
 *              Handles installation, uninstallation, and provides utility methods.
 */

class ModelExtensionModuleDockercartBlog extends Model {

	/**
	 * Install database schema
	 * Executes SQL from install file
	 */
	public function install() {
		// SQL file location - in admin/controller/extension/module/
		$sql_file = DIR_APPLICATION . 'controller/extension/module/dockercart_blog_install.sql';
		
		if (!file_exists($sql_file)) {
			throw new Exception('Installation SQL file not found: ' . $sql_file);
		}

		$sql = file_get_contents($sql_file);
		
		// Properly parse SQL file
		$sql = $this->parseSql($sql);
		
		// Execute each statement
		foreach ($sql as $statement) {
			if (!empty($statement)) {
				try {
					$this->db->query($statement);
				} catch (Exception $e) {
					// Allow table already exists errors to be skipped
					$error_msg = $e->getMessage();
					if (strpos($error_msg, 'already exists') === false && 
					    strpos($error_msg, 'Duplicate') === false &&
					    strpos($error_msg, 'already in use') === false) {
						throw $e;
					}
					// Otherwise log and continue
					error_log('Blog install: ' . $error_msg);
				}
			}
		}

		// Cleanup: remove any malformed layout_module rows (missing or empty code)
		// Some older installers/modules may have inserted empty codes which later break layout saving
		try {
			$this->db->query("DELETE FROM `" . DB_PREFIX . "layout_module` WHERE code = '' OR code IS NULL");
			$this->log->write('DockerCart Blog install: cleaned up empty layout_module entries');
		} catch (Exception $e) {
			$this->log->write('DockerCart Blog install: failed to cleanup layout_module - ' . $e->getMessage());
		}
	}

	/**
	 * Parse SQL file into individual statements
	 * Handles comments and multi-line statements properly
	 * 
	 * @param string $sql Raw SQL content
	 * @return array Array of SQL statements
	 */
	private function parseSql($sql) {
		// Remove SQL comments
		$sql = preg_replace('/^--.*?$/m', '', $sql); // Remove line comments
		$sql = preg_replace('/\/\*[\s\S]*?\*\//m', '', $sql); // Remove block comments
		
		// Split on semicolons that aren't inside quotes
		$statements = array();
		$current = '';
		$in_quote = false;
		$quote_char = '';
		$length = strlen($sql);
		
		for ($i = 0; $i < $length; $i++) {
			$char = $sql[$i];
			$next_char = ($i + 1 < $length) ? $sql[$i + 1] : '';
			
			// Handle quotes
			if (($char === "'" || $char === '"') && ($i === 0 || $sql[$i - 1] !== '\\')) {
				if (!$in_quote) {
					$in_quote = true;
					$quote_char = $char;
				} elseif ($char === $quote_char) {
					$in_quote = false;
					$quote_char = '';
				}
			}
			
			// Handle semicolon - statement separator
			if ($char === ';' && !$in_quote) {
				$current .= $char;
				$stmt = trim($current);
				if (!empty($stmt)) {
					$statements[] = $stmt;
				}
				$current = '';
				continue;
			}
			
			$current .= $char;
		}
		
		// Add last statement if exists
		$stmt = trim($current);
		if (!empty($stmt) && $stmt !== ';') {
			$statements[] = $stmt;
		}
		
		return $statements;
	}

	/**
	 * Uninstall - remove all tables (USE WITH CAUTION!)
	 */
	public function uninstall() {
		// Drop all blog tables
		$tables = array(
			'blog_author',
			'blog_category',
			'blog_category_description',
			'blog_category_to_store',
			'blog_post',
			'blog_post_description',
			'blog_post_to_store',
			'blog_post_to_category',
			'blog_post_tag',
			'blog_comment',
			'blog_seo_url',
			'blog_setting',
			'blog_event'
		);

		foreach ($tables as $table) {
			$this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . $table . "`");
		}
	}

	/**
	 * Get module statistics
	 * 
	 * @return array
	 */
	public function getStatistics() {
		$data = array();

		// Total posts
		$query = $this->db->query("SELECT COUNT(*) as total FROM `" . DB_PREFIX . "blog_post` WHERE status = 1");
		$data['total_posts'] = $query->row['total'];

		// Total categories
		$query = $this->db->query("SELECT COUNT(*) as total FROM `" . DB_PREFIX . "blog_category` WHERE status = 1");
		$data['total_categories'] = $query->row['total'];

		// Total authors
		$query = $this->db->query("SELECT COUNT(*) as total FROM `" . DB_PREFIX . "blog_author` WHERE status = 1");
		$data['total_authors'] = $query->row['total'];

		// Total comments
		$query = $this->db->query("SELECT COUNT(*) as total FROM `" . DB_PREFIX . "blog_comment` WHERE status = 1");
		$data['total_comments'] = $query->row['total'];

		// Pending comments
		$query = $this->db->query("SELECT COUNT(*) as total FROM `" . DB_PREFIX . "blog_comment` WHERE status = 0");
		$data['pending_comments'] = $query->row['total'];

		return $data;
	}

	/**
	 * Clear blog cache
	 */
	public function clearCache() {
		$this->cache->delete('blog.*');
	}
}
