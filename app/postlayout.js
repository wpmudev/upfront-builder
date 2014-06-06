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
            });
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