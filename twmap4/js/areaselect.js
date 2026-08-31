/* TWD67/TWD97 grid area selection for 出圖 (map export) — twmap4 port of
 * twmap3's addremove_polygon / update_params / generate_btn_click / export_points.
 *
 * Clicking on the map left-click selects (adds/removes) the containing 1km
 * TWD67/TWD97 grid block for the export area. Two polygons are drawn: a yellow
 * TWD67 frame and a green TWD97 frame. A params panel shows the grid range and
 * a 產生 button opens a modal to submit the range to the external map generator.
 */
function AreaSelect(opts) {
  var mapApi = opts.mapApi;
  var layer = opts.layer;
  var paramsEl = opts.paramsEl;
  var getGate = opts.getGate || function () { return false; };

  var miniX = 9999;
  var miniY = 0;
  var maxiX = 0;
  var maxiY = 9999;
  var callmake = null;
  var polygon = null;
  var polygon2 = null;
  var source = null;
  var tw = window.twProjections;

  function getSource() {
    if (!source && layer && typeof layer.getSource === 'function') {
      source = layer.getSource();
    }
    return source;
  }

  function clearPolygons() {
    var s = getSource();
    if (s && typeof s.clear === 'function') {
      s.clear();
    }
    polygon = null;
    polygon2 = null;
  }

  function drawPolygon(coords, color, fillColor, fillOpacity, strokeColor) {
    var s = getSource();
    if (!s || typeof s.addFeature !== 'function') {
      return;
    }
    var ring = coords.map(function (c) {
      return ol.proj.fromLonLat([c.x, c.y]);
    });
    var geom = new ol.geom.Polygon([ring]);
    var feature = new ol.Feature({ geometry: geom });
    feature.setStyle(new ol.style.Style({
      stroke: new ol.style.Stroke({ color: strokeColor || color, width: 1 }),
      fill: new ol.style.Fill({ color: fillColor, opacity: fillOpacity })
    }));
    s.addFeature(feature);
    return feature;
  }

  function renderPolygons(ph) {
    clearPolygons();
    var tl = tw.lonlat2twd67(miniX, miniY, ph);
    var br = tw.lonlat2twd67(maxiX, maxiY, ph);
    var data = {
      x: Math.round(tl.x / 1000),
      y: Math.round(tl.y / 1000),
      shiftx: Math.round((br.x - tl.x) / 1000),
      shifty: Math.round((tl.y - br.y) / 1000)
    };
    var gen = function (baseX, conv) {
      return [
        conv(baseX * 1000, data.y * 1000, ph),
        conv((baseX + data.shiftx) * 1000, data.y * 1000, ph),
        conv((baseX + data.shiftx) * 1000, (data.y - data.shifty) * 1000, ph),
        conv(baseX * 1000, (data.y - data.shifty) * 1000, ph)
      ];
    };
    polygon = drawPolygon(gen(data.x, tw.twd672lonlat), '#FFFF00', 'rgba(255,0,0,0.2)');
    polygon2 = drawPolygon(gen(data.x + 1, tw.twd972lonlat), '#00FF00', 'rgba(0,255,0,0.1)');
    return data;
  }

  function updateParams(ph) {
    var data = renderPolygons(ph);
    if (!data) {
      return;
    }
    var total = Math.ceil(data.shiftx / 5) * Math.ceil(data.shifty / 7);
    var total1 = Math.ceil(data.shiftx / 7) * Math.ceil(data.shifty / 5);
    var page = (total1 < total) ? (total1 + ' 張 A4R') : (total + ' 張 A4');
    callmake = 'x=' + data.x + '&y=' + data.y + '&shiftx=' + data.shiftx + '&shifty=' + data.shifty + '&ph=' + ph + '&version=3';
    if (paramsEl) {
      paramsEl.innerHTML = 'TWD67:' + data.x + ':' + data.y + ' ' + data.shiftx + 'x' + data.shifty +
        ' km 共 ' + page +
        ' <button type="button" id="generate" title="將參數傳送到地圖產生器">產生</button>';
      var generate = document.getElementById('generate');
      if (generate) {
        generate.addEventListener('click', generateBtnClick);
      }
    }
  }

  function handleClick(lon, lat) {
    if (getGate()) {
      return;
    }
    var cc = tw.isTaiwan(lat, lon);
    if (cc === 0) {
      return;
    }
    var ph = (cc === 2) ? 1 : 0;
    var data = tw.lonlat_getblock(lon, lat, ph);
    var minx = data[0].x;
    var miny = data[0].y;
    var maxx = data[1].x;
    var maxy = data[1].y;
    if (lon >= miniX && lon <= maxiX && lat >= maxiY && lat <= miniY) {
      // 相減
      maxiX = minx;
      maxiY = miny;
    } else {
      if (minx < miniX) miniX = minx;
      if (miny > miniY) miniY = miny;
      if (maxx > maxiX) maxiX = maxx;
      if (maxy < maxiY) maxiY = maxy;
    }
    if ((maxiX - miniX < 0.0088) || (miniY - maxiY < 0.0088)) {
      miniX = 9999; miniY = 0; maxiX = 0; maxiY = 9999;
      clearPolygons();
      if (paramsEl) {
        paramsEl.innerHTML = '尚未選圖';
      }
      callmake = null;
      return;
    }
    updateParams(ph);
  }

  function generateBtnClick() {
    if (callmake === null) {
      window.alert('請選擇範圍');
      return;
    }
    var modal = document.getElementById('inputtitleform');
    var overlay = document.getElementById('areaselect-modal-overlay');
    if (modal) {
      modal.style.display = 'block';
    }
    if (overlay) {
      overlay.style.display = 'block';
    }
    var centerLon = miniX + (maxiX - miniX) / 2;
    var centerLat = miniY + (maxiY - miniY) / 2;
    if (typeof mapApi.setView === 'function') {
      mapApi.setView([centerLon, centerLat], undefined);
    }
  }

  function closeModal() {
    var modal = document.getElementById('inputtitleform');
    var overlay = document.getElementById('areaselect-modal-overlay');
    if (modal) {
      modal.style.display = 'none';
    }
    if (overlay) {
      overlay.style.display = 'none';
    }
  }

  function submitModal() {
    var titleEl = document.getElementById('inputtitle');
    var datumEl = document.getElementById('datum');
    if (!titleEl || !titleEl.value) {
      window.alert('請輸入地圖標題');
      return;
    }
    var title = titleEl.value;
    var datum = datumEl ? datumEl.value : 'TWD97';
    var url = (window.appConfig && window.appConfig.callmake_url) ? window.appConfig.callmake_url : '';
    var target = url + callmake + '&title=' + encodeURIComponent(title) + '&datum=' + datum;
    closeModal();
    if (window.confirm('程式將會傳送參數給地圖產生器,確定嘛?')) {
      window.location.href = target;
    }
  }

  function initModal() {
    var submitBtn = document.getElementById('inputtitlebtn');
    var cancelBtn = document.getElementById('inputtitlebtn2');
    if (submitBtn) {
      submitBtn.addEventListener('click', submitModal);
    }
    if (cancelBtn) {
      cancelBtn.addEventListener('click', closeModal);
    }
  }

  function exportPoints() {
    var url = (window.appConfig && window.appConfig.exportkml_url) ? window.appConfig.exportkml_url : '';
    if (url && typeof showmeerkat === 'function') {
      showmeerkat(url + '?bound=' + miniX + ',' + miniY + ',' + maxiX + ',' + maxiY, { width: '600' });
    }
  }

  initModal();

  return {
    handleClick: handleClick,
    generateBtnClick: generateBtnClick,
    exportPoints: exportPoints,
    closeModal: closeModal
  };
}
