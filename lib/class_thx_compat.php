<?php

/**
 * Deals with compatibility notices
 */
class Thx_Compat {

	private function __construct () {}
	private function __clone () {}

	public static function serve () {
		$me = new self;
		$me->_add_hooks();
		return $me;
	}

	private function _add_hooks () {
		add_action('admin_notices', array($this, 'show_compat_notices'));
	}

	public function show_compat_notices () {
		if (!is_admin()) return false;
		if (!current_user_can('manage_options')) return false; // Only proper level users

		if (!class_exists('Thx_Sanitize')) require_once (dirname(__FILE__) . '/class_thx_sanitize.php');
		if (!class_exists('Thx_Template')) require_once (dirname(__FILE__) . '/class_thx_template.php');

		$tpl = Thx_Template::plugin();

		if (upfront_exporter_has_upfront_core()) {
			load_template($tpl->path('compat_core_version'));
		} else {
			load_template($tpl->path('kickstart_not_ready'));
		}
	}
}
