define([
  'underscore',
  'jquery',
  'core/layout',
], function(_, $, Layout) {
  _.templateSettings = {
    evaluate : /\{\[([\s\S]+?)\]\}/g,
    interpolate : /\{\{([\s\S]+?)\}\}/g,
    escape      : /<%-([\s\S]+?)%>/g
  };

  var parseLayoutFromData = function() {
    var layoutParts, elementStyles, layoutData,
      layouts = {};

    layoutParts = {
      header: [],
      main: [],
      footer: []
    };
    layoutData = Upfront.Application.current_subapplication.get_layout_data();

    _.each(layoutData.regions, function(region) {
      if (region.name.indexOf('header') > -1) {
        layoutParts.header.push(region);
      } else if (region.name.indexOf('footer') > -1) {
        layoutParts.footer.push(region);
      } else {
        layoutParts.main.push(region);
      }
    });
    if (layoutParts.header.length < 1) {
      delete layoutParts.header;
    }
    if (layoutParts.footer.length < 1) {
      delete layoutParts.footer;
    }
    _.each(layoutParts, function(layoutData, name) {
      var layout = new Layout({
        regions: layoutData,
        type: name
      });
      layouts[name] = layout.parse();
    });

    elementStyles = '';
    $('style[id^=upfront-style-]').each(function() {
      elementStyles += $(this).html();
    });


    return {
      layouts: layouts,
      elementStyles: elementStyles
    };
  };

  return parseLayoutFromData;
});
