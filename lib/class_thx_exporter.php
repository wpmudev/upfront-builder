<?php

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
		
		require_once (dirname(__FILE__) . '/class_thx_fs.php');
		$this->_fs = Thx_Fs::get($this->_theme);
		
		$this->_set_up_theme_settings();

		require_once (dirname(__FILE__) . '/class_thx_json.php');
		$this->_json = new Thx_Json;
	}

	private function _set_up_theme_settings () {
		$settings_file = empty($this->_theme) || in_array($this->_theme, array('upfront', 'theme'))
			? $this->_fs->get_path('settings.php')
			: $this->_fs->get_path('settings.php', false)
		;
		//$settings_file = $settings_file ? $settings_file : 'settings.php'; // Do NOT auto-init the settings file!!!
		
		$settings = new Upfront_Theme_Settings($settings_file);
		$this->_theme_settings = $settings;

		// Override the child theme settings
		$theme = Upfront_ChildTheme::get_instance();
		$theme->set_theme_settings($settings);
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
		
		$ajaxPrefix = 'wp_ajax_upfront_thx-';

		add_filter('stylesheet_directory', array($this, 'process_stylesheet_directory'), 100);
		
		add_action('wp_footer', array($this, 'injectDependencies'), 100);
		add_action($ajaxPrefix . 'create-theme', array($this, 'createTheme'));
		add_action($ajaxPrefix . 'get-themes', array($this, 'getThemesJson'));

		add_action($ajaxPrefix . 'export-layout', array($this, 'exportLayout'));
		add_action($ajaxPrefix . 'export-post-layout', array($this, 'ajax_export_post_layout'));

		add_action($ajaxPrefix . 'export-part-template', array($this, 'ajax_export_part_template'));

		add_action($ajaxPrefix . 'export-element-styles', array($this, 'exportElementStyles'));
		add_action($ajaxPrefix . 'delete-element-styles', array($this, 'deleteElementStyles'));

		add_action( 'wp_enqueue_scripts', array($this,'addStyles'));

		add_filter('upfront_data', array($this, 'addData'));

		add_filter('upfront_theme_layout_cascade', array($this, 'getThemeLayoutCascade'), 10, 2);
		add_filter('upfront_theme_postpart_templates_cascade', array($this, 'getThemePostpartTemplatesCascade'), 10, 2);

		add_filter('upfront_prepare_theme_styles', array($this, 'prepareThemeStyles'), 15);
		add_filter('upfront_prepare_typography_styles', array($this, 'prepareTypographyStyles'), 15);

		add_filter('upfront_load_layout_from_database', '__return_false');

		add_action('upfront_update_theme_colors', array($this, 'updateThemeColors'));
		add_action('upfront_update_post_image_variants', array($this, 'updatePostImageVariants'));
		add_action('upfront_update_button_presets', array($this, 'updateButtonPresets'));
		add_action('upfront_update_theme_fonts', array($this, 'updateThemeFonts'));
		add_action('upfront_update_responsive_settings', array($this, 'updateResponsiveSettings'));

		add_action('upfront_save_tab_preset', array($this, 'saveTabPreset'));
		add_action('upfront_delete_tab_preset', array($this, 'deleteTabPreset'));

		add_action('upfront_save_accordion_preset', array($this, 'saveAccordionPreset'));
		add_action('upfront_delete_accordion_preset', array($this, 'deleteAccordionPreset'));

		add_action('upfront_get_stylesheet_directory', array($this, 'getStylesheetDirectory'));
		add_action('upfront_get_stylesheet', array($this, 'getStylesheet'));

		add_action('upfront_upload_icon_font', array($this, 'uploadIconFont'));
		add_action('upfront_update_active_icon_font', array($this, 'updateActiveIconFont'));


// ALL OF THESE ARE DISABLED NOW
// ... because we're overriding the child's theme settings instead
		/*
		// This set of actions will force child theme class to load data from theme files
		// since child theme class is also hooked into this actions and loads data from
		// theme files if data is empty. So all these actions will reset data to empty.
		// These actions are lower priority than actions in child theme so they will be
		// executed first.
		add_action('upfront_get_theme_styles', array($this, 'getThemeStyles'), 5);
		add_action('upfront_get_global_regions', array($this, 'getGlobalRegions'), 5, 2);
		add_action('upfront_get_responsive_settings', array($this, 'getResponsiveSettings'), 5);
		add_action('upfront_get_layout_properties', array($this, 'getLayoutProperties'), 5);

		add_action('upfront_get_theme_fonts', array($this, 'getEmptyArray'), 5, 2);
		add_action('upfront_get_icon_fonts', array($this, 'getEmptyArray'), 5, 2);
		add_action('upfront_get_theme_colors', array($this, 'getEmptyArray'), 5, 2);
		add_action('upfront_get_post_image_variants', array($this, 'getEmptyArray'), 5, 2);
		add_action('upfront_get_button_presets', array($this, 'getEmptyArray'), 5, 2);
		add_action('upfront_get_tab_presets', array($this, 'getEmptyArray'), 5, 2);
		add_action('upfront_get_accordion_presets', array($this, 'getEmptyArray'), 5, 2);
		*/

		// Intercept theme images loading and verify that the destination actually exists
		add_action('wp_ajax_upfront-media-list_theme_images', array($this, 'check_theme_images_destination_exists'), 5);

		add_filter('upfront-this_post-unknown_post', array($this, 'prepare_preview_post'), 10, 2);
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

	public function prepareThemeStyles($styles) {
		// In editor mode this would load element styles to main stylesheet. In builder mode
		// don't load any since styles are gonna be loaded each separately.
		return '';
	}

	public function prepareTypographyStyles($styles) {
		return '';
	}

	// TODO this should go to upfront theme!
	public function getThemeLayoutCascade($cascade, $base_filename) {
		// Override brute force to ensure single-something page get their specific postlayout loaded
		$layout_cascade = !empty($_POST['layout_cascade']) ? $_POST['layout_cascade'] : false;
		if (empty($layout_cascade)) return $cascade;
		$post_type = !empty($_POST['post_type']) ? $_POST['post_type'] : false;
		$new_cascade = array(
			trailingslashit(wp_normalize_path(dirname($base_filename))) . $layout_cascade['item'] . '.php', // So... make sure this goes first, that's the most likely candidate
			$base_filename . $layout_cascade['item'] . '.php',
			$base_filename . $layout_cascade['type'] . '.php'
		);
		if (!empty($post_type) && !in_array($post_type, array_values($layout_cascade))) $new_cascade[] = $base_filename . $post_type . '.php';
		return $new_cascade;
	}

	// TODO this should go to upfront theme!
	public function getThemePostpartTemplatesCascade($cascade, $base_filename) {
		// Override brute force to ensure single-something page get page specific post layout parts loaded
		$layout_cascade = !empty($_POST['layout_cascade']) ? $_POST['layout_cascade'] : false;
		if (empty($layout_cascade)) return $cascade;

		$cascade = array(
			$base_filename . $layout_cascade['item'] . '.php',
			$base_filename . $layout_cascade['type'] . '.php'
		);
	}

	public function getStylesheet($stylesheet) {
		return upfront_exporter_get_stylesheet();
	}

	public function uploadIconFont() {
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
				'type' => 'theme-defined',// default, theme-defined or user-defined ->
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

	public function updateActiveIconFont() {
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

	public function getStylesheetDirectory($stylesheetDirectory) {
		return $this->_fs->get_path(array(
			upfront_exporter_get_stylesheet()
		));
	}

	function injectDependencies() {
		if (!is_user_logged_in()) return false; // Do not inject for non-logged in user

		$themes = $this->getThemes();
		include($this->_plugin_dir . '/templates/dependencies.php');
	}

	public function getThemesJson() {
		$this->_json->out($this->getThemes());
	}

	protected function getThemes() {
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

	public function exportLayout() {
		$data = $_POST['data'];
		if (empty($data['theme']) || empty($data['template'])) {
			$this->_json->error_msg(__('Theme & template must be choosen.', UpfrontThemeExporter::DOMAIN), 'missing_data');
		}

		$this->_theme = $data['theme'];
		$this->_fs->set_theme($this->_theme);

		$regions = json_decode(stripslashes($data['regions']));

		$template = "<?php\n";

		foreach($regions as $region) {
			if($region->name === 'shadow') continue;
			if(!empty($region->scope) && $region->scope === 'global' && (!$region->container || $region->name == $region->container)) {
				$global_region_filename = "get_stylesheet_directory() . DIRECTORY_SEPARATOR . '" . Thx_Fs::PATH_REGIONS . "' . DIRECTORY_SEPARATOR . '{$region->name}.php'";
				$template .= "if (file_exists({$global_region_filename})) include({$global_region_filename});\n\n"; // <-- Check first
				$this->_global_regions[$region->name] = $region;
				//$this->updateGlobalRegionTemplate($region->name);
				continue;
			}
			if($region->container === 'lightbox') {
				$this->exportLightbox($region);
				continue;
			}
			if(!empty($region->scope) && $region->scope === 'global' && ($region->container && $region->name != $region->container)) {
				$handle = $this->handleGlobalSideregion($region, $regions);
				if ( !$handle ) {
					$global_region_filename = "get_stylesheet_directory() . DIRECTORY_SEPARATOR . '" . Thx_Fs::PATH_REGIONS . "' . DIRECTORY_SEPARATOR . '{$region->name}.php'";
					$template .= "\$region_container = '$region->container';\n";
					$template .= "\$region_sub = '$region->sub';\n";
					$template .= "if (file_exists({$global_region_filename})) include({$global_region_filename});\n\n"; // <-- Check first
				}
				continue;
			}
			$template .= $this->renderRegion($region);
		}
		
		foreach($this->_global_regions as $region_name => $region){
			$this->updateGlobalRegionTemplate($region_name);
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

		$this->saveLayoutToTemplate(
			array(
				'template' => $data['template'],
				'content' => $template,
				'layout' => $specific_layout,
				'functions' => $file
			)
		);

		die;
	}

	protected function handleGlobalSideregion($region, $regions = array()) {
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

	public function exportElementStyles() {
		$data = stripslashes_deep($_POST['data']);
		if (empty($data['stylename']) || empty($data['styles']) || empty($data['elementType'])) {
			$this->_json->error_msg(__('Some data is missing.', UpfrontThemeExporter::DOMAIN), 'missing_data');
		}

		if ($data['elementType'] === 'layout') {
			if (upfront_exporter_is_start_page()) {
				update_option('upfront_new-layout_style', addcslashes($data['styles'], "'\\"));
				return;
			}
			$this->_theme_settings->set('layout_style', addcslashes($data['styles'], "'\\"));
			return;
		}

		$stylesheet = !empty($_POST['stylesheet']) && 'upfront' !== $_POST['stylesheet']
			? $_POST['stylesheet']
			: false
		;
		if (!empty($stylesheet)) $this->_export_element_style($stylesheet, $data);
		else $this->_temporarily_store_export_file($data);
		$this->_json->out(__('Exported', UpfrontThemeExporter::DOMAIN));
	}

	/**
	 * Actually writes element styles to files.
	 */
	private function _export_element_style ($name, $data) {
		$this->_theme = $name;
		$style = stripslashes($data['styles']);
		$style = $this->makeUrlsPassiveRelative($style);

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

	public function deleteElementStyles() {
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

	protected function renderRegion($region, $use_var = false) {
		$data = (array)$region;
		$name = preg_replace('/[^_a-z0-9]/i', '_', $data['name']);

		$data['type'] = isset( $data['type'] ) ? $data['type'] : "";
		$data['scope'] = isset( $data['scope'] ) ? $data['scope'] : "";
		$main = array(
			'name' => $data['name'],
			'title' => $data['title'],
			'type' => $data['type'],
			'scope' => $data['scope']
		);

		if ( $use_var ){ // just pass a variable string, the variable will be defined before this method call, to allow the use of sidebar region globally
			$main['container'] = "\${$name}_container";
			$main['sub'] = "\${$name}_sub";
		}
		else {
			if (!empty($data['container']) && $data['name'] !== $data['container']) $main['container'] = $data['container'];
			else $main['container'] = $data['name'];
	
			if (!empty($data['sub'])) $main['sub'] = $data['sub'];
		}
		if (!empty($data['position'])) $main['position'] = $data['position'];
		if (!empty($data['allow_sidebar'])) $main['allow_sidebar'] = $data['allow_sidebar'];
		if (!empty($data['restrict_to_container'])) $main['restrict_to_container'] = $data['restrict_to_container'];
		if (!empty($data['behavior'])) $main['behavior'] = $data['behavior'];
		if (!empty($data['sticky'])) $main['sticky'] = $data['sticky'];
		$secondary = $this->parseProperties($data['properties']);

		// Deal with the slider images
		if (!empty($secondary['background_slider_images'])) {
			foreach ($secondary['background_slider_images'] as $idx => $img) {
				$source = get_attached_file($img);
				if (empty($source)) continue;
				// copy file to theme folder
				$file = basename($source);
				$destination = $this->_fs->get_path('images') . $file;
				@copy($source, $destination);
				$secondary['background_slider_images'][$idx] = "/images/{$file}";
			}
		}

		$output = '';
		if ( $use_var ){
			$output .= '$' . $name . '_container = ( !empty($region_container) ? $region_container : "' . ( !empty($data['container']) ? $data['container'] : '' ) . '" );' . "\n";
			$output .= '$' . $name . '_sub = ( !empty($region_sub) ? $region_sub: "' . ( !empty($data['sub']) ? $data['sub'] : '' ) . '" );' . "\n\n";
		}

		$output .= '$'. $name . ' = upfront_create_region(
			' . $this->_json->stringify($main) .',
			' . $this->_json->stringify($secondary) . '
			);' . "\n";

		$output .= $this->renderModules($name, $data['modules'], $data['wrappers']);

		$output .= "\n" . '$regions->add($' . $name . ");\n\n";
		return $output;
	}

	protected function renderModules($name, $modules, $wrappers, $group = '') {
		$region_lightboxes = array();
		$output = '';
		$export_images = $this->_does_theme_export_images();
		$exported_wrappers = array();
		foreach ($modules as $i => $m) {
			$nextModule = false;
			$module = (array) $m;

			// Looking for next module, so that we can compare their wrapper_id to see if we need to add close_wrapper property
			if(!empty($modules) && sizeof($modules) > ($i+1))
				$nextModule = $this->parseProperties($modules[$i+1]->properties);

			$isGroup = (isset($module['modules']) && isset($module['wrappers']));

			$moduleProperties = $this->parseProperties($module['properties']);
			$props = $this->parseModuleClass($moduleProperties['class']);
			$props['id'] = $moduleProperties['element_id'];
			if (!$isGroup)
				$props['options'] = $this->parseProperties($module['objects'][0]->properties);
            
            foreach($moduleProperties as $property => $value){
                if ( !in_array($property, array('class', 'element_id', 'breakpoint', 'has_settings')) )
                    $props[$property] = $value;
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
					$props['new_line'] = 'true';
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

			$type = $this->getObjectType(
				(!empty($props['options']['view_class']) ? $props['options']['view_class'] : false)
			);

			// Check for hardcoded emails
			if ('Ucontact' == $type && !empty($props['options']['form_email_to']) && preg_match('/@/', $props['options']['form_email_to'])) {
				$props['options']['form_email_to'] = '';
			}

			// Check for lightboxes
			switch($type) {
				case 'Uimage':
					if ($props['options']['when_clicked'] === 'lightbox') $region_lightboxes[] = $props['options']['image_link'];
					if (!$export_images && empty($props['options']['src']) && !empty($props['options']['image_status']) && 'starting' !== $props['options']['image_status']) {
						// If we're not exporting images AND if we're dealing with zeroed-out image, update its status
						$props['options']['image_status'] = 'starting';
					}
					break;
				case 'PlainTxt':
					$region_lightboxes = array_merge($region_lightboxes, $this->getLightBoxesFromText($props));
					break;
				case 'Button':
					$region_lightboxes = array_merge($region_lightboxes, $this->getLightBoxesFromButton($props));
					break;
				case 'Unewnavigation':
					$region_lightboxes = array_merge($region_lightboxes, $this->getLightBoxesFromMenu($props));
					break;
			}

			if ($type === 'Unewnavigation') {
				$this->addMenuFromElement($props);
				$props['options']['menu_id'] = false; // Should not be set in exported layout
			}

			if ($isGroup){
				$output .= "\n" . '$' . $name . '->add_group(' . $this->_json->stringify($props) . ");\n";
				$output .= $this->renderModules($name, $module['modules'], $module['wrappers'], $props['id']);
			} else {
				if (!empty($group)){
					$props['group'] = $group;
				}
				$output .= "\n" . '$' . $name . '->add_element("' . $type . '", ' . $this->_json->stringify($props) . ");\n";
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

	protected function getLightBoxesFromMenu($properties) {
		$lightboxes = array();

		$menu_id = $properties['options']['menu_id'];
		$menu_items = wp_get_nav_menu_items($menu_id);
      if( is_array( $menu_items ) ){
        foreach($menu_items as $menu_item) {
          if(strpos($menu_item->url, '#ltb-') === false) continue;
          $lightboxes[] = $menu_item->url;
        }
      }
		return $lightboxes;
	}

	protected function getLightBoxesFromText($properties) {

		if (strpos($properties['options']['content'], '#ltb-') === false)
			return array();

		$matches = array();
		preg_match_all('#href="(\#ltb-.+?)"#', $properties['options']['content'], $matches);

		if(is_array($matches[1]))
			foreach($matches[1] as $match)
				if(strpos($match, '#ltb-') === false)
					unset($match);

		return is_array($matches[1]) ? $matches[1] : array();
	}

	protected function getLightBoxesFromButton($properties) {

		if (strpos($properties['options']['href'], '#ltb-') === false)
			return array();
		else
			return array($properties['options']['href']);

	}

	protected function addMenuFromElement($properties) {
		$menu_id = $properties['options']['menu_id'];

		$menu_object = wp_get_nav_menu_object($menu_id);
		$menu_items = wp_get_nav_menu_items($menu_id);
        if( is_array( $menu_items ) ){
          foreach($menu_items as $menu_item) {
            if(strpos($menu_item->url, site_url()) === false) continue;

            // Fix lightboxes and other anchor urls
            $menu_item->url = preg_replace('#' . get_site_url() . '/create_new/.+?(\#[A-Za-z_-]+)#', '\1', $menu_item->url);

            // Fix hardcoded site url
            $menu_item->url = str_replace(site_url(), '%siteurl%', $menu_item->url);
          }
        }

		$menu = array(
			'id' => false, // Shouldn't be set
			'slug' => $menu_object->slug,
			'name' => $menu_object->name,
			'description' => $menu_object->description,
			'items' => $menu_items
		);

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

	protected function getObjectType($class){
		return str_replace('View', '', $class);
	}

	protected function parseProperties($props){
		$parsed = array();
		if (empty($props)) return $parsed;
		foreach($props as $p){
			$parsed[$p->name] = isset( $p->value ) ? $p->value : "";
		}
		return $parsed;
	}

	protected function parseModuleClass($class){
		$classes = explode(' ', $class);
		$properties = array();
		foreach ($classes as $c) {
			if(preg_match('/^c\d+$/', $c))
				$properties['columns'] = str_replace('c', '', $c);
			else if(preg_match('/^ml\d+$/', $c))
				$properties['margin_left'] = str_replace('ml', '', $c);
			else if(preg_match('/^mr\d+$/', $c))
				$properties['margin_right'] = str_replace('mr', '', $c);
			else if(preg_match('/^mt\d+$/', $c))
				$properties['margin_top'] = str_replace('mt', '', $c);
			else if(preg_match('/^mb\d+$/', $c))
				$properties['margin_bottom'] = str_replace('mb', '', $c);
		}
		return $properties;
	}

	public function saveLayout() {
		$data = $_POST['data'];

		if (empty($data['theme']) || empty($data['template'])) {
			$this->_json->error_msg(__('Theme & template must be choosen.', UpfrontThemeExporter::DOMAIN), 'missing_data');
		}

		$this->_theme = $data['theme'];

		foreach($data['layout']['layouts'] as $index=>$layout) {
			$this->saveLayoutToTemplate(
				array(
					'template' => $index === 'main' ? $data['template'] : $index,
					'content' => stripslashes($layout)
				)
			);
		}
		die;
	}

	protected function saveLayoutToTemplate($layout) {
		$template = preg_replace('/[^-_a-z0-9]/i', '', $layout['template']);
		$content = $layout['content'];

		//$template_images_dir = $this->getThemePath('images', $template);
		$template_images_dir_args = array(
			Thx_Fs::PATH_IMAGES,
			$template,
		);
		$this->_fs->mkdir_p($template_images_dir_args);
		$template_images_dir = $this->_fs->get_path($template_images_dir_args);

		// Copy all images used in layout to theme directory
		$content = $this->exportImages($content, $template, $template_images_dir);

		$content = $this->makeUrlsRelative($content);

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
			$value = $this->makeUrlsPassiveRelative($value);
			
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
			$page = preg_replace('/[^-_a-z0-9]/i', '', $layout['layout']);
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
				$tpl_content = $contents = $this->template($this->_plugin_dir . '/templates/page-template.php', $page_layout_data);
				// Recursive definition yay

				$this->_fs->write(array(
					"{$tpl_filename}.php",
				), $tpl_content);
			}
		}
	}

	protected function makeUrlsRelative($content, $quote='"') {
		if (!in_array($quote, array('"', "'"))) $quote = '"';
		// Fix lightboxes and other anchor urls
		$content = preg_replace('#' . get_site_url() . '/create_new/.+?(\#[A-Za-z_-]+)#', '\1', $content);

		// Okay, so now the imported image is hard-linked to *current* theme dir...
		// Not what we want - the images don't have to be in the current theme, not really
		// Ergo, fix - replace all the hardcoded stylesheet URIs to dynamic ones.
		$content = str_replace(get_stylesheet_directory_uri(), $quote . ' . get_stylesheet_directory_uri() . ' . $quote, $content);

		// Replace all urls that reffer to current site with get_current_site
		$content = str_replace(get_site_url(), $quote . ' . get_site_url() . ' . $quote, $content);

		return $content;
	}

	/**
	 * This will relativize URLs for *passive* (non-PHP) stuffs
	 * @param  string $content Content to process
	 * @return string Content with URLs parsed.
	 */
	protected function makeUrlsPassiveRelative ($content) {
		$base = preg_quote(get_stylesheet_directory_uri(), '/');
		$rpl = Upfront_ChildTheme::THEME_BASE_URL_MACRO;

		return preg_replace("/{$base}/", $rpl, $content);
	}

	protected function exportImages($content, $template, $template_images_dir) {
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

		//preg_match_all("#[\"'](http.+?(jpg|jpeg|png|gif))[\"']#", $content, $matches); // Won't recognize escaped quotes (such as content images), and will find false positives such as "httpajpg"
		preg_match_all("#\b(https?://.+?\.(jpg|jpeg|png|gif))\b#", $content, $matches);

		$images_used_in_template = array();
		$separator = '/';

		$export_images = $this->_does_theme_export_images();
		$theme_ui_path = $this->_fs->get_path(Thx_Fs::PATH_UI);

		// matches[1] containes full image urls
		foreach ($matches[1] as $image) {

			// If the exports aren't allowed...
			if (!$export_images) {
				$this_theme_relative_ui_root = '/' . basename($this->_fs->get_root_path()) . '/' . Thx_Fs::PATH_UI . '/';
				$is_ui_image = false !== strpos($image, $this_theme_relative_ui_root);

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
			if (strpos($image, get_theme_root_uri()) !== false) {
				$relative_url = explode("/{$_themes_root}/", $image);
				$source_root = get_theme_root();
			}
			// Image is from uploads
			if (strpos($image, 'uploads') !== false) {
				$relative_url = explode("/{$_uploads_root}/", $image);
				$source_root = $uploads_dir['basedir'];
			}
			$relative_url = $relative_url[1];

			// Get image filename
			$source_path_parts = explode('/', $relative_url);
			$image_filename = end($source_path_parts);

			// Get source and destination image
			$source_relative_path = str_replace('/', $separator, $relative_url);
			$source_image = $source_root . $separator . $source_relative_path;

			$destination_image = $template_images_dir . $image_filename;

			// Copy image
			if (file_exists($source_image)) {
				$result = copy($source_image, $destination_image);
			}
			$images_used_in_template[] = $destination_image;

			// Replace images url root with stylesheet uri
			$image_uri = get_stylesheet_directory_uri() . '/images/' . $template . '/' . $image_filename;
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

	protected function updateGlobalRegionTemplate($region_name) {
		$region = $this->_global_regions[$region_name];
		$is_main = ( !$region->container || $region->name == $region->container );
		$content = "<?php\n";
		$render_before = array();
		$render_after = array();
		if ($is_main && isset($this->_global_sideregions[$region->name])) {
			foreach ($this->_global_sideregions[$region->name] as $sub => $sub_region){
				if ($sub == 'left' || $sub == 'top')
					$render_before[] = $this->renderRegion($sub_region);
				else
					$render_after[] = $this->renderRegion($sub_region);
			}
		}
		
		$content .= join('', $render_before);
		$content .= $this->renderRegion($region, !$is_main);
		$content .= join('', $render_after);

		// Start with global region creation
		$greg_root = $this->_fs->construct_theme_path(Thx_Fs::PATH_REGIONS);
		if (!$this->_fs->exists($greg_root)) $this->_fs->mkdir($greg_root);

		$template_images_dir = $this->_fs->mkdir_p(array(
			Thx_Fs::PATH_IMAGES, 
			Thx_Fs::PATH_REGIONS, 
			$region->name
		));

		// Copy all images used in layout to theme directory
		$content = $this->exportImages($content, Thx_Fs::PATH_REGIONS . '/' . $region->name, $template_images_dir);

		$content = $this->makeUrlsRelative($content);

		$this->_fs->write(array(
			Thx_Fs::PATH_REGIONS,
			"{$region->name}.php",
		), $content);
	}

	protected function exportLightbox($region) {
		$this->_fs->mkdir_p(array(
			Thx_Fs::PATH_REGIONS,
			Thx_Fs::PATH_LIGHTBOXES,
		));

		ob_start();

		$content = "<?php\n";
		$content .= $this->renderRegion($region);

		$this->_fs->write(array(
			Thx_Fs::PATH_REGIONS,
			Thx_Fs::PATH_LIGHTBOXES,
			"{$region->name}.php",
		), $content);
	}

	public function updateThemeFonts($theme_fonts) {
		if (upfront_exporter_is_start_page()) {
			update_option('upfront_new-theme_fonts', json_encode($theme_fonts));
			return;
		}
		$this->_theme_settings->set('theme_fonts', json_encode($theme_fonts));
	}

	public function updateResponsiveSettings($responsive_settings) {
		$this->_theme_settings->set('responsive_settings', json_encode($responsive_settings));
	}

	public function updateThemeColors($theme_colors) {
		if (upfront_exporter_is_start_page()) {
			update_option('upfront_new-theme_colors', json_encode($theme_colors));
			return;
		}
		$this->_theme_settings->set('theme_colors', json_encode($theme_colors));
	}

	public function updateButtonPresets($button_presets) {
		if (upfront_exporter_is_start_page()) {
			update_option('upfront_new-button_presets', json_encode($button_presets));
			return;
		}
		$this->_theme_settings->set('button_presets', json_encode($button_presets));
	}

	public function updatePostImageVariants($post_image_variant) {
		if (upfront_exporter_is_start_page()) {
			update_option('upfront_new-post_image_variants', json_encode($post_image_variant));
			return;
		}
		$this->_theme_settings->set('post_image_variants', json_encode($post_image_variant));
	}

	public function saveTabPreset() {
		$this->saveElementPreset('tab');
	}

	public function deleteTabPreset() {
		$this->deleteElementPreset('tab');
	}

	public function saveAccordionPreset() {
		$this->saveElementPreset('accordion');
	}

	public function deleteAccordionPreset() {
		$this->deleteElementPreset('accordion');
	}

	public function saveElementPreset($slug) {
		$this->updateElementPreset($slug);
	}

	public function deleteElementPreset($slug) {
		$this->updateElementPreset($slug, $false);
	}

	protected function updateElementPreset($slug, $delete = false) {
		if (!isset($_POST['data'])) {
			return;
		}

		$presetProperty = $slug . '_presets';

		$properties = $_POST['data'];

		if (upfront_exporter_is_start_page()) {
			$presets = json_decode(get_option('upfront_new-' . $presetProperty), true);
		} else {
			$presets = json_decode($this->_theme_settings->get($presetProperty), true);
		}

		$result = array();

		foreach ($presets as $preset) {
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
		$this->_theme_settings->set($presetProperty, json_encode($result));
	}

	private function _create_functions_file ($slug) {
		$data = array(
			'name' => ucwords(str_replace('-', '_', sanitize_html_class($slug))),
			'slug' => $slug,
			'pages' => '',
			'exports_images' => $this->_does_theme_export_images() ? 'true' : 'false', // Force conversion to string so it can be expanded in the template.
		);

		$contents = $this->template($this->_plugin_dir . '/templates/functions.php', $data);

		$this->_fs->write(array(
			'functions.php'
		), $contents);
	}

	protected function template($path, $data){
		$template = file_get_contents($path);
		foreach ($data as $key => $value) {
			$template = str_replace('%' . $key . '%', $value, $template);
		}
		return $template;
	}

	public function createTheme() {
		$form = array();
		parse_str($_POST['form'], $form);

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
			'thx-activate_theme' => false,
			'thx-export_with_images' => false,
		));

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
		$stylesheet_header = "/*\n";
		$stylesheet_header .= sprintf("Theme Name:%s\nTemplate: %s\n",
			$form['thx-theme-name'],
			$form['thx-theme-template']
		);
		if ($uri = $form['thx-theme-uri']) $stylesheet_header .= "Theme URI: $uri\n";
		if ($author = $form['thx-author']) $stylesheet_header .= "Author: $author\n";
		if ($author_uri = $form['thx-author-uri']) $stylesheet_header .= "Author URI: $author_uri\n";
		if ($description = $form['thx-theme-description']) $stylesheet_header .= "Description: $description\n";
		if ($version = $form['thx-theme-version']) $stylesheet_header .= "Version: $version\n";
		if ($licence = $form['thx-theme-licence']) $stylesheet_header .= "Licence: $licence\n";
		if ($licence_uri = $form['thx-theme-licence-uri']) $stylesheet_header .= "Licence URI: $licence_uri\n";
		if ($tags = $form['thx-theme-tags']) $stylesheet_header .= "Tags: $tags\n";
		if ($text_domain = $form['thx-theme-text-domain']) $stylesheet_header .= "Text Domain: $text_domain\n";
		$stylesheet_header .= "*/\n";
		$stylesheet_header .= "@import url(../{$form['thx-theme-template']}/style.css);";

		$this->_fs->write(array(
			'style.css'
		), $stylesheet_header);

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
		$default_layouts_dir = trailingslashit($this->_fs->construct_path(array(
			$this->_plugin_dir,
			'templates',
			'default_layouts',
		)));
		$theme_layouts_dir = trailingslashit($this->_fs->construct_path(array(
			Thx_Fs::PATH_LAYOUTS
		)));
		$default_layouts = glob($default_layouts_dir . '*');
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

		$this->getThemesJson();
	}

	public function addStyles() {
		wp_enqueue_style('upfront-exporter', $this->_plugin_dir_url . '/exporter.css');
	}

	public function addData($data) {
		$data['exporter'] = array(
			'url' => plugins_url('', __FILE__),
			'testContent' => $this->_get_preview_content(),
			'postTestContent' => $this->_get_preview_content( null, true ),
			'styledTestContent' => $this->_get_styled_preview_content(),
		);

		return $data;
	}

	public function ajax_export_part_template() {
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

		$filename = $this->export_post_part_template($type, $id, $part, $tpl);

		$this->_json->out(array('filename' => $filename));
	}

	protected function export_post_part_template($type, $id, $part, $tpl){
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

		$output = $this->generate_exported_templates($templates);

		$this->_fs->write($file_path_parts, $output);

		return $file_path;
	}

	protected function generate_exported_templates($templates){
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

	public function ajax_export_post_layout() {
		$layoutData = isset($_POST['layoutData']) ? $_POST['layoutData'] : false;
		$params = isset($_POST['params']) ? $_POST['params'] : false;
		if(!$layoutData || !$params ) $this->_json->error_msg(__('No layout data or cascade sent.', UpfrontThemeExporter::DOMAIN));

		$this->_json->out(array(
			"file" => $this->save_post_layout( $params, $layoutData ),
		));
	}


	protected function save_post_layout( $params, $layoutData ) {
		$file_name = !empty($params['specificity'])
			? $params['type'] . "-" . $params['specificity']
			: $params['type'] . "-" . $params['item']
		;
		
		$contents = "<?php return " .  $this->_json->stringify( $layoutData ) . ";";

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

  /**
   * Returns fake post content
   *
   * @param string $post_id
   * @param bool $post if it's a post post_type or not
   * @return string
   */
	protected function _get_preview_content ( $post_id = "fake_post", $post = false ) {
		$test_content_file = $post ? '/templates/tpl/postTestContent.html' : '/templates/tpl/testContent.html';
		$template_file = $post_id === "fake_styled_post" ? upfront_get_template_path('preview_post', dirname(__FILE__) . '/templates/tpl/testContentStyled.html')  : upfront_get_template_path('preview_post', dirname(__FILE__) . $test_content_file);
		if (file_exists($template_file)) {
			ob_start();
			include($template_file);
			$content = ob_get_clean();
		} else $content = '<p>' . __('some test content', UpfrontThemeExporter::DOMAIN) . '</p>';
		return $content;
	}

	private function _get_styled_preview_content ( $post_id = "fake_styled_post" ) {
	  $template_file =  upfront_get_template_path('preview_post', dirname(__FILE__) . '/templates/tpl/testContentStyled.html');
	  if (file_exists($template_file)) {
		ob_start();
		include($template_file);
		$content = ob_get_clean();
	  } else $content = '<p>' . __('some styled test content', UpfrontThemeExporter::DOMAIN) . '</p>';
	  return $content;
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
		$slug = preg_replace('/[^-_a-z0-9]/i', '', $slug);
		return $this->_is_valid_slug($slug)	&& $this->_is_not_declared_slug($slug)
			? $slug
			: false
		;
	}

	private function _is_not_declared_slug ($slug) {
		$functions = get_defined_functions();
		if (!empty($functions['user']) && in_array($slug, $functions['user'])) return false;
		if (!empty($functions['internal']) && in_array($slug, $functions['internal'])) return false;
		if (in_array($slug, get_declared_classes())) return false;
		return true;
	}

	private function _is_valid_slug ($slug) {
		$reserved = array(
			"__halt_compiler",
			"abstract",
			"and",
			"array",
			"as",
			"break",
			"callable",
			"case",
			"catch",
			"class",
			"clone",
			"const",
			"continue",
			"declare",
			"default",
			"die",
			"do",
			"echo",
			"else",
			"elseif",
			"empty",
			"enddeclare",
			"endfor",
			"endforeach",
			"endif",
			"endswitch",
			"endwhile",
			"eval",
			"exit",
			"extends",
			"final",
			"finally",
			"for",
			"foreach",
			"function",
			"global",
			"goto",
			"if",
			"implements",
			"include",
			"include_once",
			"instanceof",
			"insteadof",
			"interface",
			"isset",
			"list",
			"namespace",
			"new",
			"or",
			"print",
			"private",
			"protected",
			"public",
			"require",
			"require_once",
			"return",
			"static",
			"switch",
			"throw",
			"trait",
			"try",
			"unset",
			"use",
			"var",
			"while",
			"xor",
			"yield",
		);
		return !in_array($slug, $reserved);
	}
}