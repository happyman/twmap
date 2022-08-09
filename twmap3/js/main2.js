// $Id$
var map;

// 邊框變數
var miniX = 9999;
var miniY = 0;
var maxiX = 0;
var maxiY = 9999;
// 產生器 url
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

var show_label = 1;
var opacity = 0.5;

var show_kml = (getParameterByName("kml")) ? 1: 0;
var GPSLayer; // kml layer
var SunriverMapOptions = {
	getTileUrl_sunriver: function(coord, zoom) {
		return "http://210.59.224.195/~happyman/mapserv/mapserv.php?" + "zoom=" + zoom + "&x=" + coord.x + "&y=" + coord.y;

	},
	getTileUrl: function(a, b) {
		var z = 17 - b;
		return "http://map.happyman.idv.tw/fcgi-bin/mapserv.fcgi?x=" + a.x + "&y=" + a.y + "&zoom=" + z;
	},
	tileSize: new google.maps.Size(256, 256),
	opacity: opacity,
	maxZoom: 15,
	minZoom: 13,
	name: '一版底圖',
	alt: 'sunriver tile map'
}
var TaiwanMapOptions = {
	getTileUrl: function(coord, zoom) {
		return "http://210.59.224.195/~happyman/mapserv/tw25k.php?" + "zoom=" + zoom + "&x=" + coord.x + "&y=" + coord.y;

	},
	tileSize: new google.maps.Size(256, 256),
	maxZoom: 16,
	minZoom: 10,
	name: "台灣",
	alt: 'Taiwan TW67 Map'
}
var SunriverMapType = new google.maps.ImageMapType(SunriverMapOptions);
var TaiwanMapType = new google.maps.ImageMapType(TaiwanMapOptions);
var BackgroundMapType;
var BackgroundMapOptions;
var BackgroundMap = 0;

var oms;
var markerCluster;
var allmarkers = [];
var show_marker = 1;

// 控制背景的透明度
function showOp(op) {
	//document.getElementById('opv').innerHTML = parseInt(op * 100);
	$('#opv').html(parseInt(op * 100));
}
function changeBackgroundOpacity(op) {
	map.overlayMapTypes.removeAt(0, BackgroundMapType);
	BackgroundMapOptions.opacity = op;
	BackgroundMapType = new google.maps.ImageMapType(BackgroundMapOptions);
	opacity = op;
	map.overlayMapTypes.insertAt(0, BackgroundMapType);
}
function showInsideMarkers() {
	// alert(tags_ready + " " + markers_ready + " " + show_label);
	if (show_label == 0 || tags_ready == 0 || markers_ready == 0) return;
	var bounds = map.getBounds();
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
		if (bounds.contains(availableTagsLocation[i])) {
			if (j >= markerArrayMax) return;
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
	//$("#loc").html(" 中心點: "+ map.getCenter().toUrlValue());
}
function showCenterMarker(name) {
	var i;
	for (i = 0; i < availableTags.length; i++) {
		if (name == availableTags[i]) {
			map.panTo(availableTagsLocation[i]);
			// 建立一個 marker
			if (!centerMarker) {
				centerMarker = new google.maps.Marker({
						title: availableTags[i],
						position: availableTagsLocation[i],
						draggable: true,
						icon: new google.maps.MarkerImage("http://sites.google.com/site/mcmarkers/gachapeg-20.png")
				});
				google.maps.event.addListener(centerMarker, "dragend", function (e) {
						//alert(centerMarker.getPosition());
						var newpos = centerMarker.getPosition();
						var ll = is_taiwan(newpos.lat(), newpos.lng());
						if (ll == 2) {
							ph = 1;
							comment = "澎湖 TWD67:";
						} else {
							ph = 0;
							comment = "台灣 TWD67:";
						}
						var p = lonlat2twd67(newpos.lng(), newpos.lat(), ph);
						content = "<br>座標: " + comment + Math.round(p.x) + "," + Math.round(p.y);
						centerInfo.setContent(content);
						centerMarker.setTitle("座標位置");
						centerInfo.open(map,centerMarker);




				});
				google.maps.event.addListener(centerMarker, "dragstart", function (e) {
						if (centerInfo)
							centerInfo.close();
				});

			} else {
				centerMarker.setTitle(availableTags[i]);
				centerMarker.setPosition(availableTagsLocation[i]);
			}
			if (!centerInfo) {
				centerInfo = new google.maps.InfoWindow();
			}
			centerInfo.setPosition(availableTagsLocation[i]);
			var ll = is_taiwan(availableTagsLocation[i].lat(), availableTagsLocation[i].lng());
			if (ll == 2) {
				ph = 1;
				comment = "澎湖 TWD67:";
			} else {
				ph = 0;
				comment = "台灣 TWD67:";
			}
			var p = lonlat2twd67(availableTagsLocation[i].lng(), availableTagsLocation[i].lat(), ph);
			//centerInfo.setContent(comment+Math.round(p.x) + ","+Math.round(p.y));
			$.ajax({
					dataType: 'json',
					url: 'pointdata2.php',
					data: {
						"id": availableTagsMeta[i].id,
						"beta": 1
					},

					success: function(data) {
						content = "<b>" + data[0].name + "</b>";
						content += "<br>座標: " + comment + Math.round(p.x) + "," + Math.round(p.y);
						content += data[0].story;

						centerInfo.setContent(content);
						centerMarker.setMap(map);
						centerInfo.open(map, centerMarker);
						showInsideMarkers();
						if (map.getZoom() < 12 )
							map.setZoom(12);
					}
			});
			google.maps.event.addListener(centerMarker, 'click', function() {
					centerInfo.open(map, centerMarker);
			});
			return true;
		}
	}
	return false;
}
function initialtags(msg) {
	if (tags_ready == 1) return;

	availableTags = [];
	availableTagsLocation = [];
	availableTagsMeta = [];
	$.get('pointdata2.php', {},
		function(data) {
			for (var i = 0; i < data.length; i++) {
				availableTags[i] = data[i].name;
				//console.log(data[i][0]);
				availableTagsLocation[i] = new google.maps.LatLng(data[i].y, data[i].x);
				availableTagsMeta[i] = {
					id: data[i].id,
					//sym: data[i].sym
					type: data[i].type
				};

			}
			$("#tags").autocomplete({
					source: availableTags
			});

			$("#tags").val("");
			tags_ready = 1;
			// 初始完畢, 顯示 lables
			initialmarkers();
			showInsideMarkers();
			if (msg) {
				alert(msg + "共" + availableTagsLocation.length + "筆資料");
			}
		},
		'json');
}

function initialmarkers() {
	// if (tags_ready == 0 ) return;
	//if (markerCluster)
	//	markerCluster.clearMarkers();
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
			markersWontHide: true
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
function initialize() {

	var latlng = new google.maps.LatLng(23.55080863515257, 121.13220691680908);
	var myOptions = {
		zoom: 14,
		maxZoom: 16,
		center: latlng,
		mapTypeControlOptions: {
			//style: google.maps.MapTypeControlStyle.DROPDOWN_MENU,
			//	draggableCursor: 'url(img/A4-32x32.gif),default',
			mapTypeIds: ['twmapv1', google.maps.MapTypeId.ROADMAP, google.maps.MapTypeId.TERRAIN, google.maps.MapTypeId.SATELLITE, google.maps.MapTypeId.HYBRID]
		}

	};
	map = new google.maps.Map(document.getElementById("map_canvas"), myOptions);
	map.mapTypes.set('twmapv1', SunriverMapType);
	map.mapTypes.set('taiwan', TaiwanMapType);
	// 背景層
	BackgroundMapType = TaiwanMapType;
	BackgroundMapOptions = TaiwanMapOptions;
	// 初始顯示哪張圖?
	// map.setMapTypeId(google.maps.MapTypeId.TERRAIN);
	map.setMapTypeId(google.maps.MapTypeId.SATELLITE);
	// 背景哪張圖
	map.overlayMapTypes.insertAt(0, TaiwanMapType);
	changeBackgroundOpacity(0.7);

	// 控制背景圖的透明度
	var bar = document.getElementById("op");
	var container = $("#opSlider");
	//var container = document.getElementById("opSlider");
	//var range = (parseInt(container.style.width) - parseInt(bar.style.width));
	var range = container.width() - $("#op").width();
	map.controls[google.maps.ControlPosition.TOP].push(document.getElementById('opContainer'));
	var opSlider = new ExtDraggableObject(bar, {
			restrictY: true,
			container: container
	});


	//console.log(container.width());
	//console.log($("#op").width());

	opSlider.setValueX(range * opacity);
	showOp(opacity);

	google.maps.event.addListener(opSlider, 'drag', function(evt) {
			var op = opSlider.left() / range;
			if (op >= 1) op = 1;
			if (op <= 0) op = 0;
			changeBackgroundOpacity(op);
			opSlider.setValueX(range * opacity);
			showOp(opacity);
	});
	// https://stackoverflow.com/questions/71990197/how-to-address-adddomlistener-deprecation-in-google-maps
	document.getElementById('less').addEventListener('click', function() {
			var op = opacity - 0.1;
			if (op <= 0) return;
			changeBackgroundOpacity(op);
			opSlider.setValueX(range * opacity);
			showOp(opacity);
	});
	document.getElementById('more').addEventListener('click', function() {
			var op = opacity + 0.1;
			if (op >= 1) return;
			changeBackgroundOpacity(op);
			opSlider.setValueX(range * opacity);
			showOp(opacity);

	});
	// 顯示游標所在座標
	google.maps.event.addListener(map, 'mousemove', function(event) {
			var lon = event.latLng.lng();
			var lat = event.latLng.lat();
			var myloc = "游標座標: " + event.latLng.toUrlValue();
			var ll = is_taiwan(lat, lon);
			//if (lon < 119.31 || lon > 124.56 || lat < 21.88 || lat >25.31  ) {
			if (ll == 0) {
				myloc += "<br>不在台澎範圍";
				// do nothing
				//	} else if ( lon > 119.72 ) {
			} else if (ll == 1) {
				var p = lonlat2twd67(lon, lat);
				myloc += "<br>TWD67 台灣: " + Math.round(p.x) + "," + Math.round(p.y);
			} else { // 澎湖
				var p = lonlat2twd67(lon, lat, 1);
				myloc += "<br>TWD67 澎湖: " + Math.round(p.x) + "," + Math.round(p.y);
			}
			$("#loc").html(myloc);

	});
	// 畫框框
	listener = google.maps.event.addListener(map, 'bound_changed', showInsideMarkers);
	zoomlistener = google.maps.event.addListener(map, 'zoom_changed', showInsideMarkers);

	if (show_kml == 0) {
		$("#hint").html("點左鍵選取範圍");
		google.maps.event.addListener(map, 'click', addremove_polygon);
	} else {
		$("#hint").html("檢視 KML 航點模式");
		$("#generate").hide();
	}
	google.maps.event.addListener(map, 'dragstart', function() {
			google.maps.event.removeListener(listener);
	});
	google.maps.event.addListener(map, 'dragend', function() {
			listener = google.maps.event.addListener(map, 'bounds_changed', showInsideMarkers);
	});

	// 載入 Tags
	$("#tags").val("初始化中");
	tags_ready = 0;
	initialtags("");

	$("#goto").click(function() {
			var tries = 3;
			if (tags_ready == 0) {
				while (tries > 0) {
					initialtags("");
					tries--;
				}
				if (tags_ready == 0) {
					alert("尚未初始化,請重新載入");
					return;
				}
			}
			if (showCenterMarker($("#tags").val()) === false) {

				alert("找不到喔");
			}
	});
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
			} else {
				BackgroundMapType = TaiwanMapType;
				BackgroundMapOptions = TaiwanMapOptions;
				BackgroundMap = 0;
			}
			map.overlayMapTypes.removeAt(0, BackgroundMapType);
			map.overlayMapTypes.insertAt(0, BackgroundMapType);
	});
	$("#inputtitlebtn2").click(function() {
				$.unblockUI();
	});
	$("#inputtitlebtn").click(function() {
			// console.log($("#inputtitle"));
			if ($("#inputtitle").val() != "") {
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
			$.blockUI({ message: $('#inputtitleform') });
	});
	$("#about").click(function() {
			$("#footer").dialog();
	});
	$("#label_sw").click(function() {
			if (show_label == 1) {
				show_label = 0;
				// remove all markers
				for (var i = 0; i < markerArrayMax; i++) {
					markerArray[i].setMap(null);
					labelArray[i].setMap(null);
				}
				// alert(show_label);
			} else {
				show_label = 1;
				showInsideMarkers();
				// alert(show_label);
			}
	});
	$("#marker_sw").click(function() {
			if (show_marker == 1) {
				show_marker = 0;
				//markerCluster.clearMarkers();
				for (var i = 0; i < allmarkers.length; i++)
					allmarkers[i].setMap(null);
				oms.clearMarkers();
				//alert(show_marker);
			} else {
				show_marker = 1;
				initialmarkers();
				//showInsideMarkers();
			}
	});
	if (admin_role == 1 ) 
		$("#marker_reload").show();
	else
		$("#marker_reload").hide();

	$("#marker_reload").click(function() {
			// 清除 label
			if (show_label) {
				for (var i = 0; i < markerArrayMax; i++) {
					markerArray[i].setMap(null);
					labelArray[i].setMap(null);
				}
			}
			// 清除 markers
			if (show_marker) {
				for (i = 0; i < allmarkers.length; i++)
					allmarkers[i].setMap(null);
				oms.clearMarkers();
				allmarkers = [];
			}
			// 重新載入
			centerInfo.close();
			tags_ready = 0;
			initialtags("載入完成");
			show_marker = 1;
			show_label = 1;
			// 重新顯示
	});
	map.controls[google.maps.ControlPosition.TOP_LEFT].push(document.getElementById('locContainer'));

	// 最後把中心點移動到有興趣的位置
	if (show_kml == 1) {
		GPSLayer = new google.maps.KmlLayer(getParameterByName('kml') + '?ts=' + (new Date()).getTime(), {
				preserveViewport: false
		});
		GPSLayer.setMap(map);

		google.maps.event.addListener(GPSLayer, "defaultviewport_changed", function() {
				setTimeout(showInsideMarkers, 2000);
		});
	} else {
		setTimeout(function() {
				showCenterMarker("二子溫泉");
			},
			2000);
	}
} // end of initialize
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
