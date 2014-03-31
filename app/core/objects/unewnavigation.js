define([
  'core/objects/uobject',
  'text!core/objects/templates/unewnavigation-options.jst'
], function(BaseObject, tpl) {
  var Unewnavigation = BaseObject.extend({
    elementType: 'Unewnavigation',
    optionsTemplate: _.template(tpl),

    parseData: function() {
      this.data.allow_sub_nav = this.data.allow_sub_nav.length ? true : false;
      this.data.allow_new_pages = this.data.allow_new_pages.length ? true : false;
      this.data.is_floating = this.data.is_floating.length ? true : false;
    }
  });

  return Unewnavigation;
});
