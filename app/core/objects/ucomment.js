define([
  'core/objects/uobject'
], function(BaseObject, tpl) {
  var Ucomment = BaseObject.extend({
    elementType: 'Ucomment',
    optionsTemplate: _.template('')
  });

  return Ucomment;
});
