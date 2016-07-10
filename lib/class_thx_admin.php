<?php

class Thx_Admin {

	private function __construct () {}
	private function __clone () {}

	public static function serve () {
		$me = new self;
		$me->_add_hooks();
	}

	private function _add_hooks () {
		if (!is_admin()) return false;
		if (defined('DOING_AJAX') && DOING_AJAX) return false;

		add_action('admin_notices', array($this, 'dispatch_notices'));
		add_action('upfront-admin-general_settings-versions', array($this, 'version_info'));

		add_action('admin_menu', array($this, "add_menu_item"), 99);
	}

	/**
	 * Exposes the builder menu item in Upfront core menu
	 */
	public function add_menu_item () {
		$parent = class_exists('Upfront_Admin') && !empty(Upfront_Admin::$menu_slugs['main'])
			? Upfront_Admin::$menu_slugs['main']
			: false
		;
		if (empty($parent)) return false;

		$name = Thx_L10n::get('plugin_name');
		$name = !empty($name) ? $name : 'Builder';

		add_submenu_page(
			$parent,
			$name,
			$name,
			'manage_options',
			$parent . '-builder',
			array($this, "render_admin_page")
		);
	}

	/**
	 * Renders the Builder admin page
	 */
	public function render_admin_page () {
		if (!class_exists('Upfront_Thx_InitialPage_VirtualSubpage')) require_once(dirname(__FILE__) . '/class_thx_endpoint.php');
		Upfront_Thx_InitialPage_VirtualSubpage::out(false);
	}

	/**
	 * Outputs version info
	 */
	public function version_info () {
		$version = UpfrontThemeExporter::get_version();
		$version = !empty($version) ? $version : '0.0.0';

		$name = Thx_L10n::get('plugin_name');
		$name = !empty($name) ? $name : 'Builder';
		?>
<div class="upfront-debug-block">
	 <?php echo esc_html($name); ?> <span>V <?php echo esc_html($version); ?></span>
</div>
		<?php
	}

	/**
	 * The notices dispatch hub method.
	 * Each of the array values should be a single string which will be processed, wrapped in Ps and rendered.
	 */
	public function dispatch_notices () {
		$notices = array_filter(apply_filters('upfront-thx-admin_notices', array(
			$this->_permalink_setup_check_notice(),
		)));
		if (empty($notices)) return false;
		echo '<div class="error"><p>' .
			join('</p><p>', $notices) .
		'</p></div>';
	}

	private function _permalink_setup_check_notice () {
		if (get_option('permalink_structure')) return false;
		$msg = sprintf(
			__('Upfront Exporter requires Pretty Permalinks to work. Please enable them <a href="%s">here</a>', UpfrontThemeExporter::DOMAIN),
			admin_url('/options-permalink.php')
		);
		return $msg;
	}
}
