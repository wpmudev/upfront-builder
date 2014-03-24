define([
  'underscore',
  'core/util',
  'core/umodule', // "module" require in requirejs is reserved for something else
  'text!core/templates/region.jst'
], function(_, Util, Module, tpl) {
  function Region (regionData) {
    var template = _.template(tpl);
    var modules = [];

    var __construct = function() {
      if (regionData.name === 'shadow') return;
      regionData.name = regionData.name.replace('-', '_').replace(' ', '_');

      _.each(regionData.modules, function(moduleData, index, collection) {
        var wrapperId, nextWrapperId;
        var next = collection[index+1];
        moduleData.leaveWrapperOpen = false;

        if (next) {
          wrapperId =_.find(moduleData.properties, function(property) {
            return property.name === 'wrapper_id';
          });
          nextWrapperId =_.find(next.properties, function(property) {
            return property.name === 'wrapper_id';
          });

          if (wrapperId.value === nextWrapperId.value) {
            moduleData.leaveWrapperOpen = true;
          }
        }

        modules.push(new Module(moduleData, index, regionData.name));
      });

      // Transform strings and array into appropriate formats for template.
      regionData.properties = Util.flattenCollection(regionData.properties);
      _.each(regionData.properties, function(value, key) {
        var transformed = value;
        switch(key) {
        case 'background_slider_images':
        case 'background_map_center':
          transformed = 'array(' + value.join(', ') + ')';
          break;
        case 'background_map_controls':
          if (_.isEmpty(value)) {
            transformed = "array()";
          } else {
            transformed = "array('" + value.join("', '") + "')";
          }
          break;
        case 'background_slider_control':
        case 'background_slider_transition':
        case 'background_type':
        case 'background_image':
        case 'background_style':
        case 'background_color':
        case 'background_map_style':
        case 'background_map_location':
          transformed = "'" + value + "'";
          break;
        case 'background_map_styles':
          transformed = "json_decode('" + JSON.stringify(value) + "')";
        }
        regionData.properties[key] = transformed;
      });
    };

    this.parse = function () {
      if (regionData.name === 'shadow') return '';

      return template({
          regionName : regionData.name,
          backgroundProperties: regionData.properties,
          modules: _.invoke(modules, 'parse')
        }
      );
    }
    __construct();
  };

  return Region;
});
