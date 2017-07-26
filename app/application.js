;(function ($) {

	upfrontrjs.require([
		Upfront.themeExporter.root + 'app/dialogs.js',
		Upfront.themeExporter.root + 'app/exporter.js',
		Upfront.themeExporter.root + 'app/default_data.js',
		Upfront.themeExporter.root + 'app/post_image.js'
	], function (Dialogs, Exporter, DefaultData, postImage) {

		// JUST A BIG BLOCK OF STUFF MOVED FROM UPFRONT TO EXPORTER
		if (Upfront && Upfront.plugins) {
			var l10n = Upfront.Settings && Upfront.Settings.l10n ?
				Upfront.Settings.l10n.exporter :
				Upfront.mainData.l10n.exporter
			;
			var altl10n = Upfront.Settings && Upfront.Settings.l10n
				? Upfront.Settings.l10n.global.views
				: Upfront.mainData.l10n.global.views
			;

			var Command_ExportLayout = Upfront.Views.Editor.Command.extend({
				className: "command-export sidebar-commands-button blue",
				render: function (){
					if (Upfront.Application.mode.current === 'responsive') {
						this.$el.text(altl10n.save);
					} else {
						this.$el.text(l10n.export_str);
					}
				},
				on_click: function () {
					$('div.redactor_editor').each(function() {
						var ed = $(this).data('ueditor');
						if(ed)
							ed.stop();
					});

					// check if theme being edited is the current active one
					if ( Upfront.themeExporter.currentTheme === window._upfront_theme_exporter_active_theme ) {
						Upfront.Events.trigger("command:layout:export_theme");
					} else {
						// ask user if to activate the theme being edited first before exporting
						Dialogs.activate_edited_theme(l10n);
					}
				}
			});

			var Command_Themes = Upfront.Views.Editor.Command.extend({
				className: "command-themes upfront-icon upfront-icon-themes sidebar-commands-button light",
				render: function (){
					this.$el.text(l10n.themes);
				},
				on_click: function () {
					window.location.href = Upfront.themeExporter.admin_url;
				}
			});

			var Command_CreateResponsiveLayouts = Upfront.Views.Editor.Command.extend({
				enabled: true,
				className: 'command-create-responsive-layouts upfront-icon upfront-icon-start-responsive sidebar-commands-small-button',
				render: function () {
					this.$el.html("<span title='"+ l10n.create_responsive_layouts +"'>" + l10n.create_responsive_layouts + "</span>");
				},
				on_click: function () {
					Upfront.Application.start(Upfront.Application.MODE.RESPONSIVE);
				}
			});

			var Command_EditStructure = Upfront.Views.Editor.Command.extend({
				tagName: 'div',
				className: "command-link command-edit-structure sidebar-commands-small-button",
				render: function (){
					this.$el.html(l10n.edit_grid);
					this.$el.prop("title", l10n.edit_grid);
				},
				on_click: function () {
					Upfront.Events.trigger("command:layout:edit_structure");
				}
			});

			Upfront.plugins.addPlugin({
				name: 'Exporter',
				forbidden: [
					'show undo redo and responsive commands',
					'show reset everything command',
					'show publish layout command',
					'show save layout command',
					'show preview layout command',
					'show sidebar profile',
					'initialize featured image selector',
					'show save as dialog',
					'show region list trash',
					'trigger post editor',
					'show import image dialog'
				],
				required: [
					'generate fake post id',
					'show choose fonts button',
					'show feature image region type',
					'setup drag and drop',
					'setup resizeable',
					'update grid',
					'media filter upload'
				],
				callbacks: {
					'insert-save-buttons': function(parameters) {
						parameters.commands.push(new Command_Themes({"model": parameters.model}));
						parameters.commands.push(new Command_ExportLayout({"model": parameters.model}));
					},
					'insert-responsive-save-buttons': function(parameters) {
						parameters.commands.push(new Command_ExportLayout({"model": parameters.model}));
					},
					'insert-responsive-buttons': function(parameters) {
						parameters.commands.push(
							new Command_CreateResponsiveLayouts({model: parameters.model})
						);
					},
					'insert-command-after-typography-commands': function(parameters) {
							var edit_structure = new Command_EditStructure({"model": parameters.model});
							edit_structure.render();
							edit_structure.delegateEvents();
							parameters.rootEl.find('.panel-section-content').append(edit_structure.el);
					},
					'do-action-after-sidebar-settings-render': function(parameters) {
						// not needed for now as Draggable Elements will be the default expanded
						// setTimeout( function() {
							// parameters.settingsEl.find('.sidebar-panel-title').trigger('click');
						// }, 50);
					},
					'add-sidebar-commands-class': function(parameters) {
						return parameters.className + ' sidebar-commands-theme';
					},
					'get-default-typography': function(parameters) {
						return DefaultData.default_typography;
					},
					// Get long loading notice message.
					'long-loading-notice': function() {
						return l10n.long_loading_notice;
					},
					'cancel-post-layout': function() {
						Upfront.Events.trigger("post:layout:post:style:cancel");
					},
					'insert-css-editor-types': function (parameters) {
						parameters.types['ImageVariant'] = {label: l10n.image_variant, id: 'image_variant'};
					},
					'insert-css-editor-selectors': function (parameters) {
						parameters.cssEditor.createSelector(Upfront.Models.ImageVariant, postImage.PostImageVariant, 'ImageVariant');
					},
					'get-css-editor-selector': function (parameters) {
						var model = parameters.object.model.toJSON();
						return "vid" in (model || {}) && (model || {}).vid ? '[data-variant="' + model.vid + '"]' : '';
					},
					'css-editor-save-style': function(parameters) {
						parameters.data.stylename = parameters.stylename;
						if (parameters.isGlobalStylesheet) {
							var props = Upfront.Application.current_subapplication.layout.get('properties'),
								layout_styles = props && props.findWhere ? props.findWhere({name: 'layout_style'}) : false
							;
							if (layout_styles && layout_styles.set) {
								layout_styles.set({'value': parameters.styles});
							} else {
								props.add({name: "layout_style", value: parameters.styles});
							}
						}
						Exporter.export_element_styles(parameters.data);
					},
					'css-editor-headless-save-style': function(parameters) {
						parameters.data.stylename = parameters.stylename;
						Exporter.export_element_styles(parameters.data);
					},
					'update-background-slider': function(parameters) {
						// In builder always replace slide_images with server response
						Upfront.Views.Editor.ImageEditor.getImageData(parameters.slide_images).done(function(response){
							var images = response.data.images;
							// Rewrite slide images because in builder mode they will be just paths of theme images
							// and slider needs image objects to work.
							//slide_images = images;
							_.each(parameters.slide_images, function(id){
								var image = _.isNumber(id) || id.match(/^\d+$/) ? images[id] : _.find(images, function(img){
										return img.full[0].split(/[\\/]/).pop() == id.split(/[\\/]/).pop();
									}),
									$image = $('<div class="upfront-default-slider-item" />');
								if (image && image.full) $image.append('<img src="' + image.full[0] + '" />');
								parameters.typeEl.append($image);
							});
							parameters.me.slide_images = parameters.slide_images;
							parameters.typeEl.trigger('refresh');
						});
					},
					'clean-region-css': function(parameters) {
						Upfront.Application.ThemeEditor._get_saved_layout().done(function(saved){
							_.each(parameters.elementTypes, function(elementType){
								_.each(Upfront.data.styles[elementType.id], function(styleName){
									var onOtherLayout = false;
									_.each(saved, function(obj, id){
										if ( id == parameters.layout_id )
											return;
										var is_parent_layout = ( parameters.layout_id.match(new RegExp('^' + id + '-')) );
										if ( styleName.match(new RegExp('^' + id)) && ( !is_parent_layout || ( is_parent_layout && !styleName.match(new RegExp('^' + parameters.layout_id)) ) ) )
											onOtherLayout = true;
									});
									if ( ! _.contains(parameters.styleExists, styleName) && styleName.match(new RegExp('^' + parameters.layout_id)) && !onOtherLayout )
										parameters.deleteDatas.push({
											elementType: elementType.id,
											styleName: styleName
										});
								});
							});
							if ( parameters.deleteDatas.length > 0 ) {
								Upfront.Views.Editor.notify(Upfront.Settings.l10n.global.behaviors.cleaning_region_css);
								parameters.deleteFunc(0); // Start deleting
							}
						});
					},
					'prepare-delete-element-styles-data': function(parameters) {
						return {
							action: 'upfront_thx-delete-element-styles',
							data: {
								stylename: parameters.styleName,
								elementType: parameters.elementType
							}
						};
					},
					'save-settings': function() {
						Upfront.Events.trigger("command:layout:export_theme");
					}
				}
			});

			/**
			 * Edit structure/grid
			 */
			var edit_structure = function () {
				var ed = Upfront.Behaviors.GridEditor,
					app = Upfront.Application,
					grid = Upfront.Settings.LayoutEditor.Grid,
					grid_state = Upfront.Application.get_gridstate(),
					default_values = {
						column_width: 45,
						column_padding: 15,
						baseline: 5,
						type_padding: 10
					},
					min_values = {
						column_width: 40,
						column_padding: 0,
						baseline: 5,
						type_padding: 0
					},
					max_values = {
						column_width: 100,
						column_padding: 50,
						baseline: 50,
						type_padding: 50
					},
					$grid_wrap = $('<div class="upfront-edit-grid-wrap clearfix" />'),
					$recommended = $('<div class="upfront-edit-grid upfront-edit-grid-recommended" />'),
					$custom = $('<div class="upfront-edit-grid upfront-edit-grid-custom" />'),
					$color_wrap = $('<div class="upfront-edit-page-color" />'),
					$grid_width = $('<div class="upfront-grid-width-preview">Grid width: <span class="upfront-grid-width" /></div>'),
					$grid_width2 = $('<div class="upfront-grid-width-preview">( Grid width: <span class="upfront-grid-width" /> )</div>'),
					is_grid_custom = (
						grid.column_width != default_values.column_width
						|| grid.type_padding != default_values.type_padding
						|| grid.baseline != default_values.baseline
						|| !(/^(0|5|10|15)$/.test(grid.column_padding))
					),
					update_grid_data = function() {
						var custom = ( 'custom' === fields.grid.get_value() ),
							column_width = parseInt( custom ? fields.custom_width.get_value() : default_values.column_width, 10 ),
							column_padding = parseInt( custom ? fields.custom_padding.get_value() : fields.recommended_padding.get_value(), 10 ),
							baseline = parseInt( custom ? fields.custom_baseline.get_value() : default_values.baseline, 10 ),
							type_padding = parseInt( custom ? fields.custom_type_padding.get_value() : default_values.type_padding, 10 ),
							new_grid = {
								column_width: column_width > max_values.column_width
									? max_values.column_width
									: ( column_width < min_values.column_width ? min_values.column_width : column_width ),
								column_padding: column_padding > max_values.column_padding
									? max_values.column_padding
									: ( column_padding < min_values.column_padding ? min_values.column_padding : column_padding ),
								baseline: baseline > max_values.baseline
									? max_values.baseline
									: ( baseline < min_values.baseline ? min_values.baseline : baseline ),
								type_padding: type_padding > max_values.type_padding
									? max_values.type_padding
									: ( type_padding < min_values.type_padding ? min_values.type_padding : type_padding )
							},
							width = new_grid.column_width * grid.size
						;
						$grid_width.find('.upfront-grid-width').text(width + 'px');
						$grid_width2.find('.upfront-grid-width').text(width + 'px');
						ed.update_grid(new_grid);
					},
					togglegrid = new Upfront.Views.Editor.Command_ToggleGrid(),
					fields = {
						grid: new Upfront.Views.Editor.Field.Radios({
							label: Upfront.Settings.l10n.global.behaviors.grid_settings,
							layout: "horizontal-inline",
							default_value: is_grid_custom ? "custom" : "recommended",
							values: [
								{label: Upfront.Settings.l10n.global.behaviors.recommended_settings, value: "recommended"},
								{label: Upfront.Settings.l10n.global.behaviors.custom_settings, value: "custom"}
							],
							change: function () {
								var value = this.get_value();
								if ( value == 'custom' ){
									$custom.show();
									$recommended.hide();
								}
								else {
									// Set padding that isn't in recommended value
									if ( grid.column_padding >= 13 ) fields.recommended_padding.set_value(15);
									else if ( grid.column_padding >= 8 ) fields.recommended_padding.set_value(10);
									else if ( grid.column_padding >= 3 ) fields.recommended_padding.set_value(5);
									else fields.recommended_padding.set_value(0);

									$recommended.show();
									$custom.hide();
								}
								update_grid_data();
							}
						}),
						recommended_padding: new Upfront.Views.Editor.Field.Select({
							default_value: grid.column_padding,
							values: [
								{label: Upfront.Settings.l10n.global.behaviors.padding_large, value: 15},
								{label: Upfront.Settings.l10n.global.behaviors.padding_medium, value: 10},
								{label: Upfront.Settings.l10n.global.behaviors.padding_small, value: 5},
								{label: Upfront.Settings.l10n.global.behaviors.no_padding, value: 0}
							],
							change: update_grid_data
						}),
						bg_color: new Upfront.Views.Editor.Field.Color({
							model: app.layout,
							label: Upfront.Settings.l10n.global.behaviors.page_bg_color,
							label_style: "inline",
							property: 'background_color',
							spectrum: {
								move: function (color) {
									var rgb = color.toRgb(),
									rgba_string = 'rgba('+rgb.r+','+rgb.g+','+rgb.b+','+color.alpha+')';
									app.layout.set_property('background_color', rgba_string);
								}
							}
						}),
						custom_width: new Upfront.Views.Editor.Field.Number({
							label: Upfront.Settings.l10n.global.behaviors.column_width,
							label_style: "inline",
							min: min_values.column_width,
							max: max_values.column_width,
							default_value: grid.column_width,
							change: update_grid_data
						}),
						custom_padding: new Upfront.Views.Editor.Field.Number({
							label: Upfront.Settings.l10n.global.behaviors.column_padding,
							label_style: "inline",
							min: min_values.column_padding,
							max: max_values.column_padding,
							default_value: grid.column_padding,
							change: update_grid_data
						}),
						custom_baseline: new Upfront.Views.Editor.Field.Number({
							label: Upfront.Settings.l10n.global.behaviors.baseline_grid,
							label_style: "inline",
							min: min_values.baseline,
							max: max_values.baseline,
							default_value: grid.baseline,
							change: update_grid_data
						}),
						custom_type_padding: new Upfront.Views.Editor.Field.Number({
							label: Upfront.Settings.l10n.global.behaviors.additional_type_padding,
							label_style: "inline",
							min: min_values.type_padding,
							max: max_values.type_padding,
							default_value: grid.type_padding,
							change: update_grid_data
						}),
						floated: new Upfront.Views.Editor.Field.Checkboxes({
							multiple: false,
							default_value: true,
							values: [
								{label: Upfront.Settings.l10n.global.behaviors.allow_floats_outside_main_grid, value: true}
							]
						})
					};

				if ( !ed.structure_modal ){
					ed.structure_modal = new Upfront.Views.Editor.Modal({to: $('body'), button: true, top: 120, width: 540});
					ed.structure_modal.render();
					$('body').append(ed.structure_modal.el);
				}
				// Toggle grid on
				if ( !grid_state ) togglegrid.on_click();

				ed.structure_modal.open(function($content, $modal){
					$modal.addClass('upfront-structure-modal');
					_.each(fields, function(field){
						field.render();
						field.delegateEvents();
					});
					$content.html('');
					$content.append(fields.grid.el);
					$recommended.append(fields.recommended_padding.el);
					$recommended.append($grid_width);
					$grid_wrap.append($recommended);
					$custom.append(fields.custom_width.el);
					$custom.append($grid_width2);
					$custom.append(fields.custom_padding.el);
					$custom.append(fields.custom_baseline.el);
					$custom.append(fields.custom_type_padding.el);
					$color_wrap.append(fields.bg_color.el);
					$grid_wrap.append($custom);
					$grid_wrap.append($color_wrap);
					$content.append($grid_wrap);
					$content.append(fields.floated.el);
					fields.grid.trigger('changed');
				}, ed)
				.always(function(){
					if ( !grid_state ) togglegrid.on_click();
				});
			};

			/**
			 * Apply saved grid in layout
			 */
			var apply_grid = function () {
				var ed = Upfront.Behaviors.GridEditor,
					app = Upfront.Application,
					grid = Upfront.Settings.LayoutEditor.Grid,
					options = app.layout.get_property_value_by_name('grid');
				if ( !options || !options.column_widths || !options.column_widths[grid.size_name] )
					return;
				return ed.update_grid({
					column_width: options.column_widths[grid.size_name],
					column_padding: options.column_paddings[grid.size_name],
					baseline: options.baselines[grid.size_name],
					type_padding: options.type_paddings[grid.size_name]
				});
			};
		}
		// END A BIG BLOCK OF STUFF MOVED FROM UPFRONT TO EXPORTER


		((Upfront || {}).Application || {}).ThemeEditor = new (Upfront.Subapplication.extend({
			boot: function () {
				this.listenToOnce(Upfront.Events, 'upfront:layout:loaded', this.set_up_default_styles);
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

				this.listenToOnce(Upfront.Events, 'layout:render', apply_grid);
				this.listenToOnce(Upfront.Events, 'layout:after_render', function(){
					var skip_getting_started = parseInt((window._upfront_theme_exporter_getting_started || '0'), 10);
					if ( skip_getting_started !== 1 ) {
						Dialogs.getting_started_exp();
						// Delay import image dialog until getting started experience done
						Upfront.Events.once('upfront:getting_started:done', Upfront.Behaviors.LayoutEditor.import_image_dialog);
					}
					else {
						// Show import image dialog immediately otherwise
						Upfront.Behaviors.LayoutEditor.import_image_dialog();
					}
					Dialogs.register_quick_tour();
				});
				this.listenTo(Upfront.Events, "command:layout:edit_structure", edit_structure);
				this.listenTo(Upfront.Events, "builder:load_theme", Exporter.load_theme);

				this.listenTo(Upfront.Events, "command:themefontsmanager:open", Dialogs.open_theme_fonts_manager);
				//this.listenToOnce(Upfront.Events, 'command:layout:save_done', Dialogs.first_save_dialog); // Deprecated, we're not doing this anymore
				this.listenTo(Upfront.Events, "command:layout:create", Dialogs.create_layout_dialog); // DEPRECATED
				this.listenTo(Upfront.Events, "command:layout:browse", Dialogs.browse_layout_dialog); // DEPRECATED
			},

			stop: function () {
				var result = this.stopListening(Upfront.Events);
				// Listen this still since this has to happen in responsive too
				this.listenTo(Upfront.Events, "command:layout:export_theme", Dialogs.export_dialog);
				return result;
			},

			set_up_default_styles: function () {
				if ( !this.layout ) return;
				var layout_style = this.layout.get_property_value_by_name('layout_style');
				if ( false !== layout_style ) return;
				// No layout style defined, let's apply default
				//this.layout.set_property('layout_style', DefaultData.default_style, true); // Let's not... :/
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
