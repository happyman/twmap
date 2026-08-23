/*
    Copyright 2026 Happyman

    Based on js/shapedraw.js (Copyright 2013 Mac Craven), replacing the
    deprecated google.maps.drawing DrawingManager with Terra Draw
    (https://github.com/JamesLMilner/terra-draw).

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

var SHAPEDRAW_TYPE_RECTANGLE = "rectangle";
var SHAPEDRAW_TYPE_CIRCLE = "circle";
var SHAPEDRAW_TYPE_POLYGON = "polygon";
var SHAPEDRAW_TYPE_POLYLINE = "polyline";

var SHAPEDRAW_MODE_SELECT = "select";
var SHAPEDRAW_MODE_RECTANGLE = "rectangle";
var SHAPEDRAW_MODE_CIRCLE = "circle";
var SHAPEDRAW_MODE_POLYGON = "polygon";
var SHAPEDRAW_MODE_LINESTRING = "linestring";

var SHAPEDRAW_MODE_TO_TYPE = {};
SHAPEDRAW_MODE_TO_TYPE[SHAPEDRAW_MODE_RECTANGLE] = SHAPEDRAW_TYPE_RECTANGLE;
SHAPEDRAW_MODE_TO_TYPE[SHAPEDRAW_MODE_CIRCLE] = SHAPEDRAW_TYPE_CIRCLE;
SHAPEDRAW_MODE_TO_TYPE[SHAPEDRAW_MODE_POLYGON] = SHAPEDRAW_TYPE_POLYGON;
SHAPEDRAW_MODE_TO_TYPE[SHAPEDRAW_MODE_LINESTRING] = SHAPEDRAW_TYPE_POLYLINE;

var SHAPEDRAW_DEFAULT_COLOR = "#646464";
var SHAPEDRAW_EARTH_RADIUS_M = 6378137;
var SHAPEDRAW_CIRCLE_SEGMENTS = 64;

function shapedrawToNum(v) {
    if (typeof v === "number") {
        return isNaN(v) ? null : v;
    }
    if (typeof v !== "string") {
        return null;
    }
    var n = parseFloat(v);
    return isNaN(n) ? null : n;
}

function shapedrawHaversineM(lat1, lng1, lat2, lng2) {
    var deg2rad = Math.PI / 180;
    var phi1 = lat1 * deg2rad;
    var phi2 = lat2 * deg2rad;
    var dphi = (lat2 - lat1) * deg2rad;
    var dlmb = (lng2 - lng1) * deg2rad;
    var a =
        Math.sin(dphi / 2) * Math.sin(dphi / 2) +
        Math.cos(phi1) * Math.cos(phi2) * Math.sin(dlmb / 2) * Math.sin(dlmb / 2);
    return SHAPEDRAW_EARTH_RADIUS_M * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}

function shapedrawGeodesicRing(centerLng, centerLat, radiusMeters, segments) {
    var deg2rad = Math.PI / 180;
    var rad2deg = 180 / Math.PI;
    var angular = radiusMeters / SHAPEDRAW_EARTH_RADIUS_M;
    var lat1 = centerLat * deg2rad;
    var lng1 = centerLng * deg2rad;
    var sinLat1 = Math.sin(lat1);
    var cosLat1 = Math.cos(lat1);
    var cosAngular = Math.cos(angular);
    var sinAngular = Math.sin(angular);
    var coords = [];
    for (var i = 0; i < segments; i++) {
        var bearing = ((360 * i) / segments) * deg2rad;
        var lat2 = Math.asin(
            sinLat1 * cosAngular + cosLat1 * sinAngular * Math.cos(bearing)
        );
        var lng2 = lng1 + Math.atan2(
            Math.sin(bearing) * sinAngular * cosLat1,
            cosAngular - sinLat1 * Math.sin(lat2)
        );
        coords.push([lng2 * rad2deg, lat2 * rad2deg]);
    }
    return coords;
}

function shapedrawOpenRing(ring) {
    var r = ring.slice();
    if (r.length > 1) {
        var a = r[0];
        var b = r[r.length - 1];
        if (a[0] === b[0] && a[1] === b[1]) {
            r.pop();
        }
    }
    return r;
}

function shapedrawCloseRing(coords) {
    var a = coords[0];
    var b = coords[coords.length - 1];
    if (a[0] === b[0] && a[1] === b[1]) {
        return coords.slice();
    }
    return coords.concat([coords[0]]);
}

function shapedrawMidLng(a, b) {
    var d = b - a;
    if (d > 180) {
        d -= 360;
    }
    if (d < -180) {
        d += 360;
    }
    return a + d / 2;
}

function shapedrawRingCentroidRadius(ring) {
    var pts = shapedrawOpenRing(ring);
    var n = pts.length;
    if (n < 3) {
        return null;
    }
    var deg2rad = Math.PI / 180;
    var rad2deg = 180 / Math.PI;
    var h = Math.floor(n / 2);
    var sx = 0;
    var sy = 0;
    var sz = 0;
    var used = 0;
    function accum(idx) {
        var phi = pts[idx][1] * deg2rad;
        var lam = pts[idx][0] * deg2rad;
        var cosPhi = Math.cos(phi);
        sx += cosPhi * Math.cos(lam);
        sy += cosPhi * Math.sin(lam);
        sz += Math.sin(phi);
        used++;
    }
    if (h > 0) {
        for (var i = 0; i < h; i++) {
            accum(i);
            accum(i + h);
        }
    } else {
        accum(0);
    }
    if (!used) {
        return null;
    }
    var clat = Math.atan2(sz, Math.sqrt(sx * sx + sy * sy)) * rad2deg;
    var clng = Math.atan2(sy, sx) * rad2deg;
    var sumR = 0;
    for (var j = 0; j < n; j++) {
        sumR += shapedrawHaversineM(clat, clng, pts[j][1], pts[j][0]);
    }
    return { lat: clat, lng: clng, radiusM: sumR / n };
}

function shapedrawBoundsOfCoords(pts) {
    if (!pts || !pts.length) {
        return null;
    }
    var n = -Infinity;
    var e = -Infinity;
    var s = Infinity;
    var w = Infinity;
    for (var i = 0; i < pts.length; i++) {
        var lng = pts[i][0];
        var lat = pts[i][1];
        if (lat > n) { n = lat; }
        if (lat < s) { s = lat; }
        if (lng > e) { e = lng; }
        if (lng < w) { w = lng; }
    }
    return { north: n, east: e, south: s, west: w };
}

function shapedrawPathToCoords(path) {
    if (!path) {
        return null;
    }
    var coords = [];
    for (var i = 0; i < path.length; i++) {
        var ll = path[i];
        var lat = shapedrawToNum(ll.lat);
        var lon = shapedrawToNum(ll.lon);
        if (lat === null || lon === null) {
            return null;
        }
        coords.push([lon, lat]);
    }
    return coords.length ? coords : null;
}

function shapedrawCoordToLatlon(c) {
    return { "lat": String(c[1]), "lon": String(c[0]) };
}

function shapedrawMakeFeature(mode, geomType, coordinates, color, extraProps) {
    var props = {
        "mode": mode,
        "color": color || SHAPEDRAW_DEFAULT_COLOR
    };
    if (extraProps) {
        for (var k in extraProps) {
            if (extraProps.hasOwnProperty(k)) {
                props[k] = extraProps[k];
            }
        }
    }
    return {
        type: "Feature",
        geometry: { type: geomType, coordinates: coordinates },
        properties: props
    };
}

function shapedrawJsonRectangleToFeature(jr) {
    if (!jr.bounds || !jr.bounds.northEast || !jr.bounds.southWest) {
        return null;
    }
    var n = shapedrawToNum(jr.bounds.northEast.lat);
    var e = shapedrawToNum(jr.bounds.northEast.lon);
    var s = shapedrawToNum(jr.bounds.southWest.lat);
    var w = shapedrawToNum(jr.bounds.southWest.lon);
    if (n === null || e === null || s === null || w === null) {
        return null;
    }
    var ring = [[w, s], [e, s], [e, n], [w, n], [w, s]];
    return shapedrawMakeFeature(SHAPEDRAW_MODE_RECTANGLE, "Polygon", [ring], jr.color);
}

function shapedrawJsonCircleToFeature(jc) {
    if (!jc.center) {
        return null;
    }
    var lat = shapedrawToNum(jc.center.lat);
    var lon = shapedrawToNum(jc.center.lon);
    var radiusM = shapedrawToNum(jc.radius);
    if (lat === null || lon === null || radiusM === null || radiusM <= 0) {
        return null;
    }
    var ring = shapedrawGeodesicRing(lon, lat, radiusM, SHAPEDRAW_CIRCLE_SEGMENTS);
    ring.push(ring[0]);
    return shapedrawMakeFeature(SHAPEDRAW_MODE_CIRCLE, "Polygon", [ring], jc.color, {
        radiusKilometers: radiusM / 1000
    });
}

function shapedrawJsonPolylineToFeature(jp) {
    var coords = shapedrawPathToCoords(jp.path);
    if (!coords || coords.length < 2) {
        return null;
    }
    return shapedrawMakeFeature(SHAPEDRAW_MODE_LINESTRING, "LineString", coords, jp.color);
}

function shapedrawJsonPolygonToFeature(jp) {
    if (!jp.paths || !jp.paths.length || !jp.paths[0]) {
        return null;
    }
    var coords = shapedrawPathToCoords(jp.paths[0].path);
    if (!coords || coords.length < 3) {
        return null;
    }
    return shapedrawMakeFeature(SHAPEDRAW_MODE_POLYGON, "Polygon", [shapedrawCloseRing(coords)], jp.color);
}

function shapedrawLegacyJsonToFeatures(jsonText) {
    var out = [];
    var obj = null;
    try {
        obj = JSON.parse(jsonText);
    } catch (e) {
        obj = null;
    }
    if (!obj || !obj.shapes) {
        return out;
    }
    for (var i = 0; i < obj.shapes.length; i++) {
        var s = obj.shapes[i];
        if (!s) {
            continue;
        }
        var f = null;
        switch (s.type) {
        case SHAPEDRAW_TYPE_RECTANGLE:
            f = shapedrawJsonRectangleToFeature(s);
            break;

        case SHAPEDRAW_TYPE_CIRCLE:
            f = shapedrawJsonCircleToFeature(s);
            break;

        case SHAPEDRAW_TYPE_POLYLINE:
            f = shapedrawJsonPolylineToFeature(s);
            break;

        case SHAPEDRAW_TYPE_POLYGON:
            f = shapedrawJsonPolygonToFeature(s);
            break;
        }
        if (f) {
            out.push(f);
        } else {
            console.log("skipped invalid shape at index " + i + "\n");
        }
    }
    return out;
}

function shapedrawFeatureToLegacyShape(f) {
    if (!f || !f.geometry || !f.geometry.coordinates) {
        return null;
    }
    var props = f.properties || {};
    var type = SHAPEDRAW_MODE_TO_TYPE[props.mode];
    if (!type) {
        type = (f.geometry.type === "LineString") ? SHAPEDRAW_TYPE_POLYLINE : SHAPEDRAW_TYPE_POLYGON;
    }
    var color = (typeof props.color === "string" && props.color) ? props.color : SHAPEDRAW_DEFAULT_COLOR;

    switch (type) {
    case SHAPEDRAW_TYPE_RECTANGLE: {
        if (f.geometry.type !== "Polygon") {
            return null;
        }
        var b = shapedrawBoundsOfCoords(f.geometry.coordinates[0]);
        if (!b) {
            return null;
        }
        return {
            "type": SHAPEDRAW_TYPE_RECTANGLE,
            "color": color,
            "bounds": {
                "northEast": { "lat": String(b.north), "lon": String(b.east) },
                "southWest": { "lat": String(b.south), "lon": String(b.west) }
            }
        };
    }

    case SHAPEDRAW_TYPE_CIRCLE: {
        if (f.geometry.type !== "Polygon") {
            return null;
        }
        var cr = shapedrawRingCentroidRadius(f.geometry.coordinates[0]);
        if (!cr || cr.radiusM <= 0) {
            var rk = shapedrawToNum(props.radiusKilometers);
            if (rk === null || rk <= 0) {
                return null;
            }
            cr = { lat: f.geometry.coordinates[0][0][1], lng: f.geometry.coordinates[0][0][0], radiusM: rk * 1000 };
        }
        return {
            "type": SHAPEDRAW_TYPE_CIRCLE,
            "color": color,
            "center": { "lat": String(cr.lat), "lon": String(cr.lng) },
            "radius": String(cr.radiusM)
        };
    }

    case SHAPEDRAW_TYPE_POLYLINE: {
        if (f.geometry.type !== "LineString") {
            return null;
        }
        var path = [];
        for (var i = 0; i < f.geometry.coordinates.length; i++) {
            path.push(shapedrawCoordToLatlon(f.geometry.coordinates[i]));
        }
        if (path.length < 2) {
            return null;
        }
        return {
            "type": SHAPEDRAW_TYPE_POLYLINE,
            "color": color,
            "path": path
        };
    }

    case SHAPEDRAW_TYPE_POLYGON: {
        if (f.geometry.type !== "Polygon") {
            return null;
        }
        var path2 = [];
        var open = shapedrawOpenRing(f.geometry.coordinates[0]);
        for (var j = 0; j < open.length; j++) {
            path2.push(shapedrawCoordToLatlon(open[j]));
        }
        if (path2.length < 3) {
            return null;
        }
        return {
            "type": SHAPEDRAW_TYPE_POLYGON,
            "color": color,
            "paths": [{ "path": path2 }]
        };
    }
    }
    return null;
}

function shapedrawSnapshotToLegacyJson(features) {
    var parts = [];
    if (features) {
        for (var i = 0; i < features.length; i++) {
            var s = shapedrawFeatureToLegacyShape(features[i]);
            if (s) {
                parts.push(JSON.stringify(s));
            }
        }
    }
    return '{"shapes":[' + parts.join(",") + "]}";
}

function ShapesMap(_deleteButton, _clearButton, _infoButton, _Infocallback, _Completecallback) {

    var _draw = null;
    var _ready = false;
    var _pending = [];
    var _selectedId = null;
    var _shapeIds = [];
    var _nextAppId = 0;
    var _saveTimer = null;
    var _modeButtons = {};
    var _currentMode = SHAPEDRAW_MODE_SELECT;

    // printing

    function print(string) {
        console.log(string);
    }

    // ready gating

    function whenReady(fn) {
        if (_ready) {
            fn();
        } else {
            _pending.push(fn);
        }
    }

    function flushPending() {
        var q = _pending;
        _pending = [];
        while (q.length) {
            q.shift()();
        }
    }

    // storage

    function shapesSave() {
        if (!_ready) {
            return;
        }
        var feats = [];
        var kept = [];
        for (var i = 0; i < _shapeIds.length; i++) {
            var f = _draw.getSnapshotFeature(_shapeIds[i]);
            if (f) {
                feats.push(f);
                kept.push(_shapeIds[i]);
            }
        }
        _shapeIds = kept;
        var shapesStr = shapedrawSnapshotToLegacyJson(feats);
        localStorage.setItem("shapes", shapesStr);
        print("shapes string saved");
        if (typeof _Completecallback === "function") {
            _Completecallback(shapesStr);
        }
    }

    function jsonRead(jsonText) {
        var features = shapedrawLegacyJsonToFeatures(jsonText);
        if (!features.length) {
            return;
        }
        for (var i = 0; i < features.length; i++) {
            features[i].properties.appId = _nextAppId;
            _nextAppId++;
        }
        var results = _draw.addFeatures(features);
        for (var j = 0; j < results.length; j++) {
            if (results[j].valid !== false) {
                _shapeIds.push(results[j].id);
            }
        }
    }

    function shapesLoadRaw() {
        var start_length = _shapeIds.length;
        var shapes = localStorage.getItem("shapes");
        if (shapes) {
            jsonRead(shapes);
        }
        print((_shapeIds.length - start_length) + " shapes loaded\n");
    }

    function shapesSaveDebounced() {
        if (_saveTimer) {
            clearTimeout(_saveTimer);
        }
        _saveTimer = setTimeout(function () {
            _saveTimer = null;
            shapesSave();
        }, 250);
    }

    // selection

    function propsById(id) {
        var f = _draw.getSnapshotFeature(id);
        return f ? f.properties : null;
    }

    function selectionPrint() {
        if (_selectedId === null) {
            print("selection cleared\n");
        } else {
            var p = propsById(_selectedId);
            print(((p && p.appId != null) ? p.appId : "?") + ": selected\n");
        }
    }

    function selectionSet(id) {
        if (id == _selectedId) {
            return;
        }
        if (_selectedId !== null) {
            try { _draw.deselectFeature(_selectedId); } catch (e) {}
        }
        _selectedId = id;
        if (id !== null) {
            try { _draw.selectFeature(id); } catch (e) {}
        }
        selectionPrint();
    }

    function selectionClear() {
        selectionSet(null);
    }

    // modes and toolbar

    function highlightMode(mode) {
        for (var m in _modeButtons) {
            if (_modeButtons.hasOwnProperty(m)) {
                _modeButtons[m].style.backgroundColor = (m === mode) ? "#fcefa1" : "";
            }
        }
    }

    function isDrawingMode() {
        return _ready && _currentMode !== SHAPEDRAW_MODE_SELECT;
    }

    function setDrawMode(mode) {
        if (!_ready) {
            return;
        }
        _currentMode = mode;
        _draw.setMode(mode);
        highlightMode(mode);
        print("drawing mode set to " + mode + "\n");
    }

    function toolbarCreate() {
        var container = document.getElementById("buttons");
        if (!container) {
            return;
        }
        var defs = [
            { mode: SHAPEDRAW_MODE_SELECT, icon: "fa-mouse-pointer", title: "\u9078\u64c7/\u79fb\u52d5\u5f62\u72c0" },
            { mode: SHAPEDRAW_MODE_RECTANGLE, icon: "fa-square-o", title: "\u756b\u77e9\u5f62" },
            { mode: SHAPEDRAW_MODE_POLYGON, icon: "fa-object-group", title: "\u756b\u591a\u908a\u5f62" },
            { mode: SHAPEDRAW_MODE_LINESTRING, icon: "fa-pencil", title: "\u756b\u7dda\u6bb5" },
            { mode: SHAPEDRAW_MODE_CIRCLE, icon: "fa-circle-o", title: "\u756b\u5713\u5f62" }
        ];
        for (var i = defs.length - 1; i >= 0; i--) {
            (function (d) {
                var b = document.createElement("button");
                b.type = "button";
                b.title = d.title;
                b.className = "ui-state-default ui-corner-all";
                b.innerHTML = "<i class='fa " + d.icon + "'></i>";
                b.addEventListener("click", function () {
                    selectionClear();
                    setDrawMode(d.mode);
                });
                _modeButtons[d.mode] = b;
                container.insertBefore(b, container.firstChild);
            })(defs[i]);
        }
        highlightMode(SHAPEDRAW_MODE_SELECT);
    }

    // terra draw events

    function onFinish(id, context) {
        if (!context || context.action !== "draw") {
            return;
        }
        if (_shapeIds.indexOf(id) < 0) {
            _shapeIds.push(id);
        }
        var props = propsById(id);
        if (!props) {
            return;
        }
        var updates = {};
        if (!props.color) {
            updates.color = SHAPEDRAW_DEFAULT_COLOR;
        }
        if (props.appId == null) {
            updates.appId = _nextAppId;
            _nextAppId++;
        }
        var keyCount = 0;
        for (var k in updates) {
            if (updates.hasOwnProperty(k)) {
                keyCount++;
            }
        }
        if (keyCount) {
            try { _draw.updateFeatureProperties(id, updates); } catch (e) {}
        }
        print("new " + (SHAPEDRAW_MODE_TO_TYPE[props.mode] || props.mode) + " created (id = "
              + ((updates.appId != null) ? updates.appId : props.appId) + ")\n");
        shapesSave();
        setDrawMode(SHAPEDRAW_MODE_SELECT);
        selectionSet(id);
    }

    function onChange(ids, type) {
        if (!_ready) {
            return;
        }
        if (type === "create" || type === "styling") {
            return;
        }
        shapesSaveDebounced();
    }

    function onSelect(id) {
        if (id !== _selectedId) {
            _selectedId = id;
            selectionPrint();
        }
    }

    function onDeselect() {
        if (_selectedId !== null) {
            _selectedId = null;
            selectionPrint();
        }
    }

    // button handlers

    function onDeleteButtonClicked() {
        print("delete button clicked\n");
        if (_selectedId !== null) {
            var id = _selectedId;
            var idx = _shapeIds.indexOf(id);
            if (idx >= 0) {
                _shapeIds.splice(idx, 1);
            }
            try { _draw.removeFeatures([id]); } catch (e) {}
            _selectedId = null;
            print("selection cleared\n");
            shapesSave();
        }
    }

    function onClearButtonClicked() {
        print("clear button clicked\n");
        if (_selectedId !== null) {
            _selectedId = null;
            print("selection cleared\n");
        }
        _shapeIds = [];
        _draw.clear();
        shapesSave();
    }

    function onInfoButtonClicked() {
        if (_selectedId !== null) {
            var idx = _shapeIds.indexOf(_selectedId);
            if (idx >= 0) {
                var f = _draw.getSnapshotFeature(_shapeIds[idx]);
                if (f) {
                    var shapes_str = shapedrawSnapshotToLegacyJson([f]);
                    print(shapes_str + "\n");
                    if (typeof _Infocallback === "function") {
                        _Infocallback(shapes_str);
                    }
                }
            }
        }
    }

    function lastshapeSelectClick() {
        var id = _shapeIds[_shapeIds.length - 1];
        if (!id) {
            return;
        }
        selectionSet(id);
        onInfoButtonClicked();
    }

    // terra draw instance creation

    function colorStyler() {
        return function (feature) {
            var p = feature && feature.properties;
            return (p && typeof p.color === "string" && p.color) ? p.color : SHAPEDRAW_DEFAULT_COLOR;
        };
    }

    function buildModes() {
        function editableFlags() {
            return {
                feature: {
                    draggable: true,
                    coordinates: {
                        midpoints: true,
                        draggable: true,
                        deletable: true
                    }
                }
            };
        }
        var flags = {};
        flags[SHAPEDRAW_MODE_POLYGON] = editableFlags();
        flags[SHAPEDRAW_MODE_LINESTRING] = editableFlags();
        flags[SHAPEDRAW_MODE_RECTANGLE] = { feature: { draggable: true } };
        flags[SHAPEDRAW_MODE_CIRCLE] = { feature: { draggable: true } };

        return [
            new terraDraw.TerraDrawSelectMode({ flags: flags }),
            new terraDraw.TerraDrawRectangleMode({
                styles: {
                    fillColor: colorStyler(),
                    outlineColor: colorStyler(),
                    fillOpacity: 0.35,
                    outlineWidth: 1
                }
            }),
            new terraDraw.TerraDrawCircleMode({
                styles: {
                    fillColor: colorStyler(),
                    outlineColor: colorStyler(),
                    fillOpacity: 0.1,
                    outlineWidth: 2
                }
            }),
            new terraDraw.TerraDrawPolygonMode({
                styles: {
                    fillColor: colorStyler(),
                    outlineColor: colorStyler(),
                    fillOpacity: 0.35,
                    outlineWidth: 1
                }
            }),
            new terraDraw.TerraDrawLineStringMode({
                styles: {
                    lineStringColor: colorStyler(),
                    lineStringWidth: 3
                }
            })
        ];
    }

    function startDraw() {
        _draw = new terraDraw.TerraDraw({
            adapter: new terraDrawGoogleMapsAdapter.TerraDrawGoogleMapsAdapter({
                map: map,
                lib: google.maps,
                coordinatePrecision: 9
            }),
            modes: buildModes()
        });
        _draw.on("finish", onFinish);
        _draw.on("change", onChange);
        _draw.on("select", onSelect);
        _draw.on("deselect", onDeselect);
        _draw.on("ready", function () {
            _ready = true;
            setDrawMode(SHAPEDRAW_MODE_SELECT);
            flushPending();
        });
        _draw.start();
    }

    function onCreate() {
        if (typeof terraDraw === "undefined" || typeof terraDrawGoogleMapsAdapter === "undefined") {
            print("terra draw not loaded\n");
            return;
        }
        if (typeof google === "undefined" || !google.maps) {
            print("google maps not loaded\n");
            return;
        }
        if (typeof map === "undefined" || !map) {
            print("map not ready\n");
            return;
        }
        if (map.getProjection()) {
            startDraw();
        } else {
            google.maps.event.addListenerOnce(map, "projection_changed", startDraw);
        }
        toolbarCreate();
        _deleteButton.addEventListener('click', onDeleteButtonClicked);
        _clearButton.addEventListener('click', onClearButtonClicked);
        if (_infoButton) {
            _infoButton.addEventListener('click', onInfoButtonClicked);
        }
    }

    // initialization

    onCreate();

    return {
        selectionClear: function () { whenReady(selectionClear); },
        shapesLoad: function () { whenReady(shapesLoadRaw); },
        shapesClearAll: function () { whenReady(onClearButtonClicked); },
        lastshape_select_click: function () { whenReady(lastshapeSelectClick); },
        isDrawingMode: isDrawingMode
    };
}

ShapesMap._test = {
    legacyJsonToFeatures: shapedrawLegacyJsonToFeatures,
    snapshotToLegacyJson: shapedrawSnapshotToLegacyJson,
    geodesicRing: shapedrawGeodesicRing,
    ringCentroidRadius: shapedrawRingCentroidRadius,
    TYPE_TO_MODE: SHAPEDRAW_MODE_TO_TYPE
};
