<?php
/**
 * Blog post autosave — shared configuration.
 *
 * The blog post form opts into autosave from its template:
 *
 *   <form ... data-autosave="1" data-autosave-interval="60">
 *
 * and the module (`upload/admin/view/javascript/dockercart_autosave.js`) re-posts
 * that form to its own action once a minute while the content differs from the
 * last saved state — a plain POST, exactly what the Save button sends, so no
 * controller needs an "autosave" branch.
 *
 * The switch lives in the blog settings (Extensions → Modules → DockerCart Blog,
 * "Posts" field) as `module_dockercart_blog_post_autosave` and defaults to
 * *enabled* when the key has never been saved. This class is the single source of
 * truth for that decision and for the cadence.
 *
 * @package    DockerCart
 * @subpackage Library
 */

declare(strict_types=1);

namespace Dockercart\Admin;

final class Autosave {
	/**
	 * Autosave cadence in seconds. Fixed on purpose: the post form is heavy
	 * (whole entity POST + validation), so "every minute" is the contract.
	 */
	public const INTERVAL = 60;

	/** `oc_setting` key holding the on/off switch (code `module_dockercart_blog`). */
	public const SETTING_KEY = 'module_dockercart_blog_post_autosave';

	/** Attribute a form tag opts into autosave with. */
	public const FORM_ATTRIBUTE = 'data-autosave';

	/**
	 * Is autosave enabled for the blog post form?
	 *
	 * Enabled by default: an installation that never touched the blog settings has
	 * no `module_dockercart_blog_post_autosave` row, and `Config::has()` is false
	 * for it.
	 */
	public static function enabled(\Config $config): bool {
		if (!$config->has(self::SETTING_KEY)) {
			return true;
		}

		return (int)$config->get(self::SETTING_KEY) === 1;
	}
}
