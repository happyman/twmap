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
  <link rel="stylesheet" href="css/twmap4.css" />
</head>
<body>
  <div id="app-shell">
    <div id="toolbar">
      <div id="search-box">
        <input id="search-input" type="text" placeholder="搜尋山頭、地標或座標" />
        <button id="search-btn" type="button">到</button>
      </div>

      <select id="basemap-select" aria-label="底圖切換">
        <option value="osm">OpenStreetMap</option>
        <option value="nlsc">NLSC</option>
        <option value="rudy">Rudy</option>
      </select>

      <button id="select-area-btn" type="button">選區</button>
    </div>

    <div id="map" aria-label="地圖"></div>
  </div>

  <script>
    window.appConfig = <?php echo json_encode($CONFIG, JSON_UNESCAPED_UNICODE); ?>;
  </script>

  <script src="https://cdn.jsdelivr.net/npm/ol@10.3.1/dist/ol.js"></script>
  <script src="js/mapApi.js"></script>
  <script src="js/olAdapter.js"></script>
  <script src="js/app.js"></script>
</body>
</html>
