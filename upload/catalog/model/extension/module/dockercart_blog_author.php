<?php
/**
 * DockerCart Blog - Author Catalog Model
 */

class ModelExtensionModuleDockercartBlogAuthor extends Model {

	public function getAuthor($author_id) {
		$cache_key = 'blog.author.' . (int)$author_id;
		$author = $this->cache->get($cache_key);

		if (!$author) {
			$query = $this->db->query("SELECT * 
				FROM `" . DB_PREFIX . "blog_author` 
				WHERE author_id = '" . (int)$author_id . "' 
				AND status = '1'");

			$author = $query->row;

			$this->cache->set($cache_key, $author, 3600);
		}

		return $author;
	}

	public function getAuthors() {
		$cache_key = 'blog.authors.all';
		$authors = $this->cache->get($cache_key);

		if (!$authors) {
			$query = $this->db->query("SELECT * 
				FROM `" . DB_PREFIX . "blog_author` 
				WHERE status = '1' 
				ORDER BY sort_order, name");

			$authors = $query->rows;

			$this->cache->set($cache_key, $authors, 3600);
		}

		return $authors;
	}

	public function getTotalPostsByAuthor($author_id) {
		$query = $this->db->query("SELECT COUNT(*) AS total 
			FROM `" . DB_PREFIX . "blog_post` bp
			WHERE bp.author_id = '" . (int)$author_id . "' 
			AND bp.status = '1' 
			AND bp.date_published <= NOW()");

		return $query->row['total'];
	}
}
