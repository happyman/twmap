// 圖資資訊(copyright + 編輯 OSM) 與 游標座標/zoom 顯示
(function () {
  var msgEl = document.getElementById('msg');
  var attrEl = document.getElementById('map-attribution');
  if (!map || !mapApi || !msgEl || !attrEl) {
    return;
  }

  var copyrights = {
    osm: '&copy; <a href="http://www.openstreetmap.org/" target="_blank">OpenStreetMap</a> contributors, CC-BY-SA',
    moi_osm_twmap: '&copy; <a href="http://www.openstreetmap.org/" target="_blank">OpenStreetMap</a> contributors, CC-BY-SA <a href="http://rudy.basecamp.tw/taiwan_topo.html" target="_blank">魯地圖</a>',
    rudy: '&copy; <a href="http://www.openstreetmap.org/" target="_blank">OpenStreetMap</a> contributors, CC-BY-SA <a href="http://rudy.basecamp.tw/taiwan_topo.html" target="_blank">魯地圖</a>',
    rudy_en: '&copy; <a href="http://www.openstreetmap.org/" target="_blank">OpenStreetMap</a> contributors, CC-BY-SA <a href="http://rudy.basecamp.tw/taiwan_topo.html" target="_blank">魯地圖</a>',
    rudy_bn: '&copy; <a href="http://www.openstreetmap.org/" target="_blank">OpenStreetMap</a> contributors, CC-BY-SA <a href="http://rudy.basecamp.tw/taiwan_topo.html" target="_blank">魯地圖</a>',
    rudy_dn: '&copy; <a href="http://www.openstreetmap.org/" target="_blank">OpenStreetMap</a> contributors, CC-BY-SA <a href="http://rudy.basecamp.tw/taiwan_topo.html" target="_blank">魯地圖</a>',
    rudy_tn: '&copy; <a href="http://www.openstreetmap.org/" target="_blank">OpenStreetMap</a> contributors, CC-BY-SA <a href="http://rudy.basecamp.tw/taiwan_topo.html" target="_blank">魯地圖</a>',
    nlsc_emap: '<a href="//www.nlsc.gov.tw/" target="_blank">NLSC</a> - 通用版電子地圖',
    nlsc_photo: '<a href="//www.nlsc.gov.tw/" target="_blank">NLSC</a> - 正射影像',
    nlsc_photo_mix: '<a href="//www.nlsc.gov.tw/" target="_blank">NLSC</a> - 正射混合',
    nlsc_names: '<a href="//www.nlsc.gov.tw/" target="_blank">NLSC</a> - 地名',
    contour_2005: '<a href="//www.nlsc.gov.tw/" target="_blank">NLSC</a> - 內政部等高線 2005',
    contour_2015: '<a href="//www.nlsc.gov.tw/" target="_blank">NLSC</a> - 內政部等高線 2015',
    tw25k: '<a href="http://gissrv4.sinica.edu.tw/gis/twhgis.aspx" target="_blank">台灣歷史百年地圖</a> - 經建三版',
    tw25k_v1: '<a href="http://gissrv4.sinica.edu.tw/gis/twhgis.aspx" target="_blank">台灣歷史百年地圖</a> - 經建一版',
    historical_1924: '<a href="http://gissrv4.sinica.edu.tw/gis/twhgis.aspx" target="_blank">台灣歷史百年地圖</a> - 日治五萬分之一',
    historical_1924_new: '<a href="http://gissrv4.sinica.edu.tw/gis/twhgis.aspx" target="_blank">台灣歷史百年地圖</a> - 日治陸測 1924 新版',
    historical_1916: '<a href="http://gissrv4.sinica.edu.tw/gis/twhgis.aspx" target="_blank">台灣歷史百年地圖</a> - 日治蕃地地形圖',
    historical_1956: '<a href="http://gissrv4.sinica.edu.tw/gis/twhgis.aspx" target="_blank">台灣歷史百年地圖</a> - 臺灣五萬分之一 1956',
    historical_1966: '<a href="http://gissrv4.sinica.edu.tw/gis/twhgis.aspx" target="_blank">台灣歷史百年地圖</a> - 水利圖 1966',
    historical_1921: '<a href="http://ndaip.sinica.edu.tw/" target="_blank">台灣堡圖(大正版)</a>',
    historical_1904: '<a href="http://gissrv4.sinica.edu.tw/gis/twhgis.aspx" target="_blank">台灣歷史百年地圖</a> - 台灣堡圖(明治版)',
    historical_1904_triangulation: '<a href="https://gis.rchss.sinica.edu.tw/" target="_blank">台灣歷史百年地圖</a> - 三角測量點附圖',
    tw5k: '<a href="http://gissrv4.sinica.edu.tw/gis/twhgis.aspx" target="_blank">台灣歷史百年地圖</a> - 五千分之一相片基本圖',
    geo2016: '<a href="https://www.moeacgs.gov.tw/" target="_blank">經濟部地質調查</a> - 臺灣地質圖 2016',
    atis: '<a href="http://gissrv4.sinica.edu.tw/gis/twhgis.aspx" target="_blank">台灣歷史百年地圖</a> - 農航所正射影像',
    tri1999: '林崇雄先生於1999年手繪',
    hl20240403: '農村水保署及東華大學數位人文與地圖研究室',
    ttfb3_0601: '<a href="https://gis.sinica.edu.tw/taitung/" target="_blank">中研院</a> - 臺東郡國有林野圖',
    ttfb3_0602: '<a href="https://gis.sinica.edu.tw/taitung/" target="_blank">中研院</a> - 關山郡國有林野圖',
    ttfb3_0603: '<a href="https://gis.sinica.edu.tw/taitung/" target="_blank">中研院</a> - 新港郡國有林野圖',
    hillshading: '<a href="http://blog.nutsfactory.net/2016/09/14/taiwan-moi-20m-dtm/" target="_blank">內政部數值網格資料</a> - 山區陰影圖層',
    happyman: 'Happyman 航跡圖',
    gpx_track: 'Happyman GPX 航跡圖層',
    forest: 'Happyman 林班界圖'
  };

  var defaultCopyright = '<a href="http://www.openstreetmap.org/" target="_blank">OpenStreetMap</a> contributors';

  function currentLayerId() {
    var el = document.getElementById('bottom-layer-1-select');
    return el ? el.value : '';
  }

  function updateAttribution() {
    var layerId = currentLayerId();
    var copy = copyrights[layerId] || defaultCopyright;
    var c = ol.proj.toLonLat(map.getView().getCenter());
    var zoom = Math.round(map.getView().getZoom() || 0);
    var editurl = 'https://www.openstreetmap.org/edit#map=' + zoom + '/' + c[1].toFixed(5) + '/' + c[0].toFixed(5);
    attrEl.innerHTML = copy + ' | <a href="' + editurl + '" target="_blank" title="編輯 OpenStreetMap">[編輯OSM]</a>';
  }

  function updateMsg(lon, lat) {
    if (msgEl.hidden) {
      msgEl.hidden = false;
    }
    var gridEl = document.getElementById('grid-select');
    var grid = gridEl ? gridEl.value : 'WGS84';
    var lng = lon;
    var lng_text = lng.toFixed(4);
    var lat_text = lat.toFixed(4);
    if (twProjections && (grid.indexOf('TWD67') === 0 || grid.indexOf('TWD97') === 0)) {
      var ph = grid.indexOf('PH') > 0 ? 1 : 0;
      var is97 = grid.indexOf('TWD97') === 0;
      var p = is97 ? twProjections.lonlat2twd97(lng, lat, ph) : twProjections.lonlat2twd67(lng, lat, ph);
      if (p && isFinite(p.x) && isFinite(p.y)) {
        lng_text = Math.round(p.x).toString();
        lat_text = Math.round(p.y).toString();
      }
    }
    var zoom = Math.round(map.getView().getZoom() || 0);
    msgEl.innerHTML = '游標:' + grid + ':' + zoom + '/' + lng_text + '/' + lat_text;
  }

  var pointerMoveStamp = 0;
  map.on('pointermove', function (evt) {
    if (!evt.coordinate) {
      return;
    }
    var now = Date.now();
    if (now - pointerMoveStamp < 60) {
      return;
    }
    pointerMoveStamp = now;
    var lonlat = ol.proj.toLonLat(evt.coordinate);
    updateMsg(lonlat[0], lonlat[1]);
  });

  map.on('moveend', updateAttribution);
  map.on('change:size', updateAttribution);

  var layer1 = document.getElementById('bottom-layer-1-select');
  if (layer1) {
    layer1.addEventListener('change', updateAttribution);
  }
  var layer2 = document.getElementById('bottom-layer-2-select');
  if (layer2) {
    layer2.addEventListener('change', updateAttribution);
  }
  var gridEl2 = document.getElementById('grid-select');
  if (gridEl2) {
    gridEl2.addEventListener('change', function () {
      var c = ol.proj.toLonLat(map.getView().getCenter());
      updateMsg(c[0], c[1]);
    });
  }

  updateAttribution();
  var center = ol.proj.toLonLat(map.getView().getCenter());
  updateMsg(center[0], center[1]);
})();
