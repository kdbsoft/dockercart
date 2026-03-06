-- ============================================================================
-- DockerCart Blog - Database Schema
-- Version: 1.0.0
-- Compatible: OpenCart 3.0.0 - 3.0.3.8+
-- Author: DockerCart Team
-- Description: Complete blog system for OpenCart with posts, categories,
--              authors, comments, tags, and SEO support
-- ============================================================================

-- Blog Authors Table
CREATE TABLE IF NOT EXISTS `oc_blog_author` (
  `author_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `image` varchar(255) DEFAULT NULL,
  `bio` text,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int(11) NOT NULL DEFAULT '0',
  `date_added` datetime NOT NULL,
  `date_modified` datetime NOT NULL,
  PRIMARY KEY (`author_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Blog Categories Table
CREATE TABLE IF NOT EXISTS `oc_blog_category` (
  `category_id` int(11) NOT NULL AUTO_INCREMENT,
  `parent_id` int(11) NOT NULL DEFAULT '0',
  `image` varchar(255) DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int(11) NOT NULL DEFAULT '0',
  `date_added` datetime NOT NULL,
  `date_modified` datetime NOT NULL,
  PRIMARY KEY (`category_id`),
  KEY `idx_parent_id` (`parent_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Blog Category Descriptions (Multi-language)
CREATE TABLE IF NOT EXISTS `oc_blog_category_description` (
  `category_id` int(11) NOT NULL,
  `language_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text,
  `meta_title` varchar(255) NOT NULL,
  `meta_description` varchar(255) NOT NULL,
  `meta_keyword` varchar(255) NOT NULL,
  PRIMARY KEY (`category_id`,`language_id`),
  KEY `idx_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Blog Category to Store
CREATE TABLE IF NOT EXISTS `oc_blog_category_to_store` (
  `category_id` int(11) NOT NULL,
  `store_id` int(11) NOT NULL,
  PRIMARY KEY (`category_id`,`store_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Blog Posts Table
CREATE TABLE IF NOT EXISTS `oc_blog_post` (
  `post_id` int(11) NOT NULL AUTO_INCREMENT,
  `author_id` int(11) NOT NULL,
  `image` varchar(255) DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `featured` tinyint(1) NOT NULL DEFAULT '0',
  `allow_comments` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int(11) NOT NULL DEFAULT '0',
  `views` int(11) NOT NULL DEFAULT '0',
  `date_published` datetime NOT NULL,
  `date_added` datetime NOT NULL,
  `date_modified` datetime NOT NULL,
  PRIMARY KEY (`post_id`),
  KEY `idx_author_id` (`author_id`),
  KEY `idx_status` (`status`),
  KEY `idx_featured` (`featured`),
  KEY `idx_date_published` (`date_published`),
  KEY `idx_views` (`views`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Blog Post Descriptions (Multi-language)
CREATE TABLE IF NOT EXISTS `oc_blog_post_description` (
  `post_id` int(11) NOT NULL,
  `language_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `content` mediumtext NOT NULL,
  `meta_title` varchar(255) NOT NULL,
  `meta_description` varchar(255) NOT NULL,
  `meta_keyword` varchar(255) NOT NULL,
  PRIMARY KEY (`post_id`,`language_id`),
  KEY `idx_name` (`name`),
  FULLTEXT KEY `idx_fulltext` (`name`,`description`,`content`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Blog Post to Store
CREATE TABLE IF NOT EXISTS `oc_blog_post_to_store` (
  `post_id` int(11) NOT NULL,
  `store_id` int(11) NOT NULL,
  PRIMARY KEY (`post_id`,`store_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Blog Post to Category
CREATE TABLE IF NOT EXISTS `oc_blog_post_to_category` (
  `post_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  PRIMARY KEY (`post_id`,`category_id`),
  KEY `idx_category_id` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Blog Tags
CREATE TABLE IF NOT EXISTS `oc_blog_post_tag` (
  `post_id` int(11) NOT NULL,
  `language_id` int(11) NOT NULL,
  `tag` varchar(255) NOT NULL,
  KEY `idx_post_id` (`post_id`),
  KEY `idx_tag` (`tag`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Blog Comments
CREATE TABLE IF NOT EXISTS `oc_blog_comment` (
  `comment_id` int(11) NOT NULL AUTO_INCREMENT,
  `post_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL DEFAULT '0',
  `author` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `text` text NOT NULL,
  `rating` int(1) DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '0',
  `ip` varchar(45) NOT NULL,
  `date_added` datetime NOT NULL,
  `date_modified` datetime NOT NULL,
  PRIMARY KEY (`comment_id`),
  KEY `idx_post_id` (`post_id`),
  KEY `idx_customer_id` (`customer_id`),
  KEY `idx_status` (`status`),
  KEY `idx_date_added` (`date_added`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Blog SEO URLs
CREATE TABLE IF NOT EXISTS `oc_blog_seo_url` (
  `seo_url_id` int(11) NOT NULL AUTO_INCREMENT,
  `store_id` int(11) NOT NULL,
  `language_id` int(11) NOT NULL,
  `query` varchar(255) NOT NULL,
  `keyword` varchar(255) NOT NULL,
  PRIMARY KEY (`seo_url_id`),
  UNIQUE KEY `idx_query_store_language` (`query`,`store_id`,`language_id`),
  UNIQUE KEY `idx_keyword_store_language` (`keyword`,`store_id`,`language_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Blog Module Settings
CREATE TABLE IF NOT EXISTS `oc_blog_setting` (
  `setting_id` int(11) NOT NULL AUTO_INCREMENT,
  `store_id` int(11) NOT NULL DEFAULT '0',
  `code` varchar(128) NOT NULL,
  `key` varchar(128) NOT NULL,
  `value` text NOT NULL,
  `serialized` tinyint(1) NOT NULL,
  PRIMARY KEY (`setting_id`),
  KEY `idx_store_code` (`store_id`,`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Blog Events (for Event System integration)
CREATE TABLE IF NOT EXISTS `oc_blog_event` (
  `event_id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(64) NOT NULL,
  `trigger` varchar(255) NOT NULL,
  `action` varchar(255) NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`event_id`),
  UNIQUE KEY `idx_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================================
-- Initial Data
-- ============================================================================

-- Default Blog Settings
INSERT INTO `oc_blog_setting` (`store_id`, `code`, `key`, `value`, `serialized`) VALUES
(0, 'module_dockercart_blog', 'module_dockercart_blog_status', '1', 0),
(0, 'module_dockercart_blog', 'module_dockercart_blog_posts_per_page', '10', 0),
(0, 'module_dockercart_blog', 'module_dockercart_blog_allow_comments', '1', 0),
(0, 'module_dockercart_blog', 'module_dockercart_blog_moderate_comments', '1', 0),
(0, 'module_dockercart_blog', 'module_dockercart_blog_captcha', '1', 0),
(0, 'module_dockercart_blog', 'module_dockercart_blog_show_author', '1', 0),
(0, 'module_dockercart_blog', 'module_dockercart_blog_show_date', '1', 0),
(0, 'module_dockercart_blog', 'module_dockercart_blog_show_views', '1', 0),
(0, 'module_dockercart_blog', 'module_dockercart_blog_latest_limit', '5', 0),
(0, 'module_dockercart_blog', 'module_dockercart_blog_sitemap', '1', 0);

-- Sample Author
INSERT INTO `oc_blog_author` (`name`, `email`, `bio`, `status`, `sort_order`, `date_added`, `date_modified`) VALUES
('Admin', 'admin@example.com', 'Blog administrator', 1, 0, NOW(), NOW());

-- Sample Category
INSERT INTO `oc_blog_category` (`parent_id`, `status`, `sort_order`, `date_added`, `date_modified`) VALUES
(0, 1, 1, NOW(), NOW());

INSERT INTO `oc_blog_category_description` (`category_id`, `language_id`, `name`, `description`, `meta_title`, `meta_description`, `meta_keyword`) VALUES
(1, 1, 'News', 'Latest news and updates', 'News', 'Latest news and updates', 'news, updates'),
(1, 2, 'Новости', 'Последние новости и обновления', 'Новости', 'Последние новости и обновления', 'новости, обновления');

INSERT INTO `oc_blog_category_to_store` (`category_id`, `store_id`) VALUES (1, 0);

-- ============================================================================
-- End of SQL Schema
-- ============================================================================
