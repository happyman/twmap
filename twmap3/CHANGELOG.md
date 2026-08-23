# 地圖瀏覽器 v4.38 更新

## 繪圖引擎遷移

- 新增 `js/shadowdraw.js`：以 Terra Draw 1.32.3 + terra-draw-google-maps-adapter 1.6.1
  取代已棄用的 google.maps.drawing DrawingManager；`ShapesMap` 對外 API 與
  localStorage shapes JSON 格式完全不變，main.js / loadgpx.js 的消費端無須修改
- 支援選取／矩形／圓形／多邊形／線段模式；圓形以 64 段近似環儲存，
  存檔時由 ring 重新反推中心與半徑（拖曳編輯後仍正確）
- 繪圖進行中，地圖 click / dblclick / rightclick 不再誤觸發既有行為
  （main.js 三處 listener 加 guard、functions.js `addremove_polygon()` 入口防護）
- 舊實作 `js/shapedraw.js` 保留為備援，未移除
- index.php：Maps API libraries 以 marker 取代 drawing；
  新增 terra-draw 兩筆 pinned CDN script

## 前端載入瘦身（head 阻塞 JS 約 2.3MB → 1.25MB）

- jQuery 3.7.0（未壓縮）→ [code.jquery.com](https://code.jquery.com) `jquery-3.7.1.min.js`
- jQuery UI 1.13.2（未壓縮）→ `jquery-ui.min.js` 1.13.3，redmond 主題 CSS 同步升至 1.13.3
- 移除 jsts.min.js + javascript.util.min.js（-585KB；delaunay 按鈕已註解，
  唯一觸發路徑不可達，屬死碼依賴）
- loadgpx.js（121KB）改為首次用到才載入：main.js 新增 `withGPXParser()`
  懶載入 helper（含佇列防競態），包裝 `showmapgpx()` ajax success 與
  `loadGpx()` 兩處 GPXParser 呼叫點

## 其他

- OverlappingMarkerSpiderfier 升級至 1.0.3（js/oms.min.js）
- Maps API libraries 加入 marker
- VERSION 4.37 → 4.38
