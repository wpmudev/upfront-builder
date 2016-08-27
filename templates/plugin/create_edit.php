<?php
	$themes = array();
	$fallback_screenshot = plugins_url(THX_BASENAME . '/imgs/testImage.jpg');
	$current_theme = get_option('stylesheet');

	/**
	 * Get themes update info
	 *
	 * @var array Array of WP_Theme objects
	 */
	$updates = get_theme_updates();
	if (!is_array($updates)) $updates = array();

	foreach(wp_get_themes() as $stylesheet=>$theme) {
		if ($theme->get('Template') !== 'upfront') continue;

		if (!empty($updates[$stylesheet])) {
			$theme->uf_update = !empty($updates[$stylesheet]->update)
				? $updates[$stylesheet]->update
				: false
			;
		}
		$themes[$stylesheet] = $theme;
	}

	/**
	 * Base URL for redirection and download URL building
	 *
	 * @var string
	 */
	$redirection = remove_query_arg(array(
		'theme',
		'nonce',
		'action',
		'error'
	));
?>

<div class="wrap upfront_admin upfront-builder">

	<h1>
		<?php esc_html_e('Upfront Builder', UpfrontThemeExporter::DOMAIN); ?>
		<span class="upfront_logo"></span>
	</h1>
	<p class="info">
		<?php esc_html_e('Create a unique, responsive Upfront theme that you can export, share, sell or tweak to your heart’s content.', UpfrontThemeExporter::DOMAIN); ?>
	</p>

	<?php load_template(dirname(__FILE__) . '/admin_errors.php'); ?>

	<div class="postbox-container">
		<!-- Build New Theme -->
		<div class="postbox newtheme" id="new-theme">
			<h2 class="title"><?php esc_html_e('Build New Theme', UpfrontThemeExporter::DOMAIN); ?></h2>
			<div class="character"></div>
			<div class="newtheme-form" >
				<?php
					Thx_Template::plugin()->load('theme_form', array(
						'new' => true,
						'name' => '',
					));
				?>
				<div class="buttons">
					<button type="button" class="create theme">
						<?php esc_html_e('Start building', UpfrontThemeExporter::DOMAIN); ?>
					</button>
				</div>
			</div>
		</div>

		<!-- Existing Themes -->
		<div class="postbox themes" id="existing-theme">
			<?php if (!empty($themes)) { ?>
				<h2 class="title"><?php esc_html_e('Edit existing theme', UpfrontThemeExporter::DOMAIN); ?></h2>

				<div class="uf-thx-themes_container clearfix">
				<?php foreach ($themes as $key => $theme) { ?>
					<div class="uf-thx-theme <?php
					// if (!empty($_GET['theme']) && $theme->get_stylesheet() === $_GET['theme']) echo 'selected';
				?> <?php
					$extra_classes = array();

					if ($theme->get_stylesheet() === $current_theme) $extra_classes[] = 'current';
					if (!empty($theme->uf_update)) $extra_classes[] = 'wporg-conflict';

					echo join(' ', $extra_classes);
				?>" data-theme="<?php echo esc_attr($theme->get_stylesheet()); ?>">
						<a href="<?php
							echo esc_attr(add_query_arg('theme', $theme->get_stylesheet()));
						?>" data-download_url="<?php
							echo esc_url(add_query_arg(array(
								'action' => 'download',
								'theme' => $theme->get_stylesheet(),
								'nonce' => wp_create_nonce('download-' . $theme->get_stylesheet()),
							), $redirection));
						?>" >
							<?php $screenshot = $theme->get_screenshot() ? $theme->get_screenshot() : ''; ?>
							<?php if ( !empty($screenshot) ) { ?>
								<img src="<?php echo esc_url($screenshot); ?>" />
							<?php }?>
							<div class="uf-thx-caption">
								<span><?php echo esc_html($theme->get('Name')); ?></span>
								<button type="button" class="edit theme">
									<?php esc_html_e('Edit In Builder', UpfrontThemeExporter::DOMAIN); ?>
								</button>
								<button type="button" class="download" alt="" >
									<span class="btn-label-hidden">
									<?php esc_html_e('Download theme', UpfrontThemeExporter::DOMAIN); ?>
									</span>
								</button>
							</div>
							<button type="button" class="edit info">
							<?php if (!empty($theme->uf_update['url'])) { ?>
								<div class="uf-thx-conflict_info">
									<?php echo esc_html(sprintf(
									__('This theme name conflicts with %s', UpfrontThemeExporter::DOMAIN),
									esc_url($theme->uf_update['url'])
								)); ?>
								</div>
							<?php } ?>
								<?php esc_html_e('Edit theme info', UpfrontThemeExporter::DOMAIN); ?>
							</button>
						</a>
					</div>
				<?php } ?>
				</div>
			<?php } else { ?>
				<label class="inline"><span class="description">
					<?php esc_html_e('No existing themes, please create a new one.', UpfrontThemeExporter::DOMAIN); ?>
				</span></label>

			<?php } ?>
		</div><!-- /.postbox -->
	</div><!-- /.postbox-container -->
	<div class="postbox-modal-container">
		<div class="postbox edit-theme" id="edit-theme">
			<div id="postbox-modal-close">&times;</div>
			<div class="form_content"><!-- will be replaced with edit form --></div>
		</div><!-- /.postbox -->
	</div>
</div>
