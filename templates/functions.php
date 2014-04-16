<?php

include_once get_template_directory() . '/library/class_upfront_debug.php';
include_once get_template_directory() . '/library/class_upfront_server.php';
include_once get_template_directory() . '/library/class_upfront_theme.php';

class %name% extends Upfront_ChildTheme {
	public function initialize() {
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
}

%name%::serve();