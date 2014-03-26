define([
  'core/objects/uobject',
  'text!core/objects/templates/upost-options.jst'
], function(BaseObject, tpl) {
  var Upost = BaseObject.extend({
    elementType: 'ThisPost',
    optionsTemplate: _.template(tpl),

    parseData: function() {
      this.data.post_data = "'" + this.data.post_data.join("',\r\n'") + "'";
    },

    addElementSpecificProperties: function() {
      return "'sticky' => " + this.properties.sticky + ",";
    }
  });

  return Upost;
});
