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
      this.properties = objectData.properties;
      this.data = objectData.objectProperties;
      this.parseData();
      this.class = this.properties.class;
      this.region = objectData.region;
      this.index = objectData.index;
      this.row = this.properties.row;
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
        leaveWrapperOpen: this.leaveWrapperOpen,
        elementSpecificProperties: this.addElementSpecificProperties()
      };

      return _.template(objectTpl, tplData);
    },

    /**
     * Children have to implement this if some specific data
     * manipulation is to be done before template rendering.
     */
    parseData: function() {},

    /**
     * This gives child classes option to specify additional
     * properties that is not in options but in main element data.
     * See template for uobject and upost.js object
     * Element specific properties must be returned in formated way
     * e.g.:
     *   'sticky' => true,
     * this should be valid php in which value is assigned to
     * key and comma is finishing line.
     * This is also valid:
     * e.g.:
     *   'some_key' => array('red', 'blue', 'yellow'),
     */
    addElementSpecificProperties: function() {
      return false;
    }
  });

  BaseObject.extend = Backbone.View.extend;//borrowing from Backbone

  return BaseObject;
});
