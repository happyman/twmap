// ==UserScript==
// @id             iitc-plugin-basemap-twmap@happyman
// @name           IITC plugin: taiwan hiking map tiles
// @category       Map Tiles
// @version        0.8
// @namespace      https://github.com/jonatkins/ingress-intel-total-conversion
// @updateURL      http://map.happyman.idv.tw/~happyman/ingress/twmap.user.js
// @downloadURL    http://map.happyman.idv.tw/~happyman/ingress/twmap.user.js
// @description    [jonatkins-2015-01-13-00000] Add Taiwan Map as optional layer
// @require        http://ajax.googleapis.com/ajax/libs/jquery/1.9.1/jquery.min.js
// @require        http://myuserjs.org/API/0.0.17/jMod.js
// @grant            unsafeWindow
// @grant            GM_info
// @grant            GM_log
// @grant            GM_addStyle
// @grant            GM_getMetadata
// @grant            GM_xmlhttpRequest
// @grant            GM_registerMenuCommand
// @grant            GM_getValue
// @grant            GM_setValue
// @grant            GM_listValues
// @grant            GM_deleteValue
// @unwrap
// @noframes
// @run-at           document-start
// @jMod             {"API": {"log": {"debug": true}}}
// @include        https://www.ingress.com/intel*
// @include        http://www.ingress.com/intel*
// @match          https://www.ingress.com/intel*
// @match          http://www.ingress.com/intel*
// ==/UserScript==

// changelog:
// 20150113 add TWMAP layer
// 20150412 cross domain request WPT detail and download GPX link
if($){
  $(document).ready(function() {
    jMod.jQueryExtensions.addCrossDomainSupport($);
  });
}
function wrapper() {
// ensure plugin framework is there, even if iitc is not yet loaded
if(typeof window.plugin !== 'function') window.plugin = function() {};


// PLUGIN START ////////////////////////////////////////////////////////
// Taiwan is a independent country

// use own namespace for plugin
window.plugin.mapTileTaiwanMap = function() {};
var self = window.plugin.mapTileTaiwanMap;
self.addLayer = function() {

  var twMap_Opt = {attribution: 'Tiles © Taiwan contour map', maxNativeZoom: 18, maxZoom: 19};
  var twMap_gpx = new L.TileLayer('http://rs.happyman.idv.tw/map/twmap_gpx/{z}_{x}_{y}.png', twMap_Opt);
  var twMap_plain = new L.TileLayer('http://rs.happyman.idv.tw/map/tw25k2001/zxy/{z}_{x}_{y}.png', twMap_Opt);
  layerChooser.addBaseLayer(twMap_gpx, "Taiwan 25000 with gpx");
  layerChooser.addBaseLayer(twMap_plain, "Taiwan 25000");
};

var curlayer;
self.showpt = function showpt(e) {
  var layers = layerChooser.getLayers().baseLayers;
  for(var ll=0; ll< layers.length;ll++){
    if (layers[ll].active == true) {
      curlayer = layers[ll].name;
      break;
    }
  }

  // if not correct layer, do nothing
  if (curlayer != "Taiwan 25000 with gpx"){
      return;
  }
  var pt = e.latlng;
  var radius = (20 - map.getZoom())*10-10;
  var wpt_url = '//map.happyman.idv.tw/twmap/api/waypoints.php?x=' + pt.lng + "&y=" + pt.lat + "&r=" + radius;
	var twmap_url = 'http://map.happyman.idv.tw/~happyman/twmap3/index.php?goto=' + pt.lat + "," + pt.lng + '&zoom=14&opacity=0.707&mapversion=3&maptypeid=satellite&show_label=1&show_kml_layer=1&show_marker=a&roadmap=GoogleNames&grid=TWD67&theme=default';
  var note = "";
  var popup;
  $.ajax({
            type: "GET",
            url: wpt_url + "&detail=0"
  }).done(function(data, textStatus, jqXHR) {
         if (data.rsp.length < 1) return;
         for(ll=0; ll<data.rsp.length; ll++){
            var note1 =  "<h3><a href='"+ twmap_url + "&detail=1' target=gpx>" + data.rsp[ll].name + "</a></h3>";
            note += note1;
         }
         note += "";
         popup = L.popup().setLatLng(pt).setContent(note).openOn(map);
  });
};// showpt end

self.setup = function() {
  self.addLayer();
  // delete window.plugin.mapTileTaiwanMap.setup;
  window.map.on('click', self.showpt);
  delete self.setup;
};
// PLUGIN END //////////////////////////////////////////////////////////

// IITC plugin setup
if (window.iitcLoaded && typeof self.setup === "function") {
  self.setup();
} else if (window.bootPlugins) {
  window.bootPlugins.push(self.setup);
} else {
  window.bootPlugins = [self.setup];
}
} // end of wrapper
// inject code into site context
var script = document.createElement('script');
script.appendChild(document.createTextNode("(" + wrapper + ")();"));
(document.body || document.head || document.documentElement).appendChild(script);
