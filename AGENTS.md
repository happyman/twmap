# AGENTS.md — twmap3 → twmap4 Migration Handoff

## Project Overview
Migrate twmap3 (OpenLayers 2) features to twmap4 (OpenLayers 10.3.1). Dev site: `https://dev.happyman.idv.tw/map4/`

## Build & Deploy
```bash
cd /home/happyman/projects/dev
npx grunt rsync:twmap4    # REQUIRED after every edit — server serves from dist/
```
**Important**: The server serves `dist/twmap4/`, not `twmap4/` directly. You MUST run `npx grunt rsync:twmap4` after every edit or changes won't appear live.

## Commit History (twmap4 migration)
```
8b64645 Add splash screen with twmap.png icon on initial load
28ff7d5 Adjust point info popup: taller, close button, remove 地圖對照器, measurement, 地籍座標
69d4074 Add GPX drag-and-drop import to twmap4
82dc3a5 Move About button to toolbar far left; right-align controls; move about handler to meerkat.js
2b96edc Add About button & shorten search box; include version 0.1
00879fa Refine draw control look: remove outer box, equal-size icon buttons
73c190f Add fullscreen button above geolocation control
bd2238d Add drag-to-move for shapes; drop redundant Select tool button
547f986 Icon-style draw tools with rectangle support (port of twmap3 toolbar)
6c187ac Compact layer controls, center draw buttons, add geolocation control
98fb1e6 Refresh cursor coordinate+zoom display on zoom change
0adae34 Add map attribution/edit-OSM link and cursor coordinate+zoom display
3e5548c Port twmap3 FeatureLocation first-load fallback
8b22b8c Extract shape drawing module and add TWD grid area-select
```

## Current State (as of commit 8b64645)

### Implemented
- **Search**: Mountain/landmark/coordinate search with datalist, TWD67/97 input
- **View persistence**: Saved to localStorage (`twmap4_view`), restored on load
- **FeatureLocation**: Random landmark fallback, `?goto` URL param
- **Mapinfo**: Cursor coords + zoom level (`#msg`), copyright + edit link (`#map-attribution`)
- **Layer controls**: Triangle marker label toggle, track toggle, type filter (13 categories)
- **Draw tools**: 4 icon buttons (Polygon, Rectangle, Circle, LineString) with FA icons
- **Shape persistence**: Shapes saved to localStorage (`shapes`), WGS84 coords
- **Drag-to-move**: `ol.interaction.Translate` for moving drawn shapes
- **Fullscreen**: `#fullscreen-btn` (fa-arrows-alt/fa-compress toggle) via Fullscreen API
- **Geolocation**: `#geolocate-btn` → navigator.geolocation → setView
- **About**: `#about-btn` at toolbar far left → `about.php` in meerkat panel
- **Toolbar layout**: About far left, search 320px, controls right-aligned (margin-left:auto)
- **Responsive**: 960px/760px/600px breakpoints — buttons bottom-center, attribution narrowed
- **GPX drag-and-drop**: Drop `.gpx` file → red tracks (width 3) + 32px waypoint icons, view fit, accumulates layers
- **Point info popup**: Taller (560px), close button (X), 地籍座標 displayed, measurement start/end buttons, no 地圖對照器 link
- **Splash screen**: Full-screen dark overlay with twmap.png logo + spinner, fades out on window.load

### NOT Yet Migrated (from twmap3)
- KML export UI (right-click menu exists but may need refinement)
- Photo upload/panorama
- Hillshade overlay
- Line-of-sight (通視模擬) — basic hook exists, needs full UI
- Measurement tools
- Print/export
- Mobile-specific gestures

## Architecture

### Key Files
| File | Purpose |
|------|---------|
| `twmap4/index.php` | Main entry, toolbar, script loading |
| `twmap4/css/twmap4.css` | All styles, responsive breakpoints |
| `twmap4/js/app.js` | Main app init, fullscreen/geolocate handlers, first-load |
| `twmap4/js/olAdapter.js` | OL10 adapter: draw, translate, click handling |
| `twmap4/js/mapApi.js` | Public API: setView, addDrawSelection, moveSelection |
| `twmap4/js/shapedraw4.js` | Shape drawing UI, persistence, draw-type toggles |
| `twmap4/js/areaselect.js` | TWD grid area selection |
| `twmap4/js/mapinfo.js` | `#msg` cursor+zoom, `#map-attribution` |
| `twmap4/js/meerkat.js` | Iframe panel (showmeerkat/closeMeerkat) |
| `twmap4/js/overlays.js` | Marker/triangle/track overlays |
| `twmap4/js/coverage.js` | Signal coverage overlay |
| `twmap4/js/gpxdrop.js` | GPX drag-and-drop import (FileReader + ol.format/GPX) |
| `twmap4/about.php` | About page (reads VERSION) |
| `twmap4/VERSION` | Version string (`0.1`) |
| `twmap4/config.inc.php` | API URLs, default center, feature locations |

### Headless Testing
```bash
export PATH=/tmp/opencode/node-v20.18.0-linux-x64/bin:$PATH
# Write test to file, then run with custom node
cat > /tmp/opencode/verify4_test.js <<'EOF'
const { chromium } = require('playwright-core');
// ... test code
EOF
node /tmp/opencode/verify4_test.js
```
- Use `chromium.launch({args:['--no-sandbox','--disable-gpu','--headless=new','--js-flags=--max-old-space-size=512']})`
- Drag-based OL interactions (Circle/Rectangle draw, Translate) cannot be tested via headless synthetic drags — known OL limitation
- Click-based modes (Polygon, LineString) reproduce fine

### twmap3 → twmap4 Mapping
| twmap3 | twmap4 | Notes |
|--------|--------|-------|
| `OpenLayers.Map` | `ol.Map` (v10.3.1) | Full build from CDN |
| `OpenLayers.Layer.*` | `ol.layer.*` | XYZ tiles |
| `OpenLayers.Feature.Vector` | `ol.Feature` + `ol.source.Vector` | |
| `OpenLayers.Control.DrawFeature` | `ol.interaction.Draw` | |
| `OpenLayers.Control.ModifyFeature` | `ol.interaction.Modify` | |
| `OpenLayers.Control.DragPan` | default OL interaction | |
| `OpenLayers.Projection` | `ol.proj` + `twProjections` | TWD97/67 support |
| `showmeerkat()` | `showmeerkat()` (same API) | iframe panel |

### CSS Layout Notes
- `#toolbar`: `display:flex; flex-wrap:wrap; gap:8px; padding:10px 12px`
- `#about-btn`: first child, anchor with icon, far left
- `#search-box`: `flex:0 1 auto; width:320px` input, full row at ≤760px
- `#marker-label-toggle-btn`: `margin-left:auto` pushes controls right
- `#buttons`: draw controls, no outer box, 34px icons (30px at ≤600px)
- `#fullscreen-btn`: `position:absolute; top:10px; right:10px`
- `#geolocate-btn`: `position:absolute; top:54px; right:10px`
- `#params`: `position:absolute; top:100px; right:10px`
- `#msg`: cursor+zoom, `bottom:42px; left:50%; transform:translateX(-50%)`
- `#map-attribution`: `position:absolute; bottom:0; right:0; max-width:22%` (13% at ≤600px)
- `#drop-container`: `position:absolute; top:0; left:0; z-index:1000; display:none` → `block` on dragenter; full overlay

## User Preferences
- **Never commit** `twmap3/js/.main.js.swp` (vim swap, untracked)
- Draw-type icon buttons keep light-gray background + border (not flat/borderless)
- About button text: `地圖瀏覽器 v0.1` with icon
- twmap4 will deploy to `/map/` path — use relative URLs
- OL constructor names mangled in dist build — use element IDs, not `instanceof`

## Known Issues
- `package.json` is modified (unrelated to twmap4 — do not commit)
- `twmap4/index.php.bak` is untracked (backup — do not commit)
- `.gemini/` directory untracked (ignore)
