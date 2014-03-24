define([
  'core/objects/uobject',
  'text!core/objects/templates/usocial-options.jst',
  'text!core/objects/templates/usocial-service.jst'
], function(BaseObject, tpl, serviceTpl) {
  var Usocial = BaseObject.extend({
    serviceTpl: _.template(serviceTpl),
    elementType: 'SocialMedia',
    optionsTemplate: _.template(tpl),

    parseData: function(data) {
      this.data.like_social_media_services = "'" + this.data.like_social_media_services.join("', '") + "'";

      this.data.services = _.reduce(this.data.services, function(memo, service) {
        return memo + this.serviceTpl({service: service });
      }, '', this);

      this.data.button_services = _.reduce(this.data.button_services, function(memo, service) {
        return memo + this.serviceTpl({service: service });
      }, '', this);
    }
  });

  return Usocial;
});
