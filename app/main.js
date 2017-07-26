(function($) {
	var dependencies = [
		Upfront.themeExporter.root + 'app/styles.js',
		Upfront.themeExporter.root + 'app/postlayout.js',
		Upfront.themeExporter.root + 'app/application.js'
	];
	upfrontrjs.require(dependencies, function(StylesHelper, PostLayoutHelper){
		// Replace _.template only when we actually boot Upfront, otherwise some other scripts using it might break
		var _tpl = _.template;
		_.template = function (tpl, data) {
			if (typeof undefined === typeof data) return _tpl(tpl);
			var tmp = _tpl(tpl);
			return tmp(data);
		};

		StylesHelper.init();
		PostLayoutHelper.init();

		Upfront.Events.on("upfront:layout:loaded", function () {
			Upfront.data.global_regions = false; // Reset global regions info on layout load, so fresh batch is forced
		});

		upfrontrjs.require([
			Upfront.themeExporter.root  + 'app/sidebar.js',
			Upfront.themeExporter.root  + 'app/modal.js',
		], function (sidebar, modal) {
			sidebar.init();
			modal.init();
		});

	});
})(jQuery);
