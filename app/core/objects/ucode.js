define([
  'core/objects/uobject',
  'text!core/objects/templates/ucode-options.jst'
], function(BaseObject, tpl) {
  var Ucode = BaseObject.extend({
    elementType: 'Code',
    optionsTemplate: _.template(tpl)
  });

  return Ucode;
});
