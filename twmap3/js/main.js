/*
global $google
 */
var map;
if (typeof console == "undefined") {
    window.console = {
        log: function() {}
    };
}
// 邊框變數
var miniX = 9999;
var miniY = 0;
var maxiX = 0;
var maxiY = 9999;
// 產生器 url
var ismakingmap = 0;
var callmake;
var centerMarker;
var centerInfo;
var markerArray = [];
var markerArrayMax = 300;
var labelArray = [];
// 標記使用
var tags_ready = 0;
var markers_ready = 0;
var availableTags = [];
var availableAlias = [];
var availableAliasMap = [];
var availableTagsLocation = [];
var availableTagsMeta = [];
var showCenterMarker_id = "";
var locInfo_name = "我的座標";
var show_label = 1; 
var opacity = getParameterByName("opacity") ? getParameterByName("opacity") : 0.71;
var got_geo = 0;
// geocoding
var geocoder;
// var elevator;
var theme = "default";
// var show_kml = (getParameterByName("kml")) ? 1: 0;
// 預設開啟 
var show_kml_layer = 1;
var show_delaunay = 0;
var GPSLayer; // external kml layer
// 以下為底圖
var TaiwanMapV1Options = {
    getTileUrl: function(a, b) {
        var z = 17 - b;
        // return "http://rs.happyman.idv.tw/fcgi-bin/mapserv.fcgi?x=" + a.x + "&y=" + a.y + "&zoom=" + z;
		return "http://gis.sinica.edu.tw/googlemap/TM25K_1989/" + z + "/" + a.x + "/IMG_" + a.x + "_" + a.y + "_" + z + ".jpg";
    },
    tileSize: new google.maps.Size(256, 256),
    maxZoom: 15,
    // minZoom: 13, ## fcgi 13-18
    name: '經建1',
    alt: '經建一'	
};

var TaiwanMapOptions = {
    getTileUrl: function(coord, zoom) {
        return "http://rs.happyman.idv.tw/map/tw25k2001/zxy/" + zoom + "_" + coord.x + "_" + coord.y + ".png";
    },
    tileSize: new google.maps.Size(256, 256),
    maxZoom: 17,
    minZoom: 10,
    name: "經建3",
    alt: 'Taiwan TW67 Map'
};
var TaiwanGpxMapOptions = {
    getTileUrl: function(a, b) {
        return 'http://rs.happyman.idv.tw/map/twmap_gpx/' + b + "_" + a.x + "_" + a.y + ".png";
    },
    tileSize: new google.maps.Size(256, 256),
    maxZoom: 17,
    minZoom: 10,
    name: "台灣經建三版+GPX",
    alt: 'Taiwan TW67 Map with GPX'
};
// 以下為前景圖層(透明背景可疊合)
var GoogleNameOptions = {
    getTileUrl: function(a, b) {
        return "//mts1.google.com/vt/lyrs=h@195026035&x=" + a.x + "&y=" + a.y + "&z=" + b;
    },
    tileSize: new google.maps.Size(256, 256),
    maxZoom: 20,
    minZoom: 0,
	opacity: 1,
    name: 'GoogleNames'
};
var NLSCNameOptions = {
    getTileUrl: function(a, b) {
       // return 'http://maps.nlsc.gov.tw/S_Maps/wmts?SERVICE=WMTS&REQUEST=GetTile&VERSION=1.0.0&LAYER=EMAP2&STYLE=_null&TILEMATRIXSET=EPSG:3857&TILEMATRIX=EPSG:3857:' + b + "&TILEROW=" + a.y + "&TILECOL=" + a.x + "&FORMAT=image%2Fpng";
	   return 'http://wmts.nlsc.gov.tw/wmts/EMAP2/default/EPSG:3857/'+b+'/'+a.y+'/'+a.x;
    },
    tileSize: new google.maps.Size(256, 256),
    maxZoom: 19,
	opacity: 1,
    name: 'NLSCNames'
};
var GPXTrackOptions = {
    getTileUrl: function(a, b) {
        return 'http://rs.happyman.idv.tw/map/gpxtrack/' + b + "/" + a.x + "/" + a.y + ".png";
    },
    tileSize: new google.maps.Size(256, 256),
    maxZoom: 19,
    minZoom: 10,
    name: "GPXTrack",
    alt: 'User contributed GPX'
};

// 以下為背景圖
//var OSM_GDEM_Options = {
//	maxZoom: 18,
//	minZoom: 13,
//	name: 'GDEM',
//	tileSize: new google.maps.Size(256, 256),
//	getTileUrl: function(a,b) {
//		var z=b;
//		return "http://129.206.74.245:8006/tms_il.ashx?x="+ a.x + "&y=" + a.y +"&z=" + b;
//	}
//}
var Taiwan_General_2011_MapOptions = {
    getTileUrl: function(a, b) {
        var set = "PHOTO2";
        var road = "EMAP1";
        //return 'http://maps.nlsc.gov.tw/S_Maps/wmts?SERVICE=WMTS&REQUEST=GetTile&VERSION=1.0.0&LAYER=PHOTO2&STYLE=_null&TILEMATRIXSET=EPSG%3A3857&TILEMATRIX=' + b + "&TILEROW=" + a.y + "&TILECOL=" + a.x + "&FORMAT=image%2Fpng";
		return 'http://wmts.nlsc.gov.tw/wmts/PHOTO2/default/EPSG:3857/'+b+'/'+a.y+'/'+a.x;
    },
    tileSize: new google.maps.Size(256, 256),
    //maxZoom: 16,
    //minZoom: 6,
    maxZoom: 19,
    minZoom: 9,
    name: "NLSC",
    alt: "內政部國土測量中心 2011"
};
//var Taiwan_Formosat_2011_MapOptions = {
//	getTileUrl: function(a, b) {
//		var z = 17 - b;
//		return "http://gis.sinica.edu.tw/googlemap/Formosat_Taiwan_2011/" + z + "/"+ a.x + "/IMG_" + a.x + "_" + a.y + "_" + z + ".png";
//	},
//	tileSize: new google.maps.Size(256, 256),
//	maxZoom: 16,
//	minZoom: 6,
//	name: "台灣福衛2號2011"
//}
var OSM_Options = {
    getTileUrl: function(a, b) {
        return "http://a.tile.openstreetmap.org/" + b + "/" + a.x + "/" + a.y + ".png";
    },
    tileSize: new google.maps.Size(256, 256),
    maxZoom: 19,
    name: "OSM",
    alt: "Open Street Map"
};
var MOI_OSM_Options = {
    getTileUrl: function(a, b) {
      //  return "http://rs.happyman.idv.tw/map/moi_osm/" + b + "/" + a.x + "/" + a.y + ".png";
	return "http://rudy.tile.basecamp.tw/" + b + "/" + a.x + "/" + a.y + ".png";
    },
    tileSize: new google.maps.Size(256, 256),
    maxZoom: 19,
    name: "OSM",
    alt: "MOI_OSM @Rudy"
};
var MOI_OSM_TWMAP_Options = {
    getTileUrl: function(a, b) {
        return "http://rs.happyman.idv.tw/map/moi_osm/" + b + "/" + a.x + "/" + a.y + ".png";
    },
    tileSize: new google.maps.Size(256, 256),
    maxZoom: 19,
    name: "OSM",
    alt: "MOI_OSM @Rudy"
};
var Hillshading_Options = {
    getTileUrl: function(a, b) {
        return 'http://rs.happyman.idv.tw/map/hillshading/' + b + "/" + a.x + "/	" + a.y + ".png";
    },
    tileSize: new google.maps.Size(256, 256),
    maxZoom: 19,
    name: "陰影",
    alt: "Taiwan hillshading"
};
var MOI_OSM_GPX_Options = {
    getTileUrl: function(a, b) {
        return 'http://rs.happyman.idv.tw/map/moi_osm_gpx/' + b + "/" + a.x + "/	" + a.y + ".png";
    },
    tileSize: new google.maps.Size(256, 256),
    maxZoom: 19,
    name: "OSM_GPX",
    alt: "MOI_OSM +GPX"
};

var Darker_Options = {
    getTileUrl: function(a, b) {
        return "http://b.basemaps.cartocdn.com/dark_all/" + b + "/" + a.x + "/" + a.y + ".png";
    },
    tileSize: new google.maps.Size(256, 256),
    maxZoom: 19,
    name: "theme",
    alt: "Darker Matter from CartoDB"
};
var Fandi_Options = {
    getTileUrl: function(a, b) {
        var z = 17 - b;
        return "http://gis.sinica.edu.tw/googlemap/JM50K_1916/" + z + "/" + a.x + "/IMG_" + a.x + "_" + a.y + "_" + z + ".jpg";
    },
    tileSize: new google.maps.Size(256, 256),
    name: "蕃地",
    alt: "日治五萬分之一蕃地地形圖 1916",
    maxZoom: 17
};

var JM50K1924_Options = {
    getTileUrl: function(a, b) {
        var z = 17 - b;
        return "http://gis.sinica.edu.tw/googlemap/JM50K_1924/" + z + "/" + a.x + "/IMG_" + a.x + "_" + a.y + "_" + z + ".jpg";
    },
    tileSize: new google.maps.Size(256, 256),
    name: "陸測",
    alt: "日治五萬分之一(陸軍測量部 1924)",
    maxZoom: 17
};
var TW50K1956_Options = {
    getTileUrl: function(a,b) {
 	var z = 17-b;
	return "http://gis.sinica.edu.tw/googlemap/TM50K_1956/" + z + "/" + a.x + "/IMG_" + a.x + "_" + a.y + "_" + z + ".jpg";
    },
    tileSize: new google.maps.Size(256, 256),
    name: "老5萬",
    alt: "1956 台灣五萬分之一(依據美國陸軍製圖局 1951)",
    maxZoom: 17
};
var TW5KArielPIC_Options = {
	getTileUrl: function(a, b) {
		var y_tms = (1 << b) - a.y - 1;
        return "http://rs.happyman.idv.tw/~mountain/tw5k/"+ b + "/" + a.x + "/" + y_tms + ".png";
    },
    tileSize: new google.maps.Size(256, 256),
    maxZoom: 17,
    name: "TW5K",
    alt: "2000年五千分之一相片基本圖"
};
// 前景
var TaiwanMapV1MapType = new google.maps.ImageMapType(TaiwanMapV1Options);
var TaiwanMapType = new google.maps.ImageMapType(TaiwanMapOptions);
var TaiwanGpxMapType = new google.maps.ImageMapType(TaiwanGpxMapOptions);
var MOI_OSM_GPX_MapType = new google.maps.ImageMapType(MOI_OSM_GPX_Options);
var MOI_OSM_TWMAP_MapType = new google.maps.ImageMapType(MOI_OSM_TWMAP_Options);
//  背景
var Taiwan_General_2011_MapType = new google.maps.ImageMapType(Taiwan_General_2011_MapOptions);
var OSM_MapType = new google.maps.ImageMapType(OSM_Options);
var MOI_OSM_MapType = new google.maps.ImageMapType(MOI_OSM_Options);
var Darker_MapType = new google.maps.ImageMapType(Darker_Options);
var FanDi_MapType = new google.maps.ImageMapType(Fandi_Options);
var JM50K1924_MapType = new google.maps.ImageMapType(JM50K1924_Options);
var TW50K1956_MapType = new google.maps.ImageMapType(TW50K1956_Options);
var Hillshading_MapType = new google.maps.ImageMapType(Hillshading_Options);
var TW5KArielPIC_MapType = new google.maps.ImageMapType(TW5KArielPIC_Options);


// 前景路圖
var GoogleNameMapType = new google.maps.ImageMapType(GoogleNameOptions);
var NLSCNameMapType = new google.maps.ImageMapType(NLSCNameOptions);
var GPXTrackMapType = new google.maps.ImageMapType(GPXTrackOptions);


//圖資 copyright
// http://stackoverflow.com/questions/5489811/showing-map-copyright-in-gmaps-api-v3
var copyrightDiv, logoDiv;
var google_div__span_created = 0;
var copyrights =    {
	 'twmapv1' : "<a target=\"_blank\" href=\"http://gissrv4.sinica.edu.tw/gis/twhgis.aspx\">台灣歷史百年地圖</a> - 經建一版 1985~1989",
	 'taiwan' : "<a target=\"_blank\" href=\"http://gissrv4.sinica.edu.tw/gis/twhgis.aspx\">台灣歷史百年地圖</a> - 經建三版 1999~2001",
	 'general2011': "<a target=\"_blank\" href=\"http://www.nlsc.gov.tw/News/Detail/1256?level=458\">NLSC</a> - 通用版電子地圖正射影像",
	 'moi_osm': '&copy; <a href="http://www.openstreetmap.org/" target="_blank">OpenStreetMap</a> contributors, CC-BY-SA' + "<a target=\"_blank\" href=\"https://dl.dropboxusercontent.com/u/899714/maps/taiwan_topo.html\">Taiwan TOPO By Rudy</a>",
	 'fandi': "<a target=\"_blank\" href=\"http://gissrv4.sinica.edu.tw/gis/twhgis.aspx\">台灣歷史百年地圖</a> - 番地地形圖 1907~1916",
	 'jm50k': "<a target=\"_blank\" href=\"http://gissrv4.sinica.edu.tw/gis/twhgis.aspx\">台灣歷史百年地圖</a> - 日治五萬分之一(陸軍測量部 1924~1944)",
	 'tw50k': "<a target=\"_blank\" href=\"http://gissrv4.sinica.edu.tw/gis/twhgis.aspx\">台灣歷史百年地圖</a> - 台灣五萬分之一(依據美國陸軍製圖局 1951~1956)",
	 'hillshading': "<a target=\"_blank\" href=\"http://blog.nutsfactory.net/2016/09/14/taiwan-moi-20m-dtm/\">內政部數值網格資料</a> - 山區陰影圖層",
	 'tw5kariel': "台灣5000:1相片基本圖"
};
var logos =  {   
	'tw25k_v1': "經1版",
	'tw25k_v3': "經3版",
	'moi_osm': 'MOI_OSM' 
};

function CopyrightChange(){
				var editurl = '';
                newMapType = map.getMapTypeId();
				fMap = $("#changemap").val(); // 前景圖
                if (!google_div__span_created) { 
						logoDiv = document.createElement("div");
						copyrightDiv = document.createElement("div");
						copyrightDiv.index = 0; // used for ordering 
						map.controls[google.maps.ControlPosition.BOTTOM_LEFT].push(logoDiv);  
                        
						map.controls[google.maps.ControlPosition.BOTTOM_LEFT].push(copyrightDiv);
                        google_div__span_created = 1;
                }

                if (newMapType in copyrights)
                        copyrightDiv.innerHTML = copyrights[newMapType];
                else
                        copyrightDiv.innerHTML = "";

                if (fMap in logos)  {
						var c = map.getCenter().toUrlValue(5).split(',');
						editurl = 'https://www.openstreetmap.org/edit#map='+ map.getZoom() +'/'+ c[0]+'/'+c[1];
						logoDiv.innerHTML = logos[fMap].replace('MOI_OSM','<a href="'+ editurl + '" target=_blank>[編輯OSM]</a>');
					   //logoDiv.innerHTML = logos[fMap];
				}else
                        logoDiv.innerHTML = "";

}
// 
var BackgroundMapType;
var BackgroundMapOptions;
var BackgroundMap = 0;
var oms;
var markerCluster;
var allmarkers = [];
var show_marker = "a"; // getParameterByName("show_marker")? getParameterByName("show_marker") : 1;
var myInfoBoxOptions = {
    disableAutoPan: false,
    maxWidth: 300,
    alignBottom: true,
    pixelOffset: new google.maps.Size(-100, -35),
    boxClass: "ui-corner-all infobox",
    zIndex: null,
    boxStyle: {
        // background: "url('http://google-maps-utility-library-v3.googlecode.com/svn/trunk/infobox/examples/tipbox.gif') no-repeat",
        opacity: 0.75,
        width: "200px"
    },
    closeBoxMargin: "2px 2px 2px 2px",
    closeBoxURL: "//www.google.com/intl/en_us/mapfiles/close.gif",
    pane: "floatPane",
    enableEventPropagation: false,
    infoBoxClearance: "10px"
};
// 控制背景的透明度
function showOp(op) {
    //document.getElementById('opv').innerHTML = parseInt(op * 100);
    $('#opv').html(parseInt(op * 100));
}
// 更改透明度
function changeBackgroundOpacity(op) {
    map.overlayMapTypes.removeAt(0, BackgroundMapType);
    BackgroundMapOptions.opacity = op;
    BackgroundMapType = new google.maps.ImageMapType(BackgroundMapOptions);
    opacity = op;
    map.overlayMapTypes.insertAt(0, BackgroundMapType);
    // 要更新 InfoWindow 裡頭的 Link
    updateView("info_only");
}

function showInsideMarkers() {
    // alert(tags_ready + " " + markers_ready + " " + show_label);
    var j = 0;
    var i;
    if (show_label === 0 || tags_ready === 0 || markers_ready === 0) return;
    var bounds = map.getBounds();
    // 太小範圍不顯示
    if (map.getZoom() < 12) {
        for (i = 0; i < markerArrayMax; i++) {
            markerArray[i].setMap(null);
            labelArray[i].setMap(null);
        }
        return;
    }
    for (i = 0; i < availableTags.length; i++) {
        if (bounds && bounds.contains(availableTagsLocation[i])) {
            if (j >= markerArrayMax) return;
            // 只秀沒被隱藏的
            if (!allmarkers[i].getVisible()) continue;
            markerArray[j].setPosition(availableTagsLocation[i]);
            markerArray[j].setTitle(availableTags[i]);
            markerArray[j].setMap(map);
            labelArray[j].bindTo('position', markerArray[j], 'position');
            labelArray[j].bindTo('text', markerArray[j], 'title');
            labelArray[j].setMap(map);
            j++;
        }
    }
    // clear rest stuff
    // alert(availableTags.length + " " + j);
    for (i = j; i < markerArrayMax; i++) {
        markerArray[i].setMap(null);
        labelArray[i].setMap(null);
    }
}
var bigPoly;
var grid; // for wgs84 grid
function showGrid(grid_type) {
    var vp = map.getBounds();
    var vpp = [];
    var twp = [];
    var newp = [];
    var dstBound = TW_Bounds;
    var ph = 0;
    var i;
    if (grid_type == "TWD67PH") {
        dstBound = PH_Bounds;
        ph = 1;
    } else if (grid_type == "WGS84") {
        if (!grid) grid = new Graticule(map, true);
        else grid.show();
        return;
    } else if (grid_type == "None") {
        if (grid) grid.hide();
        // clean grid
        if (poly)
            for (i = 0; i < poly.length; i++) poly[i].setMap(null);
        if (polylabel)
            for (i = 0; i < polylabel.length; i++) polylabel[i].setMap(null);
        return true;
    }
    var InterBounds;
    if (vp && vp.intersects(dstBound)) {
        // 左下 x 取大
        if (vp.getSouthWest().lng() > dstBound.getSouthWest().lng()) newp[1] = vp.getSouthWest().lng();
        else newp[1] = dstBound.getSouthWest().lng();
        // 左下 y 取大
        if (vp.getSouthWest().lat() > dstBound.getSouthWest().lat()) newp[0] = vp.getSouthWest().lat();
        else newp[0] = dstBound.getSouthWest().lat();
        // 右上 y 取小
        if (vp.getNorthEast().lat() < dstBound.getNorthEast().lat()) newp[2] = vp.getNorthEast().lat();
        else newp[2] = dstBound.getNorthEast().lat();
        // 右上 x 取小
        if (vp.getNorthEast().lng() < dstBound.getNorthEast().lng()) newp[3] = vp.getNorthEast().lng();
        else newp[3] = dstBound.getNorthEast().lng();
        // 重合的區域
        InterBounds = new google.maps.LatLngBounds();
        InterBounds.extend(new google.maps.LatLng(newp[0], newp[1]));
        InterBounds.extend(new google.maps.LatLng(newp[2], newp[3]));
    }
    if (!bigPoly) bigPoly = new google.maps.Rectangle({
        map: map,
        fillColor: "red",
        fillOpacity: 0.1
    });
    if (typeof InterBounds !== "undefined") {
        //bigPoly.setBounds(InterBounds);
        var sw = lonlat_getblock(InterBounds.getSouthWest().lng(), InterBounds.getSouthWest().lat(), ph, 100);
        var ne = lonlat_getblock(InterBounds.getNorthEast().lng(), InterBounds.getNorthEast().lat(), ph, 100);
        var minx = sw[0].x;
        var maxx = ne[1].x;
        var miny = sw[1].y;
        var maxy = ne[0].y;
        // 畫 grid
        lonlat_range_getblock(minx, miny, maxx, maxy, ph, grid_type);
    }
}
var rainkml;
function showCWBRainfall(fcast_type) {
	if (rainkml && rainkml.term) {
		if (rainkml.term == fcast_type || rainkml.loading == 1 ) return;
		rainkml.removeDocument(rainkml.docs[0]);
	}
	if (fcast_type == 'none') {
		if (rainkml) rainkml.removeDocument(rainkml.docs[0]);
		return;
	}
	rainkml =  new geoXML3.parser({
        map: map,
        singleInfoWindow: true,
        //additional_marker_desc: decodeURIComponent(uri_enc),
        zoom: false,
    });
	rainkml.loading = 1;
        rainkml.parse('data/rainkml.php?term=' + fcast_type);

    google.maps.event.addListener(rainkml, 'parsed', function() {
        rainkml.term = fcast_type;
	rainkml.loading = 0;
    });
	
}
var skml;
/* global geoXML3 */
// zoom == true 則會移到地圖位置
var top_noty;
function showmapkml(mid, marker_desc, additional_marker_desc, zoom, need_center_marker) {
    if (skml) {
        if (skml.loading == 1) {
			console.log("showmapkml: kml is loading return");
			return;
		}
		if (skml.mid == mid) {
			skml.removeDocument(skml.docs[0]);
			skml.mid = 0;
			skml.title = '';
			skml.additional_marker_desc = '';
			console.log("showmapkml: remove kml");
			return;
		}
		skml.removeDocument(skml.docs[0]);
    }
	console.log("showmmapkml: mid=" + mid );
    skml = new geoXML3.parser({
        map: map,
        singleInfoWindow: true,
        additional_marker_desc: decodeURIComponent(additional_marker_desc),
        zoom: zoom,
    });
    skml.loading = 1;
	topnoty = noty({text: 'kml 載入中...', layout:'top'});
    skml.parse(getkml_url + "?mid=" + mid);
    google.maps.event.addListener(skml, 'parsed', function() {
        skml.mid = mid;
		skml.title = marker_desc;
		skml.additional_marker_desc = additional_marker_desc;
		skml.loading = 0;
		if (need_center_marker){
				$("#tags").val(map.getCenter().toUrlValue(5));
				$("#goto").trigger('click');
		}
		topnoty.close();
    });
}

function permLinkURL(goto) {
    // var ver = (BackgroundMap === 0) ? 3 : 1;
	var ver = $("#changemap").val();
    var curMap = $("#changegname").val();
    var curGrid = $("#changegrid").val();
	var skml_param = "";
	if (typeof skml !== 'undefined' && typeof skml.mid !== 'undefined' && skml.mid !== 0){
		skml_param = '&skml_id=' + skml.mid;
	}
    return "<a href=# id='permlinkurl' data-url='" + window.location.origin + location.pathname + "?goto=" + goto + "&zoom=" + map.getZoom() + "&opacity=" + opacity.toFixed(4) + "&mapversion=" + ver + "&maptypeid=" + map.getMapTypeId() + "&show_label=" + show_label + "&show_kml_layer=" + show_kml_layer + "&show_marker=" + show_marker + "&roadmap=" + curMap + "&grid=" + curGrid + "&theme=" + theme + "&show_delaunay=" + show_delaunay + "&rainfall="+ $("#rainfall").val() + "&mcover=" + $("#mcover").val() + skml_param + "'><img src='img/permalink.png' width=30 border=0/></a>";
}

var MapStateRestored = 0;
function saveMapState() { 
    if (MapStateRestored === 0) return;
    var mapCenter=map.getCenter();  
    // var ver = (BackgroundMap === 0) ? 3 : 1;
	var ver = $("#changemap").val();
    var curMap = $("#changegname").val();
    var curGrid = $("#changegrid").val();
    var state = { "zoom": map.getZoom(), "opacity": opacity, "mapversion": ver, "maptypeid": map.getMapTypeId(),
                  "show_label": show_label, "show_kml_layer": show_kml_layer , "show_marker": show_marker, "roadmap": curMap, "grid": curGrid, "theme": theme,
	"goto": mapCenter.toUrlValue(5), "show_delaunay": show_delaunay , "rainfall": $("#rainfall").val(), "mcover": $('#mcover').val()};
	

    localStorage.setItem("twmap_state", JSON.stringify(state));
    console.log("mapState saved");
} 
function restoreMapState(state) {
        if (state.show_label == '0')  
		$("#label_sw").trigger('click');
        if (state.show_marker) {
            show_marker = state.show_marker;
            if (show_marker == '0') 
			$("#marker_sw_select").val([]);
            else 
			$("#marker_sw_select").val(show_marker.split(","));
            $("#marker_sw_select").dropdownchecklist("refresh");
            markerFilter();
        }
        if (state.show_kml_layer === 0)  $("#kml_sw").trigger('click');
        if (state.zoom)map.setZoom(parseInt(state.zoom));
        if (state.maptypeid) map.setMapTypeId(state.maptypeid);
        if (state.roadmap) {
                $("#changegname").val(state.roadmap);
                $("#changegname").change();
        }
        if (state.grid) {
                $("#changegrid").val(state.grid);
                $("#changegrid").change();
        }
	if (state.rainfall) {
                $("#rainfall").val(state.rainfall);
                $("#rainfall").change();
	}
	if (state.mcover) {
                $("#mcover").val(state.mcover);
                $("#mcover").change();
	}
    // if (state.mapversion == 1)  $("#changemap").trigger('click');
	 if (state.mapversion) {
                $("#changemap").val(state.mapversion);
                $("#changemap").change();
     }
       

	if (state.show_delaunay == 1 ) {
	   $("#delaunay_sw").trigger('click');
	}
	var need_center_marker = 1;
	var initial_loc = 0;
	
	if (state.kml) {
		    console.log("get kml parameter");
			GPSLayer = new geoXML3.parser({
			map: map,
			singleInfoWindow: true,
			additional_marker_desc: "",
			zoom: true,
		});
		GPSLayer.parse(state.kml);
			google.maps.event.addListener(GPSLayer, 'parsed', function() {
			// 避免沒有 centerMarker 
			if (need_center_marker){
				$("#tags").val(map.getCenter().toUrlValue(5));
				$("#goto").trigger('click');
			}
			});
			initial_loc = 1;
	} else if (state.skml_id && state.skml_id !==0){
		showmapkml(state.skml_id, "", "" , true, need_center_marker);
		initial_loc = 1;
	} else if (state.goto) {
		console.log("goto: " + state.goto);
        $("#tags").val(state.goto);
        $("#goto").trigger('click');
		// need_center_marker = 0;
		initial_loc = 1;
    }
	// 最後都沒有移動位置, 就試試看取得現在位置, 不然就跳到特殊點
	if (initial_loc === 0){
		   console.log("getgeolocation");
            var position_get = 0;
            $.geolocation.get({
                win: function(position) {
                    CurrentLocation(position);
                    position_get = 1;
                },
                fail: FeatureLocation,
                error: FeatureLocation
            });
            setTimeout(function() {
                if (position_get === 0) {
                    FeatureLocation();
                }
            }, 4000);
	}
	MapStateRestored = 1;
	console.log("mapState restored");
}


var initial_meerkat = 1; // 第一次顯示
var last_pos = {};
/*
global get_waypints_url
 */
function locInfo(newpos, callback, param) {
    // 1. 檢查圖層是否是 Gpx 圖層
    if (last_pos == newpos) {
        console.log("position not change");
        return;
    }
    var radius = (show_kml_layer == 1) ? (20 - map.getZoom()) * 10 - 10 : 0;
    $.ajax({
        dataType: 'json',
        url: get_waypoints_url,
        cache: false,
        data: {
            "y": newpos.lat(),
            "x": newpos.lng(),
            "r": radius,
            "detail": 0
        }
    }).done(function(data) {
         // toggle login
		 	if (data.rsp.info){
				toggle_user_role(1);
			} else {
				toggle_user_role(0);
			}
        if (data.ok === true && data.rsp.wpt !== "undefined") {
            locInfo_name = "GPS 航跡資訊";
            var extra = [];
	    var index = 0;
            for (index = 0; index < data.rsp.wpt.length; ++index) {
                extra.push("<b>" + data.rsp.wpt[index].name + "</b>");
            }
            for (index = 0; index < data.rsp.trk.length; ++index) {
                extra.push("<b>" + data.rsp.trk[index].name + "</b>");
            }
            var extra_info = "<br>" + extra.splice(0,3).join();
            var extra_url = get_waypoints_url + "?x=" + newpos.lng() + "&y=" + newpos.lat() + "&r=" + radius + "&detail=1";
            extra_info += "<a href=# onClick=\"showmeerkat('" + extra_url + "',{ 'width': '600'} )\"><img src='img/icon-download.gif' border=0></a>";
            locInfo_show(newpos, Number(data.rsp.ele), {
                "content": extra_info,
                "radius": radius
            });
            last_pos = newpos;
            // 如果已經打開
            //	if (initial_meerkat || $("#meerkat-wrap").is(":visible")) {
            showmeerkat(extra_url, {
                'width': '600'
            });
			map.panTo(newpos);
            initial_meerkat = 0;
            //	}
        } else {
            // 2. 非航點 -- 檢查高度,產生 infowin
            var close_infowin = ((!callback) ? 0 : 1);
	    $.ajax({
		dataType: 'json',
		url: get_elev_url,
		data: {
			"loc": newpos.lat() + "," + newpos.lng()
		}
	    }).done(function(data) {
		var ele;
		if (data.ok === true ) {
			ele = Number(data.rsp.elevation);
		} else {
			ele = -20000;
		}
		var extra_info;
		if (data.rsp.admin) {
			extra_info = "<br><a class='weather-link' href='" + data.rsp.weather_forcast_url + "' target=_blank>" + data.rsp.admin + "</a>";
		}
		if (data.rsp.nature){
			 extra_info += "<br>" + data.rsp.nature;
		}
		if (data.rsp.tribe_weather) {
			extra_info +="<br>原鄉: " + data.rsp.tribe_weather;
		}
		// console.log(data);
		locInfo_show(newpos, ele, { "callback": callback, "content": extra_info ,"param": param, "close": close_infowin });
	    });
        }
    }); // done
}
// location Information
function locInfo_show(newpos, ele, extra) {
    //console.log( "locInfo:"+locInfo_name);
    var ph;
    var comment, comment2;

    var ll = is_taiwan(newpos.lat(), newpos.lng());
	ph = (ll == 2)? 1 : 0;
	var p = lonlat2twd67(newpos.lng(), newpos.lat(), ph);
	var p2 = lonlat2twd97(newpos.lng(), newpos.lat(), ph);
    if (ll == 2) {
        comment = "澎湖 TWD67 TM2:" + Math.round(p.x) + "," + Math.round(p.y);
		comment2 = "澎湖 TWD97 TM2:" + Math.round(p2.x) + "/" + Math.round(p2.y);
    } else {

        comment = "台灣 TWD67 TM2:" + Math.round(p.x) + "," + Math.round(p.y);
		comment2 = "台灣 TWD97 TM2:" + Math.round(p2.x) + "/" + Math.round(p2.y);
    }
	var p3 = lonlat2cad(newpos.lng(), newpos.lat(),"j");
	var comment3 = "地籍座標:(日間) cj:"+ p3.x.toFixed(2) + "," + p3.y.toFixed(2);
    var content = "<div class='infowin'>" + locInfo_name + "";
    if (locInfo_name == "我的位置" || locInfo_name == "GPS 航跡資訊") content += permLinkURL(newpos.toUrlValue(5));
    else content += permLinkURL(encodeURIComponent(locInfo_name));
    if (extra.content) content += extra.content;
    content += "<br>經緯度: " + newpos.toUrlValue(5) + "<br>" + ConvertDDToDMS(newpos.lat()) + "," + ConvertDDToDMS(newpos.lng());
    if (ele > -1000) content += "<br>高度: " + ele.toFixed(0) + "M";
    content += "<br>座標: " + comment + "<br>" + comment2 + "<br>" + comment3;
   // /* allow from all points
    if (ele > -1000) 
	content += "<br>其他: <a href=# id='los_link' onClick='javascript:show_line_of_sight("+newpos.toUrlValue(5)+","+ele.toFixed(0)+")'><img id=\"los_eye_img\"  title='通視模擬' src=img/eye.png width=32/></a>";
	content += "<a href='http://mc.basecamp.tw/#" + map.getZoom() + "/" + newpos.lat().toFixed(4) +"/"+ newpos.lng().toFixed(4) + "' target='mc'><img src='img/mc.png' title='地圖對照器' /></a>";
	content += "<a href=# onClick=\"showmeerkat('" + promlist_url + "',{}); return false;\"><img src='/icon/%E7%8D%A8%E7%AB%8B%E5%B3%B0.png' /></a>";
				
    //*/
    if (login_role == 1) {
        if (locInfo_name == "我的位置") 
			content += "<br><a href=# onClick=\"showmeerkat('" + pointdata_admin_url + "?x=" + newpos.lng().toFixed(5) + "&y=" + newpos.lat().toFixed(5) + "',{});return false\">新增</a>";
        else 
			content += "<br><a href=# onClick=\"showmeerkat('" + pointdata_admin_url + "?x=" + newpos.lng().toFixed(5) + "&y=" + newpos.lat().toFixed(5) + "&name=" + locInfo_name + "',{});return false\">新增</a>";
    }
    content += "</div>";
    centerInfo.setContent(content);
    centerMarker.setTitle("座標位置");
    // ???
    centerInfo.open(map, centerMarker);
    // updateView 會重新刷一次
    showCenterMarker_id = "";
    // add extra
    if (extra.close && extra.close == 1) {
        console.log("extra.close=" + extra.close);
        centerInfo.close();
    }
    if (extra.callback) extra.callback(extra.param);
    if (extra.radius) {
        circle.set('radius', parseInt(extra.radius, 10));
        circle.setMap(map);
        console.log("show circle");
    } else {
        console.log("hide circle");
        circle.setMap(null);
    }
}
// Tags Info
function tagInfo(newpos, id) {
	var ph;
	var comment, comment2;
    var ll = is_taiwan(newpos.lat(), newpos.lng());
	ph = (ll == 2)? 1 : 0;
	var p = lonlat2twd67(newpos.lng(), newpos.lat(), ph);
	var p2 = lonlat2twd97(newpos.lng(), newpos.lat(), ph);
    if (ll == 2) {
        comment = "澎湖 TWD67 TM2: " + Math.round(p.x) + "," + Math.round(p.y);
		comment2 = "澎湖 TWD97 TM2: " + Math.round(p2.x) + "/" + Math.round(p2.y);
    } else {
        comment = "台灣 TWD67 TM2: " + Math.round(p.x) + "," + Math.round(p.y);
		comment2 = "台灣 TWD97 TM2: " + Math.round(p2.x) + "/" + Math.round(p2.y);
    }
    var p3 = lonlat2cad(newpos.lng(), newpos.lat(),"j");
	var comment3 = "地籍座標:(日間) cj:"+ p3.x.toFixed(2) + "," + p3.y.toFixed(2);
    $.ajax({
        dataType: 'json',
        cache: false,
        url: pointdata_url,
        data: {
            "id": id,
        },
        success: function(data) {
	    if (typeof data[0].name == "undefined") {
				content = "<div class='infowin'><b>" + data[0].name + "</b>";
				content += "id: " + id + "有誤";
				content += "</div>";
			} else {
				content = "<div class='infowin'><b>" + data[0].name + "</b>";
				content += permLinkURL(encodeURIComponent(data[0].name));
				content += "<br>座標: " + comment + "<br>" + comment2 + "<br>"+ comment3; 
				content += "<br>經緯度: " + newpos.toUrlValue(5) + "<br>" + ConvertDDToDMS(newpos.lat()) + "," + ConvertDDToDMS(newpos.lng());
				content += data[0].story;
				content += "<br>其他: <a href=# id='los_link' onClick='javascript:show_line_of_sight("+newpos.toUrlValue(5)+","+data[0].ele+")'><img title='通視模擬' id=\"los_eye_img\" src=img/eye.png width=32/></a>";
				content += "<a href='http://mc.basecamp.tw/#" + map.getZoom() + "/" + newpos.lat().toFixed(4) +"/"+ newpos.lng().toFixed(4) + "' target='mc' ><img src=img/mc.png title='地圖對照器' /></a>";
				content += "</div>";
			}
			centerInfo.setContent(content);
			centerInfo.open(map, centerMarker);
			if (data[0].info){
				toggle_user_role(1);
			} else {
				toggle_user_role(0);
			}
	}});
    showCenterMarker_id = id;
}
var line_of_sight_lines = [];
var line_of_sight_running = 0;
var line_of_sight_display_xyz = "";
function show_line_of_sight(y,x,z){
	var names = [];
	if (line_of_sight_running == 1 ) {
		alert("搓到眼睛．．好痛");
		 return;
	}
	// 再點一次即消失原來的線條
	var input = x + "_" +  y + "_" + z;
	if (line_of_sight_display_xyz == input ) {
		for(var i=0; i< line_of_sight_lines.length; i++){
			if (line_of_sight_lines[i])
				line_of_sight_lines[i].setMap(null);
		}
		// 重設
		line_of_sight_display_xyz = "";
		return;
	}
	line_of_sight_display_xyz = input;
	$('#los_eye_img').attr('src',"img/eye_a.gif");
	line_of_sight_running = 1;
	topnoty = noty({text: '通視模擬計算中.....', layout:'top'});
	$.ajax({
		dataType: 'json', 
		url: viewshed_url, 
		data: { "x": x,	"y": y, "z": z },
		success: function(data){
				
				if (data.ok !== true){
					console.log("error show_line_of_sight response" + data);
					return;
				}
					
				// 1. clean line_of_sight_lines
				for(var i=0; i< line_of_sight_lines.length; i++){
					if (line_of_sight_lines[i])
						line_of_sight_lines[i].setMap(null);
				}
				// 2. draw lines
				
				for(i=0;i < data.rsp.length; i++){
					var d = data.rsp[i];
					//if (d[0] === true && d[3] > 3000)
					//	names[i] = d[2];
					
					line_of_sight_lines[i] = new google.maps.Polyline({
						path: [new google.maps.LatLng(y, x), new google.maps.LatLng(d[1][1],d[1][0])],
						map: map,
						geodesic: true,
						strokeColor: (d[0] === true) ? '#FF0000' : "#FF00FF",
						strokeOpacity: 1.0,
						strokeWeight: (d[0] === true) ? 2 : 1
					});
				}
				// 3. done
				// console.log(names);
				line_of_sight_running = 0;
				topnoty.close();
				$('#los_eye_img').attr('src',"img/eye.png");
				console.log("done show_line_of_sight");
		}
	});
}
var circle;

function showCenterMarker(name) {
    var i;
    if (name === '') {
       // alert("請輸入");
	showmeerkat(pointdata_url + "?lastest=10&err=1" ,{ 'width': '600'} );
        return;
    }
    if (!circle) {
        circle = new google.maps.Circle({
            map: map,
            radius: 200,
            fillColor: '#AA00000'
        });
    }
    var got_name = 0;
    for (i = 0; i < availableAlias.length; i++) {
	if (name == availableAlias[i]) {
		// 直接 mapping 置換 alias
		name = availableAliasMap[i];
		console.log("got alias " + availableAlias[i] + "=" + name);
		break;
        }
    }
    for (i = 0; i < availableTags.length; i++) {
        if (name == availableTags[i]) {
	    got_name = 1;
            map.panTo(availableTagsLocation[i]);
            // 每次都建立一個 marker, 以免拉動之後消失
            // if (!centerMarker) {
            if (centerMarker) centerMarker.setMap(null);
            centerMarker = new google.maps.Marker({
                title: availableTags[i],
                position: availableTagsLocation[i],
                draggable: true,
                map: map,
                zIndex: 10000
            });
            circle.bindTo('center', centerMarker, 'position');
            if (!centerInfo) {
		initialCenterInfo();
            }
            tagInfo(availableTagsLocation[i], availableTagsMeta[i].id);
            // 放入 cookie
// drag event
    google.maps.event.addListener(centerMarker, "dragend", centerMarkerDragEnd );
    google.maps.event.addListener(centerMarker, "dragstart", centerMarkerDragStart );
//
           // $.cookie('twmap3_goto', name);
	    break;
        }
    }
    if (got_name == 1) {
            google.maps.event.addListener(centerMarker, 'click', function() {
                centerInfo.open(map, centerMarker);
            });
            return true;
    }
    if (name === "" && centerMarker) {
        name = centerMarker.getPosition().toUrlValue(5);
        // alert(name);
    }
    // google earth type
    name = name.replace("°","");

    // 如果沒找到, 看看格式對不對, 移動到座標
    var posxy = name.match(/^(\d+\.?\d+)\s*,\s*(\d+\.\d+)$/);
    var posxy1 = name.match(/^(\d+\.?\d+)\s+(\d+\.\d+)$/);
    var postw67 = name.match(/^(\d+)\s*,\s*(\d+)$/);
    var postw671 = name.match(/^(\d+)\s+(\d+)$/);
    var postw97 = name.match(/^(\d+)\s*\/\s*(\d+)$/);
	// Cadastral coordinates
    var  cm = name.match(/^cm:\s*(\-?\d+\.?\d+)\s*,\s*(\-?\d+\.\d+)$/);
    var  cj = name.match(/^cj:\s*(\-?\d+\.?\d+)\s*,\s*(\-?\d+\.\d+)$/);
    var loc;
    var tmploc;
    var tt;
    if (posxy) {
        tmploc = {
            x: posxy[2],
            y: posxy[1]
        };
    } else if (posxy1) {
        tmploc = {
            x: posxy1[2],
            y: posxy1[1]
        };
    } else if (postw67) {
        tmploc = twd672lonlat(postw67[1], postw67[2], 0);
    } else if (postw671) {
        tmploc = twd672lonlat(postw671[1], postw671[2], 0);
    } else if (postw97) {
        tmploc = twd972lonlat(postw97[1], postw97[2], 0);
    } else if (cm) {
	tt = cad2twd67(cm[1],cm[2],'m');
        tmploc = twd672lonlat(tt[0], tt[1], 0);
    } else if (cj) {
	tt = cad2twd67(cj[1],cj[2],'j');
        tmploc = twd672lonlat(tt[0], tt[1], 0);
    } else {
        // geocoding
        $.blockUI({
            message: "查詢中..."
        });
        $.ajax({
            dataType: 'json',
            cache: false,
            url: geocodercache_url,
            data: {
                "op": 'get',
                "data": JSON.stringify({
                    'address': name
                })
            },
            success: function(data) {
                if (data.ok === true) {
                    // alert("from cache");
                    if (data.rsp.is_tw === 0) {
                        //alert('cached: 不在台澎範圍內');
						showmeerkat(pointdata_url + "?lastest=10&err=4" ,{ 'width': '600'} );
                        return false;
                    }
                    $.unblockUI();
                    //console.log(data.rsp);
                    loc = new google.maps.LatLng(parseFloat(data.rsp.lat), parseFloat(data.rsp.lng));
                    showCenterMarker_real(loc, data.rsp.name);
                    //console.log(loc);
                    return false;
                } else {
                    // geocode
                    // alert("geocode "+name);
                    geocoder.geocode({
                        'address': name + ",Taiwan",
                        'region': 'TW'
                    }, function(results, status) {
                        if (status == google.maps.GeocoderStatus.OK) {
                            $.unblockUI();
                            loc = results[0].geometry.location;
                            var p = is_taiwan(loc.lat(), loc.lng());
                            if (p === 0) {
                                //alert("不在台澎範圍");
								showmeerkat(pointdata_url + "?lastest=10&err=2" ,{ 'width': '600'} );
                                return false;
                            }
                            //console.log(results);
                            // update only full match
                            if (results[0].address_components[0].long_name == name) exact = 1;
                            else exact = 0;
                            $.post(geocodercache_url, {
                                "op": 'set',
                                "data": JSON.stringify({
                                    'address': name,
                                    'lat': loc.lat(),
                                    'lng': loc.lng(),
                                    'is_tw': p,
                                    'exact': exact,
                                    'faddr': results[0].formatted_address,
                                    'name': results[0].address_components[0].long_name
                                })
                            }, function(data) {
                                return;
                                // alert("update cache" + data.ok);
                            });
                            showCenterMarker_real(loc, results[0].address_components[0].long_name);
                        } else {
                            $.unblockUI();
                            // alert("Geocode was not successful for the following reason: " + status);
                            //alert("找不到喔! 請輸入 地址 或 座標格式: 1. t67 X,Y 如 310300,2703000 2. t97 X/Y 或者 3. 含小數點經緯度 lat,lon 24.430623,121.603503");
							showmeerkat(pointdata_url + "?lastest=10&err=3" ,{ 'width': '600'} );
                            return false;
                        }
                    });
                    return false;
                }
            }, // success
            error: function() {
                $.unblockUI();
                alert("查詢程式有誤");
                return false;
            }
        }); // ajax
    } // else
    if (tmploc) {
        var p = is_taiwan(tmploc.y, tmploc.x);
        if (p === 0) {
            // alert("不在台澎範圍");
			noty({	text: '不在台澎範圍', type: 'alert',  closeWith   : ['click','timeout'], timeout     :5000 });
            return false;
        }
        loc = new google.maps.LatLng(tmploc.y, tmploc.x);
        showCenterMarker_real(loc);
    }
}

function centerMarkerDragEnd(e) {
        var newpos = centerMarker.getPosition();
        console.log("centerMarker dragend");
        locInfo(newpos);
}
function centerMarkerDragStart(e) {
        locInfo_name = "我的位置";
        console.log("centerMarker dragstart");
        if (centerInfo) centerInfo.close();

}
function showCenterMarker_real(loc, name) {
    //	console.log('fire showCenterMarker_real' + loc + name );
    if (centerMarker) centerMarker.setMap(null);
    centerMarker = new google.maps.Marker({
        title: "test",
        position: loc,
        draggable: true,
        map: map,
        zIndex: 10000
    });
    circle.bindTo('center', centerMarker, 'position');
    google.maps.event.addListener(centerMarker, "dragend", centerMarkerDragEnd );
    google.maps.event.addListener(centerMarker, "dragstart", centerMarkerDragStart );
    google.maps.event.addListener(centerMarker, 'click', function() {
        centerInfo.open(map, centerMarker);
    });
    if (!centerInfo) {
	initialCenterInfo();
    }
    map.panTo(loc);
    locInfo_name = (typeof name === "undefined") ? loc.toUrlValue(5) : name;
    locInfo(loc);
    // save cookie
    // $.cookie('twmap3_goto', name);
    return true;
}
function initialCenterInfo() {
	centerInfo = new InfoBox(myInfoBoxOptions);
	google.maps.event.addListener(centerInfo, "domready", function() {
			$('#permlinkurl').click(function(event) {
				event.preventDefault();
				$('#copylink').dialog();
				$('#copylinkurl').val($(this).data('url'));
				//$("#copylinlurl").select();
				$('#copylinkurlshort').show();
				//$('#copylinkurlgo').hide();
				$('#copylinkurlgo').click(function() {
					location.href=$('#copylinkurl').val();
				});
				$('#copylinkurlshort').click(function() {
					var link = 'http://to.ly/api.php?json=0&longurl=' + encodeURIComponent($('#copylinkurl').val());
					$.ajax({ url: link,dataType: 'html',
						success: function(data){
						$('#copylinkurl').val(data);
						//$('#copylinkurlgo').show();
						$("#copylinlurl").select();
						}});
					$('#copylinkurlshort').hide();
					}); // end of click
				});
	});

}

function initialtags(opt) {
	if (tags_ready == 1) return;
	availableTags = [];
	availableTagsLocation = [];
	availableTagsMeta = [];
	availableAlias = [];
	availableAliasMap = [];
	$.ajax({
dataType: 'json',
cache: false,
url: pointdata_url,
data: {
"id": "ALL"
},
success: function(data) {
          var j = 0;
            for (var i = 0; i < data.length; i++) {
                availableTags[i] = data[i].name;
		if (data[i].alias) {
		   availableAlias[j] = data[i].alias;	
		   availableAliasMap[j] = data[i].name;	
		   j++;
		}
                //console.log(data[i][0]);
                availableTagsLocation[i] = new google.maps.LatLng(data[i].y, data[i].x);
                availableTagsMeta[i] = {
                    id: data[i].id,
                    //sym: data[i].sym
                    type: data[i].type,
                    class: data[i].class,
                    mt100: data[i].mt100,
					prom: data[i].prominence,
					prom_idx: data[i].prominence_index,
		    owner: data[1].owner
                };
            }
            $("#tags").autocomplete({
                source: availableTags.concat(availableAlias)
            });
            $("#search_text").html("搜尋");
            $("#tags").prop('disabled', false);
            tags_ready = 1;
            // 初始完畢, 顯示 lables
            initialmarkers();
            showInsideMarkers();
        //
	if (show_delaunay == 1 ) {
		/*
	    drawDelaunayTriangulation(1, { strokeColor: "#FFFF00", strokeWeight: 3 });
	    drawDelaunayTriangulation(2, { strokeColor: "#01DF01", strokeWeight: 2 });
	    drawDelaunayTriangulation(3, { strokeColor: "#FF00FF", strokeWeight: 1 });
		*/
		create_survey_network(3);
		create_survey_network(2);
		create_survey_network(1);
        }
            if (opt.msg) {
                alert(opt.msg + "共" + availableTagsLocation.length + "筆資料");
            }
        }
		
    });
}

/* ------ 
 will remove 
 ---- */
 
// code from http://geocodezip.com/v3_GoogleMaps_triangulation.html
function jsts2googleMaps(geometry, longestDistance) {
  var coordArray = geometry.getCoordinates();
  var distance = (typeof longestDistance === "undefined")? 9999 : longestDistance;
  GMcoords = [];
  for (var i = 0; i < coordArray.length; i++) {
    GMcoords.push(new google.maps.LatLng(coordArray[i].y, coordArray[i].x));
  }
  // 過濾一下超過長度的 ploygon
  for (i=0; i< GMcoords.length-1; i++) {
	var dist = google.maps.geometry.spherical.computeDistanceBetween(GMcoords[i],GMcoords[i+1])/1000;
	if (dist > distance )
		return [];
	//console.log("dist="+ dist);
  }
  return GMcoords;
}

function GMapPolygonToWKT(poly)
{
// Start the Polygon Well Known Text (WKT) expression
 var wkt = "POLYGON(";

 var paths = poly.getPaths();
 for(var i=0; i<paths.getLength(); i++)
 {
  var path = paths.getAt(i);

  // Open a ring grouping in the Polygon Well Known Text
  wkt += "(";
  for(var j=0; j<path.getLength(); j++)
  {
    // add each vertice and anticipate another vertice (trailing comma)
    wkt += path.getAt(j).lng().toFixed(5) +" "+ path.getAt(j).lat().toFixed(5) +",";
  }
  
  // Google's approach assumes the closing point is the same as the opening
  // point for any given ring, so we have to refer back to the initial point
  // and append it to the end of our polygon wkt, properly closing it.
  //
  // Also close the ring grouping and anticipate another ring (trailing comma)
  wkt += path.getAt(0).lng().toFixed(5) + " " + path.getAt(0).lat().toFixed(5) + "),";
}

// resolve the last trailing "," and close the Polygon
 wkt = wkt.substring(0, wkt.length - 1) + ")";

 return wkt;
}

var delaunayGMpolys = [];
function drawDelaunayTriangulation(class_num, Options) {
       // clean 一下
       cleanDelaunayTriangulation(class_num);
       // 重畫
	var points = [];
	var defaults = { strokeColor: "#FF0000", strokeWeight: 4, strokeOpacity: 0.8, fillOpacity: 0.0 , filterLongDistance: 90};
	var InputOptions = jQuery.extend(defaults, Options);
	
	var j=0;
	var geomFact = new jsts.geom.GeometryFactory();
	// 1. filter points
	for (var i = 0; i < availableTags.length; i++) {
		if (availableTagsMeta[i].class == class_num ) {
			points[j] = new jsts.geom.Coordinate(availableTagsLocation[i].lng(),availableTagsLocation[i].lat());
			j++;
		}
	}
	// 2. draw
    var input = geomFact.createMultiPoint(points);
    
    var builder = new jsts.triangulate.DelaunayTriangulationBuilder();
    builder.setSites(input);
    var delaunayResult = builder.getTriangles(geomFact);
    delaunayGMpolys[class_num] = [];

    console.log("drawdelaunayTriangulation result ploys:" + delaunayResult.getNumGeometries());
    var area = 0;
    for (i=0; i<delaunayResult.getNumGeometries(); i++) {
       var jsts_geom = delaunayResult.getGeometryN(i);
       var polygon_path = jsts2googleMaps(jsts_geom, InputOptions.filterLongDistance);
       if (polygon_path.length === 0 )
		continue;
       delaunayGMpolys[class_num].push(new google.maps.Polygon({
                     path: polygon_path,
		     clickable: false,
                     strokeWeight: InputOptions.strokeWeight,
		     fillColor: InputOptions.fillColor,
		     strokeColor: InputOptions.strokeColor,
		     strokeOpacity: InputOptions.strokeOpacity,
                     fillOpacity: InputOptions.fillOpacity,
                     map: map
                    }));
	area += google.maps.geometry.spherical.computeArea( polygon_path );
    }
    // return area
    return { "area" : area / 1000 / 1000 };
	
}
function cleanDelaunayTriangulation(class_num) {
	if (typeof delaunayGMpolys[class_num] === 'undefined') return;
	for (var i =0; i< delaunayGMpolys[class_num].length; i++){
		delaunayGMpolys[class_num][i].setMap(null);
	}
}
function dumpDelaunayTriangulation(){
	for (var k =1; k <= 3; k++) {
		class_num = k;
		for (var i =0; i< delaunayGMpolys[class_num].length; i++){
			console.log("k=" + k  + " " + GMapPolygonToWKT(delaunayGMpolys[class_num][i]));
		}
	}
	return "OK";
}

function mysetIcon2(type, isShadow) {
    var icon_tmp=[];
	/*
    icon[4] = '//commondatastorage.googleapis.com/ingress.com/img/map_icons/marker_images/enl_lev8.png';
    icon[1] = '//commondatastorage.googleapis.com/ingress.com/img/map_icons/marker_images/enl_8res.png';
    icon[2] = '//commondatastorage.googleapis.com/ingress.com/img/map_icons/marker_images/enl_6res.png';
    icon[3] = '//commondatastorage.googleapis.com/ingress.com/img/map_icons/marker_images/enl_3res.png';
    icon[6] = '//commondatastorage.googleapis.com/ingress.com/img/map_icons/marker_images/helios_shard.png';
    icon[5] = '//commondatastorage.googleapis.com/ingress.com/img/map_icons/marker_images/neutral_icon.png';
	*/
	var allicon = [];
	allicon.ingress = ['', 'img/ingress/enl_8res.png','img/ingress/enl_6res.png', 'img/ingress/enl_3res.png', 'img/ingress/enl_lev8.png', 'img/ingress/helios_shard.png', 'img/ingress/neutral_icon.png'];
	allicon.pokemon =[ '','img/pokemon/143.png','img/pokemon/003.png','img/pokemon/007.png', 'img/pokemon/025.png', 'img/pokemon/010.png' , 'img/pokemon/pokegym.png'];
	// 'img/pokemon/pokeshop.png'
	if (theme == 'pokemon'){
		return 'img/pokemon/' + pad( Math.floor((Math.random() * 151) + 1), 3) + '.png';
	}
    if (theme == 'ingress'){
		var icon = allicon[theme];
        if (type == "一等點") return 'img/ingress/enl_' + Math.floor((Math.random() * 8)+1) + 'res.png';
        else if (type == "二等點") return 'img/ingress/enl_' + Math.floor((Math.random() * 8)+1) + 'res.png';
        else if (type == "三等點") return 'img/ingress/enl_' + Math.floor((Math.random() * 8)+1) + 'res.png';
        else if (type == "森林點") return icon[4];
        else if (type == "未知森林點") return icon[5];
        else return icon[6];
    } else {
        return "//map.happyman.idv.tw/icon/" + encodeURIComponent(type) + ".png";
    }
}
function pad(num, size) {
    var s = num+"";
    while (s.length < size) s = "0" + s;
    return s;
}
function initialmarkers() {
    if (!oms) oms = new OverlappingMarkerSpiderfier(map, {
        markersWontMove: true,
        markersWontHide: false
    });
    for (var i = 0; i < availableTagsLocation.length; i++) {
        allmarkers[i] = new google.maps.Marker({
            icon: mysetIcon2(availableTagsMeta[i].type, 0),
            //icon: iconWithColor(usualColor),
            title: availableTags[i],
            map: map,
	    draggable: false,
            // shadow: mysetIcon2(availableTagsMeta[i].type, 1),
            position: availableTagsLocation[i]
        });
        oms.addMarker(allmarkers[i]);
    }
    oms.addListener('click', function(marker) {
        showCenterMarker(marker.title);
    });
    window.oms = oms;
    // 防止 marker initial 之前 filter 已經被呼叫
    markerFilter();
    console.log("markers ready");
}
var listener;
var TW_Bounds;
var PH_Bounds;
var GeoMarker;

function showUploadPanel(e) {
  e.stopPropagation();
  e.preventDefault();
  $('#drop-container').show();
  return false;
}

function hideUploadPanel(e) {
  $('#drop-container').hide();
}
function setRoadMap(){

	var name = $('#changegname').val();
	var c =  map.overlayMapTypes.getLength();
	for(var i = 0; i < c-1;  i++) {
		map.overlayMapTypes.pop();
		// console.log(i);
	}
	console.log(c);
	console.log('remove overlays cur=' + name);
	i=1;
	if (name == 'GoogleNames') {
		map.overlayMapTypes.insertAt(i++, GoogleNameMapType);
		console.log('insert Google overlay');
		//map.overlayMapTypes[1].setOpacity(1);
	} else if (name == 'NLSCNames') {
		map.overlayMapTypes.insertAt(i++, NLSCNameMapType);
		console.log('insert NLSC overlay');
		//map.overlayMapTypes[1].setOpacity(1);
	}
	if (show_kml_layer == 1){
                 map.overlayMapTypes.insertAt(i++, GPXTrackMapType);
                        console.log('insert GPX overlay');
                }
}
    // 切換前景圖

var shapesMap;

function initialize() {
    console.log('initialize');
    geocoder = new google.maps.Geocoder();
    var init_latlng = new google.maps.LatLng(23.55080, 121.13220);
    map = new google.maps.Map(document.getElementById("map_canvas"), {
        zoom: 14,
        maxZoom: 20,
        center: init_latlng,
        overviewMapControl: true,
        streetViewControl: false,
        disableDoubleClickZoom: true,
        mapTypeControlOptions: {
            style: google.maps.MapTypeControlStyle.DROPDOWN_MENU,
            position: google.maps.ControlPosition.TOP_LEFT,
            // dropdown menu 要重複一次
            mapTypeIds: ['general2011', 'twmapv1', 'taiwan', 'moi_osm', google.maps.MapTypeId.TERRAIN, google.maps.MapTypeId.SATELLITE, "theme", 'fandi', 'jm50k','tw50k','hillshading', 'tw5kariel', 'general2011']
        }
    });
	
	var green_styles = [{"featureType":"administrative","elementType":"geometry.fill","stylers":[{"color":"#7f2200"},{"visibility":"off"}]},{"featureType":"administrative","elementType":"geometry.stroke","stylers":[{"visibility":"on"},{"color":"#87ae79"}]},{"featureType":"administrative","elementType":"labels.text.fill","stylers":[{"color":"#495421"}]},{"featureType":"administrative","elementType":"labels.text.stroke","stylers":[{"color":"#ffffff"},{"visibility":"on"},{"weight":4.1}]},{"featureType":"administrative.neighborhood","elementType":"labels","stylers":[{"visibility":"off"}]},{"featureType":"landscape","elementType":"geometry.fill","stylers":[{"color":"#abce83"}]},{"featureType":"poi","elementType":"geometry.fill","stylers":[{"color":"#769E72"}]},{"featureType":"poi","elementType":"labels.text.fill","stylers":[{"color":"#7B8758"}]},{"featureType":"poi","elementType":"labels.text.stroke","stylers":[{"color":"#EBF4A4"}]},{"featureType":"poi.park","elementType":"geometry","stylers":[{"visibility":"simplified"},{"color":"#8dab68"}]},{"featureType":"road","elementType":"geometry.fill","stylers":[{"visibility":"simplified"}]},{"featureType":"road","elementType":"labels.text.fill","stylers":[{"color":"#5B5B3F"}]},{"featureType":"road","elementType":"labels.text.stroke","stylers":[{"color":"#ABCE83"}]},{"featureType":"road","elementType":"labels.icon","stylers":[{"visibility":"off"}]},{"featureType":"road.highway","elementType":"geometry","stylers":[{"color":"#EBF4A4"}]},{"featureType":"road.arterial","elementType":"geometry","stylers":[{"color":"#d2ec5f"},{"weight":"4"},{"visibility":"simplified"}]},{"featureType":"road.local","elementType":"geometry","stylers":[{"color":"#e0ef9e"},{"visibility":"simplified"}]},{"featureType":"transit","elementType":"all","stylers":[{"visibility":"off"}]},{"featureType":"water","elementType":"geometry","stylers":[{"visibility":"on"},{"color":"#aee2e0"}]}];
	map.setOptions({styles: green_styles});
	var GreenstyledMap = new google.maps.StyledMapType(green_styles,{name: "theme"});

    if (!is_mobile) {
        map.enableKeyDragZoom();
        map.setOptions({
            disableDoubleClickZoom: false
        });
		// add drawing tool
		shapesMap = new ShapesMap( $("#delete-button")[0],$("#clear-button")[0], $("#shapeinfo-button")[0], function(shapes){
			// save info to cookie
			//var expirationDate = new Date();
			//expirationDate.setDate(expirationDate.getDate + 1);
			//var value = escape(shapes) + "; expires=" + expirationDate.toUTCString() + "; path=/";
			//document.cookie = "infoshapes=" + value;
			localStorage.setItem("infoshapes", shapes);
			showmeerkat(get_elev_url + "?infoshapes=1", { 'width': '600'} );		
		} );
	}
    var moveDiv = document.createElement('div');
    var myCustomControl2 = new curLocControl(moveDiv, map);
    map.controls[google.maps.ControlPosition.RIGHT_TOP].push(moveDiv);
	if (is_mobile)
		$("#buttons").hide();
	else
		map.controls[google.maps.ControlPosition.TOP_CENTER].push($("#buttons")[0]);
    map.mapTypes.set('twmapv1', TaiwanMapV1MapType);
    map.mapTypes.set('taiwan', TaiwanMapType);
    map.mapTypes.set('general2011', Taiwan_General_2011_MapType);
    map.mapTypes.set('osm', OSM_MapType);
    map.mapTypes.set('moi_osm', MOI_OSM_MapType);
	map.mapTypes.set('moi_osm_twmap', MOI_OSM_TWMAP_MapType);
    map.mapTypes.set('fandi', FanDi_MapType);
    map.mapTypes.set('jm50k', JM50K1924_MapType);
    map.mapTypes.set('tw50k', TW50K1956_MapType);
	map.mapTypes.set('moi_osm_gpx', MOI_OSM_GPX_MapType);
	map.mapTypes.set('hillshading', Hillshading_MapType);
	map.mapTypes.set('tw5kariel', TW5KArielPIC_MapType);
	if (getParameterByName("theme") == 'ingress')
		map.mapTypes.set('theme', Darker_MapType);
	else
		map.mapTypes.set('theme', GreenstyledMap);

		
    // 前景免設
    // MOI_OSM_GPX as default
    //BackgroundMapType = TaiwanGpxMapType;
    //BackgroundMapOptions = TaiwanGpxMapOptions;
    BackgroundMapType = MOI_OSM_TWMAP_MapType;
    BackgroundMapOptions = MOI_OSM_TWMAP_Options;
    // 初始顯示哪張圖? 衛星圖
    map.setMapTypeId(google.maps.MapTypeId.SATELLITE);
    // 背景哪張圖
    map.overlayMapTypes.insertAt(0, BackgroundMapType);
    setRoadMap();
    // 控制背景圖的透明度
    var bar = document.getElementById("op");
    var container = $("#opSlider");
    //var container = document.getElementById("opSlider");
    //var range = (parseInt(container.style.width) - parseInt(bar.style.width));
    if (is_mobile) {
        $('#opSlider').width('60px');
        $('#op').width('8px');
        $('#more').css({
            'left': '84px'
        });
    }
    var range = container.width() - $("#op").width();
    map.controls[google.maps.ControlPosition.TOP_LEFT].push(document.getElementById('opContainer'));
    var opSlider = new ExtDraggableObject(bar, {
        restrictY: true,
        container: container
    });
    //
    // 顯示預設透明度, 一定要 改變才會生效.. 不懂
    changeBackgroundOpacity(opacity - 0.001);
    opSlider.setValueX(range * opacity);
    showOp(opacity);
    //  Taiwan bounds
    TW_Bounds = new google.maps.LatLngBounds();
    TW_Bounds.extend(new google.maps.LatLng(21.8, 119.8));
    TW_Bounds.extend(new google.maps.LatLng(25.7, 123.0));
    PH_Bounds = new google.maps.LatLngBounds();
    PH_Bounds.extend(new google.maps.LatLng(23.15, 119.2));
    PH_Bounds.extend(new google.maps.LatLng(23.75, 119.75));
    google.maps.event.addListener(opSlider, 'drag', function(evt) {
        var op = opSlider.left() / range;
        if (op >= 1) op = 1;
        if (op <= 0) op = 0;
        changeBackgroundOpacity(op);
        opSlider.setValueX(range * opacity);
        showOp(opacity);
    });
    google.maps.event.addDomListener(document.getElementById('less'), 'click', function(event) {
        var op = opacity - 0.1;
        if (op < 0) op = 0; // return;
        changeBackgroundOpacity(op);
        opSlider.setValueX(range * opacity);
        showOp(opacity);
        event.preventDefault();
    });
    google.maps.event.addDomListener(document.getElementById('more'), 'click', function(event) {
        var op = opacity + 0.1;
        if (op > 1) op = 1; // return;
        changeBackgroundOpacity(op);
        opSlider.setValueX(range * opacity);
        showOp(opacity);
        event.preventDefault();
    });
    // 畫框框
    google.maps.event.addListener(map, 'maptypeid_changed', function() {
        updateView("info_only");
    });
    // 真正載入完成
    listener = google.maps.event.addListener(map, 'idle', function() {
        if ($("#loading").is(":visible")) {
            $("#loading").hide();
            $(window).resize();
        }
        updateView('bounds_changed');
    });
    google.maps.event.addListener(map, "rightclick", function(event) {
        map.set('disableDoubleClickZoom', true);
        var newpos = event.latLng;
        locInfo_name = "我的位置";
        centerMarker.setPosition(newpos);
        locInfo(newpos);
        centerMarker.setVisible(true);
    });
	
    if (is_mobile) {
        google.maps.event.addListener(map, 'dblclick', function(event) {
            console.log("left click fired");
            var newpos = event.latLng;
            locInfo_name = "我的位置";
            centerMarker.setPosition(newpos);
            locInfo(newpos);
            centerMarker.setVisible(true);
        });
    } else {
        google.maps.event.addListener(map, 'click', function(event) {
            map.setOptions({
                disableDoubleClickZoom: false
            });
            console.log("left click fired");
			shapesMap.selectionClear();
            var newpos = event.latLng;
            locInfo_name = "我的位置";
            centerMarker.setPosition(newpos);
            locInfo(newpos, addremove_polygon, event);
        });
    }
    // 載入 Tags
    $("#tags").val("初始化中");
    // 搜尋框被 focus 跟 blur 的時候
    /*
    $("input:text#tags").on('focus mouseover', function() {
        $(this).css('font-size', '3em');
    }).on('blur mouseout', function() {
        $(this).css('font-size', '1em');
    });
*/
    // 按下 esc key
    $(document).keyup(function(e) {
        if (e.keyCode == 27) {
            $("input:text").blur();
        } // esc
    });
    tags_ready = 0;
    if (getParameterByName("theme")) {
		if (getParameterByName("theme") == 'ingress') {
        theme = 'ingress';
		} else if (getParameterByName("theme") == "pokemon"){
			theme = 'pokemon';
		}
    } 
    initialtags({});
    $("#gotoform").submit(function() {
        $("#goto").trigger('click');
        return false;
    });
    $("#goto").click(function() {
        console.log("goto click");
        letsgo();
    });

    function letsgo() {
        $("#tags").blur();
        if (tags_ready === 0) {
            setTimeout(letsgo, 2000);
        } else {
            showCenterMarker($.trim($("#tags").val()));
        }
        // 如果 user 自選
        got_geo = 1;
    }
    // initialize markerArray
    for (var i = 0; i < markerArrayMax; i++) {
        markerArray[i] = new google.maps.Marker({
            position: init_latlng,
            icon: "img/pointer01.jpg",
            title: "init",
            draggable: false,
            map: map
        });
        labelArray[i] = new Label({
            //clickfunc: labelClickfunc,
            //clickfunc: showCenterMarker,
            map: null
        });
        labelArray[i].bindTo('position', markerArray[i], 'position');
        labelArray[i].bindTo('text', markerArray[i], 'title');
    }
    markers_ready = 1;
 
	 $("#changemap").change(function() {
		 var curMapType = BackgroundMapType;
		 var newMap = $("#changemap").val();
		 if (newMap == 'tw25k_v3' || newMap == '3') {
	//		 if (show_kml_layer == 1) {
	//			BackgroundMapType = TaiwanGpxMapType;
          //      BackgroundMapOptions = TaiwanGpxMapOptions;
				
	//		 } else {
				BackgroundMapType = TaiwanMapType;
                BackgroundMapOptions = TaiwanMapOptions; 
		//	 }
			 BackgroundMap = 'tw25k_v3';
		} else if (newMap == 'tw25k_v1' || newMap == '1') {
			BackgroundMapType = TaiwanMapV1MapType;
            BackgroundMapOptions = TaiwanMapV1Options;
			BackgroundMap = 'tw25k_v1';
		} else {
		//	if (show_kml_layer == 1) {
		//		BackgroundMapType = MOI_OSM_GPX_MapType;
		//		BackgroundMapOptions = MOI_OSM_GPX_Options;
		//	} else {
				BackgroundMapType = MOI_OSM_TWMAP_MapType;
				BackgroundMapOptions = MOI_OSM_TWMAP_Options;
		//	}
			BackgroundMap = 'moi_osm';
		}	
		 if (curMapType == BackgroundMapType) {
			 console.log('skip change');
			 return true;
		} 	
		map.overlayMapTypes.removeAt(0, BackgroundMapType);
        map.overlayMapTypes.insertAt(0, BackgroundMapType);
        updateView("info_only");
        changeBackgroundOpacity(opacity);
	 });
    // 切換前景圖
    $('#changegname').change(function() {
        //var curMap = (map.overlayMapTypes.length == 2) ? map.overlayMapTypes.getArray()[1].name : 'None';
/*
	 var curMap = (map.overlayMapTypes.getAt(1) != "undefined" && map.overlayMapTypes.getAt(1).name != 'GPXTrack') ? map.overlayMapTypes.getArray()[1].name : 'None';
        var newMap = $('#changegname').val();
        if (curMap == newMap) return true;
	
        if ($('#changegname').val() == 'None') {
            map.overlayMapTypes.removeAt(1);
            return true;
        }
        if (curMap != 'None') {
			map.overlayMapTypes.removeAt(1);
	}
        if (newMap == 'GoogleNames') {
			map.overlayMapTypes.insertAt(1, GoogleNameMapType);
			//map.overlayMapTypes[1].setOpacity(1);
		}
        else if (newMap == 'NLSCNames') {
			map.overlayMapTypes.insertAt(1, NLSCNameMapType);
			//map.overlayMapTypes[1].setOpacity(1);
		}
*/
	setRoadMap();
        updateView("info_only");
    });
    $('#changegrid').change(function() {
        showGrid('None');
        showGrid($('#changegrid').val());
        updateView("info_only");
    });
    $('#rainfall').change(function() {
	showCWBRainfall('none');
	showCWBRainfall($('#rainfall').val());
        updateView("info_only");
    });
    $("#mcover").change(function() {
	coverage_overlay($('#mcover').val());
        updateView("info_only");
    });
    $("#inputtitlebtn2").click(function() {
        ismakingmap = 0;
        $.unblockUI();
    });
    $("#inputtitlebtn").click(function() {
        // console.log($("#inputtitle"));
        if ($("#inputtitle").val() !== "") {
            ismakingmap = 0;
            $.unblockUI();

            url = callmake_url + callmake + "&title=" + $('#inputtitle').val();

            if (confirm("程式將會傳送參數給地圖產生器,確定嘛?")) {
                if (parent.location != window.location) parent.location.href = url;
                else location.href = url;
            }
        } else {
            // alert("請輸入地圖標題");
			noty({	text: '請輸入地圖標題', type: 'alert',  closeWith   : ['click','timeout'], timeout     :5000 });
        }
    });

    $("#about").click(function() {
        //$("#footer").
	showmeerkat('about.php',{ 'width': '600'} );
    });
    $("#kml_sw").click(function() {
        if (show_kml_layer == 1) {
            show_kml_layer = 0;
            $("#kml_sw").addClass("disable");
        } else {
            show_kml_layer = 1;
            $("#kml_sw").removeClass("disable");
        }
	$("#changegname").change();
    });
    $("#label_sw").click(function() {
	console.log("label_sw triggerred: " + show_label);
        if (show_label == 1) {
            show_label = 0;
            // remove all markers
            for (var i = 0; i < markerArrayMax; i++) {
                markerArray[i].setMap(null);
                labelArray[i].setMap(null);
            }
            $("#label_sw").addClass("disable");
            $('.ui-dropdownchecklist-selector').addClass('disable');
            // alert(show_label);
        } else {
            show_label = 1;
            showInsideMarkers();
            // alert(show_label);
            $("#label_sw").removeClass("disable");
            $('.ui-dropdownchecklist-selector').removeClass('disable');
        }
        updateView("info_only");
	console.log("label_sw trigger end" + show_label);
    });
    $("#delaunay_sw").click(function() {
	if (show_delaunay == 1 ) {
		show_delaunay = 0;
		// topnoty = noty({text: '取消三角點通視....', layout:'top'});
		/*
		cleanDelaunayTriangulation(1);
		cleanDelaunayTriangulation(2);
		cleanDelaunayTriangulation(3); 
		*/
		remove_survey_network(1);
		remove_survey_network(2);
		remove_survey_network(3);
		
            $("#delaunay_sw").addClass("disable");
			// topnoty.close();
	} else {
		show_delaunay = 1;
		// topnoty = noty({text: '三角點通視計算中....', layout:'top'});
            $("#delaunay_sw").removeClass("disable");
			/*
			drawDelaunayTriangulation(1, { strokeColor: "#FFFF00", strokeWeight: 3 });
            drawDelaunayTriangulation(2, { strokeColor: "#01DF01", strokeWeight: 2 });
            drawDelaunayTriangulation(3, { strokeColor: "#FF00FF", strokeWeight: 1 });
			*/
			create_survey_network(3);
			create_survey_network(2);
			create_survey_network(1);
			// topnoty.close();
	}
        updateView("info_only");
    });
   
    $("#marker_reload").hide();
    //toggle_admin_role();
    // admin
    /*
    $("#marker_reload").click(function() {
        markerReload({
            msg: "載入完成"
        });
        // 重新顯示
    });
    */
    // map.controls[google.maps.ControlPosition.TOP_LEFT].push(document.getElementById('locContainer'));
    // everything is ready
    $("#marker_sw_select").dropdownchecklist({
        firstItemChecksAll: true,
        explicitClose: '..選好了',
        onComplete: function(selector) {
            var values = "";
            for (i = 0; i < selector.options.length; i++) {
                if (selector.options[i].selected && (selector.options[i].value !== "")) {
                    if (values !== "") values += ",";
                    values += selector.options[i].value;
                }
            }
            if (values != show_marker) {
                if (values === "") values = "0";
                show_marker = values;
                //markerReload({msg: "載入完成"});
                markerFilter();
            }
	    console.log("dropdownchecklist complete");
        }
    });
    $(".ui-dropdownchecklist-selector").addClass("ui-corner-all").css({
        'top': '1px',
        'position': 'absolute'
    });
    $(".ui-dropdownchecklist-text").css({
        "font-size": "13px",
        "margin": "1px"
    });
    //$("#ddcl-marker_sw_select").css({"top": "5px"});
    $('#changegname').menu();
    $('#changegname').removeClass('ui-widget-content ui-corner-all');
    $('#changegrid').menu();
    $('.close-meerkat2').hide();
	// ranking dialog
	$('#ranking').dialog({ 
		autoOpen: false,
		zIndex: 500,
        modal: true,
        resizable: false,
		resize: "auto",
        width: "auto",
		height: "auto"
    });
    if (is_mobile) {
        // 產生 setup menu
        $('#changegname').removeAttr('style');
        $('#changegrid').removeAttr('style');
        $("#ddcl-marker_sw_select").css({
            top: '5px'
        });
        $('#kml_sw').appendTo('#mobile_setup').hide();
        $('#label_sw').appendTo('#mobile_setup').hide();
        $('#delaunay_sw').appendTo('#mobile_setup').hide();
        $('#opContainer').appendTo('#mobile_setup');
        $('#CGRID').appendTo('#mobile_setup').hide();
        $('#CGNAME').appendTo('#mobile_setup').hide();
        $('#FORECAST').appendTo('#mobile_setup').hide();
        $('#MCOVERAGE').appendTo('#mobile_setup').hide();
		// export kml button
		$('#export_kml').click(function(){
			var bounds = map.getBounds();
			var ne = bounds.getNorthEast(); // LatLng of the north-east corner
			var sw = bounds.getSouthWest(); // LatLng of the south-west corder
			export_points(sw.lng().toFixed(6),sw.lat().toFixed(6),ne.lng().toFixed(6),ne.lat().toFixed(6));
		});
        $('#setup').click(function() {
            showmeerkat2({
                width: 600,
                height: 200
            });
            $('.close-meerkat2').show();
            $('#kml_sw').removeAttr('style').css({
                'position': 'absolute',
                'top': '30px',
                'left': '10px',
                'font-size': '20px'
            }).show();
            $('#label_sw').removeAttr('style').css({
                'position': 'absolute',
                'top': '30px',
                'left': '80px',
                'font-size': '20px'
            }).show();
            $('#delaunay_sw').removeAttr('style').css({
                'position': 'absolute',
                'top': '30px',
                'left': '150px',
                'font-size': '20px'
            }).show();
            $('#CGRID').show();
            $('#CGNAME').show();
            $('#FORECAST').show();
            $('#MCOVERAGE').show();
            $('#changegname').css({
                'left': '10px',
                'top': '80px',
                'font-size': '20px'
            }).addClass('ui-state-default ui-corner-all').show();
            $('#changegrid').css({
                'left': '10px',
                'top': '120px',
                'font-size': '20px'
            }).addClass('ui-state-default ui-corner-all').show();
            $('#rainfall').css({
                'left': '150px',
                'top': '80px',
                'font-size': '20px'
            }).addClass('ui-state-default ui-corner-all').show();
            $('#mcover').css({
                'left': '150px',
                'top': '120px',
                'font-size': '20px'
            }).addClass('ui-state-default ui-corner-all').show();
        });
        $('#setup').show();
    } 
    	

    var map_is_ready = google.maps.event.addListener(map, "bounds_changed", function() {
        console.log("bounds_changed");
// restore state
// 1. restore params
// 1.1 restore from localstorage
   var state;
	try {
	     state = JSON.parse(localStorage.getItem("twmap_state"));
	     if (state === null )
		state = {};
	} catch(e) {
	     state = {};
	}
	var st = { "show_label": getParameterByName("show_label"),
		   "show_marker": getParameterByName("show_marker"),
 		   "show_kml_layer":getParameterByName("show_kml_layer"),
		   "zoom":getParameterByName("zoom"),
		   "maptypeid":getParameterByName("maptypeid"),
		   "grid":getParameterByName("grid"),
		   "roadmap":getParameterByName("roadmap"),
		   "mapversion":getParameterByName("mapversion"),
		   "show_delaunay":getParameterByName("show_delaunay"),
		   "rainfall":getParameterByName("rainfall"),
		   "mcover":getParameterByName("mcover"),
		   "goto":getParameterByName("goto"),
		   "kml": getParameterByName("kml"),
		   "skml_id":getParameterByName("skml_id")
	};	   
	$.extend(true,state,st);
	//console.log(state);
	restoreMapState(state);

     // if show_line_of_sight == 1
     if (getParameterByName("show_line_of_sight") == 1) {

          var checkExist = setInterval(function() {
               if ($('#los_link').length) {
                $('#los_link')[0].click();
                clearInterval(checkExist);
          }
          }, 100); // check every 100ms

     }

        // 最後處理手機的事情
      if (is_mobile) {
            // 隱藏 navigator bar
            setTimeout(function() {
                window.scrollTo(0, 1);
            }, 0);
            // 隱藏一些 button
            $("#about").hide();
            $("#generate").hide();
            // $("#changemap").removeAttr('style');
			    $('#changemap').css({
        'left': '110px'
    });
            $("#search_text").hide();
            $("#loc").hide();
            google.maps.event.clearListeners(map, 'click');
            // 建立 logo
            var myCustomControlDiv = document.createElement('div');
            var myCustomControl = new MyCustomControl(myCustomControlDiv, map);
            map.controls[google.maps.ControlPosition.BOTTOM_LEFT].push(myCustomControlDiv);
        } // is_mobile
        // remove 掉
        google.maps.event.removeListener(map_is_ready);
    }); // map is ready listener
    console.log("done initialize");
} // end of initialize
function MyCustomControl(controlDiv, map) {
    var control = this;
    var testBtn = document.createElement('button');
    testBtn.id = 'testBtn';
    testBtn.className = 'ui-state-default ui-corner-all';
    testBtn.innerHTML = $("#about").html();
    controlDiv.appendChild(testBtn);
    // wire up jquery click
    $(testBtn).click(function() {
        // $("#footer").dialog();
	showmeerkat("about.php",{width: 600});
    });
}

function resizeMap() {
    var viewport_height = ($(window).height() < 460) ? 460 : $(window).height();
    $("#map_canvas").height(viewport_height - 33 + "px");
    if (map !== null && markers_ready == 1) {
        google.maps.event.trigger(map, 'resize');
    }
}

function curLocControl(controlDiv, map) {
    var control = this;
    //var testBtn = document.createElement('button');
    //testBtn.id = 'moveBtn';
    //testBtn.className = 'ui-state-default ui-corner-all';
    //testBtn.innerHTML = " 目前位置 ";
    var testBtn = document.createElement('img');
    testBtn.setAttribute('src', "img/location.png");
    controlDiv.appendChild(testBtn);
    //
    $(testBtn).click(function() {
        console.log("click on getCurrentPosition");
        //navigator.geolocation.getCurrentPosition( CurrentLocation );
        $.geolocation.get({
            win: function(position) {
                CurrentLocation(position);
                position_get = 1;
                if (!GeoMarker) {
                    // GeoMarker
                    GeoMarker = new GeolocationMarker();
                    GeoMarker.setCircleOptions({
                        fillColor: '#808080',
                        visible: false
                    });
                    google.maps.event.addListener(GeoMarker, 'position_changed', function() {
                        // console.log('position changed GeoMarker');
                        // map.setCenter(this.getPosition());
                        // map.fitBounds(this.getBounds());
                    });
                    //google.maps.event.addListener(GeoMarker, 'geolocation_error', function(e) {
                    //  alert('There was an error obtaining your position. Message: ' + e.message);
                    //});
                    GeoMarker.setMap(map);
                    // GeoMarker
                }
            }
        });
    });
}

function CurrentLocation(position) {
    // user 提供資訊
    got_geo = 1;
    var pos = new google.maps.LatLng(position.coords.latitude, position.coords.longitude);
	console.log('goto currentLocation');
    $("#tags").val(pos.toUrlValue(5));
    $("#goto").trigger('click');
    MapStateRestored = 1;
}

function FeatureLocation() {
    var feature = ["三角錐山", "南二子山北峰", "敷島山", "大檜山", "武陵山", "佐久間山", "錐錐谷", "丹錐山", "霧頭山", "出雲山", "西巴杜蘭", "公山", "大分山"];
	console.log('goto featureLocation');
    $("#tags").val(feature[Math.floor(Math.random() * feature.length)]);
    $("#goto").trigger('click');
    MapStateRestored = 1;
}

function updateView(type) {
    //hack
    if ($('div.gmnoprint').last().find("div").first().css("font-size") != "15px") {
        $('div.gmnoprint').last().find("div").css({
            "top": "3px",
            "font-size": "15px"
        });
        $('div.gmnoprint').last().find("div").first().addClass("ui-corner-all");
    }
    if (type != "info_only") {
        showInsideMarkers();
    }
    if (markers_ready === 0) {
        console.log("updateView abort");
        return;
    }
    if ($('#changegrid') != 'None') 
		showGrid($('#changegrid').val());
    // 如果已經關閉就不用重開
    if (centerInfo && centerInfo.getMap()) {
        var newpos = centerMarker.getPosition();
        // 如果不在範圍內,就關了他吧
        var bounds = map.getBounds();
        if (bounds && !bounds.contains(newpos)) {
            centerInfo.close();
            return;
        }
        // 就這樣
        if (showCenterMarker_id === '') {
            locInfo(newpos);
        } else {
            tagInfo(newpos, showCenterMarker_id);
        }
    }
	// update copyright Info
	CopyrightChange();
    saveMapState();
    console.log("updateView "+type);
}
/**
 * markerReloadSingle 只更新一筆 點位. 被 admin hook
 * @param  opt {  id: point id, action: delete/ add/update
 *                 meta: }
 * @return  null
 */
function markerReloadSingle(opt){
    // if update / delete
    // 1.1 search  existing  availableTags for id
    // 1.2. update availableTags, availableTagsLocation, availableTagsMeta
    // 1.3. update autocomplete
    // 1.4. update allmarkers
    // 1.5. update oms
    // 1.6. update inside markers
    // else if add
    // 2.1. add availableTags, availableTagsLocation, availableTagsMeta
    // 2.2. add autocomplete
    // 2.3. add allmarkers
    // 2.4. add oms
    // 2.5. update inside markers
    var i;
    var to_update_id = 0;
    if (opt.action == 'update') {
        for (i=0;i<availableTags.length;i++){
            if (availableTagsMeta[i].id == opt.id) {
                to_update_id = i;
                break;
            }
        }
        if (to_update_id === 0 ) return; // nothing to update
        availableTags[to_update_id] = opt.meta.name;
        availableTagsMeta[to_update_id] = {
                    "id": opt.meta.id,
                    "type": opt.meta.type,
                    "class": opt.meta.class,
                    "mt100": opt.meta.mt100,
					"prom": opt.meta.prominence,
					"prom_idx": opt.meta.prominence_index,
		    "owner": opt.meta.owner
                };
        availableTagsLocation[to_update_id] = new google.maps.LatLng(opt.meta.y, opt.meta.x);
        oms.removeMarker(allmarkers[to_update_id]);
        allmarkers[to_update_id].setOptions({ icon: mysetIcon2(availableTagsMeta[to_update_id].type, 0),
            title: availableTags[to_update_id],
            position: availableTagsLocation[to_update_id]});
        oms.addMarker(allmarkers[to_update_id]);
    } else if (opt.action == 'delete') {
        for (i=0;i<availableTags.length;i++){
            if (availableTagsMeta[i].id == opt.id) {
                to_update_id = i;
                break;
            }
        }
        if (to_update_id === 0 ) return; // nothing to delete
        availableTags.splice(to_update_id,1);
        availableTagsMeta.splice(to_update_id,1);
        availableTagsLocation.splice(to_update_id,1);
        oms.removeMarker(allmarkers[to_update_id]);
	allmarkers[to_update_id].setMap(null);
        allmarkers.splice(to_update_id,1);
    } else if (opt.action == 'add'){
        to_update_id = availableTags.length;
        availableTags[to_update_id] = opt.meta.name;
        availableTagsLocation[to_update_id] = new google.maps.LatLng(opt.meta.y, opt.meta.x);
	availableTagsMeta[to_update_id] = {
                    "id": opt.meta.id,
                    "type": opt.meta.type,
                    "class": opt.meta.class,
                    "mt100": opt.meta.mt100,
					"prom": opt.meta.prominence,
					"prom_idx": opt.meta.prominence_index,
		    "owner": opt.meta.owner
        };
        allmarkers[to_update_id] = new google.maps.Marker({
            icon: mysetIcon2(availableTagsMeta[to_update_id].type, 0),
	    map: map,
            title: availableTags[to_update_id],
            position: availableTagsLocation[to_update_id]});
        oms.addMarker(allmarkers[to_update_id]);
    }
console.log(to_update_id);
    $("#tags").autocomplete("option",{
                source: availableTags
    });
    if (centerInfo) centerInfo.close();
    if (show_label) {
        for (i = 0; i < markerArrayMax; i++) {
            markerArray[i].setMap(null);
            labelArray[i].setMap(null);
	}
    }
    showInsideMarkers();
}
/*
function markerReload(opt) {
    // 清除 label
    if (show_label) {
        for (i = 0; i < markerArrayMax; i++) {
            markerArray[i].setMap(null);
            labelArray[i].setMap(null);
        }
    }
    // 清除 markers
    if (show_marker !== "0") {
        for (i = 0; i < allmarkers.length; i++) allmarkers[i].setMap(null);
        oms.clearMarkers();
        allmarkers = [];
    }
    if (centerInfo) centerInfo.close();
    tags_ready = 0;
    initialtags(opt);
}
*/
function markerFilter() {
    var s = show_marker.split(",");
    //console.log(s);
    for (i = 0; i < allmarkers.length; i++) {
        want = 0;
        for (var k = 0; k < s.length; k++) {
            if (s[k] == 'a') {
                want = 1;
                break;
            }
            // 如果只有單一為 0 的話
            if (s[k] == '0' && s.length == 1) {
                want = 0;
                break;
            }
            // 其他則忽略
            if (s[k] == '0') continue;
            if (s[k] == '5') {
                if (parseInt(availableTagsMeta[i].mt100) & 1) {
                    want = 1;
                }
            }
            // 小百岳
            if (s[k] == '6') {
                if (parseInt(availableTagsMeta[i].mt100) & 2) {
                    want = 1;
                }
            } else if (s[k] == '9') {
                if (parseInt(availableTagsMeta[i].mt100) & 4) {
                    want = 1;
                }
            } else if (s[k] == '8') {
                if (availableTagsMeta[i].class == '0' && (availableTagsMeta[i].type == '溫泉')) {
                    want = 1;
                }
			} else if (s[k] == '10') {
				// prominence list
				if ( availableTagsMeta[i].prom_idx > 0)  {
					want = 1;
				}
					
            } else if (s[k] == '7') {
                // 其他
                if (availableTagsMeta[i].class == '0') {
                    want = 1;
                }
            } else {
                // 1-4 等
                if (availableTagsMeta[i].class === s[k]) {
                    want = 1;
                }
            }
        }
        if (want === 0) {
            allmarkers[i].setVisible(false);
        } else {
            allmarkers[i].setVisible(true);
        }
    }
    //
    console.log("markerFilter done");
    updateView("marker_switch");
}

function showmeerkat(url, options) {
    var screenwidth = (window.innerWidth > 0) ? window.innerWidth : screen.width;
    var opt = {};
    opt.width = (options.width) ? options.width : '830';
    if (opt.width > screenwidth) opt.width = screenwidth - 50;
    opt.height = '100%';
    if ($("#meerkat-wrap").is(":visible")) {
        $('#meerkat-wrap').hide().queue(function() {
            jQuery(this).destroyMeerkat();
        });
        console.log('close meerkat');
    } else {
        $('#meerkat').meerkat();
        console.log('create meerkat');
    }
    $('#meerkat').meerkat({
        background: '#ffffff',
        height: opt.height,
        width: opt.width,
        position: 'right',
        close: '.close-meerkat',
        dontShowAgain: '.dont-show',
        animationIn: 'slide',
        animationOut: 'slide',
        animationSpeed: 1000
    }).removeClass('pos-left pos-bot pos-top').addClass('pos-right');
    $("#meerkat-content").html("<iframe id=\"meerkatiframe\" align=\"middle\" scrolling=\"yes\" style=\"width:" + opt.width + "px;height:" + opt.height + "\"  frameborder=\"0\" allowtransparency=\"true\" hspace=\"0\" vspace=\"0\" marginheight=\"0\" marginwidth=\"0\"src='" + url + "'></iframe>");
}

function showmeerkat2(options) {
    var screenwidth = (window.innerWidth > 0) ? window.innerWidth : screen.width;
    var opt = {};
    opt.width = (options.width) ? options.width : '500';
    if (opt.width > screenwidth) opt.width = screenwidth;
    opt.height = (options.height) ? options.height : '300';
    $('#mobile_setup').meerkat({
        background: '#ffffff',
        height: opt.height + 'px',
        width: opt.width + 'px',
        position: 'top',
        close: '.close-meerkat2',
        dontShowAgain: '.dont-show',
        animationIn: 'slide',
        animationSpeed: 500
    }).removeClass('pos-left pos-bot pos-right').addClass('pos-top');
    //$(".meerkat-content2").html(.html());
}
var poly = [];
var polylabel = [];

function lonlat_range_getblock(minx, miny, maxx, maxy, ph, grid_type) {
    var sw = lonlat2twd67(minx, miny, ph);
    var ne = lonlat2twd67(maxx, maxy, ph);
    // y 軸
    var i = 0;
    var j = 0;
    //console.log(startx + " " + starty + " " + endx + " " + endy);
    var xstep = 1000;
    var ystep = 1000;
    var curZoom = map.getZoom();
    var gridColor = 'white';
    if (ph == 1) gridColor = 'yellow';
    var showlabel = 1;
    var adjusty = 0;
    var adjustx = 1;
    var sstep;
    if (map.getZoom() < 9) {
        sstep = 25000;
        xstep = sstep;
        ystep = sstep;
        endx = endx - (endx - startx) % sstep + sstep;
        endy = endy - (endy - starty) % sstep + sstep;
        showlabel = 0;
    } else if (curZoom >= 9 && curZoom <= 12) {
        sstep = 10000;
        xstep = sstep;
        ystep = sstep;
        endx = endx - (endx - startx) % sstep + sstep;
        endy = endy - (endy - starty) % sstep + sstep;
        showlabel = 1;
        adjusty = 200;
        adjustx = 2;
    } else if (curZoom == 13) {
        adjusty = 200;
        adjustx = 2;
    } else if (curZoom == 14) {
        adjusty = 120;
        adjustx = 2;
    } else if (curZoom == 15) {
        adjusty = 50;
        adjustx = 1;
    } else {
        adjusty = 30;
        adjustx = 1;
    }
    // 特別的
    if (grid_type == 'TWD67_EXT') {
        if (curZoom == 16) {
            xstep = 200;
            ystep = 200;
            adjusty = 30;
            adjustx = 1;
            gridColor = 'black';
        } else if (curZoom >= 17 && curZoom <= 18) {
            xstep = 100;
            ystep = 100;
            adjusty = 10;
            adjustx = 1;
            gridColor = 'black';
        } else if (curZoom == 19) {
            xstep = 100;
            ystep = 100;
            adjusty = 5;
            adjustx = 1;
            gridColor = 'white';
        } else {
            gridColor = 'black';
        }
    }
    if ($("#changemap").val() == 'moi_osm'){
	gridColor = 'black';
    }
	// floor / ceil 多畫一點沒關係
    var startx = Math.floor(sw.x / xstep) * xstep;
    var starty = Math.floor(sw.y / ystep) * ystep;
    var endx = Math.ceil(ne.x / xstep) * xstep;
    var endy = Math.ceil(ne.y / ystep) * ystep;
    var p;
    var p1;
    var lp;
    //console.log("x="+startx +"y="+ starty +"endx="+ endx + "endy="+ endy);
    for (var y = starty; y <= endy; y += ystep) {
        p = twd672lonlat(startx, y, ph);
        p1 = twd672lonlat(endx, y, ph);
        // 右邊一格
        lp = twd672lonlat(startx + xstep, y + adjusty, ph);
        if (!poly[i]) poly[i] = new google.maps.Polyline({
            map: map,
            path: [new google.maps.LatLng(p.y, p.x), new google.maps.LatLng(p1.y, p1.x)],
            strokeColor: gridColor,
            strokeWeight: 1
        });
        else poly[i].setPath([new google.maps.LatLng(p.y, p.x), new google.maps.LatLng(p1.y, p1.x)]);
        poly[i].setOptions({
            strokeColor: gridColor,
            clickable: false,
            strokeOpacity: 0.8
        });
        poly[i].setMap(map);
        // 畫出 Y 軸
        if (showlabel) {
            if (!polylabel[j]) polylabel[j] = new Label({
                map: map
            });
            polylabel[j].setValues({
                position: new google.maps.LatLng(lp.y, lp.x),
                text: y / 1000,
                map: map,
                style: 'color: ' + gridColor + '; cursor: none; background-color: none; border: 0;'
            });
            j++;
        }
        i++;
    }
    // x 軸
    for (var x = startx; x <= endx; x += xstep) {
        p = twd672lonlat(x, starty, ph);
        p1 = twd672lonlat(x, endy, ph);
        // 往上
        lp = twd672lonlat(x, starty + ystep * adjustx, ph);
        if (!poly[i]) poly[i] = new google.maps.Polyline({
            map: map,
            path: [new google.maps.LatLng(p.y, p.x), new google.maps.LatLng(p1.y, p1.x)],
            strokeColor: gridColor,
            strokeWeight: 1
        });
        else {
            poly[i].setPath([new google.maps.LatLng(p.y, p.x), new google.maps.LatLng(p1.y, p1.x)]);
            poly[i].setOptions({
                strokeColor: gridColor,
                clickable: false,
                strokeOpacity: 0.8
            });
            poly[i].setMap(map);
        }
        if (showlabel == 1) {
            if (!polylabel[j]) polylabel[j] = new Label({
                map: map
            });
            polylabel[j].setValues({
                position: new google.maps.LatLng(lp.y, lp.x),
                text: x / 1000,
                map: map,
                style: 'color: ' + gridColor + '; background-color: none; border: 0;'
            });
            j++;
        }
        i++;
    }
    for (var k = j; k < polylabel.length; k++) {
        polylabel[k].setMap(null);
    }
    for (j = i; j < poly.length; j++) poly[j].setMap(null);
}

function toggle_user_role(cur_role) {
  login_role = cur_role;
  if (login_role == 1)
	$("#about").html("<img src='img/ico_user.png' height='20' />" + $("#about").text());
  else
	$("#about").html($("#about").text());
}

var line_class_arr = [ [],[], [], [] ];
var poly_class_arr = [ [],[], [], [] ];

function create_survey_network(class_num) {
  // 1. class 1
 var color = [ "##" ,  "#FFFF00" ,  "#01DF01",  "#FF00FF" ];
 var colorbg = [ "##", "#FFFE01",   "#01EF01",  "#FE00FE" ];
 var strokeW = [ 0, 1, 1, 1];
 var PstrokeW = [ 0, 2, 1, 1];

 if (theme == 'ingress') {
	 fillcolor = '#01ef01';
	 fillop = 0.1;
 }else {
	 // not fill
   fillcolor = colorbg[class_num];
   fillop = 0;
 } 
 console.log("create survey network " + class_num);
 var k = class_num;
	for(var i=0; i < line_class[k].length; i++) {
		line_class_arr[k][i]= new google.maps.Polyline({
		path:  line_class[k][i], 
		strokeOpacity: 0.5,
		strokeColor: color[k],
		strokeWeight: strokeW[k],   
		map: map
		});
	}
	// draw polygons
	for(i=0; i < poly_class[k].length; i++) {
		poly_class_arr[k][i]= new google.maps.Polygon({
		path:  poly_class[k][i], 
		strokeOpacity: 1,
		strokeColor: color[k],
		strokeWeight: PstrokeW[k],   
		fillColor: fillcolor,
		fillOpacity: fillop,
		map: map
		});
	}
}

function remove_survey_network(class_num) {
	console.log("remove survey network " + class_num);
	// if (typeof line_class_arr[class_num] === 'undefined') return;
    for(var i=0; i < line_class_arr[class_num].length; i++) {
		line_class_arr[class_num][i].setMap(null);

    } 
    for( i=0; i < poly_class_arr[class_num].length; i++) {
		poly_class_arr[class_num][i].setMap(null);
    } 
}

function open_ranking_dialog(mid,link,backurl){
	$('#ranking_iframe').attr('src',link);
	$('#ranking_iframe').load(function() {
		$('#ranking').dialog("option", 
			{"title": mid,
			"close": function () {
            // $('#ranking_iframe').attr("src", "");
			// alert(backurl);
			// reload frame
			showmeerkat(backurl,{ width: 600 });
			// $('#ranking').dialog('close');
			}}
		).dialog("open");
	});
}
/*
var badline_arr = [];
function create_badline(){
	  var lineSymbol = {
    path: 'M 0,-1 0,1',
    strokeOpacity: 1,
    scale: 4
  };

  // Create the polyline, passing the symbol in the 'icons' property.
  // Give the line an opacity of 0.
  // Repeat the symbol at intervals of 20 pixels to create the dashed effect.
  for(var i=0; i < badlines.length; i++) {
  badline_arr[i]= new google.maps.Polyline({
    path: [{ lng: parseFloat(badlines[i][0].lng),lat: parseFloat(badlines[i][0].lat)},
	       {  lng: parseFloat(badlines[i][1].lng),lat: parseFloat(badlines[i][1].lat) } ],
    strokeOpacity: 0,
    icons: [{
      icon: lineSymbol,
      offset: '0',
      repeat: '20px'
    }],
    map: map
  });
  }
}
function remove_badline() {
  f(r(var i=0; i < badline_arr.length; i++) {
		badline_arr[i].setMap(null);
	  }
}
*/
