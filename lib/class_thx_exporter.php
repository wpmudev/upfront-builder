<?php

define('UF_THX_TMP_SWITCH', true); // tmp
class Thx_Exporter {

	const TEMP_STYLES_KEY = 'uf-thx-temporary_styles';

	private $_plugin_dir_url;
	private $_plugin_dir;

	private $_fs;
	private $_json;
	private $_theme_settings;

	private $_theme;

	protected $_global_regions = array();
	protected $_global_sideregions = array();

	private $_theme_exports_images = true; // Export images by default, for legacy themes

	/**
	 * Just basic, context-free bootstrap here.
	 */
	private function __construct() {
		$this->_plugin_dir = dirname(dirname(__FILE__));
		$this->_plugin_dir_url = plugin_dir_url(dirname(__FILE__));

		$this->_theme = upfront_exporter_get_stylesheet();

		require_once (dirname(__FILE__) . '/class_thx_sanitize.php');
		require_once (dirname(__FILE__) . '/class_thx_template.php');

		require_once (dirname(__FILE__) . '/class_thx_fs.php');
		$this->_fs = Thx_Fs::get($this->_theme);

		$this->_set_up_theme_settings();

		require_once (dirname(__FILE__) . '/class_thx_json.php');
		$this->_json = new Thx_Json;


		require_once(dirname(__FILE__) . '/class_thx_endpoint.php');
		if (class_exists('Upfront_Thx_Builder_VirtualPage')) Upfront_Thx_Builder_VirtualPage::serve();
	}

	private function _set_up_theme_settings () {
		$settings_file = empty($this->_theme) || in_array($this->_theme, array('upfront', 'theme'))
			? $this->_fs->get_path('settings.php')
			: $this->_fs->get_path('settings.php', false)
		;

		$settings = new Upfront_Theme_Settings($settings_file);
		$this->_theme_settings = $settings;

		// Override the child theme settings
		$theme = Upfront_ChildTheme::get_instance();
		if (!empty($theme) && $theme instanceof Upfront_ChildTheme) $theme->set_theme_settings($settings);
	}

	public static function serve () {
		$me = new self;
		$me->_add_hooks();
	}

	/**
	 * Set up exporter context in Upfront core boot hook handler.
	 * This is where all the exporter context-sensitive stuff happens.
	 * Fires when the rest of the Upfront is already initialized.
	 */
	private function _add_hooks () {

		// Clean up the temporary styles on each load, if not doing AJAX
		if (!is_admin() && !(defined('DOING_AJAX') && DOING_AJAX) && is_user_logged_in()) update_option(self::TEMP_STYLES_KEY, array());

		add_filter('stylesheet_directory', array($this, 'process_stylesheet_directory'), 100);

		//add_action('wp_footer', array($this, 'inject_dependencies'), 100);
		add_action('upfront-core-inject_dependencies', array($this, 'inject_dependencies'), 100);
		add_filter('upfront_data', array($this, 'add_data'));
		add_action('wp_enqueue_scripts', array($this,'add_styles'));

		// Allow particular layout override from GET query
		add_filter('upfront-entity_resolver-entity_ids', array($this, 'augment_layout_load_from_get_query'), 10, 2);

		$ajaxPrefix = 'wp_ajax_upfront_thx-';

		add_action($ajaxPrefix . 'create-theme', array($this, 'json_create_theme'));
		add_action($ajaxPrefix . 'update-theme', array($this, 'json_update_theme'));
		add_action($ajaxPrefix . 'get-themes', array($this, 'json_get_themes'));

		add_action($ajaxPrefix . 'export-layout', array($this, 'json_export_layout'));

		//add_action($ajaxPrefix . 'export-post-layout', array($this, 'json_export_post_layout'));
		//add_action($ajaxPrefix . 'export-part-template', array($this, 'json_export_part_template'));

		add_action($ajaxPrefix . 'export-element-styles', array($this, 'json_export_element_styles'));
		add_action($ajaxPrefix . 'delete-element-styles', array($this, 'json_delete_element_styles'));

		add_filter('upfront_theme_layout_cascade', array($this, 'get_theme_layout_cascade'), 10, 2); // Used for ThisPost - this will be going out
		add_filter('upfront_theme_postpart_templates_cascade', array($this, 'get_theme_postpart_templates_cascade'), 10, 2);

		add_filter('upfront_prepare_theme_styles', '__return_empty_string', 15);
		add_filter('upfront_prepare_typography_styles', '__return_empty_string', 15);
		add_filter('upfront_load_layout_from_database', '__return_false');

		add_action('upfront_update_theme_colors', array($this, 'update_theme_colors'));
		add_action('upfront_update_post_image_variants', array($this, 'update_post_image_variants'));
		add_action('upfront_update_theme_fonts', array($this, 'update_theme_fonts'));
		add_action('upfront_update_responsive_settings', array($this, 'update_responsive_settings'));

		add_action('upfront_update_button_presets', array($this, 'update_button_presets'));
		add_action('init', array($this, 'dispatch_preset_handling'), 99);

		add_action('upfront_get_stylesheet_directory', array($this, 'get_stylesheet_directory'));
		add_action('upfront_get_stylesheet', array($this, 'get_stylesheet'));

		add_action('upfront_upload_icon_font', array($this, 'upload_icon_font'));
		add_action('upfront_update_active_icon_font', array($this, 'update_active_icon_font'));

		// Intercept theme images loading and verify that the destination actually exists
		add_action('wp_ajax_upfront-media-list_theme_images', array($this, 'check_theme_images_destination_exists'), 5);

		add_filter('upfront-this_post-unknown_post', array($this, 'prepare_preview_post'), 10, 2);
		add_filter('upfront-post_data-unknown_post', array($this, 'prepare_preview_post'), 10, 2);

		// These things are currently in Upfront core, but should and will go out
		add_action('wp_ajax_upfront_list_theme_layouts', array($this, 'json_get_exported_layouts'), 9);
		add_action('wp_ajax_upfront_list_available_layout', array($this, 'json_get_available_layouts'), 9);
	}

	/**
	 * Fetches the list of currently exported layouts (for the current theme)
	 * and outputs it as JSON response.
	 */
	public function json_get_exported_layouts () {
		$layouts_path = $this->_fs->get_path(Thx_Fs::PATH_LAYOUTS);
		if (empty($layouts_path)) $this->_json->error_msg('Invalid layouts path');

		$layout_files = glob("{$layouts_path}/*.php");
		$layouts = array();

		foreach ($layout_files as $file) {
			$raw_layout = pathinfo($file, PATHINFO_FILENAME);
			$raw = explode('-', $raw_layout, 2);

			if (empty($raw[0])) continue;
			if (!in_array($raw[0], array('archive', 'single'))) continue;

			$layout = array(
				'label' => '',
				'layout' => array(
					'type' => $raw[0],
				),
			);
			$type_basename = 'archive' === $raw[0] ? __('Archive %s', UpfrontThemeExporter::DOMAIN) : __('Single %s', UpfrontThemeExporter::DOMAIN);
			$type_name = '';

			if (!empty($raw[1])) {
				$type_name = preg_match('/[-_]/', $raw[1])
					? sprintf('(%s)', $raw[1])
					: ucfirst($raw[1])
				;
				// Add a more specific layout entry
				$layout['layout']['item'] = $raw[0] . '-' . $raw[1];
			} else $type_name = __('(generic)', UpfrontThemeExporter::DOMAIN);

			if (empty($layout['layout']['specificity'])) $layout['layout']['specificity'] = $raw_layout;

			$label = Upfront_EntityResolver::layout_to_name($layout['layout']);
			$layout['label'] = !empty($label)
				? $label
				: sprintf($type_basename, $type_name)
			;

			$layouts[] = $layout;
		}

		$this->_json->out(array(
			'data' => $layouts,
		));
	}

	/**
	 * Fetches the list of all Upfront-available layouts
	 * and outputs a JSON reponse.
	 */
	public function json_get_available_layouts () {
		// Predefined layouts
		$layouts = Upfront_Layout::get_default_layouts();

		// add singular post type
		$post_types = get_post_types(array(
			'public' => true,
			'show_ui' => true,
		), 'objects');
		foreach ($post_types as $post_type) {
			$layouts['single-' . $post_type->name] = array(
				'layout' => array(
					'item' => 'single-' . $post_type->name,
					'type' => 'single'
				)
			);
		}

		// add taxonomy archive
		$taxonomies = get_taxonomies(array(
			'public' => true,
			'show_ui' => true
		), 'objects');
		foreach ($taxonomies as $taxonomy) {
			$layouts['archive-' . $taxonomy->name] = array(
				'layout' => array(
					'item' => 'archive-' . $taxonomy->name,
					'type' => 'archive'
				)
			);
		}

		// Now we have a list of layouts. Let's add some additional info, such as
		// human-friendly labels and whether there's any changes in the DB for each of them
		$db_layouts = Upfront_Layout::get_db_layouts();

		foreach ($layouts as $idx => $layout) {
			if (empty($layout['label'])) {
				$label = Upfront_EntityResolver::layout_to_name($layout['layout']);
				if (!empty($label)) $layout['label'] = $label;
				else continue;
			}
			$layout['saved'] = in_array($idx, $db_layouts);

			$layouts[$idx] = $layout;
		}

		$this->_json->out(array(
			'data' => $layouts
		));
	}

	/**
	 * Specific layout loading from the GET query.
	 * Filters the entity resolver results to apply query override.
	 *
	 * @param array $ids
	 * @param array $cascade
	 *
	 * @return array Augmented cascade
	 */
	public function augment_layout_load_from_get_query ($ids, $cascade) {
		$get = stripslashes_deep($_GET);

		$layout = !empty($get['layout']) ? explode('-', $get['layout']) : false;
		if (!empty($layout)) {
			$type = array_shift($layout);
			$item_part = array_shift($layout);
			$rest = join('-', $layout);

			if (!empty($type)) {
				// If we have stuff here, then reset the parsed IDs.
				$ids = array();

				if (!empty($type) && empty($get['type'])) $get['type'] = $type;
				if (!empty($item_part) && empty($get['item'])) $get['item'] = "{$type}-{$item_part}";
				if (!empty($rest) && empty($get['specificity'])) $get['specificity'] = "{$type}-{$item_part}-{$rest}";
			}

		}

		foreach (array('item', 'type', 'specificity') as $key) {
			if (!empty($get[$key])) $ids[$key] = $get[$key];
		}
		return $ids;
	}

	/**
	 * Dispatch preset handling.
	 * Intercepts particular preset server save/delete operations and delegate to
	 * unified preset ops handlers.
	 */
	public function dispatch_preset_handling () {
		$registry = Upfront_PresetServer_Registry::get_instance();
		$preset_servers = $registry->get_all();
		if (empty($preset_servers)) return false;

		foreach (array_keys($preset_servers) as $server) {
			add_action("upfront_save_{$server}_preset", array($this, 'save_preset'), 10, 2);
			add_action("upfront_delete_{$server}_preset", array($this, 'delete_preset'), 10, 2);
		}

	}

	public function process_stylesheet_directory ($style_dir) {
		if (upfront_exporter_is_start_page()) return 'upfront';
		return get_theme_root() . DIRECTORY_SEPARATOR . upfront_exporter_get_stylesheet();
	}

	public function prepare_preview_post ($post, $data) {
		if (empty($data['post_id']) || is_numeric($data['post_id'])) return $post;
		if ( 'fake_post' !== $data['post_id'] && 'fake_styled_post' !== $data['post_id'] ) return $post;
		return $this->_generate_preview_post($data);
	}

	// This is used for ThisPost filtering - will be going out soon enough
	public function get_theme_layout_cascade ($cascade, $base_filename) {
		$layout_cascade = false;
		$post_type = false;
		// Override brute force to ensure single-something page get their specific postlayout loaded
		if (!empty($_POST['layout_cascade'])) {
			$layout_cascade = stripslashes_deep($_POST['layout_cascade']);
			$post_type = !empty($_POST['post_type']) ? $_POST['post_type'] : false;
		}
		if (empty($layout_cascade)) return $cascade;

		$new_cascade = array(
			trailingslashit(wp_normalize_path(dirname($base_filename))) . $layout_cascade['item'] . '.php', // So... make sure this goes first, that's the most likely candidate
			$base_filename . $layout_cascade['item'] . '.php',
			$base_filename . $layout_cascade['type'] . '.php'
		);
		if (!empty($post_type) && !in_array($post_type, array_values($layout_cascade))) $new_cascade[] = $base_filename . $post_type . '.php';
		return $new_cascade;
	}

	// TODO this should go to upfront theme!
	public function get_theme_postpart_templates_cascade ($cascade, $base_filename) {
		// Override brute force to ensure single-something page get page specific post layout parts loaded
		$layout_cascade = !empty($_POST['layout_cascade']) ? $_POST['layout_cascade'] : false;
		if (empty($layout_cascade)) return $cascade;

		$base_path = preg_match('/-$/', $base_filename) && !is_dir($base_filename)
			? dirname($base_filename)
			: false
		;
		if ($base_path && is_dir($base_path)) $base_path = trailingslashit($base_path);

		$cascade = array(
			$base_filename . $layout_cascade['item'] . '.php',
			$base_filename . $layout_cascade['type'] . '.php'
		);

		if (!empty($base_path)) {
			$cascade[] = $base_path . $layout_cascade['item'] . '.php';
			$cascade[] = $base_path . $layout_cascade['type'] . '.php';
		}

		return $cascade;
	}

	public function get_stylesheet ($stylesheet) {
		return upfront_exporter_get_stylesheet();
	}

	public function upload_icon_font () {
		$font_path = $this->_fs->get_path(Thx_Fs::PATH_ICONS, false);
		$options = array(
			'upload_dir' => $font_path,
			'upload_url' => 'get_stylesheet/' . Thx_Fs::PATH_ICONS, // whatever
			'param_name' => 'media',
		);

		$filename = $_FILES['media']['name'];

		// Remove file first if it already exists, this will allow simple update of iconfont files
		$this->_fs->drop(array(
			Thx_Fs::PATH_ICONS,
			$filename
		));

		$uploadHandler = new UploadHandler($options, false);
		$result = $uploadHandler->post(false);

		if (isset($result['media'][0]->error)) {
			$out = new Upfront_JsonResponse_Error(array(
				'message' => 'Font file failed to upload.'
			));
			status_header($out->get_status());
			header("Content-type: " . $out->get_content_type() . "; charset=utf-8");
			die($out->get_output());
			return;
		}

		$fonts = json_decode($this->_theme_settings->get('icon_fonts'), true);
		$name_parts = explode('.', $result['media'][0]->name);

		// Reserve 'icomoon' family for UpFont
		if ($name_parts[0] === 'icomoon') {
			$out = new Upfront_JsonResponse_Error(array(
				'message' => __('Please rename font. Default Upfront font is called "icomoon".', UpfrontThemeExporter::DOMAIN),
			));
			status_header($out->get_status());
			header("Content-type: " . $out->get_content_type() . "; charset=utf-8");
			die($out->get_output());
			return;
		}

		$font_added = false;
		$new_fonts = array();

		if (!is_array($fonts)) {
			$fonts = array();
		}

		foreach($fonts as $font) {
			// Check if font is already added, just another file uploaded (e.g.: woff added now adding eot)
			if ($font['family'] === $name_parts[0]) {
				$font['files'][$name_parts[1]] = $result['media'][0]->name;
				$font_added = true;
			}
			$new_fonts[] = $font;
		}

		if (!$font_added) {
			// Add new font
			$font = array(
				'name' => $name_parts[0],
				'family' => $name_parts[0],
				'files' => array(),
				'type' => 'theme-defined',	// default, theme-defined or user-defined ->
											// 'default' is only UpFont from Upfront theme,
											// 'theme-defined' come with theme
											// 'user-defined' are uploaded by theme user
				'active' => false
			);
			$font['files'][$name_parts[1]] = $result['media'][0]->name;
			$new_fonts[] = $font;
		}

		$this->_theme_settings->set('icon_fonts', json_encode($new_fonts));
		$out = new Upfront_JsonResponse_Success(array(
			'font' => end($new_fonts)
		));
		status_header($out->get_status());
		header("Content-type: " . $out->get_content_type() . "; charset=utf-8");
		die($out->get_output());
	}

	public function update_active_icon_font () {
		if (!isset($_POST['family'])) {
			return;
		}

		$family = $_POST['family'];

		$fonts = json_decode($this->_theme_settings->get('icon_fonts'), true);;

		$result = array();

		foreach ($fonts as $font) {
			if ($font['family'] === $family) {
				$font['active'] = true;
			} else {
				$font['active'] = false;
			}
			$result[] = $font;
		}

		$this->_theme_settings->set('icon_fonts', json_encode($result));
	}

	public function get_stylesheet_directory ($stylesheetDirectory) {
		$path = $this->_fs->get_path('/');
		return !empty($path)
			? $path
			: $stylesheetDirectory
		;
	}

	function inject_dependencies () {
		if (!is_user_logged_in()) return false; // Do not inject for non-logged in user

		$layout = $this->augment_layout_load_from_get_query(array(
			'item' => 'archive-home',
			'type' => 'archive',
		), null);

		Thx_Template::plugin()->load('dependencies', array(
			'root_url' =>  $this->_plugin_dir_url,
			'includes_url' => includes_url() . 'js/',
			'themes' => $this->_get_themes(),
			'layout' => Upfront_EntityResolver::layout_to_name($layout),
		));
	}

	public function json_get_themes () {
		$this->_json->out($this->_get_themes());
	}

	protected function _get_themes () {
		$themes = array();

		foreach(wp_get_themes() as $index=>$theme) {
			if ($theme->get('Template') !== 'upfront') continue;

			$themes[$index] = array(
				'directory' => $index,
				'name' => $theme->get('Name')
			);
		}

		return $themes;
	}

	public function json_export_layout () {
		$data = $_POST['data'];
		if (empty($data['theme']) || empty($data['template'])) {
			$this->_json->error_msg(__('Theme & template must be choosen.', UpfrontThemeExporter::DOMAIN), 'missing_data');
		}

		$this->_theme = $data['theme'];
		$this->_template = $data['template'];
		$this->_fs->set_theme($this->_theme);

		$regions = json_decode(stripslashes($data['regions']));

		$template = "<?php\n";

		$template .= "\$layout_version = '" . Upfront_Layout::$version . "';\n\n";

		foreach($regions as $region) {
			if($region->name === 'shadow') continue;
			if(!empty($region->scope) && $region->scope === 'global' && (!$region->container || $region->name == $region->container)) {
				$global_region_filename = "get_stylesheet_directory() . DIRECTORY_SEPARATOR . '" . Thx_Fs::PATH_REGIONS . "' . DIRECTORY_SEPARATOR . '{$region->name}.php'";
				$template .= "if (file_exists({$global_region_filename})) include({$global_region_filename});\n\n"; // <-- Check first
				$this->_global_regions[$region->name] = $region;
				//$this->_update_global_region_template($region->name);
				continue;
			}
			if($region->container === 'lightbox') {
				$this->_export_lightbox($region);
				continue;
			}
			if(!empty($region->scope) && $region->scope === 'global' && ($region->container && $region->name != $region->container)) {
				$handle = $this->_handle_global_sideregion($region, $regions);
				if ( !$handle ) {
					$global_region_filename = "get_stylesheet_directory() . DIRECTORY_SEPARATOR . '" . Thx_Fs::PATH_REGIONS . "' . DIRECTORY_SEPARATOR . '{$region->name}.php'";
					$template .= "\$region_container = '$region->container';\n";
					$template .= "\$region_sub = '$region->sub';\n";
					$template .= "if (file_exists({$global_region_filename})) include({$global_region_filename});\n\n"; // <-- Check first
				}
				continue;
			}
			$template .= $this->_render_region($region);
		}

		foreach($this->_global_regions as $region_name => $region){
			$this->_update_global_region_template($region_name);
		}

		$file = !empty($data['functionsphp']) ? $data['functionsphp'] : false;
		if($file == 'test')
			$file = 'functions.test.php';
		else if($file == 'functions')
			$file = 'functions.php';
		else
			$file = false;

		$specific_layout = preg_match('/^single-page-.*/', $data['template'])
			? preg_replace('/^single-page-/', '', $data['template'])
			: false
		;

		$this->_save_layout_to_template(
			array(
				'template' => $data['template'],
				'content' => $template,
				'layout' => $specific_layout,
				'functions' => $file
			)
		);

		// Reset all caches on export
		upfront_exporter_clear_conversion_cache($this->_theme);

		die;
	}

	protected function _handle_global_sideregion ($region, $regions = array()) {
		// Check if the container is global, otherwise, export itself
		$has_container = false;
		foreach ( $regions as $reg ) {
			if ( $region->container == $reg->name && $reg->scope === 'global' ){
				$has_container = true;
				break;
			}
		}
		if ( !empty($regions) && !$has_container ) {
			$this->_global_regions[$region->name] = $region;
		}
		else {
			$this->_global_sideregions[$region->container][$region->sub] = $region;
		}
		return $has_container;
	}

	public function json_export_element_styles () {
		$data = stripslashes_deep($_POST['data']);
		if (empty($data['stylename']) || empty($data['styles']) || empty($data['elementType'])) {
			$this->_json->error_msg(__('Some data is missing.', UpfrontThemeExporter::DOMAIN), 'missing_data');
		}

		if ($data['elementType'] === 'layout') {
			$style = $this->_make_urls_passive_relative($data['styles']);
			if (upfront_exporter_is_start_page()) {
				update_option('upfront_new-layout_style', addcslashes($style, "'\\"));
				$this->_json->out(__('Exported', UpfrontThemeExporter::DOMAIN));
			} else {
				$this->_theme_settings->set('layout_style', addcslashes($style, "'\\"));
				$this->_json->out(__('Exported', UpfrontThemeExporter::DOMAIN));
			}
		}

		$stylesheet = !empty($_POST['stylesheet']) && 'upfront' !== $_POST['stylesheet']
			? $_POST['stylesheet']
			: false
		;

		if (!empty($stylesheet)) $this->_export_element_style($stylesheet, $_POST['data']);
		else $this->_temporarily_store_export_file($data);
		$this->_json->out(__('Exported', UpfrontThemeExporter::DOMAIN));
	}

	/**
	 * Actually writes element styles to files.
	 */
	private function _export_element_style ($name, $data) {
		$this->_theme = $name;
		$style = stripslashes_deep($data['styles']);
		$style = $this->_make_urls_passive_relative($style);

		$path = array(
			Thx_Fs::PATH_STYLES,
			$data['elementType'],
		);
		$this->_fs->mkdir_p($path);

		$path[] = $data['stylename'] . '.css';
		$this->_fs->write($path, $style);
	}

	/**
	 * This stores element styles before they're ready to be exported to files.
	 */
	private function _temporarily_store_export_file ($data) {
		$stored = get_option(self::TEMP_STYLES_KEY, array());
		$stored[] = $data;
		update_option(self::TEMP_STYLES_KEY, $stored);
	}

	public function json_delete_element_styles () {
		if (upfront_exporter_is_creating()) {
			$this->_json->error_msg(__('Can\'t do that before theme is created.', UpfrontThemeExporter::DOMAIN));
		}

		$data = $_POST['data'];
		if (empty($data['stylename']) || empty($data['elementType'])) {
			$this->_json->error_msg(__('Some data is missing.', UpfrontThemeExporter::DOMAIN), 'missing_data');
		}

		$stylesheet = !empty($_POST['stylesheet']) && 'upfront' !== $_POST['stylesheet']
			? $_POST['stylesheet']
			: false
		;

		if (!empty($stylesheet)) $this->_delete_element_style($stylesheet, $data);
	}

	private function _delete_element_style ($name, $data) {
		$this->_theme = $name;

		$this->_fs->drop(array(
			Thx_Fs::PATH_STYLES,
			$data['elementType'],
			$data['stylename']
		));
	}

	protected function _render_region ($region, $use_var = false) {
		$data = (array)$region;
		$name = Thx_Sanitize::php_safe_variable_name($data['name']);
		if (empty($name)) $name = 'region_' . substr(md5(serialize($data)), 0, 6);

		$data['type'] = isset( $data['type'] ) ? $data['type'] : "";
		$data['scope'] = isset( $data['scope'] ) ? $data['scope'] : "";
		$main = array(
			'name' => $data['name'],
			'title' => $data['title'],
			'type' => $data['type'],
			'scope' => $data['scope']
		);

		if ($use_var) { // just pass a variable string, the variable will be defined before this method call, to allow the use of sidebar region globally

			// This might break with `var_export`-based exports, so there's a fix in `Thx_Json::stringify_php` method
			// labeled "Fix single-quoted variables"

			$main['container'] = "\${$name}_container";
			$main['sub'] = "\${$name}_sub";
		} else {
			if (!empty($data['container']) && $data['name'] !== $data['container']) $main['container'] = $data['container'];
			else $main['container'] = $data['name'];

			if (!empty($data['sub'])) $main['sub'] = $data['sub'];
		}
		if (!empty($data['position'])) $main['position'] = $data['position'];
		if (!empty($data['allow_sidebar'])) $main['allow_sidebar'] = $data['allow_sidebar'];
		if (!empty($data['restrict_to_container'])) $main['restrict_to_container'] = $data['restrict_to_container'];
		if (!empty($data['behavior'])) $main['behavior'] = $data['behavior'];
		if (!empty($data['sticky'])) $main['sticky'] = $data['sticky'];
		$secondary = $this->_parse_properties($data['properties']);

		// Add version
		$secondary['version'] = Upfront_Layout::$version;

		// Deal with the slider images
		if (!empty($secondary['background_slider_images'])) {
			foreach ($secondary['background_slider_images'] as $idx => $img) {
				$source = get_attached_file($img);
				if (empty($source)) continue;
				// copy file to theme folder
				$file = basename($source);
				$path_parts = array(
					Thx_Fs::PATH_IMAGES,
					$file,
				);
				$destination = $this->_fs->get_path($path_parts, false);
				@copy($source, $destination);
				$secondary['background_slider_images'][$idx] = $this->_fs->construct_path($path_parts, true);
			}
		}

		$output = '';
		if ($use_var) {
			$output .= '$' . $name . '_container = ( !empty($region_container) ? $region_container : "' . ( !empty($data['container']) ? $data['container'] : '' ) . '" );' . "\n";
			$output .= '$' . $name . '_sub = ( !empty($region_sub) ? $region_sub: "' . ( !empty($data['sub']) ? $data['sub'] : '' ) . '" );' . "\n\n";
		}

		// Marker to let us know where region start, used to split string when we process it
		// @TODO Remove this when proper solution is available
		$output .= '/* START_REGION_OUTPUT */' . "\n";

		$output .= '$'. $name . ' = upfront_create_region(
			' . $this->_json->stringify_php($main) .',
			' . $this->_json->stringify_php($secondary) . '
			);' . "\n";

		$output .= $this->_render_modules($name, $data['modules'], $data['wrappers']);

		$output .= "\n" . '$regions->add($' . $name . ");\n\n";

		// Marker to let us know where region end
		// @TODO Remove this when proper solution is available
		$output .= '/* END_REGION_OUTPUT */' . "\n";

		return $output;
	}

	protected function _render_modules ($name, $modules, $wrappers, $group = '') {
		$region_lightboxes = array();
		$output = '';
		$export_images = $this->_does_theme_export_images();
		$exported_wrappers = array();
		foreach ($modules as $i => $m) {
			$nextModule = false;
			$module = (array) $m;

			// Looking for next module, so that we can compare their wrapper_id to see if we need to add close_wrapper property
			if (!empty($modules) && sizeof($modules) > ($i+1)) {
				$nextModule = $this->_parse_properties($modules[$i+1]->properties);
			}

			$isGroup = (isset($module['modules']) && isset($module['wrappers']));

			$moduleProperties = $this->_parse_properties($module['properties']);
			$props = $this->_parse_module_class($moduleProperties['class']);
			$props['id'] = $moduleProperties['element_id'];
			if (!$isGroup) {
				$props['options'] = $this->_parse_properties($module['objects'][0]->properties);
			}

			foreach($moduleProperties as $property => $value){
				if ( !in_array($property, array('class', 'element_id', 'breakpoint', 'has_settings')) ) {
					$props[$property] = $value;
				}
			}

			// Add new line if needed
			foreach($wrappers as $wrapper) {
				foreach($wrapper->properties as $property) {
					if ($property->name === 'wrapper_id' && $property->value === $props['wrapper_id']) {
						$module_wrapper = $wrapper;
						break;
					}
				}
			}

			foreach($module_wrapper->properties as $property) {
				if ($property->name !== 'class' && $property->name !== 'breakpoint') continue;

				if ($property->name === 'class' && strpos($property->value, 'clr') !== false) {
					$props['new_line'] = true;
				}

				if ($property->name === 'breakpoint' && !in_array($props['wrapper_id'], $exported_wrappers) && !empty($property->value)) {
					// Only export the wrapper breakpoint data on the first element
					$props['wrapper_breakpoint'] = array();
					foreach($property->value as $bidx => $point) {
						$props['wrapper_breakpoint'][$bidx] = (array)$point;
					}
					$exported_wrappers[] = $props['wrapper_id'];
				}
			}

			// Deal with per-module breakpoint props
			$breakpoints = !empty($moduleProperties['breakpoint'])
				? (array)$moduleProperties['breakpoint']
				: array()
			;
			if (!empty($breakpoints)) {
				$props['breakpoint'] = array();
				foreach($breakpoints as $bidx => $point) {
					$props['breakpoint'][$bidx] = (array)$point;
				}
			}

			if($nextModule && $moduleProperties['wrapper_id'] == $nextModule['wrapper_id']){
				$props['close_wrapper'] = false;
			}

			$type = $this->_get_object_type(
				(!empty($props['options']['view_class']) ? $props['options']['view_class'] : false)
			);

			// Check for hardcoded emails
			if ('Ucontact' == $type && !empty($props['options']['form_email_to']) && preg_match('/@/', $props['options']['form_email_to'])) {
				$props['options']['form_email_to'] = '';
			}

			// Check for lightboxes and anchors
			$page_slug = str_replace('single-page-', '', $this->_template);
			$page_slug = $page_slug === 'archive-home' ? '' : $page_slug . '/';
			switch($type) {
				case 'Uimage':
					if ($props['options']['link']->type === 'anchor') {
						$converted_url = str_replace(UpfrontThemeExporter::get_root_slug() . '/' . $this->_theme, $page_slug, $props['options']['link']->url);
						$props['options']['link']->url = $converted_url;
					}
					if ($props['options']['link']->type === 'lightbox') $region_lightboxes[] = $props['options']['link']->url;
					if (!$export_images && empty($props['options']['src']) && !empty($props['options']['image_status']) && 'starting' !== $props['options']['image_status']) {
						// If we're not exporting images AND if we're dealing with zeroed-out image, update its status
						$props['options']['image_status'] = 'starting';
					}
					break;
				case 'Ugallery':
					foreach($props['options']['images'] as $index=>$image) {
						if ($image->imageLink === false) {
							continue;
						}

						// Only anchors need this
						if ($image->imageLink->type === 'anchor') {
							$converted_url = str_replace(UpfrontThemeExporter::get_root_slug() . '/' . $this->_theme, $page_slug, $image->imageLink->url);
							$props['options']['images'][$index]->imageLink->url = $converted_url;
						}
					}
					break;
				case 'PlainTxt':
					$region_lightboxes = array_merge($region_lightboxes, $this->_get_lightboxes_from_text($props));
					break;
				case 'Button':
					$region_lightboxes = array_merge($region_lightboxes, $this->_get_lightboxes_from_button($props));
					if ($props['options']['link']->type === 'anchor') {
						$converted_url = str_replace(UpfrontThemeExporter::get_root_slug() . '/' . $this->_theme, $page_slug, $props['options']['link']->url);
						$props['options']['link']->url = $converted_url;
					}
					break;
				case 'Unewnavigation':
					$region_lightboxes = array_merge($region_lightboxes, $this->_get_lightboxes_from_menu($props));
					break;
			}

			if ($type === 'Unewnavigation') {
				$this->_add_menu_from_element($props);
				$props['options']['menu_id'] = false; // Should not be set in exported layout
			}

			if ($isGroup){
				$output .= "\n" . '$' . $name . '->add_group(' . $this->_json->stringify_php($props) . ");\n";
				$output .= $this->_render_modules($name, $module['modules'], $module['wrappers'], $props['id']);
			} else {
				if (!empty($group)){
					$props['group'] = $group;
				}

				// Render objects if there's objects attribute in the object (means it is ObjectGroup)
				if (isset($module['objects'][0]->objects)) {
					$props['objects'] = (array)$this->_render_objects($name, $module['objects'][0]->objects, $module['objects'][0]->wrappers, $props['id']);
				}

				$output .= "\n" . '$' . $name . '->add_element("' . $type . '", ' . $this->_json->stringify_php($props) . ");\n";
			}
		}

		$lightboxes_path = "get_stylesheet_directory() . DIRECTORY_SEPARATOR . 'global-regions' . DIRECTORY_SEPARATOR . 'lightboxes' . DIRECTORY_SEPARATOR";
		$region_lightboxes = array_unique($region_lightboxes);
		if (count($region_lightboxes) > 0) {
			// Include lightbox container
			$output .= "\nif (file_exists({$lightboxes_path} . 'lightbox.php')) include({$lightboxes_path} . 'lightbox.php');";
		}

		foreach($region_lightboxes as $lightbox) {
			$lightbox_parts = explode('#', $lightbox);
			$lightbox = end($lightbox_parts);
			$output .= "\nif (file_exists({$lightboxes_path} . '$lightbox.php')) include({$lightboxes_path} . '$lightbox.php');";
		}
		return $output;
	}

	protected function _render_objects ($name, $objects, $wrappers, $parent_id) {
		$return = array();
		$exported_wrappers = array();
		foreach ($objects as $i => $o) {
			$nextObject = false;
			$object = (array)$o;
			// Looking for next object, so that we can compare their wrapper_id to see if we need to add close_wrapper property
			if (!empty($objects) && sizeof($objects) > ($i+1)) {
				$nextObject = $this->_parse_properties($objects[$i+1]->properties);
			}

			$properties = $this->_parse_properties($object['properties']);
			$props = $this->_parse_module_class($properties['class']);

			foreach ($properties as $property => $value) {
				if ( !in_array($property, array('class', 'breakpoint', 'has_settings')) ) {
					$props[$property] = $value;
				}
			}

			// Add new line if needed
			foreach ($wrappers as $wrapper) {
				foreach($wrapper->properties as $property) {
					if ($property->name === 'wrapper_id' && $property->value === $props['wrapper_id']) {
						$object_wrapper = $wrapper;
						break;
					}
				}
			}

			foreach($object_wrapper->properties as $property) {
				if ($property->name !== 'class' && $property->name !== 'breakpoint') continue;

				if ($property->name === 'class' && strpos($property->value, 'clr') !== false) {
					$props['new_line'] = true;
				}

				if ($property->name === 'breakpoint' && !in_array($props['wrapper_id'], $exported_wrappers) && !empty($property->value)) {
					// Only export the wrapper breakpoint data on the first object
					$props['wrapper_breakpoint'] = array();
					foreach($property->value as $bidx => $point) {
						$props['wrapper_breakpoint'][$bidx] = (array)$point;
					}
					$exported_wrappers[] = $props['wrapper_id'];
				}
			}

			// Deal with per-object breakpoint props
			$breakpoints = !empty($properties['breakpoint'])
				? (array)$properties['breakpoint']
				: array()
			;
			if (!empty($breakpoints)) {
				$props['breakpoint'] = array();
				foreach($breakpoints as $bidx => $point) {
					$props['breakpoint'][$bidx] = (array)$point;
				}
			}

			if ($nextObject && $properties['wrapper_id'] == $nextObject['wrapper_id']) {
				$props['close_wrapper'] = false;
			}

			$return[] = $props;

		}
		return $return;
	}

	protected function _get_lightboxes_from_menu ($properties) {
		$lightboxes = array();

		$menu_id = $properties['options']['menu_id'];
		$menu_items = wp_get_nav_menu_items($menu_id);

		if( is_array( $menu_items ) ){
			foreach($menu_items as $menu_item) {
				if (!$this->_has_ligthbox($menu_item->url)) continue;
				$lightboxes[] = $menu_item->url;
			}
		}

		return $lightboxes;
	}

	protected function _get_lightboxes_from_text ($properties) {
		if (!$this->_has_ligthbox($properties['options']['content'])) return array();

		$matches = array();
		preg_match_all('#href="(\#ltb-.+?)"#', $properties['options']['content'], $matches);

		if (is_array($matches[1])) foreach ($matches[1] as $match) {
			if (!$this->_has_ligthbox($match)) unset($match);
		}

		return is_array($matches[1]) ? $matches[1] : array();
	}

	protected function _get_lightboxes_from_button ($properties) {
		if ($properties['options']['link']->type !== 'lightbox') return array();

		return array($properties['options']['link']->url);
	}

	/**
	 * Check if the passed argument has lightbox prefix within
	 *
	 * @param string $string String to check
	 *
	 * @return bool
	 */
	protected function _has_ligthbox ($string) {
		return false !== strpos($string, '#ltb-');
	}

	protected function _add_menu_from_element ($properties) {
		$menu_id = $properties['options']['menu_id'];

		$page_slug = str_replace('single-page-', '', $this->_template);
		$page_slug = $page_slug === 'archive-home' ? '' : $page_slug . '/';

		$menu_object = wp_get_nav_menu_object($menu_id);
		$menu_items = wp_get_nav_menu_items($menu_id);
		if( is_array( $menu_items ) ){
			foreach($menu_items as $menu_item) {
				// Make anchors work from other pages
				if (strpos($menu_item->url, 'create_new') !== false) {
					// If url has 'create_new' we assume it's newly linked item with anchor on this page
					$menu_item->url = str_replace(get_home_url() . '/' . UpfrontThemeExporter::get_root_slug() . '/' . $this->_theme, '{{upfront:home_url}}/' . $page_slug, $menu_item->url);
					continue;
				}

				if(strpos($menu_item->url, site_url()) === false) continue;

				// Fix lightboxes and other anchor urls
				$menu_item->url = preg_replace('#' . get_home_url() . '/' . UpfrontThemeExporter::get_root_slug() . '/.+?(\#[A-Za-z_-]+)#', '\1', $menu_item->url);

				// Fix hardcoded site url
				$menu_item->url = str_replace(site_url(), '%siteurl%', $menu_item->url);
			}
		}

		$menu = array(
			'id' => false,
			'slug' => "",
			'name' => "",
			'description' => "",
			'items' => $menu_items
		);

		if(  is_object( $menu_object ) )
			$menu = wp_parse_args( array(
				'slug' => $menu_object->slug,
				'name' => $menu_object->name,
				'description' => $menu_object->description
			), $menu );

		if (upfront_exporter_is_start_page()) {
			$menus = json_decode(get_option('upfront_new-menus'));
		} else {
			$menus = json_decode($this->_theme_settings->get('menus'));
		}

		if (is_null($menus)) $menus = array();

		$updated = false;

		foreach($menus as $index=>$stored_menu) {
			if ($stored_menu->slug != $menu['slug']) continue;

			$menus[$index] = $menu;
			$updated = true;
			break;
		}

		if ($updated === false) $menus[] = $menu;

		if (upfront_exporter_is_start_page()) {
			update_option('upfront_new-menus', json_encode($menus));
			return;
		}
		$this->_theme_settings->set('menus', json_encode($menus));
	}

	protected function _get_object_type ($class) {
		return str_replace('View', '', $class);
	}

	protected function _parse_properties ($props) {
		$parsed = array();
		if (empty($props)) return $parsed;
		foreach($props as $p){
			$parsed[$p->name] = isset( $p->value ) ? $p->value : "";
		}
		return $parsed;
	}

	protected function _parse_module_class ($class) {
		$classes = explode(' ', $class);
		$properties = array();
		foreach ($classes as $c) {
			if (preg_match('/^c\d+$/', $c)) {
				$properties['columns'] = str_replace('c', '', $c);
			}
			else if (preg_match('/^ml\d+$/', $c)) {
				$properties['margin_left'] = str_replace('ml', '', $c);
			}
			else if (preg_match('/^mr\d+$/', $c)) {
				$properties['margin_right'] = str_replace('mr', '', $c);
			}
			else if (preg_match('/^mt\d+$/', $c)) {
				$properties['margin_top'] = str_replace('mt', '', $c);
			}
			else if (preg_match('/^mb\d+$/', $c)) {
				$properties['margin_bottom'] = str_replace('mb', '', $c);
			}
		}
		$properties['class'] = trim(preg_replace('/(c|ml|mr|mt|mb)\d+ ?/', '', $class));
		return $properties;
	}

	protected function _save_layout_to_template ($layout) {
		$template = Thx_Sanitize::extended_alnum($layout['template']);
		$content = $layout['content'];

		//$template_images_dir = $this->getThemePath('images', $template);
		$template_images_dir_args = array(
			Thx_Fs::PATH_IMAGES,
			$template,
		);
		$this->_fs->mkdir_p($template_images_dir_args);
		$template_images_dir = $this->_fs->get_path($template_images_dir_args);

		// Copy all images used in layout to theme directory
		$content = $this->_export_images($content, $template, $template_images_dir);

		$content = $this->_make_urls_relative($content);

		// Remove marker
		// @TODO Remove this when proper solution is available
		$content = preg_replace("/\/\* (START|END)_REGION_OUTPUT \*\/\n/ms", '', $content);

		// Save layout to file
		$result = $this->_fs->write(array(
			Thx_Fs::PATH_LAYOUTS,
			"{$template}.php"
		), $content);

		// Save properties to settings file
		$string_properties = array('typography', 'layout_style', 'layout_properties');
		$raw_post_data = !empty($_POST['data']) ? stripslashes_deep($_POST['data']) : array();
		foreach($string_properties as $property) {
			$value = isset($raw_post_data[$property]) ? addcslashes($raw_post_data[$property], "'\\") : false;
			if ($value === false) continue;

			// Don't forget the UI images in layout style
			// Use passively expanded URLs even though this will end up in a PHP file
			// to keep things simple with aggressively quoted situation in settings array.
			$value = $this->_make_urls_passive_relative($value);

			$this->_theme_settings->set($property, $value);
		}
		$array_properties = array('theme_colors', 'button_presets', "post_image_variants");
		foreach($array_properties as $property) {
			$value = isset($raw_post_data[$property]) ? $raw_post_data[$property] : false;
			if ($value === false) continue;
			$this->_theme_settings->set($property, json_encode($value));
		}

		// Responsive settings. Yeah
		$key = "upfront_{$this->_theme}_responsive_settings";
		$resp = get_option($key);
		if (!empty($resp)) $this->_theme_settings->set("responsive", $resp);

		// Specific layout settings
		if (!empty($layout['layout'])) {
			$pages = $this->_theme_settings->get('required_pages');
			if (!empty($pages)) {
				$pages = json_decode($pages, true);
			}
			if (!is_array($pages)) $pages = array();
			$page = Thx_Sanitize::extended_alnum($layout['layout']);
			if (!empty($page)) { // We have to have this - and clean - to continue
				$name = join(' ', array_map('ucfirst', explode('-', $page)));
				$page_layout_data = array(
					'name' => $name,
					'slug' => $page,
					'layout' => $template,
				);

				$pages[$page] = $page_layout_data;
				$this->_theme_settings->set('required_pages', json_encode($pages));

				// Yeah, and now, also please do export the standard WP template too
				$tpl_filename = "page_tpl-{$page}";

				// Include the template file from which we will be generating a WP page template.
				$tpl_content = $contents = $this->_template('page-template', $page_layout_data);
				// Recursive definition yay

				$this->_fs->write(array(
					"{$tpl_filename}.php",
				), $tpl_content);
			}
		}
	}

	/**
	 * Relativize URLs for *active* stuff.
	 * This means stuff that can actually process PHP expressions.
	 *
	 * @param string $content Content to process
	 * @param string $quote Quote type to use
	 *
	 * @return string Processed content with URLs relativized
	 */
	protected function _make_urls_relative ($content, $quote='"') {
		if (!in_array($quote, array('"', "'"))) $quote = '"';
		$alt_quote = '"' === $quote ? "'" : '"';
		// Fix lightboxes and other anchor urls
		$content = preg_replace('#' . get_home_url() . '/' . UpfrontThemeExporter::get_root_slug() . '/.+?(\#[A-Za-z_-]+)#', '\1', $content);

		$stylesheet = get_stylesheet_directory_uri();

		// Okay, so now the imported image is hard-linked to *current* theme dir...
		// Not what we want - the images don't have to be in the current theme, not really
		// Ergo, fix - replace all the hardcoded stylesheet URIs to dynamic ones.

		$codec = new Upfront_MacroCodec_LayoutData;
		$content = $codec->encode_all($content);

		return $content;
	}


	/**
	 * This will relativize URLs for *passive* (non-PHP) stuffs
	 *
	 * @param  string $content Content to process
	 *
	 * @return string Content with URLs parsed.
	 */
	protected function _make_urls_passive_relative ($content) {
		$base = preg_quote(get_stylesheet_directory_uri(), '/');
		$no_proto_base = preg_quote(preg_replace('/^https?:/', '', get_stylesheet_directory_uri()), '/');
		$rpl = Upfront_ChildTheme::THEME_BASE_URL_MACRO;

		$content = preg_replace("/{$base}/", $rpl, $content);
		$content = preg_replace("/{$no_proto_base}/", $rpl, $content);

		return $content;
	}

	protected function _export_images ($content, $template, $template_images_dir) {
		$matches = array();
		$uploads_dir = wp_upload_dir();

		$_themes_root = basename(get_theme_root());
		$_uploads_root = basename($uploads_dir['basedir']);

		$template_images_dir = trailingslashit(rtrim($template_images_dir, '/'));

		// Save file list for later
		$original_images = preg_match('{\b' . $template . '\b}', wp_normalize_path($template_images_dir))
			? glob($template_images_dir . '*.{jpg,JPG,jpeg,JPEG,png,PNG,gif,GIF}', GLOB_BRACE)
			: array()
		;

		preg_match_all("#\b(https?://.+?\.(jpg|jpeg|png|gif))\b#", $content, $matches);

		$images_used_in_template = array();
		$separator = '/';

		$export_images = $this->_does_theme_export_images();

		// Use this for "theme" images - the ones in the `ui/` subdirectory
		$_this_theme_relative_ui_root = '/' . basename($this->_fs->get_root_path()) . '/' . Thx_Fs::PATH_UI . '/';
		$theme_ui_path = $this->_fs->get_path(Thx_Fs::PATH_UI);

		// matches[1] containes full image urls
		foreach ($matches[1] as $image) {
			$is_ui_image = false !== strpos($image, $_this_theme_relative_ui_root);

			// If the exports aren't allowed...
			if (!$export_images) {
				// Lots of duplication, this could really use some refactoring :/
				if ($is_ui_image) {
					// ... let's deal with the UI images first ...
					$relative_url = explode("/{$_themes_root}/", $image);
					$relative_url = $relative_url[1];
					$source_root = get_theme_root();
					$source_path_parts = explode('/', $relative_url);
					$image_filename = end($source_path_parts);

					// Get source and destination image
					$source_relative_path = str_replace('/', $separator, $relative_url);
					$source_image = $source_root . $separator . $source_relative_path;

					$destination_image = $theme_ui_path . $image_filename;

					// Copy image
					if (file_exists($source_image)) {
						$result = copy($source_image, $destination_image);
					}
					$images_used_in_template[] = $destination_image;

					// Replace images url root with stylesheet uri
					$image_uri = get_stylesheet_directory_uri() . '/images/' . $template . '/' . $image_filename;
					$content = preg_replace('/\b' . preg_quote($image, '/') . '\b/i', $image_uri, $content);
				} else {
					// ... before we null out the other stuff and carry on.
					$content = preg_replace('/\b' . preg_quote($image, '/') . '\b/i', '', $content);
				}
				continue;
			}

			// Alright, so frome here on, we know we have images exports allowed.
			// So, let's export!

			// Image is from a theme
			if (false !== strpos($image, get_theme_root_uri())) {
				$relative_url = explode("/{$_themes_root}/", $image);
				$source_root = get_theme_root();
				$current_template_directory = $template_images_dir;
			}
			// Image is from uploads
			if (false !== strpos($image, 'uploads')) {
				$relative_url = explode("/{$_uploads_root}/", $image);
				$source_root = $uploads_dir['basedir'];
				$current_template_directory = $template_images_dir;
			}
			// Image is from UI
			if ($is_ui_image) {
				$relative_url = explode("/{$_themes_root}/", $image);
				$source_root = get_theme_root();
				$current_template_directory = $theme_ui_path;
			}
			$relative_url = $relative_url[1];

			// Get image filename
			$source_path_parts = explode('/', $relative_url);
			$image_filename = end($source_path_parts);

			// Get source and destination image
			$source_relative_path = str_replace('/', $separator, $relative_url);
			$source_image = $source_root . $separator . $source_relative_path;

			$destination_image = trailingslashit($current_template_directory) . $image_filename;

			// Copy image
			if (file_exists($source_image) && !file_exists($destination_image)) {
				$result = copy($source_image, $destination_image);
			}
			$images_used_in_template[] = $destination_image;

			// Replace images url root with stylesheet uri
			$image_uri = !empty($is_ui_image)
				? get_stylesheet_directory_uri() . '/ui/' . $image_filename
				: get_stylesheet_directory_uri() . '/images/' . $template . '/' . $image_filename
			;
			$content = preg_replace('/\b' . preg_quote($image, '/') . '\b/i', $image_uri, $content);
		}

		// Delete images that are not used, this is needed if template is exported from itself
		foreach ($original_images as $file) {
			if (in_array($file, $images_used_in_template)) continue;
			if (is_file($file)) {
				unlink($file);
			}
		}

		return $content;
	}

	/**
	 * If a theme (UI) image has been requested, let's verify that the subdirectory actually exists.
	 * This only applies to themes build pre-UI changeset.
	 */
	public function check_theme_images_destination_exists () {
		$path = $this->_fs->get_path(Thx_Fs::PATH_UI, false);
		if (!$this->_fs->exists($path)) {
			$this->_fs->mkdir($path);
		}
		return $path; // `return` in an AJAX request handler? - *Yes* because we're just augmenting the default behavior.
	}

	protected function _update_global_region_template ($region_name) {
		$region = $this->_global_regions[$region_name];
		$is_main = ( !$region->container || $region->name == $region->container );
		$content = "<?php\n";
		$render_before = array();
		$render_after = array();
		if ($is_main && isset($this->_global_sideregions[$region->name])) {
			foreach ($this->_global_sideregions[$region->name] as $sub => $sub_region){
				if ($sub == 'left' || $sub == 'top')
					$render_before[] = $this->_render_region($sub_region);
				else
					$render_after[] = $this->_render_region($sub_region);
			}
		}

		$content .= join('', $render_before);
		$content .= $this->_render_region($region, !$is_main);
		$content .= join('', $render_after);

		// Start with global region creation
		$greg_root = $this->_fs->construct_theme_path(Thx_Fs::PATH_REGIONS);
		if (!$this->_fs->exists($greg_root)) $this->_fs->mkdir($greg_root);

		$path_bits = array(
			Thx_Fs::PATH_IMAGES,
			Thx_Fs::PATH_REGIONS,
			$region->name
		);
		$this->_fs->mkdir_p($path_bits);
		$template_images_dir = $this->_fs->get_path($path_bits);

		// Copy all images used in layout to theme directory
		$content = $this->_export_images($content, Thx_Fs::PATH_REGIONS . '/' . $region->name, $template_images_dir);

		$content = $this->_make_urls_relative($content);

		$this->_fs->write(array(
			Thx_Fs::PATH_REGIONS,
			"{$region->name}.php",
		), $content);
	}

	protected function _export_lightbox ($region) {
		$this->_fs->mkdir_p(array(
			Thx_Fs::PATH_REGIONS,
			Thx_Fs::PATH_LIGHTBOXES,
		));

		$content = "<?php\n";
		$content .= $this->_render_region($region);

		$this->_fs->write(array(
			Thx_Fs::PATH_REGIONS,
			Thx_Fs::PATH_LIGHTBOXES,
			"{$region->name}.php",
		), $content);
	}

	public function update_theme_fonts ($theme_fonts) {
		if (upfront_exporter_is_start_page()) {
			update_option('upfront_new-theme_fonts', json_encode($theme_fonts));
			return;
		}
		$this->_theme_settings->set('theme_fonts', json_encode($theme_fonts));
	}

	public function update_responsive_settings ($responsive_settings) {
		$this->_theme_settings->set('responsive_settings', json_encode($responsive_settings));
	}

	public function update_theme_colors ($theme_colors) {
		if (upfront_exporter_is_start_page()) {
			update_option('upfront_new-theme_colors', json_encode($theme_colors));
			return;
		}
		$this->_theme_settings->set('theme_colors', json_encode($theme_colors));
	}

	public function update_button_presets ($button_presets) {
		if (upfront_exporter_is_start_page()) {
			update_option('upfront_new-button_presets', json_encode($button_presets));
			return;
		}
		$this->_theme_settings->set('button_presets', json_encode($button_presets));
	}

	public function update_post_image_variants ($post_image_variant) {
		if (upfront_exporter_is_start_page()) {
			update_option('upfront_new-post_image_variants', json_encode($post_image_variant));
			return;
		}
		$this->_theme_settings->set('post_image_variants', json_encode($post_image_variant));
	}


	public function save_preset ($properties, $slug) {
		$this->_update_element_preset($slug);
	}

	public function delete_preset ($properties, $slug) {
		$this->_update_element_preset($slug, true);
	}

	/**
	 * Exchanges newlines and otherwise prepares preset styles for storage.
	 *
	 * @param array $preset Preset
	 *
	 * @return array Processed preset
	 */
	protected function _escape_preset_styles ($preset) {
		if (isset($preset['preset_style']) && !empty($preset['preset_style'])) {
			$preset['preset_style'] = str_replace("'", '"', $preset['preset_style']); // Unify quotes first
			$preset['preset_style'] = str_replace("\r\n", "\n", $preset['preset_style']); // Unify newlines
			$preset['preset_style'] = str_replace("\r", "\n", $preset['preset_style']); // Unify newlines
			$preset['preset_style'] = $this->_make_urls_passive_relative($preset['preset_style']);
			$preset['preset_style'] = addcslashes(str_replace("\n", "@n", $preset['preset_style']), "'\\");
		}

		return $preset;
	}

	protected function _update_element_preset ($slug, $delete=false) {
		if (!isset($_POST['data'])) {
			return;
		}

		$presetProperty = $slug . '_presets';

		$presetData = $this->_escape_preset_styles($_POST['data']);
		$properties = stripslashes_deep($presetData);

		$raw = upfront_exporter_is_start_page()
			? get_option('upfront_new-' . $presetProperty)
			: $this->_theme_settings->get($presetProperty)
		;
		$presets = !empty($raw)
			? json_decode($raw, true)
			: array()
		;

		$result = array();

		// Make sure we only iterate over what can be iterated over...
		if (is_array($presets)) foreach ($presets as $preset) {
			if ($preset['id'] === $properties['id']) {
				continue;
			}
			$result[] = $preset;
		}

		if (!$delete) {
			$result[] = $properties;
		}

		if (upfront_exporter_is_start_page()) {
			update_option('upfront_new-' . $presetProperty, json_encode($result));
			return;
		}

		// Note: `addslashes` here, because theme settings will call `stripslashes` in the `get` method
		// Second note: we do not need to addslashes here because we already do on saving settings.php which make enourmous amount of slashes
		$this->_theme_settings->set($presetProperty, json_encode($result));
	}

	/**
	 * Processes the template path and expands the macros.
	 *
	 * @param string $relpath Relative path to template.
	 * @param array $data Macro definitions hash (macro => value)
	 *
	 * @return mixed Processed template as string or (bool)false on failure
	 */
	protected function _template ($relpath, $data) {
		$path = Thx_Template::theme()->path($relpath);
		if (!$this->_fs->exists($path)) return false;

		$template = file_get_contents($path);
		foreach ($data as $key => $value) {
			$template = str_replace('%' . $key . '%', $value, $template);
		}
		return $template;
	}

	/**
	 * Creates the theme's `functions.php` file from a template.
	 *
	 * @param string $slug Theme slug to be used as child theme's class name
	 */
	private function _create_functions_file ($slug) {
		$data = array(
			'name' => ucwords(str_replace('-', '_', sanitize_html_class($slug))),
			'slug' => $slug,
			'pages' => '',
			'exports_images' => $this->_does_theme_export_images() ? 'true' : 'false', // Force conversion to string so it can be expanded in the template.
		);

		$contents = $this->_template('functions', $data);

		$this->_fs->write(array(
			'functions.php'
		), $contents);
	}

	/**
	 * Creates the theme's `style.css` file.
	 *
	 * Trumps over the old one, if it already exists.
	 * If the method is called against the existing theme, not all the data needs to be
	 * passed in. The missing info bits will be inferred from existing headers.
	 *
	 * @param string $theme_slug Theme slug
	 * @param array $data Theme data that'll be used to populate headers
	 */
	protected function _create_style_file ($theme_slug, $data) {
		$theme = wp_get_theme($theme_slug);

		// Populate missing info from the current theme
		if (is_object($theme) && $theme->exists()) {
			foreach ($data as $idx => $info) {
				if (!empty($info)) continue; // If we have stuff here, we're all good to go for this property, so carry on

				// Because both theme headers and our data keys are so super-consistent,
				// let's make sure we have what it takes
				$raw_ti = preg_replace('/\buri\b/', 'URI', $idx); // In theme headers, "uri" bit is always all-caps so make sure we comply

				// Now, the variations. Some headers can, but not always, have "Theme" prefix.
				// Likewise, our data keys can, but don't have to, have "-theme-" infix.
				// So, we spawn some variations to check both versions.
				$theme_index1 = preg_replace('/thx-/', '', $raw_ti);
				$theme_index2 = preg_replace('/thx-theme-/', '', $raw_ti);

				// No matter the variation, the theme property within the WP theme API is CamelCased.
				// Well, except for the string "URI" which is always all-caps, but that's been taken care of already.
				$theme_index1 = join('', array_values(array_filter(array_map('ucfirst', explode('-', $theme_index1)))));
				$theme_index2 = join('', array_values(array_filter(array_map('ucfirst', explode('-', $theme_index2)))));

				// Scatter gun shot here. Get anything that can be got pl0x.
				$existing1 = $theme->get($theme_index1);
				$existing2 = $theme->get($theme_index2);
				$info = '';

				if (!empty($existing1)) $info = $existing1;
				else if (!empty($existing2)) $info = $existing2;

				// Did we ended up getting anything? Set the value if so.
				if (!empty($info)) $data[$idx] = $info;
			}
		}
		$content = $this->_template('style', $data);

		//$content = preg_replace('/^\s*[A-Z][^:]+:\s*%[^%]+%\s*$/m', '', $content);
		// Collapse missing properties instead
		$carr = explode("\n", preg_replace('/\R/u', "\n", $content));
		foreach ($carr as $cidx => $cnt) {
			$cnt = preg_replace('/^\s*[A-Z][^:]+:\s*(%[^%]+%\s*)?$/', '', $cnt);
			$carr[$cidx] = $cnt;
		}
		$content = join("\n", array_values(array_filter($carr)));

		$this->_fs->write(array(
			'style.css'
		), $content);

		$theme->cache_delete(); // We need this in order to prevent the theme from using the stale fucking data
	}

	/**
	 * Populate theme form defaults from the supplied data.
	 *
	 * @param array $form Initial data, optional
	 *
	 * @return array Properly initialized form hash
	 */
	private function _get_theme_form_defaults ($form=array()) {
		$form = wp_parse_args($form, array(
			'thx-theme-template' => 'upfront',
			'thx-theme-name' => false,
			'thx-theme-slug' => false,
			'thx-theme-uri' => false,
			'thx-theme-author' => false,
			'thx-author' => false,
			'thx-theme-author-uri' => false,
			'thx-author-uri' => false,
			'thx-theme-description' => false,
			'thx-theme-version' => false,
			'thx-theme-licence' => false,
			'thx-theme-licence-uri' => false,
			'thx-theme-tags' => false,
			'thx-theme-text-domain' => false,
			'thx-activate_theme' => true,
			'thx-export_with_images' => true,
			'thx-selected_preset' => true,
		));

		// In case we have a theme name, but no slug
		if (empty($form['thx-theme-slug']) && !empty($form['thx-theme-name'])) {
			$form['thx-theme-slug'] = strtolower(Thx_Sanitize::extended_alnum($form['thx-theme-name'], '-'));
		}

		return $form;
	}

	public function json_update_theme () {
		$form = array();
		parse_str($_POST['form'], $form);

		$form = $this->_get_theme_form_defaults($form);
		$theme_slug = $this->_validate_theme_slug($form['thx-theme-slug']);
		if (empty($theme_slug)) {
			$this->_json->error_msg(__('Your chosen theme slug is invalid, please try another.', UpfrontThemeExporter::DOMAIN), 'missing_required');
		}
		// Check if theme directory already exists
		$this->_fs->set_theme($theme_slug);
		$theme_path = $this->_fs->get_root_path();
		if (!file_exists($theme_path)) {
			$this->_json->error_msg(__('Theme with that directory name does not exist.', UpfrontThemeExporter::DOMAIN), 'theme_exists');
		}
		$this->_create_style_file($theme_slug, $form);

		// Also, let's activate this, if requested
		$current = get_option('stylesheet');
		if ($current !== $theme_slug && !empty($form['thx-activate_theme'])) {
			switch_theme($theme_slug);
		}

		die;
	}

	public function json_create_theme () {
		$form = array();
		parse_str($_POST['form'], $form);

		$form = $this->_get_theme_form_defaults($form);

		// Check required fields
		if (empty($form['thx-theme-slug']) || empty($form['thx-theme-name']) || empty($form['thx-theme-template'])) {
			$this->_json->error_msg(__('Please check required fields.', UpfrontThemeExporter::DOMAIN), 'missing_required');
		}

		$theme_slug = $this->_validate_theme_slug($form['thx-theme-slug']);
		if (empty($theme_slug)) {
			$this->_json->error_msg(__('Your chosen theme slug is invalid, please try another.', UpfrontThemeExporter::DOMAIN), 'missing_required');
		}

		// Check if theme directory already exists
		$this->_fs->set_theme($theme_slug);
		$theme_path = $this->_fs->get_root_path();
		if (file_exists($theme_path)) {
			$this->_json->error_msg(__('Theme with that directory name already exists.', UpfrontThemeExporter::DOMAIN), 'theme_exists');
		}
		$this->_fs->mkdir($theme_path);

		// Write style.css with theme variables
		$this->_create_style_file($theme_slug, $form);

		// Add directories
		$this->_fs->mkdir($this->_fs->get_path(Thx_Fs::PATH_LAYOUTS, false));
		$this->_fs->mkdir($this->_fs->get_path(Thx_Fs::PATH_IMAGES, false));
		$this->_fs->mkdir($this->_fs->get_path(Thx_Fs::PATH_UI, false));

		// This is important to set *before* we create the theme
		remove_all_filters('upfront-thx-theme_exports_images'); // This is for the duration of this request - so we don't inherit old values, whatever they are
		$this->_theme_exports_images = !empty($form['thx-export_with_images']);
		// Allright, good to go

		// Write functions.php to add stylesheet for theme
		$this->_create_functions_file($theme_slug);

		// Adding default layouts
		$preset = !empty($form['thx-selected_preset']) ? $form['thx-selected_preset'] : 'default';
		$default_layouts_dir = trailingslashit(Thx_Template::theme()->dirpath("default_layouts/{$preset}", false));
		$theme_layouts_dir = trailingslashit($this->_fs->construct_path(array(
			Thx_Fs::PATH_LAYOUTS
		)));

		$default_layouts = !empty($default_layouts_dir) && $this->_fs->exists($default_layouts_dir)
			? glob($default_layouts_dir . '*')
			: array()
		;
		$add_global_regions = isset($form['add_global_regions']) && $form['add_global_regions'];
		$global_regions_path = "get_stylesheet_directory() . DIRECTORY_SEPARATOR . 'global-regions' . DIRECTORY_SEPARATOR";
		$header_include = "\nif (file_exists({$global_regions_path} . 'header.php')) include({$global_regions_path} . 'header.php');\n";
		$footer_include = "if (file_exists({$global_regions_path} . 'footer.php')) include({$global_regions_path} . 'footer.php');";

		foreach($default_layouts as $layout) {
			$destination_file = str_replace($default_layouts_dir, $theme_layouts_dir, $layout);
			$destination_file = str_replace('.tpl', '', $destination_file);
			$content = include $layout;
			if ($add_global_regions) {
				$content = preg_replace('#<\?php#', "<?php" . $header_include, $content);
				$content .= "\n" . $footer_include;
			}
			//file_put_contents($destination_file, $content);
			$this->_fs->write($destination_file, $content);
		}

		// Here, we're ready to export any temporarily stored element styles
		$styles = get_option(self::TEMP_STYLES_KEY, array());
		if (!empty($styles)) {
			$tmp_action = !empty($_POST['action']) ? $_POST['action'] : false;
			if (!empty($tmp_action)) $_POST['action'] = false; // This is to fool upfront_exporter_is_creating() and ensure the path creation in element styles export
			foreach ($styles as $style) {
				$this->_export_element_style($theme_slug, $style);
			}
			if (!empty($tmp_action)) $_POST['action'] = $tmp_action; // Revert back, just in case
			update_option(self::TEMP_STYLES_KEY, array());
		}

		$this->_set_up_theme_settings();

		$settings_options = array('theme_fonts', 'layout_style', 'theme_colors', 'button_presets', 'post_image_variants', 'accordion_presets', 'tab_presets', 'menus');
		foreach ($settings_options as $option) {
			$option_value = get_option('upfront_new-' . $option, null);
			if ($option_value !== '' && is_null($option_value) === false) {
				$this->_theme_settings->set($option, $option_value);
			}
			delete_option('upfront_new-' . $option);
		}

		// Activate the theme, if requested so
		if (!empty($form['thx-activate_theme'])) {
			switch_theme($theme_slug);
		}

		//$this->json_get_themes();
		wp_send_json(array(
			'theme' => array(
				'directory' => $theme_slug,
				'name' => $form['thx-theme-name']
			)
		));
	}

	public function add_styles () {
		wp_enqueue_style('upfront-exporter', $this->_plugin_dir_url . '/styles/exporter.css');
	}

	public function add_data ($data) {
		$data['exporter'] = array(
			'url' => plugins_url('', __FILE__),
			'testContent' => $this->_get_preview_content(),
			'postTestContent' => $this->_get_preview_content( null, true ),
			'styledTestContent' => $this->_get_styled_preview_content(),
		);

		return $data;
	}
/*
	public function json_export_part_template () {
error_log(json_encode(array("json_export_part_template", debug_backtrace())));
		global $allowedposttags;
		$allowedposttags['time'] = array('datetime' => true);
		$tpl = isset($_POST['tpl']) ? wp_kses(stripslashes($_POST['tpl']), $allowedposttags) : false;
		$type = isset($_POST['type']) ? $_POST['type'] : false;
		$part = isset($_POST['part']) ? $_POST['part'] : false;
		$id = isset($_POST['id']) ? $_POST['id'] : false;

		if(!$tpl || !$type || !$part || !$id)
			$this->_json->error_msg(__('Not all required data sent.', UpfrontThemeExporter::DOMAIN));

		if($type == 'UpostsModel')
			$type = 'archive';
		else
			$type = 'single';

		$filename = $this->_export_post_part_template($type, $id, $part, $tpl);

		$this->_json->out(array('filename' => $filename));
	}

	protected function _export_post_part_template ($type, $id, $part, $tpl){
error_log(json_encode(array("_export_post_part_template", debug_backtrace())));
		$file_path_parts = array(
			Thx_Fs::PATH_TEMPLATES,
			Thx_Fs::PATH_POSTPARTS,
		);
		$this->_fs->mkdir_p($file_path_parts);

		$file_path_parts[] = "{$type}-{$id}.php";
		$file_path = $this->_fs->get_path($file_path_parts, false);

		$templates = array();
		if($this->_fs->exists($file_path)) $templates = require $file_path;

		$templates[$part] = $tpl;

		$output = $this->_generate_exported_templates($templates);

		$this->_fs->write($file_path_parts, $output);

		return $file_path;
	}
*/
	protected function _generate_exported_templates ($templates){
		$out = '<?php $templates = array(); ob_start();' . "\n\n";

		foreach($templates as $part => $template){
			$out .= "//***** $part\n";
			$out .= "?>$template<?php\n"; //<?
			$out .= '$templates["' . $part . "\"] = ob_get_contents();\n";
			$out .= "ob_clean();\n\n";
		}

		$out .= "ob_end_clean();\n";
		$out .= 'return $templates;';

		return $out;
	}
/*
	public function json_export_post_layout() {
error_log(json_encode(array("json_export_post_layout", debug_backtrace())));
		$layoutData = isset($_POST['layoutData']) ? $_POST['layoutData'] : false;
		$params = isset($_POST['params']) ? $_POST['params'] : false;
		if(!$layoutData || !$params ) $this->_json->error_msg(__('No layout data or cascade sent.', UpfrontThemeExporter::DOMAIN));

		$this->_json->out(array(
			"file" => $this->_save_post_layout( $params, $layoutData ),
		));
	}


	protected function _save_post_layout ($params, $layoutData) {
error_log(json_encode(array("_save_post_layout", debug_backtrace())));
		$file_name = !empty($params['specificity'])
			? $params['type'] . "-" . $params['specificity']
			: $params['type'] . "-" . $params['item']
		;

		$contents = "<?php return " .  $this->_json->stringify_php( $layoutData ) . ";";

		$file_path_parts = array(
			Thx_Fs::PATH_POSTLAYOUTS,
			"{$file_name}.php",
		);
		$file_name = $this->_fs->get_path($file_path_parts, false);

		if (!$this->_fs->exists(dirname($file_name))) {
			$this->_fs->mkdir(dirname($file_name));
		}

		$this->_fs->write($file_path_parts, $contents);
		return $file_name;
	}
*/
  /**
   * Returns fake post content
   *
   * @param string $post_id
   * @param bool $post if it's a post post_type or not
   * @return string
   */
	protected function _get_preview_content ( $post_id = "fake_post", $post = false ) {
		$tpl = Thx_Template::theme();
		$test_content_file = $post
			? 'tpl/postTestContent.html'
			: 'tpl/testContent.html'
		;
		$template_file = "fake_styled_post" === $post_id
			? $tpl->filepath('tpl/testContentStyled.html')
			: $tpl->filepath($test_content_file)
		;

		return $this->_fs->exists($template_file)
			? file_get_contents($template_file)
			: '<p>' . __('some test content', UpfrontThemeExporter::DOMAIN) . '</p>'
		;
	}

	private function _get_styled_preview_content ( $post_id = "fake_styled_post" ) {
		$tpl = Thx_Template::theme();
		$template_file = $tpl->filepath('tpl/testContentStyled.html');

		return $this->_fs->exists($template_file)
			? file_get_contents($template_file)
			: '<p>' . __('some styled test content', UpfrontThemeExporter::DOMAIN) . '</p>'
		;
	}

	/**
	 * This is called to create a stub post for preview purposes.
	 */
	protected function _generate_preview_post ($data) {
		$content = $this->_get_preview_content( $data['post_id'] );
		$post = new WP_Post((object)array(
			'ID' => $data['post_id'],
			'post_type' => (!empty($data['post_type']) ? $data['post_type'] : 'post'),
			'post_status' => 'publish',
			'post_title' => __('Sample Post', UpfrontThemeExporter::DOMAIN),
			'post_content' => $content,
			'post_date' => date("Y-m-d H:i:s"),
			'filter' => 'raw',
			'post_author' => get_current_user_id()
		));
		return $post;
	}

	private function _does_theme_export_images () {
		return get_stylesheet() !== $this->_theme
			? $this->_theme_exports_images
			: apply_filters('upfront-thx-theme_exports_images', $this->_theme_exports_images)
		;
	}

	private function _validate_theme_slug ($slug) {
		return Thx_Sanitize::php_safe($slug);
	}
}
