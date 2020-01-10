var polygon;
var polygon2; // for twd97

function addremove_polygon(event) {
    var point = event.latLng;
    var cc = is_taiwan(point.lat(), point.lng());
    // 不在台澎範圍
    if (cc === 0) return;
    var ph = (cc == 2) ? 1 : 0;
    var data = lonlat_getblock(point.lng(), point.lat(), ph);
    var minx = data[0].x;
    var miny = data[0].y;
    var maxx = data[1].x;
    var maxy = data[1].y;
    if (point.lng() >= miniX && point.lng() <= maxiX && point.lat() >= maxiY && point.lat() <= miniY) {
        // 相減
        maxiX = minx;
        maxiY = miny;
    } else {
        //alert("update:"+minx+","+miny+":"+maxx+","+maxy);
        if (minx < miniX) miniX = minx;
        if (miny > miniY) miniY = miny;
        if (maxx > maxiX) maxiX = maxx;
        if (maxy < maxiY) maxiY = maxy;
    }
    if ((maxiX - miniX < 0.0088) || (miniY - maxiY < 0.0088)) {
        miniX = 9999; miniY = 0; maxiX = 0; maxiY = 9999;
        if (polygon) polygon.setMap(null);
		if (polygon2) polygon2.setMap(null);
        $("#params").html("尚未選圖");
        callmake = null;
        return;
    }
    //alert(miniX + "," + miniY + " " + maxiX + "," +maxiY );
    data = update_params(ph);
    //alert(miniY + " " + maxiX);
    var tl = twd672lonlat(data.x * 1000, data.y * 1000, ph);
    var br = twd672lonlat((data.x + data.shiftx) * 1000, (data.y - data.shifty) * 1000, ph);
    var tr = twd672lonlat((data.x + data.shiftx) * 1000, data.y * 1000, ph);
    var bl = twd672lonlat(data.x * 1000, (data.y - data.shifty) * 1000, ph);
	// TWD97
	var tl2 = twd972lonlat((data.x+1) * 1000, data.y * 1000, ph);
    var br2 = twd972lonlat(((data.x+1) + data.shiftx) * 1000, (data.y - data.shifty) * 1000, ph);
    var tr2 = twd972lonlat(((data.x+1) + data.shiftx) * 1000, data.y * 1000, ph);
    var bl2 = twd972lonlat((data.x+1) * 1000, (data.y - data.shifty) * 1000, ph);
    var points = [
        new google.maps.LatLng(tl.y, tl.x),
        new google.maps.LatLng(tr.y, tr.x),
        new google.maps.LatLng(br.y, br.x),
        new google.maps.LatLng(bl.y, bl.x)
    ];
	var points2 = [
		new google.maps.LatLng(tl2.y, tl2.x),
        new google.maps.LatLng(tr2.y, tr2.x),
        new google.maps.LatLng(br2.y, br2.x),
        new google.maps.LatLng(bl2.y, bl2.x)
	];
    if (polygon) {
        polygon.setPath(points);
		polygon2.setPath(points2);
    } else {
        polygon = new google.maps.Polygon({
            path: points,
            strokeColor: "#FFFF00",
            strokeOpacity: 1,
            strokeWeight: 1,
            fillColor: '#FF0000',
            fillOpacity: 0.2
        });
		polygon2 = new google.maps.Polygon({
            path: points2,
            strokeColor: "#00FF00",
            strokeOpacity: 1,
            strokeWeight: 1,
            fillColor: '#00FF00',
            fillOpacity: 0.1
        });
        google.maps.event.addListener(polygon, 'click', addremove_polygon);
		google.maps.event.addListener(polygon2, 'click', addremove_polygon);
        google.maps.event.addListener(polygon, 'rightclick', function () {
			export_points(miniX,miniY,maxiX,maxiY);
		});
    }
    polygon.setMap(map);
	polygon2.setMap(map);
    // hide marker
    centerMarker.setVisible(false);
    return true;
}

function update_params(ph) {
    var tl = lonlat2twd67(miniX, miniY, ph);
    var br = lonlat2twd67(maxiX, maxiY, ph);
    var data = {
        x: Math.round(tl.x / 1000),
        y: Math.round(tl.y / 1000),
        shiftx: Math.round((br.x - tl.x) / 1000),
        shifty: Math.round((tl.y - br.y) / 1000)
    };
    var total = Math.ceil(data.shiftx / 5) * Math.ceil(data.shifty / 7);
    var total1 = Math.ceil(data.shiftx / 7) * Math.ceil(data.shifty / 5);
    var page = "";
    if (total1 < total) {
        page = total1 + " 張 A4R";
    } else {
        page = total + " 張 A4";
    }
	var ver;
	if ($("#changemap").val() == 'moi_osm') ver = 2016;
	else if ($("#changemap").val() == 'tw25k_v1') ver = 1;
	else ver = 3;
	
    $("#params").html("TWD67:" + data.x + ":" + data.y + " " + data.shiftx + "x" + data.shifty + " km 共 " + page + 
                '<button type="button" id="generate" name="generate" title="將參數傳送到地圖產生器" class="ui-state-default ui-corner-all" >產生</button>');
    callmake = "x=" + data.x + "&y=" + data.y + "&shiftx=" + data.shiftx + "&shifty=" + data.shifty + "&ph=" + ph + "&version=" + ver;
	$("#generate").click(generate_btn_click);
    return data;
}
function generate_btn_click() {
        if (callmake === null) {
            alert("請選擇範圍");
            return;
        }
        // 置中
        if (centerInfo) centerInfo.close();
        map.setCenter(new google.maps.LatLng(miniY + (maxiY - miniY) / 2, miniX + (maxiX - miniX) / 2));
        ismakingmap = 1;
        $.blockUI({
            message: $('#inputtitleform')
        });
}


// proj function
Proj4js.defs["EPSG:3828"] = "+title=二度分帶：TWD67 TM2 台灣 +proj=tmerc  +towgs84=-752,-358,-179,-.0000011698,.0000018398,.0000009822,.00002329 +lat_0=0 +lon_0=121 +x_0=250000 +y_0=0 +k=0.9999 +ellps=aust_SA  +units=公尺";
Proj4js.defs["EPSG:3827"] = "+title=二度分帶：TWD67 TM2 澎湖 +proj=tmerc  +towgs84=-752,-358,-179,-.0000011698,.0000018398,.0000009822,.00002329 +lat_0=0 +lon_0=119 +x_0=250000 +y_0=0 +k=0.9999 +ellps=aust_SA  +units=公尺";
Proj4js.defs["EPSG:3826"] = "+title=二度分帶：TWD97 TM2 台灣 +proj=tmerc  +lat_0=0 +lon_0=121 +k=0.9999 +x_0=250000 +y_0=0 +ellps=GRS80 +units=公尺 +no_defs";
Proj4js.defs["EPSG:3825"] = "+title=二度分帶：TWD97 TM2 澎湖 +proj=tmerc  +lat_0=0 +lon_0=119 +k=0.9999 +x_0=250000 +y_0=0 +ellps=GRS80 +units=公尺 +no_defs";
var EPSG3828 = new Proj4js.Proj('EPSG:3828');
var EPSG3827 = new Proj4js.Proj('EPSG:3827');
var EPSG3826 = new Proj4js.Proj('EPSG:3826');
var EPSG3825 = new Proj4js.Proj('EPSG:3825');
var WGS84 = new Proj4js.Proj('WGS84');

function lonlat2twd67(x, y, ph) {
    var p = new Proj4js.Point(parseFloat(x), parseFloat(y));
    if (ph == 1) Proj4js.transform(WGS84, EPSG3827, p);
    else Proj4js.transform(WGS84, EPSG3828, p);
    var result = {
        x: p.x,
        y: p.y
    };
    return result;
}

function twd672lonlat(x, y, ph) {
    var p = new Proj4js.Point(parseFloat(x), parseFloat(y));
    if (ph == 1) Proj4js.transform(EPSG3827, WGS84, p);
    else Proj4js.transform(EPSG3828, WGS84, p);
    var result = {
        x: p.x,
        y: p.y
    };
    return result;
}

function twd972lonlat(x, y, ph) {
    var p = new Proj4js.Point(parseFloat(x), parseFloat(y));
    if (ph == 1) Proj4js.transform(EPSG3825, WGS84, p);
    else Proj4js.transform(EPSG3826, WGS84, p);
    var result = {
        x: p.x,
        y: p.y
    };
    return result;
}

function lonlat2twd97(x, y, ph) {
    var p = new Proj4js.Point(parseFloat(x), parseFloat(y));
    if (ph == 1) Proj4js.transform(WGS84, EPSG3825, p);
    else Proj4js.transform(WGS84, EPSG3826, p);
    var result = {
        x: p.x,
        y: p.y
    };
    return result;
}
/*
https://wiki.osgeo.org/wiki/Taiwan_datums/cad2twd67
*/
function cad2twd67(Xcad,Ycad, unit) {
    unit = typeof unit !== 'undefined' ? unit : 'm';
    if (unit == 'm') {
		Xcad *= 0.55;
		Ycad *= 0.55;
    }
	var XCtm69 = 227361.634 + 0.0;
	var YCtm69 = 2632574.582 + 0.0;
	var XCcad = 5750;
	var YCcad = -21300;
	var A     = 1.8182516286522;
	var B     = -0.004167109289753;
    var Xtmtrn = A * ( Xcad - XCcad ) - B * ( Ycad - YCcad ) + XCtm69;
    var Ytmtrn = B * ( Xcad - XCcad ) + A * ( Ycad - YCcad ) + YCtm69;
    return [ Xtmtrn, Ytmtrn ];


}
/* happyman */
function twd672cad(x,y, unit){
	var XCtm69 = 227361.634 + 0.0;
	var YCtm69 = 2632574.582 + 0.0;
	var XCcad = 5750;
	var YCcad = -21300;
	var A     = 1.8182516286522;
	var B     = -0.004167109289753;
	var Xcad =(B*y-B*YCtm69+B*B*XCcad+A*x-A*XCtm69+A*A*XCcad)/(A*A+B*B);
	var Ycad =(A*y-A*YCtm69+A*A*YCcad+B*B*YCcad-B*x+B*XCtm69)/(B*B+A*A);
    unit = typeof unit !== 'undefined' ? unit : 'm';
    if (unit == 'm') {
	Xcad /= 0.55;
	Ycad /= 0.55;
    }
	return { x:Xcad, y:Ycad };
}
// wrap 一下
function lonlat2cad(x,y,unit){
	var p = lonlat2twd67(x,y,0);
	return twd672cad(p.x,p.y,unit);
}
function lonlat_getblock(lon, lat, ph, unit) {
    unit = typeof unit !== 'undefined' ? unit : 1000;
    var p = lonlat2twd67(lon, lat, ph);
    var tl = {
        x: Math.floor(p.x / unit) * unit,
        y: Math.ceil(p.y / unit) * unit
    };
    var br = {
        x: Math.ceil(p.x / unit) * unit,
        y: Math.floor(p.y / unit) * unit
    };
    var p1 = twd672lonlat(tl.x, tl.y, ph);
    var p2 = twd672lonlat(br.x, br.y, ph);
    return [p1, p2, tl, br];
}
function lonlat_getblock97(lon, lat, ph, unit) {
    unit = typeof unit !== 'undefined' ? unit : 1000;
    var p = lonlat2twd97(lon, lat, ph);
    var tl = {
        x: Math.floor(p.x / unit) * unit,
        y: Math.ceil(p.y / unit) * unit
    };
    var br = {
        x: Math.ceil(p.x / unit) * unit,
        y: Math.floor(p.y / unit) * unit
    };
    var p1 = twd972lonlat(tl.x, tl.y, ph);
    var p2 = twd972lonlat(br.x, br.y, ph);
    return [p1, p2, tl, br];
}


function is_taiwan(lat, lon) {
    if (lon > 118.1 && lon < 118.52 && lat < 24.55 && lat > 24.35)
	return 3; // 金門
    if (lon > 119.5 && lon < 120.55 && lat < 26.4 && lat > 25.9 )
	return 4; // 馬祖
    if (lon < 119.31 || lon > 124.56 || lat < 21.88 || lat > 25.31) {
       	return 0; // 其他
    } else if (lon > 119.72) {
        return 1; // 台灣
    } else
    // 澎湖
        return 2;
}
/**
 * getParameterByName 
 * http://stackoverflow.com/questions/901115/get-query-string-values-in-javascript/901144
 * @param name $name 
 * @access public
 * @return void
 */
function getParameterByName(name) {
    name = name.replace(/[\[]/, "\\\[").replace(/[\]]/, "\\\]");
    var regexS = "[\\?&]" + name + "=([^&#]*)";
    var regex = new RegExp(regexS);
    var results = regex.exec(window.location.href);
    if (results === null) return undefined;
    else return decodeURIComponent(results[1].replace(/\+/g, " "));
}

function in_array(stringToSearch, arrayToSearch) {
    if (arrayToSearch !== null) {
        for (s = 0; s < arrayToSearch.length; s++) {
            thisEntry = arrayToSearch[s].toString();
            if (thisEntry == stringToSearch) {
                return true;
            }
        }
    } else {
        return false;
    }
    return false;
}
var is_mobile = false;
(function(a, b) {
    if (/android.+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino/i.test(a) || /1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|e\-|e\/|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(di|rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|xda(\-|2|g)|yas\-|your|zeto|zte\-/i.test(a.substr(0, 4))) is_mobile = true;
})(navigator.userAgent || navigator.vendor || window.opera, 'http://detectmobilebrowser.com/mobile');

function ConvertDDToDMS(D) {
    return [0 | D, 'd', 0 | (D < 0 ? D = -D : D) % 1 * 60, "'", 0 | D * 60 % 1 * 60, '"'].join('');
}
/*
https://github.com/happyman/twmap/issues/12

*/
//var cover_overlay;
var cover_overlays = Array();

function coverage_overlay(op) {

	console.log("coverage_overlay(" + op + ")");
	// https://coverage.cht.com.tw/coverage/jss/mobile/mEmbr.json
	/* 2018 update
	"ulat":26.85073525,			  
"ulon":126.130429314477, 
"llat":21.415399218816,
"llon":114.048568045892
*/
	var coverage = {
//"cht":{"bound":{"north":25.554136,"south":21.635736302326,"east":124.43445758443,"west":115.76012294636},"img":"http:\/\/221.120.19.26\/coverage\/images\/mobile\/4g_tw.png"},
//"cht":{"bound":{"north":26.6877899375,"south":21.593289442125,"west":114.507922814371,"east":125.832808493114},"img":"https:\/\/coverage.cht.com.tw\/coverage\/images\/mobile\/4g_tw.png"},
"twn":[{"bound":{"north":25.342129534416,"south":21.85034169135,"west":119.26714358482,"east":122.1162269718},"img":"https:\/\/www.taiwanmobile.com\/mobile\/calculate\/maps\/4G\/TW.png?r=2016081"}],
"fet":[{"bound":{"north":27.109534289724,"south":19.921522519575,"west":114.52355246805,"east":127.14196021948},"img":"http:\/\/www.fetnet.net\/service\/roadtestresult\/signal\/img\/coverage4G.png"}], 
//"cht2G":{"bound":{"north":27.015288659839,"south":21.614440279186,"east":122.470147573768,"west":117.924277900237},"img":"http:\/\/221.120.19.26\/coverage\/images\/mobile\/2g_tw.png"},
//"twn2G":{"bound":{"north":25.444259664518,"south":21.764367841649,"west":119.146827678062,"east":122.253772190656},"img":"https:\/\/www.taiwanmobile.com\/mobile\/calculate\/maps\/2G\/TW.png"},
//"fet2G":{"bound":{"north":26.322813780907,"south":20.912682441736,"west":116.322882125754,"east":124.708821581148},"img":"http:\/\/www.fetnet.net\/service\/roadtestresult\/signal\/img\/coverageVoice.png"},
//"cht3G":{"bound":{"north":26.7914806875,"south":21.514686195825,"east":125.955588822744,"west":114.224310037633},"img":"http:\/\/221.120.19.26\/coverage\/images\/mobile\/3g_tw.png"},
//"cht3G":{"bound":{"north":26.85073525,"south":21.415399218816,"west":114.048568045892,"east":126.130429314477},"img":"https:\/\/coverage.cht.com.tw\/coverage\/images\/mobile\/3g_tw.png"},
"twn3G":[{"bound":{"north":25.342129534416,"south":21.85034169135,"west":119.267143584823,"east":122.116226971802},"img":"https:\/\/www.taiwanmobile.com\/mobile\/calculate\/maps\/3G\/TW.png"}],
"fet3G":[{"bound":{"north":26.515893187443,"south":21.813526812557,"west":117.496893247948,"east":122.704393752052},"img":"http:\/\/www.fetnet.net\/service\/roadtestresult\/signal\/img\/coverage3.5G.png"}],
// 201905 cht 改成多個 coverage overlays, 所以修改成多個 overlay
// https://coverage.cht.com.tw/coverage/jss/mobile/mobileMap.js
// https://coverage.cht.com.tw/coverage/jss/mobile/mEmbr.json
// 以上先 pre-process 成下面的格式
"cht":[{"bound":{"north":25.43018,"south":24.842169,"east":122.13962,"west":120.842934},"img":"https:\/\/coverage.cht.com.tw\/coverage\/images\/mobile\/4g_tw_n1.png"},{"bound":{"north":25.029276,"south":24.343359,"east":122.051264,"west":120.538564},"img":"https:\/\/coverage.cht.com.tw\/coverage\/images\/mobile\/4g_tw_n2.png"},{"bound":{"north":24.677594,"south":23.795453,"east":122.071623,"west":120.126456},"img":"https:\/\/coverage.cht.com.tw\/coverage\/images\/mobile\/4g_tw_n3.png"},{"bound":{"north":24.228047,"south":23.34593,"east":121.875136,"west":119.929961},"img":"https:\/\/coverage.cht.com.tw\/coverage\/images\/mobile\/4g_tw_c1.png"},{"bound":{"north":23.778346,"south":22.896225,"east":121.777428,"west":119.832241},"img":"https:\/\/coverage.cht.com.tw\/coverage\/images\/mobile\/4g_tw_c2.png"},{"bound":{"north":23.349102,"south":22.515958,"east":121.674017,"west":119.836867},"img":"https:\/\/coverage.cht.com.tw\/coverage\/images\/mobile\/4g_tw_s1.png"},{"bound":{"north":22.919832,"south":22.135679,"east":121.669127,"west":119.940022},"img":"https:\/\/coverage.cht.com.tw\/coverage\/images\/mobile\/4g_tw_s2.png"},{"bound":{"north":22.372363,"south":21.784372,"east":121.747311,"west":120.450631},"img":"https:\/\/coverage.cht.com.tw\/coverage\/images\/mobile\/4g_tw_s3.png"},{"bound":{"north":26.649731,"south":25.675754,"east":120.539653,"west":119.875697},"img":"https:\/\/coverage.cht.com.tw\/coverage\/images\/mobile\/4g_li.png"},{"bound":{"north":23.829135,"south":23.155507,"east":119.750269,"west":119.291055},"img":"https:\/\/coverage.cht.com.tw\/coverage\/images\/mobile\/4g_ph.png"},{"bound":{"north":25.765986,"south":23.609424,"east":119.542326,"west":118.071174},"img":"https:\/\/coverage.cht.com.tw\/coverage\/images\/mobile\/4g_km.png"}],
"cht3G":[{"bound":{"north":25.43018,"south":24.842178,"east":122.139592,"west":120.842909},"img":"https:\/\/coverage.cht.com.tw\/coverage\/images\/mobile\/3g_tw_n1.png"},{"bound":{"north":25.029284,"south":24.343359,"east":122.051273,"west":120.538555},"img":"https:\/\/coverage.cht.com.tw\/coverage\/images\/mobile\/3g_tw_n2.png"},{"bound":{"north":24.677597,"south":23.795474,"east":122.071777,"west":120.126585},"img":"https:\/\/coverage.cht.com.tw\/coverage\/images\/mobile\/3g_tw_n3.png"},{"bound":{"north":24.227892,"south":23.345766,"east":121.875341,"west":119.930157},"img":"https:\/\/coverage.cht.com.tw\/coverage\/images\/mobile\/3g_tw_c1.png"},{"bound":{"north":23.778187,"south":22.896059,"east":121.777333,"west":119.832157},"img":"https:\/\/coverage.cht.com.tw\/coverage\/images\/mobile\/3g_tw_c2.png"},{"bound":{"north":23.349203,"south":22.516062,"east":121.674229,"west":119.837071},"img":"https:\/\/coverage.cht.com.tw\/coverage\/images\/mobile\/3g_tw_s1.png"},{"bound":{"north":22.919834,"south":22.135668,"east":121.669168,"west":119.940435},"img":"https:\/\/coverage.cht.com.tw\/coverage\/images\/mobile\/3g_tw_s2.png"},{"bound":{"north":22.372341,"south":21.784289,"east":121.747479,"west":120.450794},"img":"https:\/\/coverage.cht.com.tw\/coverage\/images\/mobile\/3g_tw_s3.png"},{"bound":{"north":26.638896352988,"south":25.685195668379,"east":120.54003645266,"west":119.87593395},"img":"https:\/\/coverage.cht.com.tw\/coverage\/images\/mobile\/3g_li.png"},{"bound":{"north":23.82888325,"south":23.155240570044,"east":119.750629983516,"west":119.291551544694},"img":"https:\/\/coverage.cht.com.tw\/coverage\/images\/mobile\/3g_ph.png"},{"bound":{"north":25.755553664373,"south":23.617318030723,"east":119.543362995567,"west":118.07252925},"img":"https:\/\/coverage.cht.com.tw\/coverage\/images\/mobile\/3g_km.png"}]

};

for (var i=0; i<cover_overlays.length; i++){
	if (typeof cover_overlays[i] == "object") {
			 cover_overlays[i].setMap(null);
	}
}
if (op != 'none' && typeof coverage[op] != 'undefined') {
	for(i=0;i<coverage[op].length;i++) {
		cover_overlays[i] = new google.maps.GroundOverlay(coverage[op][i].img, coverage[op][i].bound, {      opacity:0.7  } );
		cover_overlays[i].setMap(map);
	}
}
/*
	if (typeof cover_overlay == "object") {
		cover_overlay.setMap(null);
		console.log('cover_overlay set null');
	}
	if (op != 'none' && typeof coverage[op] != 'undefined') {
		cover_overlay = new google.maps.GroundOverlay(coverage[op].img, coverage[op].bound, {      opacity:0.7  } );
		cover_overlay.setMap(map);
	}
	*/
}

	
function export_points(xmin,ymin,xmax,ymax){
	var url = exportkml_url + "?bound=" + xmin + "," + ymin + "," + xmax + "," + ymax;
	showmeerkat(url ,{ 'width': '600'} );
}



/* 
    Document   : wms.js
    Created on : Feb 16, 2011, 3:25:27 PM
    Author     : "Gavin Jackson <Gavin.Jackson@csiro.au>"
    Refactored code from http://lyceum.massgis.state.ma.us/wiki/doku.php?id=googlemapsv3:home
*/
function bound(value, opt_min, opt_max) {
    if (opt_min !== null) value = Math.max(value, opt_min);
    if (opt_max !== null) value = Math.min(value, opt_max);
    return value;
}
function degreesToRadians(deg) {
    return deg * (Math.PI / 180);
}
function radiansToDegrees(rad) {
    return rad / (Math.PI / 180);
}
function MercatorProjection() {
    var MERCATOR_RANGE = 256;
    this.pixelOrigin_ = new google.maps.Point(
        MERCATOR_RANGE / 2, MERCATOR_RANGE / 2);
    this.pixelsPerLonDegree_ = MERCATOR_RANGE / 360;
    this.pixelsPerLonRadian_ = MERCATOR_RANGE / (2 * Math.PI);
}
MercatorProjection.prototype.fromLatLngToPoint = function(latLng, opt_point) {
    var me = this;
    var point = opt_point || new google.maps.Point(0, 0);
    var origin = me.pixelOrigin_;
    point.x = origin.x + latLng.lng() * me.pixelsPerLonDegree_;
    // NOTE(appleton): Truncating to 0.9999 effectively limits latitude to
    // 89.189.  This is about a third of a tile past the edge of the world tile.
    var siny = bound(Math.sin(degreesToRadians(latLng.lat())), -0.9999, 0.9999);
    point.y = origin.y + 0.5 * Math.log((1 + siny) / (1 - siny)) * -me.pixelsPerLonRadian_;
    return point;
};
MercatorProjection.prototype.fromDivPixelToLatLng = function(pixel, zoom) {
    var me = this;
    var origin = me.pixelOrigin_;
    var scale = Math.pow(2, zoom);
    var lng = (pixel.x / scale - origin.x) / me.pixelsPerLonDegree_;
    var latRadians = (pixel.y / scale - origin.y) / -me.pixelsPerLonRadian_;
    var lat = radiansToDegrees(2 * Math.atan(Math.exp(latRadians)) - Math.PI / 2);
    return new google.maps.LatLng(lat, lng);
};
MercatorProjection.prototype.fromDivPixelToSphericalMercator = function(pixel, zoom) {
    var me = this;
    var coord = me.fromDivPixelToLatLng(pixel, zoom);
    var r= 6378137.0;
    var x = r* degreesToRadians(coord.lng());
    var latRad = degreesToRadians(coord.lat());
    var y = (r/2) * Math.log((1+Math.sin(latRad))/ (1-Math.sin(latRad)));
    return new google.maps.Point(x,y);
};


function sprintf () {
  //  discuss at: http://locutus.io/php/sprintf/
  // original by: Ash Searle (http://hexmen.com/blog/)
  // improved by: Michael White (http://getsprink.com)
  // improved by: Jack
  // improved by: Kevin van Zonneveld (http://kvz.io)
  // improved by: Kevin van Zonneveld (http://kvz.io)
  // improved by: Kevin van Zonneveld (http://kvz.io)
  // improved by: Dj
  // improved by: Allidylls
  //    input by: Paulo Freitas
  //    input by: Brett Zamir (http://brett-zamir.me)
  // improved by: Rafał Kukawski (http://kukawski.pl)
  //   example 1: sprintf("%01.2f", 123.1)
  //   returns 1: '123.10'
  //   example 2: sprintf("[%10s]", 'monkey')
  //   returns 2: '[    monkey]'
  //   example 3: sprintf("[%'#10s]", 'monkey')
  //   returns 3: '[####monkey]'
  //   example 4: sprintf("%d", 123456789012345)
  //   returns 4: '123456789012345'
  //   example 5: sprintf('%-03s', 'E')
  //   returns 5: 'E00'
  //   example 6: sprintf('%+010d', 9)
  //   returns 6: '+000000009'
  //   example 7: sprintf('%+0\'@10d', 9)
  //   returns 7: '@@@@@@@@+9'
  //   example 8: sprintf('%.f', 3.14)
  //   returns 8: '3.140000'
  //   example 9: sprintf('%% %2$d', 1, 2)
  //   returns 9: '% 2'

  var regex = /%%|%(?:(\d+)\$)?((?:[-+#0 ]|'[\s\S])*)(\d+)?(?:\.(\d*))?([\s\S])/g;
  var args = arguments;
  var i = 0;
  var format = args[i++];

  var _pad = function (str, len, chr, leftJustify) {
    if (!chr) {
      chr = ' ';
    }
    var padding = (str.length >= len) ? '' : new Array(1 + len - str.length >>> 0).join(chr);
    return leftJustify ? str + padding : padding + str;
  };

  var justify = function (value, prefix, leftJustify, minWidth, padChar) {
    var diff = minWidth - value.length;
    if (diff > 0) {
      // when padding with zeros
      // on the left side
      // keep sign (+ or -) in front
      if (!leftJustify && padChar === '0') {
        value = [
          value.slice(0, prefix.length),
          _pad('', diff, '0', true),
          value.slice(prefix.length)
        ].join('');
      } else {
        value = _pad(value, minWidth, padChar, leftJustify);
      }
    }
    return value;
  };

  var _formatBaseX = function (value, base, leftJustify, minWidth, precision, padChar) {
    // Note: casts negative numbers to positive ones
    var number = value >>> 0;
    value = _pad(number.toString(base), precision || 0, '0', false);
    return justify(value, '', leftJustify, minWidth, padChar);
  };

  // _formatString()
  var _formatString = function (value, leftJustify, minWidth, precision, customPadChar) {
    if (precision !== null && precision !== undefined) {
      value = value.slice(0, precision);
    }
    return justify(value, '', leftJustify, minWidth, customPadChar);
  };

  // doFormat()
  var doFormat = function (substring, argIndex, modifiers, minWidth, precision, specifier) {
    var number, prefix, method, textTransform, value;

    if (substring === '%%') {
      return '%';
    }

    // parse modifiers
    var padChar = ' '; // pad with spaces by default
    var leftJustify = false;
    var positiveNumberPrefix = '';
    var j, l;

    for (j = 0, l = modifiers.length; j < l; j++) {
      switch (modifiers.charAt(j)) {
        case ' ':
        case '0':
          padChar = modifiers.charAt(j);
          break;
        case '+':
          positiveNumberPrefix = '+';
          break;
        case '-':
          leftJustify = true;
          break;
        case "'":
          if (j + 1 < l) {
            padChar = modifiers.charAt(j + 1);
            j++;
          }
          break;
      }
    }

    if (!minWidth) {
      minWidth = 0;
    } else {
      minWidth = +minWidth;
    }

    if (!isFinite(minWidth)) {
      throw new Error('Width must be finite');
    }

    if (!precision) {
      precision = (specifier === 'd') ? 0 : 'fFeE'.indexOf(specifier) > -1 ? 6 : undefined;
    } else {
      precision = +precision;
    }

    if (argIndex && +argIndex === 0) {
      throw new Error('Argument number must be greater than zero');
    }

    if (argIndex && +argIndex >= args.length) {
      throw new Error('Too few arguments');
    }

    value = argIndex ? args[+argIndex] : args[i++];

    switch (specifier) {
      case '%':
        return '%';
      case 's':
        return _formatString(value + '', leftJustify, minWidth, precision, padChar);
      case 'c':
        return _formatString(String.fromCharCode(+value), leftJustify, minWidth, precision, padChar);
      case 'b':
        return _formatBaseX(value, 2, leftJustify, minWidth, precision, padChar);
      case 'o':
        return _formatBaseX(value, 8, leftJustify, minWidth, precision, padChar);
      case 'x':
        return _formatBaseX(value, 16, leftJustify, minWidth, precision, padChar);
      case 'X':
        return _formatBaseX(value, 16, leftJustify, minWidth, precision, padChar).toUpperCase();
      case 'u':
        return _formatBaseX(value, 10, leftJustify, minWidth, precision, padChar);
      case 'i':
      case 'd':
        number = +value || 0;
        // Plain Math.round doesn't just truncate
        number = Math.round(number - number % 1);
        prefix = number < 0 ? '-' : positiveNumberPrefix;
        value = prefix + _pad(String(Math.abs(number)), precision, '0', false);

        if (leftJustify && padChar === '0') {
          // can't right-pad 0s on integers
          padChar = ' ';
        }
        return justify(value, prefix, leftJustify, minWidth, padChar);
      case 'e':
      case 'E':
      case 'f': // @todo: Should handle locales (as per setlocale)
      case 'F':
      case 'g':
      case 'G':
        number = +value;
        prefix = number < 0 ? '-' : positiveNumberPrefix;
        method = ['toExponential', 'toFixed', 'toPrecision']['efg'.indexOf(specifier.toLowerCase())];
        textTransform = ['toString', 'toUpperCase']['eEfFgG'.indexOf(specifier) % 2];
        value = prefix + Math.abs(number)[method](precision);
        return justify(value, prefix, leftJustify, minWidth, padChar)[textTransform]();
      default:
        // unknown specifier, consume that char and return empty
        return '';
    }
  };

  try {
    return format.replace(regex, doFormat);
  } catch (err) {
    return false;
  }
}
