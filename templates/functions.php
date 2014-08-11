<?php

include_once get_template_directory() . '/library/upfront_functions.php';
include_once get_template_directory() . '/library/class_upfront_debug.php';
include_once get_template_directory() . '/library/class_upfront_server.php';
include_once get_template_directory() . '/library/class_upfront_theme.php';

class %name% extends Upfront_ChildTheme {
	public function initialize() {
		if (!upfront_is_builder_running()) add_filter('upfront_get_layout_properties', array($this, 'get_layout_properties'), 10, 2);
		$this->add_actions_filters();
		$this->populate_pages();
		%import_styles%
	}

	public function get_prefix(){
		return '%slug%';
	}

	public static function serve(){
		return new self();
	}

	public function populate_pages() {
		%pages%
	}

	%styles_function%

	protected function add_actions_filters() {
		// Include current theme style
		add_action('wp_head', array($this, 'enqueue_styles'), 200);
	}

	public function enqueue_styles() {
		wp_enqueue_style('current_theme', get_stylesheet_uri());
		%styles%
	}

	public function get_layout_properties($properties, $args) {
        if ($this->incorrect_stylesheet($args['stylesheet'])) return $properties;

        $this->theme = $args['stylesheet'];

        $settings_file = sprintf(
            '%ssettings.php',
            trailingslashit(dirname(__FILE__))
        );
        if (file_exists($settings_file)) {
            include $settings_file;
        }
        if (!empty($layout_properties)) {
            $properties = json_decode(stripslashes($layout_properties), true);
        }
        if (!empty($typography)) {
            $properties[] = array(
                'name' => 'typography',
                'value' => json_decode(stripslashes($typography))
            );
        }
        if (!empty($layout_style)) {
            $properties[] = array(
                'name' => 'layout_style',
                'value' => addslashes($layout_style)
            );
        }

        return $properties;
    }
    protected function incorrect_stylesheet($stylesheet) {
        if(empty($stylesheet) || $stylesheet === 'theme' || $stylesheet === 'upfront') return true;
        return false;
    }
}

%name%::serve();