(function($) {
	var dependencies = [
		Upfront.themeExporter.root + 'app/styles.js',
		Upfront.themeExporter.root + 'app/postlayout.js'
	]
	require(dependencies, function(StylesHelper, PostLayoutHelper){
		StylesHelper.init();
		PostLayoutHelper.init();

		/*
		var cView = Backbone.View.extend({
			events: {
				'click a[href="#change"]': 'spawn_info_change_interface'
			},
			render: function () {
				this.$el.empty().append('<a href="#change">edit info</a>');
			},
			spawn_info_change_interface: function (e) {
				e.preventDefault();
				e.stopPropagation();

				this._modal = new Upfront.Views.Editor.Modal({to: $('body'), button: false, top: 120, width: 540});
				this._modal.render();
				$('body').append(this._modal.el);

				var me = this;
				
				Upfront.Util.post({action: 'upfront_thx-get-theme_info'})
					.done(function () {
						me._modal.open(function ($content) {
							console.log("yay open")
							$content.append("NANANANAN");
						});
					})
				;
			}
		});

		$(document).on("upfront-load", function () {
			Upfront.Events.on("application:mode:after_switch", function () {
				Upfront.Application.sidebar.sidebar_commands.additional = new cView({model: Upfront.Application.sidebar.model});
				Upfront.Application.sidebar.render();
			});
		});
		*/
		$(document).on("upfront-load", function () {
			Upfront.Events.on("upfront:layout:loaded", function () {
				Upfront.data.global_regions = false; // Reset global regions info on layout load, so fresh batch is forced
			});
		});

	});
})(jQuery);
