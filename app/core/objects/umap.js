define([
  'core/objects/uobject',
  'text!core/objects/templates/umap-options.jst'
], function(BaseObject, tpl) {
  var Umap = BaseObject.extend({
    elementType: 'Umap',
    optionsTemplate: _.template(tpl),

    parseData: function() {
      this.data.map_center = this.data.map_center.join(', ');
      this.data.controls = "'" + this.data.controls.join("', '") + "'";
    }
  });

  return Umap;
});
