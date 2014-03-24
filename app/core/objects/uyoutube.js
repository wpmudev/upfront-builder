define([
  'core/objects/uobject',
  'text!core/objects/templates/uyoutube-options.jst',
  'text!core/objects/templates/uyoutube-video.jst',
], function(BaseObject, tpl, videoTpl) {
  var Uyoutube = BaseObject.extend({
    videoTpl: _.template(videoTpl),
    optionsTemplate: _.template(tpl),
    elementType: 'Uyoutube',

    parseData: function() {
      this.data.multiple_videos = _.reduce(this.data.multiple_videos, function(memo, video) {
        return memo + this.videoTpl({video: video});
      }, '', this);
    }
  });

  return Uyoutube;
});
