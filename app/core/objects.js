define([
  'core/objects/uimage',
  'core/objects/unavigation',
  'core/objects/uplaintext',
  'core/objects/ugallery',
  'core/objects/ucontact',
  'core/objects/umap',
  'core/objects/utabs',
  'core/objects/uyoutube',
  'core/objects/uaccordion',
  'core/objects/usocial',
  'core/objects/ulikebox',
  'core/objects/uwidget',
  'core/objects/usearch',
  'core/objects/uslider',
  'core/objects/uposts',
  'core/objects/ucode',
  'core/objects/not_implemented'
], function(
    Uimage, Unavigation, UplainText, Ugallery, Ucontact, Umap,
    Utabs, Uyoutube, Uaccordion, Usocial, Ulikebox, Uwidget,
    Usearch, Uslider, Uposts, Ucode, NotImplemented
  ) {

  var get = function(type) {
    var map = {
      UimageView: Uimage,
      NavigationView: Unavigation,
      PlainTxtView: UplainText,
      UgalleryView: Ugallery,
      UcontactView: Ucontact,
      MapView: Umap,
      UtabsView: Utabs,
      UyoutubeView: Uyoutube,
      UaccordionView: Uaccordion,
      SocialMediaView: Usocial,
      LikeBoxView: Ulikebox,
      UwidgetView: Uwidget,
      UsearchView: Usearch,
      USliderView: Uslider,
      UpostsView: Uposts,
      CodeView: Ucode
    }

    return map[type] || NotImplemented;
  };

  return {
    get: get
  }
});
