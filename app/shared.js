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

				/**
				 * Responsible for showing the context mode popup
				 * Only happens when builder is installed, but affects
				 * both editor and builder.
				 */
				'mode-context-dialog': function () {
					var skip = parseInt(
						(((Upfront || {}).data || {}).exporter_shared || {}).context_mode,
						10
					);
					if (skip > 0) return false;

					var content = Upfront.Application.is_builder()
						? Upfront.mainData.l10n.exporter.builder_mode_context
						: Upfront.mainData.l10n.exporter.editor_mode_context
					;
					Upfront.Popup.open(function (data, $head, $foot) {
						$(this).html('<p>' + content + '</p>');
						$foot.append('<p>' +
							'<button type="button">' +
								'OK' +
							'</button>' +
							'<label>' +
								'<input type="checkbox" />' +
								'Never show this again' +
							'</label>' +
						'</p>');
						$foot.find('button')
							.off('click')
							.on('click', function (e) {
								if (e && e.preventDefault) e.preventDefault();
								if (e && e.stopPropagation) e.stopPropagation();

								var $chk = $foot.find(":checkbox");
								if (!!$chk.is(":checked")) Upfront.plugins.call('mode-context-request');

								Upfront.Popup.close();

								return false;
							})
						;
					});
				},

				/**
				 * Responsible for remembering mode context popup user preference
				 * Sends a request to server to make preference rememering happen.
				 */
				'mode-context-request': function () {
					Upfront.Util.post({
						action: 'upfront_thx-mode_context-skip'
					})
						.always(function () {
							console.log("Well hello there", arguments);
						})
					;
				}
			}
		});

	});
})(jQuery);
