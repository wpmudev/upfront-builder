<?php
	$themes = array();
	$fallback_screenshot = plugins_url(THX_BASENAME . '/imgs/testImage.jpg');

	foreach(wp_get_themes() as $stylesheet=>$theme) {
		if ($theme->get('Template') !== 'upfront') continue;
		$themes[$stylesheet] = $theme;
	}
?>

<header class="builder">
	<h2><?php esc_html_e('Upfront Builder', UpfrontThemeExporter::DOMAIN); ?></h2>
	<p>
		<?php esc_html_e('Build your own, slick, customizable, intuitive Upfront themes.', UpfrontThemeExporter::DOMAIN); ?>
		<br />
		<?php esc_html_e('Creating a WordPress theme has never been easier.', UpfrontThemeExporter::DOMAIN); ?>
	</p>
</header>

<div class="uf-thx-initial clearfix">
	
	<div class="uf-thx-existing_theme uf-thx-pane">
	<?php if (!empty($themes)) { ?>
		<h3><?php esc_html_e('Modify existing theme:', UpfrontThemeExporter::DOMAIN); ?></h3>
		
		<label class="inline"><span class="description"><?php esc_html_e('Select a theme to modify:', UpfrontThemeExporter::DOMAIN); ?></span></label>
		<div class="uf-thx-themes_container clearfix">
		<?php foreach ($themes as $key => $theme) { ?>
			<div class="uf-thx-theme <?php if (!empty($_GET['theme']) && $theme->get_stylesheet() === $_GET['theme']) echo 'current'; ?>">
				<a href="?theme=<?php echo esc_attr($theme->get_stylesheet()); ?>">
					<?php $screenshot = $theme->get_screenshot() ? $theme->get_screenshot() : $fallback_screenshot; ?>
					<img src="<?php echo esc_url($screenshot); ?>" />
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
		<label class="inline"><span class="description">
			<?php esc_html_e('No existing themes, please create a new one.', UpfrontThemeExporter::DOMAIN); ?>
		</span></label>
	<?php } ?>
	</div>

	<div class="uf-thx-new_theme uf-thx-pane">
	<?php if (empty($_GET['theme'])) { ?>
		<h3><?php esc_html_e('Build a new theme:', UpfrontThemeExporter::DOMAIN); ?></h3>

		<?php Thx_Template::plugin()->load('theme_form'); ?>

		<button type="button" class="create theme">
			<?php esc_html_e('Start building', UpfrontThemeExporter::DOMAIN); ?>
		</button>
	<?php } else { ?>
		<?php $theme = wp_get_theme($_GET['theme']); ?>
		<h3><?php echo esc_html(sprintf(__('Edit &quot;%s&quot;:', UpfrontThemeExporter::DOMAIN), $theme->get('Name'))); ?></h3>

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
				'screenshot' => ($theme->get_screenshot() ? $theme->get_screenshot() : $fallback_screenshot)
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
		.on("click", "button.create.theme", function (e) {
			e.preventDefault();
			e.stopPropagation();

			var data = get_data(),
				slug
			;
			if ('thx-theme-slug' in data) {
				slug = data['thx-theme-slug'];
			}
			data.add_global_regions = true;

			Upfront.Util.post({
				action: 'upfront_thx-create-theme',
				form: _.map(data, function(value, key){ return key + '=' + escape(value); }).join('&')
			}).success(function(response) {
				if (!slug && response && "theme" in response) {
					slug = (response.theme || {directory: false}).directory;
				}
				if (slug) window.location.pathname = window.location.pathname.replace(/\/theme/, '/' + slug);
				else window.location.reload();
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
				form: _.map(data, function(value, key){ return key + '=' + escape(value); }).join('&')
			}).success(function(response) {
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
		.on("click", ".uf-thx-theme a", function (e) {
			e.preventDefault();
			e.stopPropagation();

			var $me = $(this).closest(".uf-thx-theme");
			if (!$me.length) return false;

			$(".uf-thx-existing_theme .uf-thx-theme").removeClass("current");
			$me.addClass("current");

			return false;
		})
		.on("click", "button.edit.info", function (e) {
			e.preventDefault();
			e.stopPropagation();
			
			var current = $(".uf-thx-existing_theme .uf-thx-theme.current a").attr('href');
			if (!current) return false;
			
			window.location = current;

			return false;			
		})
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
body {
	background: rgb(241, 241, 241);
	color: rgb(76, 83, 85);
}
header.builder {
	border-bottom: 1px solid rgb(216, 216, 216);
	padding: 40px;
	text-align: center;
}
header.builder h2 {
	font-size: 30px;
}
header.builder p {
	font-size: 16px;
	color: rgb(76, 83, 85);
	margin-top: 1em;
}

.uf-thx-initial {
	min-width: 400px;
}
.uf-thx-pane {
	float: left;
	padding: 10px;
}

.uf-thx-new_theme {
	margin-left: -1px;
	border-left: 1px solid rgb(216, 216, 216);
	padding-left: 35px;
}
.uf-thx-existing_theme {
	width: 450px;
	border-right: 1px solid rgb(216, 216, 216);
}
.uf-thx-existing_theme h3, label.inline {
	padding-left: 20px;
}
.uf-thx-existing_theme .uf-thx-themes_container {
	margin-top: -15px;
}

/* forms */
.uf-thx-pane h3 {
	margin-bottom: 1em;
	font-size: 23px;
}
.uf-thx-pane label {
	display: block;
	color: rgb(109, 114, 116);
	margin-bottom: 1em;
	font-size: 16px;
}
.uf-thx-pane label.inline {
	margin-bottom: 0;
}
.uf-thx-pane label span.description {
	display: block;
}
.uf-thx-pane label.inline span.description {
	display: inline;
}
.uf-thx-pane label input[type="text"],
.uf-thx-pane label textarea
{
	border: 5px solid rgb(223, 223, 223);
	padding: 15px;
	min-width: 300px;
	width: 100%;
	color: rgb(109, 114, 116);
	font-size: 16px;
}
button {
	color: rgb(126, 131, 132);
	background: none;
}
button.theme {
	padding: 12px 46px;
	border: 5px solid rgb(31, 205, 143);
	font-weight: bold;
}
button.info {
	border-bottom: 1px solid rgb(31, 205, 143);
}

/* list */
.uf-thx-existing_theme .uf-thx-theme {
	width: 110px;
	height: 110px;
	float: left;
	margin: 15px;
	position: relative;
	border: 3px solid transparent;
}
.uf-thx-existing_theme .uf-thx-theme img {
	width: 110px;
	min-height: 110px;
}
.uf-thx-existing_theme .uf-thx-theme .uf-thx-caption {
	width: 100%;
	overflow: hidden;
	background: rgba(100, 100, 100, .7);
	position: absolute;
	bottom: 0px;
}
.uf-thx-existing_theme .uf-thx-theme .uf-thx-caption b {
	margin: 9px 18px;
	display: block;
	font-weight: normal;
	color: #fff;
}
/* list - current */
.uf-thx-existing_theme .uf-thx-theme.current {
	border: 3px solid rgb(31, 205, 143);
}
.uf-thx-existing_theme .uf-thx-theme.current .uf-thx-caption {
	bottom: 0;
}
.uf-thx-existing_theme .uf-thx-theme.current:before {
	content: "\2714";
	display: block;
	position: absolute;
	right: -8px;
	top: -8px;
	width: 30px;
	height: 30px;
	background: rgb(35, 49, 64);
	color: rgb(31, 205, 143);
	text-align: center;
	line-height: 30px;
}

/* info pane */
.uf-thx-theme_info .uf-thx-theme_screenshot {
	float: left;
	width: 370px;
}
.uf-thx-theme_info .uf-thx-theme_meta {
	float: left;
	margin-left: 25px;
}
</style>