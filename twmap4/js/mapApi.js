function createMapApi(adapter) {
  return {
    init(options) {
      return adapter.init(options);
    },

    setView(center, zoom) {
      return adapter.setView(center, zoom);
    },

    fitBounds(bounds) {
      return adapter.fitBounds(bounds);
    },

    addTileLayer(layer) {
      return adapter.addTileLayer(layer);
    },

    setTileLayerSource(layerId, layerConfig) {
      return adapter.setTileLayerSource(layerId, layerConfig);
    },

    setLayerOpacity(layerId, opacity) {
      return adapter.setLayerOpacity(layerId, opacity);
    },

    setLayerVisible(layerId, visible) {
      return adapter.setLayerVisible(layerId, visible);
    },

    addImageLayer(layer) {
      return adapter.addImageLayer(layer);
    },

    addVectorLayer(layer) {
      return adapter.addVectorLayer(layer);
    },

    addFeature(layerId, feature) {
      return adapter.addFeature(layerId, feature);
    },

    addMarker(layerId, lon, lat, options = {}) {
      return adapter.addMarker(layerId, lon, lat, options);
    },

    addPolygon(layerId, coordinates, options = {}) {
      return adapter.addPolygon(layerId, coordinates, options);
    },

    addPolyline(layerId, coordinates, options = {}) {
      return adapter.addPolyline(layerId, coordinates, options);
    },

    onClick(handler) {
      return adapter.onClick(handler);
    },

    onFeatureClick(layerId, handler) {
      return adapter.onFeatureClick(layerId, handler);
    },

    onMoveEnd(handler) {
      return adapter.onMoveEnd(handler);
    },

    addDrawSelection(handler) {
      return adapter.addDrawSelection(handler);
    },

    getMapInstance() {
      return adapter.getMapInstance();
    }
  };
}
