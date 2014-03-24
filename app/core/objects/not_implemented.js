define([
], function() {
  var NotImplemented = function(objectData) {
    this.parse = function() {
      return '//' + objectData.objectProperties.view_class + ' parsing is not implemented yet.';
    };
  };

  return NotImplemented;
});
