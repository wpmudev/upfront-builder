<?php
/**
 * Template: Initial builder page template 
 */
?>
<?php get_header(); ?>

<?php
	$themes = array();
	foreach(wp_get_themes() as $stylesheet=>$theme) {
		if ($theme->get('Template') !== 'upfront') continue;
		$themes[$stylesheet] = $theme->get('Name');
	}
?>

<div class="uf-thx-initial">
	
	<div class="uf-thx-new_theme uf-thx-pane">
		<h3><?php esc_html_e('New Theme', UpfrontThemeExporter::DOMAIN); ?></h3>

		<label for="theme-name">
			<?php esc_html_e('Name', UpfrontThemeExporter::DOMAIN); ?>
			<input type="text" id="theme-name" placeholder="<?php esc_attr_e('Name for your theme', UpfrontThemeExporter::DOMAIN); ?>" />
		</label>
		<label for="theme-slug">
			<?php esc_html_e('Directory', UpfrontThemeExporter::DOMAIN); ?>
			<input type="text" id="theme-slug" placeholder="<?php esc_attr_e('Base directory for your theme', UpfrontThemeExporter::DOMAIN); ?>" />
		</label>
		<label for="author">
			<?php esc_html_e('Author', UpfrontThemeExporter::DOMAIN); ?>
			<input type="text" id="author" placeholder="<?php esc_attr_e('Your name', UpfrontThemeExporter::DOMAIN); ?>" />
		</label>
		<label for="author-uri">
			<?php esc_html_e('Author URL', UpfrontThemeExporter::DOMAIN); ?>
			<input type="text" id="author-uri" placeholder="<?php esc_attr_e('Your URL', UpfrontThemeExporter::DOMAIN); ?>" />
		</label>

		<label for="activate_theme">
			<input type="checkbox" checked id="activate_theme" />
			<?php esc_html_e('Activate theme', UpfrontThemeExporter::DOMAIN); ?>
		</label>
		<label for="export_with_images">
			<input type="checkbox" checked id="export_with_images" />
			<?php esc_html_e('Export with images', UpfrontThemeExporter::DOMAIN); ?>
		</label>

		<button type="button">
			<?php esc_html_e('Create', UpfrontThemeExporter::DOMAIN); ?>
		</button>
	</div>

	<div class="uf-thx-existing_theme uf-thx-pane">
	<?php if (!empty($themes)) { ?>
		<h3><?php esc_html_e('Existing Theme', UpfrontThemeExporter::DOMAIN); ?></h3>
		<label>
			<select>
				<option><?php esc_html_e('Please, select one', UpfrontThemeExporter::DOMAIN); ?></option>
			<?php foreach ($themes as $key => $name) { ?>
				<option value="<?php echo esc_attr($key); ?>">
					<?php echo esc_html($name); ?>
				</option>
			<?php } ?>
			</select>
		</label>
		<button type="button">
			<?php esc_html_e('Edit', UpfrontThemeExporter::DOMAIN); ?>
		</button>
	<?php } else { ?>
		<p>
			<?php esc_html_e('No existing themes, please create a new one.', UpfrontThemeExporter::DOMAIN); ?>
		</p>
	<?php } ?>
	</div>
</div>

<script type="text/javascript">
;(function ($) {

function init_new () {
	$(".uf-thx-new_theme").on("click", "button", function (e) {
		e.preventDefault();
		e.stopPropagation();

		var data = {},
			$ins = $(".uf-thx-new_theme input")
		;
		$ins.each(function () {
			var $me = $(this),
				idx = $me.attr("id"),
				value = $me.is(":checkbox") ? $me.is(":checked") : $me.val()
			;
			data["thx-" + idx] = value;
		});
		data.add_global_regions = true;

		if (!data['thx-theme-slug']) return false;

		Upfront.Util.post({
			action: 'upfront_thx-create-theme',
			form: _.map(data, function(value, key){ return key + '=' + value; }).join('&')
		}).success(function(response){
			window.location.pathname = window.location.pathname.replace(/\/theme/, '/' + data['thx-theme-slug']);
		}).error(function(){
			console.log("error");
		});

		return false;
	});
}

function init_existing () {
	$(".uf-thx-existing_theme").on("click", "button", function (e) {
		e.preventDefault();
		e.stopPropagation();

		var current = $.trim($(".uf-thx-existing_theme select option:selected").val());
		if (!current) return false;

		window.location.pathname = window.location.pathname.replace(/\/theme/, '/' + current);

		return false;
	});
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
.uf-thx-new_theme {
	left: 0;
}
.uf-thx-existing_theme {
	left: 50%;
}
.uf-thx-pane label {
	display: block;
}
</style>

<?php get_footer(); ?>