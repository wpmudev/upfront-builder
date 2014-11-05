<?php
/*
Plugin Name: Upfront Theme Exporter
Plugin URI: http://premium.wpmudev.com/
Description: Exports upfront page layouts to theme.
Version: 0.0.1
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

include_once 'util.php';
include_once 'phpon.php';
class UpfrontThemeExporter {

	const TEMP_STYLES_KEY = 'uf-thx-temporary_styles';

	protected $pluginDirUrl;
	protected $pluginDir;
	protected $global_regions = array();
	protected $global_sideregions = array();

	private $_theme_exports_images = true; // Export images by default, for legacy themes

	var $DEFAULT_ELEMENT_STYLESHEET = 'elementStyles.css';

	public function __construct() {

		$this->pluginDir = dirname(__FILE__);
		$this->pluginDirUrl = plugin_dir_url(__FILE__);

		$this->theme = upfront_exporter_get_stylesheet();

		$this->themeSettings = new Upfront_Theme_Settings($this->getThemePath(false) . 'settings.php');

		$ajaxPrefix = 'wp_ajax_upfront_thx-';

		// Clean up the temporary styles on each load, if not doing AJAX
		if (!is_admin() && !(defined('DOING_AJAX') && DOING_AJAX) && is_user_logged_in()) update_option(self::TEMP_STYLES_KEY, array());

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

		add_action('upfront_get_stylesheet_directory', array($this, 'getStylesheetDirectory'));
		add_action('upfront_get_stylesheet', array($this, 'getStylesheet'));

		// This set of actions will force child theme class to load data from theme files
		// since child theme class is also hooked into this actions and loads data from
		// theme files if data is empty. So all these actions will reset data to empty.
		// These actions are lower priority than actions in child theme so they will be
		// executed first.
		add_action('upfront_get_theme_styles', array($this, 'getThemeStyles'), 5);
		add_action('upfront_get_global_regions', array($this, 'getGlobalRegions'), 5, 2);
		add_action('upfront_get_responsive_settings', array($this, 'getResponsiveSettings'), 5);
		add_action('upfront_get_theme_fonts', array($this, 'getThemeFonts'), 5, 2);
		add_action('upfront_get_theme_colors', array($this, 'getThemeColors'), 5, 2);
		add_action('upfront_get_post_image_variants', array($this, 'getPostImageVariants'), 5, 2);
		add_action('upfront_get_button_presets', array($this, 'getButtonPresets'), 5, 2);
		add_action('upfront_get_tab_presets', array($this, 'getTabPresets'), 5, 2);
		add_action('upfront_get_layout_properties', array($this, 'getLayoutProperties'), 5);

		// Intercept theme images loading and verify that the destination actually exists
		add_action('wp_ajax_upfront-media-list_theme_images', array($this, 'check_theme_images_destination_exists'), 5);

		add_filter('upfront-this_post-unknown_post', array($this, 'prepare_preview_post'), 10, 2);
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

	public function getThemeStyles($styles) {
		if (upfront_exporter_is_start_page()) {
			// Provide empty defaults
			return array('plain_text' => array());
		}

		$styles = array();

		return $styles;
	}

	public function getGlobalRegions($scope_region, $scope_id) {
		return array();
	}

	public function getLayoutProperties($properties) {
		if (upfront_exporter_is_start_page()) {
			// Provide empty defaults
			return array('typography' => array());
		}

		return array();
	}

	public function getResponsiveSettings($settings) {
		return array();
	}

	public function getThemeFonts($fonts, $args) {
		if (isset($args['json']) && $args['json']) return json_encode(array());
		return array();
	}

	public function getThemeColors($colors, $args) {
		if (isset($args['json']) && $args['json']) return '';
		return array();
	}

	public function getPostImageVariants($variants, $args) {
	  if (isset($args['json']) && $args['json']) return '';
	  return array();
	}

	public function getButtonPresets($presets, $args) {
		if (isset($args['json']) && $args['json']) return '';
		return array();
	}

	public function getTabPresets($presets, $args) {
		if (isset($args['json']) && $args['json']) return '';
		return array();
	}

	public function getStylesheet($stylesheet) {
		return upfront_exporter_get_stylesheet();
	}

	public function getStylesheetDirectory($stylesheetDirectory) {
		return	sprintf(
			'%s%s%s',
			get_theme_root(),
			DIRECTORY_SEPARATOR,
			upfront_exporter_get_stylesheet()
		);
	}

	function injectDependencies() {
		if (!is_user_logged_in()) return false; // Do not inject for non-logged in user

		$themes = $this->getThemes();
		include($this->pluginDir . '/templates/dependencies.php');
	}

	public function getThemesJson() {
		wp_send_json($this->getThemes());
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

	protected function jsonError($message, $code='generic_error') {
		status_header(400);
		wp_send_json(array('error' => array('message' => $message, 'code' => $code)));
	}

	public function exportLayout() {
		$data = $_POST['data'];
		if (empty($data['theme']) || empty($data['template'])) {
			$this->jsonError('Theme & template must be choosen.', 'missing_data');
		}

		$this->theme = $data['theme'];

		$regions = json_decode(stripslashes($data['regions']));

		$template = "<?php\n";

		foreach($regions as $region) {
			if($region->name === 'shadow') continue;
			if(in_array($region->name, array('header', 'footer')) && $region->scope === 'global') {
				$global_region_filename = "get_stylesheet_directory() . DIRECTORY_SEPARATOR . 'global-regions' . DIRECTORY_SEPARATOR . '{$region->name}.php'";
				$template .= "if (file_exists({$global_region_filename})) include({$global_region_filename});\n\n"; // <-- Check first
				$this->global_regions[$region->name] = $region;
				$this->updateGlobalRegionTemplate($region->name);
				continue;
			}
			if($region->container === 'lightbox') {
				$this->exportLightbox($region);
				continue;
			}
			if(in_array($region->container, array('header', 'footer')) && $region->scope === 'global') {
				$this->handleGlobalSideregion($region);
				continue;
			}
			$template .= $this->renderRegion($region);
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

	protected function handleGlobalSideregion($region) {
		$this->global_sideregions[$region->container][$region->sub] = $region;
		if ($region->sub === 'right') $this->updateGlobalRegionTemplate($region->container);
	}

	public function exportElementStyles() {
		$data = stripslashes_deep($_POST['data']);
		if (empty($data['stylename']) || empty($data['styles']) || empty($data['elementType'])) {
			$this->jsonError('Some data is missing.', 'missing_data');
		}

		if ($data['elementType'] === 'layout') {
			$this->themeSettings->set('layout_style', addcslashes($data['styles'], "'\\"));
			return;
		}

		$stylesheet = !empty($_POST['stylesheet']) && 'upfront' !== $_POST['stylesheet']
			? $_POST['stylesheet']
			: false
		;
		if (!empty($stylesheet)) $this->_export_element_style($stylesheet, $data);
		else $this->_temporarily_store_export_file($data);
	}

	/**
	 * Actually writes element styles to files.
	 */
	private function _export_element_style ($name, $data) {
		$this->theme = $name;

		$style_file = sprintf(
			'%s%s.css',
			$this->getThemePath('element-styles', $data['elementType']),
			$data['stylename']
		);

		file_put_contents($style_file, stripslashes($data['styles']));
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
		$data = $_POST['data'];
		if (empty($data['stylename']) || empty($data['elementType'])) {
			$this->jsonError('Some data is missing.', 'missing_data');
		}

		$stylesheet = !empty($_POST['stylesheet']) && 'upfront' !== $_POST['stylesheet']
			? $_POST['stylesheet']
			: false
		;

		if (!empty($stylesheet)) $this->_delete_element_style($stylesheet, $data);
	}

	private function _delete_element_style ($name, $data) {
		$this->theme = $name;

		$style_file = sprintf(
			'%s%s.css',
			$this->getThemePath('element-styles', $data['elementType']),
			$data['stylename']
		);

		if (is_file($style_file)) unlink($style_file);
	}

	protected function renderRegion($region) {
		$data = (array)$region;
		$name = preg_replace('/[^_a-z0-9]/i', '_', $data['name']);

		$main = array(
			'name' => $data['name'],
			'title' => $data['title'],
			'type' => $data['type'],
			'scope' => $data['scope']
		);

		if (!empty($data['container']) && $data['name'] !== $data['container']) $main['container'] = $data['container'];
		else $main['container'] = $data['name'];

		if (!empty($data['sub'])) $main['sub'] = $data['sub'];
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
				$destination = $this->getThemePath('images') . $file;
				@copy($source, $destination);
				$secondary['background_slider_images'][$idx] = "/images/{$file}";
			}
		}

		$output = '$'. $name . ' = upfront_create_region(
			' . PHPON::stringify($main) .',
			' . PHPON::stringify($secondary) . '
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

			// What does this do?
			if(!empty($module['modules']) && sizeof($module['modules']) > ($i+1))
				$nextModule = $this->parseProperties($module['modules'][$i+1]->properties);
			// And why do we have it???

			$isGroup = (isset($module['modules']) && isset($module['wrappers']));

			$moduleProperties = $this->parseProperties($module['properties']);
			$props = $this->parseModuleClass($moduleProperties['class']);
			$props['id'] = $moduleProperties['element_id'];
			$props['rows'] = !empty($moduleProperties['row']) ? $moduleProperties['row'] : 10;
			if (!$isGroup)
				$props['options'] = $this->parseProperties($module['objects'][0]->properties);
			$props['wrapper_id'] = $moduleProperties['wrapper_id'];

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
				$region_lightboxes += $this->getLightBoxesFromText($props);
				break;
			case 'Unewnavigation':
				$region_lightboxes += $this->getLightBoxesFromMenu($props);
				break;
			}

			if ($type === 'Unewnavigation') {
				$this->addMenuFromElement($props);
				$props['options']['menu_id'] = false; // Should not be set in exported layout
			}

			if ($isGroup){
				$output .= "\n" . '$' . $name . '->add_group(' . PHPON::stringify($props) . ");\n";
				$output .= $this->renderModules($name, $module['modules'], $module['wrappers'], $props['id']);
			}
			else {
				if (!empty($group)){
					$props['group'] = $group;
				}
				$output .= "\n" . '$' . $name . '->add_element("' . $type . '", ' . PHPON::stringify($props) . ");\n";
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
		if (strpos($properties['options']['content'], 'rel="lightbox"') === false) return array();
		$matches = array();
		preg_match_all('#href="(.+?)" rel="lightbox"#', $properties['options']['content'], $matches);
		return is_array($matches[1]) ? $matches[1] : array();
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

		$menus = json_decode($this->themeSettings->get('menus'));

		if (is_null($menus)) $menus = array();

		$updated = false;

		foreach($menus as $index=>$stored_menu) {
			if ($stored_menu->slug != $menu['slug']) continue;

			$menus[$index] = $menu;
			$updated = true;
			break;
		}

		if ($updated === false) $menus[] = $menu;

		$this->themeSettings->set('menus', json_encode($menus));
	}

	protected function getObjectType($class){
		return str_replace('View', '', $class);
	}

	protected function parseProperties($props){
		$parsed = array();
		if (empty($props)) return $parsed;
		foreach($props as $p){
			$parsed[$p->name] = $p->value;
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
			$this->jsonError('Theme & template must be choosen.', 'missing_data');
		}

		$this->theme = $data['theme'];

		$elementStyles = $data['layout']['elementStyles'];
		if (!empty($elementStyles)) {
			// Let's save the default styles directly
			//$this->saveElementStyles($elementStyles);
		}

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

	protected function getThemePath ($do_mkdir=true) {
		if (($this->theme === 'theme' || $this->theme === 'upfront') && !upfront_exporter_is_creating()) {
			if ($do_mkdir) $this->jsonError('Invalid theme name.', 'system_error');
			return false;
		}

		if (empty($this->theme) || !preg_match('/^[-_a-z0-9]+$/i', $this->theme)) {
			$this->jsonError('Invalid theme name.', 'system_error');
			return false;
		}

		$path = sprintf('%s%s%s%s',
			get_theme_root(),
			DIRECTORY_SEPARATOR,
			$this->theme,
			DIRECTORY_SEPARATOR
		);

		$create = true;
		if (upfront_exporter_is_creating()) {
			$create = false;
		} else {
			if (!file_exists($path)) {
				$this->jsonError('Theme root does not exists.', 'system_error');
			}
		}

		$segments = func_get_args();

		if ($segments[0] === false) {
			$create = false;
			array_splice($segments, 0, 1);
		}

		foreach($segments as $segment) {
			$path .= $segment . DIRECTORY_SEPARATOR;
			if (file_exists($path) === false && $create) {
				mkdir($path);
			}
		}

		return rtrim(realpath($path), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
	}
	protected function saveElementStyles($elementStyles) {
		$stylePath = $this->getThemePath() . 'elementStyles.css';
		file_put_contents($stylePath, $elementStyles);
	}

	protected function saveLayoutToTemplate($layout) {
		$template = preg_replace('/[^-_a-z0-9]/i', '', $layout['template']);
		$content = $layout['content'];

		$template_images_dir = $this->getThemePath('images', $template);

		// Copy all images used in layout to theme directory
		$content = $this->exportImages($content, $template, $template_images_dir);

		$content = $this->makeUrlsRelative($content);

		// Save layout to file
		$layout_file = sprintf('%s%s.php',
			$this->getThemePath('layouts'),
			$template
		);

		$result = file_put_contents($layout_file, $content);

		// Save properties to settings file
		$string_properties = array('typography', 'layout_style', 'layout_properties');
		$raw_post_data = !empty($_POST['data']) ? stripslashes_deep($_POST['data']) : array();
		foreach($string_properties as $property) {
			$value = isset($raw_post_data[$property]) ? addcslashes($raw_post_data[$property], "'\\") : false;
			if ($value === false) continue;
			$this->themeSettings->set($property, $value);
		}
		$array_properties = array('theme_colors', 'button_presets', "post_image_variants");
		foreach($array_properties as $property) {
			$value = isset($raw_post_data[$property]) ? $raw_post_data[$property] : false;
			if ($value === false) continue;
			$this->themeSettings->set($property, json_encode($value));
		}

		// Responsive settings. Yeah
		$key = "upfront_{$this->theme}_responsive_settings";
		$resp = get_option($key);
		if (!empty($resp)) $this->themeSettings->set("responsive", $resp);

		// Specific layout settings
		if (!empty($layout['layout'])) {
			$pages = $this->themeSettings->get('required_pages');
			if (!empty($pages)) {
				$pages = json_decode($pages, true);
			}
			if (!is_array($pages)) $pages = array();
			$page = $layout['layout'];
			$name = join(' ', array_map('ucfirst', explode('-', $page)));
			$page_layout_data = array(
				'name' => $name,
				'slug' => $page,
				'layout' => $template,
			);

			$pages[$page] = $page_layout_data;
			$this->themeSettings->set('required_pages', json_encode($pages));

			// Yeah, and now, also please do export the standard WP template too
			$tpl_filename = "page-{$page}";
			$tpl_filepath = sprintf('%s%s.php',
				$this->getThemePath(),
				$tpl_filename
			);

			// Include the template file from which we will be generating a WP page template.
			$tpl_content = $contents = $this->template($this->pluginDir . '/templates/page-template.php', $page_layout_data);
			// Recursive definition yay

			file_put_contents($tpl_filepath, $tpl_content);
		}
	}

	protected function makeUrlsRelative($content) {
		// Fix lightboxes and other anchor urls
		$content = preg_replace('#' . get_site_url() . '/create_new/.+?(\#[A-Za-z_-]+)#', '\1', $content);

		// Okay, so now the imported image is hard-linked to *current* theme dir...
		// Not what we want - the images don't have to be in the current theme, not really
		// Ergo, fix - replace all the hardcoded stylesheet URIs to dynamic ones.
		$content = str_replace(get_stylesheet_directory_uri(), '" . get_stylesheet_directory_uri() . "', $content);

		// Replace all urls that reffer to current site with get_current_site
		$content = str_replace(get_site_url(), '" . get_site_url() . "', $content);

		return $content;
	}

	protected function exportImages($content, $template, $template_images_dir) {
		$matches = array();
		$uploads_dir = wp_upload_dir();

		$_themes_root = basename(get_theme_root());
		$_uploads_root = basename($uploads_dir['basedir']);

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
		$theme_ui_path = $this->getThemePath('ui');

		// matches[1] containes full image urls
		foreach ($matches[1] as $image) {

			// If the exports aren't allowed...
			if (!$export_images) {
				$this_theme_relative_ui_root = '/' . basename($this->getThemePath(false)) . '/ui/';
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
		return $this->getThemePath('ui'); // `return` in an AJAX request handler? - *Yes* because we're just augmenting the default behavior.
	}

	protected function updateGlobalRegionTemplate($region_name) {
		$region = $this->global_regions[$region_name];
		$content = "<?php\n";
		if (isset($this->global_sideregions[$region->name]) && isset($this->global_sideregions[$region->name]['left'])) $content .= $this->renderRegion($this->global_sideregions[$region->name]['left']);
		$content .= $this->renderRegion($region);
		if (isset($this->global_sideregions[$region->name]) && isset($this->global_sideregions[$region->name]['right'])) $content .= $this->renderRegion($this->global_sideregions[$region->name]['right']);

		$template_images_dir = $this->getThemePath('images', 'global-regions', $region->name);

		// Copy all images used in layout to theme directory
		$content = $this->exportImages($content, "global-regions/{$region->name}", $template_images_dir);

		$content = $this->makeUrlsRelative($content);

		$layout_filepath = sprintf('%s%s.php',
			$this->getThemePath('global-regions'),
			$region->name
		);

		file_put_contents($layout_filepath, $content);
	}

	protected function exportLightbox($region) {
		$content = "<?php\n";
		$content .= $this->renderRegion($region);

		$layout_filepath = sprintf('%s%s.php',
			$this->getThemePath('global-regions', 'lightboxes'),
			$region->name
		);

		file_put_contents($layout_filepath, $content);
	}

	public function updateThemeFonts($theme_fonts) {
		$this->themeSettings->set('theme_fonts', json_encode($theme_fonts));
	}

	public function updateResponsiveSettings($responsive_settings) {
		$this->themeSettings->set('responsive_settings', json_encode($responsive_settings));
	}

	public function updateThemeColors($theme_colors) {
		$this->themeSettings->set('theme_colors', json_encode($theme_colors));
	}

	public function updateButtonPresets($button_presets) {
		$this->themeSettings->set('button_presets', json_encode($button_presets));
	}

	public function updatePostImageVariants($post_image_variant) {
	  $this->themeSettings->set('post_image_variants', json_encode($post_image_variant));
	}

	public function saveTabPreset() {
		if (!isset($_POST['data'])) {
			return;
		}

		$properties = $_POST['data'];

		$presets = json_decode($this->themeSettings->get('tab_presets'), true);;

		$result = array();

		foreach ($presets as $preset) {
			if ($preset['id'] === $properties['id']) {
				continue;
			}
			$result[] = $preset;
		}

		$result[] = $properties;

		$this->themeSettings->set('tab_presets', json_encode($result));
	}

	public function deleteTabPreset() {
		if (!isset($_POST['data'])) {
			return;
		}

		$properties = $_POST['data'];

		$presets = json_decode($this->themeSettings->get('tab_presets'), true);;

		$result = array();

		foreach ($presets as $preset) {
			if ($preset['id'] === $properties['id']) {
				continue;
			}
			$result[] = $preset;
		}

		$this->themeSettings->set('tab_presets', json_encode($result));
	}

	public function createFunctionsPhp($themepath, $filename, $slug) {
		if(substr($themepath, -1) != DIRECTORY_SEPARATOR)
			$themepath .=  DIRECTORY_SEPARATOR;

		$filepath = $themepath . $filename;
		$data = array(
			'name' => ucwords(str_replace('-', '_', sanitize_html_class($slug))),
			'slug' => $slug,
			'pages' => '',
			'exports_images' => $this->_does_theme_export_images() ? 'true' : 'false', // Force conversion to string so it can be expanded in the template.
		);

		$contents = $this->template($this->pluginDir . '/templates/functions.php', $data);

		file_put_contents($filepath, $contents);
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
			$this->jsonError('Please check required fields.', 'missing_required');
		}

		$theme_slug = preg_replace('/[^-_a-z0-9]/i', '', $form['thx-theme-slug']);

		// Check if theme directory already exists
		$theme_path = sprintf('%s%s%s',
			get_theme_root(),
			DIRECTORY_SEPARATOR,
			$theme_slug
		);

		if (file_exists($theme_path)) {
			$this->jsonError('Theme with that directory name already exists.', 'theme_exists');
		}

		mkdir($theme_path);

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

		file_put_contents($theme_path.DIRECTORY_SEPARATOR.'style.css', $stylesheet_header);

		// Add directories
		mkdir($theme_path.DIRECTORY_SEPARATOR.'layouts');
		mkdir($theme_path.DIRECTORY_SEPARATOR.'images'); // For general images
		mkdir($theme_path.DIRECTORY_SEPARATOR.'ui'); // For sprites and such UI-specific images

		// This is important to set *before* we create the theme
		remove_all_filters('upfront-thx-theme_exports_images'); // This is for the duration of this request - so we don't inherit old values, whatever they are
		$this->_theme_exports_images = !empty($form['thx-export_with_images']);
		// Allright, good to go

		// Write functions.php to add stylesheet for theme
		$this->createFunctionsPhp($theme_path, 'functions.php', $theme_slug);

		// Adding default layouts
		$default_layouts_dir = sprintf(
			'%s%stemplates%sdefault_layouts%s',
			$this->pluginDir,
			DIRECTORY_SEPARATOR,
			DIRECTORY_SEPARATOR,
			DIRECTORY_SEPARATOR
		);
		$theme_layouts_dir = sprintf(
			'%s%slayouts%s',
			$theme_path,
			DIRECTORY_SEPARATOR,
			DIRECTORY_SEPARATOR
		);
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
			file_put_contents($destination_file, $content);
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

		// Activate the theme, if requested so
		if (!empty($form['thx-activate_theme'])) {
			switch_theme($theme_slug);
		}

		$this->getThemesJson();
	}

	public function addStyles() {
		wp_enqueue_style('upfront-exporter', $this->pluginDirUrl . '/exporter.css');
	}

	public function addData($data) {
		//ob_start();
		//include dirname(__FILE__) . '/templates/testContent.php';
		//$testContent = ob_get_clean();
		$data['exporter'] = array(
			'url' => plugins_url('', __FILE__),
			'testContent' => $this->_get_preview_content(),
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
			$this->jsonError('Not all required data sent.');

		if($type == 'UpostsModel')
			$type = 'archive';
		else
			$type = 'single';

		$filename = $this->export_post_part_template($type, $id, $part, $tpl);

		wp_send_json(array('filename' => $filename));
	}

	protected function export_post_part_template($type, $id, $part, $tpl){
		/*
		$filePath = trailingslashit( get_stylesheet_directory() ) . "templates";
		if (!file_exists( $filePath ))
				mkdir( $filePath );

		$filePath .= '/postparts';
		if (!file_exists( $filePath ))
				mkdir( $filePath );

		$filePath .= '/' . $type . '-' . $id . '.php';
		*/
		$filePath = sprintf(
			'%s%s.php',
			$this->getThemePath('templates', 'postparts'),
			"{$type}-{$id}"
		);
		$templates = array();
		if(file_exists($filePath))
			$templates = require $filePath;

		$templates[$part] = $tpl;

		$output = $this->generate_exported_templates($templates);

		file_put_contents($filePath, $output);

		return $filePath;
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
		if(!$layoutData || !$params )
				$this->jsonError('No layout data or cascade sent.');

		wp_send_json(array(
				"file" => $this->save_post_layout( $params, $layoutData ),
		));
	}


	protected function save_post_layout( $params, $layoutData ) {
		$file_name = !empty($params['specificity'])
			? $params['type'] . "-" . $params['specificity']
			: $params['type'] . "-" . $params['item']
		;
		/*
		$dir = trailingslashit( get_stylesheet_directory() ) . "postlayouts";
		if (!file_exists( $dir )) {
				mkdir( $dir );
		}
		$file_name = $dir . DIRECTORY_SEPARATOR . $file_name . ".php";
		*/
		$file_name = sprintf(
			'%s%s.php',
			$this->getThemePath('postlayouts'),
			$file_name
		);

		$contents = "<?php return " .  PHPON::stringify( $layoutData ) . ";";
		$result = file_put_contents($file_name, $contents);
		//chmod($file_name, 0777);
		return $file_name;
	}

  /**
   * Returns fake post content
   *
   * @param string $post_id
   * @return string
   */
	protected function _get_preview_content ( $post_id = "fake_post" ) {
		$template_file = $post_id === "fake_styled_post" ? upfront_get_template_path('preview_post', dirname(__FILE__) . '/templates/testContentStyled.html')  : upfront_get_template_path('preview_post', dirname(__FILE__) . '/templates/testContent.html');
		if (file_exists($template_file)) {
			ob_start();
			include($template_file);
			$content = ob_get_clean();
		} else $content = '<p>some test content</p>';
		return $content;
	}

	private function _get_styled_preview_content ( $post_id = "fake_styled_post" ) {
	  $template_file =  upfront_get_template_path('preview_post', dirname(__FILE__) . '/templates/testContentStyled.html');
	  if (file_exists($template_file)) {
		ob_start();
		include($template_file);
		$content = ob_get_clean();
	  } else $content = '<p>some styled test content</p>';
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
			'post_title' => 'Sample Post',
			'post_content' => $content,
			'filter' => 'raw',
			'post_author' => get_current_user_id()
		));
		return $post;
	}

	private function _does_theme_export_images () {
		return get_stylesheet() !== $this->theme
			? $this->_theme_exports_images
			: apply_filters('upfront-thx-theme_exports_images', $this->_theme_exports_images)
		;
	}

}

function upfront_exporter_initialize() {
	new UpfrontThemeExporter();
}

function upfront_exporter_stylesheet_directory($stylesheet_dir) {
	if (upfront_exporter_is_start_page()) return 'upfront';
	return get_theme_root() . DIRECTORY_SEPARATOR . upfront_exporter_get_stylesheet();
}

if (upfront_exporter_is_running()) {
	add_action('upfront-core-initialized', 'upfront_exporter_initialize');
	add_filter('stylesheet_directory', 'upfront_exporter_stylesheet_directory', 100);
}

if (is_admin()) {
	require_once('class_thx_admin.php');
	Thx_Admin::serve();
}
