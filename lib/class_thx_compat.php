<?php

/**
 * Deals with compatibility notices
 */
class Thx_Compat {

	private function __construct () {}
	private function __clone () {}

	public static function serve () {
		$me = new self;
		$me->_add_hooks();
		return $me;
	}

	private function _add_hooks () {
		add_action('admin_notices', array($this, 'show_compat_notices'));
	}

	public function show_compat_notices () {
		
		?>
<div class="notice notice-error">
	<p>
		<?php echo wp_kses(
		sprintf(
			__('You need Upfront core at version v%s or better for Upfront Builder to work properly. <a href="%s" target="_blank">Get it here.</a>', UpfrontThemeExporter::DOMAIN),
			'1.4',
			'https://premium.wpmudev.org/projects/category/themes/'
		), array(
			'a' => array(
				'href' => array(),
				'target' => array(),
			),
		)
		); ?>
		<br />
		<?php echo esc_html(sprintf(__('Your current version is %s', UpfrontThemeExporter::DOMAIN), Upfront_Compat::get_upfront_core_version())); ?>
	</p>
</div>
		<?php
	}
}
