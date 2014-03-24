define([
  'underscore',
  'core/util',
  'core/objects'
], function(_, Util, objects) {
  function Module (moduleData, index, region) {
    var that = this;
    var object;

    var __construct = function() {
      var objectData = Util.flattenModuleData(moduleData);
      objectData.leaveWrapperOpen = moduleData.leaveWrapperOpen;
      var klass = objects.get(objectData.objectProperties.view_class);
      objectData.region = region;
      objectData.index = index;
      object = new klass(objectData);
    };

    this.parse = function () {
      return object.parse();
    }

    __construct();
  };

  return Module;
});
