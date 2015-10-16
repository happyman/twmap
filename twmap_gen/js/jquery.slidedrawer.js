/*
 * jQuery Slide Drawer
 * Examples and documentation at: http://www.icastwork.com
 * Copyright (c) 2013 Isaac Castillo
 * Version: 0.1.1 (23-MAR-2013)
 * Licensed under the MIT license. https://github.com/icemancast/jquery-slide-drawer#license
 * Requires: jQuery v1.7.1 or later.
*/

;(function ($) {
	
	var drawer = {
		
		init: function ( options, div ) {
			
			// Set 			
			if(options.showDrawer == true && options.slideTimeout == true)
			{
				setTimeout(function() {
					drawer.slide(div, options.drawerHiddenHeight, options.slideSpeed);
				}, options.slideTimeoutCount);
			} 
			else if(options.showDrawer == 'slide')
			{
				// Set drawer hidden with slide effect
				drawer.slide(div, options.drawerHiddenHeight, options.slideSpeed);
			}
			else if(options.showDrawer == false)
			{
				// Set drawer to hide
				drawer.hide(options, div);
			}

			// Toggle drawer when clicked
			$('.clickme').on('click', function(){
				drawer.toggle(options, div);
			});
			
		},
		
		//Toggle function
		toggle: function(options, div) {
			($(div).height()+options.borderHeight === options.drawerHeight) ? drawer.slide( div, options.drawerHiddenHeight, options.slideSpeed ) : drawer.slide( div, options.drawerHeight-options.borderHeight, options.slideSpeed );
		},
		
		// Slide animation function
		slide: function( div, height, speed ) {
			$(div).animate({
				'height': height
			}, speed );
		},

		hide: function(options, div) {
			$(div).css('height', options.drawerHiddenHeight);
		}
	};

	// Function wrapper
  $.fn.slideDrawer = function ( options ) {
	
		var drawerContent = this.children('.drawer-content'), /* Content height of drawer */
			borderHeight = parseInt(drawerContent.css('border-top-width')); /* Border height of content */
	
		var drawerHeight = this.height() + borderHeight; /* Total drawer height + border height */
		var drawerContentHeight = drawerContent.height() - borderHeight; /* Total drawer content height minus border top */
		var drawerHiddenHeight = drawerHeight - drawerContentHeight; /* How much to hide the drawer, total height minus content height */
  
	  var defaults = {
			showDrawer: 'slide', /* Drawer hidden on load by default, options (true, false, slide) */
			slideSpeed: 700, /* Slide drawer speed 3 secs by default */
			slideTimeout: true, /* Sets time out if set to true showDrawer false will be ignored */
			slideTimeoutCount: 5000, /* How long to wait before sliding drawer */
			drawerContentHeight: drawerContentHeight, /* Div content height no including tab or border */
			drawerHeight: drawerHeight, /* Full div height */
			drawerHiddenHeight: drawerHiddenHeight, /* Height of div when hidden full height minus content height */
			borderHeight: borderHeight /* border height if set in css you cann overwrite but best just leave alone */
	  };
				
		/* Overwrite defaults */
		var options = $.extend(defaults, options);
		
		return this.each(function() {
			
			drawer.init(options, this);
    	
		});		
	};
	
})(jQuery);