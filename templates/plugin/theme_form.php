<?php

	/**
	 * Conflict with wp.org theme repo status
	 *
	 * @var bool
	 */
	$is_conflicted = false;

	/**
	 * Get themes update info
	 *
	 * @var array Array of WP_Theme objects
	 */
	$updates = get_theme_updates();
	if (!is_array($updates)) $updates = array();

	$slug = !empty($slug) ? $slug : false;
	$is_conflicted = !empty($updates[$slug]) && !empty($updates[$slug]->update)
		? $updates[$slug]->update
		: false
	;

?>
<?php if ( $new ) { /* inputs for new theme */ ?>
	<div class="uf-thx-theme_info clearfix">
		<div class="uf-thx-theme_meta">
			<label for="theme-name">
				<span class="description"><?php esc_html_e('What do you want to call your new theme?', UpfrontThemeExporter::DOMAIN); ?></span>
				<input type="text" id="theme-name" />
				<input type="hidden" id="export_with_images" value="true" />
				<input type="hidden" id="activate_theme" value="false" />
			</label>
		</div>
	</div>
<?php } else { /* inputs for existing theme */ ?>
	<h2 class="title"><?php echo esc_html(sprintf(__('%s Theme Info', UpfrontThemeExporter::DOMAIN), $name)); ?></h2>
	<div class="uf-thx-theme_info clearfix">
		<?php if ($is_conflicted) { ?>
			<div class="notice notice-warning">
				<p>
					<?php esc_html_e("This theme name conflicts with a theme from WordPress themes repository:", UpfrontThemeExporter::DOMAIN); ?>
					<?php if(!empty($is_conflicted['url'])) { ?> <a href="<?php echo esc_url($is_conflicted['url']); ?>" target="_blank"> <?php } ?>
						<?php echo esc_html($is_conflicted['theme']); ?>
					<?php if(!empty($is_conflicted['url'])) { ?> </a> <?php } ?>
				</p>
				<p>
					<?php esc_html_e("This may cause issues with automatic updates down the line.", UpfrontThemeExporter::DOMAIN); ?>
					<button type="button" class="button conflict fix">
						<?php esc_html_e("Clone theme and fix this", UpfrontThemeExporter::DOMAIN); ?>
					</button>
				</p>
			</div>
		<?php } ?>
		<div class="uf-thx-theme_screenshot">
			<label>
				<span class="description"><?php esc_html_e('Theme listing image:', UpfrontThemeExporter::DOMAIN); ?></span>
			<?php if (!empty($screenshot)) { ?>
				<img class="nostyle" src="<?php echo esc_url($screenshot); ?>" alt="Add Image" />
			<?php } else { ?>
				<div class="no-image"></div>
			<?php }?>

				<input type="hidden" id="theme-screenshot" value="" />
			</label>
			<button type="button" class="change-image"><?php esc_html_e('Change Image', UpfrontThemeExporter::DOMAIN); ?></button>
		</div>
		<div class="uf-thx-theme_meta">
			<label for="theme-name">
				<span class="description"><?php esc_html_e('Theme name:', UpfrontThemeExporter::DOMAIN); ?></span>
				<input type="text" id="theme-name" placeholder="<?php esc_attr_e('Name for your theme', UpfrontThemeExporter::DOMAIN); ?>" value="<?php if (!empty($name)) echo esc_attr($name); ?>" />
			</label>
			<label for="theme-slug">
				<span class="description"><?php esc_html_e('Theme directory:', UpfrontThemeExporter::DOMAIN); ?></span>
				<input readonly type="text" id="theme-slug" placeholder="<?php esc_attr_e('Base directory for your theme', UpfrontThemeExporter::DOMAIN); ?>" value="<?php if (!empty($slug)) echo esc_attr($slug); ?>" />
			</label>
			<label for="author">
				<span class="description"><?php esc_html_e('Author name:', UpfrontThemeExporter::DOMAIN); ?></span>
				<input type="text" id="author" placeholder="<?php esc_attr_e('Your name', UpfrontThemeExporter::DOMAIN); ?>" value="<?php if (!empty($author)) echo esc_attr($author); ?>" />
			</label>
			<label for="author-uri">
				<span class="description"><?php esc_html_e('Author URL:', UpfrontThemeExporter::DOMAIN); ?></span>
				<input type="text" id="author-uri" placeholder="<?php esc_attr_e('Your URL', UpfrontThemeExporter::DOMAIN); ?>" value="<?php if (!empty($author_uri)) echo esc_attr($author_uri); ?>" />
			</label>
			<label for="theme-description">
				<span class="description"><?php esc_html_e('Theme description:', UpfrontThemeExporter::DOMAIN); ?></span>
				<textarea id="theme-description"><?php if (!empty($description)) echo esc_textarea($description); ?></textarea>
			</label>
			<label for="theme-version">
				<span class="description"><?php esc_html_e('Theme version:', UpfrontThemeExporter::DOMAIN); ?></span>
				<input type="text" id="theme-version" value="<?php if (!empty($version)) echo esc_attr($version); ?>" />
			</label>
			<label for="theme-uri">
				<span class="description"><?php esc_html_e('Theme URL:', UpfrontThemeExporter::DOMAIN); ?></span>
				<input type="text" id="theme-uri" value="<?php if (!empty($theme_uri)) echo esc_attr($theme_uri); ?>" />
			</label>
			<label for="theme-licence">
				<span class="description"><?php esc_html_e('Theme license:', UpfrontThemeExporter::DOMAIN); ?></span>
				<input type="text" id="theme-licence" value="<?php if (!empty($licence)) echo esc_attr($licence); ?>" />
			</label>
			<label for="theme-licence-uri">
				<span class="description"><?php esc_html_e('License URL:', UpfrontThemeExporter::DOMAIN); ?></span>
				<input type="text" id="theme-licence-uri" value="<?php if (!empty($licence_uri)) echo esc_attr($licence_uri); ?>" />
			</label>
			<label for="theme-tags">
				<span class="description"><?php esc_html_e('Theme tags:', UpfrontThemeExporter::DOMAIN); ?></span>
				<input type="text" id="theme-tags" value="<?php if (!empty($tags)) echo esc_attr(join(', ', $tags)); ?>" />
			</label>
			<label for="theme-text-domain">
				<span class="description"><?php esc_html_e('Theme text domain:', UpfrontThemeExporter::DOMAIN); ?></span>
				<input type="text" id="theme-text-domain" value="<?php if (!empty($text_domain)) echo esc_attr($text_domain); ?>" />
			</label>
			<div class="check">
				<label for="activate_theme" class="inline">
					<input type="checkbox" checked id="activate_theme" />
					<span class="description"><?php esc_html_e('Activate theme', UpfrontThemeExporter::DOMAIN); ?></span>
				</label>
			</div>
			<div class="check">
				<label for="export_with_images" class="inline">
					<input type="checkbox" checked id="export_with_images" />
					<span class="description"><?php esc_html_e('Export with images', UpfrontThemeExporter::DOMAIN); ?></span>
				</label>
			</div>
		</div>
	</div>
	<div class="buttons">
		<button type="button" class="edit info">
			<?php esc_html_e('Save Changes', UpfrontThemeExporter::DOMAIN); ?>
		</button>
	</div>
<?php } ?>
