define([
  'underscore', 'jquery-loader'
], function(_, $) {

  var ThemeStyleManager = function(){};

  ThemeStyleManager.prototype = {
    init: function(){
      var me = this;

      if(!Upfront.Events.on){
        return setTimeout(function(){
          me.init();
        },50);
      }

      $(document).on('click', 'a.upfront-css-edit', this.onEditDefaultStyle);

      Upfront.Events.on('Upfront:loaded', function(){
        me.fetchDefaultStyles();
        me.setThemeCSSImages();
      });
    },

    onEditDefaultStyle: function(e){
      var editor = Upfront.Application.cssEditor;
      if(!editor.name){
        var name = '_default',
          selector = editor.elementType.id + '-' + name,
          styleId = 'upfront-style-' + selector
        ;
        editor.name = name;
        editor.selector = selector;
        //Avoid the csseditor throw errors
        if(!$('#' + styleId).length)
          $('body').append('<style id="' + styleId + '"></style>');

        editor.$('input.upfront-css-save-name').val(name);

        //If the default style already exists, load it in the editor
        var defaultStyle = $('#upfront-default-style-' + editor.elementType.id),
          styleContent
        ;
        if(defaultStyle.length){
          styleContent = $.trim(defaultStyle.html());
          styleContent = styleContent.replace(new RegExp('.upfront-output-' + editor.elementType.id + ' ', 'g'), '');
          editor.prepareAce.then(function(){
            editor.editor.setValue(styleContent, -1);
          });
        }
      }
    },

    fetchDefaultStyles: function(){
      $.ajax({
        type: "POST",
        url: Upfront.Settings.ajax_url + '?action=upfront_thx-get-default-styles',
        data: {}
      }).done(function(response){
        var styles = response.data,
          body = $('body')
        ;
        _.each(styles, function(style, elementId) {
          body.append('<style id="upfront-default-style-' + elementId + '">' + style + '</style>');
        });
      });
    },

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

        var url = result.models[0].get('original_url').replace(document.location.origin, '');
        editor.editor.insert('url("' + url + '")');
        editor.editor.focus();
      });
    }
  };

  return new ThemeStyleManager();
});