const GRID_TW_BOUNDS = { minLon: 119.8, minLat: 21.8, maxLon: 123.0, maxLat: 25.7 };
const GRID_PH_BOUNDS = { minLon: 119.2, minLat: 23.15, maxLon: 119.75, maxLat: 23.75 };

function gridLayerRef() {
  const layers = map.getLayers().getArray();
  for (const layer of layers) {
    if (layer && layer.get('id') === 'grid') {
      return layer;
    }
  }
  const layer = mapApi.addVectorLayer({ id: 'grid', visible: false, zIndex: 30 });
  return layer;
}

function toViewCoord(lon, lat) {
  return ol.proj.fromLonLat([lon, lat]);
}

function drawGridFeature(source, lineFrom, lineTo, color) {
  const main = new ol.Feature({
    geometry: new ol.geom.LineString([lineFrom, lineTo])
  });
  main.setStyle(new ol.style.Style({
    stroke: new ol.style.Stroke({
      color: color,
      width: 1
    })
  }));
  source.addFeature(main);
}

function drawGridLabel(source, lon, lat, text, color) {
  const label = new ol.Feature({
    geometry: new ol.geom.Point(toViewCoord(lon, lat))
  });
  label.setStyle(new ol.style.Style({
    text: new ol.style.Text({
      text: String(text),
      font: '12px Arial, sans-serif',
      fill: new ol.style.Fill({ color: color }),
      stroke: new ol.style.Stroke({ color: 'rgba(255, 255, 255, 0.9)', width: 3 }),
      offsetY: -2
    })
  }));
  source.addFeature(label);
}

function niceStep(raw) {
  const pow = Math.pow(10, Math.floor(Math.log(raw) / Math.LN10));
  const ratio = raw / pow;
  if (ratio < 1.5) {
    return pow;
  }
  if (ratio < 3) {
    return 2 * pow;
  }
  if (ratio < 7) {
    return 5 * pow;
  }
  return 10 * pow;
}

function formatDegree(value, step) {
  if (step >= 1) {
    return Math.round(value) + '°';
  }
  return parseFloat(value.toFixed(3)) + '°';
}

function drawGraticule(source, viewport, color) {
  const spanLon = viewport[2] - viewport[0];
  const spanLat = viewport[3] - viewport[1];
  const step = niceStep(Math.max(spanLon, spanLat) / 14);

  const startLon = Math.floor(viewport[0] / step) * step;
  const endLon = Math.ceil(viewport[2] / step) * step;
  const startLat = Math.floor(viewport[1] / step) * step;
  const endLat = Math.ceil(viewport[3] / step) * step;

  for (let lon = startLon; lon <= endLon; lon += step) {
    const from = toViewCoord(lon, viewport[1]);
    const to = toViewCoord(lon, viewport[3]);
    drawGridFeature(source, from, to, color);
    drawGridLabel(source, lon + step * 0.06, viewport[1] + spanLat * 0.03, formatDegree(lon, step), color);
  }
  for (let lat = startLat; lat <= endLat; lat += step) {
    const from = toViewCoord(viewport[0], lat);
    const to = toViewCoord(viewport[2], lat);
    drawGridFeature(source, from, to, color);
    drawGridLabel(source, viewport[0] + spanLon * 0.015, lat + step * 0.25, formatDegree(lat, step), color);
  }
}

function showGrid(grid_type) {
  const layer = gridLayerRef();
  if (!layer || !layer.getSource) {
    return;
  }
  const source = layer.getSource();
  source.clear();

  if (!grid_type || grid_type === 'None') {
    layer.setVisible(false);
    return;
  }

  const size = map.getSize();
  if (!size) {
    layer.setVisible(true);
    return;
  }
  const viewport = ol.proj.transformExtent(
    map.getView().calculateExtent(size),
    'EPSG:3857',
    'EPSG:4326'
  );

  layer.setVisible(true);

  if (grid_type === 'WGS84') {
    drawGraticule(source, viewport, '#000000');
    return;
  }

  const is97 = grid_type.indexOf('TWD97') === 0;
  const ph = grid_type === 'TWD67PH' || grid_type === 'TWD97PH' ? 1 : 0;
  const db = ph ? GRID_PH_BOUNDS : GRID_TW_BOUNDS;

  const minLon = Math.max(viewport[0], db.minLon);
  const minLat = Math.max(viewport[1], db.minLat);
  const maxLon = Math.min(viewport[2], db.maxLon);
  const maxLat = Math.min(viewport[3], db.maxLat);
  if (minLon >= maxLon || minLat >= maxLat) {
    return;
  }

  const getblock = is97 ? twProjections.lonlat_getblock97 : twProjections.lonlat_getblock;
  const toTwd = is97 ? twProjections.lonlat2twd97 : twProjections.lonlat2twd67;
  const toLonLat = is97 ? twProjections.twd972lonlat : twProjections.twd672lonlat;

  const sw = getblock(minLon, minLat, ph, 100);
  const ne = getblock(maxLon, maxLat, ph, 100);
  const minxx = sw[0].x;
  const maxxx = ne[1].x;
  const minyy = sw[1].y;
  const maxyy = ne[0].y;

  const swT = toTwd(minxx, minyy, ph);
  const neT = toTwd(maxxx, maxyy, ph);

  const zoom = map.getView().getZoom() || 0;
  let xstep = 1000;
  let ystep = 1000;
  const gridColor = 'black';
  let showlabel = 1;
  let adjusty = 0;
  let adjustx = 1;
  let sstep;

  if (zoom < 9) {
    sstep = 25000;
    xstep = sstep;
    ystep = sstep;
    showlabel = 0;
  } else if (zoom >= 9 && zoom <= 12) {
    sstep = 10000;
    xstep = sstep;
    ystep = sstep;
    showlabel = 1;
    adjusty = 200;
    adjustx = 2;
  } else if (zoom === 13) {
    adjusty = 200;
    adjustx = 2;
  } else if (zoom === 14) {
    adjusty = 120;
    adjustx = 2;
  } else if (zoom === 15) {
    adjusty = 50;
    adjustx = 1;
  } else {
    adjusty = 30;
    adjustx = 1;
  }

  if (grid_type === 'TWD67_EXT' || grid_type === 'TWD97_EXT') {
    if (zoom === 16) {
      xstep = 200;
      ystep = 200;
      adjusty = 30;
      adjustx = 1;
    } else if (zoom >= 17 && zoom <= 18) {
      xstep = 100;
      ystep = 100;
      adjusty = 10;
      adjustx = 1;
    } else if (zoom === 19) {
      xstep = 100;
      ystep = 100;
      adjusty = 5;
      adjustx = 1;
    }
  }

  const startx = Math.floor(swT.x / xstep) * xstep;
  const starty = Math.floor(swT.y / ystep) * ystep;
  const endx = Math.ceil(neT.x / xstep) * xstep;
  const endy = Math.ceil(neT.y / ystep) * ystep;

  for (let y = starty; y <= endy; y += ystep) {
    const p = toLonLat(startx, y, ph);
    const p1 = toLonLat(endx, y, ph);
    const from = toViewCoord(p.x, p.y);
    const to = toViewCoord(p1.x, p1.y);
    drawGridFeature(source, from, to, gridColor);
    if (showlabel) {
      const lp = toLonLat(startx + xstep, y + adjusty, ph);
      drawGridLabel(source, lp.x, lp.y, Math.round(y / 1000), gridColor);
    }
  }

  for (let x = startx; x <= endx; x += xstep) {
    const p = toLonLat(x, starty, ph);
    const p1 = toLonLat(x, endy, ph);
    const from = toViewCoord(p.x, p.y);
    const to = toViewCoord(p1.x, p1.y);
    drawGridFeature(source, from, to, gridColor);
    if (showlabel) {
      const lp = toLonLat(x, starty + ystep * adjustx, ph);
      drawGridLabel(source, lp.x, lp.y, Math.round(x / 1000), gridColor);
    }
  }
}

let rainfallTerm = 'none';
let rainfallLoading = false;

function rainfallLayerRef() {
  const layers = map.getLayers().getArray();
  for (const layer of layers) {
    if (layer && layer.get('id') === 'rainfall') {
      return layer;
    }
  }
  const layer = mapApi.addImageLayer({ id: 'rainfall', visible: false, opacity: 0.5, zIndex: 15 });
  return layer;
}

function parseRainfallKml(text) {
  const doc = new DOMParser().parseFromString(text, 'text/xml');
  const overlay = doc.getElementsByTagName('GroundOverlay')[0];
  if (!overlay) {
    return null;
  }
  const icon = overlay.getElementsByTagName('Icon')[0];
  const href = icon ? icon.getElementsByTagName('href')[0] : null;
  const box = overlay.getElementsByTagName('LatLonBox')[0];
  if (!href || !box) {
    return null;
  }
  const url = (href.textContent || '').trim();
  if (!url) {
    return null;
  }
  const north = parseFloat((box.getElementsByTagName('north')[0] || { textContent: '' }).textContent);
  const south = parseFloat((box.getElementsByTagName('south')[0] || { textContent: '' }).textContent);
  const east = parseFloat((box.getElementsByTagName('east')[0] || { textContent: '' }).textContent);
  const west = parseFloat((box.getElementsByTagName('west')[0] || { textContent: '' }).textContent);
  if (!isFinite(north) || !isFinite(south) || !isFinite(east) || !isFinite(west)) {
    return null;
  }
  return { url, extent: [west, south, east, north] };
}

function showCWBRainfall(term) {
  if (term === rainfallTerm && rainfallLoading === false) {
    return;
  }
  rainfallTerm = term;
  const layer = rainfallLayerRef();

  if (!term || term === 'none') {
    layer.setVisible(false);
    return;
  }

  const url = window.appConfig.rainkml_url + '?term=' + encodeURIComponent(term);
  rainfallLoading = true;
  fetch(url, { cache: 'no-store' })
    .then(function (response) {
      if (!response.ok) {
        throw new Error('rainkml request failed');
      }
      return response.text();
    })
    .then(function (text) {
      const info = parseRainfallKml(text);
      if (!info) {
        throw new Error('rainkml parse failed');
      }
      const source = new ol.source.ImageStatic({
        url: info.url,
        imageExtent: info.extent,
        projection: 'EPSG:4326'
      });
      layer.setSource(source);
      layer.setVisible(true);
    })
    .catch(function (error) {
      console.warn('rainfall overlay unavailable:', error.message);
    })
    .finally(function () {
      rainfallLoading = false;
    });
}

if (typeof globalThis !== 'undefined') {
  globalThis.showGrid = showGrid;
  globalThis.showCWBRainfall = showCWBRainfall;
}