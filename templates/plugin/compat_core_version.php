<?php
	$current_version = class_exists('Upfront_Compat') && is_callable(array('Upfront_Compat', 'get_upfront_core_version'))
		? sprintf(__('Your current version is at v%s.', UpfrontThemeExporter::DOMAIN), Upfront_Compat::get_upfront_core_version())
		: __('Your current version is too old.', UpfrontThemeExporter::DOMAIN)
	;
?>
<div class="notice notice-error">
	<p>
		<?php echo wp_kses(
		sprintf(
			__('You need Upfront core at version v1.4 or better for Upfront Builder to work properly. <a href="%s" target="_blank">Get it here.</a>', UpfrontThemeExporter::DOMAIN),
			'https://premium.wpmudev.org/projects/category/themes/'
		), array(
			'a' => array(
				'href' => array(),
				'target' => array(),
			),
		)
		); ?>
		<br />
		<?php echo esc_html($current_version); ?>
	</p>
</div>
