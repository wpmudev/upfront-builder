define([
  'core/objects/uobject',
  'text!core/objects/templates/uposts-options.jst'
], function(BaseObject, tpl) {
  var Uposts = BaseObject.extend({
    elementType: 'Uposts',
    optionsTemplate: _.template(tpl),

    parseData: function() {
      this.data.post_data = "'" + this.data.post_data.join("',\r\n'") + "'";
    }
  });

  return Uposts;
});
