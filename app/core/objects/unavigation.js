define([
  'core/objects/uobject',
  'text!core/objects/templates/unavigation-options.jst'
], function(BaseObject, tpl) {
  var Unavigation = BaseObject.extend({
    elementType: 'Navigation',
    optionsTemplate: _.template(tpl)
  });

  return Unavigation;
});
