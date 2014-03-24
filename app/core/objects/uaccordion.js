define([
  'core/objects/uobject',
  'text!core/objects/templates/uaccordion-options.jst',
  'text!core/objects/templates/uaccordion-slide.jst'
], function(BaseObject, tpl, slideTpl) {
  var Uaccordion = BaseObject.extend({
    optionsTemplate: _.template(tpl),
    slideTpl: _.template(slideTpl),
    elementType: 'Uaccordion',

    parseData: function() {
      this.data.accordion = _.reduce(this.data.accordion, function(memo, slide) {
        return memo + this.slideTpl({slide: slide});
      }, '', this);
    }
  });

  return Uaccordion;
});
