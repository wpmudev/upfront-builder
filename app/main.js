(function() {
	var dependencies = [
		Upfront.themeExporter.root + 'app/styles.js',
        Upfront.themeExporter.root + 'app/post_image.js',
        Upfront.themeExporter.root + 'app/postlayout.js'
    ]
  require(dependencies, function(StylesHelper, postImage, PostLayoutHelper){
    StylesHelper.init();
    PostLayoutHelper.init();
  });
})();
