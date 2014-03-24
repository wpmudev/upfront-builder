define([
  'core/objects/uobject',
  'text!core/objects/templates/uwidget-options.jst'
], function(BaseObject, tpl) {
  var Uwidget = BaseObject.extend({
    elementType: 'Uwidget',
    optionsTemplate: _.template(tpl),

    parseData: function() {
      var fields = '';
      _.each(this.data.widget_specific_fields, function(value, key) {
        fields += "'" + key + "' => array(\r\n";
        _.each(value, function(value, aKey) {
          fields += "  '" + aKey + "' => '" + value + "',\r\n";
        });
        fields += "),\r\n";
      });
      this.data.widget_specific_fields = fields;
    }
  });

  return Uwidget;
});
