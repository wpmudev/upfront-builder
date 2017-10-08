;(function ($) {

	/**
	 * Resets the status output area
	 *
	 * @return {Object} Output area jQuery node wrapper
	 */
	function reset_output_area () {
		var $area = $(".wp-admin .notice .upfront-kickstart-out");

		$area
			.removeClass("success")
			.removeClass("error")
			.empty()
			.hide()
		;

		return $area;
	}

	/**
	 * Dispatch error message
	 *
	 * @param {String} msg Optional error message
	 *
	 * @return {Boolean}
	 */
	function dispatch_error (msg) {
		var fallback = (_thx_kickstart || {}).general_error || 'General error';
		msg = msg || fallback;

		reset_output_area()
			.addClass("error")
			.text(msg)
			.show()
		;

		return true;
	}

	/**
	 * Build process start click event handler
	 *
	 * @param {Object} e Event
	 *
	 * @return {Boolean}
	 */
	function start_building (e) {
		if (e && e.preventDefault) e.preventDefault();
		if (e && e.stopPropagation) e.stopPropagation();

		reset_output_area();

		$.post(ajaxurl, {
			action: 'upfront-kickstart-start_building'
		}).done(function (response) {
			var status = (response || {}).success,
				payload = (response || {}).data
			;
			if (!status) return dispatch_error(payload);
			else if (!payload.match(/admin.php\?page=upfront-builder/)) return dispatch_error();
			else {
				var msg = (_thx_kickstart || {}).success_msg || '';
				if (msg) {
					reset_output_area()
						.addClass("success")
						.text(msg)
						.show()
					;
				}
				window.location = payload;
			}

			return true;
		}).error(function () {
			return dispatch_error();
		});

		return false;
	}

	/**
	 * Sends perma-disabling AJAX request
	 *
	 * @param {Object} e Click event (optional)
	 *
	 * @return {Boolean} false
	 */
	function go_away (e) {
		if (e && e.preventDefault) e.preventDefault();
		if (e && e.stopPropagation) e.stopPropagation();

		$.post(ajaxurl, {
			action: 'upfront-kickstart-go_away'
		}).done(function () {
			var el_notice = $(".wp-admin .notice.uf-thx-kickstart");

			el_notice.fadeTo(100 , 0, function() {
				el_notice.slideUp(100, function() {
					el_notice.remove();
				});
			});
		});

		return false;
	}

	/**
	 * Initializes click listeners
	 *
	 * @return {Boolean}
	 */
	function init () {
		if (!window.ajaxurl) return false;

		$(document).on(
			"click",
			".wp-admin .notice #upfront-kickstart-start_building",
			start_building
		);

		$(document).on(
			"click",
			".wp-admin .notice #upfront-kickstart-go_away",
			go_away
		);
		$(document).on(
			"click",
			".wp-admin .notice.uf-thx-kickstart .notice-dismiss",
			go_away
		);

		return true;
	}

	$(init);

})(jQuery);
