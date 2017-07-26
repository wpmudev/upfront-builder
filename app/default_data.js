(function ($, undefined) {

	upfrontrjs.define([
		Upfront.themeExporter.root + 'app/data/default_typography.js',
		'text!' + Upfront.themeExporter.root + 'app/data/default_style.css'
	], function(typography, style) {

		return {
			default_typography: typography.default_typography,
			default_style: style
		};

	});
})(jQuery);
