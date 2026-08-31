/* Shape drawing & management module for twmap4 (OpenLayers).
 *
 * Port of twmap3's shadowdraw.js (ShapesMap): draw multiple shapes
 * (polygon / rectangle / circle / line), select a shape, delete/clear/info.
 * Persisted to localStorage (key "shapes") using the same JSON schema as
 * twmap3 so the shared getelev.php infoshapes panel can consume it.
 *
 * Drawing mode is entered directly by clicking a draw-type toggle icon.
 * It auto-exits after each shape is drawn.
 */
function ShapeDraw4(opts) {
  var mapApi = opts.mapApi;
  var layer = opts.selectionLayer;
  var selectionLayerId = opts.selectionLayerId;
  var drawTypeBtns = (opts.drawTypeBtns || []);
  if (!(drawTypeBtns instanceof Array) && typeof NodeList !== 'undefined' && drawTypeBtns instanceof NodeList) {
    drawTypeBtns = Array.prototype.slice.call(drawTypeBtns);
  }
  var deleteBtn = opts.deleteBtn;
  var clearBtn = opts.clearBtn;
  var infoBtn = opts.infoBtn;

  var source = null;
  var getSource = function () {
    if (!source && layer && typeof layer.getSource === 'function') {
      source = layer.getSource();
    }
    return source;
  };

  var features = [];
  var selected = null;
  var currentMode = 'select';
  var nextAppId = 0;
  var drawing = false;

  var DEFAULT_COLOR = '#646464';

  function styleFor(feature) {
    var sel = selected && selected.get('shapeId') === feature.get('shapeId');
    var stroke = new ol.style.Stroke({
      color: sel ? '#0ea5e9' : '#ffcc00',
      width: sel ? 3 : 2
    });
    if (feature.getGeometry() && feature.getGeometry().getType() === 'LineString') {
      return new ol.style.Style({ stroke: stroke });
    }
    return new ol.style.Style({
      stroke: stroke,
      fill: new ol.style.Fill({
        color: sel ? 'rgba(14, 165, 233, 0.22)' : 'rgba(255, 204, 0, 0.18)'
      })
    });
  }

  function restyle() {
    var s = getSource();
    if (s && typeof s.getFeatures === 'function') {
      (s.getFeatures() || []).forEach(function (f) {
        f.setStyle(styleFor(f));
      });
    }
    refreshButtons();
  }

  function refreshButtons() {
    var hasSel = !!selected;
    if (deleteBtn) {
      deleteBtn.classList.toggle('disabled', !hasSel);
    }
    if (infoBtn) {
      infoBtn.classList.toggle('disabled', !hasSel);
    }
  }

  function setDrawingActive(active) {
    drawing = active;
    if (mapApi) {
      mapApi.shapeDrawActive = active;
    }
  }

  function setMode(mode) {
    currentMode = mode;
    drawTypeBtns.forEach(function (b) {
      b.classList.toggle('active', b.getAttribute('data-type') === mode);
    });
    if (mode === 'select' && drawing) {
      stopDraw();
    }
  }

  function stopDraw() {
    if (drawing) {
      if (typeof mapApi.stopDrawSelection === 'function') {
        mapApi.stopDrawSelection();
      }
      setDrawingActive(false);
    }
    setMode('select');
    refreshButtons();
  }

  function startDraw(type) {
    if (drawing) {
      stopDraw();
    }
    if (typeof mapApi.addDrawSelection !== 'function') {
      return;
    }
    mapApi.addDrawSelection(onShapeDrawn, type);
    setDrawingActive(true);
    currentMode = type;
    drawTypeBtns.forEach(function (b) {
      b.classList.toggle('active', b.getAttribute('data-type') === type);
    });
  }

  function selectShape(feature) {
    if (drawing) {
      stopDraw();
    }
    selected = feature || null;
    restyle();
  }

  function onShapeDrawn(ring, feature) {
    if (!feature) {
      return;
    }
    feature.set('shapeId', 'shape_' + Date.now() + '_' + features.length);
    feature.set('mode', currentMode);
    feature.set('color', feature.get('color') || DEFAULT_COLOR);
    feature.set('appId', nextAppId);
    nextAppId++;
    feature.setStyle(styleFor(feature));
    features.push(feature);
    selected = feature;
    restyle();
    stopDraw();
    if (mapApi) {
      mapApi.lastShapeDrawnTime = Date.now();
    }
    save();
  }

  function removeSelectedShape() {
    var feature = selected || features[features.length - 1];
    if (!feature) {
      return;
    }
    var idx = features.indexOf(feature);
    if (idx !== -1) {
      features.splice(idx, 1);
    }
    var s = getSource();
    if (s && typeof s.removeFeature === 'function') {
      s.removeFeature(feature);
    }
    selected = null;
    restyle();
    save();
  }

  function clearShapes() {
    features = [];
    selected = null;
    var s = getSource();
    if (s && typeof s.clear === 'function') {
      s.clear();
    }
    refreshButtons();
    save();
  }

  // ---- persistence (twmap3 legacy schema for getelev.php) ----

  function coordToLatlon(c) {
    var ll = ol.proj.toLonLat(c);
    return { lat: String(ll[1]), lon: String(ll[0]) };
  }

  function featureToShape(f) {
    if (!f || !f.getGeometry()) {
      return null;
    }
    var g = f.getGeometry();
    var t = g.getType();
    var color = (typeof f.get('color') === 'string' && f.get('color')) ? f.get('color') : DEFAULT_COLOR;
    if (t === 'Circle') {
      var ctr = ol.proj.toLonLat(g.getCenter());
      var radiusM = (typeof g.getRadius === 'function') ? g.getRadius() : 0;
      return {
        type: 'circle',
        color: color,
        center: { lat: String(ctr[1]), lon: String(ctr[0]) },
        radius: String(radiusM)
      };
    }
    if (t === 'LineString') {
      var path = g.getCoordinates().map(coordToLatlon);
      if (path.length < 2) {
        return null;
      }
      return { type: 'polyline', color: color, path: path };
    }
    if (t === 'Polygon') {
      var ring = g.getCoordinates()[0] || [];
      var open = ring.slice(0, ring.length - 1);
      var path2 = open.map(coordToLatlon);
      if (path2.length < 3) {
        return null;
      }
      return { type: 'polygon', color: color, paths: [{ path: path2 }] };
    }
    return null;
  }

  function snapshotJson(featuresArray) {
    var parts = [];
    (featuresArray || []).forEach(function (f) {
      var s = featureToShape(f);
      if (s) {
        parts.push(JSON.stringify(s));
      }
    });
    return '{"shapes":[' + parts.join(',') + ']}';
  }

  function save() {
    try {
      localStorage.setItem('shapes', snapshotJson(features));
    } catch (e) {}
  }

  // ---- loading ----

  function latlonToCoord(ll) {
    return ol.proj.fromLonLat([Number(ll.lon), Number(ll.lat)]);
  }

  function addFeature(geom, mode) {
    var feature = new ol.Feature({ geometry: geom });
    feature.set('shapeId', 'shape_' + Date.now() + '_' + features.length);
    feature.set('mode', mode);
    feature.set('color', DEFAULT_COLOR);
    feature.set('appId', nextAppId);
    nextAppId++;
    feature.setStyle(styleFor(feature));
    var s = getSource();
    if (s && typeof s.addFeature === 'function') {
      s.addFeature(feature);
    }
    features.push(feature);
  }

  function load() {
    var jsonText = null;
    try {
      jsonText = localStorage.getItem('shapes');
    } catch (e) {}
    if (!jsonText) {
      return;
    }
    var data = null;
    try {
      data = JSON.parse(jsonText);
    } catch (e) {
      return;
    }
    var list = (data && data.shapes) ? data.shapes : [];
    list.forEach(function (sh) {
      if (!sh || !sh.type) {
        return;
      }
      if (sh.type === 'circle' && sh.center && sh.radius) {
        var ctr = ol.proj.fromLonLat([Number(sh.center.lon), Number(sh.center.lat)]);
        var geom = new ol.geom.Circle(ctr, Number(sh.radius));
        addFeature(geom, 'Circle');
      } else if (sh.type === 'polyline' && sh.path && sh.path.length >= 2) {
        var coords = sh.path.map(latlonToCoord);
        addFeature(new ol.geom.LineString(coords), 'LineString');
      } else if (sh.type === 'polygon' && sh.paths && sh.paths.length && sh.paths[0].path && sh.paths[0].path.length >= 3) {
        var ring = sh.paths[0].path.map(latlonToCoord);
        ring.push(ring[0].slice());
        addFeature(new ol.geom.Polygon([ring]), 'Polygon');
      }
    });
    restyle();
  }

  // ---- info (meerkat panel) ----

  function showInfo() {
    var feature = selected || features[features.length - 1];
    if (!feature) {
      return;
    }
    var shape = featureToShape(feature);
    if (!shape) {
      return;
    }
    try {
      localStorage.setItem('infoshapes', JSON.stringify({ shapes: [shape] }));
    } catch (e) {}
    var url = (window.appConfig && window.appConfig.get_elev_url) ? window.appConfig.get_elev_url : '';
    if (url && typeof showmeerkat === 'function') {
      showmeerkat(url + '?infoshapes=1', { width: '600' });
    }
  }

  // ---- wiring ----

  if (typeof mapApi.onFeatureClick === 'function') {
    mapApi.onFeatureClick(selectionLayerId, selectShape);
  }

  drawTypeBtns.forEach(function (btn) {
    btn.addEventListener('click', function () {
      var type = btn.getAttribute('data-type');
      if (type === 'Select') {
        stopDraw();
      } else if (drawing && currentMode === type) {
        stopDraw();
      } else {
        startDraw(type);
      }
    });
  });

  if (deleteBtn) {
    deleteBtn.addEventListener('click', removeSelectedShape);
  }
  if (clearBtn) {
    clearBtn.addEventListener('click', clearShapes);
  }
  if (infoBtn) {
    infoBtn.addEventListener('click', showInfo);
  }

  refreshButtons();
  load();

  return {
    selectShape: selectShape,
    removeSelectedShape: removeSelectedShape,
    clearShapes: clearShapes,
    stopDraw: stopDraw
  };
}
