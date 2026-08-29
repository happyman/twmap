<?php
require_once __DIR__ . "/config.inc.php";
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>TWMap4</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/ol@10.3.1/ol.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css" />
  <link rel="stylesheet" href="css/twmap4.css?v=<?php echo filemtime(__DIR__ . '/css/twmap4.css'); ?>" />
</head>
<body>
  <div id="app-shell">
    <div id="toolbar">
      <div id="search-box">
        <input id="search-input" type="text" placeholder="搜尋山頭、地標或座標" />
        <button id="search-btn" type="button">到</button>
      </div>

      <label for="bottom-layer-1-select">第一層</label>
      <select id="bottom-layer-1-select" aria-label="第一層圖資">
      </select>

      <label for="bottom-layer-2-opacity">透明度</label>
      <input id="bottom-layer-2-opacity" type="range" min="0" max="1" step="0.05" value="0.7" aria-label="第二層透明度" />

      <label for="bottom-layer-2-select">第二層</label>
      <select id="bottom-layer-2-select" aria-label="第二層圖資">
      </select>

      <label for="road-layer-select">道路</label>
      <select id="road-layer-select" aria-label="道路圖層">
      </select>

      <button id="select-area-btn" type="button">選區</button>
    </div>

    <div id="map-wrap">
      <div id="map" aria-label="地圖"></div>
      <div id="point-popup" class="hidden" aria-live="polite"></div>
    </div>
  </div>

  <script>
    window.appConfig = <?php echo json_encode($CONFIG, JSON_UNESCAPED_UNICODE); ?>;
    window.twmap4IconVersions = {
      peak_1st: <?php echo filemtime(__DIR__ . '/icons/peak_1st.png'); ?>,
      peak_2nd: <?php echo filemtime(__DIR__ . '/icons/peak_2nd.png'); ?>,
      peak_3rd: <?php echo filemtime(__DIR__ . '/icons/peak_3rd.png'); ?>,
      forest_point: <?php echo filemtime(__DIR__ . '/icons/forest_point.png'); ?>,
      forest_unknown: <?php echo filemtime(__DIR__ . '/icons/forest_unknown.png'); ?>,
      giant_tree: <?php echo filemtime(__DIR__ . '/icons/giant_tree.png'); ?>,
      independent_peak: <?php echo filemtime(__DIR__ . '/icons/independent_peak.png'); ?>,
      nameless_peak: <?php echo filemtime(__DIR__ . '/icons/nameless_peak.png'); ?>,
      mountain_hut: <?php echo filemtime(__DIR__ . '/icons/mountain_hut.png'); ?>,
      shelter: <?php echo filemtime(__DIR__ . '/icons/shelter.png'); ?>,
      water_source: <?php echo filemtime(__DIR__ . '/icons/water_source.png'); ?>,
      hot_spring: <?php echo filemtime(__DIR__ . '/icons/hot_spring.png'); ?>,
      waterfall: <?php echo filemtime(__DIR__ . '/icons/waterfall.png'); ?>,
      stream: <?php echo filemtime(__DIR__ . '/icons/stream.png'); ?>,
      lake: <?php echo filemtime(__DIR__ . '/icons/lake.png'); ?>,
      rock: <?php echo filemtime(__DIR__ . '/icons/rock.png'); ?>,
      point: <?php echo filemtime(__DIR__ . '/icons/point.png'); ?>
    };
  </script>

  <script src="https://cdn.jsdelivr.net/npm/ol@10.3.1/dist/ol.js"></script>
  <script src="js/mapApi.js?v=<?php echo filemtime(__DIR__ . '/js/mapApi.js'); ?>"></script>
  <script src="js/olAdapter.js?v=<?php echo filemtime(__DIR__ . '/js/olAdapter.js'); ?>"></script>
  <script src="js/layers.js?v=<?php echo filemtime(__DIR__ . '/js/layers.js'); ?>"></script>
  <script src="js/app.js?v=<?php echo filemtime(__DIR__ . '/js/app.js'); ?>"></script>
</body>
</html>
