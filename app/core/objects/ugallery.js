define([
  'core/objects/uobject',
  'text!core/objects/templates/ugallery-options.jst'
], function(BaseObject, tpl) {
  var Ugallery = BaseObject.extend({
    elementType: 'Ugallery',
    optionsTemplate: _.template(tpl)
  });

  return Ugallery;
});
