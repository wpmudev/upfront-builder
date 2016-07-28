<div class="notice notice-info is-dismissible">
	<p>
		<?php esc_html_e('You do not seem have Upfront theme active on your site, which is needed to make use of the Upfront Builder plugin.', UpfrontThemeExporter::DOMAIN); ?>
	</p>
	<p>
		<?php esc_html_e('We can fix that for you:', UpfrontThemeExporter::DOMAIN); ?>
		<button type="button" class="button button-primary" id="upfront-kickstart-start_building">
			<?php esc_html_e('Start Building', UpfrontThemeExporter::DOMAIN); ?>
		</button>
	</p>
	<p class="upfront-kickstart-out" style="display:none"></p>
</div>
<style>
.notice .upfront-kickstart-out.error { color: #c00; }
.notice .upfront-kickstart-out.success { color: #0c0; }
</style>
