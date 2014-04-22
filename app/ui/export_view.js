define([
  'underscore',
  'jquery',
  'backbone',
  'text!ui/templates/export.jst'
], function(_, $, Backbone, tpl) {
  var ExportView = Backbone.View.extend({
    events: {
      'submit #thx-page-export-form': 'exportPage',
      'click #thx-submit-form': 'exportPage',
      'click #thx-back-to-actions': 'finish',
      'change [name=thX-destination-theme]': 'changeTheme'
    },

    initialize: function(options) {
      var specificTemplates = [];
      _.each(Upfront.Application.LayoutEditor.get_layout_data().layout, function(layout) {
        specificTemplates.push({
          filename: layout,
          name: layout
        });
      }, this);
      this.model = new Backbone.Model({
        currentTheme: Upfront.themeExporter.currentTheme,
        themes: Upfront.themeExporter.themes,
        generalTemplates: Upfront.themeExporter.templates,
        specificTemplates: specificTemplates
      });


      this.parse = options.parser;
    },

    changeTheme: function(event) {
      var theme =  this.$('[name=thX-destination-theme] :selected').val();
      Upfront.themeExporter.currentTheme = theme;
      this.model.set({'currentTheme': theme});
    },

    render: function() {
      var oldSettings = _.templateSettings;
      _.templateSettings = {
        evaluate    : /<%([\s\S]+?)%>/g,
        interpolate : /<%=([\s\S]+?)%>/g,
        escape      : /<%-([\s\S]+?)%>/g
      };

      this.$el.html(_.template(tpl, this.model.toJSON()));

      _.templateSettings = oldSettings;

      return this;
    },

    exportPage: function(e){
      var self = this,
          data = {
            theme: self.$('form [name="thX-destination-theme"] :selected').val(),
            template: self.$('form [name="thX-destination-template"] :selected').val(),
            functionsphp: self.$('form [name="thX-functionsphp"]:checked').val(),
            regions: JSON.stringify(Upfront.Application.LayoutEditor.get_layout_data().regions)
          }
      ;

      this.$el.html('exporting...');

      $.ajax({
        type: "POST",
        url: Upfront.Settings.ajax_url + '?action=upfront_thx-export-layout',
        data: {data: data}
      })
      .done(function() {
        self.render();
        return;
      });
    },

    exportPageOld: function(event) {
      var self = this;
      var theme = self.$('form [name="thX-destination-theme"] :selected').val();
      var template = self.$('form [name="thX-destination-template"] :selected').val();
      var layout = this.parse();

      event.preventDefault();
      if (self.$('form [name="thX-debug"]').is(':checked')) {
        _.each(layout.layouts, function(aLayout) {
          console.info(aLayout);
        });
        return;
      }
      this.$el.html('exporting...');
      $.ajax({
        type: "POST",
        url: Upfront.Settings.ajax_url + '?action=upfront_thx-save-layout-to-template',
        data: {
          data: {
            theme: theme,
            template: template,
            layout: layout
          }
        }
      })
        .done(function() {
          self.render();
          return;
        });
    },

    finish: function(event) {
      event.preventDefault();
      this.trigger('finish');
    }
  });

  return ExportView;
});
