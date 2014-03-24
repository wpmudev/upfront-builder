define([
  'underscore'
], function(_) {
  function flattenCollection(collection) {
    var flatCollection = {};

    _.map(collection, function(property) {
      flatCollection[property.name] = property.value;
    });

    return flatCollection;
  };

  function flattenModuleData(moduleData) {
    var result = {};
    result.properties = {};
    result.objectProperties = {};

    result.properties = flattenCollection(moduleData.properties);
    result.objectProperties = flattenCollection(moduleData.objects[0].properties);
    // _.map(moduleData.properties, function(property) {
      // result.properties[property.name] = property.value;
    // });

    // _.map(moduleData.objects[0].properties, function(property) {
      // result.objectProperties[property.name] = property.value;
    // });
    return result;
  };

  return {
    flattenCollection: flattenCollection,
    flattenModuleData: flattenModuleData
  };
});
