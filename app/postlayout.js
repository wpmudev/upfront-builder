upfrontrjs.define([
	'underscore',
	'jquery',
	Upfront.themeExporter.root + 'app/post_image.js',
	'text!' + Upfront.themeExporter.root + 'templates/theme/tpl/post_design.html'
	//'underscore', 'jquery', 'text!templates/image_variants.html'
], function(_, $, post_image, post_design_tpl) {

	var l10n = Upfront.Settings && Upfront.Settings.l10n ?
			/* ? */ Upfront.Settings.l10n.exporter :
			/* : */ Upfront.mainData.l10n.exporter
		;

	var PostDesignButton = Backbone.View.extend({
		tpl: _.template($(post_design_tpl).find('#save-post-design').html()),
		className: "uf-exporter-save-post-design",
		initialize: function( options ) {

		},
		events: {
			"click" : "on_click"
		},
		render: function(){
			this.$el.html( this.tpl() );
			return this;
		},
		on_click: function( e ){
			e.preventDefault();
			postLayoutManager.exportPostLayout();
			Upfront.Events.trigger('post:layout:post:style:cancel');
		}
	});

	var PostDataDesignButton = PostDesignButton.extend({
		on_click: function( e ){
			e.preventDefault();
			Upfront.Events.trigger('post:layout:post:style:cancel');
		}
	});

	var PostLayoutManager = function(){
		this.init();
	};

	PostLayoutManager.prototype = {
		init: function(){
			var me = this;
			if(!Upfront.Events.on){
				return setTimeout(function(){
					me.init();
				},50);
			}

			// For old this_post
			Upfront.Events.on('post:layout:partrendered',_.bind(this.setTestContent, this) );
			Upfront.Events.on('post:layout:post:style:cancel', _.bind(this.cancelPostContentStyle, this));
			Upfront.Events.on('post:parttemplates:edit', _.bind(this.addTemplateExportButton, this));

			// For new post data
			Upfront.Events.on('post-data:part:rendered', _.bind(this.setDataTestContent, this));
		},

/*
		addExportCommand: function(){
			if( Upfront.Application.PostLayoutEditor.postView.editor.post.get("post_type") === "post" ) return;
			var commands = Upfront.Application.sidebar.sidebar_commands.control.commands,
				wrapped = commands._wrapped,
				Command = Upfront.Views.Editor.Command.extend({
					className: "command-export",
					render: function (){
						this.$el.text('Export');
					},
					on_click: this.exportPostLayout
				})
				;

			if(wrapped[wrapped.length - 2].className != 'command-export')
				wrapped.splice(wrapped.length - 1, 0, new Command());

		},
*/
		addTemplateExportButton: function(){
			var me = this,
				exportButton = $('<button class="upfront-export-postpart">Export</button>'),
				editorBody = $('#upfront_code-editor').find('.upfront-css-body')
			;

			editorBody
				.find('button')
				.remove()
			;

			if(!editorBody.find('.upfront-export-postpart').length) {
				editorBody.append(exportButton);
			}

			exportButton.off('click').on('click', function(e){
				e.preventDefault();
				me.exportPartTemplate();
			});
		},
		exportPostLayout: function(){
			Upfront.Events.trigger("command:layout:export_postlayout");
			var self = this,
				editor = Upfront.Application.PostLayoutEditor,
				elementType = editor.postView.property('type'),
				specificity = editor.postView.property('post_type') ? editor.postView.property('post_type') : "post" ,
				post_type = editor.postView.editor.post.get('post_type'),
				elementSlug = elementType == 'ThisPostModel' ? 'single' : 'archive',
				loading = new Upfront.Views.Editor.Loading({
					loading: "Saving post layout...",
					done: "Thank you for waiting",
					fixed: false
				})
			;

			// Check if we need to overwrite the post_type, which is possible
			// e.g. on pre-existing single page default layouts
			post_type = this.fetch_type(post_type);

			var layoutData = {
				postLayout: editor.exportPostLayout(),
				partOptions: editor.postView.partOptions || {}
			};

			loading.render();
			$('body').append(loading.$el);
			Upfront.Util.post({
				action: 'upfront_thx-export-post-layout',
				layoutData: layoutData,
				params: {
					specificity : post_type,
					type        : elementSlug
				}
			}).done(function (response) {
				loading.done();
				var message = "<h4>Layout successfully exported in the following file:</h4>";
				message += response.file;
				Upfront.Views.Editor.notify( message );
				editor.postView.postLayout = layoutData.postLayout;
				editor.postView.editor.render();
				Upfront.Application.start(Upfront.Application.mode.last);
			});
		},
		exportPartTemplate: function(){
			var self = this,
				editor = Upfront.Application.PostLayoutEditor.templateEditor,
				tpl = editor.ace.getValue(),
				postView = Upfront.Application.PostLayoutEditor.postView,
				postPart = editor.postPart,
				element = postView.property('type'),
				id = this.fetch_type(postView.editor.post.get('post_type'))
			;

			Upfront.Util.post({
				action: 'upfront_thx-export-part-template',
				part: postPart,
				tpl: tpl,
				type: element,
				id: id
			}).done(function(response){
				postView.partTemplates[editor.postPart] = tpl;
				postView.model.trigger('template:' + editor.postPart);
				editor.close();
				Upfront.Views.Editor.notify("Part template exported in file " + response.filename);
			});
		},

		fetch_type: function (fallback) {
			// So this bit here is for the post type heuristics
			var layout_info = Upfront.Application.current_subapplication.get_layout_data().layout,
				overall_layout_type = (layout_info || {type: false}).type,
				overall_layout_item = (layout_info || {item: false}).item,
				post_type = fallback
			;

			// Check if we need to overwrite the post_type, which is possible
			// e.g. on pre-existing single page default layouts
			if (overall_layout_type && overall_layout_item && overall_layout_type + "-" + post_type !== overall_layout_item) {
				post_type = overall_layout_item.replace(new RegExp(overall_layout_type + "-"), '');
			}
			// Okay, so we're now set

			return post_type;
		},

		setTestContent: function(view){
			var self = this;
			if(view.postPart != 'contents')
				return;

			if( Upfront.Application.PostLayoutEditor.postView.editor.post.get("post_type") === "post" ){
				this._setPostTestContent(view);
			}else{
				this._setTestContent(view);
			}

		},
		_setTestContent: function( view ){
			view.$('.upfront-object-content .post_content').html(Upfront.data.exporter.testContent);
		},
		_setPostTestContent: function( view ){
			var self = this;
			if( view ) this.view = view;
			this.view.$('.upfront-object-content .post_content')
                .html(Upfront.data.exporter.postTestContent)
				.append('<div class="upfront_edit_content_style">' + l10n.edit_content_style + '</div>')
			;
			this.savePostDesingButton = new PostDesignButton();
			this.savePostDesingButton.render();
		   setTimeout(function(){
				$(".upfront-region-container-postlayouteditor").append( self.savePostDesingButton.$el );
			}, 50);

			/**
			 * Start content styler on click
			 */
			this.view.$(".upfront_edit_content_style").on("click", function(e){
				self.view.$('.upfront-object-content .post_content').html(Upfront.data.exporter.styledTestContent);
				self.view.$('.upfront-object').addClass("upfront-editing-content-style");
				self.view.$('.upfront-object-content').closest(".upfront-object-view").addClass("upfront-disable-surroundings");
				new post_image.PostImageVariants({
					contentView : self.view
				});
				Upfront.Application.set_post_content_style();
			});
		},
		cancelPostContentStyle: function(){
			var $main = $(Upfront.Settings.LayoutEditor.Selectors.main);
			$main.removeClass('upfront-editing-content-style');
			$main.find('.upfront-region-edit-trigger').show();
			this.savePostDesingButton.remove();
			$('.upfront-output-PostPart_contents, .upostdata-part.content').closest(".upfront-object-view").removeClass("upfront-disable-surroundings");
			//$('.upfront-output-PostPart_contents .post_content').html(Upfront.data.exporter.postTestContent);
			this._setPostTestContent(); // To be deprecated
			this._setPostDataTestContent();
			Upfront.Application.set_post_content_style();

			Upfront.Events.trigger('upfront:edit_content_style:stop');
		},

		setDataTestContent: function (view) {
			var type = view.model.get_property_value_by_name('part_type');
			if ( type != 'content' || !view.object_group_view ) return;
			//TODO abstract this to plugins
			var layout = Upfront.Application.current_subapplication.get_layout_data().layout;
			if (layout && layout.specificity && (layout.specificity === 'single-page-mpproducts' || layout.specificity === 'single-page-mpcart')) return;
			var editor = view.object_group_view.editor;
			if( editor.post.get("post_type") === "post" ){
				this._setPostDataTestContent(view);
			}else{
				this._setDataTestContent(view);
			}
		},
		_setDataTestContent: function( view ){
			var $content = view.$('.upostdata-part.content'),
				$indented_content = $content.find('.upfront-indented_content')
			;
			if ( $indented_content.length > 0 ) {
				$indented_content.html(Upfront.data.exporter.testContent);
			}
			else {
				$content.html(Upfront.data.exporter.testContent);
			}
		},
		_setPostDataTestContent: function( view ){
			if( view ) this.view = view;
			var $main = $(Upfront.Settings.LayoutEditor.Selectors.main),
				$content = this.view.$('.upostdata-part.content'),
				$indented_content = $content.find('.upfront-indented_content'),
				self = this
			;
			$content.addClass('edit-content');
			if ( $indented_content.length > 0 ) {
				$indented_content
                    .html(Upfront.data.exporter.postTestContent)
                    .append('<div class="upfront_edit_content_style">' + l10n.edit_content_style + '</div>')
                ;
			}
			else {
				$content
                    .html(Upfront.data.exporter.postTestContent)
                    .append('<div class="upfront_edit_content_style">' + l10n.edit_content_style + '</div>')
                ;
			}
			this.savePostDesingButton = new PostDataDesignButton();

			/**
			 * Start content styler on click
			 */
			this.view.$(".upfront_edit_content_style").on("click", function(e){
				$main.addClass('upfront-editing-content-style');
				Upfront.Events.trigger('upfront:edit_content_style:start');
				if ( $indented_content.length > 0 ) {
					$indented_content.html(Upfront.data.exporter.styledTestContent);
				}
				else {
					$content.html(Upfront.data.exporter.styledTestContent);
				}
				//self.view.$('.upfront-object').addClass("upfront-editing-content-style");
				self.view.$('.upfront-object-content').closest(".upfront-object-view").addClass("upfront-disable-surroundings");
				$main.find('.upfront-region-edit-trigger').hide();
				new post_image.PostImageVariants({
					contentView : self.view
				});
				Upfront.Application.set_post_content_style();
				self.savePostDesingButton.render();
				self.view.$el.append( self.savePostDesingButton.$el );
			});
		},


	};

	var postLayoutManager = new PostLayoutManager();
	return postLayoutManager;

});
