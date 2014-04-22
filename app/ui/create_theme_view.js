define([
  'underscore',
  'jquery',
  'backbone',
  'text!ui/templates/create_theme.jst'
], function(_, $, Backbone, tpl) {
  var ExportView = Backbone.View.extend({
    events: {
      'click #thx-back-to-actions': 'finish',
      'click #thx-theme-submit': 'create'
    },

    render: function() {
      var oldSettings = _.templateSettings;
      _.templateSettings = {
        evaluate    : /<%([\s\S]+?)%>/g,
        interpolate : /<%=([\s\S]+?)%>/g,
        escape      : /<%-([\s\S]+?)%>/g
      }

      this.$el.html(_.template(tpl));

      _.templateSettings = oldSettings;

      return this;
    },

    finish: function(event) {
      event.preventDefault();
      this.trigger('finish');
    },

    create: function(event) {
      var self = this;
      var data = self.$('form').serialize();

      event.preventDefault();
      this.$('#thx-theme-submit').val('Creating theme...');
      this.$('#thx-form-error').remove();
      $.ajax({
        type: "POST",
        url: Upfront.Settings.ajax_url + '?action=upfront_thx-create-theme',
        data: {
          form: data
        }
      })
        .done(function(response) {
          Upfront.themeExporter.themes = response;
          self.render();
        })
        .fail(function(error) {
          self.$('#thx-theme-submit').val('Create theme');
          self.$el.append('<span id="thx-form-error">' + error.responseJSON.error.message + '</span>');
        });

    },
  });


  return ExportView;
});
