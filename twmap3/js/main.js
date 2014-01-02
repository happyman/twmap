// $Id$
var map;

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
var availableTagsLocation = [];
var availableTagsMeta = [];
var showCenterMarker_id = "";
var locInfo_name = "我的座標";

var show_label = 1; //getParameterByName("show_label")? getParameterByName("show_label") : 1;
var opacity = getParameterByName("opacity")? getParameterByName("opacity") : 0.71;
var got_geo = 0;
// geocoding
var geocoder;
// var show_kml = (getParameterByName("kml")) ? 1: 0;
var show_kml_layer = 0; // getParameterByName("show_kml_layer")?  getParameterByName("show_kml_layer") : 1;
var GPSLayer; // external kml layer
var kmlArray = [];
var kmlArrayMax = 50;
var SunriverMapOptions = {
	getTileUrl_sunriver: function(coord, zoom) {
		return "http://210.59.147.231/~happyman/mapserv/mapserv.php?" + "zoom=" + zoom + "&x=" + coord.x + "&y=" + coord.y;

	},
	getTileUrl: function(a, b) {
		var z = 17 - b;
		return "http://map.happyman.idv.tw/fcgi-bin/mapserv.fcgi?x=" + a.x + "&y=" + a.y + "&zoom=" + z;
	},
	tileSize: new google.maps.Size(256, 256),
	maxZoom: 18,
	minZoom: 13,
	name: '一版底圖',
	alt: 'sunriver tile map'
}
var GoogleNameOptions = {
	getTileUrl: function(a, b) { 
		return "http://mts1.google.com/vt/lyrs=h@195026035&x=" + a.x + "&y=" + a.y +"&z=" + b;
	},
	tileSize: new google.maps.Size(256,256),
	maxZoom: 20,
	minZoom: 0,
	name: 'GoogleNames'
}
var NLSCNameOptions = {
	getTileUrl: function(a, b) { 

		return 'http://maps.nlsc.gov.tw/S_Maps/wmts?SERVICE=WMTS&REQUEST=GetTile&VERSION=1.0.0&LAYER=EMAP2&STYLE=_null&TILEMATRIXSET=EPSG:3857&TILEMATRIX=EPSG:3857:' + b + "&TILEROW=" + a.y + "&TILECOL=" + a.x + "&FORMAT=image%2Fpng";
	},
	tileSize: new google.maps.Size(256,256),
	maxZoom: 19,
	name: 'NLSCNames'
}
var OSM_GDEM_Options = {
	maxZoom: 18,
	minZoom: 13,
	name: 'GDEM',
	tileSize: new google.maps.Size(256, 256),
	getTileUrl: function(a,b) {
		var z=b;
		return "http://129.206.74.245:8006/tms_il.ashx?x="+ a.x + "&y=" + a.y +"&z=" + b;
		// return "http://210.69.91.53/ArcGIS/rest/services/EMAP_Raster102/MapServer/tile/"+z+"/"+a.y+"/"+a.x;
	}

}
//var url = 'http://210.69.91.63/ArcGIS/rest/services/EMAP_Raster102/MapServer';
//var agsType = new  gmaps.ags.MapType(url,{name:'ArcGIS'});



var Taiwan_General_2011_MapOptions = {
	getTileUrl: function(a, b) {
		var set="PHOTO2";
		var road="EMAP1";
		//	var z = 17 - b;
		//		return "http://gis.sinica.edu.tw/googlemap/Formosat_Taiwan_2011/" + z + "/"+ a.x + "/IMG_" + a.x + "_" + a.y + "_" + z + ".png";
		return 'http://maps.nlsc.gov.tw/S_Maps/wmts?SERVICE=WMTS&REQUEST=GetTile&VERSION=1.0.0&LAYER=PHOTO2&STYLE=_null&TILEMATRIXSET=EPSG:3857&TILEMATRIX=EPSG:3857:' + b + "&TILEROW=" + a.y + "&TILECOL=" + a.x + "&FORMAT=image%2Fpng";
	},
	tileSize: new google.maps.Size(256, 256),
	//maxZoom: 16,
	//minZoom: 6,
	maxZoom: 19,
	minZoom: 9,
	name: "NLSC",
	alt: "內政部國土測量中心 2011"

}
var Taiwan_Formosat_2011_MapOptions = {
	getTileUrl: function(a, b) {
		var z = 17 - b;
		return "http://gis.sinica.edu.tw/googlemap/Formosat_Taiwan_2011/" + z + "/"+ a.x + "/IMG_" + a.x + "_" + a.y + "_" + z + ".png";
	},
	tileSize: new google.maps.Size(256, 256),
	maxZoom: 16,
	minZoom: 6,
	name: "台灣福衛2號2011"
}
var TaiwanMapOptions = {
	getTileUrl: function(coord, zoom) {
		return "http://rs.happyman.idv.tw/map/tw25k2001/zxy/" + zoom + "_" + coord.x + "_" + coord.y + ".png";
	},
	tileSize: new google.maps.Size(256, 256),
	maxZoom: 16,
	minZoom: 10,
	name: "台灣",
	alt: 'Taiwan TW67 Map'
}
var SunriverMapType = new google.maps.ImageMapType(SunriverMapOptions);
var TaiwanMapType = new google.maps.ImageMapType(TaiwanMapOptions);
var OSM_GDEM_MapType = new google.maps.ImageMapType(OSM_GDEM_Options);
//var Taiwan_Formosat_2011_MapType = new google.maps.ImageMapType(Taiwan_Formosat_2011_MapOptions);
var Taiwan_General_2011_MapType = new google.maps.ImageMapType(Taiwan_General_2011_MapOptions);
var GoogleNameMapType =  new google.maps.ImageMapType(GoogleNameOptions);
var NLSCNameMapType =  new google.maps.ImageMapType(NLSCNameOptions);
var BackgroundMapType;
var BackgroundMapOptions;
var BackgroundMap = 0;

var oms;
var markerCluster;
var allmarkers = [];
var show_marker = "a"; // getParameterByName("show_marker")? getParameterByName("show_marker") : 1;

var myInfoBoxOptions = {
	disableAutoPan: false,
	maxWidth: 200,
	alignBottom: true,
	pixelOffset: new google.maps.Size(-100, -35),
	boxClass: "ui-corner-all infobox",
	zIndex: null,
	boxStyle: {
		background: "white",
		width: "200px"
	},
	closeBoxMargin: "2px 2px 2px 2px",
	closeBoxURL: "http://www.google.com/intl/en_us/mapfiles/close.gif",
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
	if (show_label == 0 || tags_ready == 0 || markers_ready == 0) return;
	var bounds = map.getBounds();
	// 太小範圍不顯示
	if (map.getZoom() < 12) {
		for (i = 0; i < markerArrayMax; i++) {
			markerArray[i].setMap(null);
			labelArray[i].setMap(null);
		}
		return;
	}
	var j = 0;
	var i;
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
function showGrid(loc) {

	var vp = map.getBounds();
	var vpp = [];
	var twp = [];
	var newp = [];
	var dstBound = TW_Bounds;
	var ph = 0;
	if (loc == 'TWD67PH') {
		dstBound = PH_Bounds;
		ph = 1;
	} else if (loc == 'WGS84') {
		if (!grid)
			grid = new Graticule(map, true);
		else
			grid.show();
		return;
	} else if (loc == 'None') {
		if (grid)
			grid.hide();
		// clean grid
		if (poly)
			for(var i=0; i<poly.length; i++)
			poly[i].setMap(null);
		if (polylabel)
			for(i=0; i<polylabel.length; i++)
			polylabel[i].setMap(null);
		return true;
	}
	if (vp && vp.intersects(dstBound)) {
		// 左下 x 取大
		if (vp.getSouthWest().lng() > dstBound.getSouthWest().lng())
			newp[1] = vp.getSouthWest().lng();
		else
			newp[1] = dstBound.getSouthWest().lng();
		// 左下 y 取大
		if (vp.getSouthWest().lat() > dstBound.getSouthWest().lat()) 
			newp[0] = vp.getSouthWest().lat();
		else
			newp[0] =  dstBound.getSouthWest().lat();
		// 右上 y 取小
		if (vp.getNorthEast().lat() < dstBound.getNorthEast().lat())
			newp[2] = vp.getNorthEast().lat();
		else
			newp[2] =  dstBound.getNorthEast().lat();
		// 右上 x 取小
		if (vp.getNorthEast().lng() < dstBound.getNorthEast().lng())
			newp[3] = vp.getNorthEast().lng();
		else
			newp[3] = dstBound.getNorthEast().lng();
		// 重合的區域
		var InterBounds = new google.maps.LatLngBounds();
		InterBounds.extend(new google.maps.LatLng(newp[0],newp[1]));
		InterBounds.extend(new google.maps.LatLng(newp[2],newp[3]));
	}
	if (!bigPoly)
		bigPoly = new google.maps.Rectangle({map: map,fillColor: "red", fillOpacity: 0.1 });
	if (InterBounds) {
		//bigPoly.setBounds(InterBounds);
		var sw = lonlat_getblock(InterBounds.getSouthWest().lng(), InterBounds.getSouthWest().lat(), ph);
		var ne = lonlat_getblock(InterBounds.getNorthEast().lng(), InterBounds.getNorthEast().lat(), ph);
		var minx = sw[0].x;
		var maxx = ne[1].x;
		var miny = sw[1].y;
		var maxy = ne[0].y;
		// 畫 grid
		lonlat_range_getblock(minx,miny,maxx,maxy, ph);
	}
}

var parsedkml = 0;
function showInsideKML() {
	//alert("showInsideKML: "+tags_ready);
	if (show_kml_layer != 1) return;
	if (tags_ready == 0 ) return;
	if (map.getZoom() < 13) {
		for(var key in kmlArray) {
			kmlArray[key].hideDocument(kmlArray[key].docs[0]);
			delete kmlArray[key];
		}
		return;
	}
	var parts = map.getBounds().toUrlValue(5).split(",").map(Number);
	//console.log(parts);
	var minX, minY, maxX, maxY;
	if (parts[1] > parts[3]) {
		minX = parts[3];
		maxX = parts[1];
	} else {
		maxX = parts[3];
		minX = parts[1];
	}
	if (parts[0] > parts[2]) {
		maxY = parts[0];
		minY = parts[2];
	} else {
		maxY = parts[2];
		minY = parts[0];
	}
	//  左上座標
	var cc = is_taiwan(maxY, minX);
	if (cc == 0 )
		return;
	var ph = (cc==2)?1:0;
	var tl = lonlat2twd67(minX, maxY, ph);
	var br = lonlat2twd67(maxX, minY, ph);
	var keys = [];
	for (var key in kmlArray) {
		if (kmlArray.hasOwnProperty(key)) {
			keys.push(key);
		}
	}
	// todo: not yet support ph
	$.ajax({
			dataType: 'json',
			url: getkmlfrombounds_url,
			cache: false,
			data: {
				tlx: parseInt(tl.x),
				tly: parseInt(tl.y),
				brx: parseInt(br.x),
				bry: parseInt(br.y),
				gpx: 1,
				keys: keys.join(","),
				maxkeys: 0
			},

			success: function(data) {
				if (data.ok != true) return;
				// 總共要加幾個
				parsedkml = data.rsp.count['add'];
				//alert(parsedkml);
				if (parsedkml == 0 ) {
					if (!ismakingmap)
						$.unblockUI();
				}

				for(var key in data.rsp.add) {
					if (!kmlArray[key]) {

						kmlArray[key] = new geoXML3.parser(
							{map:map, singleInfoWindow: true,
								additional_marker_desc: data.rsp.add[key].desc,
								additional_path_desc: data.rsp.add[key].desc,
								zoom:false
						});
						kmlArray[key].parse(data.rsp.add[key].url + '&ts=' + (new Date()).getTime());
						google.maps.event.addListener( kmlArray[key], 'parsed',  function() {
								parsedkml--;
								$.unblockUI();
						});

					}

				}
				for(var key in data.rsp.del) {
					if (kmlArray[key]) {
						kmlArray[key].hideDocument(kmlArray[key].docs[0]);
						delete kmlArray[key];
					}
				}
			}
	});


}
function permLinkURL(goto) {
	var ver = (BackgroundMap==0)?3:1;
	var curMap = $("#changegname").val();
	var curGrid = $("#changegrid").val();
	return "<a href='?goto=" + goto + "&zoom="+ map.getZoom() +"&opacity="+ opacity + "&mapversion=" + ver + "&maptypeid="+ map.getMapTypeId() +"&show_label="+ show_label + "&show_kml_layer=" + show_kml_layer + "&show_marker=" + show_marker +"&roadmap="+ curMap+"&grid=" + curGrid +"'><img src='img/permlink.png' border=0/></a>";
}

// location Information
function locInfo(newpos) {
	//console.log( "locInfo:"+locInfo_name);
	var ll = is_taiwan(newpos.lat(), newpos.lng());
	if (ll == 2) {
		ph = 1;
		comment = "澎湖 TWD67:";
	} else {
		ph = 0;
		comment = "台灣 TWD67:";
	}
	var p = lonlat2twd67(newpos.lng(), newpos.lat(), ph);
	//content = "<div class='infowin'><b>" + (locInfo_name == '' ) ? "我的位置" : locInfo_name  +"</b>";

	content = "<div class='infowin'><b>" + locInfo_name  +"</b>";
	if (locInfo_name == "我的位置")
		content += permLinkURL( newpos.toUrlValue(5) );
	else
		content += permLinkURL( encodeURIComponent(locInfo_name) );
	content += "<br>經緯度: " + newpos.toUrlValue(5);
	content += "<br>座標: " + comment + ""+  Math.round(p.x) + "," + Math.round(p.y);
	content += "</div>";
	centerInfo.setContent(content);
	centerMarker.setTitle("座標位置");
	centerInfo.open(map,centerMarker);
	// updateView 會重新刷一次
	showCenterMarker_id = "";
}
// Tags Info
function tagInfo(newpos,id){
	var ll = is_taiwan(newpos.lat(), newpos.lng());
	if (ll == 2) {
		ph = 1;
		comment = "澎湖 TWD67:";
	} else {
		ph = 0;
		comment = "台灣 TWD67:";
	}
	var p = lonlat2twd67(newpos.lng(), newpos.lat(), ph);
	//centerInfo.setContent(comment+Math.round(p.x) + ","+Math.round(p.y));
	$.ajax({
			dataType: 'json',
			cache: false,
			url: pointdata_url,
			data: {
				"id": id,
				"beta": 1
			},

			success: function(data) {
				content = "<div class='infowin'><b>"+ data[0].name +"</b>";
				content += permLinkURL( encodeURIComponent(data[0].name) );
				content += "<br>座標: " + comment + "<br>"+ Math.round(p.x) + "," + Math.round(p.y);
				content += data[0].story;
				content += "</div>";

				centerInfo.setContent(content);
				// centerMarker.setMap(map);
				// centerMarker.setVisible(true);
				centerInfo.open(map, centerMarker);
				// showInsideMarkers();
				// 不管 zoom
				//if (map.getZoom() < 12 )
				//  map.setZoom(12);
			}
	});
	showCenterMarker_id = id;
}
function showCenterMarker(name) {
	var i;
	for (i = 0; i < availableTags.length; i++) {
		if (name == availableTags[i]) {
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
					// icon: new google.maps.MarkerImage("http://sites.google.com/site/mcmarkers/gachapeg-20.png")
			});
			google.maps.event.addListener(centerMarker, "dragend", function (e) {
					//alert(centerMarker.getPosition());
					var newpos = centerMarker.getPosition();
					locInfo(newpos);
			});
			google.maps.event.addListener(centerMarker, "dragstart", function (e) {
					locInfo_name = "我的位置";
					if (centerInfo)
						centerInfo.close();
			});
			google.maps.event.addListener(centerMarker, 'click', function() {
					centerInfo.open(map, centerMarker);
			});

			if (!centerInfo) {
				centerInfo = new InfoBox(myInfoBoxOptions);
			}
			tagInfo(availableTagsLocation[i], availableTagsMeta[i].id);
			// 放入 cookie
			$.cookie('twmap3_goto', name );
			return true;
		}
	}
	if (name == "" && centerMarker) {
		name = centerMarker.getPosition().toUrlValue(5);
		// alert(name);
	}
	// 如果沒找到, 看看格式對不對, 移動到座標
	var posxy = name.match(/^(\d+\.?\d+)\s*,\s*(\d+\.\d+)$/);
	var posxy1 = name.match(/^(\d+\.?\d+)\s+(\d+\.\d+)$/);
	var postw67 = name.match(/^(\d+)\s*,\s*(\d+)$/);
	var postw671 = name.match(/^(\d+)\s+(\d+)$/);
	var loc;
	var tmploc;
	if (posxy) {
		tmploc = { x: posxy[2], y: posxy[1] };
	} else if (posxy1) {
		tmploc = { x: posxy1[2], y: posxy1[1] };
	} else if (postw67) {
		tmploc = twd672lonlat(postw67[1],postw67[2],0);
	} else if (postw671) {
		tmploc = twd672lonlat(postw671[1],postw671[2],0);
	} else {
		// geocoding
		$.blockUI({ message: "查詢中..." });
		$.ajax({
				dataType: 'json',
				cache: false,
				url: geocodercache_url,
				data: {
					"op": 'get',
					"data": JSON.stringify({ 'address': name })
				},

				success: function(data) {
					if (data.ok == true ) {
						// alert("from cache");
						if (data.rsp.is_tw == 0 ) {
							alert('cached: 不在台澎範圍內');
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
						geocoder.geocode( { 'address': name + ",Taiwan", 'region': 'TW'}, function(results, status) {
								if (status == google.maps.GeocoderStatus.OK) {
									$.unblockUI();
									loc = results[0].geometry.location;						
									var p = is_taiwan(loc.lat(), loc.lng());
									if (p==0) {
										alert("不在台澎範圍");
										return false;
									}
									//console.log(results);
									// update only full match 
									if(results[0].address_components[0].long_name == name)
										exact = 1;
									else
										exact = 0;
									$.post(geocodercache_url, { "op": 'set', "data": JSON.stringify({'address': name, 'lat': loc.lat(), 'lng': loc.lng(), 'is_tw': p, 'exact': exact, 'faddr': results[0].formatted_address, 'name': results[0].address_components[0].long_name}) }, function(data) { return ; alert("update cache"+ data.ok ); });


									showCenterMarker_real(loc, results[0].address_components[0].long_name);
								} else {
									$.unblockUI();
									// alert("Geocode was not successful for the following reason: " + status);
									alert("找不到喔! 請輸入 地址 或 座標格式 twd67 X,Y 如 310300,2703000 或者經緯度 lat,lon 24.430623,121.603503");
									return false;
								}
						});
						return false;

					} 
				},  // success
				error: function() {
					$.unblockUI();
					alert("查詢程式有誤");
					return false;
				}
		}); // ajax
	} // else
	if (tmploc) {
		var p = is_taiwan(tmploc.y, tmploc.x);
		if (p==0) {
			alert("不在台澎範圍");
			return false;
		}
		loc = new google.maps.LatLng(tmploc.y,tmploc.x);
		showCenterMarker_real(loc);
	}
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
			//icon: new google.maps.MarkerImage("http://sites.google.com/site/mcmarkers/gachapeg-20.png")
	});
	google.maps.event.addListener(centerMarker, "dragend", function (e) {
			//alert(centerMarker.getPosition());
			var newpos = centerMarker.getPosition();
			locInfo(newpos);
	});
	google.maps.event.addListener(centerMarker, "dragstart", function (e) {
			locInfo_name = "我的位置";
			if (centerInfo)
				centerInfo.close();
	});
	google.maps.event.addListener(centerMarker, 'click', function() {
			centerInfo.open(map, centerMarker);
	});

	if (!centerInfo) {
		centerInfo = new InfoBox(myInfoBoxOptions);
	}
	map.panTo(loc);
	locInfo_name = (typeof name === "undefined") ? loc.toUrlValue(5) : name;
	locInfo(loc);
	// save cookie
	$.cookie('twmap3_goto', name );
	return true;
}
function initialtags(opt) {
	if (tags_ready == 1) return;

	availableTags = [];
	availableTagsLocation = [];
	availableTagsMeta = [];
	$.ajax({
			dataType: 'json',
			cache: false,
			url: pointdata_url,
			//data: {
			//		filter: show_marker
			//	},
			success: function(data) {
				for (var i = 0; i < data.length; i++) {
					availableTags[i] = data[i].name;
					//console.log(data[i][0]);
					availableTagsLocation[i] = new google.maps.LatLng(data[i].y, data[i].x);
					availableTagsMeta[i] = {
						id: data[i].id,
						//sym: data[i].sym
						type: data[i].type,
						class: data[i].class,
						mt100: data[i].mt100
					};

				}
				$("#tags").autocomplete({
						source: availableTags
				});

				$("#search_text").html("搜尋");
				$("#tags").prop('disabled', false);

				tags_ready = 1;
				// 初始完畢, 顯示 lables
				initialmarkers();
				showInsideMarkers();
				if (opt.msg) {
					alert(opt.msg + "共" + availableTagsLocation.length + "筆資料");
				}
			}
	});
}

function initialmarkers() {
	var shadow = new google.maps.MarkerImage("img/shadow.png", new google.maps.Size(36.0, 18.0), new google.maps.Point(0, 0), new google.maps.Point(0, 19)

	);
	var icon = [];
	icon[4] = "http://map.happyman.idv.tw/kml/3-4ok.png";
	icon[1] = "http://map.happyman.idv.tw/kml/3-1ok.png";
	icon[2] = "http://map.happyman.idv.tw/kml/3-2ok.png";
	icon[3] = "http://map.happyman.idv.tw/kml/3-3ok.png";
	icon[5] = "http://map.happyman.idv.tw/kml/3-5new.png";
	var mysetIcon = function(i, isShadow) {
		if (i > 0 && i <= 5) {
			if (isShadow) return shadow;
			return icon[i];
		}
		if (isShadow) return null;
		return "img/pointer01.jpg";
	}
	var mysetIcon2 = function(type, isShadow) {
		if (type == "其他") {

			if (isShadow)
				return 	"http://maps.google.com/mapfiles/kml/pal4/icon24s.png";
			return "http://maps.google.com/mapfiles/kml/pal4/icon24.png";
		}
		if (isShadow)
			return new google.maps.MarkerImage("http://map.happyman.idv.tw/icon/shadow-"+encodeURIComponent(type)+".png", null, new google.maps.Point(0, 0), new google.maps.Point(0, 19));
		return "http://map.happyman.idv.tw/icon/"+encodeURIComponent(type)+".png";
	}

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
				shadow: mysetIcon2(availableTagsMeta[i].type, 1),
				position: availableTagsLocation[i]
		});
		oms.addMarker(allmarkers[i]);

	}
	oms.addListener('click', function(marker) {
			showCenterMarker(marker.title);
	});
	//markerCluster = new MarkerClusterer(map, allmarkers);
	/*
	 oms.addListener('spiderfy', function(markers) {
			 for (var i = 0; i < markers.length; i++) {
				 //markers[i].setIcon(iconWithColor(spiderfiedColor));
				 markers[i].setShadow(null);
			 }
			 centerInfo.close();
	 });
	 oms.addListener('unspiderfy', function(markers) {
			 for (var i = 0; i < markers.length; i++) {
				 //markers[i].setIcon(iconWithColor(usualColor));
				 markers[i].setShadow(shadow);
			 }
	 });
	 */
	window.oms = oms;

}
var listener;
var TW_Bounds;
var PH_Bounds;
function initialize() {

	geocoder = new google.maps.Geocoder();

	var latlng = new google.maps.LatLng(23.55080, 121.13220);
	var myOptions = {
		zoom: 14,
		maxZoom: 20,
		center: latlng,
		overviewMapControl: true,
		mapTypeControlOptions: {
			style: google.maps.MapTypeControlStyle.DROPDOWN_MENU,
			position: google.maps.ControlPosition.TOP_LEFT,
			//	draggableCursor: 'url(img/A4-32x32.gif),default',
			mapTypeIds: ['general2011','twmapv1', google.maps.MapTypeId.TERRAIN, google.maps.MapTypeId.SATELLITE ]
		}

	};
	if (is_mobile) {
		myOptions.mapTypeControlOptions.position = google.maps.ControlPosition.RIGHT_BOTTOM;
	} 
	resizeMap();

	map = new google.maps.Map(document.getElementById("map_canvas"), myOptions);
	if (!is_mobile)
		map.enableKeyDragZoom();
	map.mapTypes.set('gdem', OSM_GDEM_MapType);
	// map.mapTypes.set('gdem', agsType);
	map.mapTypes.set('twmapv1', SunriverMapType);
	map.mapTypes.set('taiwan', TaiwanMapType);
	map.mapTypes.set('general2011', Taiwan_General_2011_MapType);
	// 前景免設
	//map.mapTypes.set('googlename', GoogleNameMapType);
	//map.mapTypes.set('nlscname', NLSCNameMapType);
	// 背景層
	BackgroundMapType = TaiwanMapType;
	BackgroundMapOptions = TaiwanMapOptions;
	// 初始顯示哪張圖?
	// map.setMapTypeId(google.maps.MapTypeId.TERRAIN);
	map.setMapTypeId(google.maps.MapTypeId.SATELLITE);
	// 背景哪張圖
	map.overlayMapTypes.insertAt(0, BackgroundMapType);
	map.overlayMapTypes.insertAt(1, GoogleNameMapType);

	// 控制背景圖的透明度
	var bar = document.getElementById("op");
	var container = $("#opSlider");
	//var container = document.getElementById("opSlider");
	//var range = (parseInt(container.style.width) - parseInt(bar.style.width));
	var range = container.width() - $("#op").width();

	map.controls[google.maps.ControlPosition.TOP_LEFT].push(document.getElementById('opContainer'));
	var opSlider = new ExtDraggableObject(bar, {
			restrictY: true,
			container: container
	});
	//

	// 顯示預設透明度, 一定要 改變才會生效.. 不懂 
	changeBackgroundOpacity(opacity-0.001);
	opSlider.setValueX(range * opacity);
	showOp(opacity);

	//  Taiwan bounds
	TW_Bounds = new google.maps.LatLngBounds();
	TW_Bounds.extend(new google.maps.LatLng(21.8,119.8));
	TW_Bounds.extend(new google.maps.LatLng(25.7,123.0));
	PH_Bounds = new google.maps.LatLngBounds();
	PH_Bounds.extend(new google.maps.LatLng(23.15,119.2));
	PH_Bounds.extend(new google.maps.LatLng(23.75,119.75));

	google.maps.event.addListener(opSlider, 'drag', function(evt) {
			var op = opSlider.left() / range;
			if (op >= 1) op = 1;
			if (op <= 0) op = 0;
			changeBackgroundOpacity(op);
			opSlider.setValueX(range * opacity);
			showOp(opacity);
	});

	google.maps.event.addDomListener(document.getElementById('less'), 'click', function() {
			var op = opacity - 0.1;
			if (op < 0) op = 0; // return;
			changeBackgroundOpacity(op);
			opSlider.setValueX(range * opacity);
			showOp(opacity);
	});
	google.maps.event.addDomListener(document.getElementById('more'), 'click', function() {
			var op = opacity + 0.1;
			if (op > 1) op = 1; // return;
			changeBackgroundOpacity(op);
			opSlider.setValueX(range * opacity);
			showOp(opacity);

	});
	// 畫框框
	google.maps.event.addListener( map, 'maptypeid_changed', function() { 
			updateView("info_only");
	});
	listener = google.maps.event.addListener(map, 'idle', function() { 
			updateView('bounds_changed');
	});

	google.maps.event.addListener(map, "rightclick", function(event) {
			var newpos = event.latLng;
			locInfo_name = "我的位置";
			centerMarker.setPosition(newpos);
			locInfo(newpos);
	});
	google.maps.event.addListener(map, 'click', addremove_polygon);

	// 載入 Tags
	$("#tags").val("初始化中");
	// 搜尋框被 focus 跟 blur 的時候
	$("input:text").on('focus',function() {
			$(this).css('font-size','3em');
			$(this).autoGrowInput().trigger('keydown');
	}).on('blur',function() {
			$(this).css({'font-size':'1em'});
			$(this).autoGrowInput().trigger('keydown');
	});
	// 按下 esc key
	$(document).keyup(function(e) {
			if (e.keyCode == 27) { $("input:text").blur(); }   // esc
	});

	tags_ready = 0;
	initialtags({});

	$("#gotoform").submit(function() {
			$("#goto").trigger('click');
			return false;
	});
	$("#goto").click(function() {
			letsgo();
	});
	function letsgo() {
		$("#tags").blur();
		if (tags_ready == 0 ) {
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
				position: latlng,
				icon: "img/pointer01.jpg",
				title: "init",
				draggable: true,
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
	// 切換舊版地圖
	$("#changemap").click(function() {
			if (BackgroundMap == 0) {
				BackgroundMapType = SunriverMapType;
				BackgroundMapOptions = SunriverMapOptions;
				BackgroundMap = 1;
				$("#changemap").addClass("disable");
				$("#changemap").text("經建一");
			} else {
				BackgroundMapType = TaiwanMapType;
				BackgroundMapOptions = TaiwanMapOptions;
				BackgroundMap = 0;
				$("#changemap").removeClass("disable");
				$("#changemap").text("經建三");
			}
			map.overlayMapTypes.removeAt(0, BackgroundMapType);
			map.overlayMapTypes.insertAt(0, BackgroundMapType);
			updateView("info_only");
	});
	// 切換前景圖
	$('#changegname').change(function() {
			var curMap = (map.overlayMapTypes.length == 2 )? map.overlayMapTypes.getArray()[1].name : 'None';
			var newMap = $('#changegname').val();
			if (curMap == newMap) 
				return true;

			if ($('#changegname').val() == 'None'){
				map.overlayMapTypes.removeAt(1);
				return true;
			}

			if (curMap != 'None')
				map.overlayMapTypes.removeAt(1);
			if (newMap == 'GoogleNames')
				map.overlayMapTypes.insertAt(1, GoogleNameMapType);
			else
				map.overlayMapTypes.insertAt(1, NLSCNameMapType);
			updateView("info_only");
	});
	$('#changegrid').change(function() {
			showGrid('None');
			showGrid($('#changegrid').val());
	});
	$("#inputtitlebtn2").click(function() {
			ismakingmap = 0;
			$.unblockUI();
	});
	$("#inputtitlebtn").click(function() {
			// console.log($("#inputtitle"));
			if ($("#inputtitle").val() != "") {
				ismakingmap = 0;
				$.unblockUI();
				callmake = callmake + "&title=" + $('#inputtitle').val();

				if (parent.location == window.location) {
					url = "http://map.happyman.idv.tw/twmap/main.php?tab=0&" + callmake;
				} else {
					// for test url
					var goto = parse_url(parent.location.href);
					url = goto['scheme'] + "://" + goto['host'] + goto['path'].replace(/\\/g,'/').replace(/\/[^\/]*$/, '') + "/main.php?tab=0&" + callmake;
				}
				if (confirm("程式將會傳送參數給地圖產生器,確定嘛?")) {
					if (parent.location != window.location) parent.location.href = url
					else location.href = url;
				}

			} else {
				alert("請輸入地圖標題");
			}
	});
	$("#generate").click(function() {
			if (callmake == null) {
				alert("請選擇範圍");
				return;
			}
			// 置中
			if (centerInfo)
				centerInfo.close();

			map.setCenter(new google.maps.LatLng(miniY+(maxiY-miniY)/2,miniX+(maxiX-miniX)/2));

			ismakingmap = 1;
			$.blockUI({ message: $('#inputtitleform') });
	});
	$("#about").click(function() {
			$("#footer").dialog();
	});
	$("#kml_sw").click(function() {
			// 還在載入途中
			if (parsedkml > 0 ){  return; }
			if (show_kml_layer == 1) {
				$.blockUI({ message: "..." });
				for (var key in kmlArray) {
					kmlArray[key].hideDocument(kmlArray[key].docs[0]);
					delete kmlArray[key];
				}
				show_kml_layer = 0;
				$("#kml_sw").addClass("disable");
				//alert($('#kml_sw').attr('class'));
				$.unblockUI();
			} else {
				$.blockUI({ message: "載入中" });
				show_kml_layer = 1;
				showInsideKML();
				$("#kml_sw").removeClass("disable");
			}
			updateView("info_only");
	});
	$("#label_sw").click(function() {
			if (show_label == 1) {
				show_label = 0;
				// remove all markers
				for (var i = 0; i < markerArrayMax; i++) {
					markerArray[i].setMap(null);
					labelArray[i].setMap(null);
				}
				$("#label_sw").addClass("disable");
				// alert(show_label);
			} else {
				show_label = 1;
				showInsideMarkers();
				// alert(show_label);
				$("#label_sw").removeClass("disable");
			}
			updateView("info_only");
	});
	// 
	/*
	 $("#marker_sw").click(function() {
			 $.blockUI({ message: "..." });
			 if (show_marker == 1) {
				 show_marker = 0;
				 //markerCluster.clearMarkers();
				 for (var i = 0; i < allmarkers.length; i++)
					 allmarkers[i].setMap(null);
				 oms.clearMarkers();
				 //alert(show_marker);
				 $("#marker_sw").addClass("disable");
			 } else {
				 show_marker = 1;
				 initialmarkers();
				 showInsideMarkers();
				 $("#marker_sw").removeClass("disable");
			 }
			 updateView("info_only");
			 $.unblockUI();
	 });
	 */
	if (admin_role == 1 ) 
		$("#marker_reload").show();
	else
		$("#marker_reload").hide();

	// admin
	$("#marker_reload").click( function() {
			markerReload({msg: "載入完成"});
			// 重新顯示
	});
	// map.controls[google.maps.ControlPosition.TOP_LEFT].push(document.getElementById('locContainer'));
	// everything is ready
	$("#marker_sw_select").dropdownchecklist({firstItemChecksAll: true, explicitClose: '..選好了',
			onComplete: function(selector) {
				var values = "";
				for( i=0; i < selector.options.length; i++ ) {
					if (selector.options[i].selected && (selector.options[i].value != "")) {
						if ( values != "" ) values += ",";
						values += selector.options[i].value;
					}
				}
				if (values != show_marker) {
					if (values == "")
						values = "0";
					show_marker = values;
					//markerReload({msg: "載入完成"});
					markerFilter();
				}

			}
	});
	//$("#ddcl-marker_sw_select").addClass("ui-corner-all");
	$(".ui-dropdownchecklist-selector").addClass("ui-corner-all");
	$(".ui-dropdownchecklist-text").css({"font-size":"12px", "margin": "1px"});
	$("#ddcl-marker_sw_select").offset({top: 9});

	$('#changegname').menu();
	$('#changegrid').menu();



	// 最後把中心點移動到有興趣的位置
	// 改變預設
	setTimeout(function() {
			if (getParameterByName("show_label") && getParameterByName("show_label") == 0 ) { $("#label_sw").trigger('click'); }
			//if (getParameterByName("show_marker") && getParameterByName("show_marker") == 0 ) { $("#marker_sw").trigger('click'); }
			if (getParameterByName("show_marker")) { 
				show_marker = getParameterByName("show_marker"); 
				if (show_marker == '0' )
					$("#marker_sw_select").val([]);
				else
					$("#marker_sw_select").val(show_marker.split(",")); 
				$("#marker_sw_select").dropdownchecklist("refresh"); 
				markerFilter(); 
			}
			if (getParameterByName("show_kml_layer") && getParameterByName("show_kml_layer") == 1 ) { $("#kml_sw").trigger('click'); }
			if (getParameterByName("zoom")) { map.setZoom(parseInt(getParameterByName("zoom"))); }
			if (getParameterByName("maptypeid")) { map.setMapTypeId(getParameterByName("maptypeid")); }
			if (getParameterByName("roadmap")) { $("#changegname").val(getParameterByName("roadmap")); $("#changegname").change(); }
			if (getParameterByName("grid")) { $("#changegrid").val(getParameterByName("grid")); $("#changegrid").change(); }

			if (getParameterByName("mapversion")) {
				if (getParameterByName("mapversion") == 1)
					$("#changemap").trigger('click');
			}

			if (getParameterByName("goto")) {
				//showCenterMarker(getParameterByName("goto"));
				$("#tags").val(getParameterByName("goto"));
				$("#goto").trigger('click');
			}
			// 顯示 external kml layer
			else if (getParameterByName('kml')) {

				GPSLayer = new google.maps.KmlLayer(getParameterByName('kml') + '?ts=' + (new Date()).getTime(), {
						preserveViewport: false
				});
				GPSLayer.setMap(map);
			} else if ($.cookie('twmap3_goto')) {
				$("#tags").val($.cookie('twmap3_goto'));
				$("#goto").trigger('click');

			}
			// 那就顯示一個點, 如果拿得到座標就到座標, 不然就任選一個興趣點
			else {
				if (navigator.geolocation) {
					// 如果 user 不選, 20 秒後移走
					var location_timeout = setTimeout(function() { 
							if (got_geo == 0 ) 
								FeatureLocation();
					}, 20000);

					navigator.geolocation.getCurrentPosition( function(position) {
							CurrentLocation(position);
							var moveDiv = document.createElement('div');
							var myCustomControl2 = new curLocControl(moveDiv, map);
							map.controls[google.maps.ControlPosition.LEFT_BOTTOM].push(moveDiv);
						}, 

						function (err) { 
							// alert(err.code);
							clearTimeout(location_timeout);
							FeatureLocation(); }, 
						{ timeout: 3000 }
					);

				} else {
					FeatureLocation();
				}
			}
			// 最後處理手機的事情
			if (is_mobile) {
				// 隱藏 navigator bar
				setTimeout(function(){
						window.scrollTo(0, 1);
				}, 0);
				// 隱藏一些 button
				$("#about").hide();
				$("#generate").hide();
				$("#changemap").hide();
				$("#search_text").hide();
				$("#loc").hide();
				google.maps.event.clearListeners(map, 'click');
				// 建立 logo
				var myCustomControlDiv = document.createElement('div');
				var myCustomControl = new MyCustomControl(myCustomControlDiv, map);
				map.controls[google.maps.ControlPosition.BOTTOM_LEFT].push(myCustomControlDiv);
			} // is_mobile

		},
		500);
} // end of initialize
function MyCustomControl(controlDiv, map) {
	var control = this;    
	var testBtn = document.createElement('button');
	testBtn.id = 'testBtn';    
	testBtn.className = 'ui-state-default ui-corner-all';
	testBtn.innerHTML = $("#about").html();
	controlDiv.appendChild(testBtn);

	// wire up jquery click
	$(testBtn).click (function() {        
			$("#footer").dialog();
	});

}
function resizeMap() {
	var viewport_height = ($(window).height() < 460 )?  460 : $(window).height();
	$("#map_canvas").height( viewport_height - 33+ "px");
	if(map != null) {
		google.maps.event.trigger(map, 'resize');
	}
}
function curLocControl(controlDiv, map) {
	var control = this;    
	var testBtn = document.createElement('button');
	testBtn.id = 'moveBtn';    
	testBtn.className = 'ui-state-default ui-corner-all';
	testBtn.innerHTML = " 目前位置 ";
	controlDiv.appendChild(testBtn);
	// 
	$(testBtn).click(function() {
			navigator.geolocation.getCurrentPosition( CurrentLocation );
	});
}
function CurrentLocation(position) {
	// user 提供資訊
	got_geo = 1;
	var pos =  new google.maps.LatLng(position.coords.latitude,position.coords.longitude);
	$("#tags").val(pos.toUrlValue(5));
	$("#goto").trigger('click');
}

function FeatureLocation() {
	var feature = [ "三角錐山", "南二子山北峰", "敷島山", "大檜山", "武陵山", "佐久間山", 
		"錐錐谷", "丹錐山", "霧頭山", "出雲山", "西巴杜蘭" ];
	$("#tags").val(feature[Math.floor(Math.random() * feature.length)]);
	$("#goto").trigger('click');

}
function updateView(type) {
	//hack
	if ($('div.gmnoprint').last().find("div").first().css("font-size") != "15px") {
		$('div.gmnoprint').last().find("div").css({ "top": "3px","font-size": "15px"} );
		$('div.gmnoprint').last().find("div").first().addClass("ui-corner-all");
	}

	if (type != "info_only")  {
		showInsideMarkers();
		showInsideKML();
	}
	if ($('#changegrid') != 'None')
		showGrid($('#changegrid').val());
	//} else if (type == 'bounds_changed') {
	//	showInsideMarkers();
	//	showInsideKML();
	//	return;
	//}
	// 如果已經關閉舊不用重開
	if (centerInfo && centerInfo.getMap()) {
		var newpos = centerMarker.getPosition();
		// 如果不在範圍內,就關了他吧
		var bounds = map.getBounds();
		if (bounds && !bounds.contains(newpos)) {
			centerInfo.close();
			return;
		}
		// 就這樣
		if (showCenterMarker_id == '' ) {
			locInfo(newpos);
		} else {
			tagInfo(newpos, showCenterMarker_id);
		}
	}

}
function markerReload(opt) {
	// 清除 label
	if (show_label) {
		for (var i = 0; i < markerArrayMax; i++) {
			markerArray[i].setMap(null);
			labelArray[i].setMap(null);
		}
	}
	// 清除 markers
	if (show_marker != "0") {
		for (i = 0; i < allmarkers.length; i++)
			allmarkers[i].setMap(null);
		oms.clearMarkers();
		allmarkers = [];
	}
	if (centerInfo)
		centerInfo.close();
	tags_ready = 0;
	initialtags(opt);
}
function markerFilter() {
	var s = show_marker.split(",");
	//console.log(s);
	for (i = 0; i < allmarkers.length; i++) {
		want =0;
		for(var k=0; k < s.length; k++) {
			if (s[k] == 'a') {
				want = 1;
				break;
			}
			// 如果只有單一為 0 的話
			if (s[k] == '0' && s.length == 1 ) {
				want = 0;
				break;
			}
			// 其他則忽略
			if (s[k] == '0') continue;
			if (s[k] == '5') {
				if (availableTagsMeta[i].mt100 == 1 ){
					want  = 1;
				}
			} else if (s[k] == '6') {
				if (availableTagsMeta[i].class == 0 ){
					want  = 1;
				}
			} else {
				if (availableTagsMeta[i].class == s[k] ){
					want  = 1;
				}
			}
		}
		if (want == 0 ) {
			allmarkers[i].setVisible(false);
		} else {
			allmarkers[i].setVisible(true);
		}
	}
	// 
	updateView("marker_switch");

}

function showmeerkat(url) {
	$('#meerkat').meerkat({
			background: '#ffffff',
			height: '100%',
			width: '830px',
			position: 'right',
			close: '.close-meerkat',
			dontShowAgain: '.dont-show',
			animationIn: 'slide',
			onMeerkatShow: function() { 
				//alert($('#meerkat').height());
				$("#meerkatiframe").css('height', $('#meerkat').height());
			},
			animationSpeed: 500
	}).removeClass('pos-left pos-bot pos-top').addClass('pos-right');
	$(".meerkat-content").html("<iframe id=\"meerkatiframe\" align=\"middle\" scrolling=\"yes\" width=830px style=\"height:100%\"  frameborder=\"0\" allowtransparency=\"true\" hspace=\"0\" vspace=\"0\" marginheight=\"0\" marginwidth=\"0\"src='"+url+"'></iframe>");
}
var poly = [];
var polylabel = [];
function lonlat_range_getblock(minx,miny,maxx,maxy,ph) {

	var gridColor = 'white';
	if (ph == 1) gridColor = 'yellow';
	var sw = lonlat2twd67(minx,miny,ph);
	var ne = lonlat2twd67(maxx,maxy,ph);
	var startx = Math.round(sw.x/1000)*1000;
	var starty = Math.round(sw.y/1000)*1000;
	var endx = Math.round(ne.x/1000)*1000;
	var endy = Math.round(ne.y/1000)*1000;
	// y 軸
	var i = 0;
	var j = 0;
	//console.log(startx + " " + starty + " " + endx + " " + endy);
	var xstep = 1000;
	var ystep = 1000;
	var curZoom = map.getZoom();
	var showlabel = 1;
	var adjusty = 0;
	var adjustx = 1;
	if (map.getZoom() < 9 ) {
		var sstep = 25000;
		xstep = sstep;
		ystep = sstep;
		endx = endx - (endx-startx)%sstep + sstep;
		endy = endy - (endy-starty)%sstep + sstep;
		showlabel = 0;
	} else if (curZoom >=9 && curZoom <= 12) {
		var sstep = 10000;
		xstep = sstep;
		ystep = sstep;
		endx = endx - (endx-startx)%sstep + sstep;
		endy = endy - (endy-starty)%sstep + sstep;
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
	for(var y=starty; y<=endy; y+=ystep) {
		var p = twd672lonlat(startx, y,ph);
		var p1 = twd672lonlat(endx, y ,ph);
		// 右邊一格
		var lp = twd672lonlat(startx + xstep, y+adjusty, ph );
		if (!poly[i])
			poly[i] = new google.maps.Polyline({map: map, path:[ new google.maps.LatLng(p.y,p.x), new google.maps.LatLng(p1.y,p1.x) ], strokeColor: gridColor, strokeWeight: 1  });
		else 
			poly[i].setPath([ new google.maps.LatLng(p.y,p.x), new google.maps.LatLng(p1.y,p1.x) ]);
		poly[i].setOptions({strokeColor: gridColor});
		poly[i].setMap(map);
		// 畫出 Y 軸
		if (showlabel) {
			if (!polylabel[j])
				polylabel[j] = new Label({ map: map });
			polylabel[j].setValues({position: new google.maps.LatLng(lp.y,lp.x), text: y/1000 , map: map, style: 'color: '+ gridColor + '; cursor: none; background-color: none; border: 0;' });
			j++;
		}

		i++;
	}
	// x 軸
	for(var x = startx; x<= endx; x+=xstep ) {
		var p = twd672lonlat(x, starty,ph);
		var p1 = twd672lonlat(x, endy ,ph);
		// 往上
		var lp = twd672lonlat(x, starty + ystep*adjustx, ph);
		if (!poly[i])
			poly[i] = new google.maps.Polyline({map: map, path:[ new google.maps.LatLng(p.y,p.x), new google.maps.LatLng(p1.y,p1.x) ], strokeColor: gridColor, strokeWeight: 1});
		else {
			poly[i].setPath([ new google.maps.LatLng(p.y,p.x), new google.maps.LatLng(p1.y,p1.x) ]);
			poly[i].setOptions({strokeColor: gridColor});
			poly[i].setMap(map);
		}
		if (showlabel == 1) {
			if (!polylabel[j])
				polylabel[j] = new Label({ map: map });
			polylabel[j].setValues({position: new google.maps.LatLng(lp.y,lp.x), text: x/1000, map: map, style: 'color: '+gridColor+'; background-color: none; border: 0;' });
			j++;
		}
		i++;
	}
	for (var k=j; k< polylabel.length; k++) {
		polylabel[k].setMap(null);
	}
	for(var j=i; j< poly.length; j++)
		poly[j].setMap(null);

}

