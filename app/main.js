(function() {
	var dependencies = [
		Upfront.themeExporter.root + 'app/styles.js',
		Upfront.themeExporter.root + 'app/postlayout.js',
        Upfront.themeExporter.root + 'app/post_image.js'
    ]
  require(dependencies, function(StylesHelper, PostLayoutHelper){
    StylesHelper.init();
    PostLayoutHelper.init();
  });
})();
