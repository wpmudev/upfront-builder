define([
  'underscore', 'jquery'
], function(_, $) {

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

		console.log(commands.length);
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
		if(view.postPart != 'contents')
			return;
		view.$('.upfront-object-content').html(Upfront.data.exporter.testContent);
	}
};

return new PostLayoutManager();

});