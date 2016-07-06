;(function ($) {

	require([
		Upfront.themeExporter.root + 'app/dialogs.js',
		Upfront.themeExporter.root + 'app/exporter.js'
	], function (Dialogs, Exporter) {

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
				this.listenTo(Upfront.Events, "builder:load_theme", Exporter.load_theme);

				this.listenTo(Upfront.Events, "command:themefontsmanager:open", Dialogs.open_theme_fonts_manager);
				this.listenToOnce(Upfront.Events, 'command:layout:save_done', Dialogs.first_save_dialog);
				this.listenTo(Upfront.Events, "command:layout:create", Dialogs.create_layout_dialog); // DEPRECATED
				this.listenTo(Upfront.Events, "command:layout:browse", Dialogs.browse_layout_dialog); // DEPRECATED
				this.listenTo(Upfront.Events, "command:layout:export_theme", Dialogs.export_dialog);
				
				var skip_getting_started = parseInt(_upfront_builder_getting_started, 10);
				if ( skip_getting_started !== 1 ) {
						setTimeout(function(){
							Dialogs.getting_started_exp();
					}, 500);
				}
			},

			stop: function () {
				return this.stopListening(Upfront.Events);
			}

		}))();

		Upfront.Application.ThemeEditor._get_saved_layout = Exporter._get_saved_layout;

		if (!Exporter.is_exporter_start_page()) {
			// Start the subapplication
			jQuery(document).data("upfront-auto_start", true);
			Upfront.Application.start("theme");
		}

	});

})(jQuery);
