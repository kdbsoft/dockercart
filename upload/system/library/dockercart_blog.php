<?php
/**
 * DockerCart Blog - System Library
 * 
 * @package    DockerCart Blog
 * @version    1.0.0
 * @author     DockerCart Team
 * 
 * Description: Core library for blog functionality including:
 *              - Event handlers
 *              - SEO helpers
 *              - Sitemap generation (streaming)
 *              - URL rewriting
 *              - Utility functions
 */

class DockercartBlog {
	
	private $registry;
	
	/**
	 * Constructor
	 * 
	 * @param Registry $registry
	 */
	public function __construct($registry) {
		$this->registry = $registry;
	}
	
	/**
	 * Magic getter for registry objects
	 */
	public function __get($key) {
		return $this->registry->get($key);
	}
	
	/**
	 * Generate SEO URL for blog entity
	 * 
	 * @param string $type Entity type (post, category, author)
	 * @param int $id Entity ID
	 * @param int $language_id Language ID
	 * @return string SEO URL keyword or empty string
	 */
	public function getSeoUrl($type, $id, $language_id = null) {
		if ($language_id === null) {
			$language_id = $this->config->get('config_language_id');
		}
		
		$query_map = array(
			'post'     => 'blog_post_id',
			'category' => 'blog_category_id',
			'author'   => 'blog_author_id'
		);
		
		if (!isset($query_map[$type])) {
			return '';
		}
		
		$query_string = $query_map[$type] . '=' . (int)$id;
		
		$result = $this->db->query("SELECT keyword FROM `" . DB_PREFIX . "blog_seo_url` 
			WHERE query = '" . $this->db->escape($query_string) . "' 
			AND store_id = '" . (int)$this->config->get('config_store_id') . "' 
			AND language_id = '" . (int)$language_id . "'");
		
		return $result->num_rows ? $result->row['keyword'] : '';
	}
	
	/**
	 * Generate sitemap XML for blog posts (streaming)
	 * 
	 * @param resource $handle File handle for output
	 * @param int $store_id Store ID
	 * @param int $language_id Language ID
	 */
	public function generateSitemap($handle, $store_id = 0, $language_id = 1) {
		// Get all published posts
		$query = $this->db->query("SELECT bp.post_id, bp.date_modified, bpd.name 
			FROM `" . DB_PREFIX . "blog_post` bp
			LEFT JOIN `" . DB_PREFIX . "blog_post_description` bpd ON (bp.post_id = bpd.post_id)
			LEFT JOIN `" . DB_PREFIX . "blog_post_to_store` bps ON (bp.post_id = bps.post_id)
			WHERE bp.status = '1' 
			AND bp.date_published <= NOW()
			AND bpd.language_id = '" . (int)$language_id . "'
			AND bps.store_id = '" . (int)$store_id . "'
			ORDER BY bp.date_modified DESC");
		
		foreach ($query->rows as $post) {
			$keyword = $this->getSeoUrl('post', $post['post_id'], $language_id);
			
			if ($keyword) {
				$url = $this->getStoreUrl($store_id) . $keyword;
			} else {
				$url = $this->getStoreUrl($store_id) . 'index.php?route=blog/post&blog_post_id=' . $post['post_id'];
			}
			
			// Write directly to handle (streaming)
			fwrite($handle, "\t<url>\n");
			fwrite($handle, "\t\t<loc>" . htmlspecialchars($url, ENT_XML1) . "</loc>\n");
			fwrite($handle, "\t\t<lastmod>" . date('Y-m-d', strtotime($post['date_modified'])) . "</lastmod>\n");
			fwrite($handle, "\t\t<changefreq>weekly</changefreq>\n");
			fwrite($handle, "\t\t<priority>0.6</priority>\n");
			fwrite($handle, "\t</url>\n");
			
			// Free memory
			unset($post);
		}
		
		// Categories
		$query = $this->db->query("SELECT bc.category_id, bc.date_modified 
			FROM `" . DB_PREFIX . "blog_category` bc
			LEFT JOIN `" . DB_PREFIX . "blog_category_to_store` bcs ON (bc.category_id = bcs.category_id)
			WHERE bc.status = '1' 
			AND bcs.store_id = '" . (int)$store_id . "'");
		
		foreach ($query->rows as $category) {
			$keyword = $this->getSeoUrl('category', $category['category_id'], $language_id);
			
			if ($keyword) {
				$url = $this->getStoreUrl($store_id) . $keyword;
			} else {
				$url = $this->getStoreUrl($store_id) . 'index.php?route=blog/category&blog_category_id=' . $category['category_id'];
			}
			
			fwrite($handle, "\t<url>\n");
			fwrite($handle, "\t\t<loc>" . htmlspecialchars($url, ENT_XML1) . "</loc>\n");
			fwrite($handle, "\t\t<lastmod>" . date('Y-m-d', strtotime($category['date_modified'])) . "</lastmod>\n");
			fwrite($handle, "\t\t<changefreq>weekly</changefreq>\n");
			fwrite($handle, "\t\t<priority>0.7</priority>\n");
			fwrite($handle, "\t</url>\n");
			
			unset($category);
		}
	}
	
	/**
	 * Get store URL
	 * 
	 * @param int $store_id
	 * @return string
	 */
	private function getStoreUrl($store_id) {
		if ($store_id && $this->config->get('config_url')) {
			return $this->config->get('config_url');
		} else {
			return HTTP_SERVER;
		}
	}
	
	/**
	 * Slugify string for SEO URL
	 * 
	 * @param string $text
	 * @return string
	 */
	public function slugify($text) {
		// Replace non letter or digits by -
		$text = preg_replace('~[^\pL\d]+~u', '-', $text);
		
		// Transliterate
		$text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
		
		// Remove unwanted characters
		$text = preg_replace('~[^-\w]+~', '', $text);
		
		// Trim
		$text = trim($text, '-');
		
		// Remove duplicate -
		$text = preg_replace('~-+~', '-', $text);
		
		// Lowercase
		$text = strtolower($text);
		
		if (empty($text)) {
			return 'n-a';
		}
		
		return $text;
	}
	
	/**
	 * Get breadcrumb path for category
	 * 
	 * @param int $category_id
	 * @return array
	 */
	public function getCategoryPath($category_id) {
		$path = array();
		
		$query = $this->db->query("SELECT parent_id FROM `" . DB_PREFIX . "blog_category` 
			WHERE category_id = '" . (int)$category_id . "'");
		
		if ($query->num_rows && $query->row['parent_id']) {
			$path = array_merge($this->getCategoryPath($query->row['parent_id']), array($category_id));
		} else {
			$path[] = $category_id;
		}
		
		return $path;
	}
	
	/**
	 * Format post excerpt
	 * 
	 * @param string $text Full text
	 * @param int $length Character limit
	 * @return string
	 */
	public function excerpt($text, $length = 200) {
		$text = strip_tags(html_entity_decode($text, ENT_QUOTES, 'UTF-8'));
		
		if (mb_strlen($text) > $length) {
			$text = mb_substr($text, 0, $length);
			
			// Cut at last word boundary
			$last_space = mb_strrpos($text, ' ');
			if ($last_space !== false) {
				$text = mb_substr($text, 0, $last_space);
			}
			
			$text .= '...';
		}
		
		return $text;
	}
	
	/**
	 * Send email notification for new comment
	 * 
	 * @param int $post_id
	 * @param array $comment_data
	 */
	public function sendCommentNotification($post_id, $comment_data) {
		// Get post info
		$query = $this->db->query("SELECT bpd.name FROM `" . DB_PREFIX . "blog_post_description` bpd
			WHERE bpd.post_id = '" . (int)$post_id . "' 
			AND bpd.language_id = '" . (int)$this->config->get('config_language_id') . "'");
		
		if (!$query->num_rows) {
			return;
		}
		
		$post_name = $query->row['name'];
		
		// Prepare email
		$subject = 'New comment on: ' . $post_name;
		
		$message = "A new comment has been posted on your blog post:\n\n";
		$message .= "Post: " . $post_name . "\n";
		$message .= "Author: " . $comment_data['author'] . "\n";
		$message .= "Email: " . $comment_data['email'] . "\n";
		$message .= "Comment:\n" . $comment_data['text'] . "\n\n";
		$message .= "View post: " . $this->url->link('blog/post', 'blog_post_id=' . $post_id) . "\n";
		
		// Send to admin
		$mail = new Mail($this->config->get('config_mail_engine'));
		$mail->parameter = $this->config->get('config_mail_parameter');
		$mail->smtp_hostname = $this->config->get('config_mail_smtp_hostname');
		$mail->smtp_username = $this->config->get('config_mail_smtp_username');
		$mail->smtp_password = html_entity_decode($this->config->get('config_mail_smtp_password'), ENT_QUOTES, 'UTF-8');
		$mail->smtp_port = $this->config->get('config_mail_smtp_port');
		$mail->smtp_timeout = $this->config->get('config_mail_smtp_timeout');
		
		$mail->setTo($this->config->get('config_email'));
		$mail->setFrom($this->config->get('config_email'));
		$mail->setSender($this->config->get('config_name'));
		$mail->setSubject($subject);
		$mail->setText($message);
		$mail->send();
	}
	
	/**
	 * Get popular posts by views
	 * 
	 * @param int $limit
	 * @param int $language_id
	 * @return array
	 */
	public function getPopularPosts($limit = 5, $language_id = null) {
		if ($language_id === null) {
			$language_id = $this->config->get('config_language_id');
		}
		
		$cache_key = 'blog.popular.' . $limit . '.' . $language_id;
		$posts = $this->cache->get($cache_key);
		
		if (!$posts) {
			$query = $this->db->query("SELECT bp.post_id, bp.image, bp.views, bp.date_published, 
				bpd.name, bpd.description 
				FROM `" . DB_PREFIX . "blog_post` bp
				LEFT JOIN `" . DB_PREFIX . "blog_post_description` bpd ON (bp.post_id = bpd.post_id)
				LEFT JOIN `" . DB_PREFIX . "blog_post_to_store` bps ON (bp.post_id = bps.post_id)
				WHERE bp.status = '1' 
				AND bp.date_published <= NOW()
				AND bpd.language_id = '" . (int)$language_id . "'
				AND bps.store_id = '" . (int)$this->config->get('config_store_id') . "'
				ORDER BY bp.views DESC
				LIMIT " . (int)$limit);
			
			$posts = $query->rows;
			$this->cache->set($cache_key, $posts, 3600);
		}
		
		return $posts;
	}
	
	/**
	 * Get recent posts
	 * 
	 * @param int $limit
	 * @param int $language_id
	 * @return array
	 */
	public function getRecentPosts($limit = 5, $language_id = null) {
		if ($language_id === null) {
			$language_id = $this->config->get('config_language_id');
		}
		
		$cache_key = 'blog.recent.' . $limit . '.' . $language_id;
		$posts = $this->cache->get($cache_key);
		
		if (!$posts) {
			$query = $this->db->query("SELECT bp.post_id, bp.image, bp.date_published, 
				bpd.name, bpd.description 
				FROM `" . DB_PREFIX . "blog_post` bp
				LEFT JOIN `" . DB_PREFIX . "blog_post_description` bpd ON (bp.post_id = bpd.post_id)
				LEFT JOIN `" . DB_PREFIX . "blog_post_to_store` bps ON (bp.post_id = bps.post_id)
				WHERE bp.status = '1' 
				AND bp.date_published <= NOW()
				AND bpd.language_id = '" . (int)$language_id . "'
				AND bps.store_id = '" . (int)$this->config->get('config_store_id') . "'
				ORDER BY bp.date_published DESC
				LIMIT " . (int)$limit);
			
			$posts = $query->rows;
			$this->cache->set($cache_key, $posts, 3600);
		}
		
		return $posts;
	}
	
	/**
	 * Get archive dates (year/month with post count)
	 * 
	 * @return array
	 */
	public function getArchiveDates() {
		$cache_key = 'blog.archive.' . $this->config->get('config_language_id');
		$dates = $this->cache->get($cache_key);
		
		if (!$dates) {
			$query = $this->db->query("SELECT 
				DATE_FORMAT(bp.date_published, '%Y') as year,
				DATE_FORMAT(bp.date_published, '%m') as month,
				DATE_FORMAT(bp.date_published, '%M') as month_name,
				COUNT(*) as total
				FROM `" . DB_PREFIX . "blog_post` bp
				LEFT JOIN `" . DB_PREFIX . "blog_post_to_store` bps ON (bp.post_id = bps.post_id)
				WHERE bp.status = '1' 
				AND bp.date_published <= NOW()
				AND bps.store_id = '" . (int)$this->config->get('config_store_id') . "'
				GROUP BY year, month
				ORDER BY year DESC, month DESC");
			
			$dates = $query->rows;
			$this->cache->set($cache_key, $dates, 3600);
		}
		
		return $dates;
	}
	
	/**
	 * Clear all blog cache
	 */
	public function clearCache() {
		$this->cache->delete('blog.');
	}
}
