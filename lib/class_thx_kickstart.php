<?php

/**
 * Kickstart loads when the plugin is active,
 * but the active theme isn't Upfront child (or core).
 *
 * This is how we go ahead and set everything up for
 * builder being ready to go.
 */
class Thx_Kickstart {

	const FLAG_DISMISS = 'upfront-thx-kickstart-dismiss';

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
		add_action('wp_ajax_upfront-kickstart-go_away', array($this, 'json_go_away'));

		add_filter('plugin_row_meta', array($this, 'augment_plugin_meta'), 10, 2);
	}

	/**
	 * Augments plugin meta info row
	 *
	 * Used to render core missing notice when there's no upfront active
	 *
	 * @param array $meta Plugin meta row
	 * @param string $plugin Plugin basename
	 *
	 * @return array Augmented plugin meta row
	 */
	public function augment_plugin_meta ($meta, $plugin) {
		if (THX_PLUGIN_BASENAME !== $plugin) return $meta;

		$icon = '<span style="display:block;float:left;margin-right:.2em">' .
			$this->get_svg() .
		'</span>';
		$msg = $this->_has_upfront()
			? __('%s Activate an Upfront theme to use Builder', UpfrontThemeExporter::DOMAIN)
			: __('%s You need to have Upfront core present in order to use Builder', UpfrontThemeExporter::DOMAIN)
		;
		array_unshift($meta, sprintf($msg, $icon));

		return $meta;
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
	 * JSON AJAX handler for permanent notice dismissal
	 */
	public function json_go_away () {
		// Check user prerequisites
		if (!current_user_can('manage_options')) wp_send_json_error(__('No way.', UpfrontThemeExporter::DOMAIN));

		update_option(self::FLAG_DISMISS, 'yes');

		wp_send_json_success();
	}

	/**
	 * Cleanup method
	 *
	 * Used on plugin deactivation
	 *
	 * @return bool Status
	 */
	public static function clean_up () {
		return !!delete_option(self::FLAG_DISMISS);
	}

	/**
	 * Shows the builder kickstart notice
	 */
	public function show_kickstart_notices () {
		if (!is_admin() || upfront_thx_is_current_theme_upfront_related()) return false;
		if (!current_user_can('manage_options')) return false; // Only proper level users

		if (get_option(self::FLAG_DISMISS)) return false; // Dismissed

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

	/**
	 * Returns graphic to be used in plugin notice
	 *
	 * The notice appears in admin plugins area, when Builder is
	 * active, but no Upfront theme is currently active.
	 *
	 * @return string SVG to render
	 */
	public function get_svg () {
		return <<<EOSVG
<svg width="17px" height="17px" viewBox="0 0 17 17" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
    <!-- Generator: Sketch 43.2 (39069) - http://www.bohemiancoding.com/sketch -->
    <desc>Created with Sketch.</desc>
    <defs></defs>
    <g id="-misc." stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
        <g id="Dynamic-group" transform="translate(-6.000000, -6.000000)" fill="#D60A2D">
            <g id="Group-14">
                <path d="M14.25,6 C15.3958391,6 16.4664664,6.2148416 17.4619141,6.64453125 C18.4573617,7.0742209 19.3310509,7.6650353 20.0830078,8.41699219 C20.8349647,9.16894907 21.4257791,10.0426383 21.8554688,11.0380859 C22.2851584,12.0335336 22.5,13.1041609 22.5,14.25 C22.5,15.3958391 22.2851584,16.4664664 21.8554688,17.4619141 C21.4257791,18.4573617 20.8349647,19.3310509 20.0830078,20.0830078 C19.3310509,20.8349647 18.4573617,21.4257791 17.4619141,21.8554688 C16.4664664,22.2851584 15.3958391,22.5 14.25,22.5 C13.1041609,22.5 12.0335336,22.2851584 11.0380859,21.8554688 C10.0426383,21.4257791 9.16894907,20.8349647 8.41699219,20.0830078 C7.6650353,19.3310509 7.0742209,18.4573617 6.64453125,17.4619141 C6.2148416,16.4664664 6,15.3958391 6,14.25 C6,13.1041609 6.2148416,12.0335336 6.64453125,11.0380859 C7.0742209,10.0426383 7.6650353,9.16894907 8.41699219,8.41699219 C9.16894907,7.6650353 10.0426383,7.0742209 11.0380859,6.64453125 C12.0335336,6.2148416 13.1041609,6 14.25,6 Z M15.0878906,18.8476562 L15.0878906,13.5195312 C15.0878906,13.4335933 15.0592451,13.3619795 15.0019531,13.3046875 C14.9446612,13.2473955 14.8802087,13.21875 14.8085938,13.21875 L13.7128906,13.21875 C13.6269527,13.21875 13.5553388,13.2473955 13.4980469,13.3046875 C13.4407549,13.3619795 13.4121094,13.4335933 13.4121094,13.5195312 L13.4121094,18.8476562 C13.4121094,18.9192712 13.4407549,18.9837237 13.4980469,19.0410156 C13.5553388,19.0983076 13.6269527,19.1269531 13.7128906,19.1269531 L14.8085938,19.1269531 C14.8802087,19.1269531 14.9446612,19.0983076 15.0019531,19.0410156 C15.0592451,18.9837237 15.0878906,18.9192712 15.0878906,18.8476562 Z M14.25,12.0371094 C14.5221368,12.0371094 14.7584625,11.93685 14.9589844,11.7363281 C15.1595062,11.5358063 15.2597656,11.2994805 15.2597656,11.0273438 C15.2597656,10.755207 15.1630869,10.5188812 14.9697266,10.3183594 C14.7763662,10.1178375 14.5364598,10.0175781 14.25,10.0175781 C13.9778632,10.0175781 13.7451182,10.1178375 13.5517578,10.3183594 C13.3583975,10.5188812 13.2617188,10.755207 13.2617188,11.0273438 C13.2617188,11.2994805 13.3583975,11.5358063 13.5517578,11.7363281 C13.7451182,11.93685 13.9778632,12.0371094 14.25,12.0371094 Z" id="#"></path>
            </g>
        </g>
    </g>
</svg>
EOSVG;
	}
}
