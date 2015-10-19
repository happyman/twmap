jQuery.fn.extend({ 

	meerkat: function(options) {

		var defaults = {
			background: 'none',
			opacity: null,
			height: 'auto',
			width: '100%',
			position: 'bottom',
			close: '.close',
			dontShowAgain: '#dont-show',
			dontShowAgainAuto: false,
			animationIn: 'none',
			animationOut: null,
			easingIn: 'swing',
			easingOut: 'swing',
			animationSpeed: 'normal',
			cookieExpires: 0,
			removeCookie: '.removeCookie',
			delay: 0,
			onMeerkatShow: function() {},
			timer: null		
		};

		var settings = jQuery.extend(defaults, options);
		
	
		if(jQuery.easing.def){
			settings.easingIn = settings.easingIn;
			settings.easingOut = settings.easingOut;
		}else {
			settings.easingIn = 'swing';
			settings.easingOut = 'swing';
		}

		if(settings.animationOut === null){
			settings.animationOut = settings.animationIn;	
		}

		settings.delay = settings.delay * 1000;
		if(settings.timer != null){
			settings.timer = settings.timer * 1000;
		}

		function createCookie(name,value,days) {
			if (days) {
				var date = new Date();
				date.setTime(date.getTime()+(days*24*60*60*1000));
				var expires = "; expires="+date.toGMTString();
			}
			else { 
				var expires = "";
			}
			document.cookie = name+"="+value+expires+"; path=/";
		}

		function readCookie(name) {
			var nameEQ = name + "=";
			var ca = document.cookie.split(';');
			for(var i=0;i < ca.length;i++) {
				var c = ca[i];
				while (c.charAt(0)===' ') c = c.substring(1,c.length);
				if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length,c.length);
			}
			return null;
		}
		
		function eraseCookie(name) {
			createCookie(name,"",-1);
		}		
		jQuery(settings.removeCookie).click(function(){ eraseCookie('meerkat')});

		return this.each(function() {
			var element = jQuery(this);
			if(readCookie('meerkat') != "dontshow"){
				settings.onMeerkatShow.call(this);

				function animateMeerkat(showOrHide, fadeOrSlide){					
					var meerkatWrap = jQuery('#meerkat-wrap');
					if(fadeOrSlide === "slide"){				
						if(settings.position === "left" || settings.position === "right"){
							var animationType = 'width';
						} else {
							var animationType = 'height';
						}
					} else {
						var animationType = "opacity";
					} 
					var animationProperty = {};
					animationProperty[animationType] = showOrHide;

					if(showOrHide === "show"){
						if(fadeOrSlide !== "none"){
							if(settings.delay > 0){
								jQuery(meerkatWrap).hide().delay(settings.delay).animate(animationProperty,settings.animationSpeed, settings.easingIn);
							} else {
								jQuery(meerkatWrap).hide().animate(animationProperty,settings.animationSpeed, settings.easingIn);
							}							
						} else if ((fadeOrSlide === "none")&&(settings.delay > 0)){
							jQuery(meerkatWrap).hide().delay(settings.delay).show(0);
						} else {
							jQuery(meerkatWrap).show();
						}
						jQuery(element).show(0);
					}

					if(showOrHide === "hide"){
						if(fadeOrSlide !== "none"){
							if(settings.timer !== null){
								jQuery(meerkatWrap).delay(settings.timer).animate(animationProperty,settings.animationSpeed, settings.easingOut,
								  function(){
								    jQuery(this).destroyMeerkat(); 
								    if(settings.dontShowAgainAuto === true) { createCookie('meerkat','dontshow', settings.cookieExpires); }
								  });
							}
							jQuery(settings.close).click(function(){
								jQuery(meerkatWrap).stop().animate(animationProperty,settings.animationSpeed, settings.easingOut, function(){jQuery(this).destroyMeerkat();});
								return false;
							});
							jQuery(settings.dontShowAgain).click(function(){
								jQuery(meerkatWrap).stop().animate(animationProperty,settings.animationSpeed, settings.easingOut, function(){jQuery(this).destroyMeerkat();});
								createCookie('meerkat','dontshow', settings.cookieExpires);
								return false;
							});
						} else if((fadeOrSlide === "none")&&(settings.timer !== null)) {
							jQuery(meerkatWrap).delay(settings.timer).hide(0).queue(function(){
								jQuery(this).destroyMeerkat();
							});
						} else {
							jQuery(settings.close).click(function(){
								jQuery(meerkatWrap).hide().queue(function(){
									jQuery(this).destroyMeerkat();
								});
								return false;
							});
							jQuery(settings.dontShowAgain).click(function(){
								jQuery(meerkatWrap).hide().queue(function(){
									jQuery(this).destroyMeerkat();
								});
								createCookie('meerkat','dontshow', settings.cookieExpires);
								return false;
							});
						}
					}
				}


				jQuery('html, body').css({'margin':'0', 'height':'100%'});
				// happyman
				jQuery(element).wrap('<div id="meerkat-wrap"><div id="meerkat-container"></div></div>');
				jQuery('#meerkat-wrap').css({'position':'fixed', 'z-index': '10000', 'width': settings.width, 'height': settings.height}).css(settings.position, "0");
				jQuery('#meerkat-container').css({'background': settings.background, 'height': settings.height});

				if(settings.position === "left" || settings.position === "right"){ jQuery('#meerkat-wrap').css("top", 0);}

				if(settings.opacity != null){
					jQuery("#meerkat-wrap").prepend('<div class="opacity-layer"></div>');
					jQuery('#meerkat-container').css({'background': 'transparent', 'z-index' : '2', 'position': 'relative'});
					jQuery(".opacity-layer").css({
							'position': 'absolute', 
							'top' : '0', 
							'height': '100%', 
							'width': '100%',  
							'background': settings.background, 
							"opacity" : settings.opacity
						});					

				}
				if(jQuery.browser.msie  && jQuery.browser.version <= 6){
					jQuery('#meerkat-wrap').css({'position':'absolute', 'bottom':'-1px', 'z-index' : '0'});
					if(jQuery('#ie6-content-container').length == 0){			
					jQuery('body').children()
						.filter(function (index) {
							return jQuery(this).attr('id') != 'meerkat-wrap';
						})
					.wrapAll('<div id="ie6-content-container"></div>');
					jQuery('html, body').css({'height':'100%', 'width':'100%', 'overflow':'hidden'});
					jQuery('#ie6-content-container').css({'overflow':'auto', 'width':'100%', 'height':'100%', 'position':'absolute'});
					var bgProperties = document.body.currentStyle.backgroundColor+ " ";
					bgProperties += document.body.currentStyle.backgroundImage+ " ";
					bgProperties += document.body.currentStyle.backgroundRepeat+ " ";
					bgProperties += document.body.currentStyle.backgroundAttachment+ " ";
					bgProperties += document.body.currentStyle.backgroundPositionX+ " ";
					bgProperties += document.body.currentStyle.backgroundPositionY;
					jQuery("body").css({'background':'none'});
					jQuery("#ie6-content-container").css({'background' : bgProperties});
					}
					var ie6ContentContainer = document.getElementById('ie6-content-container');					
					if((ie6ContentContainer.clientHeight < ie6ContentContainer.scrollHeight) && (settings.position != 'left')) {
						jQuery('#meerkat-wrap').css({'right' : '17px'});
					}
				}

				switch (settings.animationIn)
				{
					case "slide":
						animateMeerkat("show", "slide");
						break;
					case "fade":
						animateMeerkat("show", "fade");
						break;
					case "none":
						animateMeerkat("show", "none");
						break;
					default:
						alert('The animationIn option only accepts "slide", "fade", or "none"');
				}

				switch (settings.animationOut)
				{
					case "slide":
						animateMeerkat("hide", "slide");
						break;

					case "fade":
						animateMeerkat("hide", "fade");
						break;

					case "none":
						if(settings.timer != null){
							jQuery('#meerkat-wrap').delay(settings.timer).hide(0).queue(function(){
								jQuery(this).destroyMeerkat();
							});
						}
						jQuery(settings.close).click(function(){
							jQuery('#meerkat-wrap').hide().queue(function(){
								jQuery(this).destroyMeerkat();
							});
						});
						jQuery(settings.dontShowAgain).click(function(){
							jQuery('#meerkat-wrap').hide().queue(function(){
								jQuery(this).destroyMeerkat();
							});
							createCookie('meerkat','dontshow', settings.cookieExpires);
						});
						break;

					default:
					  alert('The animationOut option only accepts "slide", "fade", or "none"');
				}
			} else {
				jQuery(element).hide();	
			}
		});
	},
	destroyMeerkat: function() {
		jQuery('#meerkat-wrap').replaceWith( jQuery('#meerkat-container').contents().hide() );		
	}
});
