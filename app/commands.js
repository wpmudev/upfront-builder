(function ($, undefined) {

define(function() {


var l10n = Upfront.Settings && Upfront.Settings.l10n
	? Upfront.Settings.l10n.global.views
	: Upfront.mainData.l10n.global.views
;

var SidebarCommands_PrimaryLayout = Upfront.Views.Editor.Commands.extend({
	"className": "sidebar-commands sidebar-commands-primary clearfix",
	initialize: function () {
		this.commands = _([
			new Command_InfoPanel({model: this.model}),
			new Command_LayoutModal({model: this.model}),
			
			new Command_ThemeImagesSprites({model: this.model}),
		]);
	}
});


var Command_InfoPanel = Upfront.Views.Editor.Command.extend({
	render: function () {
		var layout = Upfront.Application.layout.get("current_layout") || Upfront.Application.layout.get("layout"),
			info = layout.specificity || layout.item || layout.type
		;
		this.$el.html(
			'Current Layout: <b>' + info + '</b>'
		);
	}
});

var Command_LayoutModal = Upfront.Views.Editor.Command.extend({
	className: "command-browse-layout upfront-icon upfront-icon-browse-layouts",
	render: function () {
		this.$el.html(l10n.layouts);
        this.$el.prop("title", l10n.layouts);
	},
	on_click: function () {
		Upfront.Events.trigger("command:layout:browse");
	}
});

var Command_ThemeImagesSprites = Upfront.Views.Editor.Command.extend({
	tagName: 'li',
	className: 'command-open-media-gallery upfront-icon upfront-icon-open-gallery',
	initialize: function () {
		this.events = _.extend({}, Upfront.Views.Editor.Command.prototype.events, {
			'click a.images': 'pop_images',
			'click a.sprites': 'pop_sprites'
		});
		Upfront.Views.Editor.Command.prototype.initialize.call(this);
	},
	render: function () {
		this.$el.html(
			'<a class="images" title="'+ l10n.media +'">Theme Images</a>'
			+
			'<a class="sprites" title="'+ l10n.media +'">Theme Sprites</a>'
		);
	},
	pop_images: function (e) {
		if (e.preventDefault) e.preventDefault();
		if (e.stopPropagation) e.stopPropagation();
		Upfront.Media.Manager.open({
			media_type: ["images"]
		});
	},
	pop_sprites: function (e) {
		if (e.preventDefault) e.preventDefault();
		if (e.stopPropagation) e.stopPropagation();
		Upfront.Media.Manager.open({
			media_type: ["images"],
			themeImages: true
		});
	}
});



var LayoutsModal = Upfront.Views.Editor.Modal.extend({
	initialize: function () {
		this.events = _.extend(Upfront.Views.Editor.Modal.prototype.events, this.events);
		Upfront.Views.Editor.Modal.prototype.initialize.apply(this, arguments);
	},
	open: function () {
		var available = new LayoutsModal_Available(),
			existing = new LayoutsModal_Existing()
		;
		existing.render();
		available.render();
		Upfront.Views.Editor.Modal.prototype.open.apply(this, [function ($content, $modal) {
			$content.empty();
			$content.append(existing.$el);
			$content.append(available.$el);
		}, this]);
	}
});

var LayoutsModal_Pane = Backbone.View.extend({
	className: function () {
		var cls = 'upfront-layouts-pane';
		if (this.paneType) cls += ' ' + this.paneType;
		return cls;
	},
	paneType: '',
	action_label: '',
	layout_field: false,
	data: false,
	reload: function () {
		var me = this,
			def = new $.Deferred()
		;
		this.data = false;
		this.get_data().done(function (resp) {
			me.data = resp.data;
			def.resolve();
		});
		return def;
	},
	get_field: function () {
		if (this.layout_field) return this.layout_field;
		this.layout_field = new Upfront.Views.Editor.Field.Select({
			name: this.paneType,
			values: [{label: Upfront.Settings.l10n.global.behaviors.loading, value: ""}]
		});
		return this.layout_field;
	},
	render: function () {
		var me = this;
		if (!this.data) {
			this.reload().done(function () {
				me.render();
			});
		}
		this.$el.empty();
		this.pane();
		if (this.action_label) {
			var button = new Upfront.Views.Editor.Field.Button({
				label: this.action_label,
				compact: true,
				on_click: function () { me.dispatch_selected(); }
			});
			button.render();
			this.$el.append(button.$el);
		}
	},
	dispatch_selected: function () {
		var field = this.get_field();
		this.selected(field.get_value());
	},
/* Implementation-specific */
	get_data: function () { return new $.Deferred(); },
	pane: function () {},
	selected: function (value) { console.log(this.paneType, value); }
});

var LayoutsModal_Available = LayoutsModal_Pane.extend({
	paneType: 'available',
	action_label: 'Create Layout',
	_page_field: false,
	initialize: function () {
		LayoutsModal_Pane.prototype.initialize.apply(this, arguments);
		if (!this._page_field) {
			this._page_field = new LayoutsModal_AvailablePane_SinglePage();
		}
	},
	pane: function () {
		var me = this,
			field = this.get_field()
		;
		if (this.data) {
			field.options.values = _.map(this.data, function (layout, layout_id) {
				return {label: layout.label, value: layout_id};
			});
			field.on('changed', this.dispatch_page, this);
		}
		field.render();
		this.$el.html('Select New Layout to Create');
		this.$el.append(field.$el);
		field.delegateEvents();

		this._page_field.render();
		this._page_field.$el.hide();
		this.$el.append(this._page_field.$el);
	},
	get_data: function () {
		return Upfront.Util.post({
			action: 'upfront_list_available_layout'
		});
	},
	dispatch_page: function (value) {
		if ('single-page' !== value) return;
		this._page_field.$el.show();
	}
});

var LayoutsModal_Existing = LayoutsModal_Pane.extend({
	paneType: 'existing',
	action_label: 'Edit Layout',
	pane: function () {
		var field = this.get_field();
		if (this.data) {
			field.options.values = _.map(this.data, function (item) {
				return { label: item.label, value: item.layout.specificity || item.layout.item || item.layout.type, disabled: item.saved };
			});
		}
		field.render();
		this.$el.html('Edit Existing Layout');
		this.$el.append(field.$el);
		field.delegateEvents();
	},
	get_data: function () {
		return Upfront.Util.post({
			action: 'upfront_list_theme_layouts'
		});
	}
});


var LayoutsModal_AvailablePane_SinglePage = Backbone.View.extend({
	page: false,
	inherit: false,
	existing: false,
	all_templates: false,
	initialize: function () {
		this.page = new Upfront.Views.Editor.Field.Text({
			name: 'page_name',
			label: Upfront.Settings.l10n.global.behaviors.page_layout_name,
		});
		this.inherit = new Upfront.Views.Editor.Field.Radios({
			name: 'inherit',
			layout: "horizontal-inline",
			values: [
				{label: Upfront.Settings.l10n.global.behaviors.start_fresh, value: ''},
				{label: Upfront.Settings.l10n.global.behaviors.start_from_existing, value: 'existing'}
			]
		});
		this.existing = new Upfront.Views.Editor.Field.Select({
			name: 'existing',
			values: []
		});

		this.reload();
	},
	render: function () {
		this.page.render();
		this.inherit.render();
		this.existing.render();

		this.$el.append(this.page.$el);
		this.$el.append(this.inherit.$el);
		this.$el.append(this.existing.$el);
	},
	reload: function () {
		if (this.all_templates) return;

		var me = this;
		Upfront.Util.post({
			action: "upfront-wp-model",
			model_action: "get_post_extra",
			postId: "fake", // Stupid walkaround for model handler insanity
			allTemplates: true
		}).done(function (response) {
			if (!response.data || !response.data.allTemplates) return false;
			if (0 === response.data.allTemplates.length) {
				me.inherit.$el.hide();
				me.existing.$el.hide();
				return false;
			}
			me.all_templates = response.data.allTemplates;
			me.existing.options.values = [];
			_.each(response.data.allTemplates, function (tpl, title) {
				me.existing.options.values.push({label: title, value: tpl});
			});
			me.render();
		});
	}
});

var modal = false;
function browse_layouts () {
	if (!modal) {
		modal = new LayoutsModal({
			to: $('body'), 
			button: false, 
			top: 120, 
			width: 540
		});
		modal.render();
		$('body').append(modal.el);
	}
	modal.open()
}




function init () {
	Upfront.Events.on("application:mode:after_switch", function () {
		if (Upfront.Application.get_current() !== Upfront.Settings.Application.MODE.THEME) return false;

		Upfront.Application.sidebar.sidebar_commands.primary = new SidebarCommands_PrimaryLayout({model: Upfront.Application.sidebar.model});
		Upfront.Application.sidebar.sidebar_commands.additional = false;
		
		Upfront.Application.sidebar.render();

		Upfront.Application.current_subapplication.stopListening(Upfront.Events, "command:layout:browse"); // Unbind this stuff, we don't use this
		Upfront.Application.current_subapplication.stopListening(Upfront.Events, "command:layout:create"); // Unbind this stuff, we don't use this
		Upfront.Application.current_subapplication.listenTo(Upfront.Events, "command:layout:browse", browse_layouts);
	});
}

return {
	init: init
};


});
})(jQuery);