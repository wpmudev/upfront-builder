define([
  'underscore', 'jquery', 'text!upfront/templates/image_variants.html'
], function(_, $, variant_tpl) {

var PostLayoutManager = function(){
	this.init();
};

var PostImageVariants =  Backbone.View.extend({
    initialize: function( options ) {
        this.contentView = options.contentView;
        this.populate_style_content();
    },
    add_new_variant : function(e){
        e.preventDefault();
        e.stopPropagation();
        var model = new Upfront.Models.ImageVariant();
        model.set("vid", Upfront.Util.get_unique_id("variant"));
        var variant = new PostImageVariant({ model : model});
        variant.render();
        variant.$el.hide();
        /**
         * Add it after the last variant
         */
        $(".ueditor-insert-variant").last().after( variant.el );
        variant.$el.fadeIn();
        Upfront.Content.ImageVariants.add(model);
    },
    populate_style_content : function() {
        var self = this;
        var $page = $('#page');

        $page.find('.upfront-module').draggable('disable').resizable('disable');
        $page.find('.upfront-region-edit-trigger').hide();

        Upfront.Content.ImageVariants.each(function (model) {
            var variant = new PostImageVariant({model: model});
            variant.render();
            self.contentView.$el.find("#upfront-image-variants").append(variant.el);
        });

        if (Upfront.Content.ImageVariants.length === 0) {
            var model = new Upfront.Models.ImageVariant();
            model.set("vid", Upfront.Util.get_unique_id("variant"));
            var variant = new PostImageVariant({model: model});
            Upfront.Content.ImageVariants.add(model);
            variant.render();
            this.contentView.$el.find("#upfront-image-variants").append(variant.el);
        }

        /**
         * Add new button
         */
        var $add_new_button = $("<div class='upfront-add-image-insert-variant'>Add Image Insert Variant</div>").on("click", this.add_new_variant);
        $("#upfront-image-variants").append($add_new_button);
    }
});
var PostImageVariant = Backbone.View.extend({
    tpl : _.template($(variant_tpl).find('#upfront-post-image-variant-tpl').html()),
    se_handle : '<span class="upfront-icon-control upfront-icon-control-resize-se upfront-resize-handle-se ui-resizable-handle ui-resizable-se nosortable"></span>',
    nw_handle : '<span class="upfront-icon-control upfront-icon-control-resize-nw upfront-resize-handle-nw ui-resizable-handle ui-resizable-nw nosortable"></span>',
    initialize: function( options ){
        this.opts = options;
        Upfront.Events.on("post:layout:style:stop", function(){

        });
    },
    events : {
        "click .upfront_edit_image_insert" : "start_editing",
        "click .finish_editing_image_insert" : "finish_editing",
        "click .upfront-image-variant-delete_trigger" : "remove_variant"
    },
    render : function() {
        this.$el.html( this.tpl( this.model.toJSON() ) );
        this.$self = this.$(".ueditor-insert-variant");
        this.$self.prepend('<a href="#" class="upfront-icon-button upfront-icon-button-delete upfront-image-variant-delete_trigger"></a>');
        this.$image =  this.$(".ueditor-insert-variant-image");
        this.$caption = this.$(".ueditor-insert-variant-caption");
        this.make_resizable();
        this.$label = this.$(".image-variant-label");
        return this;
    },
    remove_variant : function(e){
        e.preventDefault();
        e.stopPropagation();
        ImageVariants.remove(this.model);
        this.remove();
    },
    start_editing : function(e){
        e.preventDefault();
        e.stopPropagation();

        // Show title input
        this.$label.show();

        // Hide edit button
        this.$(".upfront_edit_image_insert").css({
            visibility : "hidden"
        });
        //disable group's resizability
        this.$self.resizable("option", "disabled", true);

        this.$self.addClass("editing");

        // hide group's resize handles
        this.$self.find(".upfront-icon-control").hide();

        // explicitly set group's height
        this.$self.css("height", this.$(".ueditor-insert-variant").height());

        this.make_items_draggable();
        this.make_items_resizable();
        this.$self.append("<div class='finish_editing_image_insert'>Finish editing image insert</div>")
    },
    finish_editing : function( e ){
        e.preventDefault();
        e.stopPropagation();

        //Hide title
        this.$label.hide();

        this.model.set( "label", this.$label.val() );

        // Show edit button
        this.$(".upfront_edit_image_insert").css({
            visibility : "visible"
        });
        //enable group's resizability
        this.$self.resizable("option", "disabled", false);

        this.$self.removeClass("editing");

        // Show group's resize handles
        this.$self.find(".upfront-icon-control").show();

        this.$image.draggable("option", "disabled", true);
        this.$image.resizable("option", "disabled", true);
        this.$image.find(".upfront-icon-control").hide();

        this.$caption.draggable("option", "disabled", true);
        this.$caption.resizable("option", "disabled", true);
        this.$caption.find(".upfront-icon-control").hide();

        $(e.target).remove();

    },
    make_items_draggable : function(){
        var self = this,
            ge = Upfront.Behaviors.GridEditor,
            options = {
                zIndex: 100,
                containment : 'parent',
                delay: 50,
                helper: 'clone',
                start : function( event, ui ){
                    event.stopPropagation();
                    $(this).resizable("option", "disabled", true);
                },
                drag : function( event, ui ){
                    event.stopPropagation();
                },
                stop : function(event, ui){
                    event.stopPropagation();
                    var $this = $(this),
                        top = Upfront.Util.height_to_row( ui.position.top > 0 ? ui.position.top : 0 ) * ge.baseline ,
                        left  =  Upfront.Util.width_to_col( ui.position.left ) * ge.col_size,
                        model = $this.is( self.$image ) ? self.model.get("image") : self.model.get("caption");

                    model.left = left;
                    model.top = top;

                    //self.update_class( $this, "ml", left  );
                    //self.update_class( $this, "mt", top  );

                    $(this).css({
                        top : top,
                        left : left,
                        marginLeft : 0,
                        marginTop: 0
                    });

                    $this.resizable("option", "disabled", false);
                }
            };

        /**
         * Make image draggable
         */
        if( _.isEmpty( this.$image.data("ui-draggable") ) ){
            this.$image.draggable( options );
        }else{
            this.$image.draggable( "option", "disabled", false );
        }

        /**
         * Make caption draggable
         */
        if( _.isEmpty( this.$caption.data("ui-draggable") ) ){
            this.$caption.draggable( options );
        }else{
            this.$caption.draggable( "option", "disabled", false );
        }
    },
    make_items_resizable : function(){
        var self = this,
            ge = Upfront.Behaviors.GridEditor,
            options = {
                handles: {
                    nw: '.upfront-resize-handle-nw',
                    se: '.upfront-resize-handle-se'
                },
                //autoHide: true,
                delay: 50,
                minHeight: 50,
                minWidth: 45,
                containment: "parent",
                start : function( event, ui ){
                    event.stopPropagation();
                    $(this).draggable("option", "disabled", true);
                },
                resize: function( event, ui ){
                    event.stopPropagation();
                    //var $this = $(this),
                    //    model = $this.is( self.$image ) ? self.model.get("image") : self.model.get("caption"),
                    //    left = Upfront.Util.width_to_col( ui.position.left ) * ge.col_size,
                    //    top = Upfront.Util.height_to_row( ui.position.top > 0 ? ui.position.top : 0 ) * ge.baseline,
                    //    height = Upfront.Util.grid.normalize_height( ui.size.height > 0 ? ui.size.height : 0 ),
                    //    width = Upfront.Util.grid.normalize_width( ui.size.width > 0 ? ui.size.width : 0 ),
                    //    col_class_size = Upfront.Util.width_to_col( ui.size.width );
                    //
                    //model.left = left;
                    //model.top = top;
                    //model.height = height;
                    //model.width_cls = ge.grid.class + col_class_size;
                    //
                    //Upfront.Util.grid.update_class($this, ge.grid.class, col_class_size);
                    //
                    //$(this).css({
                    //    left : left,
                    //    top: top,
                    //    height: height
                    //});
                },
                stop : function(event, ui){
                    $(this).draggable("option", "disabled", false);
                    event.stopPropagation();
                    var $this = $(this),
                        model = $this.is( self.$image ) ? self.model.get("image") : self.model.get("caption"),
                        left = Upfront.Util.width_to_col( ui.position.left ) * ge.col_size,
                        top = Upfront.Util.height_to_row( ui.position.top > 0 ? ui.position.top : 0 ) * ge.baseline,
                        height = Upfront.Util.grid.normalize_height( ui.size.height ),
                        width  = Upfront.Util.grid.normalize_width(  ui.size.width),
                        col_class_size = Upfront.Util.width_to_col( ui.size.width );

                    model.left = left;
                    model.top = top;
                    model.height = height;
                    model.width_cls = ge.grid.class + col_class_size;

                    Upfront.Util.grid.update_class($this, ge.grid.class, col_class_size);

                    $(this).css({
                        left : left,
                        top: top,
                        height: height,
                        width : ""
                    });
                }
            };
        /**
         * Make image resizable
         */


        if(_.isEmpty(  this.$image.data("ui-resizable") ) ){
            this.$image.append(this.nw_handle);
            this.$image.append(this.se_handle);
            this.$image.resizable(options);
        }else{
            this.$image.find(".upfront-icon-control").show();
            this.$image.resizable("option", "disabled", false);
        }


        /**
         * Make caption resizable
         */

        if(_.isEmpty(  this.$caption.data("ui-resizable") ) ){
            this.$caption.append(this.nw_handle);
            this.$caption.append(this.se_handle);
            this.$caption.resizable(options);
        }else{
            this.$caption.find(".upfront-icon-control").show();
            this.$caption.resizable("option", "disabled", false);
        }

    },
    ui_right : function( ui, el ){
        var $el = $(el),
            $content = $el.closest(".upfront-object-view"),
            content_width = $content.width(),
            left = ui.position.left;
        return content_width - left - $el.width();
    },
    make_resizable : function(){
        var self = this,
            ge = Upfront.Behaviors.GridEditor;
        this.$self.append(this.nw_handle);
        this.$self.append(this.se_handle);
        this.$self.resizable({
            //autoHide: true,
            delay: 50,
            handles: {
                nw: '.upfront-resize-handle-nw',
                se: '.upfront-resize-handle-se'
            },
            minHeight: 50,
            minWidth: 45,
            containment: "parent",
            //alsoResize: '.ueditor-insert-variant-image',
            start : function(){

                /**
                 * Reset caption and image styles
                 */
                self.$image.css({
                    left : 0,
                    top : 0
                });

                self.$caption.css({
                    left : 0,
                    top : 0
                });

                Upfront.Util.grid.update_class( self.$image, "c24" );
                Upfront.Util.grid.update_class( self.$caption, "c24" );

                self.model.get("image").width_cls = "c24";
                self.model.get("caption").width_cls = "c24";

            },
            resize: function (event, ui) {
                if (ui.position.left ===  0 && self.ui_right( ui, this) !== 0) { //float left
                    $(this).css({
                        float : "left",
                        left  : 0,
                        right : 0
                    });
                    self.model.get("group").float = "left"
                } else if( ui.position.left > 0 && self.ui_right( ui, this) === 0 ) { // float right
                    $(this).css({
                        float : "right",
                        left  : 0,
                        right : 0
                    });
                    self.model.get("group").float = "right";

                }

                //Float none
                if(  (  ui.position.left ===  0 && self.ui_right( ui, this) === 0 ) ||  ( ui.position.left !==  0 && self.ui_right( ui, this) !== 0 )){
                    $(this).css({
                        float : "none"
                    });
                    self.model.get("group").float = "none";
                }


            },
            stop: function (event, ui) {
                var $this = $(this),
                    col_class_size = Upfront.Util.grid.width_to_col( ui.size.width),
                    margin_left = ui.position.left,
                    left_class_size = Math.round(margin_left / ge.col_size),
                    height =  Upfront.Util.height_to_row(ui.size.height) * ge.baseline;

                Upfront.Util.grid.update_class($this, ge.grid.class, col_class_size);
                self.model.get("group").height = height;
                self.model.get("group").width_cls = ge.grid.class + col_class_size;

                $this.css({
                    height: height,
                    width: "",
                    "margin-left": ""
                });

                var image_height = height - $this.find(".ueditor-insert-variant-caption").height() - 60;
                $this.find(".ueditor-insert-variant-image").css("height", image_height);

                //self.update_class($this, ge.grid.left_margin_class, left_class_size);
            }
        });
    },
    update_class :  function ($el, class_name, class_size) {
        var rx = new RegExp('\\b' + class_name + '\\d+');
        if ( ! $el.hasClass( class_name + class_size) ){
            if ( $el.attr('class').match(rx) )
                $el.attr('class', $el.attr('class').replace(rx, class_name + class_size));
            else
                $el.addClass( class_name + class_size );
        }
    }
});


PostLayoutManager.prototype = {
	init: function(){
		var me = this;
		if(!Upfront.Events.on){
            return setTimeout(function(){
                me.init();
                },50);
        }

		Upfront.Events.on('post:layout:sidebarcommands', _.bind(this.addExportCommand, this));
		Upfront.Events.on('command:layout:export_postlayout', _.bind(this.exportPostLayout, this));
		Upfront.Events.on('post:layout:partrendered', this.setTestContent);
        Upfront.Events.on('post:parttemplates:edit', _.bind(this.addTemplateExportButton, this));
	},

	addExportCommand: function(){
		var commands = Upfront.Application.sidebar.sidebar_commands.control.commands,
			wrapped = commands._wrapped,
			Command = Upfront.Views.Editor.Command.extend({
				className: "command-export",
				render: function (){
					this.$el.text('Export');
				},
				on_click: function () {
					Upfront.Events.trigger("command:layout:export_postlayout");
				}
			})
		;

		if(wrapped[wrapped.length - 2].className != 'command-export')
			wrapped.splice(wrapped.length - 1, 0, new Command());

	},
    addTemplateExportButton: function(){
        var me = this,
            exportButton = $('<a href="#" class="upfront-export-postpart">Export</a>'),
            editorBody = $('#upfront_code-editor').find('.upfront-css-body')
        ;

        if(!editorBody.find('.upfront-export-postpart').length)
            editorBody.append(exportButton);
            exportButton.on('click', function(e){
                e.preventDefault();
                me.exportPartTemplate();
            });

        console.log('Export button');

    },
	exportPostLayout: function(){
        var self = this,
            editor = Upfront.Application.PostLayoutEditor,
            saveDialog = new Upfront.Views.Editor.SaveDialog({
                question: 'Do you wish to export the post layout just for this post or apply it to all posts?',
                thisPostButton: 'This post only',
                allPostsButton: 'All posts of this type'
            });
        saveDialog.render();
        saveDialog.on('closed', function(){
                saveDialog.remove();
                saveDialog = false;
        });
        saveDialog.on('save', function(type) {
            var elementType = editor.postView.property('type');
            var specificity,
                post_type = editor.postView.editor.post.get('post_type'),
                elementSlug = elementType == 'ThisPostModel' ? 'single' : 'archive',
                loading = new Upfront.Views.Editor.Loading({
                    loading: "Saving post layout...",
                    done: "Thank you for waiting",
                    fixed: false
                })
            ;
            if(elementSlug == 'single')
                specificity = type == 'this-post' ? editor.postView.postId : editor.postView.editor.post.get('post_type');
            else
                specificity = type == 'this-post' ? editor.postView.property('element_id').replace('uposts-object-','') : editor.postView.property('post_type');

            var layoutData = {
                postLayout: editor.exportPostLayout(),
                partOptions: editor.postView.partOptions || {}
            };

            loading.render();
            saveDialog.$('#upfront-save-dialog').append(loading.$el);

            Upfront.Util.post({
                action: 'upfront_thx-export-post-layout',
                layoutData: layoutData,
                params: {
                    specificity : specificity,
                    type        : elementSlug
                }
            }).done(function (response) {
                loading.done();
                var message = "<h4>Layout successfully exported in the following file:</h4>";
                message += response.file;
                Upfront.Views.Editor.notify( message );
                saveDialog.close();
                editor.postView.postLayout = layoutData.postLayout;
                editor.postView.render();
                Upfront.Application.start(Upfront.Application.mode.last);

            });
        });
	},
    exportPartTemplate: function(){
        console.log('Export!');

        var self = this,
            editor = Upfront.Application.PostLayoutEditor.templateEditor,
            saveDialog = new Upfront.Views.Editor.SaveDialog({
                question: 'Do you wish to export the template just for this post or all the post of this type?',
                thisPostButton: 'This post only',
                allPostsButton: 'All posts of this type'
            })
        ;

        saveDialog.render();
        saveDialog.on('closed', function(){
            saveDialog.remove();
            saveDialog = false;
        });

        saveDialog.on('save', function(type) {
            var me = this,
                tpl = editor.ace.getValue(),
                postView = Upfront.Application.PostLayoutEditor.postView,
                postPart = editor.postPart,
                element = postView.property('type'),
                id
            ;

            if(type == 'this-post'){
                if(element == 'UpostsModel')
                    id = postView.property('element_id').replace('uposts-object-', '');
                else
                    id = postView.editor.postId;
            }
            else
                id = postView.editor.post.get('post_type');

            Upfront.Util.post({
                    action: 'upfront_thx-export-part-template',
                    part: postPart,
                    tpl: tpl,
                    type: element,
                    id: id
                })
                .done(function(response){
                    postView.partTemplates[editor.postPart] = tpl;
                    postView.model.trigger('template:' + editor.postPart);
                    editor.close();
                    saveDialog.close();
                    Upfront.Views.Editor.notify("Part template exported in file " + response.filename);
                })
            ;

        });
    },

	setTestContent: function(view){
        var self = this;
		if(view.postPart != 'contents')
			return;

		view.$('.upfront-object-content').html(Upfront.data.exporter.testContent);

        /**
         * Start content styler on click
         */
        view.$('.upfront-object-content').find(".upfront_edit_content_style").on("click", function(e){
            view.$('.upfront-object-content').html(Upfront.data.exporter.styledTestContent);
            new PostImageVariants({
                contentView : view
            });
        });
	}
};

return new PostLayoutManager();

});