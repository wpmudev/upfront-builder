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
		if (!Upfront_Permissions::current(Upfront_Permissions::BOOT)) return false;

		$parent = class_exists('Upfront_Admin') && !empty(Upfront_Admin::$menu_slugs['main'])
			? Upfront_Admin::$menu_slugs['main']
			: false
		;
		if (empty($parent)) return false;

		$name = Thx_L10n::get('plugin_name');
		$name = !empty($name) ? $name : 'Builder';

		$full_page_suffix = add_submenu_page(
			$parent,
			$name,
			$name,
			'manage_options',
			$parent . '-builder',
			array($this, "render_admin_page")
		);
		Upfront_Admin::$menu_slugs['builder'] = 'upfront-builder';

		add_action("load-{$full_page_suffix}", array($this, 'set_up_dependencies'));
	}

	/**
	 * Add admin dependencies load action.
	 *
	 * Admin page load hook handler.
	 */
	public function set_up_dependencies () {
		if (!Upfront_Permissions::current(Upfront_Permissions::BOOT)) wp_die("Nope.");
		add_action('admin_enqueue_scripts', array($this, 'enqueue_dependencies'));
	}

	/**
	 * Set up admin page dependencies.
	 *
	 * Admin page enqueue scripts handler, registered in `set_up_dependencies()` method
	 */
	public function enqueue_dependencies () {
		if (!Upfront_Permissions::current(Upfront_Permissions::BOOT)) wp_die("Nope.");

		if (!class_exists('Thx_Sanitize')) require_once (dirname(__FILE__) . '/class_thx_sanitize.php');
		if (!class_exists('Thx_Template')) require_once (dirname(__FILE__) . '/class_thx_template.php');

		if (!class_exists('Upfront_Thx_Builder_VirtualPage')) require_once (dirname(__FILE__) . '/class_thx_endpoint.php');

		$tpl = Thx_Template::plugin();

		wp_enqueue_style('create_edit', $tpl->url('css/create_edit.css'));

		wp_enqueue_script('create_edit', $tpl->url('js/create_edit.js'), array('jquery'));
		wp_localize_script('create_edit', '_thx', array(
			'editor_base' => esc_url(Upfront_Thx_Builder_VirtualPage::get_url(Upfront_Thx_Builder_VirtualPage::get_initial_url())),
			'admin_ajax' => admin_url('admin-ajax.php'),
			'l10n' => array(
				'oops' => __('Oops, something went wrong with processing your request.', UpfrontThemeExporter::DOMAIN),
			)
		));

		wp_enqueue_media();
	}

	/**
	 * Renders the Builder admin page
	 */
	public function render_admin_page () {
		if (!Upfront_Permissions::current(Upfront_Permissions::BOOT)) wp_die("Nope.");

		if (!class_exists('Thx_Sanitize')) require_once (dirname(__FILE__) . '/class_thx_sanitize.php');
		if (!class_exists('Thx_Template')) require_once (dirname(__FILE__) . '/class_thx_template.php');

		load_template(Thx_Template::plugin()->path('create_edit'));
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
