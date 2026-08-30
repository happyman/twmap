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
      visible: layerConfig.visible !== false,
      opacity: 1,
      zIndex: 100
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

    const source = layer.getSource();
    if (source && typeof source.getSource === 'function' && source.getSource() instanceof ol.source.Vector) {
      source.getSource().addFeature(feature);
    } else if (source && typeof source.addFeature === 'function') {
      source.addFeature(feature);
    }
    return feature;
  },

  addMarker(layerId, lon, lat, options = {}) {
    const feature = new ol.Feature({
      geometry: new ol.geom.Point(ol.proj.fromLonLat([lon, lat]))
    });
    feature.setProperties({
      title: options.title || '',
      labelText: options.labelText || options.title || '',
      showLabel: options.showLabel !== false,
      pointId: options.pointId,
      iconName: options.iconName || 'point',
      pointType: options.pointType || '',
      pointClass: options.pointClass || ''
    });

    feature.setStyle((featureInstance) => this.createMarkerStyle(featureInstance.getProperties()));
    this.addFeature(layerId, feature);
    return feature;
  },

  createMarkerStyle(options = {}) {
    const color = options.color || '#888888';
    const iconName = options.iconName || 'point';
    const radius = options.radius || 10;
    const labelText = String(options.labelText || options.title || '');
    const showLabel = options.showLabel !== false;

    const iconStyle = this.getIconStyle(iconName, color, radius, labelText, showLabel);
    if (iconStyle) {
      return iconStyle;
    }

    return new ol.style.Style({
      image: new ol.style.Circle({
        radius,
        fill: new ol.style.Fill({ color }),
        stroke: new ol.style.Stroke({ color: '#ffffff', width: 2 })
      })
    });
  },

  getIconStyle(iconName, color, radius, labelText = '', showLabel = true) {
    const path = this.getLegacyIconPath(iconName);
    if (!path) {
      return null;
    }

    const label = showLabel && labelText ? new ol.style.Text({
      text: labelText,
      font: '600 11px Arial, sans-serif',
      fill: new ol.style.Fill({ color: '#0f172a' }),
      stroke: new ol.style.Stroke({ color: '#ffffff', width: 3 }),
      offsetY: -Math.max(18, radius + 8),
      textAlign: 'center',
      textBaseline: 'bottom'
    }) : undefined;

    return new ol.style.Style({
      image: new ol.style.Icon({
        src: path,
        scale: 1,
        opacity: 1,
        color: '#ffffff',
        anchor: [0.5, 1],
        anchorXUnits: 'fraction',
        anchorYUnits: 'fraction'
      }),
      text: label,
      zIndex: 100
    });
  },

  getLegacyIconPath(iconName) {
    const fallback = {
      peak_1st: 'icons/peak_1st.png',
      peak_2nd: 'icons/peak_2nd.png',
      peak_3rd: 'icons/peak_3rd.png',
      forest_point: 'icons/forest_point.png',
      forest_unknown: 'icons/forest_unknown.png',
      giant_tree: 'icons/giant_tree.png',
      independent_peak: 'icons/independent_peak.png',
      nameless_peak: 'icons/nameless_peak.png',
      mountain_hut: 'icons/mountain_hut.png',
      shelter: 'icons/shelter.png',
      station: 'icons/station.png',
      police_box: 'icons/police_box.png',
      watch_station: 'icons/watch_station.png',
      tribal_station: 'icons/tribal_station.png',
      water_source: 'icons/water_source.png',
      hot_spring: 'icons/hot_spring.png',
      waterfall: 'icons/waterfall.png',
      stream: 'icons/stream.png',
      lake: 'icons/lake.png',
      rock: 'icons/rock.png',
      point: 'icons/point.png'
    };

    const normalized = (iconName || 'point').toString().trim();
    const iconPath = fallback[normalized] || 'icons/point.png';
    const versions = window.twmap4IconVersions || {};
    const version = versions[normalized] || 1;
    return iconPath + '?v=' + version;
  },

  getIconDataUrl(iconName, color, size) {
    try {
      const canvas = document.createElement('canvas');
      canvas.width = size;
      canvas.height = size;
      const ctx = canvas.getContext('2d');

      switch (iconName) {
        case 'peak_1st':
          this.drawPeakIcon(ctx, size, color, 'I');
          break;
        case 'peak_2nd':
          this.drawPeakIcon(ctx, size, color, 'II');
          break;
        case 'peak_3rd':
          this.drawPeakIcon(ctx, size, color, 'III');
          break;
        case 'forest_point':
          this.drawForestIcon(ctx, size, color);
          break;
        case 'forest_unknown':
          this.drawForestUnknownIcon(ctx, size, color);
          break;
        case 'independent_peak':
          this.drawIndependentPeakIcon(ctx, size, color);
          break;
        case 'mountain_hut':
          this.drawHutIcon(ctx, size, color);
          break;
        case 'water_source':
          this.drawWaterIcon(ctx, size, color);
          break;
        default:
          this.drawPointIcon(ctx, size, color);
      }

      return canvas.toDataURL('image/png');
    } catch (e) {
      console.warn('Failed to create marker icon:', iconName, e);
      return null;
    }
  },

  drawPeakIcon(ctx, size, color, label) {
    const center = size / 2;
    const radius = size * 0.35;
    
    // White background
    ctx.fillStyle = '#ffffff';
    ctx.beginPath();
    ctx.arc(center, center, radius, 0, Math.PI * 2);
    ctx.fill();
    
    // Colored circle
    ctx.fillStyle = color;
    ctx.beginPath();
    ctx.arc(center, center, radius * 0.9, 0, Math.PI * 2);
    ctx.fill();
    
    // Black outline
    ctx.strokeStyle = '#000000';
    ctx.lineWidth = Math.max(1, size * 0.08);
    ctx.beginPath();
    ctx.arc(center, center, radius, 0, Math.PI * 2);
    ctx.stroke();

    // Label
    ctx.fillStyle = '#ffffff';
    ctx.font = 'bold ' + Math.floor(size * 0.5) + 'px Arial';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText(label, center, center);
  },

  drawForestIcon(ctx, size, color) {
    const center = size / 2;
    
    // White background
    ctx.fillStyle = '#ffffff';
    ctx.beginPath();
    ctx.moveTo(center, size * 0.15);
    ctx.lineTo(center + size * 0.3, size * 0.5);
    ctx.lineTo(center - size * 0.3, size * 0.5);
    ctx.closePath();
    ctx.fill();
    ctx.beginPath();
    ctx.arc(center, size * 0.55, size * 0.18, 0, Math.PI * 2);
    ctx.fill();
    
    // Colored shape
    ctx.fillStyle = color;
    ctx.beginPath();
    ctx.moveTo(center, size * 0.18);
    ctx.lineTo(center + size * 0.27, size * 0.47);
    ctx.lineTo(center - size * 0.27, size * 0.47);
    ctx.closePath();
    ctx.fill();
    ctx.beginPath();
    ctx.arc(center, size * 0.55, size * 0.14, 0, Math.PI * 2);
    ctx.fill();
    
    // Black outline
    ctx.strokeStyle = '#000000';
    ctx.lineWidth = Math.max(1, size * 0.08);
    ctx.beginPath();
    ctx.moveTo(center, size * 0.15);
    ctx.lineTo(center + size * 0.3, size * 0.5);
    ctx.lineTo(center - size * 0.3, size * 0.5);
    ctx.closePath();
    ctx.stroke();
    ctx.beginPath();
    ctx.arc(center, size * 0.55, size * 0.18, 0, Math.PI * 2);
    ctx.stroke();
  },

  drawForestUnknownIcon(ctx, size, color) {
    const center = size / 2;
    
    // White background
    ctx.fillStyle = '#ffffff';
    ctx.beginPath();
    ctx.moveTo(center, size * 0.15);
    ctx.lineTo(center + size * 0.27, size * 0.48);
    ctx.lineTo(center - size * 0.27, size * 0.48);
    ctx.closePath();
    ctx.fill();
    
    // Colored shape
    ctx.fillStyle = color;
    ctx.globalAlpha = 0.85;
    ctx.beginPath();
    ctx.moveTo(center, size * 0.18);
    ctx.lineTo(center + size * 0.24, size * 0.45);
    ctx.lineTo(center - size * 0.24, size * 0.45);
    ctx.closePath();
    ctx.fill();
    ctx.globalAlpha = 1;
    
    // Black outline
    ctx.strokeStyle = '#000000';
    ctx.lineWidth = Math.max(1, size * 0.08);
    ctx.beginPath();
    ctx.moveTo(center, size * 0.15);
    ctx.lineTo(center + size * 0.27, size * 0.48);
    ctx.lineTo(center - size * 0.27, size * 0.48);
    ctx.closePath();
    ctx.stroke();

    // Question mark label
    ctx.fillStyle = '#ffffff';
    ctx.font = 'bold ' + Math.floor(size * 0.4) + 'px Arial';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText('?', center, size * 0.55);
  },

  drawIndependentPeakIcon(ctx, size, color) {
    const center = size / 2;
    
    // White background triangle
    ctx.fillStyle = '#ffffff';
    ctx.beginPath();
    ctx.moveTo(center, size * 0.12);
    ctx.lineTo(center + size * 0.32, size * 0.55);
    ctx.lineTo(center - size * 0.32, size * 0.55);
    ctx.closePath();
    ctx.fill();
    
    // Colored triangle
    ctx.fillStyle = color;
    ctx.beginPath();
    ctx.moveTo(center, size * 0.16);
    ctx.lineTo(center + size * 0.29, size * 0.52);
    ctx.lineTo(center - size * 0.29, size * 0.52);
    ctx.closePath();
    ctx.fill();
    
    // Gold star at bottom
    ctx.fillStyle = '#FFD700';
    ctx.beginPath();
    ctx.arc(center, size * 0.6, size * 0.12, 0, Math.PI * 2);
    ctx.fill();
    
    // Black outline
    ctx.strokeStyle = '#000000';
    ctx.lineWidth = Math.max(1, size * 0.08);
    ctx.beginPath();
    ctx.moveTo(center, size * 0.12);
    ctx.lineTo(center + size * 0.32, size * 0.55);
    ctx.lineTo(center - size * 0.32, size * 0.55);
    ctx.closePath();
    ctx.stroke();
    ctx.beginPath();
    ctx.arc(center, size * 0.6, size * 0.12, 0, Math.PI * 2);
    ctx.stroke();
  },

  drawHutIcon(ctx, size, color) {
    const center = size / 2;
    
    // White background
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(center - size * 0.28, center - size * 0.2, size * 0.56, size * 0.35);
    ctx.beginPath();
    ctx.moveTo(center - size * 0.28, center - size * 0.2);
    ctx.lineTo(center, center - size * 0.38);
    ctx.lineTo(center + size * 0.28, center - size * 0.2);
    ctx.closePath();
    ctx.fill();
    
    // Colored shape
    ctx.fillStyle = color;
    ctx.fillRect(center - size * 0.24, center - size * 0.16, size * 0.48, size * 0.28);
    ctx.beginPath();
    ctx.moveTo(center - size * 0.24, center - size * 0.16);
    ctx.lineTo(center, center - size * 0.32);
    ctx.lineTo(center + size * 0.24, center - size * 0.16);
    ctx.closePath();
    ctx.fill();
    
    // Black outline
    ctx.strokeStyle = '#000000';
    ctx.lineWidth = Math.max(1, size * 0.08);
    ctx.strokeRect(center - size * 0.28, center - size * 0.2, size * 0.56, size * 0.35);
    ctx.beginPath();
    ctx.moveTo(center - size * 0.28, center - size * 0.2);
    ctx.lineTo(center, center - size * 0.38);
    ctx.lineTo(center + size * 0.28, center - size * 0.2);
    ctx.closePath();
    ctx.stroke();
  },

  drawWaterIcon(ctx, size, color) {
    const center = size / 2;
    const radius = size * 0.32;
    
    // White background
    ctx.fillStyle = '#ffffff';
    ctx.beginPath();
    ctx.arc(center, center, radius, 0, Math.PI * 2);
    ctx.fill();
    
    // Colored water drop
    ctx.fillStyle = color;
    ctx.globalAlpha = 0.8;
    ctx.beginPath();
    ctx.arc(center, center, radius * 0.7, 0, Math.PI * 2);
    ctx.fill();
    ctx.globalAlpha = 1;

    // Label
    ctx.fillStyle = '#ffffff';
    ctx.font = 'bold ' + Math.floor(size * 0.6) + 'px Arial';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText('~', center, center);
    
    // Black outline
    ctx.strokeStyle = '#000000';
    ctx.lineWidth = Math.max(1, size * 0.1);
    ctx.beginPath();
    ctx.arc(center, center, radius, 0, Math.PI * 2);
    ctx.stroke();
  },

  drawPointIcon(ctx, size, color) {
    const center = size / 2;
    const radius = size * 0.32;
    
    // White background circle
    ctx.fillStyle = '#ffffff';
    ctx.beginPath();
    ctx.arc(center, center, radius, 0, Math.PI * 2);
    ctx.fill();
    
    // Colored inner circle
    ctx.fillStyle = color;
    ctx.beginPath();
    ctx.arc(center, center, radius * 0.85, 0, Math.PI * 2);
    ctx.fill();

    // Black outline for contrast
    ctx.strokeStyle = '#000000';
    ctx.lineWidth = Math.max(1, size * 0.1);
    ctx.beginPath();
    ctx.arc(center, center, radius, 0, Math.PI * 2);
    ctx.stroke();
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

  onFeatureClick(layerId, handler) {
    const layer = this.layers.get(layerId);
    if (!layer) {
      return false;
    }

    this.map.on('click', (event) => {
      const feature = this.map.forEachFeatureAtPixel(event.pixel, function (candidate) {
        if (candidate && candidate.get('features') && candidate.get('features').length) {
          const candidates = candidate.get('features');
          return candidates.length === 1 ? candidates[0] : candidate;
        }
        return candidate;
      }, {
        layerFilter: function (candidateLayer) {
          return candidateLayer === layer;
        },
        hitTolerance: 6
      });

      if (feature && typeof handler === 'function') {
        const resolvedFeature = feature.get && feature.get('features') && feature.get('features').length === 1
          ? feature.get('features')[0]
          : feature;
        handler(resolvedFeature, event);
      }
    });

    return true;
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
