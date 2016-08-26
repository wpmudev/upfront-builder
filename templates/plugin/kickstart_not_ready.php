<div class="notice notice-error is-dismissible">
	<p>
		<?php esc_html_e('Unfortunately, we do not seem to have Upfront core installed on this site.', UpfrontThemeExporter::DOMAIN); ?>
		<?php esc_html_e('We need that for the Upfront Builder plugin to work.', UpfrontThemeExporter::DOMAIN); ?>
		<?php echo wp_kses(
			sprintf(
				__('<a href="%s" target="_blank">Get it here.</a>', UpfrontThemeExporter::DOMAIN),
				'https://premium.wpmudev.org/projects/category/themes/'
			), array(
				'a' => array(
					'href' => array(),
					'target' => array(),
				),
			)
		); ?>
	</p>
</div>
