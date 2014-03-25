define([
  'core/objects/uobject',
  'text!core/objects/templates/umap-options.jst'
], function(BaseObject, tpl) {
  var Umap = BaseObject.extend({
    elementType: 'Umap',
    optionsTemplate: _.template(tpl),

    parseData: function() {
      var markers = 'array(';
      for(var i in this.data.markers){
        markers += 'array("lat" => ' + this.data.markers[i].lat + ', "lng" => ' + this.data.markers[i].lng + ')';
      }
      this.data.markers = markers + ')';
      this.data.map_center = this.data.map_center.join(', ');
      this.data.controls = "'" + this.data.controls.join("', '") + "'";
    }
  });

  return Umap;
});
