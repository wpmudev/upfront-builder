;(function ($, undefined) {

function edit_theme (theme) {
	var search = window.location.search.toString(),
		url = (window._thx || {}).editor_base.replace(/\/theme/, '/' + theme)
	;
	if (!url) return false;

	if (search.length && search.match(/[?&]dev=/)) {
		url += '?dev=true';
	}
	window.location.assign(url);
}

function get_data () {
	var data = {},
		$ins = $(".uf-thx-new_theme input, .uf-thx-new_theme textarea, .uf-thx-new_theme select")
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
				base_url = (window._thx || {}).editor_base || window.location.pathname,
				slug
			;
			if ('thx-theme-slug' in data) {
				slug = data['thx-theme-slug'];
			}
			data.add_global_regions = true;

			// Hack-force current mode to exporter, so this can be reused in admin
			Upfront.Application.mode.current = Upfront.Application.MODE.THEME;
			Upfront.Util.post({
				action: 'upfront_thx-create-theme',
				form: _.map(data, function(value, key){ return key + '=' + escape(value); }).join('&')
			}).success(function(response) {
				if (!slug && response && "theme" in response) {
					slug = (response.theme || {directory: false}).directory;
				}
				if (slug) window.location = base_url.replace(/\/theme/, '/' + slug);
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

			// Hack-force current mode to exporter, so this can be reused in admin
			Upfront.Application.mode.current = Upfront.Application.MODE.THEME;
			Upfront.Util.post({
				action: 'upfront_thx-update-theme',
				form: _.map(data, function(value, key){ return key + '=' + escape(value); }).join('&')
			}).success(function(response) {
				//window.location.reload();
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
		.on("click", ".uf-thx-theme_screenshot", function (e) {
			e.preventDefault();
			e.stopPropagation();

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

			$(".uf-thx-existing_theme .uf-thx-theme").removeClass("selected");
			$me.addClass("selected");

			return false;
		})
		.on("click", "button.edit.info", function (e) {
			e.preventDefault();
			e.stopPropagation();

			var current = $(".uf-thx-existing_theme .uf-thx-theme.selected a").attr('href');
			if (!current) return false;

			window.location = current;

			return false;
		})
		.on("click", "button.edit.theme", function (e) {
			e.preventDefault();
			e.stopPropagation();

			var current = $.trim($(".uf-thx-existing_theme .uf-thx-theme.selected").attr('data-theme'));
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
