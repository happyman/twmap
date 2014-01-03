/***
 @title:
 Fixed Center

 @version:
 1.4

 @author:
 David Tang

 @url
 www.david-tang.net

 @copyright:
 2010 David Tang

 @requires:
 jquery

 @does:
 This plugin centers an element on the page using fixed positioning and keeps the element centered
 if you scroll horizontally or vertically.

 @howto:
 jQuery('#my-element').fixedCenter(); would center the element with ID 'my-element' using absolute positioning

 */
(function($) {
		jQuery.fn.fixedCenter = function(){
			return this.each(function(){
					var element = $(this),
					    win = $(window);

					centerElement();

					win.bind('resize',function(){
							centerElement();
					});

					function centerElement(){
						var elementWidth, elementHeight, windowWidth, windowHeight, X2, Y2;
						elementWidth = element.outerWidth(true);
						elementHeight = element.outerHeight(true);
						windowWidth = win.width();
						windowHeight = win.height();
						X2 = (windowWidth/2 - elementWidth/2) + "px";
						Y2 = (windowHeight/2 - elementHeight/2) + "px";
						jQuery(element).css({
								'left':X2,
								'top':Y2,
								'position':'fixed'
						});
					}
			});
		};
})(jQuery);
