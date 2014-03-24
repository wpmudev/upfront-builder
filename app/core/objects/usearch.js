define([
  'core/objects/uobject',
  'text!core/objects/templates/usearch-options.jst'
], function(BaseObject, tpl) {
  var Usearch = BaseObject.extend({
    elementType: 'Usearch',
    optionsTemplate: _.template(tpl)
  });

  return Usearch;
});
