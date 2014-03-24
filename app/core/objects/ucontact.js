define([
  'core/objects/uobject',
  'text!core/objects/templates/ucontact-options.jst'
], function(BaseObject, tpl) {
  var Ucontact = BaseObject.extend({
    optionsTemplate: _.template(tpl),
    elementType: 'Ucontact'
  });

  return Ucontact;
});
