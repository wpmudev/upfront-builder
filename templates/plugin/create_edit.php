<?php
	$themes = array();
	$fallback_screenshot = plugins_url(THX_BASENAME . '/imgs/testImage.jpg');

	foreach(wp_get_themes() as $stylesheet=>$theme) {
		if ($theme->get('Template') !== 'upfront') continue;
		$themes[$stylesheet] = $theme;
	}
?>

<div class="uf-thx-initial">
	
	<div class="uf-thx-existing_theme uf-thx-pane">
	<?php if (!empty($themes)) { ?>
		<h3><?php esc_html_e('Modify existing theme', UpfrontThemeExporter::DOMAIN); ?></h3>
		
		<div class="uf-thx-themes_container clearfix">
		<?php foreach ($themes as $key => $theme) { ?>
			<div class="uf-thx-theme <?php if (!empty($_GET['theme']) && $theme->get_stylesheet() === $_GET['theme']) echo 'current'; ?>">
				<a href="?theme=<?php echo esc_attr($theme->get_stylesheet()); ?>">
					<?php $screenshot = $theme->get_screenshot() ? $theme->get_screenshot() : $fallback_screenshot; ?>
					<img src="<?php echo esc_url($screenshot); ?>" width="50" height="50" />
					<div class="uf-thx-caption">
						<b><?php echo esc_html($theme->get('Name')); ?></b>
					</div>
				</a>
			</div>
		<?php } ?>
		</div>
		
		<button type="button" class="edit theme">
			<?php esc_html_e('Edit theme', UpfrontThemeExporter::DOMAIN); ?>
		</button>
		<button type="button" class="edit info">
			<?php esc_html_e('Edit theme details', UpfrontThemeExporter::DOMAIN); ?>
		</button>
	<?php } else { ?>
		<p>
			<?php esc_html_e('No existing themes, please create a new one.', UpfrontThemeExporter::DOMAIN); ?>
		</p>
	<?php } ?>
	</div>

	<div class="uf-thx-new_theme uf-thx-pane">
	<?php if (empty($_GET['theme'])) { ?>
		<h3><?php esc_html_e('Build a new theme', UpfrontThemeExporter::DOMAIN); ?></h3>

		<?php Thx_Template::plugin()->load('theme_form'); ?>

		<button type="button" class="create info">
			<?php esc_html_e('Start building', UpfrontThemeExporter::DOMAIN); ?>
		</button>
	<?php } else { ?>
		<?php $theme = wp_get_theme($_GET['theme']); ?>
		<h3><?php echo esc_html(sprintf(__('Edit %s', UpfrontThemeExporter::DOMAIN), $theme->get('Name'))); ?></h3>

		<?php 
			Thx_Template::plugin()->load('theme_form', array(
				'name' => $theme->get('Name'),
				'slug' => $theme->get_stylesheet(),
				'author' => $theme->get('Author'),
				'author_uri' => $theme->get('AuthorURI'),
				'description' => $theme->get('Description'),
				'version' => $theme->get('Version'),
				'theme_uri' => $theme->get('ThemeURI'),
				'licence' => $theme->get('Licence'),
				'licence_uri' => $theme->get('LicenceURI'),
				'tags' => $theme->get('Tags'),
				'text_domain' => $theme->get('TextDomain'),
			)); 
		?>

		<button type="button" class="edit info">
			<?php esc_html_e('Edit info', UpfrontThemeExporter::DOMAIN); ?>
		</button>
	<?php } ?>
	</div>

</div>

<script type="text/javascript">
;(function ($) {

function edit_theme (theme) {
	var search = window.location.search.toString(),
		url = window.location.pathname.toString().replace(/\/theme/, '/' + theme)
	;
	if (search.length && search.match(/[?&]dev=/)) {
		url += '?dev=true';
	}
	window.location.assign(url);
}

function get_data () {
	var data = {},
		$ins = $(".uf-thx-new_theme input, .uf-thx-new_theme textarea")
	;
	$ins.each(function () {
		var $me = $(this),
			idx = $me.attr("id"),
			value = $me.is(":checkbox") ? $me.is(":checked") : $me.val()
		;
		data["thx-" + idx] = value;
	});
	return data;
}

function init_new () {
	$(".uf-thx-new_theme")
		.on("click", "button.create.info", function (e) {
			e.preventDefault();
			e.stopPropagation();

			var data = get_data();
			if (!data['thx-theme-slug']) return false;
			
			data.add_global_regions = true;

			Upfront.Util.post({
				action: 'upfront_thx-create-theme',
				form: _.map(data, function(value, key){ return key + '=' + value; }).join('&')
			}).success(function(response){
				window.location.pathname = window.location.pathname.replace(/\/theme/, '/' + data['thx-theme-slug']);
			}).error(function(){
				console.log("error");
			});

			return false;
		})
		.on("click", "button.edit.info", function (e) {
			e.preventDefault();
			e.stopPropagation();

			var data = get_data();
			if (!data['thx-theme-slug']) return false;
			
			data.add_global_regions = true;

			Upfront.Util.post({
				action: 'upfront_thx-update-theme',
				form: _.map(data, function(value, key){ return key + '=' + value; }).join('&')
			}).success(function(response){
				window.location.reload();
			}).error(function(){
				console.log("error");
			});

			return false;
		})
		.on("click", "button.edit.theme", function (e) {
			e.preventDefault();
			e.stopPropagation();
			
			var current = $.trim($(this).attr('data-theme'));
			if (!current) return false;
			
			edit_theme(current);

			return false;			
		})
	;
}

function init_existing () {
	$(".uf-thx-existing_theme")
		.on("click", "button.edit.theme", function (e) {
			e.preventDefault();
			e.stopPropagation();
			
			var current = $.trim($(".uf-thx-existing_theme .uf-thx-theme.current").attr('data-theme'));
			if (!current) return false;
			
			edit_theme(current);

			return false;			
		})
	;
}

function init () {
	init_new();
	init_existing();
}

$(document).on("upfront-load", init);

})(jQuery);
</script>

<style type="text/css">
.uf-thx-initial {
	width: 80%;
	min-width: 400px;
	clear: both;
}
.uf-thx-pane {
	float: left;
	width: 49%;
	padding: .5%;
}
.uf-thx-pane label {
	display: block;
}

.uf-thx-new_theme {
	left: 0;
}

.uf-thx-existing_theme {
	left: 50%;
}
.uf-thx-existing_theme .uf-thx-theme {
	width: 50px;
	overflow: hidden;
	float: left;
	margin-left: 10px;
}
.uf-thx-existing_theme .uf-thx-theme.current {
	border: 5px solid red;
}
</style>