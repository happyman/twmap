var polygon;

function addremove_polygon(event) {
    var point = event.latLng;
    var cc = is_taiwan(point.lat(), point.lng());
    // 不在台澎範圍
    if (cc == 0) return;
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
        miniX = 9999, miniY = 0, maxiX = 0, maxiY = 9999;
        if (polygon) polygon.setMap(null);
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
    var points = [
        new google.maps.LatLng(tl.y, tl.x),
        new google.maps.LatLng(tr.y, tr.x),
        new google.maps.LatLng(br.y, br.x),
        new google.maps.LatLng(bl.y, bl.x)
    ];
    if (polygon) {
        polygon.setPath(points);
    } else {
        polygon = new google.maps.Polygon({
            path: points,
            strokeColor: "#FFFF00",
            strokeOpacity: 1,
            strokeWeight: 1,
            fillColor: '#FF0000',
            fillOpacity: 0.2
        });
        google.maps.event.addListener(polygon, 'click', addremove_polygon);
    }
    polygon.setMap(map);
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
    $("#params").html("X: " + data.x + " Y:" + data.y + " 東 " + data.shiftx + "km 南 " + data.shifty + "km 共 " + page);
    callmake = "x=" + data.x + "&y=" + data.y + "&shiftx=" + data.shiftx + "&shifty=" + data.shifty + "&ph=" + ph;
    return data;
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

function is_taiwan(lat, lon) {
    if (lon < 119.31 || lon > 124.56 || lat < 21.88 || lat > 25.31) {
        return 0;
    } else if (lon > 119.72) {
        return 1;
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
    if (results == null) return "";
    else return decodeURIComponent(results[1].replace(/\+/g, " "));
}

function in_array(stringToSearch, arrayToSearch) {
    if (arrayToSearch != null) {
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
