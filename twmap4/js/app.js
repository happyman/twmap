function initialViewFromURL() {
  const params = new URLSearchParams(window.location.search);
  const view = {
    center: window.appConfig.default_center,
    zoom: window.appConfig.default_zoom
  };
  const goto = params.get('goto');
  if (goto) {
    const parts = goto.split(',');
    if (parts.length === 2) {
      const lat = parseFloat(parts[0]);
      const lon = parseFloat(parts[1]);
      if (isFinite(lat) && isFinite(lon) && lat >= -90 && lat <= 90 && lon >= -180 && lon <= 180) {
        view.center = [lon, lat];
      }
    }
  }
  const zoom = params.get('zoom');
  if (zoom) {
    const z = parseFloat(zoom);
    if (isFinite(z) && z >= 1 && z <= 21) {
      view.zoom = z;
    }
  }
  return view;
}

const mapApi = createMapApi(olMapApiAdapter);
const initialView = initialViewFromURL();
const map = mapApi.init({
  target: 'map',
  center: initialView.center,
  zoom: initialView.zoom
});

const markerLayerId = 'markers';
const selectionLayerId = 'selection';
const roadLayerId = 'road';
const losLayerId = 'line_of_sight';
const losLayer = mapApi.addVectorLayer({ id: losLayerId, zIndex: 35 });
const pointPopup = document.getElementById('point-popup');
const pointPopupOverlay = new ol.Overlay({
  element: pointPopup,
  positioning: 'bottom-center',
  offset: [0, -16],
  stopEvent: true
});
map.addOverlay(pointPopupOverlay);

const layerControlsEl = document.getElementById('layer-controls');
if (layerControlsEl) {
  map.addControl(new ol.control.Control({ element: layerControlsEl }));
}

let markerLabelsEnabled = true;
const markerFilterState = new Set([
  'peak_1st',
  'peak_2nd',
  'peak_3rd',
  'forest_point',
  'forest_unknown',
  'giant_tree',
  'independent_peak',
  'nameless_peak',
  'mountain_hut',
  'shelter',
  'station',
  'police_box',
  'watch_station',
  'tribal_station',
  'water_source',
  'hot_spring',
  'waterfall',
  'stream',
  'lake',
  'rock',
  'point',
  'ruins',
  'terrain_point',
  'valley',
  'hut',
  'camp',
  'dry_ravine',
  'water_pool',
  'old_village',
  'steps',
  'cliff',
  'bridge',
  'workstation'
]);
const allMarkerFeatures = [];
const poiIndex = [];
let poiDataReady = false;

const baseMapSources = Object.fromEntries(
  Object.entries(mapSources).filter(function ([sourceId]) {
    return mapSources[sourceId].category !== 'road';
  }).sort(function ([, sourceA], [, sourceB]) {
    return sourceA.order - sourceB.order;
  })
);
const roadSources = Object.fromEntries(
  Object.entries(mapSources).filter(function ([sourceId]) {
    return mapSources[sourceId].category === 'road';
  }).sort(function ([, sourceA], [, sourceB]) {
    return sourceA.order - sourceB.order;
  })
);

mapApi.addTileLayer({ id: 'bottom-1', ...mapSources.atis, visible: true, zIndex: 0 });
mapApi.addTileLayer({ id: 'bottom-2', ...mapSources.moi_osm_twmap, visible: true, opacity: 0.7, zIndex: 1 });
mapApi.addTileLayer({
  id: roadLayerId,
  ...mapSources.nlsc_names,
  visible: true,
  zIndex: 20
});

mapApi.addTileLayer({
  id: 'tracks',
  ...mapSources.gpx_track,
  visible: true,
  zIndex: 18
});

mapApi.addVectorLayer({ id: markerLayerId, visible: true });
const selectionLayer = mapApi.addVectorLayer({ id: selectionLayerId, visible: true });
const areaselectLayerId = 'areaselect';
const areaselectLayer = mapApi.addVectorLayer({ id: areaselectLayerId, visible: true, zIndex: 30 });

const markerLayer = map.getLayers().getArray().find(function (layer) {
  return layer && layer.get('id') === markerLayerId;
});
const markerSource = markerLayer ? markerLayer.getSource() : null;
if (markerSource && typeof markerSource.getSource === 'function') {
  const clusterSource = new ol.source.Cluster({
    distance: 24,
    source: markerSource
  });
  markerLayer.setSource(clusterSource);
  markerLayer.setStyle(function (feature) {
    const clusterFeatures = feature.get('features');
    if (!clusterFeatures || !clusterFeatures.length) {
      return null;
    }

    if (clusterFeatures.length === 1) {
      return olMapApiAdapter.createMarkerStyle(clusterFeatures[0].getProperties());
    }

    return new ol.style.Style({
      image: new ol.style.Circle({
        radius: 12,
        fill: new ol.style.Fill({ color: 'rgba(15, 23, 42, 0.9)' }),
        stroke: new ol.style.Stroke({ color: '#ffffff', width: 2 })
      }),
      text: new ol.style.Text({
        text: String(clusterFeatures.length),
        font: '600 10px Arial',
        fill: new ol.style.Fill({ color: '#ffffff' }),
        stroke: new ol.style.Stroke({ color: '#0f172a', width: 2 })
      })
    });
  });
}

function shouldDisplayMarker(feature) {
  if (!feature || typeof feature.get !== 'function') {
    return false;
  }

  const iconName = feature.get('iconName') || 'point';
  return markerFilterState.has(iconName);
}

function rebuildVisibleMarkerFeatures() {
  const markerLayerTarget = map.getLayers().getArray().find(function (layer) {
    return layer && layer.get('id') === markerLayerId;
  });
  if (!markerLayerTarget || !markerLayerTarget.getSource) {
    return;
  }

  const source = markerLayerTarget.getSource();
  const baseSource = source && typeof source.getSource === 'function' ? source.getSource() : source;
  if (!baseSource || typeof baseSource.clear !== 'function') {
    return;
  }

  baseSource.clear();
  allMarkerFeatures.forEach(function (feature) {
    if (shouldDisplayMarker(feature)) {
      baseSource.addFeature(feature);
    }
  });
  baseSource.changed();
}

function refreshMarkerFilterState() {
  rebuildVisibleMarkerFeatures();
  syncMarkerLabelState();
}

function markerReloadSingle(opt) {
  if (!opt || !opt.action) {
    return;
  }

  if (opt.action === 'delete') {
    const idx = allMarkerFeatures.findIndex(function (feature) {
      return feature.get('pointId') === opt.id;
    });
    if (idx >= 0) {
      const removed = allMarkerFeatures.splice(idx, 1)[0];
      const layer = map.getLayers().getArray().find(function (candidate) {
        return candidate && candidate.get('id') === markerLayerId;
      });
      if (layer && layer.getSource()) {
        const source = layer.getSource();
        const baseSource = typeof source.getSource === 'function' ? source.getSource() : source;
        if (typeof baseSource.removeFeature === 'function') {
          baseSource.removeFeature(removed);
        }
      }
      rebuildVisibleMarkerFeatures();
    }
    return;
  }

  if (opt.action !== 'update' && opt.action !== 'add') {
    return;
  }

  const url = window.appConfig.pointdata_url + '?id=' + encodeURIComponent(opt.id);
  fetch(url, { cache: 'no-store' })
    .then(function (response) {
      if (!response.ok) {
        throw new Error('point reload failed');
      }
      return response.json();
    })
    .then(function (points) {
      const point = Array.isArray(points) && points.length ? points[0] : null;
      if (!point) {
        return;
      }
      const lon = Number(point.x);
      const lat = Number(point.y);
      if (!Number.isFinite(lon) || !Number.isFinite(lat)) {
        return;
      }

      const existing = allMarkerFeatures.find(function (feature) {
        return feature.get('pointId') === opt.id;
      });
      if (existing) {
        existing.setGeometry(new ol.geom.Point(ol.proj.fromLonLat([lon, lat])));
        existing.setProperties({
          color: pointColor(point),
          title: point.name || '',
          labelText: point.name || '',
          showLabel: markerLabelsEnabled && map.getView().getZoom() >= 12,
          pointId: point.id,
          iconName: getIconName(point),
          pointType: point.type || '',
          pointClass: point.class || '',
          radius: 12
        });
        existing.changed();
      } else {
        const feature = mapApi.addMarker(markerLayerId, lon, lat, {
          color: pointColor(point),
          title: point.name || '',
          labelText: point.name || '',
          showLabel: markerLabelsEnabled && map.getView().getZoom() >= 12,
          pointId: point.id,
          iconName: getIconName(point),
          pointType: point.type || '',
          pointClass: point.class || '',
          radius: 12
        });
        if (feature) {
          allMarkerFeatures.push(feature);
        }
        rebuildVisibleMarkerFeatures();
        syncMarkerLabelState();
      }
    })
    .catch(function (error) {
      console.warn('point reload unavailable:', error.message);
    });
}

const initialMarkers = [
  [121.5654, 25.0330, '#ff0000'],
  [121.5390, 25.0474, '#00aa00'],
  [121.6038, 25.0167, '#0000ff']
];

for (const [lon, lat, color] of initialMarkers) {
  const feature = mapApi.addMarker(markerLayerId, lon, lat, { color });
  if (feature) {
    allMarkerFeatures.push(feature);
  }
}

const typeToIconMap = {
  '一等點': 'peak_1st',
  '二等點': 'peak_2nd',
  '三等點': 'peak_3rd',
  '森林點': 'forest_point',
  '未知森林點': 'forest_unknown',
  '巨木': 'giant_tree',
  '獨立峰': 'independent_peak',
  '無基石山頭': 'nameless_peak',
  '山屋': 'mountain_hut',
  '工寮': 'shelter',
  '駐在所': 'station',
  '警察駐在所': 'police_box',
  '蕃務駐在所': 'tribal_station',
  '水源': 'water_source',
  '溫泉': 'hot_spring',
  '瀑布': 'waterfall',
  '溪流': 'stream',
  '湖泊': 'lake',
  '岩石': 'rock',
  '遺跡': 'ruins',
  '補點': 'terrain_point',
  '圖根點': 'terrain_point',
  '谷地': 'valley',
  '獵寮': 'hut',
  '營地': 'camp',
  '乾溝': 'dry_ravine',
  '黑水池': 'water_pool',
  '積水池': 'water_pool',
  '舊部落': 'old_village',
  '階梯': 'steps',
  '崩壁': 'cliff',
  '吊橋': 'bridge',
  '工作站': 'workstation'
};

function getIconName(point) {
  if (point.type && typeToIconMap[point.type]) {
    return typeToIconMap[point.type];
  }
  if (point.class === '1' || point.class === 1) {
    return 'peak_1st';
  }
  if (point.class === '2' || point.class === 2) {
    return 'peak_2nd';
  }
  if (point.class === '3' || point.class === 3) {
    return 'peak_3rd';
  }
  return 'point';
}

function pointColor(point) {
  if (point.class === '1' || point.class === 1) {
    return '#d32f2f';
  }
  if (point.class === '2' || point.class === 2) {
    return '#f57c00';
  }
  if (point.class === '3' || point.class === 3) {
    return '#1976d2';
  }
  return '#455a64';
}

function loadPointData() {
  const url = window.appConfig.pointdata_url + '?id=ALL';
  fetch(url, { cache: 'no-store' })
    .then(function (response) {
      if (!response.ok) {
        throw new Error('pointdata request failed');
      }
      return response.json();
    })
    .then(function (points) {
      if (!Array.isArray(points)) {
        throw new Error('pointdata response is not an array');
      }
      for (const point of points) {
        const lon = Number(point.x);
        const lat = Number(point.y);
        if (!Number.isFinite(lon) || !Number.isFinite(lat)) {
          continue;
        }
        const feature = mapApi.addMarker(markerLayerId, lon, lat, {
          color: pointColor(point),
          title: point.name || '',
          labelText: point.name || '',
          showLabel: markerLabelsEnabled,
          pointId: point.id,
          iconName: getIconName(point),
          pointType: point.type || '',
          pointClass: point.class || '',
          radius: 12
        });
        if (feature) {
          allMarkerFeatures.push(feature);
        }
        if (point.name) {
          poiIndex.push({
            name: point.name,
            alias: point.alias || '',
            lon: lon,
            lat: lat,
            id: point.id,
            type: point.type || ''
          });
        }
      }
      populateSearchDatalist();
      rebuildVisibleMarkerFeatures();
      syncMarkerLabelState();
      poiDataReady = true;
      console.log('loaded point data:', points.length);
    })
    .catch(function (error) {
      poiDataReady = true;
      console.warn('pointdata unavailable:', error.message);
    });
}

loadPointData();

function twRange(lat, lon) {
  return typeof twProjections.isTaiwan === 'function' ? twProjections.isTaiwan(lat, lon) : 0;
}

function coordBlock(lon, lat) {
  const wgsLat = Number(lat);
  const wgsLon = Number(lon);
  const ll = twRange(wgsLat, wgsLon);
  const rows = ['經緯度: ' + wgsLon.toFixed(5) + ', ' + wgsLat.toFixed(5)];

  if (twProjections.available()) {
    if (ll === 1) {
      const p67 = twProjections.lonlat2twd67(wgsLon, wgsLat, 0);
      const p97 = twProjections.lonlat2twd97(wgsLon, wgsLat, 0);
      const cadM = twProjections.lonlat2cad(wgsLon, wgsLat, 'm');
      const cadJ = twProjections.lonlat2cad(wgsLon, wgsLat, 'j');
      rows.push(
        '台灣 TWD67 TM2: ' + Math.round(p67.x) + ',' + Math.round(p67.y),
        '台灣 TWD97 TM2: ' + Math.round(p97.x) + '/' + Math.round(p97.y),
        '地籍(公尺): cm:' + cadM.x.toFixed(2) + ',' + cadM.y.toFixed(2),
        '地籍(日制): cj:' + cadJ.x.toFixed(2) + ',' + cadJ.y.toFixed(2)
      );
    } else if (ll === 2 || ll === 3 || ll === 4) {
      const labels = { 2: '澎湖', 3: '金門', 4: '馬祖' };
      const area = labels[ll] || '澎湖';
      const p67 = twProjections.lonlat2twd67(wgsLon, wgsLat, 1);
      const p97 = twProjections.lonlat2twd97(wgsLon, wgsLat, 1);
      rows.push(
        area + ' TWD67 TM2: ' + Math.round(p67.x) + ',' + Math.round(p67.y),
        area + ' TWD97 TM2: ' + Math.round(p97.x) + '/' + Math.round(p97.y)
      );
    }
  }

  rows.push(twProjections.ConvertDDToDMS(wgsLat) + ', ' + twProjections.ConvertDDToDMS(wgsLon));

  return rows.map(function (r) {
    return '<div class="popup-coord">' + r + '</div>';
  }).join('');
}

function popupLinks(lon, lat, zoom) {
  const wgsLat = Number(lat);
  const wgsLon = Number(lon);
  const link = function (href, icon, title, label, panel) {
    if (panel) {
      return '<a href="#" onClick="showmeerkat(\'' + href + '\',{}); return false;" title="' + title + '"><i class="fa ' + icon + '"></i> ' + label + '</a>';
    }
    return '<a href="' + href + '" target="_blank" rel="noopener" title="' + title + '"><i class="fa ' + icon + '"></i> ' + label + '</a>';
  };
  const links = [
    link('//maps.nlsc.gov.tw/go/' + (wgsLon.toFixed(5)) + '/' + (wgsLat.toFixed(5)), 'fa-globe', 'NLSC 地圖', 'NLSC'),
    link(window.appConfig.promlist_url, 'fa-star', '獨立峰排名', '獨立峰', true),
    link('//www.windy.com/' + (wgsLat.toFixed(3)) + '/' + (wgsLon.toFixed(3)) + '/meteogram?rain,' + (wgsLat.toFixed(3)) + ',' + (wgsLon.toFixed(3)) + ',' + zoom + ',m:ejkajw7', 'fa-cloud', 'windy', 'windy'),
    link('//wiwari.github.io/accTW/?center=' + (wgsLat.toFixed(3)) + ',' + (wgsLon.toFixed(3)) + '&zoom=' + zoom, 'fa-tint', '集水區觀察員', '集水區')
  ];
  return '<div class="popup-links">' + links.join('') + '</div>';
}

function permalink(lon, lat, zoom) {
  return window.location.origin + window.location.pathname + '?goto=' + Number(lat).toFixed(5) + ',' + Number(lon).toFixed(5) + '&zoom=' + zoom;
}

function showPointPopup(point, lon, lat) {
  if (!pointPopup) {
    return;
  }

  const title = point.name || '未命名點位';
  const summary = point.story || '<br>未提供詳細資料';
  const zoom = Math.round(map.getView().getZoom());
  const pointMeta = [
    point.type ? '類型: ' + point.type : '',
    point.class ? '類別: ' + point.class : ''
  ].filter(Boolean).join(' · ');

  const adminLink = point.info ? '<br><div class="popup-meta">[已登入] <a href="#" onClick="showmeerkat(\'' + window.appConfig.pointdata_admin_url + '?x=' + Number(lon).toFixed(5) + '&y=' + Number(lat).toFixed(5) + '\',{}); return false;">新增點位</a></div>' : '';

  const ele = Number(point.ele);
  const losLink = (isFinite(ele) && ele > -1000 && typeof show_line_of_sight === 'function') ?
    '<div class="popup-meta"><a href="#" id="los_link" onClick="show_line_of_sight(' + Number(lon).toFixed(5) + ',' + Number(lat).toFixed(5) + ',' + Math.round(ele) + '); return false;">通視模擬 (' + Math.round(ele) + 'M)</a></div>' : '';

  pointPopup.innerHTML = [
    '<button class="popup-close" onclick="closePointPopup()" title="關閉">&times;</button>',
    '<div class="popup-header">' + title +
      ' <a class="popup-permalink" href="' + permalink(lon, lat, zoom) + '" target="_blank" title="複製此位置連結"><i class="fa fa-link"></i></a>' +
      '</div>',
    pointMeta ? '<div class="popup-meta">' + pointMeta + '</div>' : '',
    coordBlock(lon, lat),
    '<div class="popup-story">' + summary + '</div>',
    losLink,
    adminLink,
    measureButtonsHtml(lon, lat),
    popupLinks(lon, lat, zoom)
  ].join('');

  pointPopupOverlay.setPosition(ol.proj.fromLonLat([lon, lat]));
  pointPopup.classList.remove('hidden');
}

function fetchElevAndAdmin(lon, lat, rows) {
  const url = window.appConfig.get_elev_url +
    '?loc=' + Number(lat).toFixed(6) + ',' + Number(lon).toFixed(6);
  return fetch(url, { cache: 'no-store' })
    .then(function (response) {
      if (!response.ok) {
        throw new Error('elev request failed');
      }
      return response.json();
    })
    .then(function (data) {
      let elevation = null;
      if (data && data.ok === true && data.rsp) {
        const lines = [];
        if (typeof data.rsp.elevation === 'number' && data.rsp.elevation > -1000) {
          lines.push('高度: ' + Math.round(data.rsp.elevation) + 'M');
          elevation = data.rsp.elevation;
        }
        if (data.rsp.admin) {
          lines.push(data.rsp.admin);
        }
        if (data.rsp.nature) {
          lines.push(data.rsp.nature);
        }
        if (lines.length) {
          rows.push('<div class="popup-meta">' + lines.join('<br>') + '</div>');
        }
      }
      return elevation;
    })
    .catch(function (error) {
      console.warn('elev unavailable:', error.message);
      return null;
    });
}

function renderLocationPopup(lon, lat, zoom, rows) {
  if (!pointPopup) {
    return;
  }
  pointPopup.innerHTML = [
    '<button class="popup-close" onclick="closePointPopup()" title="關閉">&times;</button>',
    '<div class="popup-header">位置資訊</div>',
    coordBlock(lon, lat),
    rows.join(''),
    measureButtonsHtml(lon, lat),
    popupLinks(lon, lat, zoom)
  ].join('');
  pointPopupOverlay.setPosition(ol.proj.fromLonLat([lon, lat]));
  pointPopup.classList.remove('hidden');
}

function showLocationInfo(lon, lat) {
  const zoom = Math.round(map.getView().getZoom()) || 0;
  const radius = (20 - zoom) * 10 - 10;
  const rows = [];

  const finish = function () {
    fetchElevAndAdmin(lon, lat, rows).then(function (elevation) {
      if (elevation !== null && typeof show_line_of_sight === 'function') {
        rows.push('<div class="popup-meta"><a href="#" id="los_link" onClick="show_line_of_sight(' + Number(lon).toFixed(5) + ',' + Number(lat).toFixed(5) + ',' + Math.round(elevation) + '); return false;">通視模擬 (' + Math.round(elevation) + 'M)</a></div>');
      }
      renderLocationPopup(lon, lat, zoom, rows);
    });
  };

  if (radius > 0 && radius <= 100) {
    const url = window.appConfig.get_waypoints_url +
      '?x=' + Number(lon).toFixed(5) + '&y=' + Number(lat).toFixed(5) + '&r=' + radius + '&detail=0';
    fetch(url, { cache: 'no-store' })
      .then(function (response) {
        if (!response.ok) {
          throw new Error('waypoints request failed');
        }
        return response.json();
      })
      .then(function (data) {
        const names = [];
        if (data.ok === true && data.rsp) {
          (data.rsp.wpt || []).forEach(function (w) {
            names.push(w.name || w.x + ',' + w.y);
          });
          (data.rsp.trk || []).forEach(function (t) {
            names.push(t.name || '');
          });
        }
        if (names.length) {
          const extras = names.slice(0, 3).map(function (n) {
            return '<span class="popup-wpt">' + n + '</span>';
          });
          rows.push('<div class="popup-meta">附近航點 (半徑 ' + radius + 'M): ' + extras.join('、') + '</div>');
          if (typeof showmeerkat === 'function' && window.appConfig.get_waypoints_url) {
            const extraUrl = window.appConfig.get_waypoints_url +
              '?x=' + Number(lon).toFixed(5) + '&y=' + Number(lat).toFixed(5) + '&r=' + radius + '&detail=1';
            showmeerkat(extraUrl, { width: 600 });
          }
        }
        finish();
      })
      .catch(function (error) {
        console.warn('waypoints unavailable:', error.message);
        finish();
      });
  } else {
    finish();
  }
}

function closePointPopup() {
  if (pointPopup) {
    pointPopup.classList.add('hidden');
    pointPopupOverlay.setPosition(undefined);
    pointPopup.innerHTML = '';
  }
}

let measureStartCoords = null;
let measureStartOverlay = null;

function measureButtonsHtml(lon, lat) {
  const isActive = measureStartCoords !== null;
  return '<div class="popup-measure">' +
    '<button onclick="setMeasureStart(' + lon + ',' + lat + ')" title="設定測量起點' + (isActive ? ' (已設定)' : '') + '"' +
    (isActive ? ' class="measure-active"' : '') + '><i class="fa fa-play"></i> 起點</button>' +
    '<button onclick="setMeasureEnd(' + lon + ',' + lat + ')" title="設定測量終點"' +
    (!isActive ? ' disabled style="opacity:0.4"' : '') + '><i class="fa fa-stop"></i> 終點</button>' +
    '</div>';
}

function setMeasureStart(lon, lat) {
  measureStartCoords = [lon, lat];
  if (!measureStartOverlay) {
    const el = document.createElement('div');
    el.style.cssText = 'width:14px;height:14px;background:#fbbf24;border:2px solid #fff;border-radius:50%;box-shadow:0 0 6px rgba(0,0,0,.5);';
    measureStartOverlay = new ol.Overlay({ element: el, positioning: 'center-center', offset: [0, 0], stopEvent: false });
    map.addOverlay(measureStartOverlay);
  }
  measureStartOverlay.setPosition(ol.proj.fromLonLat([lon, lat]));
  const zoom = Math.round(map.getView().getZoom());
  const coord = coordBlock(lon, lat);
  if (pointPopup) {
    const html = pointPopup.innerHTML;
    pointPopup.innerHTML = html.replace(
      /(<button[^>]*measure-active[^>]*>)/,
      '$1'
    );
  }
  updateMsgBar();
}

function setMeasureEnd(lon, lat) {
  if (!measureStartCoords) return;
  const lon1 = measureStartCoords[0], lat1 = measureStartCoords[1];
  const lon2 = lon, lat2 = lat;
  const dist = ol.sphere.getDistance([lon1, lat1], [lon2, lat2]);
  let bearing = Math.atan2(
    Math.sin((lon2 - lon1) * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180),
    Math.cos(lat1 * Math.PI / 180) * Math.sin(lat2 * Math.PI / 180) -
    Math.sin(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * Math.cos((lon2 - lon1) * Math.PI / 180)
  ) * 180 / Math.PI;
  if (bearing < 0) bearing += 360;

  // Add measurement line to selection layer (like draw tool polyline)
  var coords = [
    ol.proj.fromLonLat([lon1, lat1]),
    ol.proj.fromLonLat([lon2, lat2])
  ];
  var feature = new ol.Feature({
    geometry: new ol.geom.LineString(coords)
  });
  feature.set('color', '#ff0000');
  feature.set('distance', dist);
  feature.set('bearing', bearing);

  if (shapeDrawInstance && typeof shapeDrawInstance.addMeasurementFeature === 'function') {
    shapeDrawInstance.addMeasurementFeature(feature);
  }

  // Trigger info button click to open meerkat panel with this shape
  var infoBtn = document.getElementById('shape-info-btn');
  if (infoBtn) {
    infoBtn.click();
  }

  measureStartCoords = null;
  if (measureStartOverlay) measureStartOverlay.setPosition(undefined);
}

let losRunning = false;
let losDisplayXyz = '';

function clearLosLines() {
  if (losLayer && losLayer.getSource && typeof losLayer.getSource().clear === 'function') {
    losLayer.getSource().clear();
  }
}

function show_line_of_sight(lon, lat, z) {
  if (losRunning) {
    return;
  }
  const input = lon + '_' + lat + '_' + z;
  if (losDisplayXyz === input) {
    clearLosLines();
    losDisplayXyz = '';
    return;
  }
  losDisplayXyz = input;
  losRunning = true;
  const losLink = document.getElementById('los_link');
  const origLabel = losLink ? losLink.innerHTML : '';
  if (losLink) {
    losLink.classList.add('los-running');
    losLink.innerHTML = '通視模擬計算中…';
  }
  const url = window.appConfig.viewshed_url +
    '?x=' + Number(lon).toFixed(5) + '&y=' + Number(lat).toFixed(5) + '&z=' + Math.round(z);
  fetch(url, { cache: 'no-store' })
    .then(function (response) {
      if (!response.ok) {
        throw new Error('line of sight request failed');
      }
      return response.json();
    })
    .then(function (data) {
      if (data.ok !== true || !Array.isArray(data.rsp)) {
        console.warn('show_line_of_sight bad response', data);
        return;
      }
      clearLosLines();
      data.rsp.forEach(function (d) {
        const visible = d[0] === true;
        const end = d[1];
        if (!Array.isArray(end) || end.length < 2) {
          return;
        }
        mapApi.addPolyline(losLayerId, [
          [lon, lat],
          [Number(end[0]), Number(end[1])]
        ], { color: visible ? '#FF0000' : '#FF00FF', width: visible ? 2 : 1 });
      });
    })
    .catch(function (error) {
      console.warn('line of sight unavailable:', error.message);
    })
    .finally(function () {
      losRunning = false;
      const link = document.getElementById('los_link');
      if (link) {
        link.classList.remove('los-running');
        link.innerHTML = origLabel;
      }
    });
}


function loadPointDetails(pointId, lon, lat) {
  const url = window.appConfig.pointdata_url + '?id=' + encodeURIComponent(pointId);
  fetch(url, { cache: 'no-store' })
    .then(function (response) {
      if (!response.ok) {
        throw new Error('point detail request failed');
      }
      return response.json();
    })
    .then(function (points) {
      const point = Array.isArray(points) && points.length ? points[0] : null;
      if (!point) {
        return;
      }
      showPointPopup(point, lon, lat);
    })
    .catch(function (error) {
      console.warn('point detail unavailable:', error.message);
      showPointPopup({
        name: '點位資訊',
        story: '<br>無法載入詳細資料。'
      }, lon, lat);
    });
}

let lastFeatureClickTime = 0;

mapApi.onFeatureClick(markerLayerId, function (feature, event) {
  const pointId = feature.get('pointId');
  if (!pointId) {
    return;
  }
  lastFeatureClickTime = Date.now();

  const coordinate = ol.proj.toLonLat(feature.getGeometry().getCoordinates());
  loadPointDetails(pointId, coordinate[0], coordinate[1]);
});

function syncMarkerLabelState() {
  const markerLayerTarget = map.getLayers().getArray().find(function (layer) {
    return layer && layer.get('id') === markerLayerId;
  });
  if (!markerLayerTarget || !markerLayerTarget.getSource) {
    return;
  }

  const zoom = map.getView().getZoom();
  const effectiveShowLabel = markerLabelsEnabled && zoom >= 12;
  const source = markerLayerTarget.getSource();
  const features = source && typeof source.getSource === 'function' ? source.getSource().getFeatures() : source.getFeatures();
  features.forEach(function (feature) {
    feature.set('showLabel', effectiveShowLabel);
    feature.set('labelText', feature.get('title') || feature.get('labelText') || '');
    feature.changed();
  });
}

const labelToggleBtn = document.getElementById('marker-label-toggle-btn');
if (labelToggleBtn) {
  labelToggleBtn.addEventListener('click', function () {
    markerLabelsEnabled = !this.classList.contains('active');
    this.classList.toggle('active');
    this.classList.toggle('disable');
    syncMarkerLabelState();
  });
}

const trackToggleBtn = document.getElementById('track-toggle-btn');
if (trackToggleBtn) {
  trackToggleBtn.addEventListener('click', function () {
    const visible = mapApi.setLayerVisible('tracks', !this.classList.contains('active'));
    if (visible) {
      this.classList.toggle('active');
      this.classList.toggle('disable');
    }
  });
}

map.getView().on('change:resolution', function () {
  syncMarkerLabelState();
});

const filterMenuBtn = document.getElementById('filter-menu-btn');
const filterMenu = document.getElementById('filter-menu');
if (filterMenuBtn && filterMenu) {
  filterMenuBtn.addEventListener('click', function () {
    filterMenu.classList.toggle('hidden');
  });
  document.addEventListener('click', function (event) {
    if (!event.target.closest || !event.target.closest('#filter-menu-wrap')) {
      filterMenu.classList.add('hidden');
    }
  });
}

const markerFilterToggleBtns = document.querySelectorAll('.marker-filter-toggle');
markerFilterToggleBtns.forEach(function (btn) {
  btn.addEventListener('click', function () {
    const values = (this.dataset.values || this.dataset.value || '').split(',');
    if (!values.length) {
      return;
    }
    if (this.classList.contains('active')) {
      values.forEach(function (value) {
        markerFilterState.delete(value);
      });
      this.classList.remove('active');
      this.classList.add('disable');
    } else {
      values.forEach(function (value) {
        markerFilterState.add(value);
      });
      this.classList.add('active');
      this.classList.remove('disable');
    }
    refreshMarkerFilterState();
  });
});

let areaselectInstance = null;

function clickedOnShape(pixel) {
  const target = map.getLayers().getArray().find(function (layer) {
    return layer && layer.get('id') === selectionLayerId;
  });
  if (!target) {
    return false;
  }
  const hit = map.forEachFeatureAtPixel(pixel, function (candidate) {
    return candidate;
  }, {
    layerFilter: function (candidateLayer) {
      return candidateLayer === target;
    },
    hitTolerance: 6
  });
  return !!hit;
}

mapApi.onClick(function ({ lon, lat, event }) {
  closePointPopup();
  if (mapApi.shapeDrawActive) {
    return;
  }
  if (Date.now() - lastFeatureClickTime < 500) {
    return;
  }
  if (event && event.pixel && clickedOnShape(event.pixel)) {
    return;
  }
  if (areaselectInstance && typeof areaselectInstance.handleClick === 'function') {
    areaselectInstance.handleClick(lon, lat);
  }
});

mapApi.onContextMenu(function ({ lon, lat, event }) {
  closePointPopup();
  if (event) {
    const pixel = map.getEventPixel(event);
    const target = map.getLayers().getArray().find(function (layer) {
      return layer && layer.get('id') === areaselectLayerId;
    });
    if (target) {
      const hit = map.forEachFeatureAtPixel(pixel, function (candidate) {
        return candidate;
      }, {
        layerFilter: function (candidateLayer) {
          return candidateLayer === target;
        },
        hitTolerance: 6
      });
      if (hit && areaselectInstance && typeof areaselectInstance.exportPoints === 'function') {
        areaselectInstance.exportPoints();
        return;
      }
    }
  }
  showLocationInfo(lon, lat);
});

function bindBottomLayerSelect(selectId, layerId) {
  const select = document.getElementById(selectId);
  for (const source of Object.values(baseMapSources)) {
    const option = document.createElement('option');
    option.value = source.sourceId;
    option.textContent = source.icon + '  ' + source.label;
    select.appendChild(option);
  }

  select.addEventListener('change', function () {
    const source = mapSources[this.value];
    if (source) {
      mapApi.setTileLayerSource(layerId, source);
    }
  });
}

bindBottomLayerSelect('bottom-layer-1-select', 'bottom-1');
bindBottomLayerSelect('bottom-layer-2-select', 'bottom-2');
document.getElementById('bottom-layer-1-select').value = 'atis';
document.getElementById('bottom-layer-2-select').value = 'moi_osm_twmap';

const roadSelect = document.getElementById('road-layer-select');
for (const source of Object.values(roadSources)) {
  const option = document.createElement('option');
  option.value = source.sourceId;
  option.textContent = source.icon + '  ' + source.label;
  roadSelect.appendChild(option);
}
const noRoadOption = document.createElement('option');
noRoadOption.value = 'none';
noRoadOption.textContent = '無道路';
roadSelect.appendChild(noRoadOption);

roadSelect.addEventListener('change', function () {
  const source = roadSources[this.value];
  if (source) {
    mapApi.setTileLayerSource(roadLayerId, source);
  }
  mapApi.setLayerVisible(roadLayerId, this.value !== 'none');
});

roadSelect.value = 'nlsc_names';

function bindLayerOpacity(inputId, layerId) {
  const input = document.getElementById(inputId);
  input.addEventListener('input', function () {
    mapApi.setLayerOpacity(layerId, parseFloat(this.value));
  });
}

bindLayerOpacity('bottom-layer-2-opacity', 'bottom-2');

function populateSearchDatalist() {
  const datalist = document.getElementById('search-datalist');
  if (!datalist) {
    return;
  }
  datalist.textContent = '';
  const seen = new Set();
  for (const poi of poiIndex) {
    if (seen.has(poi.name)) {
      continue;
    }
    seen.add(poi.name);
    const option = document.createElement('option');
    option.value = poi.name;
    datalist.appendChild(option);
  }
}

function resolvePoi(query) {
  const q = String(query || '').trim();
  if (!q) {
    return null;
  }
  for (const poi of poiIndex) {
    if (poi.name === q || poi.alias === q) {
      return poi;
    }
  }
  const lower = q.toLowerCase();
  for (const poi of poiIndex) {
    if (poi.name && poi.name.toLowerCase() === lower) {
      return poi;
    }
    if (poi.alias && poi.alias.toLowerCase() === lower) {
      return poi;
    }
  }
  return null;
}

const gotoBtn = document.getElementById('search-btn');

function parseCoordinateInput(query) {
  // cadastral meter / jia
  const cm = query.match(/^cm:\s*(-?\d+\.?\d*)\s*,\s*(-?\d+\.?\d+)$/i);
  const cj = query.match(/^cj:\s*(-?\d+\.?\d*)\s*,\s*(-?\d+\.?\d+)$/i);

  // TWD97 TM2 with slash: X/Y
  const postw97 = query.match(/^([-\d]+)\s*\/\s*([-\d]+)$/);
  // WGS84 lat,lon with decimals: lat,lon or lat lon
  const posxy = query.match(/^(-?\d+\.?\d*)\s*[, ]\s*(-?\d+\.\d+)$/);
  // TWD67 TM2 integers only with comma/space: X,Y
  const postw67 = query.match(/^(-?\d+)\s*[, ]\s*(-?\d+)$/);

  if (cm) {
    return twProjections.twd672lonlat(twProjections.cad2twd67(cm[1], cm[2], 'm')[0], twProjections.cad2twd67(cm[1], cm[2], 'm')[1], 0);
  }
  if (cj) {
    return twProjections.twd672lonlat(twProjections.cad2twd67(cj[1], cj[2], 'j')[0], twProjections.cad2twd67(cj[1], cj[2], 'j')[1], 0);
  }
  if (postw97) {
    return twProjections.twd972lonlat(postw97[1], postw97[2], 0);
  }
  if (posxy) {
    // lat,lon order
    return { x: parseFloat(posxy[2]), y: parseFloat(posxy[1]) };
  }
  if (postw67) {
    return twProjections.twd672lonlat(postw67[1], postw67[2], 0);
  }
  return null;
}

gotoBtn.addEventListener('click', function () {
  const query = document.getElementById('search-input').value.trim();
  if (!query) {
    return;
  }

  const coord = parseCoordinateInput(query);
  if (coord) {
    const p = twProjections.isTaiwan(coord.y, coord.x);
    if (p === 0) {
      console.warn('not in Taiwan/Penghu range');
    } else {
      mapApi.setView([coord.x, coord.y], 14);
    }
    return;
  }

  const poi = resolvePoi(query);
  if (poi) {
    mapApi.setView([poi.lon, poi.lat], 14);
    if (poi.id && typeof loadPointDetails === 'function') {
      loadPointDetails(poi.id, poi.lon, poi.lat);
    }
    return;
  }

  if (typeof showmeerkat === 'function' && window.appConfig.poisearch_url) {
    showmeerkat(window.appConfig.poisearch_url + '?name=' + encodeURIComponent(query), { width: 600 });
  }
});

var shapeDrawInstance = new ShapeDraw4({
  mapApi: mapApi,
  selectionLayer: selectionLayer,
  selectionLayerId: selectionLayerId,
  drawTypeBtns: document.querySelectorAll('#draw-type .draw-type-toggle'),
  deleteBtn: document.getElementById('shape-delete-btn'),
  clearBtn: document.getElementById('shape-clear-btn'),
  infoBtn: document.getElementById('shape-info-btn')
});

areaselectInstance = new AreaSelect({
  mapApi: mapApi,
  layer: areaselectLayer,
  paramsEl: document.getElementById('params'),
  getGate: function () {
    if (mapApi.shapeDrawActive) {
      return true;
    }
    if (mapApi.lastShapeDrawnTime && Date.now() - mapApi.lastShapeDrawnTime < 500) {
      return true;
    }
    if (Date.now() - lastFeatureClickTime < 500) {
      return true;
    }
    return false;
  }
});

const gridSelect = document.getElementById('grid-select');
if (gridSelect) {
  const redrawGrid = function () {
    showGrid(gridSelect.value);
  };
  gridSelect.addEventListener('change', redrawGrid);
  map.on('moveend', redrawGrid);
  map.on('change:size', redrawGrid);
  setTimeout(redrawGrid, 0);
}

const rainfallSelect = document.getElementById('rainfall-select');
if (rainfallSelect) {
  rainfallSelect.addEventListener('change', function () {
    showCWBRainfall(this.value);
  });
  showCWBRainfall(rainfallSelect.value);
}

const coverageSelect = document.getElementById('coverage-select');
if (coverageSelect) {
  coverageSelect.addEventListener('change', function () {
    coverage_overlay(this.value);
  });
}

const VIEW_STORAGE_KEY = 'twmap4_view';

function saveCurrentView() {
  try {
    const center = ol.proj.toLonLat(map.getView().getCenter());
    const zoom = map.getView().getZoom();
    localStorage.setItem(VIEW_STORAGE_KEY, JSON.stringify({
      lon: center[0],
      lat: center[1],
      zoom: zoom
    }));
  } catch (e) {
    // localStorage unavailable
  }
}

map.on('moveend', saveCurrentView);

function gotoLandmark() {
  if (!poiDataReady) {
    setTimeout(gotoLandmark, 250);
    return;
  }
  const names = (window.appConfig && window.appConfig.feature_locations) ||
    ["三角錐山", "南二子山北峰", "敷島山", "大檜山", "武陵山", "佐久間山", "錐錐谷", "丹錐山", "霧頭山", "出雲山", "西巴杜蘭", "公山", "大分山"];
  const name = names[Math.floor(Math.random() * names.length)];
  const poi = typeof resolvePoi === 'function' ? resolvePoi(name) : null;
  if (poi && Number.isFinite(poi.lon) && Number.isFinite(poi.lat)) {
    mapApi.setView([poi.lon, poi.lat], 14);
    if (poi.id && typeof loadPointDetails === 'function') {
      loadPointDetails(poi.id, poi.lon, poi.lat);
    }
  } else {
    mapApi.setView(window.appConfig.default_center, window.appConfig.default_zoom);
  }
}

function gotoFeatureLocation() {
  if (!mapApi || !mapApi.setView || !navigator.geolocation) {
    gotoLandmark();
    return;
  }
  let got = 0;
  const done = function () {
    if (got) {
      return;
    }
    got = 1;
    if (!mapApi.__geoApplied) {
      gotoLandmark();
    }
  };
  navigator.geolocation.getCurrentPosition(
    function (position) {
      got = 1;
      mapApi.__geoApplied = 1;
      mapApi.setView([position.coords.longitude, position.coords.latitude], 14);
    },
    done,
    { timeout: 4000, maximumAge: 60000 }
  );
  setTimeout(done, 5000);
}

let restoredView = null;
try {
  const saved = localStorage.getItem(VIEW_STORAGE_KEY);
  if (saved) {
    const data = JSON.parse(saved);
    if (data && Number.isFinite(data.lon) && Number.isFinite(data.lat) && Number.isFinite(data.zoom)) {
      restoredView = data;
    }
  }
} catch (e) {
  restoredView = null;
}

if (restoredView) {
  mapApi.setView([restoredView.lon, restoredView.lat], restoredView.zoom);
} else if (new URLSearchParams(window.location.search).get('goto')) {
  // explicit URL target already applied as the initial view; do not override
  mapApi.setView(initialView.center, initialView.zoom);
} else {
  gotoFeatureLocation();
}

const fullscreenBtn = document.getElementById('fullscreen-btn');
if (fullscreenBtn) {
  fullscreenBtn.addEventListener('click', function () {
    if (!document.fullscreenElement) {
      document.documentElement.requestFullscreen().catch(function () {});
    } else {
      document.exitFullscreen();
    }
  });
  document.addEventListener('fullscreenchange', function () {
    var icon = fullscreenBtn.querySelector('i');
    if (icon) {
      icon.className = document.fullscreenElement ? 'fa fa-compress' : 'fa fa-arrows-alt';
    }
  });
}

const geolocateBtn = document.getElementById('geolocate-btn');
if (geolocateBtn) {
  geolocateBtn.addEventListener('click', function () {
    if (!mapApi || !mapApi.setView || !navigator.geolocation) {
      console.warn('geolocation unavailable');
      return;
    }
    navigator.geolocation.getCurrentPosition(
      function (position) {
        mapApi.setView([position.coords.longitude, position.coords.latitude], 14);
      },
      function (err) {
        console.warn('geolocation failed:', err && err.message ? err.message : err);
      },
      { timeout: 8000, maximumAge: 60000 }
    );
  });
}
