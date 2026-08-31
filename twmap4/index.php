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
        <input id="search-input" type="text" placeholder="搜尋山頭、地標或座標" list="search-datalist" autocomplete="off" />
        <datalist id="search-datalist"></datalist>
        <button id="search-btn" type="button">到</button>
      </div>

      <button id="marker-label-toggle-btn" type="button" class="active" title="三角點名稱標籤">標籤</button>

      <button id="track-toggle-btn" type="button" class="active" title="山友登山軌跡 (z10-19)">行跡</button>

      <div id="filter-menu-wrap">
        <button id="filter-menu-btn" type="button" title="篩選點位類型">篩選</button>
        <div id="filter-menu" class="hidden">
          <button type="button" class="marker-filter-toggle active" data-values="peak_1st">一等</button>
          <button type="button" class="marker-filter-toggle active" data-values="peak_2nd">二等</button>
          <button type="button" class="marker-filter-toggle active" data-values="peak_3rd">三等</button>
          <button type="button" class="marker-filter-toggle active" data-values="forest_point,forest_unknown">森林</button>
          <button type="button" class="marker-filter-toggle active" data-values="nameless_peak">山峰</button>
          <button type="button" class="marker-filter-toggle active" data-values="independent_peak">獨立峰</button>
          <button type="button" class="marker-filter-toggle active" data-values="mountain_hut">山屋</button>
          <button type="button" class="marker-filter-toggle active" data-values="station">駐在所</button>
          <button type="button" class="marker-filter-toggle active" data-values="police_box">警察</button>
          <button type="button" class="marker-filter-toggle active" data-values="tribal_station">蕃務</button>
          <button type="button" class="marker-filter-toggle active" data-values="water_source">水源</button>
          <button type="button" class="marker-filter-toggle active" data-values="hot_spring">溫泉</button>
          <button type="button" class="marker-filter-toggle active" data-values="point,shelter,giant_tree,rock,waterfall,stream,lake,ruins,valley,camp,dry_ravine,water_pool,old_village,steps,cliff,bridge,hut,terrain_point,workstation">其他</button>
        </div>
      </div>

      <label for="rainfall-select">雨量</label>
      <select id="rainfall-select" aria-label="雨量疊圖">
        <option value="none" selected>雨量圖</option>
        <option value="o2d">前日</option>
        <option value="o1d">昨日</option>
        <option value="now">今日</option>
        <option value="f12h">未來12h</option>
        <option value="f24h">未來24h</option>
      </select>

      <label for="coverage-select">訊號</label>
      <select id="coverage-select" aria-label="訊號涵蓋圖">
        <option value="none" selected>訊號</option>
        <option value="cht">cht4+5G</option>
        <option value="twn">twn4+5G</option>
        <option value="fet">fet4+5G</option>
      </select>

      <label for="grid-select">格線</label>
      <select id="grid-select" aria-label="格線疊圖">
        <option value="TWD67" selected>TWD67</option>
        <option value="TWD67PH">TWD67澎湖</option>
        <option value="TWD67_EXT">TWD67 EXT</option>
        <option value="TWD97">TWD97</option>
        <option value="TWD97PH">TWD97澎湖</option>
        <option value="TWD97_EXT">TWD97 EXT</option>
        <option value="WGS84">經緯度</option>
        <option value="None">無格線</option>
      </select>
    </div>

    <div id="map-wrap">
      <div id="map" aria-label="地圖"></div>
      <div id="point-popup" class="hidden" aria-live="polite"></div>
      <div id="buttons" aria-label="繪圖工具">
        <div id="draw-type" class="draw-type">
          <button type="button" class="draw-type-toggle" data-type="Polygon" title="多邊形">多</button>
          <button type="button" class="draw-type-toggle" data-type="Circle" title="圓形">圓</button>
          <button type="button" class="draw-type-toggle" data-type="LineString" title="線條">線</button>
        </div>
        <span class="buttons-sep"></span>
        <button id="shape-delete-btn" type="button" title="刪除所選形狀"><i class="fa fa-times"></i></button>
        <button id="shape-clear-btn" type="button" title="刪除全部形狀"><i class="fa fa-times-circle"></i></button>
        <button id="shape-info-btn" type="button" title="顯示所選形狀資訊"><i class="fa fa-info"></i></button>
      </div>
      <div id="params" aria-label="出圖範圍"></div>
      <div id="msg" aria-live="off" hidden></div>
      <div id="map-attribution" aria-label="圖資資訊"></div>
      <button id="geolocate-btn" type="button" title="定位到我" aria-label="定位到我"><i class="fa fa-crosshairs"></i></button>
      <div id="layer-controls">
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
      </div>
    </div>

    <div id="meerkat-wrap" class="hidden" aria-label="TWMap 面板">
      <div class="meerkat-header">
        <span class="meerkat-title">TWMap</span>
        <button id="meerkat-close" type="button" title="關閉面板" aria-label="關閉面板"><i class="fa fa-times"></i></button>
      </div>
      <iframe id="meerkatiframe" title="TWMap 面板" src="about:blank"></iframe>
    </div>

    <input type="hidden" id="tags" />
    <button id="goto" type="button" hidden></button>

    <div id="areaselect-modal-overlay" class="hidden"></div>
    <div id="inputtitleform" class="hidden">
      <br>請輸入地圖標題: <br><br><input id="inputtitle" type="text" size="20" />
      <br>
      <select id="datum">
        <option value="TWD67">TWD67 紅色框</option>
        <option value="TWD97" selected>TWD97 綠色框</option>
      </select>
      <br>
      <input type="button" id="inputtitlebtn" value="送出" />
      <input type="button" id="inputtitlebtn2" value="取消" />
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
      hut: <?php echo filemtime(__DIR__ . '/icons/hut.png'); ?>,
      shelter: <?php echo filemtime(__DIR__ . '/icons/shelter.png'); ?>,
      station: <?php echo filemtime(__DIR__ . '/icons/station.png'); ?>,
      police_box: <?php echo filemtime(__DIR__ . '/icons/police_box.png'); ?>,
      watch_station: <?php echo filemtime(__DIR__ . '/icons/watch_station.png'); ?>,
      tribal_station: <?php echo filemtime(__DIR__ . '/icons/tribal_station.png'); ?>,
      water_source: <?php echo filemtime(__DIR__ . '/icons/water_source.png'); ?>,
      hot_spring: <?php echo filemtime(__DIR__ . '/icons/hot_spring.png'); ?>,
      waterfall: <?php echo filemtime(__DIR__ . '/icons/waterfall.png'); ?>,
      stream: <?php echo filemtime(__DIR__ . '/icons/stream.png'); ?>,
      lake: <?php echo filemtime(__DIR__ . '/icons/lake.png'); ?>,
      rock: <?php echo filemtime(__DIR__ . '/icons/rock.png'); ?>,
      ruins: <?php echo filemtime(__DIR__ . '/icons/ruins.png'); ?>,
      terrain_point: <?php echo filemtime(__DIR__ . '/icons/terrain_point.png'); ?>,
      valley: <?php echo filemtime(__DIR__ . '/icons/valley.png'); ?>,
      camp: <?php echo filemtime(__DIR__ . '/icons/camp.png'); ?>,
      dry_ravine: <?php echo filemtime(__DIR__ . '/icons/dry_ravine.png'); ?>,
      water_pool: <?php echo filemtime(__DIR__ . '/icons/water_pool.png'); ?>,
      old_village: <?php echo filemtime(__DIR__ . '/icons/old_village.png'); ?>,
      steps: <?php echo filemtime(__DIR__ . '/icons/steps.png'); ?>,
      cliff: <?php echo filemtime(__DIR__ . '/icons/cliff.png'); ?>,
      bridge: <?php echo filemtime(__DIR__ . '/icons/bridge.png'); ?>,
      workstation: <?php echo filemtime(__DIR__ . '/icons/workstation.png'); ?>,
      point: <?php echo filemtime(__DIR__ . '/icons/point.png'); ?>
    };
  </script>

  <script src="https://cdn.jsdelivr.net/npm/ol@10.3.1/dist/ol.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/proj4js/2.15.0/proj4.js"></script>
  <script src="js/projections.js?v=<?php echo filemtime(__DIR__ . '/js/projections.js'); ?>"></script>
  <script src="js/mapApi.js?v=<?php echo filemtime(__DIR__ . '/js/mapApi.js'); ?>"></script>
  <script src="js/olAdapter.js?v=<?php echo filemtime(__DIR__ . '/js/olAdapter.js'); ?>"></script>
  <script src="js/layers.js?v=<?php echo filemtime(__DIR__ . '/js/layers.js'); ?>"></script>
  <script src="js/overlays.js?v=<?php echo filemtime(__DIR__ . '/js/overlays.js'); ?>"></script>
  <script src="js/shapedraw4.js?v=<?php echo filemtime(__DIR__ . '/js/shapedraw4.js'); ?>"></script>
  <script src="js/areaselect.js?v=<?php echo filemtime(__DIR__ . '/js/areaselect.js'); ?>"></script>
  <script src="js/app.js?v=<?php echo filemtime(__DIR__ . '/js/app.js'); ?>"></script>
  <script src="js/mapinfo.js?v=<?php echo filemtime(__DIR__ . '/js/mapinfo.js'); ?>"></script>
  <script src="js/meerkat.js?v=<?php echo filemtime(__DIR__ . '/js/meerkat.js'); ?>"></script>
  <script src="js/coverage.js?v=<?php echo filemtime(__DIR__ . '/js/coverage.js'); ?>"></script>
</body>
</html>
