define([
  'core/objects/uobject',
  'text!core/objects/templates/uplaintext-options.jst'
], function(BaseObject, tpl) {

  var UplainText = BaseObject.extend({
    elementType: 'PlainTxt',
    optionsTemplate: _.template(tpl),

    parseData: function() {
      var data = this.data;
      data.bg_color_enabled || (data.bg_color_enabled = false);
      data.bg_color || (data.bg_color = false);
      data.border_enabled || (data.border_enabled  = false);
      data.border_width || (data.border_width = false);
      data.border_color || (data.border_color = false);
      data.border_style || (data.border_style = false);
      data.anchor || (data.anchor = false);
      data.background_color || (data.background_color = false);
      this.data = data;
    }
  });

  return UplainText;
});
