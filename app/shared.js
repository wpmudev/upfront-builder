;(function ($, undefined) {
	"use strict";

	/**
	 * This is the common file exposed by exporter,
	 * that will always be loaded, regardless if we're
	 * booting Editor or Exporter
	 */

	$(document).on('Upfront:loaded', function () {

		Upfront.plugins.addPlugin({
			'name': 'Shared',
			'callbacks': {
				'mode-context-dialog': function () {
					var content = Upfront.Application.is_builder()
						? Upfront.mainData.l10n.exporter.builder_mode_context
						: Upfront.mainData.l10n.exporter.editor_mode_context
					;
					Upfront.Popup.open(function ($body) {
						$(this).text(content);
					});
				}
			}
		});

	});
})(jQuery);
