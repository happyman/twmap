const mapApi = createMapApi(olMapApiAdapter);
const map = mapApi.init({
  target: 'map',
  center: window.appConfig.default_center,
  zoom: window.appConfig.default_zoom
});

const markerLayerId = 'markers';
const selectionLayerId = 'selection';
const roadLayerId = 'road';
const pointPopup = document.getElementById('point-popup');

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

const initialMarkers = [
  [121.5654, 25.0330, '#ff0000'],
  [121.5390, 25.0474, '#00aa00'],
  [121.6038, 25.0167, '#0000ff']
];

for (const [lon, lat, color] of initialMarkers) {
  mapApi.addMarker(markerLayerId, lon, lat, { color });
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
        mapApi.addMarker(markerLayerId, lon, lat, {
          color: pointColor(point),
          title: point.name || '',
          pointId: point.id,
          iconName: getIconName(point),
          pointType: point.type || '',
          pointClass: point.class || ''
        });
      }
      console.log('loaded point data:', points.length);
    })
    .catch(function (error) {
      console.warn('pointdata unavailable:', error.message);
    });
}

loadPointData();

function showPointPopup(point, lon, lat) {
  if (!pointPopup) {
    return;
  }

  const title = point.name || '未命名點位';
  const summary = point.story || '<br>未提供詳細資料';
  const pointMeta = [
    '經度: ' + Number(lon).toFixed(5),
    '緯度: ' + Number(lat).toFixed(5),
    point.type ? '類型: ' + point.type : '',
    point.class ? '類別: ' + point.class : ''
  ].filter(Boolean).join(' · ');

  pointPopup.innerHTML = [
    '<div class="popup-header">' + title + '</div>',
    '<div class="popup-meta">' + pointMeta + '</div>',
    summary
  ].join('');
  pointPopup.classList.remove('hidden');
}

function closePointPopup() {
  if (pointPopup) {
    pointPopup.classList.add('hidden');
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

mapApi.onFeatureClick(markerLayerId, function (feature, event) {
  const pointId = feature.get('pointId');
  if (!pointId) {
    return;
  }

  const coordinate = ol.proj.toLonLat(feature.getGeometry().getCoordinates());
  loadPointDetails(pointId, coordinate[0], coordinate[1]);
});

mapApi.onClick(function ({ lon, lat }) {
  console.log('map click:', lon, lat);
  if (!pointPopup) {
    return;
  }
  const markerHit = document.getElementById('point-popup');
  if (markerHit && !markerHit.classList.contains('hidden')) {
    closePointPopup();
  }
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
