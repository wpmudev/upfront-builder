<?php
/*
Plugin Name: Upfront Theme Exporter
Plugin URI: http://premium.wpmudev.com/
Description: Exports upfront page layouts to theme.
Version: 0.9.0
Author: WPMU DEV
Text Domain: upfront_thx
Author URI: http://premium.wpmudev.com
License: GPLv2 or later
WDP ID:
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

class UpfrontThemeExporter {

	const DOMAIN = 'upfront_thx';

	/**
	 * Just basic, context-free bootstrap here.
	 */
	private function __construct() {}

	/**
	 * Boot point.
	 */
	public static function serve () {
		$me = new self;
		$me->_add_hooks();
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

		if (is_admin() && !(defined('DOING_AJAX') && DOING_AJAX)) {
			require_once(dirname(__FILE__) . '/lib/class_thx_admin.php');
			Thx_Admin::serve();
		}
		$this->_load_textdomain();
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
	 * @param object $toolbar
	 * @param array $item
	 */
	public function add_toolbar_item ($toolbar, $item) {
		return false; // Deprecated since v0.9

		if (!Upfront_Permissions::current(Upfront_Permissions::BOOT)) return false;
		if (empty($item['meta'])) return false; // Only actual boot item has meta set

		$child = upfont_thx_is_current_theme_upfront_child();
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
			'meta' => array( 'class' => 'upfront-create_theme' )
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

UpfrontThemeExporter::serve();
