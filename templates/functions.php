<?php

include_once get_template_directory() . '/library/upfront_functions.php';
include_once get_template_directory() . '/library/class_upfront_debug.php';
include_once get_template_directory() . '/library/class_upfront_server.php';
include_once get_template_directory() . '/library/class_upfront_theme.php';

class %name% extends Upfront_ChildTheme {
	public function initialize() {
		 if (!upfront_is_builder_running()) {
            add_filter('upfront_get_layout_properties', array($this, 'get_layout_properties'), 10, 2);
        }
        add_filter('upfront_augment_theme_layout', array($this, 'augment_layout'));
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

    public function augment_layout ($layout) {
        if (empty($layout['regions'])) return $layout;
        $layout['regions'] = $this->augment_regions($layout['regions']);
        return $layout;
    }

    public function augment_regions ($regions) {
        if ($this->_slider_imported) return $regions;

        if (empty($regions) || !is_array($regions)) return $regions;
        foreach ($regions as $idx => $region) {
            if (empty($region['properties']) || !is_array($region['properties'])) continue;
            foreach($region['properties'] as $pidx => $prop) {
                if (empty($prop['name']) || empty($prop['value']) || 'background_slider_images' !== $prop['name']) continue;
                foreach ($prop['value'] as $order_id => $attachment_src) {
                    if (is_numeric($attachment_src)) continue; // A hopefully existing image.
                    $regions[$idx]['properties'][$pidx]['value'][$order_id] = $this->_import_slider_image($attachment_src);
                }
            }
        }

        $this->_slider_imported = true;
        return $regions;
    }

    private function _import_slider_image ($filepath) {
        $key = $this->get_prefix() . '-slider-images';
        $images = get_option($key, array());
        if (!empty($images[$filepath])) return $images[$filepath];
        // else import image
        $wp_upload_dir = wp_upload_dir();
        $pfx = !empty($wp_upload_dir['path']) ? trailingslashit($wp_upload_dir['path']) : '';
        if (!function_exists('wp_generate_attachment_metadata')) require_once(ABSPATH . 'wp-admin/includes/image.php');
        $filename = basename($filepath);
        while (file_exists("{$pfx}{$filename}")) {
            $filename = rand() . $filename;
        }
        @copy($filepath, "{$pfx}{$filename}");
        $wp_filetype = wp_check_filetype(basename($filename), null);
        $attachment = array(
            'guid' => $wp_upload_dir['url'] . '/' . basename($filename),
            'post_mime_type' => $wp_filetype['type'],
            'post_title' => preg_replace('/\.[^.]+$/', '', basename($filename)),
            'post_content' => '',
            'post_status' => 'inherit'
        );
        $attach_id = wp_insert_attachment($attachment, "{$pfx}{$filename}");
        $attach_data = wp_generate_attachment_metadata( $attach_id, "{$pfx}{$filename}" );
        wp_update_attachment_metadata( $attach_id, $attach_data );

        $images[$filepath] = $attach_id;
        update_option($key, $images);

        return $attach_id;
    }
}

%name%::serve();