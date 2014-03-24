(function() {
  var customRequire = require.config({
    context: 'themeExporter',
    baseUrl: Upfront.themeExporter.root + 'app',
    paths: {
      underscore: Upfront.themeExporter.includes + 'underscore.min',
      backbone: Upfront.themeExporter.includes + 'backbone.min'
    },
    shim: {
      'jquery-loader': {
        exports : '$'
      },
      'underscore' : {
        exports : '_'
      },
      'backbone': {
        deps: ['underscore', 'jquery-loader'],
        exports: 'Backbone'
      }
    }
  });

  customRequire([
    'underscore',
    'jquery-loader',
    'core/layoutParser',
    'ui/ui',
    'core/styles'
  ], function(_, $, parser, UI, StylesHelper){
    _.templateSettings = {
      evaluate : /\{\[([\s\S]+?)\]\}/g,
      interpolate : /\{\{([\s\S]+?)\}\}/g,
      escape : /<%-([\s\S]+?)%>/g
    };

    var ui = new UI({
      parser: parser
    });

    // Upfront.themeExporter.ui = ui;
    $('body').append(ui.render().el);
    StylesHelper.init();
  });
})();
