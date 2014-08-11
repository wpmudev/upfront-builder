<?php
/*
Plugin Name: Upfront Theme Exporter
Plugin URI: http://premium.wpmudev.com/
Description: Exports upfront page layouts to theme.
Version: 0.0.1
Author: WPMU DEV
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

require 'settings.php';
include_once 'phpon.php';
class UpfrontThemeExporter {
    protected $pluginDirUrl;
    protected $pluginDir;

    var $DEFAULT_ELEMENT_STYLESHEET = 'elementStyles.css';

    public function __construct() {
      $this->pluginDir = dirname(__FILE__);
      $this->pluginDirUrl = plugin_dir_url(__FILE__);

			$this->theme = upfront_get_builder_stylesheet();

			$this->themeSettings = new UfExThemeSettings($this->getThemePath());

      $ajaxPrefix = 'wp_ajax_upfront_thx-';

      add_action('wp_footer', array($this, 'injectDependencies'), 100);
      add_action($ajaxPrefix . 'save-layout-to-template', array($this, 'saveLayout'));
      add_action($ajaxPrefix . 'create-theme', array($this, 'createTheme'));
      add_action($ajaxPrefix . 'get-themes', array($this, 'getThemesJson'));

      add_action($ajaxPrefix . 'export-layout', array($this, 'exportLayout'));
      add_action($ajaxPrefix . 'export-post-layout', array($this, 'ajax_export_post_layout'));

      add_action($ajaxPrefix . 'export-part-template', array($this, 'ajax_export_part_template'));

      add_action($ajaxPrefix . 'export-element-styles', array($this, 'exportElementStyles'));

      add_action( 'wp_enqueue_scripts', array($this,'addStyles'));

      add_filter('upfront_data', array($this, 'addData'));

      add_filter('upfront_get_layout_properties', array($this, 'getLayoutProperties'), 10, 2);

      add_action('upfront_update_theme_fonts', array($this, 'updateThemeFonts'));
      add_filter('upfront_get_theme_fonts', array($this, 'getThemeFonts'), 10, 2);

			add_action('upfront_update_theme_colors', array($this, 'updateThemeColors'));
			add_filter('upfront_get_theme_colors', array($this, 'getThemeColors'), 10, 2);
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

      foreach($regions as $index=>$region) {
        if($region->name === 'shadow') continue;
        $template .= $this->renderRegion($region);
      }

      $file = !empty($data['functionsphp']) ? $data['functionsphp'] : false;
      if($file == 'test')
        $file = 'functions.test.php';
      else if($file == 'functions')
        $file = 'functions.php';
      else
        $file = false;

      //Export elements' alternative styles
      $elements = get_option('upfront_' . $this->theme . '_styles');
      if($elements){
        $stylesheet = "/* IMPORTANT: This file is used only in the theme installation and should not be included as a stylesheet. */\n\n";
        foreach($elements as $element => $styles) {
          foreach($styles as $name => $style)
          $stylesheet .= "\n/* start $element.$name */\n$style\n/* end $element.$name */\n";
        }
        file_put_contents($this->getThemePath() . '/alternativeElementStyles.css', $stylesheet);
      }

      $this->saveLayoutToTemplate(
        array(
          'template' => $data['template'],
          'content' => $template,
          'functions' => $file
        )
      );

      die;
    }

    public function exportElementStyles() {
      $data = $_POST['data'];
      if (empty($data['stylename']) || empty($data['styles']) || empty($data['elementType'])) {
        $this->jsonError('Some data is missing.', 'missing_data');
      }

      $this->theme = $_POST['stylesheet'];

      $style_file = sprintf(
        '%s%s.css',
        $this->getThemePath('element-styles', $data['elementType']),
        $data['stylename']
      );

      file_put_contents($style_file, stripslashes($data['styles']));
    }

    protected function renderRegion($region) {
      $data = (array) $region;
      $name = str_replace('-', '_', $data['name']);

      $main = array(
        'name' => $name,
        'title' => $data['title'],
        'type' => $data['type'],
        'scope' => $data['scope']
      );
      if (!empty($data['container'])) $main['container'] = $data['container'];
      if (!empty($data['sub'])) $main['sub'] = $data['sub'];
      if (!empty($data['position'])) $main['position'] = $data['position'];
      if (!empty($data['allow_sidebar'])) $main['allow_sidebar'] = $data['allow_sidebar'];
      $secondary = $this->parseProperties($data['properties']);

      // Deal with the slider images
      if (!empty($secondary['background_slider_images'])) {
        foreach ($secondary['background_slider_images'] as $idx => $img) {
          $source = get_attached_file($img);
          if (empty($source)) continue;
          // copy file to theme folder
          $file = basename($source);
          $destination = $this->getThemePath('images') . $file;
          copy($source, $destination);
          $secondary['background_slider_images'][$idx] = "/images/{$file}";
        }
      }

      $output = '$'. $name . ' = upfront_create_region(
        ' . PHPON::stringify($main) .',
        ' . PHPON::stringify($secondary) . '
        );';

      foreach ($data['modules'] as $i => $m) {
        $nextModule = false;
        if(sizeof($data['modules']) > ($i+1))
          $nextModule = $this->parseProperties($data['modules'][$i+1]->properties);

        $module = (array) $m;
        $moduleProperties = $this->parseProperties($module['properties']);
        $props = $this->parseModuleClass($moduleProperties['class']);
        $props['id'] = $moduleProperties['element_id'];
        $props['rows'] = $moduleProperties['row'] ? $moduleProperties['row'] : 10;
        $props['options'] = $this->parseProperties($module['objects'][0]->properties);

        if($nextModule && $moduleProperties['wrapper_id'] == $nextModule['wrapper_id']){
          $props['close_wrapper'] = false;
        }

        $type = $this->getObjectType($props['options']['view_class']);

				if ($type === 'Unewnavigation') {
					$this->addMenuFromElement($props);
				}

				// This is needed since module groups are not correctly exported yet and
				// until that is handled we're just skipping module group export.
				if (!$type) continue;

        $output .= "\n" . '$' . $name . '->add_element("' . $type . '", ' . PHPON::stringify($props) . ");\n";
      }

      $output .= "\n" . '$regions->add($' . $name . ");\n";
      return $output;
    }

		protected function addMenuFromElement($properties) {
			$menu_id = $properties['options']['menu_id'];

			$menu_object = wp_get_nav_menu_object($menu_id);
			$menu_items = wp_get_nav_menu_items($menu_id);

			$menu = array(
				'id' => $menu_object->id,
				'slug' => $menu_object->slug,
				'name' => $menu_object->name,
				'description' => $menu_object->description,
				'items' => $menu_items
			);

			$menus = json_decode($this->themeSettings->get('menus'));

			if (is_null($menus)) $menus = array();

			$updated = false;

			foreach($menus as $index=>$stored_menu) {
				if ($stored_menu->id != $menu['id']) continue;

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

    protected function getThemePath() {
      $path = sprintf('%s%s%s%s',
        get_theme_root(),
        DIRECTORY_SEPARATOR,
        $this->theme,
        DIRECTORY_SEPARATOR
      );

      if (!file_exists($path)) $this->jsonError('Theme root does not exists.', 'system_error');

      $segments = func_get_args();

      foreach($segments as $segment) {
        $path .= $segment . DIRECTORY_SEPARATOR;
        if (!file_exists($path)) {
          mkdir($path);
        }
      }

      return $path;
    }
    protected function saveElementStyles($elementStyles) {
      $stylePath = $this->getThemePath() . 'elementStyles.css';
      file_put_contents($stylePath, $elementStyles);
    }

    protected function saveLayoutToTemplate($layout) {
      $template = $layout['template'];
      $content = $layout['content'];

      $matches = array();
      $uploads_dir = wp_upload_dir();

      // Copy all images used in layout to theme directory
      $template_images_dir = $this->getThemePath('images', $template);

      // Save file list for later
      $original_images = glob($template_images_dir . '*');

      //preg_match_all("#[\"'](http.+?(jpg|jpeg|png|gif))[\"']#", $content, $matches); // Won't recognize escaped quotes (such as content images), and will find false positives such as "httpajpg"
      preg_match_all("#\b(https?://.+?\.(jpg|jpeg|png|gif))\b#", $content, $matches);

      $images_used_in_template = array();
      $separator = '/';

      // matches[1] containes full image urls
      foreach ($matches[1] as $image) {
        // Image is from a theme
        if (strpos($image, get_theme_root_uri()) !== false) {
          $relative_url = explode('themes/', $image);
          $source_root = get_theme_root();
        }
        // Image is from uploads
        if (strpos($image, 'uploads') !== false) {
          $relative_url = explode('uploads/', $image);
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
        /*
        $image_uri = sprintf("' . get_stylesheet_directory_uri() . '%simages%s%s%s%s'",
          $separator,
          $separator,
          $template,
          $separator,
          $image_filename
        );
        // var_dump('image uri', $image_uri);

        $content = str_replace("'" . $image . "'", $image_uri, $content);
        $content = str_replace('"' . $image . '"', $image_uri, $content);
        */

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

      // Okay, so now the imported image is hard-linked to *current* theme dir...
      // Not what we want - the images don't have to be in the current theme, not really
      // Ergo, fix - replace all the hardcoded stylesheet URIs to dynamic ones.
      $content = str_replace(get_stylesheet_directory_uri(), '" . get_stylesheet_directory_uri() . "', $content);

      // Replace all urls that reffer to current site with get_current_site
      $content = str_replace(get_site_url(), '" . get_site_url() . "', $content);

      // Save layout to file
      $layout_file = sprintf('%s%s.php',
        $this->getThemePath('layouts'),
        $template
      );

      $result = file_put_contents($layout_file, $content);

			// Save properties to settings file
			$properties = array('typography', 'layout_style', 'layout_properties');
			foreach($properties as $property) {
				$value = isset($_POST['data'][$property]) ? $_POST['data'][$property] : false;
				if ($value === false) continue;
				$this->themeSettings->set($property, $value);
			}
    }

    protected function incorrect_stylesheet($stylesheet) {
      if(empty($stylesheet) || $stylesheet === 'theme' || $stylesheet === 'upfront') return true;
      return false;
    }

    public function getLayoutProperties($properties, $args) {
      if ($this->incorrect_stylesheet($args['stylesheet'])) return $properties;

			if ($this->themeSettings->get('layout_properties')) {
				$properties = json_decode(stripslashes($this->themeSettings->get('layout_properties')), true);
			}
			if ($this->themeSettings->get('typography')) {
				$properties[] = array(
					'name' => 'typography',
					'value' => json_decode(stripslashes($this->themeSettings->get('typography')))
				);
			}
			if ($this->themeSettings->get('layout_style')) {
				$properties[] = array(
					'name' => 'layout_style',
					'value' => addslashes($this->themeSettings->get('layout_style'))
				);
			}

			return $properties;
		}

		public function updateThemeFonts($theme_fonts) {
			$this->themeSettings->set('theme_fonts', json_encode($theme_fonts));
		}

		public function getThemeFonts($theme_fonts, $args) {
			if ($this->incorrect_stylesheet($args['stylesheet'])) return $theme_fonts;

			$theme_fonts = $this->themeSettings->get('theme_fonts');
			if (isset($args['json']) && $args['json']) return $theme_fonts;

			return is_array( $$theme_fonts ) ? $theme_fonts : json_decode($theme_fonts);
		}

		public function updateThemeColors($theme_colors) {
			$this->themeSettings->set('theme_colors', json_encode($theme_colors));
		}

    public function getThemeColors($theme_colors, $args) {
      if ($this->incorrect_stylesheet($args['stylesheet'])) return $theme_colors;

			$theme_colors = $this->themeSettings->get('theme_colors');
			if (isset($args['json']) && $args['json']) return $theme_colors;

      return json_decode($theme_colors);
    }

    public function createFunctionsPhp($themepath, $filename, $slug) {
      if(substr($themepath, -1) != DIRECTORY_SEPARATOR)
        $themepath .=  DIRECTORY_SEPARATOR;

      $filepath = $themepath . $filename;
      $data = array(
        'name' => ucwords(str_replace('-', '_', sanitize_html_class($slug))),
        'slug' => $slug,
        'pages' => '',
        'styles' => '',
        'import_styles' => '',
        'styles_function' => ''
      );

      //Enqueue default element styles?
      if(file_exists($themepath . 'elementStyles.css'))
        $data['styles'] = 'wp_enqueue_style("elements_styles", get_stylesheet_directory_uri() . "/elementStyles.css");';

      //Import alternative element styles?
      if(file_exists($themepath . 'alternativeElementStyles.css')){
        $data['import_styles'] = '$this->install_element_alternative_styles();';
        $data['styles_function'] = 'protected function install_element_alternative_styles(){
    $this->import_element_styles();
  }';
      }

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
      //  [thx-theme-name] =>
      //  [thx-theme-template] => upfront
      //  [thx-theme-slug] =>
      //  [thx-theme-uri] =>
      //  [thx-theme-author] =>
      //  [thx-theme-author-uri] =>
      //  [thx-theme-description] =>
      //  [thx-theme-version] =>
      //  [thx-theme-licence] =>
      //  [thx-theme-licence-uri] =>
      //  [thx-theme-tags] =>
      //  [thx-theme-text-domain] =>
      $form = array();
      parse_str($_POST['form'], $form);
      // print_r($form);

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
      ));

      // Check required fields
      if (empty($form['thx-theme-slug']) || empty($form['thx-theme-name']) || empty($form['thx-theme-template'])) {
        $this->jsonError('Please check required fields.', 'missing_required');
      }

      // Check if theme directory already exists
      $theme_path = sprintf('%s%s%s',
        get_theme_root(),
        DIRECTORY_SEPARATOR,
        $form['thx-theme-slug']
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
      mkdir($theme_path.DIRECTORY_SEPARATOR.'images');

      // Write functions.php to add stylesheet for theme
      $this->createFunctionsPhp($theme_path, 'functions.php', $form['thx-theme-slug']);

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
      foreach($default_layouts as $layout) {
        $destination_file = str_replace($default_layouts_dir, $theme_layouts_dir, $layout);
        copy($layout, $destination_file);
      }

      $this->getThemesJson();
    }

    public function addStyles() {
      wp_enqueue_style('upfront-exporter', $this->pluginDirUrl . '/exporter.css');
    }

    public function addData($data) {
      ob_start();
      include dirname(__FILE__) . '/templates/testContent.php';
      $testContent = ob_get_clean();

      $data['exporter'] = array(
        'url' => plugins_url('', __FILE__),
        'testContent' => $testContent
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
      $filePath = trailingslashit( get_stylesheet_directory() ) . "templates";
      if (!file_exists( $filePath ))
          mkdir( $filePath );

      $filePath .= '/postparts';
      if (!file_exists( $filePath ))
          mkdir( $filePath );

      $filePath .= '/' . $type . '-' . $id . '.php';
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
        $out .= "?>$template<?php\n";
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
        $file_name = $params['type'] . "-" . $params['specificity'];
        $dir = trailingslashit( get_stylesheet_directory() ) . "postlayouts";
        if (!file_exists( $dir )) {
            mkdir( $dir );
        }

        $file_name = $dir . DIRECTORY_SEPARATOR . $file_name . ".php";
        $contents = "<?php return " .  PHPON::stringify( $layoutData ) . ";";
        $result = file_put_contents($file_name, $contents);
        chmod($file_name, 0777);
        return $file_name;
    }

}

function upfront_exporter_initialize() {
	new UpfrontThemeExporter();
}
add_action('upfront-core-initialized', 'upfront_exporter_initialize');
