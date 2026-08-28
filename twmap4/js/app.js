const mapApi = createMapApi(olMapApiAdapter);
const map = mapApi.init({
  target: 'map',
  center: window.appConfig.default_center,
  zoom: window.appConfig.default_zoom
});

const markerLayerId = 'markers';
const selectionLayerId = 'selection';

mapApi.addTileLayer({ id: 'bottom-1', ...mapSources.osm, visible: true, zIndex: 0 });
mapApi.addTileLayer({ id: 'bottom-2', ...mapSources.nlsc_emap, visible: true, opacity: 0.7, zIndex: 1 });
mapApi.addTileLayer({ id: 'overlay-1', ...mapSources.rudy, visible: false, zIndex: 10 });

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

function bindBottomLayerSelect(selectId, layerId) {
  const select = document.getElementById(selectId);
  for (const source of Object.values(mapSources)) {
    const option = document.createElement('option');
    option.value = source.sourceId;
    option.textContent = source.label;
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
