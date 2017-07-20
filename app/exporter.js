;(function ($) {
upfrontrjs.define(function () {

	var Exporter = {
		is_exporter_start_page: function() {
			return Upfront.themeExporter.currentTheme === 'upfront';
		},
		export_single_layout: function(loading, theme_name) {
			var self = this,
	            app = Upfront.Application,
				ed = Upfront.Behaviors.LayoutEditor;

			var layout_id = _upfront_post_data.layout.specificity || _upfront_post_data.layout.item || _upfront_post_data.layout.type; // Also make sure to include specificity first
			loading.update_loading_text(Upfront.Settings.l10n.global.behaviors.exporting_layout + layout_id);

			return Exporter._export_layout({ theme: theme_name }).done(function() {
				loading.done(function() {
					if (ed.export_modal) ed.export_modal.close(true);
					ed.clean_region_css();
				});
			});

		},
		message_dialog: function (title, msg) {
			var app = Upfront.Application,
				ed = Upfront.Behaviors.LayoutEditor;
			if ( !ed.message_modal ){
				ed.message_modal = new Upfront.Views.Editor.Modal({to: $('body'), button: true, top: 120, width: 540});
				ed.message_modal.render();
				$('body').append(ed.message_modal.el);
			}
			ed.message_modal.open(function($content, $modal){
				$modal.addClass('upfront-message-modal');
				$content.html(
					'<h1 class="upfront-modal-title">' + title + '</h1>'
				);
				$content.append(msg);
			}, ed);
		},
		/**
		 * DEPRECATED
		 */
		_get_saved_layout: function (){
			var me = this,
				deferred = new $.Deferred()
			;

			// The request should only ever be sent in builder mode
			if (Upfront.Application.is_builder()) {
				Upfront.Util.post({
					action: 'upfront_list_theme_layouts'
				}).success(function(response){
					me.saved_layouts = response.data;
					deferred.resolve(response.data);
				}).error(function(){
					deferred.reject();
				});
			} else setTimeout(deferred.reject);

			return deferred.promise();
		},
		_get_themes: function () {
			var me = this,
				deferred = new $.Deferred()
			;
			// The request should only ever be sent in builder mode
			if (Upfront.Application.is_builder()) {
				Upfront.Util.post({
					action: 'upfront_thx-get-themes'
				}).success(function(response){
					me.themes = response;
					deferred.resolve(response);
				}).error(function(){
					deferred.reject();
				});
			} else setTimeout(deferred.reject);
			return deferred.promise();
		},
		_create_theme: function (data) {
			var deferred = new $.Deferred();

			// The request should only ever be sent in builder mode
			if (Upfront.Application.is_builder()) {
				Upfront.Util.post({
					action: 'upfront_thx-create-theme',
					form: this._build_query(data)
				}).success(function(response){
					if ( response && response.error )
						deferred.reject(response.error);
					else
						deferred.resolve();
				}).error(function(){
					deferred.reject();
				});
			} else setTimeout(deferred.reject);
			return deferred.promise();
		},
		export_element_styles: function(data) {
			// The request should only ever be sent in builder mode
			if (!Upfront.Application.is_builder()) return false;

			Upfront.Util.post({
				action: 'upfront_thx-export-element-styles',
				data: data
			}).success(function(response){
				if ( response && response.error ) {
					Upfront.Views.Editor.notify(response.error);
					return;
				}
				if(!Upfront.data.styles[data.elementType])
					Upfront.data.styles[data.elementType] = [];
				if(Upfront.data.styles[data.elementType].indexOf(data.stylename) === -1)
					Upfront.data.styles[data.elementType].push(data.stylename);

				Upfront.Views.Editor.notify(Upfront.Settings.l10n.global.behaviors.style_exported);
			}).error(function(){
				Upfront.Views.Editor.notify(Upfront.Settings.l10n.global.behaviors.style_export_fail);
			});
		},
		_save_presets: function(deferred) {
			var presetSave = Upfront.Application.presetSaver.save();

			presetSave.done( function() {
				deferred.resolve();
			}).fail( function() {
				deferred.reject();
			});
		},
		_export_layout: function (custom_data) {
			var typography,
				properties,
				layout_style,
				deferred = new $.Deferred(),
				data = {},
				data_regions, data_regions_compressed
			;

			// The request should only ever be sent in builder mode
			if (!Upfront.Application.is_builder()) {
				setTimeout(deferred.reject);
				return deferred.promise();
			}

			typography = _.findWhere(
				Upfront.Application.current_subapplication.get_layout_data().properties,
				{ 'name': 'typography' }
			);

			layout_style = _.findWhere(
				Upfront.Application.current_subapplication.get_layout_data().properties,
				{ 'name': 'layout_style' }
			);


			properties = _.extend({}, Upfront.Util.model_to_json(Upfront.Application.current_subapplication.get_layout_data().properties));
			properties = _.reject(properties, function(property) {
				return _.contains(['typography', 'layout_style', 'global_regions'], property.name);
			});

			data_regions = Upfront.Application.current_subapplication.get_layout_data().regions;
			if ( Upfront.mainData.save_compression ) {
				data_regions_compressed = Upfront.Util.compress(data_regions);
				data_regions = data_regions_compressed.result;
			}
			else {
				data_regions = JSON.stringify(data_regions);
			}


			data = {
				typography: (typography ? JSON.stringify(typography.value) : ''),

				compression: Upfront.mainData.save_compression ? 1 : 0,
				regions: data_regions,
				regions_original_length: data_regions_compressed ? data_regions_compressed.original_length : 0,
				regions_compressed_length: data_regions_compressed ? data_regions_compressed.compressed_length : 0,

				template: _upfront_post_data.layout.specificity || _upfront_post_data.layout.item || _upfront_post_data.layout.type, // Respect proper cascade ordering
				layout_properties: JSON.stringify(properties),
				theme: Upfront.themeExporter.currentTheme,
				layout_style: layout_style ? layout_style.value : '',
				theme_colors: {
					colors: Upfront.Views.Theme_Colors.colors.toJSON(),
					range: Upfront.Views.Theme_Colors.range
				},
				/*
				 * Commented, because presets are updated in settings.php on create/edit
				 * button_presets: Upfront.Views.Editor.Button.Presets.toJSON(),
				 */
				post_image_variants: Upfront.Content.ImageVariants.toJSON()
			};

			if (Upfront.themeExporter.layoutStyleDirty) {
				data.layout_style = $('#layout-style').html();
				Upfront.themeExporter.layoutStyleDirty = false;
			}

			if (custom_data) data = _.extend(data, custom_data);

			Upfront.Util.post({
				action: 'upfront_thx-export-layout',
				data: data
			}).success(function(response){
				if ( response && response.error )
					deferred.reject(response.error);
				else
					Exporter._save_presets(deferred);
			}).error(function(){
				deferred.reject();
			});
			return deferred.promise();
		},
		/**
		 * We are loading theme by reloading page since lots of stuff needs
		 * to be setup like stylesheet etc. Only way to get this right is to
		 * load page from scratch.
		 */
		load_theme: function (theme_slug) {
			var url = location.origin;
			// Add anything before create_new
			url += location.pathname.split('create_new')[0];
			// Add create_new and theme slug
			url += 'create_new/' + theme_slug;
			// Check for dev=true
			if (location.toString().indexOf('dev=true') > -1) url += '?dev=true';

			window.location = url;
		}
	};

	return Exporter;
});
})(jQuery);
