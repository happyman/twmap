// http://blog.mridey.com/2009/09/label-overlay-example-for-google-maps.html
// Define the overlay, derived from google.maps.OverlayView
function Label(opt_options) {
	// Initialization
	this.setValues(opt_options);

	// Label specific
	var span = this.span_ = document.createElement('span');
	span.style.cssText = this.defaultCSS = 'position: relative; left: -50%; top: 2px; ' +
		'font-size: 12px; '+
		'cursor:pointer; '+
		'white-space: nowrap; border: 1px solid blue; ' +
	//										 'background: none repeat scroll 0% 0% transparent; border: medium none;';
											 'padding: 2px; background-color: white ';
	/*
	  'padding: 2px; background-color: white ' + 
	  '-ms-filter:"progid:DXImageTransform.Microsoft.Alpha(Opacity=50)"; filter: alpha(opacity=50); opacity:.5;'
		*/


	var div = this.div_ = document.createElement('div');
	div.appendChild(span);
	div.style.cssText = 'z-index: 100; position: absolute; display: none';
};
Label.prototype = new google.maps.OverlayView;

// Implement onAdd
Label.prototype.onAdd = function() {
	var pane = this.getPanes().overlayLayer;
	//pane.style['zIndex'] = 1000;
	pane.appendChild(this.div_);

	// Ensures the label is redrawn if the text or position is changed.
	var me = this;
	this.listeners_ = [
		google.maps.event.addListener(this, 'position_changed',
				function() { me.draw(); }),
		google.maps.event.addListener(this, 'text_changed',
				function() { me.draw(); }),
		this.div_.addEventListener('click',function(e){
		// google.maps.event.addDomListener(this.div_,"click",function(e) {
				if (me.clickfunc) {
				// 把點的 label text 送給 clickfunc
				me.clickfunc(me.get('text').toString());
				} else {
				me.getMap().setCenter(me.get('position'));
				}
				// alert(e);
				// Cancel Bubble http://www.w3.org/TR/DOM-Level-3-Events/#event-flow
				// http://blog.xuite.net/vexed/tech/25193980
				e.returnValue = false;
				// https://developer.mozilla.org/en/DOM/Event
				if (!$.browser.msie)
					e.stopPropagation();
				})


	];
}

// Implement onRemove
Label.prototype.onRemove = function() {
	this.div_.parentNode.removeChild(this.div_);

	// Label is removed from the map, stop updating its position/text.
	for (var i = 0, I = this.listeners_.length; i < I; ++i) {
		google.maps.event.removeListener(this.listeners_[i]);
	}
};

// Implement draw
Label.prototype.draw = function() {
	var projection = this.getProjection();
	var position = projection.fromLatLngToDivPixel(this.get('position'));

	var div = this.div_;
	div.style.left = position.x + 'px';
	div.style.top = position.y + 'px';
	div.style.display = 'block';

	this.span_.innerHTML = this.get('text').toString();
	if (this.get('style'))
		this.span_.style.cssText = this.get('style');
	else
		this.span_.style.cssText = this.defaultCSS;
};
