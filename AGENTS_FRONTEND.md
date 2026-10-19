# AGENTS.md — TWMap 前端程式架構說明

本文件說明本專案（twmap）中與「地圖產生器前端」相關的程式結構、開發慣例與維護方式。主要針對 `twmap4/`、`twmap3/`、`twmap_gen/` 等前端程式。

## 概覽

TWMap 是台灣地圖瀏覽與出圖系統，包含：
- `twmap4/`：以 OpenLayers 為基礎的現代版前端（主要開發重心）
- `twmap3/`：以 Google Maps API 為基礎的舊版前端
- `twmap_gen/`：地圖產生器（PHP + JS）的 Web UI
- `dist/`：透過 Grunt 建置產出的靜態檔案（不直接編輯）

## 目錄結構

```
dev/
├── twmap4/           # OpenLayers 前端（建議修改此版本）
│   ├── index.php     # 主頁面
│   ├── js/
│   │   ├── layers.js # 圖層定義（mapSources、layerMetadata）
│   │   ├── mapinfo.js# 圖資版權/屬性顯示
│   │   ├── app.js    # 地圖邏輯（圖層綁定、UI、事件）
│   │   ├── mapapi.js # OpenLayers 包裝
│   │   └── ...
│   ├── css/          # 樣式
│   ├── icon/         # 圖示
│   └── lib/          # 第三方函式庫
├── twmap3/           # 舊版 Google Maps 前端（維護性修改請謹慎）
├── twmap_gen/        # PHP 地圖產生器前端
├── dist/             # Grunt 建置輸出（由 rsync 複製 twmap*/ 至此）
├── Gruntfile.js      # 建置設定
└── package.json      # devDependencies（grunt、rsync、usemin 等）
```

## twmap4 架構（重點）

twmap4 採用模組化的 JS 結構，重點檔案如下：

### 1. `twmap4/js/layers.js`
集中定義所有可用圖層的來源與顯示資訊。

**`mapSources`（物件）**
- 每個 key 為 `sourceId`
- 必要欄位：`sourceId`, `label`, `url` 或 `tileUrlFunction`
- 選用欄位：`category`（`'road'` 表示「道路」類圖層，其餘為底圖/base layer）、`minZoom`, `maxZoom`, `order`, `icon`
- 底圖（非 road 類）會出現在「第一層」「第二層」下拉選單
- 道路類（`category: 'road'`）會出現在「道路」下拉選單

範例：
```js
moi_osm_twmap: {
  sourceId: 'moi_osm_twmap',
  label: '魯地圖 twmap 樣式',
  url: 'https://tile.happyman.idv.tw/map/moi_osm/{z}/{x}/{y}.png'
},
bochengsiong: {
  sourceId: 'bochengsiong',
  label: '魯地圖 bochengsiong',
  url: 'https://tile.happyman.idv.tw/map/bochengsiong/{z}/{x}/{y}.png'
},
```

**`layerMetadata`（物件）**
控制圖層在選單中的排序與圖示（icon）。
- `order`：數值越小越前面（用於 `sort((a,b)=>a.order-b.order)`）
- `icon`：顯示於下拉選單選項前的 emoji/icon 字元

範例：
```js
moi_osm_twmap: { order: 170, icon: '🗺' },
bochengsiong: { order: 175, icon: '🗺' },
```

**注意事項**
- 新增底圖時，建議沿用相似圖層的 `icon` 與 `copyright` 風格
- `order` 建議維持既有群組的相對順序，避免大幅重排

### 2. `twmap4/js/mapinfo.js`
處理圖資版權（attribution）與游標座標顯示。

**`copyrights`（物件）**
key 為 `sourceId`，值為 HTML 字串（顯示於地圖右下角的圖資資訊）。
```js
moi_osm_twmap: '&copy; <a href="http://www.openstreetmap.org/" target="_blank">OpenStreetMap</a> contributors, CC-BY-SA <a href="http://rudy.basecamp.tw/taiwan_topo.html" target="_blank">魯地圖</a>',
bochengsiong: '&copy; <a href="http://www.openstreetmap.org/" target="_blank">OpenStreetMap</a> contributors, CC-BY-SA <a href="http://rudy.basecamp.tw/taiwan_topo.html" target="_blank">魯地圖</a>',
```

當圖層切換時，`mapinfo.js` 會根據目前第一層（`#bottom-layer-1-select`）的值更新 attribution。

### 3. `twmap4/js/app.js`
主程式邏輯：
- 建立 `baseMapSources` / `roadSources`：從 `mapSources` 過濾 `category !== 'road'` / `=== 'road'`，並依 `sourceA.order - sourceB.order` 排序
- 初始化底圖：`bottom-1`、`bottom-2`、道路圖層、GPX 軌跡等
- 綁定下拉選單：`bindBottomLayerSelect()` 動態產生 `<option>`，顯示 `source.icon + '  ' + source.label`
- 預設值：`bottom-layer-1-select` 預設 `'atis'`，`bottom-layer-2-select` 預設 `'moi_osm_twmap'`
- 其他 UI（全螢幕、定位、繪圖工具、permalink 等）

### 4. `twmap4/index.php`
HTML 介面，定義圖層控制元件：
```html
<div id="layer-controls">
  <label for="bottom-layer-1-select">第一層</label>
  <select id="bottom-layer-1-select"></select>
  ...
  <label for="bottom-layer-2-select">第二層</label>
  <select id="bottom-layer-2-select"></select>
  ...
  <label for="road-layer-select">道路</label>
  <select id="road-layer-select"></select>
</div>
```

選單內容由 JS 動態填入，故新增圖層至 `mapSources` 後即可自動出現在下拉選單中。

## 建置（Grunt）

專案使用 Grunt 進行建置與部署。相關指令在專案根目錄（`/home/happyman/projects/dev/`）執行。

### 主要任務

- `npx grunt twmap4-dist`：僅同步 `twmap4/` 至 `dist/twmap4/`（rsync）
- `npx grunt`（default）：完整建置流程
  1. `clean` 清理舊版 rev 檔案
  2. `jshint`、`phplint` 靜態檢查
  3. `rsync:generated`（同步 twmap_gen、twmap3 至 dist）
  4. `rsync:twmap4`（同步 twmap4 至 dist）
  5. `useminPrepare`、`concat`、`uglify`、`cssmin`、`filerev`、`usemin` 處理資源

### 注意事項

- **絕對不要直接編輯 `dist/` 目錄**。`dist/` 的內容全由 Grunt 從原始來源（`twmap*/`）產生。
- 修改前端原始檔（如 `twmap4/js/*.js`）後，若僅更新 twmap4，可用 `npx grunt twmap4-dist` 快速部署。
- 完整建置前建議先確認 JS 語法（專案有 jshint 設定）。

## 開發慣例

### 新增底圖圖層（twmap4）

以「魯地圖 bochengsiong」為例，步驟如下：

1. 編輯 `twmap4/js/layers.js`
   - 在 `mapSources` 加入新物件，設定 `sourceId`、`label`、`url`（或 `tileUrlFunction`）
   - 在 `layerMetadata` 加入對應項目，設定 `order` 與 `icon`

2. 編輯 `twmap4/js/mapinfo.js`
   - 在 `copyrights` 加入對應的 `sourceId` 與 attribution HTML

3. （選用）調整預設值
   - 若要設為預設底圖，修改 `twmap4/js/app.js` 中 `bottom-layer-1-select` 或 `bottom-layer-2-select` 的預設值

4. 建置並部署
   ```bash
   cd /home/happyman/projects/dev
   npx grunt twmap4-dist
   ```

5. 提交變更（僅提交原始檔，不提交 dist）
   ```bash
   git add twmap4/js/layers.js twmap4/js/mapinfo.js
   git commit -m "feat: add <layer> layer"
   git push
   ```

### 命名與風格

- 中文優先：UI 標籤（`label`）、註解多使用繁體中文
- `sourceId` 採用英文小寫與底線（如 `moi_osm_twmap`, `bochengsiong`）
- 圖示（icon）：底圖常用 `🗺`，歷史圖資常用 `🗿`，衛星/正射常用 `🌳`/`🌐` 等，建議與同類型圖層一致
- 版權資訊格式：沿用既有的 OSM + 來源連結格式
- URL 格式（瓦片）：`{z}/{x}/{y}.png`（OpenLayers 樣式）或對應的 WMTS 格式（NLSC 使用 EPSG:3857 WMTS）

### Tile 來源慣例

- 自建瓦片：多數置於 `https://tile.happyman.idv.tw/map/<name>/{z}/{x}/{y}.png`
- NLSC：使用 WMTS 服務（`https://wmts.nlsc.gov.tw/wmts/.../default/EPSG:3857/{z}/{y}/{x}`）
- 中研院等外部：部分使用 `file-exists.php` 形式的動態 URL 或特殊函式（如蘭嶼地區需座標判斷的 `createHistoricalTileUrl`）

## twmap3 / twmap_gen 備註

- `twmap3/` 採用 Google Maps API，圖層定義方式與 twmap4 不同（直接定義 `ImageMapType` Options）。除非明確需求，通常不需大幅修改。
- `twmap_gen/` 為 PHP 地圖產生器的前端介面，與實際出圖邏輯（`cmd_make2.php`、`lib/Twmap/*`）相關。前端修改時需留意與後端參數的對應。

## 開發建議

- 修改前先閱讀相關檔案（layers.js、mapinfo.js、app.js）瞭解現有模式，再依樣新增
- 儘量小幅修改，避免破壞現有排序、預設值或相依邏輯
- 建置前後可用瀏覽器開啟 `twmap4/index.php`（或透過 PHP 內建伺服器）進行手動驗證
- 圖層加入後應確認：下拉選單有出現、切換正常、版權資訊正確顯示

