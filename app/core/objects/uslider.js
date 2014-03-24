define([
  'core/objects/uobject',
  'text!core/objects/templates/uslider-options.jst',
  'text!core/objects/templates/uslider-slide.jst'
], function(BaseObject, tpl, slideTpl) {
  var Uslider = BaseObject.extend({
    slideTpl: _.template(slideTpl),
    optionsTemplate: _.template(tpl),
    elementType: 'USlider',

    parseData: function() {
      this.data.slides = _.reduce(this.data.slides, function(memo, slide) {
        return memo + this.slideTpl({ slide: slide });
      }, '', this);
    }
  });

  return Uslider;
});
