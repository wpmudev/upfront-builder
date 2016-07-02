;(function ($) {

	require([
		Upfront.themeExporter.root + 'app/dialogs.js'
	], function (Dialogs) {


		/**
		 * We are loading theme by reloading page since lots of stuff needs
		 * to be setup like stylesheet etc. Only way to get this right is to
		 * load page from scratch.
		 */
		var load_theme = function (theme_slug) {
			var url = location.origin;
			// Add anything before create_new
			url += location.pathname.split('create_new')[0];
			// Add create_new and theme slug
			url += 'create_new/' + theme_slug;
			// Check for dev=true
			if (location.toString().indexOf('dev=true') > -1) url += '?dev=true';

			window.location = url;
		};

		((Upfront || {}).Application || {}).ThemeEditor = new (Upfront.Subapplication.extend({
			boot: function () {

			},

			start: function () {
				this.stop();
				this.set_up_event_plumbing_before_render();
				// @TODO hack to implement LayoutEditor objects
				this.Objects = Upfront.Application.LayoutEditor.Objects;
				this.set_up_editor_interface();

				this.set_up_event_plumbing_after_render();
				$("html").removeClass("upfront-edit-layout upfront-edit-content upfront-edit-postlayout upfront-edit-responsive").addClass("upfront-edit-theme");
				if ( Upfront.themeExporter.currentTheme === 'upfront') {
					this.listenToOnce(Upfront.Events, 'layout:render', function() {
						Upfront.Events.trigger("command:layout:edit_structure");
					});
				}

				this.listenToOnce(Upfront.Events, 'layout:render', Upfront.Behaviors.GridEditor.apply_grid);
				this.listenTo(Upfront.Events, "command:layout:edit_structure", Upfront.Behaviors.GridEditor.edit_structure);
				this.listenTo(Upfront.Events, "builder:load_theme", load_theme);

				this.listenTo(Upfront.Events, "command:themefontsmanager:open", Dialogs.open_theme_fonts_manager);
				this.listenToOnce(Upfront.Events, 'command:layout:save_done', Dialogs.first_save_dialog);
				this.listenTo(Upfront.Events, "command:layout:create", Dialogs.create_layout_dialog); // DEPRECATED
				this.listenTo(Upfront.Events, "command:layout:browse", Dialogs.browse_layout_dialog); // DEPRECATED
				this.listenTo(Upfront.Events, "command:layout:export_theme", Dialogs.export_dialog);
			},

			stop: function () {
				return this.stopListening(Upfront.Events);
			}

		}))();

	});

})(jQuery);
