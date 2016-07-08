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
	}

	/**
	 * Outputs version info
	 */
	public function version_info () {
		$version = UpfrontThemeExporter::get_version();
		?>
<div class="upfront-debug-block">
	Builder <span>V <?php echo esc_html($version); ?></span>
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
