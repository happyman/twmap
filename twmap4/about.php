<!DOCTYPE html>
<html lang="zh-Hant">
<head>
  <meta charset="UTF-8">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <title>關於地圖瀏覽器 v<?= @trim(file_get_contents(__DIR__ . '/VERSION')) ?></title>
  <style>
    body {font-family: Arial, sans-serif; max-width: 600px; margin: 2rem auto; line-height: 1.6;}
    h2 {text-align: center;}
    .instructions {margin: 1rem 0; padding: 1rem; background: #f3f4f6; border-radius: 6px;}
    .instructions li {margin-bottom: 0.5rem;}
  </style>
</head>
<body>
  <h2>關於地圖瀏覽器 v<?= @trim(file_get_contents(__DIR__ . '/VERSION')) ?></h2>

  <div class="instructions">
    <strong>功能說明：</strong>
    <ul>
      <li>搜尋山頭、地標或座標：輸入關鍵字後點「到」</li>
      <li>繪製形狀：使用繪圖工具（多邊形/圓形/線段/矩形）</li>
      <li>全螢幕：點右上方全螢幕圖示進入/離開全螢幕模式</li>
      <li>右鍵功能：選取圖形後可右鍵匯出 KML</li>
      <li>地圖捲動：使用滑鼠捲輪或捖曳移動畫面</li>
    </ul>

    <strong>資訊：</strong>
    <ul>
      <li>使用 OpenLayers 10.3.1 + OpenStreetMap 圖層</li>
      <li>座標參考：TWD67 / TWD97</li>
      <li>版本：v<?= @trim(file_get_contents(__DIR__ . '/VERSION')) ?></li>
    </ul>
  </div>
</body>
</html>