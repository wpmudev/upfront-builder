;(function ($) {
	upfrontrjs.define([
		Upfront.themeExporter.root + 'app/exporter.js',
		'text!' + Upfront.themeExporter.root + 'templates/theme/tpl/getting_started.html',
		'text!' + Upfront.themeExporter.root + 'templates/theme/tpl/activate_theme.html'
	], function (Exporter, getting_started_tpl, activate_theme_tpl) {

		return {
			/**
			 * Shows a "well done" type dialog on first save
			 *
			 * Deprecated, we're not doing this anymore
			 *
			 * @param {Boolean} success Save status
			 */
			first_save_dialog: function (success) {
				return false;
				/*
				var app = Upfront.Application,
					ed = Upfront.Behaviors.LayoutEditor,
					current_layout = app.layout.get('current_layout');
				if ( success && (!current_layout || current_layout == 'archive-home') ){
					Exporter.message_dialog(Upfront.Settings.l10n.global.behaviors.excellent_start, Upfront.Settings.l10n.global.behaviors.homepage_created);
				}
				*/
			},
			/**
			 * DEPRECATED
			 */
			create_layout_dialog: function() {
				var app = Upfront.Application,
					ed = Upfront.Behaviors.LayoutEditor,
					fields = {
						layout: new Upfront.Views.Editor.Field.Select({
							name: 'layout',
							values: [{label: Upfront.Settings.l10n.global.behaviors.loading, value: ""}],
							change: function() {
								var value = this.get_value();

								if ( value === 'single-page' )
									fields.$_page_name_wrap.show();
								else
									fields.$_page_name_wrap.hide();
							}
						}),
						page_name: new Upfront.Views.Editor.Field.Text({
							name: 'page_name',
							label: Upfront.Settings.l10n.global.behaviors.page_layout_name
						}),
						inherit: new Upfront.Views.Editor.Field.Radios({
							name: 'inherit',
							layout: "horizontal-inline",
							values: [
								{label: Upfront.Settings.l10n.global.behaviors.start_fresh, value: ''},
								{label: Upfront.Settings.l10n.global.behaviors.start_from_existing, value: 'existing'}
							]
						}),
						existing: new Upfront.Views.Editor.Field.Select({
							name: 'existing',
							values: []
						})
					};
				if ( !ed.available_layouts ) {
					Upfront.Util.post({
						action: 'upfront_list_available_layout'
					}).done(function(data) {
						ed.available_layouts = data.data;
						fields.layout.options.values = _.map(ed.available_layouts, function(layout, layout_id){
							return { label: layout.label, value: layout_id, disabled: layout.saved };
						});
						fields.layout.render();
						fields.layout.delegateEvents();
					});
				} else {
					fields.layout.options.values = _.map(ed.available_layouts, function(layout, layout_id){
						return {label: layout.label, value: layout_id, disabled: layout.saved};
					});
				}
				if (!ed.all_templates) {
					Upfront.Util.post({
						action: "upfront-wp-model",
						model_action: "get_post_extra",
						postId: "fake", // Stupid walkaround for model handler insanity
						allTemplates: true
					}).done(function (response) {
						if (!response.data || !response.data.allTemplates) return false;
						if (0 === response.data.allTemplates.length) {
							fields.inherit.$el.hide();
							fields.existing.$el.hide();
							return false;
						}
						ed.all_templates = response.data.allTemplates;
						fields.existing.options.values = [];
						_.each(response.data.allTemplates, function (tpl, title) {
							fields.existing.options.values.push({label: title, value: tpl});
						});
						fields.existing.render();
					});
				} else {
					fields.existing.options.values = _.map(ed.all_templates, function(tpl, title){
						return {label: title, value: tpl};
					});
				}

				if ( !ed.layout_modal ){
					ed.layout_modal = new Upfront.Views.Editor.Modal({to: $('body'), button: false, top: 120, width: 540});
					ed.layout_modal.render();
					$('body').append(ed.layout_modal.el);
				}

				ed.layout_modal.open(function($content, $modal){
					var $button = $('<div style="clear:both"><span class="uf-button">' + Upfront.Settings.l10n.global.behaviors.create + '</span></div>'),
						$select_wrap = $('<div class="upfront-modal-select-wrap" />');
						$page_name_wrap = $('<div class="upfront-modal-select-wrap" />')
					;
					fields.$_page_name_wrap = $page_name_wrap;
					_.each(fields, function(field) {
						if (!field.render) return true;
						field.render();
						field.delegateEvents();
					});
					$content.html(
						'<h1 class="upfront-modal-title">' + Upfront.Settings.l10n.global.behaviors.create_new_layout + '</h1>'
					);
					$select_wrap.append(fields.layout.el);
					$content.append($select_wrap);

					$page_name_wrap.hide();
					$page_name_wrap.append(fields.page_name.el);
					$page_name_wrap.append(fields.inherit.el);
					$page_name_wrap.append(fields.existing.el);
					$content.append($page_name_wrap);

					$content.append($button);
					$button.on('click', function(){
						ed.layout_modal.close(true);
					});
				}, ed)
				.done(function(){
					var layout = fields.layout.get_value(),
						layout_slug = app.layout.get('layout_slug'),
						data = _.extend({}, ed.available_layouts[layout]),
						specific_layout = fields.page_name.get_value();

					// Check if user is creating single page with specific name
					if (layout === 'single-page' && specific_layout) {
						layout = 'single-page-' + specific_layout.replace(/\s/g, '-').toLowerCase();
						data = {
							layout: {
								'type': 'single',
								'item': 'single-page',
								'specificity': layout
							}
						};
					}

					data.use_existing = layout.match(/^single-page/) && specific_layout && "existing" === fields.inherit.get_value() ?
						/* ? */ fields.existing.get_value() :
						/* : */ false
					;
		/*
		// Why were we using this?
		// It was causing issues when trying to create a pre-existing layout: https://app.asana.com/0/11140166463836/36929734950095
					if ( data.latest_post )
						_upfront_post_data.post_id = data.latest_post;
		*/
					app.create_layout(data.layout, {layout_slug: layout_slug, use_existing: data.use_existing}).done(function() {
						app.layout.set('current_layout', layout);
						// Immediately export layout to write initial state to file.
						Exporter._export_layout();
					});
				});
			},
			/**
			 * DEPRECATED
			 */
			browse_layout_dialog: function () {
				var app = Upfront.Application,
					ed = Upfront.Behaviors.LayoutEditor,
					fields = {
						layout: new Upfront.Views.Editor.Field.Select({
							name: 'layout',
							values: [{label: Upfront.Settings.l10n.global.behaviors.loading, value: ""}],
							default_value: app.layout.get('current_layout')
						})
					};

				if ( !ed.browse_modal ){
					ed.browse_modal = new Upfront.Views.Editor.Modal({to: $('body'), button: false, top: 120, width: 540});
					ed.browse_modal.render();
					$('body').append(ed.browse_modal.el);
				}
				Exporter._get_saved_layout().done(function(data){
					if ( !data || 0 === data.length ){
						fields.layout.options.values = [{label: Upfront.Settings.l10n.global.behaviors.no_saved_layout, value: ""}];
					}
					else {
						fields.layout.options.values = _.map(ed.saved_layouts, function(layout, layout_id){
							return {label: layout.label, value: layout_id};
						});
					}
					fields.layout.render();
					fields.layout.delegateEvents();
				});

				ed.browse_modal.open(function($content, $modal){
					var $button = $('<span class="uf-button">' + Upfront.Settings.l10n.global.behaviors.edit + '</span>'),
						$select_wrap = $('<div class="upfront-modal-select-wrap" />');
					_.each(fields, function(field){
						field.render();
						field.delegateEvents();
					});
					$content.html(
						'<h1 class="upfront-modal-title">' + Upfront.Settings.l10n.global.behaviors.edit_saved_layout + '</h1>'
					);
					$select_wrap.append(fields.layout.el);
					$content.append($select_wrap);
					$content.append($button);
					$button.on('click', function(){
						ed.browse_modal.close(true);
					});
				}, ed)
				.done(function(){
					var layout = fields.layout.get_value(),
						layout_slug = app.layout.get('layout_slug'),
						data = ed.saved_layouts[layout];
					if ( data.latest_post )
						_upfront_post_data.post_id = data.latest_post;
					app.layout.set('current_layout', layout);
					app.load_layout(data.layout, {layout_slug: layout_slug});
				});

			},
			export_dialog: function () {
				var app = Upfront.Application,
					ed = Upfront.Behaviors.LayoutEditor,
					fields,
					loading;

				loading = new Upfront.Views.Editor.Loading({
					loading: Upfront.Settings.l10n.global.behaviors.checking_layouts,
					done: Upfront.Settings.l10n.global.behaviors.layout_exported,
					fixed: true
				});

				if (Exporter.is_exporter_start_page()) {
					// Prepare export dialog
					fields = {
						theme: new Upfront.Views.Editor.Field.Select({
							name: 'theme',
							default_value: Upfront.themeExporter.currentTheme === 'upfront' ?
								'' : Upfront.themeExporter.currentTheme,
							label: Upfront.Settings.l10n.global.behaviors.select_theme,
							values: [{label: Upfront.Settings.l10n.global.behaviors.new_theme, value: ""}],
							change: function(){
								var value = this.get_value(),
									$fields = $([fields.name.el, fields.directory.el, fields.author.el, fields.author_uri.el]);
								if ( value !== '' )
									$fields.hide();
								else
									$fields.show();
							}
						}),
						name: new Upfront.Views.Editor.Field.Text({
							name: 'name',
							label: Upfront.Settings.l10n.global.behaviors.theme_name
						}),
						directory: new Upfront.Views.Editor.Field.Text({
							name: 'directory',
							label: Upfront.Settings.l10n.global.behaviors.directory
						}),
						author: new Upfront.Views.Editor.Field.Text({
							name: 'author',
							label: Upfront.Settings.l10n.global.behaviors.author
						}),
						author_uri: new Upfront.Views.Editor.Field.Text({
							name: 'author_uri',
							label: Upfront.Settings.l10n.global.behaviors.author_uri
						}),
						activate: new Upfront.Views.Editor.Field.Checkboxes({
							name: 'activate',
							default_value: true,
							multiple: false,
							values: [{ label: Upfront.Settings.l10n.global.behaviors.activate_upon_creation, value: 1 }]
						}),
						with_images: new Upfront.Views.Editor.Field.Checkboxes({
							name: 'with_images',
							default_value: true,
							multiple: false,
							values: [{ label: Upfront.Settings.l10n.global.behaviors.export_theme_images, value: 1 }]
						})
					};

					if ( !ed.export_modal ){
						ed.export_modal = new Upfront.Views.Editor.Modal({to: $('body'), button: false, top: 120, width: 540});
						ed.export_modal.render();
						$('body').append(ed.export_modal.el);
					}

					Exporter._get_themes().done(function(data){
						fields.theme.options.values = _.union( [{label: Upfront.Settings.l10n.global.behaviors.new_theme, value: ""}], _.map(data, function(theme, directory){
							return {label: theme.name, value: theme.directory};
						}) );
						fields.theme.render();
						fields.theme.delegateEvents();
						fields.theme.$el.find('input').trigger('change'); // to collapse other fields if theme is set
					});

					ed.export_modal.open(function($content, $modal) {
						var $button = $('<span class="uf-button">' + Upfront.Settings.l10n.global.behaviors.export_button + '</span>');
						_.each(fields, function(field){
							field.render();
							field.delegateEvents();
						});
						$content.html(
							'<h1 class="upfront-modal-title">' + Upfront.Settings.l10n.global.behaviors.export_theme + '</h1>'
						);
						$content.append(fields.theme.el);
						$content.append(fields.name.el);
						$content.append(fields.directory.el);
						$content.append(fields.author.el);
						$content.append(fields.author_uri.el);
						$content.append(fields.activate.el);
						$content.append(fields.with_images.el);
						$content.append($button);
						$button.on('click', function() {
							var theme_name, create_theme, export_layout, export_layouts, do_export;
							theme_name = fields.theme.get_value() ? fields.theme.get_value() : fields.directory.get_value();
							create_theme = function(){
								var data = {
									'thx-theme-name': fields.name.get_value(),
									'thx-theme-slug': fields.directory.get_value(),
									'thx-author': fields.author.get_value(),
									'thx-author-uri': fields.author_uri.get_value(),
									'thx-theme-template': 'upfront',
									'thx-activate_theme': fields.activate.get_value() || '',
									'thx-export_with_images': fields.with_images.get_value() || '',
									add_global_regions: Upfront.Application.current_subapplication.layout.get('layout_slug') !== 'blank'
								};
								loading.update_loading_text(Upfront.Settings.l10n.global.behaviors.creating_theme);
								return Exporter._create_theme(data);
							};
							loading.render();
							$('body').append(loading.el);
							create_theme().done(function() {
								Exporter.export_single_layout(loading, theme_name).done(function() {
									Exporter.load_theme(theme_name);
								});
							});
						});
					}, ed);
				} else {
					// Just export layout
					loading.render();
					$('body').append(loading.el);
					Exporter.export_single_layout(loading, Upfront.themeExporter.currentTheme);
				}
			},
			open_theme_fonts_manager: function() {
				var me = {};
				var textFontsManager = new Upfront.Views.Editor.Fonts.Text_Fonts_Manager({ collection: Upfront.Views.Editor.Fonts.theme_fonts_collection });
				textFontsManager.render();
				// Only enable font icon manager on builder for now
				if (Upfront.Application.mode.current === Upfront.Application.MODE.THEME) {
					var iconFontsManager = new Upfront.Views.Editor.Fonts.Icon_Fonts_Manager({collection: Upfront.Views.Editor.Fonts.icon_fonts_collection});
					iconFontsManager.render();
				}

				var popup = Upfront.Popup.open(
					function (data, $top, $bottom) {
						var $me = $(this);
						$me.empty()
							.append('<p class="upfront-popup-placeholder">' + Upfront.Settings.l10n.global.behaviors.loading_content + '</p>');

						me.$popup = {
							"top": $top,
							"content": $me,
							"bottom": $bottom
						};
					},
					{
						width: 750
					},
					'font-manager-popup'
				);

				me.$popup.top.html(
					'<ul class="upfront-tabs">' +
						'<li id="theme-text-fonts-tab" class="active">' + Upfront.Settings.l10n.global.behaviors.theme_text_fonts + '</li>' +
						(Upfront.Application.mode.current === Upfront.Application.MODE.THEME ? '<li id="theme-icon-fonts-tab">' + Upfront.Settings.l10n.global.behaviors.theme_icon_fonts + '</li>' : '') +
					'</ul>' +
					me.$popup.top.html()
				);

				me.$popup.top.on('click', '#theme-text-fonts-tab', function(event) {
					me.$popup.content.html(textFontsManager.el);
					$('#theme-icon-fonts-tab').removeClass('active');
					$('#theme-text-fonts-tab').addClass('active');
					$('.theme-fonts-ok-button').css('margin-top', '30px');
				});

				me.$popup.top.on('click', '#theme-icon-fonts-tab', function() {
					me.$popup.content.html(iconFontsManager.el);
					$('#theme-text-fonts-tab').removeClass('active');
					$('#theme-icon-fonts-tab').addClass('active');
					$('.theme-fonts-ok-button').css('margin-top', 0);
				});

				me.$popup.bottom.append('<a class="theme-fonts-ok-button">' + Upfront.Settings.l10n.global.behaviors.ok + '</a>');
				me.$popup.content.html(textFontsManager.el);
				textFontsManager.set_ok_button(me.$popup.bottom.find('.theme-fonts-ok-button'));
				me.$popup.bottom.find('.theme-fonts-ok-button').on('click', function() {
					Upfront.Popup.close();
				});
			},
			getting_started_exp: function() {
				// Could be subsequent layout edit after "skip tour" has been clicked
				//if (1 === parseInt((window._upfront_theme_exporter_getting_started || '0'), 10)) return false;

				// No? carry on as usual
				var me = {},
					step_one_tpl = _.template($(getting_started_tpl).find('#upfront-getting-started-step-one-tpl').html()),
					step_two_tpl = _.template($(getting_started_tpl).find('#upfront-getting-started-step-two-tpl').html()),
					step_three_tpl = _.template($(getting_started_tpl).find('#upfront-getting-started-step-three-tpl').html()),
					$sidebar_ui = $('#sidebar-ui')
				;

				if ( $sidebar_ui.length ) {
					// spawning popup
					var popup = Upfront.Popup.open(
						function (data, $top, $bottom) {
							var $me = $(this);
							$me.empty()
								.append(step_one_tpl)
								.addClass('getting_started_content');

							me.$popup = {
								"top": $top,
								"content": $me,
								"bottom": $bottom
							};
							Upfront.Popup.$background.addClass('transparent');
						},
						{
							width: 520,
							disable_esc: true
						},
						'getting-started-popup'
					);

					// toggling step one
					me.toggle_step_one = function() {
						var $upfront_popup = $('#upfront-popup');
						if ( $upfront_popup.length ) {
							$upfront_popup.removeClass('step-two-popup');
							$upfront_popup.removeClass('step-three-popup');
						}
						$sidebar_ui.removeClass('show-sidebar-panel-settings');
						$sidebar_ui.find('.no-click-overlay').remove();
						$sidebar_ui.find('.sidebar-panels li.sidebar-panel-elements').addClass('expanded');
						$sidebar_ui.find('.sidebar-panels li.sidebar-panel-settings').removeClass('expanded');
						$sidebar_ui.addClass('show-primary-sidebar');
						$sidebar_ui.find('ul.sidebar-commands-primary').prepend('<li class="no-click-overlay"></li>');
					};

					// toggling step two
					me.toggle_step_two = function() {
						$sidebar_ui.removeClass('show-primary-sidebar');
						$sidebar_ui.removeClass('show-sidebar-commands-control');
						$sidebar_ui.find('.no-click-overlay').remove();
						$sidebar_ui.addClass('show-sidebar-panel-settings');
						$sidebar_ui.find('.sidebar-panels li.sidebar-panel-elements').removeClass('expanded');
						$sidebar_ui.find('.sidebar-panels li.sidebar-panel-settings').addClass('expanded');
						$sidebar_ui.find('.sidebar-panels li.sidebar-panel-settings').prepend('<div class="no-click-overlay"></div>');
					};

					// toggling step three
					me.toggle_step_three = function() {
						$sidebar_ui.removeClass('show-sidebar-panel-settings');
						$sidebar_ui.find('.sidebar-panels li.sidebar-panel-elements').addClass('expanded');
						$sidebar_ui.find('.sidebar-panels li.sidebar-panel-settings').removeClass('expanded');
						$sidebar_ui.find('.no-click-overlay').remove();
						$sidebar_ui.addClass('show-sidebar-commands-control');
						$sidebar_ui.find('ul.sidebar-commands-control').prepend('<li class="no-click-overlay"></li>');
					};

					me.close_popup = function() {
						Upfront.Popup.close();
						$sidebar_ui.removeClass('show-primary-sidebar');
						$sidebar_ui.removeClass('show-sidebar-panel-settings');
						$sidebar_ui.removeClass('show-sidebar-commands-control');
						$sidebar_ui.find('.no-click-overlay').remove();
						Upfront.Events.trigger('upfront:getting_started:done');
						Upfront.Popup.$background.removeClass('transparent');
					};

					// button events
					me.$popup.content.on('click', 'button.next.step-one', function() {
						$(this).parents('#upfront-popup').addClass('step-two-popup');
						me.$popup.content.html(step_two_tpl);
						me.toggle_step_two();
					});

					me.$popup.content.on('click', 'button.prev.step-two', function() {
						$(this).parents('#upfront-popup').removeClass('step-two-popup');
						me.$popup.content.html(step_one_tpl);
						me.toggle_step_one();
					});

					me.$popup.content.on('click', 'button.next.step-two', function() {
						$(this).parents('#upfront-popup').removeClass('step-two-popup');
						$(this).parents('#upfront-popup').addClass('step-three-popup');
						me.$popup.content.html(step_three_tpl);
						me.toggle_step_three();
					});

					me.$popup.content.on('click', 'button.prev.step-three', function() {
						$(this).parents('#upfront-popup').removeClass('step-three-popup');
						$(this).parents('#upfront-popup').addClass('step-two-popup');
						me.$popup.content.html(step_two_tpl);
						me.toggle_step_two();
					});

					me.$popup.content.on('click', 'button.finish.step-three', function() {
						$(this).parents('#upfront-popup').removeClass('step-three-popup');
						if (Upfront.Application.is_builder() && 0 === parseInt((window._upfront_theme_exporter_getting_started || '0'), 10)) {
							Upfront.Util.post({
								action: 'upfront_thx-skip-getting-started',
								data: {
									key: 'upfront_show_builder_exp'
								}
							}).done(function () {
								// Record the local global state change as well
								window._upfront_theme_exporter_getting_started = 1;
							});
						}
						me.close_popup();
					});

					// do not allow clicking from outside
					Upfront.Popup.$background.off("click");

					// default view
					me.toggle_step_one();
				}
			},
			register_quick_tour: function() {
				var me = this,
					$replay = $('#page .upfront-replay-quick-tour a.upfront_cta')
				;
				if ( $replay.length ) {
					$replay.on('click', function() {
						me.getting_started_exp();
					});
				}
			},
			activate_edited_theme: function(l10n) {
				// skip this whole process if was already done for this session
				if (1 === parseInt((window._upfront_builder_theme_activated || '0'), 10)) {
					Upfront.Events.trigger("command:layout:export_theme");
					return false;
				}

				var me = {},
					activate_tpl = _.template($(activate_theme_tpl).find('#upfront-builder-activate-theme-tpl').html(),{
						current_theme: Upfront.themeExporter.currentTheme,
						l10n: l10n,
					})
				;
				// spawning popup
				var popup = Upfront.Popup.open(
					function (data, $top, $bottom) {
						var $me = $(this);
						$me.empty()
							.append(activate_tpl)
							.addClass('getting_started_content builder-activate-theme');

						me.$popup = {
							"top": $top,
							"content": $me,
							"bottom": $bottom
						};
					},
					{
						width: 520
					},
					'getting-started-popup builder-activate-theme-popup'
				);

				me.$popup.content.on('click', 'button.yes', function() {
					Upfront.Util.post({
						action: 'upfront_thx-activate-selected-theme'
					}).done(function () {
						// Record the local global state change as well
						window._upfront_builder_theme_activated = 1;
					});
					Upfront.Popup.close();
					// proceed to exporting
					Upfront.Events.trigger("command:layout:export_theme");
				});
				me.$popup.content.on('click', 'button.no', function() {
					// Record the local global state change as well
					window._upfront_builder_theme_activated = 1;
					Upfront.Popup.close();
					Upfront.Events.trigger("command:layout:export_theme");
				});

				// do not allow clicking from outside
				Upfront.Popup.$background.off("click");
			}
		};
	});
})(jQuery);
