define([
  'underscore', 'jquery', 'text!' + Upfront.themeExporter.root + 'templates/image_variants.html'
  //'underscore', 'jquery', 'text!templates/image_variants.html'
], function(_, $, variant_tpl) {

var PostLayoutManager = function(){
	this.init();
};

var PostImageVariants =  Backbone.View.extend({
    initialize: function( options ) {
        this.contentView = options.contentView;
        this.populate_style_content();
    },
    add_new_variant : function(e){
        e.preventDefault();
        e.stopPropagation();
        var model = new Upfront.Models.ImageVariant();
        model.set("vid", Upfront.Util.get_unique_id("variant"));
        var variant = new PostImageVariant({ model : model});
        variant.parent_view = this;
        variant.render();
        variant.$el.hide();
        /**
         * Add it after the last variant
         */
        $(".ueditor-insert-variant").last().parent().after( variant.el );
        variant.$el.fadeIn();
        Upfront.Content.ImageVariants.add(model);
    },
    populate_style_content : function() {
        var self = this;
        var $page = $('#page');

        $page.find('.upfront-module').draggable('disable').resizable('disable');
        $page.find('.upfront-region-edit-trigger').hide();

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
    tpl : _.template($(variant_tpl).find('#upfront-post-image-variant-tpl').html()),
    se_handle : '<span class="upfront-icon-control upfront-icon-control-resize-se upfront-resize-handle-se ui-resizable-handle ui-resizable-se nosortable"></span>',
    nw_handle : '<span class="upfront-icon-control upfront-icon-control-resize-nw upfront-resize-handle-nw ui-resizable-handle ui-resizable-nw nosortable"></span>',
    initialize: function( options ){
        this.opts = options;
        Upfront.Events.on("post:layout:style:stop", function(){

        });
    },
    events : {
        "click .upfront_edit_image_insert" : "start_editing",
        "click .finish_editing_image_insert" : "finish_editing",
        "click .upfront-image-variant-delete_trigger" : "remove_variant"
    },
    render : function() {
        this.$el.html( this.tpl( this.render_model_data() ) );
        this.$self = this.$(".ueditor-insert-variant-group");
        this.$self.prepend('<a href="#" class="upfront-icon-button upfront-icon-button-delete upfront-image-variant-delete_trigger"></a>');
        this.$image =  this.$(".ueditor-insert-variant-image");
        this.$caption = this.$(".ueditor-insert-variant-caption");
        // Change order if needed
        if ( this.model.get('image').order > this.model.get('caption').order )
        	this.$image.insertAfter(this.$caption);
        this.make_resizable();
        this.$label = this.$(".image-variant-label");
        return this;
    },
    render_model_data: function () {
    	var model_data = this.model.toJSON(),
    		grid = Upfront.Settings.LayoutEditor.Grid,
    		apply_classes = function (data) {
    			data.height = data.row * grid.baseline;
    			data.width_cls = grid.class + data.col;
    			data.left_cls = grid.left_margin_class + data.left;
    			if ( data.top )
	    			data.top_cls = grid.top_margin_class + data.top;
	    		data.clear_cls = ( ( _.isString(data.clear) && data.clear.toLowerCase() === 'true' ) || ( !_.isString(data.clear) && data.clear ) ) ? 'clr' : '';
    		};
    	apply_classes( model_data.group );
    	apply_classes( model_data.image );
    	apply_classes( model_data.caption );
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

        // Show title input
        this.$label.show();

        // Hide edit button
        this.$(".upfront_edit_image_insert").css({
            visibility : "hidden"
        });
        //disable group's resizability
        this.$self.resizable("option", "disabled", true);

        this.$self.addClass("editing");

        // hide group's resize handles
        this.$self.find(".upfront-icon-control").hide();

        // explicitly set group's height
        //this.$self.css("height", this.$(".ueditor-insert-variant").height());

        this.make_items_draggable();
        this.make_items_resizable();
        this.$self.append("<div class='finish_editing_image_insert'>Finish editing image insert</div>")
    },
    finish_editing : function( e ){
        e.preventDefault();
        e.stopPropagation();

        //Hide title
        this.$label.hide();

        this.model.set( "label", this.$label.val() );

        // Show edit button
        this.$(".upfront_edit_image_insert").css({
            visibility : "visible"
        });
        //enable group's resizability
        this.$self.resizable("option", "disabled", false);

        this.$self.removeClass("editing");

        // Show group's resize handles
        this.$self.find(".upfront-icon-control").show();

        this.$image.draggable("option", "disabled", true);
        this.$image.resizable("option", "disabled", true);
        this.$image.find(".upfront-icon-control").hide();

        this.$caption.draggable("option", "disabled", true);
        this.$caption.resizable("option", "disabled", true);
        this.$caption.find(".upfront-icon-control").hide();

        $(e.target).remove();

    },
    make_items_draggable : function(){
        var self = this,
            ge = Upfront.Behaviors.GridEditor,
            max_col = this.model.get('group').col,
            col_size = this.$self.width()/max_col,
            min_col = 2,
            compare_col = 5,
            compare_row = 20,
            $preview,
            drop_col, drop_left, drop_top, drop_order, drop_clear,
            other_drop_left, other_drop_top, other_drop_order, other_drop_clear,
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
	            		height = $this.outerHeight(),
	            		width = $this.outerWidth(),
	            		offset = $this.offset(),
	            		other_height = $other.outerHeight(),
	            		other_width = $other.outerWidth(),
	            		other_offset = $other.offset(),
	            		self_height = self.$self.outerHeight(),
	            		self_width = self.$self.outerWidth(),
	            		self_offset = self.$self.offset();
	            	
	            	// Setting up position data
	            	this_pos = {
	            		width: width,
	            		height: height,
	            		top: offset.top,
	            		left: offset.left,
	            		bottom: offset.top + height,
	            		right: offset.left + width
	            	};
	            	other_pos = {
	            		width: other_width,
	            		height: other_height,
	            		top: other_offset.top,
	            		left: other_offset.left,
	            		bottom: other_offset.top + other_height,
	            		right: other_offset.left + other_width
	            	};
	            	self_pos = {
	            		width: self_width,
	            		height: self_height,
	            		top: self_offset.top,
	            		left: self_offset.left,
	            		bottom: self_offset.top + self_height,
	            		right: self_offset.left + self_width
	            	};
	            	
	            	max_col = self.model.get('group').col;
	            	
	            	// Now define the possible drop position, there's 4 possible drops
	            	var is_on_side = ( other_pos.top < this_pos.bottom && other_pos.bottom > this_pos.top ),
	            		is_me;
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
	            	// This one is on the left side, only available if columns is sufficient (see min_col)
	            	if ( other_pos.left-self_pos.left >= min_col ) {
	            		is_me = ( is_on_side && this_pos.right <= other_pos.left );
	            		drops.push({
	            			top: is_me && this_pos.top < other_pos.top ? this_pos.top : other_pos.top,
	            			left: self_pos.left,
	            			right: other_pos.left,
	            			bottom: is_me && this_pos.bottom > other_pos.bottom ? this_pos.bottom : other_pos.bottom,
	            			type: 'side-before',
	            			is_me: is_me,
	            			is_clear: true,
	            			order: 0,
	            			priority_index: 0
	            		});
	            	}
	            	// This one is on the right side, only available if columns is sufficient (see min_col)
	            	if ( self_pos.right-other_pos.right >= min_col ) {
	            		is_me = ( is_on_side && this_pos.left >= other_pos.right );
	            		drops.push({
	            			top: is_me && this_pos.top < other_pos.top ? this_pos.top : other_pos.top,
	            			left: other_pos.right,
	            			right: self_pos.right,
	            			bottom: is_me && this_pos.bottom > other_pos.bottom ? this_pos.bottom : other_pos.bottom,
	            			type: 'side-after',
	            			is_me: is_me,
	            			is_clear: false,
	            			order: 1,
	            			priority_index: 0
	            		});
	            	}
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
	            	
	            	// Initiate the preview
	            	$preview = $('<div id="upfront-drop-preview" style="top:' + this_pos.top + 'px; left: ' + this_pos.left + 'px;"></div>');
	            	$('body').append($preview);
	            	
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
						group_row = self.model.get('group').row;
					
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
						if ( selected_drop !== false )
							$('.upfront-drop').removeClass('upfront-drop-use').animate({height: 0}, 300, function(){ $(this).remove(); });
						var $drop = $('<div class="upfront-drop upfront-drop-use"></div>');
						if ( drop.order == 0 && drop.type == 'full' && model.order == 0 )
							$drop.insertBefore($this);
						else if ( drop.order == 0 )
							$drop.insertBefore($other);
						else if ( drop.order == 1 && drop.type == 'full' && model.order == 1 )
							$drop.insertAfter($this);
						else
							$drop.insertAfter($other);
						if ( drop.type == 'full' && !drop.is_me )
							$drop.css('width', drop.right-drop.left).animate({height: this_pos.height}, 300, 'swing');
						selected_drop = drop;
					}
					
					// Now set the movement of our element and show it in preview
					var drop_width = ( width < selected_drop.right-selected_drop.left ? width : selected_drop.right-selected_drop.left ),
						max_drop_top = group_row - Upfront.Util.grid.height_to_row(height);
					drop_col = Math.round( drop_width / col_size );
					if ( selected_drop.type == 'full' && selected_drop.order == 1 )
						drop_top = Upfront.Util.grid.height_to_row( current_top < other_pos.bottom ? 0 : current_top - other_pos.bottom );
					else
						drop_top = Upfront.Util.grid.height_to_row( current_top < selected_drop.top ? 0 : current_top - selected_drop.top );
					drop_top = drop_top > max_drop_top ? max_drop_top : drop_top;
					drop_left = Math.round( ( current_right < selected_drop.right ? current_left-selected_drop.left : selected_drop.right-selected_drop.left-drop_width) / col_size );
					drop_left = drop_left < 0 ? 0 : drop_left;
					drop_order = selected_drop.order;
					drop_clear = selected_drop.is_clear;
					
					// Also set the other element as it could be affected
					other_drop_left = ( selected_drop.type == 'side-before' ? other_model.left - ( drop_left+drop_col ) : other_model.left );
					if ( selected_drop.type != 'side-before' && !other_model.clear && other_model.order > model.order )
						other_drop_left += model.left + model.col;
					other_drop_top = other_model.top;
					other_drop_order = ( drop_order == 1 ? 0 : 1 );
					other_drop_clear = ( selected_drop.type == 'side-before' ? false : true );
					
					// Set the preview position
					var ref_top = ( selected_drop.type == 'full' && selected_drop.order == 1 ? other_pos.bottom : selected_drop.top ),
						ref_left = selected_drop.left;
					$preview.css({
						top: ref_top + ( drop_top*ge.baseline ),
						left: ref_left + ( drop_left*col_size ),
						width: drop_col*col_size,
						height: this_pos.height
					});
                },
                stop : function(event, ui){
                    event.stopPropagation();
                   
                  	var $this = $(this),
	                  	$other = $this.is( self.$image ) ? self.$caption : self.$image,
                    	model = $this.is( self.$image ) ? self.model.get("image") : self.model.get("caption"),
                    	other_model = $this.is( self.$image ) ? self.model.get("caption") : self.model.get("image");
                    
	                Upfront.Util.grid.update_class($this, ge.grid.class, drop_col);
	                Upfront.Util.grid.update_class($this, ge.grid.left_margin_class, drop_left);
	                Upfront.Util.grid.update_class($this, ge.grid.top_margin_class, drop_top);
	                if ( drop_clear && !$this.hasClass('clr') )
	                	$this.addClass('clr');
	                else
	                	$this.removeClass('clr');
	                model.col = drop_col;
	                model.left = drop_left;
	                model.top = drop_top;
	                model.clear = drop_clear;
	                model.order = drop_order;
	                
	                Upfront.Util.grid.update_class($other, ge.grid.left_margin_class, other_drop_left);
	                Upfront.Util.grid.update_class($other, ge.grid.top_margin_class, other_drop_top);
	                if ( other_drop_clear && !$other.hasClass('clr') )
	                	$other.addClass('clr');
	                else
	                	$other.removeClass('clr');
	                other_model.left = other_drop_left;
	                other_model.top = other_drop_top;
	                other_model.clear = other_drop_clear;
	                other_model.order = other_drop_order;
	                
	                if ( drop_order == 0 )
	                	$this.insertBefore($other);
	                else
	                	$this.insertAfter($other);
                  	
                  	$this.css({
                  		position: "",
                  		top: "",
                  		left: "",
                  		visibility: ""
                  	});
                  	
                  	$preview.remove();
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
            axis, rsz_row, rsz_col, rsz_left, rsz_top, other_rsz_left, other_rsz_top,
            this_pos, other_pos,
            options = {
                handles: {
                    nw: '.upfront-resize-handle-nw',
                    se: '.upfront-resize-handle-se'
                },
                //autoHide: true,
                delay: 50,
                minHeight: 50,
                minWidth: col_size,
                containment: "document",
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
	            		height = ui.originalSize.height,
	            		width = ui.originalSize.width,
	            		offset = $this.offset(),
	            		other_height = $other.height(),
	            		other_width = $other.width(),
	            		other_offset = $other.offset();
	            	this_pos = {
	            		width: width,
	            		height: height,
	            		top: offset.top,
	            		left: offset.left,
	            		bottom: offset.top + height,
	            		right: offset.left + width
	            	};
	            	other_pos = {
	            		width: other_width,
	            		height: other_height,
	            		top: other_offset.top,
	            		left: other_offset.left,
	            		bottom: other_offset.top + other_height,
	            		right: other_offset.left + other_width
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
					})
					if ( axis == 'nw' ) {
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
					
	                // A little hack to normalize originalPosition, to better handle nw resizing
	                var pos_left = offset.left - self.$self.offset().left;
	                if ( other_pos.right <= this_pos.left && other_pos.top < this_pos.bottom && other_pos.bottom > this_pos.top )
	                	pos_left -= other_pos.right - self.$self.offset().left;
	                data.originalPosition.left = pos_left;
	                //data.originalPosition.top = pos_top;
					data._updateCache({
						left: pos_left,
						top: data.originalPosition.top //pos_top
					});
					if ( axis == 'nw' ) {
						$(ui.helper).css({
							left: pos_left
						});
					}
                },
                resize: function( event, ui ){
                    event.stopPropagation();
                    
                    var $this = $(this),
                    	model = $this.is( self.$image ) ? self.model.get("image") : self.model.get("caption"),
                    	other_model = $this.is( self.$image ) ? self.model.get("caption") : self.model.get("image"),
                    	current_col = Math.round(ui.size.width/col_size),
                    	current_row = Upfront.Util.grid.height_to_row(ui.size.height),
                    	current_top = model.top + Math.round((ui.position.top-ui.originalPosition.top)/ge.baseline);
                    	current_left = model.left + Math.round((ui.position.left-ui.originalPosition.left)/col_size);
	            		rsz_max_col = max_col,
	            		group_row = self.model.get('group').row,
	            		is_other_on_right = ( other_pos.left >= this_pos.right && other_pos.top < this_pos.bottom && other_pos.bottom > this_pos.top );
	            	if ( axis == 'nw' ) {
	            		rsz_top = current_top > 0 ? current_top : 0;
	            		rsz_left = current_left > 0 ? current_left : 0;
	            		rsz_max_col = model.left + model.col;
	            		rsz_max_row = model.top + model.row;
	            	}
	            	else {
	            		rsz_top = model.top;
	            		rsz_left = model.left;
	            		if ( is_other_on_right )
	            			rsz_max_col = Math.round(((model.col*col_size)+(other_pos.left-this_pos.right))/col_size);
	            		else
		            		rsz_max_col = Math.round(((max_col*col_size)-($this.offset().left-self.$self.offset().left))/col_size);
	            		rsz_max_row = Math.round((group_row*ge.baseline)/ge.baseline);
	            	}
	            	rsz_col = ( current_col > rsz_max_col ? rsz_max_col : current_col );
	            	rsz_row = ( current_row > rsz_max_row ? rsz_max_row : current_row );
	            	
	            	other_rsz_left = ( is_other_on_right && axis == 'se' ? other_model.left - (rsz_col-model.col) : other_model.left );
	            	other_rsz_top = other_model.top;
	            	
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
						marginLeft: ( axis == 'nw' ? this_pos.left - (model.left*col_size) : 0 ),
						height: ( rsz_row >= rsz_max_row ? rsz_row*ge.baseline : ui.size.height )
					});
					if ( axis == 'nw' && rsz_row >= rsz_max_row )
						$(ui.helper).css('top', this_pos.top - (model.top*ge.baseline));
                },
                stop : function(event, ui){
                    event.stopPropagation();
                    
                    var $this = $(this),
                    	$other = $this.is( self.$image ) ? self.$caption : self.$image,
	                    model = $this.is( self.$image ) ? self.model.get("image") : self.model.get("caption"),
	                    other_model = $this.is( self.$image ) ? self.model.get("caption") : self.model.get("image"),
	                    margin_left = ui.position.left,
	                    left_class_size = Math.round(margin_left / ge.col_size),
	                    height =  rsz_row * ge.baseline;
	                    
                    $this.draggable("option", "disabled", false);
	
	                Upfront.Util.grid.update_class($this, ge.grid.class, rsz_col);
	                Upfront.Util.grid.update_class($this, ge.grid.left_margin_class, rsz_left);
	                Upfront.Util.grid.update_class($this, ge.grid.top_margin_class, rsz_top);
	                model.row = rsz_row;
	                model.col = rsz_col;
	                model.left = rsz_left;
	                model.top = rsz_top;
	                
	                Upfront.Util.grid.update_class($other, ge.grid.left_margin_class, other_rsz_left);
	                Upfront.Util.grid.update_class($other, ge.grid.top_margin_class, other_rsz_top);
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
	                self.render_model_data();
                }
            };
        /**
         * Make image resizable
         */


        if(_.isEmpty(  this.$image.data("ui-resizable") ) ){
            this.$image.append(this.nw_handle);
            this.$image.append(this.se_handle);
            this.$image.resizable(options);
        }else{
            this.$image.find(".upfront-icon-control").show();
            this.$image.resizable("option", "disabled", false);
        }


        /**
         * Make caption resizable
         */

        if(_.isEmpty(  this.$caption.data("ui-resizable") ) ){
            this.$caption.append(this.nw_handle);
            this.$caption.append(this.se_handle);
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
            parent_col = ge.get_class_num(content_view.parent_module_view.model.get_property_value_by_name('class'), ge.grid.class),
            padding_left = content_view.model.get_property_value_by_name('padding_left'),
            padding_right = content_view.model.get_property_value_by_name('padding_right'),
            max_col = parent_col,
            group = this.model.get('group'),
            $resize,
            col_size, axis, rsz_row, rsz_col, rsz_left, rsz_float;
            
        padding_left = padding_left ? parseInt(padding_left) : 0;
        padding_right = padding_right ? parseInt(padding_right) : 0;
        max_col = max_col - padding_left - padding_right;
        col_size = $parent.width()/max_col;

        if ( group.col > max_col + Math.abs(group.margin_left) + Math.abs(group.margin_right) ) {
        	group.col = max_col + Math.abs(group.margin_left) + Math.abs(group.margin_right);
        	Upfront.Util.grid.update_class(this.$self, ge.grid.class, group.col);
        }
        
        if ( group.float == 'left' && padding_left > 0 )
        	this.$self.css('margin-left', ( padding_left - Math.abs(group.margin_left) ) * col_size);
        else if ( group.float == 'right' && padding_right > 0 )
        	this.$self.css('margin-right', ( padding_right - Math.abs(group.margin_right) ) * col_size);
        else if ( group.float == 'none' && padding_left > 0 )
        	this.$self.css('margin-left', ( padding_left - Math.abs(group.margin_left) + Math.abs(group.left) ) * col_size);

        this.$self.append(this.nw_handle);
        this.$self.append(this.se_handle);
        this.$self.resizable({
            //autoHide: true,
            delay: 50,
            handles: {
                nw: '.upfront-resize-handle-nw',
                se: '.upfront-resize-handle-se'
            },
            minHeight: 50,
            minWidth: 45,
            containment: "document",
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
            		floatval = self.model.get('group').float;
				
				axis = data.axis ? data.axis : 'se';
				
				$resize = $('<div class="upfront-resize" style="height:'+height+'px;"></div>');
				$resize.css({
					height: height,
					width: width,
					minWidth: width,
					maxWidth: width,
					position: 'absolute'
				})
				if ( axis == 'nw' ) {
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
                var pos_left = offset.left - $parent.offset().left + (padding_left*col_size);
                pos_left = pos_left > 0 ? pos_left : 0;
                data.originalPosition.left = pos_left;
				data._updateCache({
					left: pos_left,
					top: data.originalPosition.top
				});
				if ( axis == 'nw' )
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
            		floatval = self.model.get('group').float;
            	if ( axis == 'nw' )
            		rsz_max_col = Math.round((ui.originalPosition.left+ui.originalSize.width)/col_size);
            	else
            		rsz_max_col = Math.round(((max_col*col_size)-ui.originalPosition.left)/col_size) + padding_left + padding_right;
            	rsz_col = ( current_col > rsz_max_col ? rsz_max_col : current_col );
            	rsz_row = Upfront.Util.grid.height_to_row(ui.size.height);
            	rsz_left = Math.round(ui.position.left/col_size) - padding_left;
            	
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
					width: ( rsz_col >= rsz_max_col ? rsz_col*col_size : ui.size.width ),
					marginLeft: ( axis == 'nw' ? $parent.offset().left - (padding_left*col_size) : 0 )
				});
            },
            stop: function (event, ui) {
                var $this = $(this),
                    height =  rsz_row * ge.baseline,
                    rsz_margin_left = rsz_left < 0 ? rsz_left : 0,
                    rsz_margin_right = rsz_left + rsz_col - max_col;
                rsz_margin_right = rsz_margin_right > 0 ? rsz_margin_right*-1 : 0;

                Upfront.Util.grid.update_class($this, ge.grid.class, rsz_col);
                self.model.get("group").row = rsz_row;
                self.model.get("group").col = rsz_col;
                self.model.get("group").float = rsz_float;
                self.model.get("group").margin_left = rsz_margin_left;
                self.model.get("group").margin_right = rsz_margin_right;
                if ( rsz_float == 'none' ) {
                	rsz_left = rsz_left < 0 ? 0 : rsz_left;
	                Upfront.Util.grid.update_class($this, ge.grid.left_margin_class, rsz_left);
	                self.model.get("group").left = rsz_left;
	            }
	            else {
	            	Upfront.Util.grid.update_class($this, ge.grid.left_margin_class, 0);
	                self.model.get("group").left = 0;
	           	}
                
                $resize.remove();
                
                var margin_left = ( rsz_float == 'left' && rsz_margin_left <= 0 ? ( padding_left - Math.abs(rsz_margin_left) ) * col_size : 0 );
                if ( rsz_float == 'none' )
					margin_left = ( padding_left - Math.abs(rsz_margin_left) + rsz_left ) * col_size;

                $this.css({
                	float: rsz_float,
                	height: "",
                    minHeight: height,
                    width: "",
                    top: "",
                    left: "",
                    marginLeft: ( margin_left > 0 ? margin_left  : "" ),
                    marginTop: "",
                    marginRight: ( rsz_float == 'right' && rsz_margin_right <= 0 ? ( padding_right - Math.abs(rsz_margin_right) ) * col_size : "" ),
                    clear: "",
                    visibility: ""
                });
                self.render_model_data();
            }
        });
    },
    update_class :  function ($el, class_name, class_size) {
        var rx = new RegExp('\\b' + class_name + '\\d+');
        if ( ! $el.hasClass( class_name + class_size) ){
            if ( $el.attr('class').match(rx) )
                $el.attr('class', $el.attr('class').replace(rx, class_name + class_size));
            else
                $el.addClass( class_name + class_size );
        }
    }
});


PostLayoutManager.prototype = {
	init: function(){
		var me = this;
		if(!Upfront.Events.on){
            return setTimeout(function(){
                me.init();
                },50);
        }

		Upfront.Events.on('post:layout:sidebarcommands', _.bind(this.addExportCommand, this));
		Upfront.Events.on('command:layout:export_postlayout', _.bind(this.exportPostLayout, this));
		Upfront.Events.on('post:layout:partrendered', this.setTestContent);
		Upfront.Events.on('post:layout:style:cancel', this.cancelContentStyle);
        Upfront.Events.on('post:parttemplates:edit', _.bind(this.addTemplateExportButton, this));
	},

	addExportCommand: function(){
		var commands = Upfront.Application.sidebar.sidebar_commands.control.commands,
			wrapped = commands._wrapped,
			Command = Upfront.Views.Editor.Command.extend({
				className: "command-export",
				render: function (){
					this.$el.text('Export');
				},
				on_click: function () {
					Upfront.Events.trigger("command:layout:export_postlayout");
				}
			})
		;

		if(wrapped[wrapped.length - 2].className != 'command-export')
			wrapped.splice(wrapped.length - 1, 0, new Command());

	},
    addTemplateExportButton: function(){
        var me = this,
            exportButton = $('<a href="#" class="upfront-export-postpart">Export</a>'),
            editorBody = $('#upfront_code-editor').find('.upfront-css-body')
        ;

        if(!editorBody.find('.upfront-export-postpart').length)
            editorBody.append(exportButton);
            exportButton.on('click', function(e){
                e.preventDefault();
                me.exportPartTemplate();
            });

        console.log('Export button');

    },
	exportPostLayout: function(){
        var self = this,
            editor = Upfront.Application.PostLayoutEditor,
            saveDialog = new Upfront.Views.Editor.SaveDialog({
                question: 'Do you wish to export the post layout just for this post or apply it to all posts?',
                thisPostButton: 'This post only',
                allPostsButton: 'All posts of this type'
            });
        saveDialog.render();
        saveDialog.on('closed', function(){
                saveDialog.remove();
                saveDialog = false;
        });
        saveDialog.on('save', function(type) {
            var elementType = editor.postView.property('type');
            var specificity,
                post_type = editor.postView.editor.post.get('post_type'),
                elementSlug = elementType == 'ThisPostModel' ? 'single' : 'archive',
                loading = new Upfront.Views.Editor.Loading({
                    loading: "Saving post layout...",
                    done: "Thank you for waiting",
                    fixed: false
                })
            ;
            if(elementSlug == 'single')
                specificity = type == 'this-post' ? editor.postView.postId : editor.postView.editor.post.get('post_type');
            else
                specificity = type == 'this-post' ? editor.postView.property('element_id').replace('uposts-object-','') : editor.postView.property('post_type');

            var layoutData = {
                postLayout: editor.exportPostLayout(),
                partOptions: editor.postView.partOptions || {}
            };

            loading.render();
            saveDialog.$('#upfront-save-dialog').append(loading.$el);

            Upfront.Util.post({
                action: 'upfront_thx-export-post-layout',
                layoutData: layoutData,
                params: {
                    specificity : specificity,
                    type        : elementSlug
                }
            }).done(function (response) {
                loading.done();
                var message = "<h4>Layout successfully exported in the following file:</h4>";
                message += response.file;
                Upfront.Views.Editor.notify( message );
                saveDialog.close();
                editor.postView.postLayout = layoutData.postLayout;
                editor.postView.render();
                Upfront.Application.start(Upfront.Application.mode.last);

            });
        });
	},
    exportPartTemplate: function(){
        console.log('Export!');

        var self = this,
            editor = Upfront.Application.PostLayoutEditor.templateEditor,
            saveDialog = new Upfront.Views.Editor.SaveDialog({
                question: 'Do you wish to export the template just for this post or all the post of this type?',
                thisPostButton: 'This post only',
                allPostsButton: 'All posts of this type'
            })
        ;

        saveDialog.render();
        saveDialog.on('closed', function(){
            saveDialog.remove();
            saveDialog = false;
        });

        saveDialog.on('save', function(type) {
            var me = this,
                tpl = editor.ace.getValue(),
                postView = Upfront.Application.PostLayoutEditor.postView,
                postPart = editor.postPart,
                element = postView.property('type'),
                id
            ;

            if(type == 'this-post'){
                if(element == 'UpostsModel')
                    id = postView.property('element_id').replace('uposts-object-', '');
                else
                    id = postView.editor.postId;
            }
            else
                id = postView.editor.post.get('post_type');

            Upfront.Util.post({
                    action: 'upfront_thx-export-part-template',
                    part: postPart,
                    tpl: tpl,
                    type: element,
                    id: id
                })
                .done(function(response){
                    postView.partTemplates[editor.postPart] = tpl;
                    postView.model.trigger('template:' + editor.postPart);
                    editor.close();
                    saveDialog.close();
                    Upfront.Views.Editor.notify("Part template exported in file " + response.filename);
                })
            ;

        });
    },

	setTestContent: function(view){
        var self = this;
		if(view.postPart != 'contents')
			return;

		view.$('.upfront-object-content .post_content').html(Upfront.data.exporter.testContent);
        $(".sidebar-commands-theme .command-cancel").hide();
        /**
         * Start content styler on click
         */
        view.$('.upfront-object-content').find(".upfront_edit_content_style").on("click", function(e){
            $(".sidebar-commands-theme .command-cancel").show();
            view.$('.upfront-object-content .post_content').html(Upfront.data.exporter.styledTestContent);
            view.$('.upfront-object').addClass("upfront-editing-content-style");
            view.$('.upfront-object-content').closest(".upfront-object-view").addClass("upfront-disable-surroundings");
            new PostImageVariants({
                contentView : view
            });
        });
	},
    cancelContentStyle: function(){
        $(".sidebar-commands-theme .command-cancel").hide();
        $('.upfront-output-PostPart_contents .post_content').html(Upfront.data.exporter.testContent);
    }
};

return new PostLayoutManager();

});