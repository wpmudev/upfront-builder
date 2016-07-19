<?php
	$themes = array();
	$fallback_screenshot = plugins_url(THX_BASENAME . '/imgs/testImage.jpg');
	$current_theme = get_option('stylesheet');

	foreach(wp_get_themes() as $stylesheet=>$theme) {
		if ($theme->get('Template') !== 'upfront') continue;
		$themes[$stylesheet] = $theme;
	}
?>

<div class="wrap upfront_admin upfront-builder">

	<h1><?php esc_html_e('Upfront Builder', UpfrontThemeExporter::DOMAIN); ?><span class="upfront_logo"></span></h1>
	<p class="info">
		<?php esc_html_e('Build your own, slick, customizable, intuitive Upfront themes.', UpfrontThemeExporter::DOMAIN); ?>
		<?php esc_html_e('Creating a WordPress theme has never been easier.', UpfrontThemeExporter::DOMAIN); ?>
	</p>

	<?php load_template(dirname(__FILE__) . '/admin_errors.php'); ?>

	<div class="uf-thx-initial clearfix">
		<!-- Left Side UI Column -->
		<div class="upfront-col-left">
			<div class="postbox-container">
				<div class="postbox" id="existing-theme">
					<?php if (!empty($themes)) { ?>
						<h2 class="title"><?php esc_html_e('Modify existing theme', UpfrontThemeExporter::DOMAIN); ?></h2>

						<div class="uf-thx-themes_container clearfix">
						<?php foreach ($themes as $key => $theme) { ?>
							<div class="uf-thx-theme <?php
							if (!empty($_GET['theme']) && $theme->get_stylesheet() === $_GET['theme']) echo 'selected';
						?> <?php
							if ($theme->get_stylesheet() === $current_theme) {
								echo 'current';
								if (empty($_GET['theme'])) echo ' selected';
							}
						?>" data-theme="<?php echo esc_attr($theme->get_stylesheet()); ?>">
								<a href="<?php echo esc_attr(add_query_arg('theme', $theme->get_stylesheet())); ?>">
									<?php $screenshot = $theme->get_screenshot() ? $theme->get_screenshot() : $fallback_screenshot; ?>
									<img src="<?php echo esc_url($screenshot); ?>" />
									<div class="uf-thx-caption">
										<span><?php echo esc_html($theme->get('Name')); ?></span>
									</div>
								</a>
							</div>
						<?php } ?>
						</div>
						<div class="buttons">
							<button type="button" class="edit theme">
								<?php esc_html_e('Edit theme', UpfrontThemeExporter::DOMAIN); ?>
							</button>
							<button type="button" class="edit info">
								<?php esc_html_e('Edit theme details', UpfrontThemeExporter::DOMAIN); ?>
							</button>
						</div>
					<?php } else { ?>
						<label class="inline"><span class="description">
							<?php esc_html_e('No existing themes, please create a new one.', UpfrontThemeExporter::DOMAIN); ?>
						</span></label>

					<?php } ?>
				</div><!-- /.postbox -->
			</div><!-- /.postbox-container -->
		</div><!-- /.upfront-col-left -->

		<!-- Right Side UI Column -->
		<div class="upfront-col-right">
			<div class="postbox-container">
				<div class="postbox" id="new-theme">

					<?php if (empty($_GET['theme'])) { ?>
						<h2 class="title"><?php esc_html_e('Build a new theme', UpfrontThemeExporter::DOMAIN); ?></h2>

						<?php Thx_Template::plugin()->load('theme_form'); ?>
						<div class="buttons">
							<button type="button" class="create theme">
								<?php esc_html_e('Start building', UpfrontThemeExporter::DOMAIN); ?>
							</button>
						</div>
					<?php } else { ?>
						<?php $theme = wp_get_theme($_GET['theme']); ?>
						<h2 class="title"><?php echo esc_html(sprintf(__('Edit &quot;%s&quot;', UpfrontThemeExporter::DOMAIN), $theme->get('Name'))); ?></h2>

						<?php
							Thx_Template::plugin()->load('theme_form', array(
								'name' => $theme->get('Name'),
								'slug' => $theme->get_stylesheet(),
								'author' => $theme->get('Author'),
								'author_uri' => $theme->get('AuthorURI'),
								'description' => $theme->get('Description'),
								'version' => $theme->get('Version'),
								'theme_uri' => $theme->get('ThemeURI'),
								'licence' => $theme->get('License'),
								'licence_uri' => $theme->get('License URI'),
								'tags' => $theme->get('Tags'),
								'text_domain' => $theme->get('TextDomain'),
								'screenshot' => ($theme->get_screenshot() ? $theme->get_screenshot() : $fallback_screenshot),
							));
						?>
						<div class="buttons">
							<button type="button" class="edit info">
								<?php esc_html_e('Save Changes', UpfrontThemeExporter::DOMAIN); ?>
							</button>
							<button type="button" class="download" data-download_url="<?php
								echo esc_url(add_query_arg(array(
									'action' => 'download',
									'nonce' => wp_create_nonce('download-' . $theme->get_stylesheet()),
								)));
							?>">
								<?php esc_html_e('Download theme', UpfrontThemeExporter::DOMAIN); ?>
							</button>
						</div>
					<?php } ?>

				</div><!-- /.postbox -->
			</div><!-- /.postbox-container -->
		</div><!-- /.upfront-col-right -->

	</div>
</div>
