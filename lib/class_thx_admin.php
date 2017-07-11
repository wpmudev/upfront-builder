<?php

/**
 * Exporter admin class
 */
class Thx_Admin {

	const ERROR_DEFAULT = 1;
	const ERROR_PARAM = 2;
	const ERROR_PERMISSION = 3;

	/**
	 * Constructor - never for the outside world
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
	}

	/**
	 * Initialize and hook up to WP
	 */
	private function _add_hooks () {
		if (!is_admin()) return false;
		if (defined('DOING_AJAX') && DOING_AJAX) return false;

		add_action('admin_notices', array($this, 'dispatch_notices'));
		add_action('upfront-admin-general_settings-versions', array($this, 'version_info'));

		add_action('admin_menu', array($this, "add_menu_item"), 99);

		add_filter('upfront-admin-admin_notices', array($this, 'process_core_notices'));

		add_action('admin_init', array($this, 'initialize_dashboard'));

		add_filter('plugin_action_links_' . THX_PLUGIN_BASENAME, array($this, 'add_settings_link'));
	}

	/**
	 * Adds settings link to plugins menu list item
	 *
	 * @param array $links List of plugin action links
	 *
	 * @return array Augmented links
	 */
	public function add_settings_link ($links) {
		$links[] = sprintf(
			'<a href="%s">%s</a>',
			esc_url(admin_url('admin.php?page=upfront-builder')),
			__('Settings', UpfrontThemeExporter::DOMAIN)
		);
		return $links;
	}

	/**
	 * Initialize dashboard and set it up to render on proper pages
	 */
	public function initialize_dashboard () {
		if (file_exists(dirname(__FILE__) . '/external/dashboard-notice/wpmudev-dash-notification.php')) {
			global $wpmudev_notices;
			if (!is_array($wpmudev_notices)) $wpmudev_notices = array();
			$wpmudev_notices[] = array(
				'id' => 1107287,
				'name' => 'Upfront Builder',
				'screens' => array(
					'upfront_page_upfront-builder',
				),
			);
			require_once (dirname(__FILE__) . '/external/dashboard-notice/wpmudev-dash-notification.php');
		}
	}

	/**
	 * Processes the core notices and shifts off the ones that
	 * don't make sense anymore.
	 *
	 * Hooks up to `upfront-admin-admin_notices` core filter.
	 *
	 * @param array $notices Admin notices hash
	 *
	 * @return array
	 */
	public function process_core_notices ($notices) {
		if (!empty($notices['core_activation'])) unset($notices['core_activation']);
		return $notices;
	}

	/**
	 * Exposes the builder menu item in Upfront core menu
	 */
	public function add_menu_item () {
		if (!class_exists('Upfront_Permissions')) return false; // Upfront not available
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

		// Dispatch theme download
		if (!empty($_GET['page']) && $parent . '-builder' === $_GET['page']) {
			if (!empty($_GET['action']) && 'download' === $_GET['action']) return $this->_download_theme();
		}
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
			'editor_base' => esc_url(Upfront_Thx_Builder_VirtualPage::get_url(
				Upfront_Thx_Builder_VirtualPage::get_initial_url()
			)),
			'action_slug' => Upfront_Thx_Builder_VirtualPage::get_initial_url(),
			'admin_ajax' => admin_url('admin-ajax.php'),
			'l10n' => array(
				'oops' => __('Oops, something went wrong with processing your request.', UpfrontThemeExporter::DOMAIN),
				'start_building' => __('Start Building', UpfrontThemeExporter::DOMAIN),
				'checking' => __('Checking...', UpfrontThemeExporter::DOMAIN),
				'creating' => __('Creating...', UpfrontThemeExporter::DOMAIN),
				'select_media' => __('Select or Upload Media Of Your Chosen Persuasion', UpfrontThemeExporter::DOMAIN),
				'use_media' => __('Use this media', UpfrontThemeExporter::DOMAIN),
				'loading' => __('Loading data...', UpfrontThemeExporter::DOMAIN),
			),
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
	 * Packs up and serves a theme for download
	 */
	private function _download_theme () {
		// Check prerequisites
		if (!Upfront_Permissions::current(Upfront_Permissions::BOOT)) return $this->_error_redirect(self::ERROR_PERMISSION);
		if (!class_exists('ZipArchive')) return $this->_error_redirect();

		$data = stripslashes_deep($_GET);

		// Verify nonce existence
		$nonce = !empty($data['nonce']) ? $data['nonce'] : false;
		if (empty($nonce)) return $this->_error_redirect(self::ERROR_PARAM);

		// Load dependencies
		if (!class_exists('Thx_Sanitize')) require_once (dirname(__FILE__) . '/class_thx_sanitize.php');
		if (!class_exists('Thx_Fs')) require_once (dirname(__FILE__) . '/class_thx_fs.php');

		// Validate theme
		$slug = !empty($data['theme']) ? $data['theme'] : false;
		$theme = wp_get_theme($slug);
		$name = $theme->get('Name');
		if (empty($slug) || empty($name)) return $this->_error_redirect(self::ERROR_PARAM);

		// Verify nonce
		$nonce_action = 'download-' . $theme->get_stylesheet();
		if (!wp_verify_nonce($nonce, $nonce_action)) return $this->_error_redirect(self::ERROR_PARAM);

		// Alright, so we're good. Let's pack this up

		$fs = Thx_Fs::get($slug);
		$root = $fs->get_root_path();

		$archive_name = basename($root);
		$prefix = trailingslashit(dirname($root));

		$version = $theme->get('Version');
		$version = !empty($version) ? $version : '0.0.0';

		$list = $fs->ls();
		if (empty($list))  return $this->_error_redirect();

		$file = tempnam("tmp", "zip");
		$zip = new ZipArchive();
		if (true !== $zip->open($file, ZipArchive::OVERWRITE))  return $this->_error_redirect();

		foreach ($list as $item) {
			$relative = preg_replace('/^' . preg_quote($prefix, '/') . '/', '', $item);
			if (is_dir($item)) $zip->addEmptyDir($relative);
			else $zip->addFile($item, $relative);
		}

		// Close and send to users
		$zip->close();
		header('Content-Type: application/zip');
		header('Content-Length: ' . filesize($file));
		header('Content-Disposition: attachment; filename="' . $archive_name . '-' . $version . '.zip"');
		readfile($file);
		unlink($file);
		die;
	}

	/**
	 * Error redirection helper
	 *
	 * @param int $error Optional error type
	 */
	private function _error_redirect ($error=false) {
		$redirection = remove_query_arg(array(
			'nonce',
			'action',
		));
		$error = !empty($error) && is_numeric($error) ? (int)$error : self::ERROR_DEFAULT;
		wp_safe_redirect(add_query_arg('error', $error, $redirection));
		die;
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

	/**
	 * Dispatch permalink setup notice
	 *
	 * @return string|false Notice
	 */
	private function _permalink_setup_check_notice () {
		if (get_option('permalink_structure')) return false;
		$msg = sprintf(
			__('Upfront Exporter requires Pretty Permalinks to work. Please enable them <a href="%s">here</a>', UpfrontThemeExporter::DOMAIN),
			admin_url('/options-permalink.php')
		);
		return $msg;
	}
}
