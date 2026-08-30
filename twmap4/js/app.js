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
const pointPopup = document.getElementById('point-popup');
const pointPopupOverlay = new ol.Overlay({
  element: pointPopup,
  positioning: 'bottom-center',
  offset: [0, -16],
  stopEvent: true
});
map.addOverlay(pointPopupOverlay);

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
  'point'
]);
const allMarkerFeatures = [];

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

mapApi.addTileLayer({ id: 'bottom-1', ...baseMapSources.osm, visible: true, zIndex: 0 });
mapApi.addTileLayer({ id: 'bottom-2', ...mapSources.nlsc_emap, visible: true, opacity: 0.7, zIndex: 1 });
mapApi.addTileLayer({
  id: roadLayerId,
  ...mapSources.nlsc_names,
  visible: true,
  zIndex: 20
});

mapApi.addVectorLayer({ id: markerLayerId, visible: true });
mapApi.addVectorLayer({ id: selectionLayerId, visible: true });

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
  '岩石': 'rock'
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
      }
      rebuildVisibleMarkerFeatures();
      syncMarkerLabelState();
      console.log('loaded point data:', points.length);
    })
    .catch(function (error) {
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
      rows.push(
        '台灣 TWD67 TM2: ' + Math.round(p67.x) + ',' + Math.round(p67.y),
        '台灣 TWD97 TM2: ' + Math.round(p97.x) + '/' + Math.round(p97.y)
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
    link('//mc.basecamp.tw/#' + zoom + '/' + (wgsLat.toFixed(4)) + '/' + (wgsLon.toFixed(4)), 'fa-exchange', '地圖對照器', '地圖對照'),
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

  pointPopup.innerHTML = [
    '<div class="popup-header">' + title +
      ' <a class="popup-permalink" href="' + permalink(lon, lat, zoom) + '" target="_blank" title="複製此位置連結"><i class="fa fa-link"></i></a>' +
      '</div>',
    pointMeta ? '<div class="popup-meta">' + pointMeta + '</div>' : '',
    coordBlock(lon, lat),
    '<div class="popup-story">' + summary + '</div>',
    adminLink,
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
      if (data && data.ok === true && data.rsp) {
        const lines = [];
        if (typeof data.rsp.elevation === 'number' && data.rsp.elevation > -1000) {
          lines.push('高度: ' + Math.round(data.rsp.elevation) + 'M');
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
    })
    .catch(function (error) {
      console.warn('elev unavailable:', error.message);
    });
}

function renderLocationPopup(lon, lat, zoom, rows) {
  if (!pointPopup) {
    return;
  }
  pointPopup.innerHTML = [
    '<div class="popup-header">位置資訊</div>',
    coordBlock(lon, lat),
    rows.join(''),
    popupLinks(lon, lat, zoom)
  ].join('');
  pointPopupOverlay.setPosition(ol.proj.fromLonLat([lon, lat]));
  pointPopup.classList.remove('hidden');
}

function showLocationInfo(lon, lat) {
  const zoom = Math.round(map.getView().getZoom()) || 0;
  const radius = (20 - zoom) * 10 - 10;
  const rows = [];

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
        }
        renderLocationPopup(lon, lat, zoom, rows);
      })
      .catch(function (error) {
        console.warn('waypoints unavailable:', error.message);
        fetchElevAndAdmin(lon, lat, rows).then(function () {
          renderLocationPopup(lon, lat, zoom, rows);
        });
      });
  } else {
    fetchElevAndAdmin(lon, lat, rows).then(function () {
      renderLocationPopup(lon, lat, zoom, rows);
    });
  }
}

function closePointPopup() {
  if (pointPopup) {
    pointPopup.classList.add('hidden');
    pointPopupOverlay.setPosition(undefined);
    pointPopup.innerHTML = '';
  }
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

const labelToggle = document.getElementById('marker-label-toggle');
if (labelToggle) {
  labelToggle.addEventListener('change', function () {
    markerLabelsEnabled = this.checked;
    syncMarkerLabelState();
  });
}

map.getView().on('change:resolution', function () {
  syncMarkerLabelState();
});

const markerFilterControls = document.querySelectorAll('.marker-filter');
markerFilterControls.forEach(function (input) {
  input.addEventListener('change', function () {
    const value = this.value;
    if (this.checked) {
      markerFilterState.add(value);
    } else {
      markerFilterState.delete(value);
    }
    refreshMarkerFilterState();
  });
});

mapApi.onClick(function ({ lon, lat }) {
  closePointPopup();
  if (Date.now() - lastFeatureClickTime < 500) {
    return;
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
document.getElementById('bottom-layer-1-select').value = 'osm';
document.getElementById('bottom-layer-2-select').value = 'nlsc_emap';

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

const gotoBtn = document.getElementById('search-btn');
gotoBtn.addEventListener('click', function () {
  const query = document.getElementById('search-input').value.trim();
  if (!query) {
    return;
  }

  const byCoord = query.match(/^([-+]?\d+(?:\.\d+)?)\s*,\s*([-+]?\d+(?:\.\d+)?)$/);
  if (byCoord) {
    const lon = parseFloat(byCoord[1]);
    const lat = parseFloat(byCoord[2]);
    mapApi.setView([lon, lat], 14);
    return;
  }

  fetch(window.appConfig.geocodercache_url + '?q=' + encodeURIComponent(query))
    .then(function (response) { return response.json(); })
    .then(function (data) {
      if (data && data.lat && data.lon) {
        mapApi.setView([data.lon, data.lat], 14);
      }
    })
    .catch(function () {
      console.warn('search fallback: no geocoder result');
    });
});

const selectAreaBtn = document.getElementById('select-area-btn');
selectAreaBtn.addEventListener('click', function () {
  mapApi.addDrawSelection();
});

mapApi.setView(window.appConfig.default_center, window.appConfig.default_zoom);
