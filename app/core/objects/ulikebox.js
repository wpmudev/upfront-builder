define([
  'core/objects/uobject',
  'text!core/objects/templates/ulikebox-options.jst'
], function(BaseObject, tpl) {
  var Ulikebox = BaseObject.extend({
    elementType: 'LikeBox',
    optionsTemplate: _.template(tpl),

    parseData: function() {
      this.data.element_size = "'width' => " + this.data.element_size.width
        + ", 'height' => " + this.data.element_size.height;
    }
  });

  return Ulikebox;
});
