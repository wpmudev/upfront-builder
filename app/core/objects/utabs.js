define([
  'core/objects/uobject',
  'text!core/objects/templates/utabs-options.jst'
], function(BaseObject, tpl) {
  var Utabs = BaseObject.extend({
    elementType: 'Utabs',
    optionsTemplate: _.template(tpl),

    parseData: function() {
      this.data.tabs = _.reduce(this.data.tabs, function(memo, tab) {
        return memo + "array('title' => '" + tab.title + "', 'content' => '" + tab.content + "'),\r\n";
      }, '');
    }
  });

  return Utabs;
});
