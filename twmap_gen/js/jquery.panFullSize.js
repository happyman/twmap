/**
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 *
 * http://www.gnu.org/licenses/gpl.txt
 *
 *
 * Creates a image like div with the image as background with ability to
 * pan it with mouse.
 *
 * @name        panFullSize
 * @author      Esa-Matti Suuronen
 * @contact     esa-matti [a] suuronen [dot] org
 * @license     GPL
 * @version     1.1
 *
 *
 *
 * Takes only img-elements (and custom pan-divs). Otherwise exception is thrown.
 *
 * Example: $("img#mypic").panFullSize(300,200);
 *
 *
 *
 */
jQuery.fn.panFullSize = function(x, y, afterLoaded){

    this.each(function(){

        var pic;
        var pic_real_width;
        var pic_real_height;
        var picX_start = 0;
        var picY_start = 0;
        var prevX = 0;
        var prevY = 0;
        var newX = 0;
        var newY = 0;
        var mousedown = false;


        if ( $(this).is("img") ) {
            pic = $(this);
        }
        else if ( $(this).is("div.panFullSize")  ) { // from custom pan-div
            pic = $(this).prev("img"); // Get the real pic
        }
        else {
            throw "Not an image! panFullSize can only be used with images.";
        }

        var pan = getPan();
        // Already have pan div?
        var exists = pan.is("*");

        if (exists) {
            x = x || pan.width();
            y = y || pan.height();
        }
        else {
            // Defaults from img-element
            x = x || pic.width();
            y = y || pic.height();
        }

        var box_width = x;
        var box_height = y;


        function initialize(){

            pic.hide();

            pan.css( 'width', box_width + "px").css( 'height', box_height + "px" );
            pan.css( 'display', 'inline-block');  // Make div to act like img. inline-block does not work in FF2?
            // It's only needed to add size-attributes if we already have pan-div
            if ( exists ) {
                return;
            }

            //pan.css( 'background-color', "red" ); // For debugging. Should not be seen ever.
            pan.css( 'background-image', 'url("' + pic.attr("src") + '")' );
            pan.css( 'background-repeat', "no-repeat" );
            pan.css( 'background-origin','content-box');
						pan.css( 'background-attachment','fixed');


            // Get the real size of the image
            var pic_orig_width = pic.width();
            var pic_orig_height = pic.height();
            pic.removeAttr("width");
            pic.removeAttr("height");
            pic_real_width = pic.width();
            pic_real_height = pic.height();
            pic.width(pic_orig_width).height(pic_orig_height);



            pan.mousedown(function(e){
                e.preventDefault();
                mousedown = true;

                box_width = pan.width();
                box_height = pan.height();

                picX_start = e.clientX;
                picY_start = e.clientY;
            });

            $(document).mousemove(onpan);

            $(document).mouseup(function(e){
                onpan(e);
                mousedown = false;

                prevX = newX;
                prevY = newY;

            });

            if (afterLoaded) {
                afterLoaded();
            }
        }


        if ( !exists ) {
            // new pan-div
            // The space in div is required
            pic.after('<div id="pan' + pic.attr("id") +'" class="panFullSize"></div>');
            pan = getPan().hide();
            // On creating pan-div, we need to wait for the image to load before we can initialize pan-div.
            // Otherwise getting the real image width&heigth fails...
            //pic.load(initialize);
            pic.get(0).complete ? initialize() : pic.load(initialize);
        }
        else {
            initialize();
        }





        function onpan(e){

            var diffX = e.clientX - picX_start;
            var diffY = e.clientY - picY_start;

             if ( mousedown ){

                  var in_areaX = true;
                  var in_areaY = true;

                  if ( prevX + diffX >= 0 ) {
                      in_areaX = false;
                  }
                  if ( -(prevX + diffX) > pic_real_width - box_width ) {
                      in_areaX = false;
                  }
                  if (in_areaX) {
                    newX = prevX + diffX;
                  }


                  if ( prevY + diffY >= 0 ){
                      in_areaY = false;
                  }
                  if ( -(prevY + diffY) > pic_real_height - box_height ){
                      in_areaY = false;
                  }
                  if (in_areaY){
                      newY = prevY + diffY;
                  }

                  pan.css( {backgroundPosition:  newX.toString() +"px " + newY.toString() + "px"} )

									//console.log( "newx:" + newX.toString() + "newy:"+ newY.toString());
             }

         }

         function getPan(){
             return pic.next("div.panFullSize");
         }


    });

    // Lets return the new pan-divs
    return $(this).next("div.panFullSize");


};


/**
 * Restores normal image view
 */
jQuery.fn.normalView = function(){
 this.each(function(){
    if ( $(this).is("div.panFullSize") ){
        $(this).hide();
        $(this).prev("img").show();

    }
    else if ( $(this).is("img") && $(this).next("div.panFullSize").is("*") ) {
        $(this).show();
        $(this).next("div.panFullSize").hide();

    }

 });


if ( $(this).is("div.panFullSize")  )
    return $(this).prev("img");
return $(this);

};



