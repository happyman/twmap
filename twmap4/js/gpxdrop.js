(function () {
  var gpxLayers = [];
  var dropContainer = null;

  function showPanel(e) {
    e.stopPropagation();
    e.preventDefault();
    if (dropContainer) dropContainer.style.display = 'block';
    return false;
  }

  function hidePanel() {
    if (dropContainer) dropContainer.style.display = 'none';
  }

  function loadGpx(gpxString) {
    if (!window.map) return;
    var format = new ol.format.GPX();
    var features;
    try {
      features = format.readFeatures(gpxString, {
        featureProjection: map.getView().getProjection()
      });
    } catch (err) {
      console.error('GPX parse error:', err);
      return;
    }
    if (!features || !features.length) return;

    var trackFeatures = [];
    var wptFeatures = [];
    features.forEach(function (f) {
      var geom = f.getGeometry();
      if (geom && geom.getType() === 'Point') {
        wptFeatures.push(f);
      } else {
        trackFeatures.push(f);
      }
    });

    var source = new ol.source.Vector();
    var layers = [];

    if (trackFeatures.length) {
      var trackSource = new ol.source.Vector({ features: trackFeatures });
      var trackLayer = new ol.layer.Vector({
        source: trackSource,
        style: new ol.style.Style({
          stroke: new ol.style.Stroke({ color: '#ff0000', width: 3 })
        }),
        zIndex: 40
      });
      map.addLayer(trackLayer);
      layers.push(trackLayer);
      gpxLayers.push(trackLayer);
    }

    if (wptFeatures.length) {
      var wptSource = new ol.source.Vector({ features: wptFeatures });
      var wptLayer = new ol.layer.Vector({
        source: wptSource,
        style: new ol.style.Style({
          image: new ol.style.Icon({
            src: 'https://dayanuyim.github.io/maps/images/sym/128/Waypoint.png',
            scale: 32 / 128
          })
        }),
        zIndex: 41
      });
      map.addLayer(wptLayer);
      layers.push(wptLayer);
      gpxLayers.push(wptLayer);
    }

    var allFeatures = trackFeatures.concat(wptFeatures);
    if (allFeatures.length) {
      var extent = ol.extent.createEmpty();
      allFeatures.forEach(function (f) {
        ol.extent.extend(extent, f.getGeometry().getExtent());
      });
      map.getView().fit(extent, { padding: [50, 50, 50, 50], maxZoom: 16 });
    }
  }

  function handleDrop(e) {
    e.preventDefault();
    e.stopPropagation();
    hidePanel();

    var files = e.dataTransfer.files;
    if (files.length) {
      var file = files[0];
      var reader = new FileReader();
      reader.onload = function (ev) {
        loadGpx(ev.target.result);
      };
      reader.onerror = function () {
        console.error('GPX file read failed');
      };
      reader.readAsText(file);
    } else {
      var plainText = e.dataTransfer.getData('text/plain');
      if (plainText && plainText.indexOf('<gpx') !== -1) {
        loadGpx(plainText);
      }
    }
    return false;
  }

  document.addEventListener('DOMContentLoaded', function () {
    dropContainer = document.getElementById('drop-container');
    var mapViewport = document.getElementById('map');
    if (!mapViewport || !dropContainer) return;

    mapViewport.addEventListener('dragenter', showPanel, false);
    dropContainer.addEventListener('dragover', showPanel, false);
    dropContainer.addEventListener('drop', handleDrop, false);
    dropContainer.addEventListener('dragleave', hidePanel, false);
  });
})();
