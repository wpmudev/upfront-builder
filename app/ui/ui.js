define([
  'underscore',
  'jquery',
  'backbone',
  'ui/export_view',
  'ui/actions_view',
  'ui/create_theme_view',
  'text!ui/templates/ui.jst'
], function(_, $, Backbone, ExportView, ActionsView, CreateThemeView, tpl) {
  var UI = Backbone.View.extend({
    events: {
      'click .export': 'toggle'
    },

    initialize: function(options) {
      this.options = options;
      this.model = new Backbone.Model();
    },

    render: function() {
      var oldSettings = _.templateSettings;
      _.templateSettings = {
        evaluate    : /<%([\s\S]+?)%>/g,
        interpolate : /<%=([\s\S]+?)%>/g,
        escape      : /<%-([\s\S]+?)%>/g
      }

      this.$el.html(_.template(tpl, this.model.toJSON()));
      if (this.view) {
        this.listenToOnce(this.view, 'finish', this.showActions);
        this.$('.output').html(this.view.render().el);
      }

      _.templateSettings = oldSettings;
      return this;
    },

    toggle: function() {
      if (this.view) {
        this.view = null;
        this.render();
        return;
      }

      this.showActions();
    },

    showActions: function() {
      this.view = new ActionsView();
      this.listenToOnce(this.view, 'change', this.showAction);
      this.render();
    },

    showAction: function(action) {
      if (this.view) this.view.remove();

      this['show' + action]();
    },

    showExport: function() {
      this.view = new ExportView({
        parser: this.options.parser
      });
      this.render();
    },

    showCreateTheme: function() {
      this.view = new CreateThemeView();
      this.render();
    }
  });

  return UI;
});
