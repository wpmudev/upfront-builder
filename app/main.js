(function($) {
	var dependencies = [
		Upfront.themeExporter.root + 'app/styles.js',
		Upfront.themeExporter.root + 'app/postlayout.js',
		Upfront.themeExporter.root + 'app/application.js'
	];
	require(dependencies, function(StylesHelper, PostLayoutHelper){
		StylesHelper.init();
		PostLayoutHelper.init();

		Upfront.Events.on("upfront:layout:loaded", function () {
			Upfront.data.global_regions = false; // Reset global regions info on layout load, so fresh batch is forced
		});

		require([
			Upfront.themeExporter.root  + 'app/sidebar.js',
			Upfront.themeExporter.root  + 'app/modal.js',
		], function (sidebar, modal) {
			sidebar.init();
			modal.init();
		});

	});
})(jQuery);
