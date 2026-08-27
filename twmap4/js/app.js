const mapApi = createMapApi(olMapApiAdapter);
const map = mapApi.init({
  target: 'map',
  center: window.appConfig.default_center,
  zoom: window.appConfig.default_zoom
});

const baseLayers = {
  osm: {
    id: 'osm',
    name: 'OSM',
    type: 'tile',
    url: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
    visible: true
  },
  nlsc: {
    id: 'nlsc',
    name: 'NLSC',
    type: 'tile',
    url: 'https://wmts.nlsc.gov.tw/wmts/EMAP5/default/EPSG:3857/{z}/{y}/{x}',
    visible: false
  },
  rudy: {
    id: 'rudy',
    name: 'Rudy',
    type: 'tile',
    url: 'https://tile.happyman.idv.tw/map/rudy/{z}/{x}/{y}.png',
    visible: false
  }
};

const markerLayerId = 'markers';
const selectionLayerId = 'selection';

for (const key of Object.keys(baseLayers)) {
  mapApi.addTileLayer(baseLayers[key]);
}

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

mapApi.onClick(function ({ lon, lat }) {
  console.log('map click:', lon, lat);
});

const basemapSelect = document.getElementById('basemap-select');
basemapSelect.addEventListener('change', function () {
  const selected = this.value;
  const layers = map.getLayers();

  layers.forEach(function (layer) {
    const id = layer.get('id');
    if (id && typeof baseLayers[id] !== 'undefined') {
      layer.setVisible(id === selected);
    }
  });
});

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
  mapApi.addDrawSelection(function (coords) {
    mapApi.addPolygon(selectionLayerId, coords.map(([lon, lat]) => [lon, lat]), {
      strokeColor: '#00ff88',
      fillColor: 'rgba(0, 255, 136, 0.18)'
    });
  });
});

mapApi.setView(window.appConfig.default_center, window.appConfig.default_zoom);
