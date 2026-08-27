# TWMap4 Agent Handoff

更新日期：2026-08-28

## 目標

將既有的 `twmap3` 地圖瀏覽器改造成以 OpenLayers 為底層的新版，放在 `twmap4/`。目前工作仍在 scaffold/架構階段，尚未完成 twmap3 的功能移植。

重要決策：不保留 Google Maps API 相容層。若需要抽象，只保留以 app 功能為導向的 map API；目前只實作 OpenLayers adapter。

## 已完成

### twmap4 初始檔案

- `twmap4/index.php`
  - 載入 OpenLayers 10.3.1 CSS/JS、twmap4 CSS，以及 map API、OpenLayers adapter、app。
  - PHP 載入 `config.inc.php`，將 `$CONFIG` 輸出為 `window.appConfig`。
  - 目前 UI 只有搜尋、底圖切換、選區按鈕和地圖容器。
- `twmap4/config.inc.php`
  - 複製既有地圖產生器 API endpoint 設定。
  - 加入 `default_center` 與 `default_zoom`。
  - 注意：這是新建的簡化設定，尚未加入 twmap3 的 login/session bootstrap。
- `twmap4/js/mapApi.js`
  - `createMapApi(adapter)` 封裝 app 需要的功能：初始化、視角、圖層、feature、marker、polygon、polyline、click、move end、draw selection。
- `twmap4/js/olAdapter.js`
  - 以 OpenLayers 實作 map API。
  - 支援 XYZ tile layer、vector layer、marker、polygon、polyline、點擊事件、Polygon draw。
- `twmap4/js/app.js`
  - 建立地圖。
  - 目前加入 OSM、NLSC、Rudy 三個示範底圖。
  - 加入示範 markers、座標搜尋、geocoder fetch、選區繪製。
- `twmap4/css/twmap4.css`
  - 最小版 toolbar/map layout。

### Grunt

`Gruntfile.js` 已新增：

- `rsync.twmap4`：將 `twmap4` 複製至 `dist/`，結果為 `dist/twmap4`。
- default task 執行 `rsync:twmap4`。
- `phplint` 檢查 `twmap4/**/*.php`。
- `twmap4-dist` task：只執行 `rsync:twmap4`。

使用：

```bash
grant twmap4-dist
```

注意：目前 `twmap4` 沒有加入 `useminPrepare`、`usemin`、`filerev`、`uglify` 或 `jshint`，因為它使用新的簡單結構，先以原樣同步為主。之後若需要 production bundling，再另行設計，不要直接套用 twmap3 的 legacy pipeline。

## 已驗證

已執行：

```bash
php -l twmap4/index.php
php -l twmap4/config.inc.php
node -e "const cfg=require('./Gruntfile.js'); console.log('Gruntfile loaded')"
```

結果：PHP 無 syntax error，Gruntfile 可載入。

尚未驗證：

- 瀏覽器中 OpenLayers 地圖是否能實際載入。
- `grunt twmap4-dist` 是否可在目前環境完成 rsync。
- geocoder API 實際 response schema。
- 所有 twmap3 feature 的功能 parity。

## twmap3 功能移植範圍

需要從 `twmap3` 逐項移植：

1. 多底圖切換與透明度。
2. 前景圖層：道路名、等高線、GPX track、歷史圖層等。
3. POI、三角點、山峰與 labels。
4. KML/GPX 載入與顯示。
5. 搜尋、geocoder、座標定位。
6. 雨量與電信覆蓋 image overlays。
7. 選區、TWD67/TWD97 grid 計算、地圖產生器整合。
8. elevation、viewshed、waypoints、KML export。
9. login/session、admin、point editing 等整合。

關鍵來源：

- `twmap3/index.php`：HTML shell、Google Maps script、全域 endpoint variables、控制項。
- `twmap3/config.inc.php`：後端 endpoint 設定。
- `twmap3/js/main.js`：Google `ImageMapType` 和大量 tile URL、圖層初始化、marker/KML/分析流程。
- `twmap3/js/functions.js`：選區、產生器、Proj4 投影、coverage overlay、export，以及大量 Google Maps 專用操作。
- `twmap3/js/loadgpx.js`：GPX marker/polyline/InfoWindow。
- `twmap3/js/label.js`：label 相關邏輯。
- `twmap3/js/shapedraw.js`：Google drawing manager 相關功能。
- `twmap3/js/ProjectedOverlay.js`：Google custom overlay。

## 投影策略

- OpenLayers map view 預設使用 EPSG:3857。
- 使用者輸入與一般 API 交換優先使用 WGS84 lon/lat。
- 只有在地圖產生器、grid 或本地資料介接時轉換到 TWD67/TWD97。
- 既有 `twmap3/js/functions.js` 的 EPSG 3825、3826、3827、3828 定義需要移植到 `twmap4/js/map/projections.js` 或同等模組。
- OpenLayers 正式註冊 proj4 projection 時，需確認目前專案是否引入 proj4 套件；不能假設舊版 `Proj4js` 可直接使用。

## 下一個 agent 的建議工作順序

### 1. 先做執行驗證

```bash
cd /home/happyman/projects/dev
grant twmap4-dist
```

如果 `grunt` 不存在，先查看 `package.json` 和 node_modules 狀態，不要自動安裝依賴，除非使用者要求。

再用 PHP server 或既有部署方式開啟 `twmap4/index.php`，確認：

- OpenLayers 載入。
- OSM tile 顯示。
- 底圖切換有效。
- 點擊、搜尋座標、選區繪製有效。

### 2. 將 twmap3 的 tile definitions 搬成 config

從 `twmap3/js/main.js` 抽出所有 `getTileUrl`，建立 `twmap4/js/map/layers.js`。先處理 XYZ/TMS/WMTS 的差異，尤其 NLSC WMTS 的 `{z}/{y}/{x}` 順序與自訂歷史圖 tile URL。

不要把每個圖層繼續寫成 Google `ImageMapType`。

### 3. 建立 layer manager

需要提供：

- base layer 單選。
- overlay layer 顯示/隱藏。
- opacity 調整。
- layer id 與 UI select 對應。

### 4. 移植純地理邏輯

先把 projection、`is_taiwan`、`lonlat_getblock`、`update_params` 等沒有必要綁定 Google Maps 的邏輯移到新模組。然後再把 `google.maps.LatLng`、`google.maps.Polygon` 改為 OpenLayers geometry/feature。

### 5. 移植資料圖層

優先順序：

- POI/markers/labels。
- KML/GPX。
- coverage image overlays。
- elevation/viewshed。

### 6. 最後移植 generator/export/admin

保持後端 endpoint 和參數格式相容，先不要修改 `twmap_gen` 後端；使用實際 API response 驗證。

## 目前已知注意事項

- `twmap4/index.php` 中 OpenLayers、jQuery 等使用 CDN，正式部署前需確認網路/CSP 政策。
- `app.js` 目前的 geocoder response 假設格式是 `{lat, lon}`，需依實際 `api/geocoder.php` 回應修正。
- `olAdapter.addDrawSelection()` 每按一次「選區」就新增一個 Draw interaction；後續需避免重複 interaction，並將畫出的 feature 放到可管理的 selection layer，而不是只放在 interaction 的匿名 source。
- `onMoveEnd()` 目前監聽的是 `change:resolution`，並不是真正的 move end；後續應改用 map `moveend` event。
- `addPolygon()` 的 ring 應確保首尾閉合；OpenLayers 通常要求 polygon ring closed。
- `setView()` 使用 `zoom || view.getZoom()`，zoom 為 0 時會被忽略；後續可改成明確的 undefined 判斷。
- `config.inc.php` 尚未處理 session/login，不能視為 twmap3 的完整替代品。
- 外部 coverage 圖片、Google 名稱圖層和部分第三方 tile 可能有授權、CORS、服務穩定性問題；移植時保留原始 URL 前先確認需求。

## 工作區變更邊界

本次相關變更：

- `Gruntfile.js`
- `twmap4/`
- `docs/TWMap4_HANDOFF.md`

執行 `git status` 時還看到其他未追蹤檔案，例如：

- `docs/rudymap/README.md`
- `twmap3/js/oms.min.js_ame`
- `twmap_gen/a.png`
- `twmap_gen/api/cpu.prof`
- `twmap_gen/jm*.sh`
- `twmap_gen/kk`
- `twmap_gen/lib/Twmap/Svg/gpx/`
- `twmap_gen/localthresh`

這些不屬於 twmap4 這次工作，請勿刪除或重置。

## 完成條件

twmap4 至少應達到：

- 不載入 Google Maps API。
- 能載入並切換主要底圖。
- 能顯示既有 POI、labels、KML/GPX。
- 能完成搜尋與定位。
- 能完成範圍選取和既有 generator API 呼叫。
- TWD67/TWD97 grid 結果和 twmap3 一致。
- 重要功能在 desktop/mobile 都可使用。
- `grunt twmap4-dist` 能產生 `dist/twmap4`。
