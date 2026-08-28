const olMapApiAdapter = {
  init(options) {
    if (!window.ol) {
      throw new Error('OpenLayers is not loaded.');
    }

    const map = new ol.Map({
      target: options.target,
      layers: [],
      view: new ol.View({
        center: ol.proj.fromLonLat(options.center || [121.5654, 25.0330]),
        zoom: options.zoom === undefined ? 8 : options.zoom
      })
    });

    this.map = map;
    this.layers = new Map();
    return map;
  },

  getMapInstance() {
    return this.map;
  },

  setView(center, zoom) {
    const view = this.map.getView();
    view.animate({
      center: ol.proj.fromLonLat(center),
      zoom: zoom === undefined ? view.getZoom() : zoom,
      duration: 300
    });
  },

  fitBounds(bounds) {
    const extent = ol.extent.boundingExtent([
      ol.proj.fromLonLat([bounds[0][0], bounds[0][1]]),
      ol.proj.fromLonLat([bounds[1][0], bounds[1][1]])
    ]);
    this.map.getView().fit(extent, { padding: [20, 20, 20, 20] });
  },

  addTileLayer(layerConfig) {
    const source = this.createTileSource(layerConfig);

    const mapLayer = new ol.layer.Tile({
      source,
      visible: layerConfig.visible !== false,
      opacity: layerConfig.opacity === undefined ? 1 : layerConfig.opacity,
      zIndex: layerConfig.zIndex
    });

    mapLayer.set('id', layerConfig.id);
    this.map.addLayer(mapLayer);
    this.layers.set(layerConfig.id, mapLayer);
    return mapLayer;
  },

  createTileSource(layerConfig) {
    const sourceOptions = {
      attributions: layerConfig.attribution || '',
      minZoom: layerConfig.minZoom,
      maxZoom: layerConfig.maxZoom
    };
    if (layerConfig.tileUrlFunction) {
      sourceOptions.tileUrlFunction = layerConfig.tileUrlFunction;
    } else {
      sourceOptions.url = layerConfig.url;
    }
    return new ol.source.XYZ(sourceOptions);
  },

  setTileLayerSource(layerId, layerConfig) {
    const layer = this.layers.get(layerId);
    if (!layer) {
      return false;
    }

    layer.setSource(this.createTileSource(layerConfig));
    layer.set('sourceId', layerConfig.sourceId || layerId);
    return true;
  },

  setLayerOpacity(layerId, opacity) {
    const layer = this.layers.get(layerId);
    if (!layer || typeof layer.setOpacity !== 'function') {
      return false;
    }

    layer.setOpacity(Math.max(0, Math.min(1, opacity)));
    return true;
  },

  setLayerVisible(layerId, visible) {
    const layer = this.layers.get(layerId);
    if (!layer || typeof layer.setVisible !== 'function') {
      return false;
    }

    layer.setVisible(visible);
    return true;
  },

  addVectorLayer(layerConfig) {
    const vectorSource = new ol.source.Vector();
    const layer = new ol.layer.Vector({
      source: vectorSource,
      visible: layerConfig.visible !== false
    });

    layer.set('id', layerConfig.id);
    this.map.addLayer(layer);
    this.layers.set(layerConfig.id, layer);
    return layer;
  },

  addFeature(layerId, feature) {
    const layer = this.layers.get(layerId);
    if (!layer || !layer.getSource) {
      return null;
    }
    layer.getSource().addFeature(feature);
    return feature;
  },

  addMarker(layerId, lon, lat, options = {}) {
    const feature = new ol.Feature({
      geometry: new ol.geom.Point(ol.proj.fromLonLat([lon, lat]))
    });

    const style = new ol.style.Style({
      image: new ol.style.Circle({
        radius: options.radius || 6,
        fill: new ol.style.Fill({ color: options.color || '#ff0000' }),
        stroke: new ol.style.Stroke({ color: '#ffffff', width: 2 })
      })
    });

    feature.setStyle(style);
    this.addFeature(layerId, feature);
    return feature;
  },

  addPolygon(layerId, coordinates, options = {}) {
    const ring = coordinates.map(([lon, lat]) => ol.proj.fromLonLat([lon, lat]));
    if (ring.length > 0 &&
      (ring[0][0] !== ring[ring.length - 1][0] ||
        ring[0][1] !== ring[ring.length - 1][1])) {
      ring.push(ring[0]);
    }
    const feature = new ol.Feature({
      geometry: new ol.geom.Polygon([ring])
    });

    feature.setStyle(new ol.style.Style({
      stroke: new ol.style.Stroke({
        color: options.strokeColor || '#ffcc00',
        width: options.strokeWidth || 2
      }),
      fill: new ol.style.Fill({
        color: options.fillColor || 'rgba(255, 204, 0, 0.2)'
      })
    }));

    this.addFeature(layerId, feature);
    return feature;
  },

  addPolyline(layerId, coordinates, options = {}) {
    const line = coordinates.map(([lon, lat]) => ol.proj.fromLonLat([lon, lat]));
    const feature = new ol.Feature({
      geometry: new ol.geom.LineString(line)
    });

    feature.setStyle(new ol.style.Style({
      stroke: new ol.style.Stroke({
        color: options.color || '#00a0ff',
        width: options.width || 3
      })
    }));

    this.addFeature(layerId, feature);
    return feature;
  },

  onClick(handler) {
    this.map.on('click', function (event) {
      const coordinate = ol.proj.toLonLat(event.coordinate);
      handler({ lon: coordinate[0], lat: coordinate[1], event });
    });
  },

  onMoveEnd(handler) {
    this.map.on('moveend', handler);
  },

  addDrawSelection(handler) {
    if (this.selectionDraw) {
      this.map.removeInteraction(this.selectionDraw);
    }

    const selectionLayer = this.layers.get('selection');
    const source = selectionLayer ? selectionLayer.getSource() : new ol.source.Vector();
    const draw = new ol.interaction.Draw({
      type: 'Polygon',
      source
    });

    this.map.addInteraction(draw);
    draw.on('drawend', function (event) {
      const coords = event.feature.getGeometry().getCoordinates()[0];
      const ring = coords.map(([x, y]) => ol.proj.toLonLat([x, y]));
      if (handler) {
        handler(ring);
      }
    });

    this.selectionDraw = draw;
    return draw;
  }
};
