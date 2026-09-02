(function () {
  var currentTrack = null;
  var loading = false;

  window.showmapgpx = function (mid, marker_desc, additional_marker_desc, zoom) {
    if (loading) return;
    if (!mid) return;

    if (currentTrack && currentTrack.mid == mid) {
      map.removeLayer(currentTrack.layer);
      currentTrack = null;
      return;
    }

    if (currentTrack) {
      map.removeLayer(currentTrack.layer);
      currentTrack = null;
    }

    loading = true;
    var url = window.appConfig.getkml_url + '?mid=' + mid + '&type=gpx';

    fetch(url)
      .then(function (r) { return r.text(); })
      .then(function (gpxText) {
        var format = new ol.format.GPX();
        var features = format.readFeatures(gpxText, {
          featureProjection: map.getView().getProjection()
        });

        var source = new ol.source.Vector({ features: features });
        var layer = new ol.layer.Vector({
          source: source,
          style: new ol.style.Style({
            stroke: new ol.style.Stroke({ color: '#fffe00', width: 3 })
          }),
          zIndex: 40
        });
        map.addLayer(layer);

        currentTrack = { mid: mid, layer: layer };

        if (zoom && features.length > 0) {
          map.getView().fit(source.getExtent(), { padding: [50, 50, 50, 50], maxZoom: 16 });
        }

        loading = false;
      })
      .catch(function (err) {
        console.warn('showmapgpx failed:', err);
        loading = false;
      });
  };
})();
