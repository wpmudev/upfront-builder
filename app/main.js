(function($) {
	var dependencies = [
		Upfront.themeExporter.root + 'app/styles.js',
		Upfront.themeExporter.root + 'app/postlayout.js'
	]
	require(dependencies, function(StylesHelper, PostLayoutHelper){
		StylesHelper.init();
		PostLayoutHelper.init();

		$(document).on("upfront-load", function () {

			Upfront.Events.on("upfront:layout:loaded", function () {
				Upfront.data.global_regions = false; // Reset global regions info on layout load, so fresh batch is forced
			});
/*
			require([Upfront.themeExporter.root  + 'app/commands.js'], function (sidebar) {
				sidebar.init();
			});
*/
		});

	});
})(jQuery);
