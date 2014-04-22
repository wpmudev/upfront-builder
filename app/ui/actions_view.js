define([
  'underscore',
  'jquery',
  'backbone',
  'text!ui/templates/actions.jst'
], function(_, $, Backbone, tpl) {
  var ExportView = Backbone.View.extend({
    events: {
      'change [name=thx-choose-action]': 'choose'
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

    choose: function(event) {
      this.trigger('change', this.$('select :selected').val());
    }
  });

  return ExportView;
});
