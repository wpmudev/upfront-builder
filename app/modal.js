(function ($, undefined) {

define([
	Upfront.themeExporter.root + 'app/exporter.js'
], function (Exporter) {


var LayoutsModal = Upfront.Views.Editor.Modal.extend({
	header: false,
	available: false,
	existing: false,
	initialize: function () {
		this.events = _.extend(Upfront.Views.Editor.Modal.prototype.events, this.events);
		Upfront.Views.Editor.Modal.prototype.initialize.apply(this, arguments);

		this.header = new LayoutsModal_Header();
		this.available = new LayoutsModal_Available();
		this.existing = new LayoutsModal_Existing();

		this.header.on("close", this.close, this);
		this.available.on("selected", this.close, this);
		this.existing.on("selected", this.close, this);

		// Clear output on export
		this.listenTo(Upfront.Events, 'command:layout:export_theme', this.clear_content);
	},
	open: function () {
		Upfront.Views.Editor.Modal.prototype.open.apply(this, [function ($content, $modal) {
			if (!$content.is(":empty")) return;
			this.$content = $content;
			this.render_content();
		}, this]);
	},

	/**
	 * Cleans content, forcing reload
	 */
	clear_content: function () {
		this.$content.empty(); // Force whole content to repaint
		this.existing.data = false; // Force existing layouts reload
		this.existing.layout_field = false; // Force layout field repaint
	},

	/**
	 * (Re)paints the content
	 *
	 * @return {Boolean}
	 */
	render_content: function () {
		if (!this.$content) return false;

		this.header.render();
		this.existing.render();
		this.available.render();

		this.header.delegateEvents();

		this.$content.addClass('thx-browse_layouts thx-all_layouts');
		this.$content.empty();
		this.$content.append(this.header.$el);
		this.$content.append(this.existing.$el);
		this.$content.append(this.available.$el);

		return true;
	}
});

var ResponsiveLayoutsModal = Upfront.Views.Editor.Modal.extend({
	existing: false,
	initialize: function () {
		this.events = _.extend(Upfront.Views.Editor.Modal.prototype.events, this.events);
		Upfront.Views.Editor.Modal.prototype.initialize.apply(this, arguments);
		this.existing = new LayoutsModal_Existing();
		this.existing.on("selected", this.close, this);
	},
	open: function () {
		Upfront.Views.Editor.Modal.prototype.open.apply(this, [function ($content, $modal) {
			if (!$content.is(":empty")) return;

			$content.addClass('thx-browse_layouts thx-existing_layouts');

			this.existing.render();

			$content.empty();
			$content.append(this.existing.$el);

		}, this]);
	}
});

var LayoutsModal_Header = Backbone.View.extend({
	className: 'thx-layouts-header clearfix',
	events: {
		'click a': 'propagate_close'
	},
	render: function () {
		this.$el.empty()
			.append('<h3>' +  Upfront.Settings.l10n.exporter.manage_layouts + '</h3>')
			.append('<a href="#close">&times;</a>')
		;
	},
	propagate_close: function (e) {
		if (e.preventDefault) e.preventDefault();
		if (e.stopPropagation) e.stopPropagation();

		this.trigger("close");

		return false;
	}
});

var LayoutsModal_Pane = Backbone.View.extend({
	className: function () {
		var cls = 'thx-layouts-pane';
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
			values: [{label: Upfront.Settings.l10n.exporter.loading, value: ""}]
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
			button.delegateEvents();
		}
	},
	dispatch_selected: function () {
		var field = this.get_field();
		this.selected(field.get_value());
		this.trigger("selected");
	},
/* Implementation-specific */
	get_data: function () { return new $.Deferred(); },
	pane: function () {},
	selected: function (value) { console.log(this.paneType, value); }
});

var LayoutsModal_Available = LayoutsModal_Pane.extend({
	paneType: 'available',
	action_label:  Upfront.Settings.l10n.exporter.create_layout,
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
		if ('single-page' !== value) this._page_field.$el.hide();
		else this._page_field.$el.show();
	},
	selected: function (layout) {
		var layout_slug = Upfront.Application.layout.get('layout_slug'),
			data = _.extend({}, this.data[layout]),
			specific_layout = this._page_field.page.get_value()
		;

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

		data.use_existing = layout.match(/^single-page/) && specific_layout && "existing" === this._page_field.inherit.get_value() ?
			/* ? */ this._page_field.existing.get_value() :
			/* : */ false
		;

		Upfront.themeExporter.current_layout_label = data.label;

		Upfront.Application.create_layout(data.layout, {layout_slug: layout_slug, use_existing: data.use_existing}).done(function() {
			Upfront.Application.layout.set('current_layout', layout);
			Upfront.Events.trigger("grid:toggle"); // Make sure the grid toggle button reverts back to its original state
			// Immediately export layout to write initial state to file.
			Exporter._export_layout();
		});
	}
});

var LayoutsModal_Existing = LayoutsModal_Pane.extend({
	paneType: 'existing',
	action_label:  Upfront.Settings.l10n.exporter.edit_layout,

	pane: function () {
		var field = this.get_field();
		if (this.data) {
			field.options.values = _.map(this.data, function (item, item_id) {
				return { label: item.label, value: item_id, disabled: item.saved };
			});
		}
		field.render();
		this.$el.html( Upfront.Settings.l10n.exporter.edit_existing_layout);
		this.$el.append(field.$el);
		field.delegateEvents();
	},

	get_data: function () {
		return Upfront.Util.post({
			action: 'upfront_list_theme_layouts'
		});
	},
	selected: function (layout) {
		var layout_slug = Upfront.Application.layout.get('layout_slug'),
			data = this.data[layout],
			loading = false
		;
		if (!data) return false;

		if (data.label) Upfront.themeExporter.current_layout_label = data.label;

		if (data.latest_post) _upfront_post_data.post_id = data.latest_post;
		Upfront.Application.layout.set('current_layout', layout);
		loading = Upfront.Application.load_layout(data.layout, {layout_slug: layout_slug});

		if (loading && loading.done) loading.done(function () {
			Upfront.Events.trigger("grid:toggle"); // Make sure the grid toggle button reverts back to its original state
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
			label: Upfront.Settings.l10n.exporter.page_layout_name,
		});
		this.inherit = new Upfront.Views.Editor.Field.Radios({
			name: 'inherit',
			layout: "horizontal-inline",
			values: [
				{label: Upfront.Settings.l10n.exporter.start_fresh, value: ''},
				{label: Upfront.Settings.l10n.exporter.start_from_existing, value: 'existing'}
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

		this.existing.delegateEvents();
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

var normal_modal = false;
function browse_normal_layouts () {
	if (!normal_modal) {
		normal_modal = new LayoutsModal({
			to: $('body'),
			button: false,
			top: 120,
			width: 540
		});
		normal_modal.render();
		$('body').append(normal_modal.el);
	}
	normal_modal.open();
}

var responsive_modal = false;
function browse_responsive_layouts () {
	if (!responsive_modal) {
		responsive_modal = new ResponsiveLayoutsModal({
			to: $('body'),
			button: false,
			top: 120,
			width: 540
		});
		responsive_modal.render();
		$('body').append(responsive_modal.el);
	}
	responsive_modal.open();
}


function init_normal_exporter () {
	Upfront.Application.current_subapplication.stopListening(Upfront.Events, "command:layout:browse"); // Unbind this stuff, we don't use this
	Upfront.Application.current_subapplication.stopListening(Upfront.Events, "command:layout:create"); // Unbind this stuff, we don't use this
	Upfront.Application.current_subapplication.listenTo(Upfront.Events, "command:layout:browse", browse_normal_layouts);
}

function init_responsive_exporter () {
	Upfront.Application.current_subapplication.stopListening(Upfront.Events, "command:layout:browse"); // Unbind this stuff, we don't use this
	Upfront.Application.current_subapplication.listenTo(Upfront.Events, "command:layout:browse", browse_responsive_layouts);
}


function init () {
	Upfront.Events.on("application:mode:after_switch", function () {
		if (Upfront.Application.get_current() === Upfront.Settings.Application.MODE.THEME) return init_normal_exporter();
		if (Upfront.Application.get_current() === Upfront.Settings.Application.MODE.RESPONSIVE) return init_responsive_exporter();
	});
}

return {
	init: init
};


});
})(jQuery);
