define([
  'backbone',
  'text!core/objects/templates/uobject.jst',
], function(Backbone, objectTpl) {
  _.templateSettings = {
    evaluate : /\{\[([\s\S]+?)\]\}/g,
    interpolate : /\{\{([\s\S]+?)\}\}/g,
    escape : /<%-([\s\S]+?)%>/g
  };

  var BaseObject = function(objectData) {
    this.initialize.apply(this, arguments);
  };

  _.extend(BaseObject.prototype, {
    elementType: '',

    initialize: function(objectData) {
      this.data = objectData.objectProperties;
      this.parseData();
      this.class = objectData.properties.class;
      this.region = objectData.region;
      this.index = objectData.index;
      this.row = objectData.properties.row;
      this.leaveWrapperOpen = objectData.leaveWrapperOpen;
    },

    parse: function() {
      var margin_top = this.class.match(/mt(\d+)/);
      var margin_right = this.class.match(/mr(\d+)/);
      var margin_bottom = this.class.match(/mb(\d+)/);
      var margin_left = this.class.match(/ml(\d+)/);


      var tplData = {
        rows: this.row,
        columns: this.class.match(/c(\d+)/)[1],
        margin_top: margin_top ? margin_top[1] : 0,
        margin_right: margin_right ? margin_right[1] : 0,
        margin_bottom: margin_bottom ? margin_bottom[1] : 0,
        margin_left: margin_left ? margin_left[1] : 0,
        theme_style: this.data.theme_style || '',
        anchor: this.data.anchor || '',
        region: this.region,
        index: this.index,
        elementOptions: this.optionsTemplate(this.data),
        elementType: this.elementType,
        leaveWrapperOpen: this.leaveWrapperOpen
      };

      return _.template(objectTpl, tplData);
    },

    /**
     * Children have to implement this if some specific data
     * manipulation is to be done before template rendering.
     */
    parseData: function() {}
  });

  BaseObject.extend = Backbone.View.extend;//borrowing from Backbone

  return BaseObject;
});
