<?php

/**
 * Kickstart loads when the plugin is active,
 * but the active theme isn't Upfront child (or core).
 *
 * This is how we go ahead and set everything up for
 * builder being ready to go.
 */
class Thx_Kickstart {

	/**
	 * Constructor - never for the outside world.
	 */
	private function __construct () {}

	/**
	 * No public clones
	 */
	private function __clone () {}

	/**
	 * Public serving method
	 */
	public static function serve () {
		$me = new self;
		$me->_add_hooks();
		return $me;
	}

	/**
	 * Initialize and hook up to WP
	 */
	private function _add_hooks () {
		add_action('admin_notices', array($this, 'show_kickstart_notices'));
		add_action('wp_ajax_upfront-kickstart-start_building', array($this, 'json_start_building'));
	}

	/**
	 * JSON AJAX handler for kickstart build start action
	 */
	public function json_start_building () {
		// Check user prerequisites
		if (!current_user_can('manage_options')) wp_send_json_error(__('No way.', UpfrontThemeExporter::DOMAIN));

		// Can we even do this?
		if (!$this->_has_upfront()) wp_send_json_error(__('Core not available.', UpfrontThemeExporter::DOMAIN));

		// We can. Yay.
		switch_theme('upfront');

		wp_send_json_success(admin_url('admin.php?page=upfront-builder'));
	}

	/**
	 * Shows the builder kickstart notice
	 */
	public function show_kickstart_notices () {
		if (!is_admin() || upfront_thx_is_current_theme_upfront_related()) return false;
		if (!current_user_can('manage_options')) return false; // Only proper level users

		if (!class_exists('Thx_Sanitize')) require_once (dirname(__FILE__) . '/class_thx_sanitize.php');
		if (!class_exists('Thx_Template')) require_once (dirname(__FILE__) . '/class_thx_template.php');

		$tpl = Thx_Template::plugin();

		if ($this->_has_upfront()) {
			load_template($tpl->path('kickstart_ready'));
			wp_enqueue_script('kickstart', $tpl->url('js/kickstart.js'), array('jquery'));
			wp_localize_script('kickstart', '_thx_kickstart', array(
				'general_error' => __('Ooops, something went wrong.', UpfrontThemeExporter::DOMAIN),
				'success_msg' => __('All good, please hold on while we redirect you to your Builder page.', UpfrontThemeExporter::DOMAIN),
			));
		} else {
			load_template($tpl->path('kickstart_not_ready'));
		}
	}

	/**
	 * Checks if we have Upfront core available
	 *
	 * @return boolean
	 */
	private function _has_upfront () {
		return upfront_exporter_has_upfront_core();
	}
}
