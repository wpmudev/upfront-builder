<div class="uf-thx-theme_info clearfix">
	<div class="uf-thx-theme_screenshot">
		<label>
			<span class="description"><?php esc_html_e('Theme listing preview:', UpfrontThemeExporter::DOMAIN); ?></span>
			<img src="<?php if (!empty($screenshot)) echo esc_url($screenshot); ?>" width="370" height="255" />
		</label>
	</div>
	<div class="uf-thx-theme_meta">
		<label for="theme-name">
			<span class="description"><?php esc_html_e('Name', UpfrontThemeExporter::DOMAIN); ?></span>
			<input type="text" id="theme-name" placeholder="<?php esc_attr_e('Name for your theme', UpfrontThemeExporter::DOMAIN); ?>" value="<?php if (!empty($name)) echo esc_attr($name); ?>" />
		</label>
		<label for="author">
			<span class="description"><?php esc_html_e('Author', UpfrontThemeExporter::DOMAIN); ?></span>
			<input type="text" id="author" placeholder="<?php esc_attr_e('Your name', UpfrontThemeExporter::DOMAIN); ?>" value="<?php if (!empty($author)) echo esc_attr($author); ?>" />
		</label>
		<label for="author-uri">
			<span class="description"><?php esc_html_e('Author URL', UpfrontThemeExporter::DOMAIN); ?></span>
			<input type="text" id="author-uri" placeholder="<?php esc_attr_e('Your URL', UpfrontThemeExporter::DOMAIN); ?>" value="<?php if (!empty($author_uri)) echo esc_attr($author_uri); ?>" />
		</label>

	<?php if (!empty($name)) { /* if we're not creating a new theme, show all */ ?>
		<label for="theme-slug">
			<span class="description"><?php esc_html_e('Directory', UpfrontThemeExporter::DOMAIN); ?></span>
			<input type="text" id="theme-slug" placeholder="<?php esc_attr_e('Base directory for your theme', UpfrontThemeExporter::DOMAIN); ?>" value="<?php if (!empty($slug)) echo esc_attr($slug); ?>" />
		</label>
		<label for="description">
			<span class="description"><?php esc_html_e('Description', UpfrontThemeExporter::DOMAIN); ?></span>
			<textarea id="description"><?php if (!empty($description)) echo esc_textarea($description); ?></textarea>
		</label>
		<label for="version">
			<span class="description"><?php esc_html_e('Version', UpfrontThemeExporter::DOMAIN); ?></span>
			<input type="text" id="version" value="<?php if (!empty($version)) echo esc_attr($version); ?>" />
		</label>
		<label for="theme-uri">
			<span class="description"><?php esc_html_e('Theme URI', UpfrontThemeExporter::DOMAIN); ?></span>
			<input type="text" id="theme-uri" value="<?php if (!empty($theme_uri)) echo esc_attr($theme_uri); ?>" />
		</label>
		<label for="licence">
			<span class="description"><?php esc_html_e('Licence', UpfrontThemeExporter::DOMAIN); ?></span>
			<input type="text" id="licence" value="<?php if (!empty($licence)) echo esc_attr($licence); ?>" />
		</label>
		<label for="licence-uri">
			<span class="description"><?php esc_html_e('Licence URI', UpfrontThemeExporter::DOMAIN); ?></span>
			<input type="text" id="licence-uri" value="<?php if (!empty($licence_uri)) echo esc_attr($licence_uri); ?>" />
		</label>
		<label for="tags">
			<span class="description"><?php esc_html_e('Tags', UpfrontThemeExporter::DOMAIN); ?></span>
			<input type="text" id="tags" value="<?php if (!empty($tags)) echo esc_attr(join(', ', $tags)); ?>" />
		</label>
		<label for="text-domain">
			<span class="description"><?php esc_html_e('Text Domain', UpfrontThemeExporter::DOMAIN); ?></span>
			<input type="text" id="text-domain" value="<?php if (!empty($text_domain)) echo esc_attr($text_domain); ?>" />
		</label>
	<?php } ?>

		<label for="activate_theme" class="inline">
			<input type="checkbox" checked id="activate_theme" />
			<span class="description"><?php esc_html_e('Activate theme', UpfrontThemeExporter::DOMAIN); ?></span>
		</label>
		<label for="export_with_images" class="inline">
			<input type="checkbox" checked id="export_with_images" />
			<span class="description"><?php esc_html_e('Export with images', UpfrontThemeExporter::DOMAIN); ?></span>
		</label>
	</div>
</div>