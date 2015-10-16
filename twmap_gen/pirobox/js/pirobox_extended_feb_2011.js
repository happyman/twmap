/**
* Name: PiroBox Extended v.1.1 Beta
* Date: Feb 2011
* Autor: Diego Valobra (http://www.pirolab.it),(http://www.diegovalobra.com)
* Version: 1.1
* Licence: CC-BY-SA http://creativecommons.org/licenses/by-sa/3/it/
**/
(function($) {
	$.piroBox_ext = function(opt) {
		opt = jQuery.extend({
		piro_speed : 700,
		bg_alpha : 0.9,
		piro_scroll : true,
		piro_drag : null,
		piro_nav_pos : 'top'
		}, opt);
	$.fn.piroFadeIn = function(speed, callback) {
		$(this).fadeIn(speed, function() {
		if($.browser.msie())
			$(this).get(0).style.removeAttribute('filter');
		if(callback != undefined)
			callback();
		});
	};
	$.fn.piroFadeOut = function(speed, callback) {
		$(this).fadeOut(speed, function() {
		if($.browser.msie())
			$(this).get(0).style.removeAttribute('filter');
		if(callback != undefined)
			callback();
		});
	};
	var my_gall_obj = $('a[class*="pirobox"]');
	var map = new Object();
		for (var i=0; i<my_gall_obj.length; i++) {
			var it=$(my_gall_obj[i]);
			map['a.'+ it.attr('class').match(/^pirobox_gall\w*/)]=0;
		}
	var gall_settings = new Array();
	for (var key in map) {
		gall_settings.push(key);
	}
	for (var i=0; i<gall_settings.length; i++) {
		$(gall_settings[i]+':first').addClass('first');
		$(gall_settings[i]+':last').addClass('last');
	}
	var piro_gallery = $(my_gall_obj);

	$('a[class*="pirobox_gall"]').each(function(rev){
			
		this.rev = rev+0});
	if($('a[class*="pirobox_gall"]').length == 1){ 
	$('a[class*="pirobox_gall"]').each(function(){
		$(this).addClass('single_fix');
		//alert('1');
		});
	}else{//NULL}
	//alert($(piro_gallery).length);
	}
	var	piro_capt_cont = '<div class="caption"></div>';
	var rz_img =0.90; //var rz_img =1.203; /*::::: ORIGINAL SIZE :::::*/
	var struct =(
		'<div class="piro_overlay"></div>'+
		'<table class="piro_html"  cellpadding="0" cellspacing="0">'+
		'<tr>'+
		'<td class="h_t_l"></td>'+
		'<td class="h_t_c" title="drag me!!"></td>'+
		'<td class="h_t_r"></td>'+
		'</tr>'+
		'<tr>'+
		'<td class="h_c_l"></td>'+
		'<td class="h_c_c">'+
		'<div class="piro_loader" title="close"><span></span></div>'+
		'<div class="resize">'+
		'<div class="nav_container">'+
		'<a href="#prev" class="piro_prev" title="previous"></a>'+
		'<a href="#next" class="piro_next" title="next"></a>'+
		'<div class="piro_prev_fake">prev</div>'+
		'<div class="piro_next_fake">next</div>'+
		'<div class="piro_close" title="close"></div>'+
		'</div>'+
		'<div class="div_reg"></div>'+
		'</div>'+
		'</td>'+
		'<td class="h_c_r"></td>'+
		'</tr>'+
		'<tr>'+
		'<td class="h_b_l"></td>'+
		'<td class="h_b_c"></td>'+
		'<td class="h_b_r"></td>'+
		'</tr>'+
		'</table>'
	);

	// only run once: happyman 2012.1
	//alert($(".piro_overlay").length);

	$(".piro_overlay").remove();
	$(".piro_html").remove();
	
	//alert($(".piro_overlay").length);
	
	$('body').append(struct);
	var wrapper = $('.piro_html');
	piro_bg = $('.piro_overlay'),
	piro_nav = $('.nav_container'),
	piro_next = $('.piro_next'),
	piro_prev = $('.piro_prev'),
	piro_next_fake = $('.piro_next_fake'),
	piro_prev_fake = $('.piro_prev_fake'),
	piro_close = $('.piro_close'),
	div_reg = $('.div_reg'),
	piro_loader = $('.piro_loader'),
	resize = $('.resize'),
	y = $(window).height(),
	x = $(window).width();
	piro_nav.hide().css(opt.piro_nav_pos ,'-38px');
	if(opt.piro_nav_pos == 'top'){
		var position = +5;
	}else if(opt.piro_nav_pos == 'bottom'){
		var position = -30;
	}
	wrapper.css({left:  ((x/2)-(250))+ 'px',top: parseInt($(document).scrollTop())+(100)});
	$(wrapper).add(piro_bg).hide();
	piro_bg.css({'opacity':opt.bg_alpha});	
	$(piro_prev).add(piro_next).bind('click',function(c) {
		piro_nav.hide();
		$('.caption').remove();
		$('.zoomIn').add('.zoomOut').remove();
		c.preventDefault();
		piro_next.add(piro_prev).hide();
		var obj_count = parseInt($('a[class*="pirobox_gall"]').filter('.item').attr('rev'));
		var start = $(this).is('.piro_prev') ? $('a[class*="pirobox_gall"]').eq(obj_count - 1) : $('a[class*="pirobox_gall"]').eq(obj_count + 1);
		start.click();
	});
	$('html').bind('keyup', function (c) {
		 if(c.keyCode == 27) {
			c.preventDefault();
			if($(piro_close).is(':visible')){close_all();}
		}
	});
	$('html').bind('keyup' ,function(e) {
		 if ($('.item').is('.first')){
		}else if ($('.item').attr('rel') == 'single'){
			piro_nav.show();
		}else if(e.keyCode == 37){
		e.preventDefault();
			if($(piro_close).is(':visible')){piro_prev.click();}
		 }
	});
	$('html').bind('keyup' ,function(z) {
		if ($('.item').is('.last')){
		}else if ($('.item').attr('rel') == 'single'){
			piro_nav.show();
		}else if(z.keyCode == 39){
		z.preventDefault();
			if($(piro_close).is(':visible')){piro_next.click();}
		}
	});
	$(window).resize(function(){
		var new_y = $(window).height(),
		new_x = $(window).width(),
		new_h = wrapper.height(),
		new_w = wrapper.width();
		wrapper.css({
			left:  ((new_x/2)-(new_w/2))+ 'px',
			top: parseInt($(document).scrollTop())+(new_y-new_h)/2+position
			});			  
	});	
	function scrollIt (){
		$(window).scroll(function(){
			var new_y = $(window).height(),
			new_x = $(window).width(),
			new_h = wrapper.height()-20,
			new_w = wrapper.width();
			wrapper.css({
				left:  ((new_x/2)-(new_w/2))+ 'px',
				top: parseInt($(document).scrollTop())+(new_y-new_h)/2+position
			});			  
		});
	}
	if(opt.piro_scroll == true){
		scrollIt();
	  }
	  //$('a[class*="pirobox_gall"]');
	$(piro_gallery).each(function(){
		

		var descr = $(this).attr('title'),
		params = $(this).attr('rel').split('-'),
		p_link = $(this).attr('href');
		
		$(this).unbind(); 
		$(this).bind('click', function(e) {
			piro_bg.css({'opacity':opt.bg_alpha});	
			e.preventDefault();
			piro_next.add(piro_prev).hide().css('visibility','hidden');
			$(piro_gallery).filter('.item').removeClass('item');
			$(this).addClass('item');
			open_all();
			if($(this).is('.first')){
				piro_prev.hide();
				piro_next.show();
				piro_prev_fake.show().css({'visibility':'hidden'});
			}else{
				piro_next.add(piro_prev).show();
				piro_next_fake.add(piro_prev_fake).hide();	  
			}
			if($(this).is('.last')){
				piro_prev.show();
				piro_next_fake.show().css({'visibility':'hidden'});
				piro_next.hide();	
			}
			if($(this).is('.pirobox') || $(this).is('.single_fix')){
				piro_next.add(piro_prev).hide();
			}
			if($(this).is('.last') && $(this).is('.first') ){
				piro_next.add(piro_prev).hide();

		  	}	

		});
		function open_all(){
			wrapper.add(piro_bg).add(div_reg).add(piro_loader).show();
			function animate_html(){
				$('.caption').remove();
				if(params[1] == 'full' && params[2] == 'full'){
				params[2] = $(window).height()-70;	
				params[1] = $(window).width()-55;
				}
				var y = $(window).height();
				var x = $(window).width();
				piro_close.hide();
				div_reg.add(resize).animate({
					'height':+ (params[2]) +'px',
					'width':+ (params [1])+'px'
					},opt.piro_speed).css('visibility','visible');
				wrapper.animate({
					height:+ (params[2])+20 +'px',
					width:+ (params[1]) +20+'px',
					left:  ((x/2)-((params[1])/2+10))+ 'px',
					top: parseInt($(document).scrollTop())+(y-params[2])/2+ position
					},opt.piro_speed ,function(){
						piro_next.add(piro_prev).add(piro_prev_fake).add(piro_next_fake).css('visibility','visible');
						piro_nav.show();
						piro_close.show();
				});
			}
			function animate_image (){
				piro_nav.add('.caption').hide();
					if(descr == ""){
						$('.caption').remove();
					}else{
						$(piro_capt_cont).appendTo(resize);
					}
						var img = new Image();
						img.onerror = function (){
							$('.caption').remove();
							img.src = "http://www.pirolab.it/pirobox/js/error.jpg";
						};
						img.onload = function() {
							var this_h = img.height;
							var this_w = img.width;
							var y = $(window).height();
							var x = $(window).width();
							var	imgH = img.height;
							var	imgW = img.width;
							if(imgH+20 > y || imgW+20 > x){
								var _x = (imgW + 20)/x;
								var _y = (imgH + 20)/y;
								if ( _y > _x ){
									imgW = Math.round(img.width* (rz_img/_y));
									imgH = Math.round(img.height* (rz_img/_y));
								}else{
									imgW = Math.round(img.width * (rz_img/_x));
									imgH = Math.round(img.height * (rz_img/_x));
								}
								$('.zoomIn').add('.zoomOut').remove();
								$('.h_c_c').append('<a href="#" class="zoomIn" title="">zoomIn</a>');
								$('.zoomIn,.zoomOut').hide();
							}else{
								imgH = img.height;
								imgW = img.width;
								$('.zoomIn').add('.zoomOut').remove();
								}
							var y = $(window).height();
							var x = $(window).width();
							$(img).height(imgH).width(imgW).hide();
							$('.div_reg img').remove();
							$('.div_reg').html('');
							div_reg.append(img).show();
							$(img).addClass('immagine');
							if(opt.piro_drag == true){
								$('.immagine,.h_b_c,.h_t_c').css('cursor','move');
								if ( $.browser.msie() ) {
									wrapper.draggable({ handle:'.h_t_c,.h_b_c,.div_reg img'});
								}else{
									wrapper.draggable({ handle:'.h_t_c,.h_b_c,.div_reg img',opacity: 0.80});
								}
							}
							div_reg.add(resize).animate({height:imgH+'px',width:imgW+'px'},opt.piro_speed);
							wrapper.animate({
								height : (imgH+20) + 'px' ,
								width : (imgW+20) + 'px' , 
								left:  ((x/2)-((imgW+20)/2)) + 'px',
								top: parseInt($(document).scrollTop())+(y-imgH)/2 + position
								},opt.piro_speed, function(){
									piro_loader.hide();		
									var cap_w = resize.width();
									$('.caption').css({width:cap_w+'px'});
									$('.caption').html('<p>'+descr+'</p>').hide();		
									$(img).fadeIn(300,function(){
									if(img.src == "http://www.pirolab.it/pirobox/js/error.jpg" ){
										}
									$(window).scroll(function(){
										if($('.zoomOut').is(':visible')){
										//alert('visibile');
											$(img).animate({'width':  imgW , 'height': imgH, top:0, left:0 },600,function(){
											 $('.immagine').css('cursor', 'auto');
											$(img).draggable({disabled:true});
											$('.zoomIn').add('.zoomOut').remove();
											$('.h_c_c').append('<a href="#" class="zoomIn" title="">zoomIn</a>');
											$('.zoomIn').show();
											});										
										}
										});
									//$('.zoomOut').live('click',function(h){
									$('.zoomOut').click(function(h){
										h.preventDefault();
										$('.immagine').css('cursor', 'auto');
										if(opt.piro_drag == true){
											wrapper.draggable({disabled:false});
										}
											$(img).draggable({disabled:true});
											$('.zoomIn').add('.zoomOut').remove();
											$('.h_c_c').append('<a href="#" class="zoomIn" title="">zoomIn</a>');
											$('.zoomIn').show();
											$(img).animate({'width':  imgW , 'height': imgH, top:0, left:0 },600)
										});
									//$('.zoomIn').live('click',function(w){
									$('.zoomIn').click(function(w){
										w.preventDefault();
										$('.zoomIn').add('.zoomOut').remove();
										$('.h_c_c').append('<a href="#" class="zoomOut" title="">zoomOut</a>');
										$('.zoomOut').show();
										if(opt.piro_drag == true){									
											wrapper.draggable({disabled:true});
										}
										$(img).draggable({disabled:false});
										$(img).animate({'width':  this_w , 'height': this_h , top:-(this_h-imgH)/2, left:-(this_w-imgW)/2 },600,function(){
										var imgPos     = div_reg.offset();
										var x1 = (imgPos.left + imgW) - this_w;
										var y1 = (imgPos.top + imgH) - this_h;
										var x2 = imgPos.left;
										var y2 = imgPos.top;
									  $(img).draggable({containment: [x1,y1,x2,y2],scrollSpeed: 400});
									  $('.immagine').css('cursor', 'move');
									  });
									 });
									$('.zoomIn').show();
									piro_close.show();
									$('.caption').slideDown(200);
									piro_next.add(piro_prev).add(piro_prev_fake).add(piro_next_fake).css('visibility','visible');
									piro_nav.show();
									resize.resize(function(){
										NimgW = img.width;//1.50;
										NimgH = img.heigh;//1.50;
										$('.caption').css({width:(NimgW)+'px'});
									});	
								});	
							});	
						};
						img.src = p_link;
						piro_loader.click(function(){
						img.src = 'about:blank';
					});
				}
			switch (params[0]) {
				case 'iframe':
				$('.zoomIn').add('.zoomOut').remove();
					div_reg.html('').css('overflow','hidden');
					resize.css('overflow','hidden');
					piro_close.hide();
					animate_html();
					div_reg.piroFadeIn(300,function(){
						div_reg.append(
						'<iframe id="my_frame" class="my_frame" src="'+p_link+'" frameborder="0" allowtransparency="true" scrolling="auto" align="top"></iframe>'
						);
						$('.my_frame').css({'height':+ (params[2]) +'px','width':+ (params [1])+'px'});
						piro_loader.hide();
					});
				break;
				case 'content':
				$('.zoomIn').add('.zoomOut').remove();
					div_reg.html('').css('overflow','auto');
					resize.css('overflow','auto');
					$('.my_frame').remove();
					piro_close.hide();
					animate_html();
					div_reg.piroFadeIn(300,function(){
						div_reg.load(p_link);
						//alert(p_link);
						piro_loader.hide();
					});
				break;
				case 'inline':
				$('.zoomIn').add('.zoomOut').remove();
					div_reg.html('').css('overflow','auto');
					resize.css('overflow','auto');
					$('.my_frame').remove();
					piro_close.hide();
					animate_html();
					div_reg.piroFadeIn(300,function(){
						$(p_link).clone(true).appendTo(div_reg).piroFadeIn(300);
						piro_loader.hide();
					});
				break;
				case 'flash':
				$('.zoomIn').add('.zoomOut').remove();
				$('.my_frame').remove();
				div_reg.html('').css('overflow','hidden');
				animate_html();
					var flash_cont =(
					'<object  width="'+params[1]+'" height="'+params[2]+'">'+
					'<param name="movie" value="'+ p_link +'" />'+
					'<param name="wmode" value="transparent" />'+
					'<param name="allowFullScreen" value="true" />'+
					'<param name="allowscriptaccess" value="always" />'+
					'<param name="menu" value="false" />'+
					'<embed src="'+ p_link +'" type="application/x-shockwave-flash" allowscriptaccess="always" menu="false" wmode="transparent" allowfullscreen="true" width="'+params[1]+'" height="'+params[2]+'">'+
					'</embed>'+
					'</object>');
					div_reg.piroFadeIn(300,function(){
					$(flash_cont).appendTo(div_reg);
					piro_loader.hide();
					});
				break;
				case 'gallery':
					div_reg.css('overflow','hidden');
					resize.css('overflow','hidden');
					$('.my_frame').remove();
					animate_image();
				break;
				case 'single':
					$('.my_frame').remove();
					div_reg.html('').css('overflow','hidden');
					resize.css('overflow','hidden');
					animate_image();
				break;
			} 	
		}
	});
	$('.immagine').click(function(){
		$('.caption').slideToggle(200);
	});
	function close_all (){
		if($('.piro_close').is(':visible')){
				$('.zoomIn').add('.zoomOut').add('.my_frame').add('.caption').remove();
				wrapper.add(div_reg).add(resize).stop();
				if(opt.piro_drag == true){
					wrapper.draggable({disabled:false});
				}
				var ie_sucks = wrapper;
				if ( $.browser.msie() ) {
					ie_sucks = div_reg.add(piro_bg);
					$('.div_reg img').remove();
				}else{
					ie_sucks = wrapper.add(piro_bg);
				}
				ie_sucks.piroFadeOut(200,function(){
					div_reg.html('');
					piro_loader.hide();
					piro_nav.hide();
					piro_bg.add(wrapper).hide().css('visibility','visible');
				});
			}
		}
		piro_close.add(piro_loader).add(piro_bg).bind('click',function(y){y.preventDefault(); close_all(); });	
	}
})(jQuery);
