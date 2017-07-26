upfrontrjs.define([
  'underscore', 'jquery'
], function(_, $) {

  var ThemeStyleManager = function(){};
  ThemeStyleManager.prototype = {
    init: function(){
/*
      var me = this;

      if(!Upfront.Events.on){
        return setTimeout(function(){
          me.init();
        },50);
      }
      Upfront.Events.on('Upfront:loaded', function(){
        me.setThemeCSSImages();
      });
*/
    },
/*
    setThemeCSSImages: function(){
      var editor = Upfront.Application.cssEditor;

      delete(editor.events['click .upfront-css-image']);

      editor.delegateEvents();
      editor.$el.on('click', '.upfront-css-image', _.bind(this.openImagePicker, this));
    },
    openImagePicker: function(){
      var me = this,
        editor = Upfront.Application.cssEditor
      ;

      Upfront.Media.Manager.open({
        themeImages: true
      }).done(function(popup, result){
        Upfront.Events.trigger('upfront:element:edit:stop');
        if(!result)
          return;

        var url = result.models[0].get('original_url');//.replace(document.location.origin, '');
        editor.editor.insert('url("' + url + '")');
        editor.editor.focus();
      });
    }
*/
  };

  return new ThemeStyleManager();
});
