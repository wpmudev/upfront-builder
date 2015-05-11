<label for="theme-name">
	<?php esc_html_e('Name', UpfrontThemeExporter::DOMAIN); ?>
	<input type="text" id="theme-name" placeholder="<?php esc_attr_e('Name for your theme', UpfrontThemeExporter::DOMAIN); ?>" value="<?php if (!empty($name)) echo esc_attr($name); ?>" />
</label>
<label for="theme-slug">
	<?php esc_html_e('Directory', UpfrontThemeExporter::DOMAIN); ?>
	<input type="text" id="theme-slug" placeholder="<?php esc_attr_e('Base directory for your theme', UpfrontThemeExporter::DOMAIN); ?>" value="<?php if (!empty($slug)) echo esc_attr($slug); ?>" />
</label>
<label for="author">
	<?php esc_html_e('Author', UpfrontThemeExporter::DOMAIN); ?>
	<input type="text" id="author" placeholder="<?php esc_attr_e('Your name', UpfrontThemeExporter::DOMAIN); ?>" value="<?php if (!empty($author)) echo esc_attr($author); ?>" />
</label>
<label for="author-uri">
	<?php esc_html_e('Author URL', UpfrontThemeExporter::DOMAIN); ?>
	<input type="text" id="author-uri" placeholder="<?php esc_attr_e('Your URL', UpfrontThemeExporter::DOMAIN); ?>" value="<?php if (!empty($author_uri)) echo esc_attr($author_uri); ?>" />
</label>

<?php if (!empty($description)) { ?>
<label for="description">
	<?php esc_html_e('Description', UpfrontThemeExporter::DOMAIN); ?>
	<textarea id="description"><?php if (!empty($description)) echo esc_textarea($description); ?></textarea>
</label>
<?php } ?>
<?php if (!empty($version)) { ?>
<label for="version">
	<?php esc_html_e('Version', UpfrontThemeExporter::DOMAIN); ?>
	<input type="text" id="version" value="<?php if (!empty($version)) echo esc_attr($version); ?>" />
</label>
<?php } ?>
<?php if (!empty($theme_uri)) { ?>
<label for="theme-uri">
	<?php esc_html_e('Theme URI', UpfrontThemeExporter::DOMAIN); ?>
	<input type="text" id="theme-uri" value="<?php if (!empty($theme_uri)) echo esc_attr($theme_uri); ?>" />
</label>
<?php } ?>
<?php if (!empty($licence)) { ?>
<label for="licence">
	<?php esc_html_e('Licence', UpfrontThemeExporter::DOMAIN); ?>
	<input type="text" id="licence" value="<?php if (!empty($licence)) echo esc_attr($licence); ?>" />
</label>
<?php } ?>
<?php if (!empty($licence_uri)) { ?>
<label for="licence-uri">
	<?php esc_html_e('Licence URI', UpfrontThemeExporter::DOMAIN); ?>
	<input type="text" id="licence-uri" value="<?php if (!empty($licence_uri)) echo esc_attr($licence_uri); ?>" />
</label>
<?php } ?>
<?php if (!empty($tags)) { ?>
<label for="tags">
	<?php esc_html_e('Tags', UpfrontThemeExporter::DOMAIN); ?>
	<input type="text" id="tags" value="<?php if (!empty($tags)) echo esc_attr(join(', ', $tags)); ?>" />
</label>
<?php } ?>
<?php if (!empty($text_domain)) { ?>
<label for="text-domain">
	<?php esc_html_e('Text Domain', UpfrontThemeExporter::DOMAIN); ?>
	<input type="text" id="text-domain" value="<?php if (!empty($text_domain)) echo esc_attr($text_domain); ?>" />
</label>
<?php } ?>

<label for="activate_theme">
	<input type="checkbox" checked id="activate_theme" />
	<?php esc_html_e('Activate theme', UpfrontThemeExporter::DOMAIN); ?>
</label>
<label for="export_with_images">
	<input type="checkbox" checked id="export_with_images" />
	<?php esc_html_e('Export with images', UpfrontThemeExporter::DOMAIN); ?>
</label>