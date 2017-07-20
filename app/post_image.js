upfrontrjs.define([
	'underscore',
	'jquery',
	'text!' + Upfront.themeExporter.root + 'templates/theme/tpl/image_variants.html',
], function(_, $, variant_tpl){


var l10n = Upfront.Settings && Upfront.Settings.l10n ?
		/* ? */ Upfront.Settings.l10n.exporter :
		/* : */ Upfront.mainData.l10n.exporter
	;

var PostImageVariants =  Backbone.View.extend({
	initialize: function( options ) {
		this.contentView = options.contentView;
		this.populate_style_content();
	},
	add_new_variant : function(e){
		e.preventDefault();
		e.stopPropagation();
		var model = new Upfront.Models.ImageVariant(),
			label_id = model.get('label').toLowerCase().trim().replace(/\s/g, "-"),
			label_set = false,
			label_count = 0,
			label_found = false,
			variant
		;

		model.set("vid", Upfront.Util.get_unique_id("variant"));
		while ( !label_set ) {
			label_found = false;
			Upfront.Content.ImageVariants.each(function (each) {
				var each_label_id = each.get('label').toLowerCase().trim().replace(/\s/g, "-");
				if ( label_id === each_label_id ) label_found = true;
			});
			if ( label_found ) {
				label_count++;
				label_id = model.get('label').toLowerCase().trim().replace(/\s/g, "-") + '-' + label_count;
			}
			else {
				if ( label_count > 0 ) {
					model.set('label', model.get('label') + ' ' + label_count);
				}
				label_set = true;
				break;
			}
		}

		variant = new PostImageVariant({ model : model});
		variant.parent_view = this;
		variant.render();
		variant.$el.hide();
		/**
		 * Add it after the last variant
		 */
		var $variants = $(".ueditor-insert-variant")
		if ( $variants.length > 0 ) {
			$variants.last().parent().after( variant.el );
		}
		else {
			this.contentView.$el.find("#upfront-image-variants").prepend( variant.el );
		}
		variant.$el.fadeIn();
		Upfront.Content.ImageVariants.add(model);
	},
	populate_style_content : function() {
		var self = this;

		Upfront.Content.ImageVariants.each(function (model) {
			var variant = new PostImageVariant({model: model});
			variant.parent_view = self;
			variant.render();
			self.contentView.$el.find("#upfront-image-variants").append(variant.el);
		});

		if (Upfront.Content.ImageVariants.length === 0) {
			var model = new Upfront.Models.ImageVariant();
			model.set("vid", Upfront.Util.get_unique_id("variant"));
			var variant = new PostImageVariant({model: model});
			Upfront.Content.ImageVariants.add(model);
			variant.parent_view = self;
			variant.render();
			this.contentView.$el.find("#upfront-image-variants").append(variant.el);
		}

		/**
		 * Add new button
		 */
		var $add_new_button = $("<div class='upfront-add-image-insert-variant'>Add Image Insert Variant</div>").on("click", $.proxy(this.add_new_variant, this));
		$("#upfront-image-variants").append($add_new_button);
	}
});
var PostImageVariant = Backbone.View.extend({
	cssSelectors: {
		'.uinsert-image-wrapper': {label: l10n.variant_image_label, info: l10n.variant_image_info},
		'.wp-caption-text, .wp-caption-text p': {label: l10n.variant_caption_label, info: l10n.variant_caption_info}
	},
	tpl : _.template($(variant_tpl).find('#upfront-post-image-variant-tpl').html()),
	se_handle : '<span class="upfront-icon-control upfront-icon-control-resize-se upfront-resize-handle-se ui-resizable-handle ui-resizable-se nosortable"></span>',
	nw_handle : '<span class="upfront-icon-control upfront-icon-control-resize-nw upfront-resize-handle-nw ui-resizable-handle ui-resizable-nw nosortable"></span>',
	e_handle : '<span class="upfront-resize-handle-e ui-resizable-handle ui-resizable-e nosortable"></span>',
	w_handle : '<span class="upfront-resize-handle-w ui-resizable-handle ui-resizable-w nosortable"></span>',
	s_handle : '<span class="upfront-resize-handle-s ui-resizable-handle ui-resizable-s nosortable"></span>',
	initialize: function( options ){
		this.opts = options;
		this.listenTo(Upfront.Events, 'builder:image_variant:edit:start', this.on_other_edit);
	},
	events : {
		"click .upfront_edit_image_insert" : "start_editing",
		"click .finish_editing_image_insert" : "finish_editing",
		"click .upfront-image-variant-delete_trigger" : "remove_variant",
		"click .image-variant-edit-css": "edit_css"
	},
	render : function() {
		this.$el.html( this.tpl( this.render_model_data() ) );
		this.$wrap = this.$(".ueditor-insert-variant");
		this.$self = this.$(".ueditor-insert-variant-group");
		this.$self.prepend('<a href="#" class="upfront-icon-button upfront-icon-button-delete upfront-image-variant-delete_trigger"></a>');
		this.$image =  this.$(".ueditor-insert-variant-image");
		this.$caption = this.$(".ueditor-insert-variant-caption");
		// Change order if needed
		if ( this.model.get('image').order > this.model.get('caption').order ) {
			this.$image.insertAfter(this.$caption);
		}
		this.make_resizable();
		this.$wrap_edit = this.$(".image-variant-edit-wrap");
		this.$label = this.$(".image-variant-label");
		this.$edit_css = this.$(".image-variant-edit-css");
		// Prevent input mouseover/mouseout hack on global-event-handlers.js
		this.$label.on('mouseover mouseout', 'input', function(e){
			e.stopPropagation();
		});
		return this;
	},
	render_model_data: function () {
		var model_data = this.model.toJSON(),
			grid = Upfront.Settings.LayoutEditor.Grid,
			is_clear = function (data) {
				return ( ( _.isString(data.clear) && data.clear.toLowerCase() === 'true' ) || ( !_.isString(data.clear) && data.clear ) );
			},
			apply_classes = function (type, default_order) {
				var data = model_data[type],
					order = !_.isUndefined(data.order) ? data.order : default_order,
					other_data
				;
				data.height = data.row * grid.baseline;
				data.width_cls = grid.class + data.col;
				//data.left_cls = grid.left_margin_class + data.left;
				//if ( data.top )
				//    data.top_cls = grid.top_margin_class + data.top;
				data.clear_cls = is_clear(data) ? 'clr' : '';
				if ( type != 'group' ) {
					other_data = type == 'image' ? model_data['caption'] : model_data['image'];
					data.order_cls = 'order-' + order;
					if ( ( data.order == 0 && is_clear(other_data) ) || ( data.order == 1 && is_clear(data) ) ) {
						data.full_cls = 'is-full';
					}
					else {
						data.full_cls = '';
					}
				}
			}
		;
		apply_classes('group');
		apply_classes('image', 0);
		apply_classes('caption', 1);
		model_data.label_id = this.get_label_id();
		return model_data;
	},
	remove_variant : function(e){
		e.preventDefault();
		e.stopPropagation();
		Upfront.Content.ImageVariants.remove(this.model);
		this.remove();
	},
	start_editing : function(e){
		e.preventDefault();
		e.stopPropagation();

		// Show editing stuff
		this.$wrap_edit.show();

		// Hide edit button
		this.$(".upfront_edit_image_insert").css({
			visibility : "hidden"
		});
		//disable group's resizability
		this.$self.resizable("option", "disabled", true);

		this.$self.addClass("editing");

		// hide group's resize handles
		this.$self.find(".upfront-icon-control").hide();

		// set group's width to a round number, always ceil to give some room for child items
		this.$self.css({
			width: Math.ceil(this.$self.outerWidth()),
			maxWidth: 'none'
		});

		// explicitly set group's height
		//this.$self.css("height", this.$(".ueditor-insert-variant").height());

		this.make_items_draggable();
		this.make_items_resizable();
		this.$self.append("<div class='finish_editing_image_insert'>Finish editing image insert</div>");

		Upfront.Events.trigger('builder:image_variant:edit:start', this);
	},
	finish_editing : function( e ){
		e.preventDefault();
		e.stopPropagation();

		// Hide editing stuff
		this.$wrap_edit.hide();

		this.model.set( "label", this.$label.find('>input').val() );

		// Show edit button
		this.$(".upfront_edit_image_insert").css({
			visibility : "visible"
		});
		//enable group's resizability
		this.$self.resizable("option", "disabled", false);

		this.$self.removeClass("editing");

		// remove set width
		this.$self.css({
			width: '',
			maxWidth: ''
		});

		// Show group's resize handles
		this.$self.find(".upfront-icon-control").show();

		this.$image.draggable("option", "disabled", true);
		this.$image.resizable("option", "disabled", true);
		this.$image.find(".upfront-icon-control").hide();

		this.$caption.draggable("option", "disabled", true);
		this.$caption.resizable("option", "disabled", true);
		this.$caption.find(".upfront-icon-control").hide();

		$(e.target).remove();

		Upfront.Events.trigger('builder:image_variant:edit:stop', this);

	},
	on_other_edit: function (view) {
		if ( view == this ) return;
		this.$(".finish_editing_image_insert").trigger("click");
	},
	edit_css: function (e) {
		e.preventDefault();
		e.stopPropagation();

		Upfront.Application.cssEditor.init({
			model: this.model,
			type: 'ImageVariant',
			element_id: this.model.get('vid'),
			stylename: this.model.get('vid')
		});
	},
	make_items_draggable : function(){
		var self = this,
			ge = Upfront.Behaviors.GridEditor,
			max_col = this.model.get('group').col,
			col_size = this.$self.width()/max_col,
			min_col = 2,
			compare_col = 2,
			compare_row = 10,
			$preview,
			drop_col, drop_left, drop_top, drop_order, drop_clear,
			other_drop_col, other_drop_left, other_drop_top, other_drop_order, other_drop_clear,
			this_pos, other_pos, self_pos,
			drops = [],
			selected_drop = false,
			options = {
				revert: true,
				revertDuration: 0,
				zIndex: 100,
				containment : 'document',
				appendTo: self.$self,
				delay: 50,
				helper: 'clone',
				start : function( event, ui ){
					event.stopPropagation();
					var $this = $(this),
						$other = $this.is( self.$image ) ? self.$caption : self.$image,
						data = $this.data('ui-resizable'),
						height = Math.floor($this.outerHeight()),
						width = Math.floor($this.outerWidth()),
						offset = $this.offset(),
						other_height = Math.floor($other.outerHeight()),
						other_width = Math.floor($other.outerWidth()),
						other_offset = $other.offset(),
						self_height = Math.floor(self.$self.outerHeight()),
						self_width = Math.floor(self.$self.outerWidth()),
						self_offset = self.$self.offset();

					// Setting up position data
					this_pos = {
						width: width,
						height: height,
						top: Math.round(offset.top),
						left: Math.round(offset.left),
						bottom: Math.round(offset.top + height),
						right: Math.round(offset.left + width)
					};
					other_pos = {
						width: other_width,
						height: other_height,
						top: Math.round(other_offset.top),
						left: Math.round(other_offset.left),
						bottom: Math.round(other_offset.top + other_height),
						right: Math.round(other_offset.left + other_width)
					};
					self_pos = {
						width: self_width,
						height: self_height,
						top: Math.round(self_offset.top),
						left: Math.round(self_offset.left),
						bottom: Math.round(self_offset.top + self_height),
						right: Math.round(self_offset.left + self_width)
					};

					max_col = self.model.get('group').col;

					// Now define the possible drop position, there's 4 possible drops
					var is_on_side = ( other_pos.top < this_pos.bottom && other_pos.bottom > this_pos.top ),
						is_me
					;
					drops = [];
					// This one is on the top, take full columns
					is_me = ( this_pos.bottom <= other_pos.top );
					drops.push({
						top: self_pos.top,
						left: self_pos.left,
						right: self_pos.right,
						bottom: other_pos.top + (other_pos.height/2),
						type: 'full',
						is_me: is_me,
						is_clear: true,
						order: 0,
						priority_index: 1
					});
					// This one is on the left side
					is_me = ( is_on_side && this_pos.right <= other_pos.left );
					drops.push({
						top: is_me && this_pos.top < other_pos.top ? this_pos.top : other_pos.top,
						left: self_pos.left,
						right: other_pos.left+Math.round(other_pos.width/4),
						bottom: is_me && this_pos.bottom > other_pos.bottom ? this_pos.bottom : other_pos.bottom,
						type: 'side-before',
						is_me: is_me,
						is_clear: true,
						order: 0,
						priority_index: 0
					});
					// This one is on the right side
					is_me = ( is_on_side && this_pos.left >= other_pos.right );
					drops.push({
						top: is_me && this_pos.top < other_pos.top ? this_pos.top : other_pos.top,
						left: other_pos.right-Math.round(other_pos.width/4),
						right: self_pos.right,
						bottom: is_me && this_pos.bottom > other_pos.bottom ? this_pos.bottom : other_pos.bottom,
						type: 'side-after',
						is_me: is_me,
						is_clear: false,
						order: 1,
						priority_index: 0
					});
					// This one is on the bottom, take full columns
					is_me = ( this_pos.top >= other_pos.bottom );
					drops.push({
						top: other_pos.top + (other_pos.height/2),
						left: self_pos.left,
						right: self_pos.right,
						bottom: self_pos.bottom,
						type: 'full',
						is_me: is_me,
						is_clear: true,
						order: 1,
						priority_index: 1
					});

					// Let's hide our actual element
					$this.css('visibility', 'hidden');

					// Normalize helper
					$(ui.helper).css({
						width: this_pos.width,
						height: this_pos.height,
						marginLeft: $this.css('margin-left')
					});

					$this.resizable("option", "disabled", true);
				},
				drag : function( event, ui ){
					event.stopPropagation();

					var $this = $(this),
						$helper = $(ui.helper),
						$other = $this.is( self.$image ) ? self.$caption : self.$image,
						model = $this.is( self.$image ) ? self.model.get("image") : self.model.get("caption"),
						other_model = $this.is( self.$image ) ? self.model.get("caption") : self.model.get("image"),
						height = $helper.outerHeight(),
						width = $helper.outerWidth(),
						current_offset = $helper.offset(),
						current_left = current_offset.left,
						current_top = current_offset.top,
						current_bottom = current_top+height,
						current_right = current_left+width,
						compare_area_top = event.pageY-(compare_row*ge.baseline/2),
						compare_area_bottom = compare_area_top+(compare_row*ge.baseline),
						compare_area_left = event.pageX-(compare_col*col_size/2),
						compare_area_right = compare_area_left+(compare_col*col_size),
						group_row = self.model.get('group').row
					;

					// Setting up compare area against the drops
					compare_area_top = compare_area_top < current_top ? current_top : compare_area_top;
					compare_area_bottom = compare_area_bottom > current_bottom ? current_bottom: compare_area_bottom;
					compare_area_left = compare_area_left < current_left ? current_left : compare_area_left;
					compare_area_right = compare_area_right > current_right ? current_right : compare_area_right;

					function get_area_compared (compare) {
						var top, bottom, left, right, area;
						if ( compare_area_left >= compare.left && compare_area_left <= compare.right )
							left = compare_area_left;
						else if ( compare_area_left < compare.left )
							left = compare.left;
						if ( compare_area_right >= compare.left && compare_area_right <= compare.right )
							right = compare_area_right;
						else if ( compare_area_right > compare.right )
							right = compare.right;
						if ( compare_area_top >= compare.top && compare_area_top <= compare.bottom )
							top = compare_area_top;
						else if ( compare_area_top < compare.top )
							top = compare.top;
						if ( compare_area_bottom >= compare.top && compare_area_bottom <= compare.bottom )
							bottom = compare_area_bottom;
						else if ( compare_area_bottom > compare.bottom )
							bottom = compare.bottom;
						if ( top && bottom && left && right )
							area = (right-left+1) * (bottom-top+1);
						else
							area = 0;
						return area ? area : 0;
					}

					// Now try getting drops that's getting covered the most
					var drops_area = _.map(drops, function(each){
							var area = get_area_compared(each);
							return {
								area: area,
								drop: each
							};
						}).filter(function(each){
							if ( each !== false )
								return true;
							return false;
						}),
						max_drop = _.max(drops_area, function(each){ return each.area; });

					if ( max_drop.area > 0 ){
						var max_drops = _.filter(drops_area, function(each){ return each.area == max_drop.area; }),
							max_drops_sort = _.sortBy(max_drops, function(each, index, list){
								return each.drop.priority_index;
							}),
							drop = _.first(max_drops_sort).drop;
					}
					else {
						var drop = _.find(drops, function(each){
							return each.is_me;
						});
					}

					// Drop found, now we select it
					if ( selected_drop === false || ( max_drop.area > 0 && drop != selected_drop ) ) {
						if ( selected_drop !== false ) {
							$('.upfront-drop').removeClass('upfront-drop-use').remove();
						}
						var $drop = $('<div class="upfront-drop upfront-drop-use"></div>'),
							is_after = false
						;
						if ( drop.order == 0 && drop.type == 'full' && model.order == 0 ) {
							$drop.insertBefore($this);
						}
						else if ( drop.order == 0 ) {
							$drop.insertBefore($other);
						}
						else if ( drop.order == 1 && drop.type == 'full' && model.order == 1 ) {
							$drop.insertAfter($this);
							is_after = true;
						}
						else {
							$drop.insertAfter($other);
							is_after = true;
						}
						if ( drop.type == 'full' ) {
							$drop.css('width', drop.right-drop.left);
							if ( drop.is_me ) {
								if ( is_after ) {
									$drop.css('margin-top', this_pos.height*-1);
								}
								else {
									$drop.css('margin-bottom', this_pos.height*-1);
								}
								$drop.css('height', this_pos.height);
							}
						}
						else {
							$drop.css('height', (drop.bottom-drop.top));
							// If drop is current element, add width too
							if ( drop.is_me ){
								$drop.css('width', this_pos.width);
								if ( drop.type == 'side-before' ) $drop.css('margin-right', this_pos.width*-1);
								else $drop.css('margin-left', this_pos.width*-1);
							}
							$drop.css({
								position: 'absolute',
								top: drop.top-self_pos.top,
								left: !Upfront.Util.isRTL()
									? ( drop.type == 'side-after' ? self_pos.width : 0 )
									: ( drop.type == 'side-after' ? 0 : self_pos.width )
							});
						}
						selected_drop = drop;
					}

					// Now set the movement of our element and show it in preview
					if ( drop.is_me ){
						drop_col = Math.round( this_pos.width / col_size );
					}
					else {
						drop_col = drop.type == 'full' ? Math.round( self_pos.width / col_size ) : Math.round( self_pos.width / 2 / col_size );
					}
					drop_top = 0;
					drop_left = 0;
					drop_order = selected_drop.order;
					drop_clear = selected_drop.is_clear;

					// Also set the other element as it could be affected
					if ( drop.type == 'full' ) {
						other_drop_col = Math.round( self_pos.width / col_size );
					}
					else {
						other_drop_col = Math.round( self_pos.width / col_size ) - drop_col;
					}
					other_drop_left = 0;
					other_drop_top = 0;
					other_drop_order = ( drop_order == 1 ? 0 : 1 );
					other_drop_clear = ( selected_drop.type == 'side-before' ? false : true );
				},
				stop : function(event, ui){
					event.stopPropagation();

					var $this = $(this),
						$other = $this.is( self.$image ) ? self.$caption : self.$image,
						model = $this.is( self.$image ) ? self.model.get("image") : self.model.get("caption"),
						other_model = $this.is( self.$image ) ? self.model.get("caption") : self.model.get("image");

					Upfront.Util.grid.update_class($this, ge.grid.class, drop_col);
					//Upfront.Util.grid.update_class($this, ge.grid.left_margin_class, drop_left);
					//Upfront.Util.grid.update_class($this, ge.grid.top_margin_class, drop_top);
					if ( drop_clear ) $this.addClass('clr');
					else $this.removeClass('clr');
					if ( selected_drop.type == 'full' ) $this.addClass('is-full');
					else $this.removeClass('is-full');
					$this.removeClass('order-0 order-1').addClass('order-' + drop_order);
					model.col = drop_col;
					model.left = drop_left;
					model.top = drop_top;
					model.clear = drop_clear;
					model.order = drop_order;

					Upfront.Util.grid.update_class($other, ge.grid.class, other_drop_col);
					//Upfront.Util.grid.update_class($other, ge.grid.left_margin_class, other_drop_left);
					//Upfront.Util.grid.update_class($other, ge.grid.top_margin_class, other_drop_top);
					if ( other_drop_clear ) $other.addClass('clr');
					else $other.removeClass('clr');
					if ( selected_drop.type == 'full'  ) $other.addClass('is-full');
					else $other.removeClass('is-full');
					$other.removeClass('order-0 order-1').addClass('order-' + other_drop_order);
					other_model.col = other_drop_col;
					other_model.left = other_drop_left;
					other_model.top = other_drop_top;
					other_model.clear = other_drop_clear;
					other_model.order = other_drop_order;

					if ( drop_order == 0 ) {
						$this.insertBefore($other);
					}
					else {
						$this.insertAfter($other);
					}

					$this.css({
						position: "",
						top: "",
						left: "",
						visibility: ""
					});

					$('.upfront-drop').remove();

					$this.resizable("option", "disabled", false);
					self.render_model_data();
				}
			};

		/**
		 * Make image draggable
		 */
		if( _.isEmpty( this.$image.data("ui-draggable") ) ){
			this.$image.draggable( options );
		}else{
			this.$image.draggable( "option", "disabled", false );
		}

		/**
		 * Make caption draggable
		 */
		if( _.isEmpty( this.$caption.data("ui-draggable") ) ){
			this.$caption.draggable( options );
		}else{
			this.$caption.draggable( "option", "disabled", false );
		}
	},
	make_items_resizable : function(){
		var self = this,
			ge = Upfront.Behaviors.GridEditor,
			max_col = this.model.get('group').col,
			col_size = this.$self.width()/max_col,
			$resize,
			axis, rsz_row, rsz_col, rsz_left, rsz_top,
			other_rsz_col, other_rsz_left, other_rsz_top,
			this_pos, other_pos,
			options = {
				handles: {
					e: '.upfront-resize-handle-e',
					w: '.upfront-resize-handle-w',
					s: '.upfront-resize-handle-s'
				},
				//autoHide: true,
				delay: 50,
				minHeight: 50,
				minWidth: col_size,
				//containment: "document",
				ghost: true,
				start : function( event, ui ){
					event.stopPropagation();

					// Hide the actual element, and normalize
					$(this).css({
						visibility: "hidden",
						position: "",
						top: "",
						left: ""
					});

					var $this = $(this),
						$other = $this.is( self.$image ) ? self.$caption : self.$image,
						data = $this.data('ui-resizable'),
						height = Math.floor(ui.originalSize.height),
						width = Math.floor(ui.originalSize.width),
						offset = $this.offset(),
						other_height = Math.floor($other.outerHeight()),
						other_width = Math.floor($other.outerWidth()),
						other_offset = $other.offset()
					;

					this_pos = {
						width: width,
						height: height,
						top: Math.floor(offset.top),
						left: Math.floor(offset.left),
						bottom: Math.floor(offset.top + height),
						right: Math.floor(offset.left + width)
					};
					other_pos = {
						width: other_width,
						height: other_height,
						top: Math.floor(other_offset.top),
						left: Math.floor(other_offset.left),
						bottom: Math.floor(other_offset.top + other_height),
						right: Math.floor(other_offset.left + other_width)
					};

					max_col = self.model.get('group').col;

					$this.draggable("option", "disabled", true);

					axis = data.axis ? data.axis : 'se';

					$resize = $('<div class="upfront-resize" style="height:'+height+'px;"></div>');
					$resize.css({
						height: height,
						width: width,
						minWidth: width,
						maxWidth: width,
						position: 'absolute'
					});
					if ( axis == 'nw' || axis == 'w' ) {
						$resize.css({
							top: offset.top,
							right: $('body').width() - (width + offset.left)
						});
					}
					else {
						$resize.css({
							top: offset.top,
							left: offset.left
						});
					}
					$('body').append($resize);

					$(ui.helper).find('.ui-resizable-ghost').css('opacity', 1);
					$this.css('width', width);

					// A little hack to normalize originalPosition, to better handle nw resizing
					/*var pos = $this.position();
					$this.css({
						marginLeft: 0,
						marginTop: 0,
						position: 'absolute',
						left: pos.left,
						top: pos.top,
						minHeight: ''
					});
					data.originalPosition.left = pos.left;
					//data.originalPosition.top = pos_top;
					data._updateCache({
						left: pos.left,
						top: data.originalPosition.top //pos_top
					});
					/*if ( axis == 'nw' || axis == 'w' ) {
						$(ui.helper).css({
							left: pos_left
						});
					}*/
				},
				resize: function( event, ui ){
					event.stopPropagation();

					var $this = $(this),
						$other = $this.is( self.$image ) ? self.$caption : self.$image,
						model = $this.is( self.$image ) ? self.model.get("image") : self.model.get("caption"),
						other_model = $this.is( self.$image ) ? self.model.get("caption") : self.model.get("image"),
						current_width = ui.size.width,
						current_col = Math.round(ui.size.width/col_size),
						current_row = Upfront.Util.grid.height_to_row(ui.size.height),
						current_top = model.top + Math.round((ui.position.top-ui.originalPosition.top)/ge.baseline),
						current_left = model.left + Math.round((ui.position.left-ui.originalPosition.left)/col_size),
						rsz_max_col = ( axis == 'w' || axis == 'e' ) ? max_col - 1 : max_col,
						rsz_max_width = Math.floor(rsz_max_col * col_size),
						group_row = self.model.get('group').row,
						is_other_on_right = ( other_pos.left >= this_pos.right && other_pos.top < this_pos.bottom && other_pos.bottom > this_pos.top )
					;
					current_width = current_width > rsz_max_width ? rsz_max_width : current_width;
					rsz_top = 0;
					rsz_left = 0;
					if ( axis == 'nw' ) {
						rsz_max_row = model.top + model.row;
					}
					else {
						rsz_max_row = Math.round((group_row*ge.baseline)/ge.baseline);
					}
					rsz_col = ( current_col > rsz_max_col ? rsz_max_col : current_col );
					rsz_row = ( current_row > rsz_max_row ? rsz_max_row : current_row );

					if ( axis == 'w' || axis == 'e' ) {
						other_rsz_col = max_col - rsz_col;
					}
					else {
						other_rsz_col = 0;
					}
					other_rsz_left = 0;
					other_rsz_top = 0;

					$resize.css({
						height: rsz_row*ge.baseline,
						width: rsz_col*col_size,
						minWidth: rsz_col*col_size,
						maxWidth: rsz_col*col_size,
					});

					if(axis == 'nw') {
						$resize.css({
							top: this_pos.top,
							marginTop: this_pos.height-(rsz_row*ge.baseline)
						});
					}

					// Let's control the helper resize, don't let it overflow
					// Also fix the nw axis resize
					$(ui.helper).css({
						width: ( rsz_col >= rsz_max_col ? rsz_col*col_size : ui.size.width ),
						//marginLeft: ( axis == 'nw' || axis == 'w' ? this_pos.left - (model.left*col_size) : 0 ),
						height: ( rsz_row >= rsz_max_row ? rsz_row*ge.baseline : ui.size.height )
					});
					if ( (axis == 'w' || axis == 'e') && other_rsz_col > 0 ) {
						$other.css('width', other_pos.width + (this_pos.width-current_width));
						if ( axis == 'w' ) $other.css('margin-right', current_width-this_pos.width-1);
						else $other.css('margin-left', current_width-this_pos.width-1);
					}
					if ( axis == 'nw' && rsz_row >= rsz_max_row ) {
						$(ui.helper).css('top', this_pos.top - (model.top*ge.baseline));
					}
				},
				stop : function(event, ui){
					event.stopPropagation();

					var $this = $(this),
						$other = $this.is( self.$image ) ? self.$caption : self.$image,
						model = $this.is( self.$image ) ? self.model.get("image") : self.model.get("caption"),
						other_model = $this.is( self.$image ) ? self.model.get("caption") : self.model.get("image"),
						margin_left = ui.position.left,
						left_class_size = Math.round(margin_left / ge.col_size),
						height =  rsz_row * ge.baseline
					;

					$this.draggable("option", "disabled", false);

					Upfront.Util.grid.update_class($this, ge.grid.class, rsz_col);
					//Upfront.Util.grid.update_class($this, ge.grid.left_margin_class, rsz_left);
					//Upfront.Util.grid.update_class($this, ge.grid.top_margin_class, rsz_top);
					model.row = rsz_row;
					model.col = rsz_col;
					model.left = rsz_left;
					model.top = rsz_top;

					if ( other_rsz_col > 0 ) {
						Upfront.Util.grid.update_class($other, ge.grid.class, other_rsz_col);
					}
					//Upfront.Util.grid.update_class($other, ge.grid.left_margin_class, other_rsz_left);
					//Upfront.Util.grid.update_class($other, ge.grid.top_margin_class, other_rsz_top);
					if ( other_rsz_col > 0 ) {
						other_model.col = other_rsz_col;
					}
					other_model.left = other_rsz_left;
					other_model.top = other_rsz_top;

					$resize.remove();

					$this.css({
						height: height,
						width: "",
						top: "",
						left: "",
						marginLeft: "",
						marginTop: "",
						clear: "",
						visibility: ""
					});
					$other.css({
						width: "",
						marginLeft: "",
						marginRight: ""
					});
					self.render_model_data();
				}
			};
		/**
		 * Make image resizable
		 */


		if(_.isEmpty(  this.$image.data("ui-resizable") ) ){
			this.$image.append(this.w_handle);
			this.$image.append(this.e_handle);
			this.$image.append(this.s_handle);
			this.$image.resizable(options);
		}else{
			this.$image.find(".upfront-icon-control").show();
			this.$image.resizable("option", "disabled", false);
		}


		/**
		 * Make caption resizable
		 */

		if(_.isEmpty(  this.$caption.data("ui-resizable") ) ){
			this.$caption.append(this.w_handle);
			this.$caption.append(this.e_handle);
			this.$caption.append(this.s_handle);
			this.$caption.resizable(options);
		}else{
			this.$caption.find(".upfront-icon-control").show();
			this.$caption.resizable("option", "disabled", false);
		}

	},
	make_resizable : function(){
		var self = this,
			ge = Upfront.Behaviors.GridEditor,
			$parent = $('#upfront-image-variants'),
			content_view = this.parent_view.contentView,
			object_group_view = content_view.object_group_view,
			module_view = object_group_view ? object_group_view.parent_module_view : content_view.parent_module_view,
			module_col = module_view.model ? ge.get_class_num(module_view.model.get_property_value_by_name('class'), ge.grid.class) : 24,
			parent_col = content_view.model ? ge.get_class_num(content_view.model.get_property_value_by_name('class'), ge.grid.class) : module_col,
			padding_left = content_view.model.get_property_value_by_name('padding_left'),
			padding_right = content_view.model.get_property_value_by_name('padding_right'),
			min_col = 1,
			max_col = parent_col,
			group = this.model.get('group'),
			$content = content_view.$el.find('> .upfront-editable_entity'),
			content_padding_left = parseInt($content.css('padding-left'), 10),
			content_padding_right = parseInt($content.css('padding-right'), 10),
			$resize,
			col_size, axis, rsz_row, rsz_col, rsz_left, rsz_float
		;

		parent_col = parent_col > module_col ? module_col : parent_col;
		max_col = parent_col;

		// Get left/right indent from object_group_view
		if ( object_group_view ) {
			padding_left = object_group_view.get_preset_property('left_indent');
			padding_right = object_group_view.get_preset_property('right_indent');
		}

		padding_left = padding_left ? parseInt(padding_left) : 0;
		padding_right = padding_right ? parseInt(padding_right) : 0;
		max_col = max_col - padding_left - padding_right;
		col_size = $content.outerWidth()/parent_col;
		//col_size = ge.col_size;

		this.$wrap.attr('style',
			'margin-left: '+ ( ( padding_left * col_size * -1 ) - content_padding_left ) + 'px !important;' +
			'margin-right: ' + ( ( padding_right * col_size * -1 ) - content_padding_right ) + 'px !important;'
		); // Set style attr instead of using jQuery.css since we need !important

		if ( group.col > max_col + Math.abs(group.margin_left) + Math.abs(group.margin_right) ) {
			group.col = max_col + Math.abs(group.margin_left) + Math.abs(group.margin_right);
			Upfront.Util.grid.update_class(this.$self, ge.grid.class, group.col);
		}

		if ( group.float == 'left' ) {
			this.$self.css('margin-left', ( padding_left - Math.abs(group.margin_left) ) * col_size);
		}
		else if ( group.float == 'right' ) {
			this.$self.css('margin-right', ( padding_right - Math.abs(group.margin_right) ) * col_size);
		}
		else if ( group.float == 'none' ) {
			this.$self.css('margin-left', ( padding_left - Math.abs(group.margin_left) + Math.abs(group.left) ) * col_size);
		}

		this.$self.append(this.w_handle);
		this.$self.append(this.e_handle);
		//this.$self.append(this.s_handle);
		this.$self.resizable({
			//autoHide: true,
			delay: 50,
			handles: {
				w: '.upfront-resize-handle-w',
				e: '.upfront-resize-handle-e',
				//s: '.upfront-resize-handle-s'
			},
			minHeight: 20,
			minWidth: col_size,
			//containment: "document",
			ghost: true,
			//alsoResize: '.ueditor-insert-variant-image',
			start : function(event, ui){
				// Hide the actual element
				$(this).css({
					visibility: "hidden"
				});

				var $this = $(this),
					data = $this.data('ui-resizable'),
					height = ui.originalSize.height,
					width = ui.originalSize.width,
					offset = $this.offset(),
					floatval = self.model.get('group').float
				;

				axis = data.axis ? data.axis : 'e';
				min_col = ( !self.$image.hasClass('is-full') && !self.$caption.hasClass('is-full') ) ? 2 : 1;

				$resize = $('<div class="upfront-resize" style="height:'+height+'px;"></div>');
				$resize.css({
					height: height,
					width: width,
					minWidth: width,
					maxWidth: width,
					position: 'absolute'
				})
				if ( axis == 'nw' || axis == 'w' ) {
					$resize.css({
						top: offset.top,
						right: $('body').width() - (width + offset.left)
					});
				}
				else {
					$resize.css({
						top: offset.top,
						left: offset.left
					});
				}
				$('body').append($resize);

				$(ui.helper).find('.ui-resizable-ghost').css('opacity', 1);

				// A little hack to normalize originalPosition, to allow resizing when floated right
				var pos_left = offset.left - $content.offset().left;
				pos_left = pos_left > 0 ? pos_left : 0;
				data.originalPosition.left = pos_left;
				data._updateCache({
					left: pos_left,
					top: data.originalPosition.top
				});
				if ( axis == 'nw' || axis == 'w' )
					$(ui.helper).css({
						left: pos_left,
						marginLeft: $parent.offset().left - (padding_left*col_size)
					});
				else
					$(ui.helper).css({
						marginLeft: 0
					});
			},
			resize: function (event, ui) {
				var $this = $(this),
					current_col = Math.round(ui.size.width/col_size),
					rsz_max_col = max_col,
					floatval = self.model.get('group').float
				;
				if ( axis == 'nw' || axis == 'w' ) {
					rsz_max_col = Math.round((ui.originalPosition.left+ui.originalSize.width+content_padding_left)/col_size);
				}
				else {
					rsz_max_col = Math.round(((max_col*col_size)-ui.originalPosition.left-content_padding_left)/col_size) + padding_left + padding_right;
				}
				rsz_col = ( current_col > rsz_max_col ? rsz_max_col : current_col );
				rsz_col = ( rsz_col < min_col ? min_col : rsz_col );
				rsz_row = Upfront.Util.grid.height_to_row(ui.size.height);
				rsz_left = Math.round((ui.position.left)/col_size) - padding_left;

				if ( ( axis == 'nw' || axis == 'w' ) && rsz_left + rsz_col > rsz_max_col ) { // If rounding made the total mismatched
					rsz_left -= ( rsz_left + rsz_col ) - rsz_max_col;
				}

				if ( rsz_left <= 0 && rsz_left + rsz_col < max_col ) { //float left
					rsz_float = "left"
				}
				else if ( rsz_left > 0 && rsz_left + rsz_col >= max_col ) { // float right
					rsz_float = "right";
				}
				else {
					rsz_float = "none";
				}

				$resize.css({
					height: rsz_row*ge.baseline,
					width: rsz_col*col_size,
					minWidth: rsz_col*col_size,
					maxWidth: rsz_col*col_size,
				});
				if(axis == 'nw') {
					$resize.css({
						top: $this.offset().top,
						marginTop: $this.height()-(rsz_row*ge.baseline)
					});
				}

				// Let's control the helper resize, don't let it overflow
				// Also fix the nw axis resize
				$(ui.helper).css({
					width: ( rsz_col >= rsz_max_col || rsz_col <= min_col ? rsz_col*col_size : ui.size.width ),
					marginLeft: ( axis == 'nw' || axis == 'w' ? $parent.offset().left - (padding_left*col_size) : 0 )
				});
			},
			stop: function (event, ui) {
				var $this = $(this),
					height =  rsz_row * ge.baseline,
					rsz_margin_left = rsz_left < 0 ? rsz_left : 0,
					rsz_margin_right = rsz_left + rsz_col - max_col
				;
				rsz_margin_right = rsz_margin_right > 0 ? rsz_margin_right*-1 : 0;

				Upfront.Util.grid.update_class($this, ge.grid.class, rsz_col);
				self.model.get("group").row = rsz_row;
				self.model.get("group").col = rsz_col;
				self.model.get("group").float = rsz_float;
				self.model.get("group").margin_left = rsz_margin_left;
				self.model.get("group").margin_right = rsz_margin_right;
				if ( rsz_float == 'none' ) {
					rsz_left = rsz_left < 0 ? 0 : rsz_left;
					//Upfront.Util.grid.update_class($this, ge.grid.left_margin_class, rsz_left);
					self.model.get("group").left = rsz_left;
				}
				else {
					//Upfront.Util.grid.update_class($this, ge.grid.left_margin_class, 0);
					self.model.get("group").left = 0;
				}

				// Also update the child items
				self.update_items_size(rsz_col);

				$resize.remove();

				var margin_left = ( rsz_float == 'left' && rsz_margin_left <= 0 ? ( padding_left - Math.abs(rsz_margin_left) ) * col_size : 0 );
				if ( rsz_float == 'none' ) {
					margin_left = ( padding_left - Math.abs(rsz_margin_left) + rsz_left ) * col_size;
				}

				$this.css({
					float: rsz_float,
					height: "",
					//minHeight: height,
					width: "",
					top: "",
					left: "",
					marginLeft: ( margin_left > 0 ? margin_left  : "" ),
					marginTop: "",
					marginRight: ( rsz_float == 'right' && rsz_margin_right <= 0 ? ( padding_right - Math.abs(rsz_margin_right) ) * col_size : "" ),
					clear: "",
					visibility: ""
				});
				$this.removeClass('ueditor-insert-float-left ueditor-insert-float-right ueditor-insert-float-none').addClass('ueditor-insert-float-' + rsz_float);
				self.render_model_data();
			}
		});
	},

	update_items_size: function (parent_col) {
		var ge = Upfront.Behaviors.GridEditor,
			image_model = this.model.get('image'),
			caption_model = this.model.get('caption'),
			image_col = parent_col,
			caption_col = parent_col
		;
		if ( !this.$image.hasClass('is-full') && !this.$caption.hasClass('is-full') ) {
			// Image and caption is side-by-side, let's use the same ratio and update the columns
			image_col = Math.ceil( image_model.col / (image_model.col+caption_model.col) * parent_col );
			if ( image_col == parent_col ) image_col -= 1; // Special case when image col is the same as parent, leave 1 col for caption
			caption_col = parent_col - image_col;
		}
		if ( image_col != image_model.col ) {
			Upfront.Util.grid.update_class(this.$image, ge.grid.class, image_col);
			image_model.col = image_col;
		}
		if ( caption_col != caption_model.col ) {
			Upfront.Util.grid.update_class(this.$caption, ge.grid.class, caption_col);
			caption_model.col = caption_col;
		}
	},

	get_label_id: function () {
		var model = this.model.toJSON();
		return model.label && model.label.trim() !== "" ? "ueditor-image-style-" +  model.label.toLowerCase().trim().replace(/\s/g, "-") : model.vid;
	}
});

		return {
			PostImageVariant: PostImageVariant,
			PostImageVariants: PostImageVariants
		};
}); //End require
