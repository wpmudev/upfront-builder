define([
  'core/objects/uobject',
  'text!core/objects/templates/uimage-options.jst'
], function(BaseObject, tpl) {
  var Uimage = BaseObject.extend({
    elementType: 'Uimage',
    optionsTemplate: _.template(tpl),

    parseData: function() {
      if (this.data.srcOriginal) {
        this.data.srcOriginal = "'" + this.data.srcOriginal + "'";
      } else {
        this.data.srcOriginal = "false";
      }
    }
  });

  return Uimage;
});
