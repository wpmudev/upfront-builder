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
		console.log('Export layout');
	},
	setTestContent: function(view){
		if(view.postPart != 'contents')
			return;
		view.$el.html(Upfront.data.exporter.testContent);
	}
};

return new PostLayoutManager();

});