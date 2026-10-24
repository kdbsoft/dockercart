-- Migration: 20261028 - blog post Manticore indexing events
-- Blog articles are part of the admin global search (common/admin_search) and
-- are indexed into the Manticore `blog_posts` table. These events keep the
-- index in sync whenever a post is created, edited, copied or deleted.
-- Idempotent: `make migrate` re-runs every file, so inserts guard on NOT EXISTS.
INSERT INTO `oc_event` (`code`, `trigger`, `action`, `status`, `sort_order`)
SELECT 'dockercart_search_blog_post_add', 'admin/model/extension/module/dockercart_blog_post/addPost/after', 'extension/module/dockercart_search/eventBlogPostAdd', 1, 0
WHERE NOT EXISTS (SELECT 1 FROM `oc_event` WHERE `code` = 'dockercart_search_blog_post_add');

INSERT INTO `oc_event` (`code`, `trigger`, `action`, `status`, `sort_order`)
SELECT 'dockercart_search_blog_post_edit', 'admin/model/extension/module/dockercart_blog_post/editPost/after', 'extension/module/dockercart_search/eventBlogPostEdit', 1, 0
WHERE NOT EXISTS (SELECT 1 FROM `oc_event` WHERE `code` = 'dockercart_search_blog_post_edit');

INSERT INTO `oc_event` (`code`, `trigger`, `action`, `status`, `sort_order`)
SELECT 'dockercart_search_blog_post_delete', 'admin/model/extension/module/dockercart_blog_post/deletePost/after', 'extension/module/dockercart_search/eventBlogPostDelete', 1, 0
WHERE NOT EXISTS (SELECT 1 FROM `oc_event` WHERE `code` = 'dockercart_search_blog_post_delete');

INSERT INTO `oc_event` (`code`, `trigger`, `action`, `status`, `sort_order`)
SELECT 'dockercart_search_blog_post_copy', 'admin/model/extension/module/dockercart_blog_post/copyPost/after', 'extension/module/dockercart_search/eventBlogPostAdd', 1, 0
WHERE NOT EXISTS (SELECT 1 FROM `oc_event` WHERE `code` = 'dockercart_search_blog_post_copy');
