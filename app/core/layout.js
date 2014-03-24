define([
  'underscore',
  'core/region',
  'text!core/templates/layout.jst'
], function(_, Region, tpl) {
  function Layout (layoutData) {
    var template = _.template(tpl);
    var regions = [];
    var type = '';
    var empty = false;
    var __construct = function() {
      type = layoutData.type;

      if (layoutData.regions.length < 1 || (layoutData.type === 'main' && layoutData.regions.length < 2)) {
        empty = true;
        return;
      }

      _.each(layoutData.regions, function(regionData) {
        regions.push(new Region(regionData));
      });
    };

    this.parse = function() {
      var regionsData;
      if (empty) {
        regionsData = false;
      } else {
        regionsData = _.invoke(regions, 'parse');
      }
      return template({
        type: type,
        regions: regionsData
      });
    };

    __construct();
  };

  return Layout;
});
