<?php
/*
Plugin Name: Upfront Theme Exporter
Plugin URI: http://premium.wpmudev.com
Description: Exports upfront page layouts to theme.
Version: 0.0.1
Author: WPMUdev
Author URI: http://premium.wpmudev.com
License: GPLv2 or later
*/
class UpfrontThemeExporter {
    protected $pluginDirUrl;
    protected $pluginDir;

    var $DEFAULT_ELEMENT_STYLESHEET = 'defaultElementStyles.css';

    public function __construct()
    {
      $this->pluginDir = dirname(__FILE__);
      $this->pluginDirUrl = plugin_dir_url(__FILE__);

      $ajaxPrefix = 'wp_ajax_upfront_thx-';

      add_action('wp_footer', array($this, 'injectDependencies'), 100);
      add_action($ajaxPrefix . 'save-layout-to-template', array($this, 'saveLayout'));
      add_action($ajaxPrefix . 'create-theme', array($this, 'createTheme'));
      add_action($ajaxPrefix . 'get-themes', array($this, 'getThemesJson'));

      add_action($ajaxPrefix . 'get-default-styles', array($this, 'ajaxGetDefaultStyles'));
      add_filter('upfront-save_styles', array($this, 'saveDefaultElementStyles'), 10, 3);
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

    public function saveLayout() {
      $data = $_POST['data'];

      if (empty($data['theme']) || empty($data['template'])) {
        $this->jsonError('Theme & template must be choosen.', 'missing_data');
      }

      $this->theme = $data['theme'];

      $elementStyles = $data['layout']['elementStyles'];
      if (!empty($elementStyles)) {
        $this->saveElementStyles($elementStyles);
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
      extract($layout); //$template, $content
      $matches = array();
      $uploads_dir = wp_upload_dir();

      // Copy all images used in layout to theme directory
      $template_images_dir = $this->getThemePath('images', $template);

      // Save file list for later
      $original_images = glob($template_images_dir . '*');

      preg_match_all("#'(http.+?(jpg|jpeg|png|gif))'#", $content, $matches);

      $images_used_in_template = array();
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
        $source_relative_path = str_replace('/', DIRECTORY_SEPARATOR, $relative_url);
        $source_image = $source_root . DIRECTORY_SEPARATOR . $source_relative_path;

        $destination_image = $template_images_dir . $image_filename;

        // Copy image
        $result = copy($source_image, $destination_image);
        $images_used_in_template[] = $destination_image;

        // Replace images url root with stylesheet uri
        $image_uri = sprintf("get_stylesheet_directory_uri() . '%simages%s%s%s%s'",
          DIRECTORY_SEPARATOR,
          DIRECTORY_SEPARATOR,
          $template,
          DIRECTORY_SEPARATOR,
          $image_filename
        );

        $content = str_replace("'" . $image . "'", $image_uri, $content);
      }

      // Delete images that are not used, this is needed if template is exported from itself
      foreach ($original_images as $file) {
        if (in_array($file, $images_used_in_template)) continue;
        if (is_file($file)) {
          unlink($file);
        }
      }

      // Save layout to file
      $layout_file = sprintf('%s%s.php',
        $this->getThemePath('layouts'),
        $template
      );

      $result = file_put_contents($layout_file, $content);
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
      if ($author = $form['thx-theme-author']) $stylesheet_header .= "Author: $author\n";
      if ($author_uri = $form['thx-theme-author-uri']) $stylesheet_header .= "Author URI: $author_uri\n";
      if ($description = $form['thx-theme-description']) $stylesheet_header .= "Description: $description\n";
      if ($version = $form['thx-theme-version']) $stylesheet_header .= "Version: $version\n";
      if ($licence = $form['thx-theme-licence']) $stylesheet_header .= "Licence: $licence\n";
      if ($licence_uri = $form['thx-theme-licence-uri']) $stylesheet_header .= "Licence URI: $licence_uri\n";
      if ($tags = $form['thx-theme-tags']) $stylesheet_header .= "Tags: $tags\n";
      if ($text_domain = $form['thx-theme-text-domain']) $stylesheet_header .= "Text Domain: $text_domain\n";
      $stylesheet_header .= "*/\n";
      $stylesheet_header .= "@import url(../{$form['thx-theme-template']}/style.css);";
      $stylesheet_header .= "@import url(elementStyle.css);@import url(dedfaultElementStyles.css);";

      file_put_contents($theme_path.DIRECTORY_SEPARATOR.'style.css', $stylesheet_header);

      // Add directories
      mkdir($theme_path.DIRECTORY_SEPARATOR.'layouts');
      mkdir($theme_path.DIRECTORY_SEPARATOR.'images');

      // Write functions.php to add stylesheet for theme
      copy($this->pluginDir . '/templates/functions.php', $theme_path);

      //TODO Maybe add empty files for layouts? (with one region)

      $this->getThemesJson();
    }

    public function saveDefaultElementStyles($styles, $name, $element_type){
      if($name != '_default')
        return $styles;

      $stylesheetPath = get_stylesheet_directory() . DIRECTORY_SEPARATOR . $this->DEFAULT_ELEMENT_STYLESHEET;

      //Storing a new default style, intercepting upfront response
      $elementStyles = @file_get_contents($stylesheetPath);
      if($elementStyles === FALSE)
        $elementStyles = '';

      $styleArray = $this->parseDefaultStyles($elementStyles);
      $styleArray[$element_type] = $styles;
      $elementStyles = $this->createDefaultStyles($styleArray);

      if(@file_put_contents($stylesheetPath, $elementStyles) !== FALSE)
        wp_send_json(array('data' => array(
          'name' => $name,
          'styles' => $styles
        )));
      else{
        $this->jsonError('Could not save the stylesheet.');
        die;
      }
    }

    protected function parseDefaultStyles($styles) {
      $elements = array();
      preg_match_all("/\/\* start ([^\s]+?) \*\/(.*?)\/\* end/s", $styles, $matches);
      foreach($matches[1] as $i => $element)
        $elements[$element] = $matches[2][$i];
      return $elements;
    }

    protected function createDefaultStyles($styleArray) {
      $styles = array();
      foreach($styleArray as $element => $style){
        $withSelectors = $this->addElementSelector($element, $style);
        $styles[] = "/* start $element */\n$withSelectors\n/* end $element */";
      }
      $notice = "/*\n" .
        " IMPORTANT: This file is also used by Upfront's theme builder.\n" .
        " Please don't delete the comments before and after each element styles to preserve builder compatibility.\n" .
        "*/\n\n"
      ;

      return $notice . implode("\n\n", $styles);
    }

    protected function addElementSelector($element, $styles) {
      $rules = explode('}', $styles);
      array_pop($rules);
      foreach ($rules as $i => $rule){
        $rules[$i] = '.upfront-output-' . $element . ' ' . trim($rule) . "\n}";
      }

      return implode("\n\n", $rules);
    }

    public function ajaxGetDefaultStyles(){
      $stylesheetPath = get_stylesheet_directory() . DIRECTORY_SEPARATOR . $this->DEFAULT_ELEMENT_STYLESHEET;
      $elementStyles = @file_get_contents($stylesheetPath);
      if($elementStyles === FALSE){
        $this->jsonError('Not styles available.');
        die;
      }

      $styles = $this->parseDefaultStyles($elementStyles);

      wp_send_json(array('data' => $styles));
    }
}

new UpfrontThemeExporter();




