<?php
/*
Plugin Name: Upfront Builder
Plugin URI: http://premium.wpmudev.com/
Description: The fastest, most visual, way to build WordPress themes. Now anyone can design, build, export, share, and sell WordPress themes.
Version: 1.1.6
Author: WPMU DEV
Text Domain: upfront_thx
Author URI: http://premium.wpmudev.com
License: GPLv2 or later
WDP ID: 1107287
*/

/*
Copyright 2009-2014 Incsub (http://incsub.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License (Version 2 - GPLv2) as published by
the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

require_once dirname(__FILE__) . '/lib/util.php';
require_once dirname(__FILE__) . '/lib/class_thx_l10n.php';

define('THX_BASENAME', basename(dirname(__FILE__)));

/**
 * Main plugin class
 */
class UpfrontThemeExporter {

	const DOMAIN = 'upfront_thx';

	/**
	 * Just basic, context-free bootstrap here.
	 */
	private function __construct() {}

	/**
	 * Boot point.
	 */
	public static function dispatch () {
		// Check if we have proper core version support
		if (!upfront_exporter_has_upfront_version('1.4')) {
			return self::_serve_compat();
		}

		// Check if we have Upfront-related theme active
		if (upfront_thx_is_current_theme_upfront_related()) {
			return self::_serve_exporter();
		}

		// No? Serve kickstart
		return self::_serve_kickstart();
	}

	/**
	 * Serves exporter compat
	 *
	 * @return object
	 */
	private static function _serve_compat () {
		require_once dirname(__FILE__) . '/lib/class_thx_compat.php';
		return Thx_Compat::serve();
	}

	/**
	 * Serves exporter kickstart
	 *
	 * @return object
	 */
	private static function _serve_kickstart () {
		require_once dirname(__FILE__) . '/lib/class_thx_kickstart.php';
		return Thx_Kickstart::serve();
	}

	/**
	 * Serves exporter, ready to work
	 *
	 * @return object
	 */
	private static function _serve_exporter () {
		$me = new self;
		$me->_add_hooks();
		return $me;
	}

	/**
	 * This is where we dispatch the context-sensitive/global hooks.
	 */
	private function _add_hooks () {
		$this->_add_exposed_hooks();
		// Just dispatch specific scope hooks.
		if (upfront_exporter_is_running()) {
			$this->_add_exporter_hooks();
		}
		$this->_add_global_hooks();
	}

	/**
	 * These hooks will *always* trigger.
	 * No need to wait for the rest of Upfront, set our stuff up right now.
	 */
	private function _add_global_hooks () {
		/*
		// Not adding the toolbar item since the admin page move
		add_action('upfront-admin_bar-process', array($this, 'add_toolbar_item'), 10, 2);
		*/

		add_filter('extra_theme_headers', array($this, 'process_extra_child_theme_headers'));

		if (is_admin() && !(defined('DOING_AJAX') && DOING_AJAX)) {
			require_once(dirname(__FILE__) . '/lib/class_thx_admin.php');
			Thx_Admin::serve();
		}
		$this->_load_textdomain();

		// Add shared Upfront/Exporter JS resources
		add_action('upfront-core-inject_dependencies', array($this, 'add_shared_scripts'));
		add_filter('upfront_data', array($this, 'add_shared_data'));

		// Shared - context mode popup
		add_action('wp_ajax_upfront_thx-mode_context-skip', array($this, 'json_context_mode_skip'));
	}

	/**
	 * Handle data augmentation for shared resource
	 *
	 * @param array $data Data gathered this far
	 *
	 * @return array Augmented data
	 */
	public function add_shared_data ($data) {
		$user_id = get_current_user_id();

		$data['exporter_shared'] = array(
			'context_mode' => (int)get_user_option('upfront-context_mode_skip', $user_id),
		);

		return $data;
	}

	/**
	 * Stores user preference regarding context mode popup
	 *
	 * AJAX request handler
	 */
	public function json_context_mode_skip () {
		$result = array('error' => 1);
		if (!Upfront_Permissions::current(Upfront_Permissions::BOOT)) return wp_send_json($result);

		$user_id = get_current_user_id();
		if (empty($user_id) || !is_numeric($user_id)) return wp_send_json($result);

		$skip = (int)get_user_option('upfront-context_mode_skip', $user_id);
		if ($skip) return wp_send_json($result);

		update_user_option($user_id, 'upfront-context_mode_skip', 1, true);
		wp_send_json(array(
			'error' => 0,
		));
	}

	/**
	 * This is where we inject shared scripts
	 */
	public function add_shared_scripts () {
		$deps = Upfront_CoreDependencies_Registry::get_instance();
		$deps->add_script(plugins_url('app/shared.js', __FILE__));
		/*
		foreach ($script_urls as $url) {
			$deps->add_script($url);
		}
		 */
	}

	/**
	 * Introduces additional theme headers, supported by exporter
	 *
	 * Filters the `extra_theme_headers` hook
	 *
	 * @param array $headers Existing headers
	 *
	 * @return array Augmented headers
	 */
	public function process_extra_child_theme_headers ($headers=array()) {
		if (!is_array($headers)) return $headers;

		if (!in_array('WDP ID', $headers)) $headers[] = 'WDP ID';
		if (!in_array('License', $headers)) $headers[] = 'License';
		if (!in_array('License URI', $headers)) $headers[] = 'License URI';

		return $headers;
	}

	/**
	 * These hooks will *always* trigger even when doing AJAX either via admin or builder
	 */
	private function _add_exposed_hooks () {
		if ( is_admin() || upfront_exporter_is_running() ) {
			require_once(dirname(__FILE__) . '/lib/class_thx_exposed.php');
			Thx_Exposed::serve();
		}
	}

	/**
	 * Loads translations text domain for the plugin
	 */
	private function _load_textdomain () {
		load_plugin_textdomain(self::DOMAIN, false, dirname(plugin_basename(__FILE__)) . '/languages');
	}

	/**
	 * Now, this is exporter-specific.
	 * Wait until Upfront is ready and set us up.
	 */
	private function _add_exporter_hooks () {
		require_once(dirname(__FILE__) . '/lib/class_thx_exporter.php');
		add_action('upfront-core-initialized', array('Thx_Exporter', 'serve'));
	}

	/**
	 * Adds the builder toolbar item
	 *
	 * Deprecated since v0.9
	 *
	 * @param object $toolbar Toolbar object
	 * @param array $item Upfront item
	 */
	public function add_toolbar_item ($toolbar, $item) {
		return false; // Deprecated since v0.9

		if (!Upfront_Permissions::current(Upfront_Permissions::BOOT)) return false;
		if (empty($item['meta'])) return false; // Only actual boot item has meta set

		$child = upfront_thx_is_current_theme_upfront_child();
		$create_title = __('Create New Theme', self::DOMAIN);
		$main_title = (bool)$child
			? __('Builder', self::DOMAIN)
			: $create_title
		;
		$root_item_id = 'upfront-create-theme';

		$toolbar->add_menu(array(
			'id' => $root_item_id,
			'title' => '<span style="top:2px" class="ab-icon dashicons-hammer"></span><span class="ab-label">' . $main_title . '</span>',
			'href' => admin_url('admin.php?page=upfront-builder'),
			'meta' => array( 'class' => 'upfront-create_theme' ),
		));

		if ((bool)$child) {
			$toolbar->add_menu(array(
				'parent' => $root_item_id,
				'id' => 'upfront-builder-current_theme',
				'title' => __('Edit current theme', self::DOMAIN),
				'href' => home_url('/' . UpfrontThemeExporter::get_root_slug() . '/' . $child),
			));
			$toolbar->add_menu(array(
				'parent' => $root_item_id,
				'id' => 'upfront-builder-create_theme',
				'title' => $create_title,
				'href' => admin_url('admin.php?page=upfront-builder'),
			));
		}
	}

	/**
	 * Get the root slug in endpoint-agnostic manner
	 *
	 * @return string Root slug
	 */
	public static function get_root_slug () {
		return class_exists('Upfront_Thx_Builder_VirtualPage')
			? Upfront_Thx_Builder_VirtualPage::SLUG
			: 'create_new'
		;
	}

	/**
	 * Fetches (and caches) the plugin version number
	 *
	 * @return string Plugin version number
	 */
	public static function get_version () {
		static $version;
		if (!empty($version)) return $version;

		$data = get_plugin_data(__FILE__);
		if (!empty($data['Version'])) $version = $data['Version'];

		return $version;
	}

}

UpfrontThemeExporter::dispatch();
