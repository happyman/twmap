# AGENTS.md — Python Rewrite of cmd_make2.php

## Goal
Rewrite the PHP CLI map generator (`cmd_make2.php` + `lib/Twmap/*`) in Python.
Uses `rasterio.warp.reproject()` instead of the current affine-rotation hack.
Independent repo at `twmap_gen_py/`.

## Original PHP Source
- Entry: `twmap_gen/cmd_make2.php` (~520 lines)
- Libraries: `twmap_gen/lib/Twmap/` (Stitcher.php, Splitter.php, Proj.php, Websocat.php, Export/*, Svg/Gpx2Svg.php)
- 7 stitcher subclasses with different tile sources and preprocessing

## Architecture

### Plugin-based MapSource Registry
Each map source (經建三, 魯地圖, 堡圖1904, etc.) is a `MapSource` dataclass entry in `config.py`.
Adding a new source = adding a dict entry, no subclassing needed.

### Composable Transform Pipeline
Image transforms (`Equalize`, `Gamma`, `Level`, `Normalize`, `GrayscaleSimple`, `GrayscaleEnhanced`, `AdaptiveThreshold`) are composable classes.
Each `MapSource` defines `pre_merge` and `post_merge` transform lists.

### Layer Compositor
Supports `multiply` (current behavior), `alpha` (future transparency), `overlay` modes.
Future: GPX track layer can be alpha-blended onto base map (currently baked before grayscale).

### Core: rasterio
- `rasterio.merge.merge()` — stitch tiles into EPSG:3857 mosaic
- `rasterio.warp.reproject()` — proper reprojection to TWD97/TWD67 (replaces 0.3° affine hack)
- `rasterio.windows.Window` — exact crop (no 2px fudge factor)

## Pipeline
```
1. Parse CLI args → resolve TWD67/97 bounds → convert to tile XYZ (pyproj)
2. aiohttp download tiles (semaphore-limited concurrency)
3. Apply pre_merge transforms per tile (numpy/Pillow)
4. rasterio.merge.merge() → EPSG:3857 mosaic
5. Layer compositing (if multi-layer: multiply, alpha, etc.)
6. rasterio.warp.reproject() → TWD97/TWD67 (auto-chunk if >15000px)
7. Crop to exact TWD bounds (windowed read)
8. Apply post_merge transforms
9. Grayscale conversion (source-specific strategy)
10. Grid lines, coordinate tags, logo (Pillow)
11. Export: PDF (img2pdf+pypdf), KMZ (zipfile+KML), GeoTIFF (rasterio), PNG
```

## File Structure
```
twmap_gen_py/
├── pyproject.toml
├── requirements.txt
├── map_sources.json            # Optional: external source definitions
├── cmd_make2.py                # Entry point
├── mapgen/
│   ├── __init__.py
│   ├── cli.py                  # argparse + legacy -r/-O/-v aliases
│   ├── config.py               # MapSource dataclass + built-in SOURCES registry
│   ├── transforms.py           # All Transform classes
│   ├── proj.py                 # TWD67/97 ↔ WGS84 (pyproj), tile XYZ math
│   ├── stitcher.py             # Download → merge → reproject → crop (rasterio)
│   ├── compositor.py           # Layer compositing (multiply, alpha, overlay)
│   ├── splitter.py             # Image → page tiles
│   ├── grinder.py              # Grid lines, coordinate tags, logo (Pillow)
│   ├── export/
│   │   ├── __init__.py
│   │   ├── pdf.py              # img2pdf + pypdf
│   │   ├── kmz.py              # zipfile + KML
│   │   └── geotiff.py          # rasterio write
│   ├── gpx2svg.py              # GPX overlay (gpxpy + cairosvg)
│   └── notify.py               # WebSocket progress (websockets)
└── tests/
    ├── test_proj.py
    ├── test_transforms.py
    └── test_sources.py
```

## CLI Interface
```bash
# New Pythonic
mapgen make --region 307000,2677000,12,6 --output ./out/ --map-type rudymap

# Legacy compatible (km units, matches PHP cmd_make2.php -r payload)
cmd_make2.py -r 307:2677:12:6:TWD67 -O ./out/ -v 2016

# Testing helpers
mapgen test-source rudymap --tile 16/23456/12345 --show-steps --output ./debug/
mapgen compare-sources rudymap,v3 --region 307000,2677000,2,2 --output ./compare/
mapgen list-sources
```

## Dependencies
```
pyproj>=3.6          # TWD67/97 ↔ WGS84
rasterio>=1.3        # merge, reproject, crop, GeoTIFF
aiohttp>=3.9         # Async tile download
Pillow>=10.0         # Grid tags, logo, text rendering
gpxpy>=1.6           # GPX parsing
cairosvg>=2.7        # SVG → PNG (GPX overlay)
img2pdf>=0.4         # PNG → PDF pages
pypdf>=3.17          # PDF merge
websockets>=12.0     # Progress notifications
numpy>=1.24          # Transforms, adaptive threshold
```

## Key Design Decisions
1. **rasterio.reproject** replaces the 0.3° affine rotation hack
2. **Auto-chunking** for large regions (>15000px), whole-region by default
3. **MapSource dataclass** — adding new sources = adding dict entry
4. **Transform pipeline** — composable, testable, no hardcoded IM commands
5. **Grayscale strategies** — simple, enhanced, adaptive_threshold (parameterized)
6. **No external binaries** except optional `pngquant` + CJK font file

## Map Source Config (from PHP Stitcher subclasses)
| Source | Zoom | px/km | pre_merge | post_merge | Grayscale |
|--------|------|-------|-----------|------------|-----------|
| v3 經建三 | 16 | 315 | Equalize, Gamma(2.2) | — | enhanced (brightness+20, contrast+5, tint+40) |
| v2016 魯地圖 | 16 | 315 | — | Normalize | simple |
| nlsc | 17 | 630 | Level(0.25, 1.0, 0.1) | — | simple |
| 1904 堡圖 | 16 | 315 | — | Normalize | adaptive_threshold |
| 1916 蕃地 | 16 | 315 | — | Normalize | simple |
| 1921 堡圖紅字 | 16 | 315 | Level(0.25) | — | adaptive_threshold |
| 1924 陸測 | 16 | 315 | — | — | adaptive_threshold |

## Implementation Order
- [x] 1. `config.py` + `transforms.py` — Source registry + transform classes
- [x] 2. `proj.py` — Coordinate conversion (pyproj)
- [x] 3. `stitcher.py` — Download + merge + reproject + crop (rasterio core)
- [x] 4. `compositor.py` — Layer compositing
- [x] 5. `grinder.py` — Grid lines, coordinate tags, logo (Pillow)
- [x] 6. `splitter.py` — Image → page tiles
- [x] 7. `export/pdf.py`, `kmz.py`, `geotiff.py` — Export formats
- [x] 8. `gpx2svg.py` — GPX overlay
- [x] 9. `notify.py` — WebSocket progress
- [x] 10. `cli.py` + `cmd_make2.py` — Wire everything together
- [x] 11. CLI helpers: `test-source`, `compare-sources`, `list-sources`
- [x] 12. Unit tests (78 passing)

## Current Status
- Core pipeline implemented and working end-to-end (download → stitch → reproject → grid/tags/logo → grayscale → PDF/KMZ/GeoTIFF)
- Uses `uv` as package manager (`uv sync`, `uv run`)
- Verified working: `2016` (魯地圖), `3` (經建三) on TWD67 region
- `nlsc` TWD render blocked only by sandbox SSL cert verification (URL/yzx order correct)
- **Print layout parity (PDF splitter)**: page orientation is auto-chosen — wide regions (e.g. 7x5) produce landscape (A4R) pages, tall ones portrait. `make_simage` mirrors PHP `im_simage_resize`: a fixed layout ratio (`min((px_w-42)/(tw*px/km),(px_h-42)/(th*px/km))`, 92% for 5x7 A4) resizes each page uniformly (aspect preserved) and centers it on white paper, so every page of a dimension prints at the same scale and regions smaller than a page are *not* stretched to fill it. pdf.py uses img2pdf A4 shrink-only with `auto_orient`, matching PHP `-S A4 --fit shrink --auto-orient`.
- **GPX overlay wired into `make`**: `--gpx file:trk_label:wpt_label` parses, renders elevation-colored tracks directly (numpy/PIL, no cairosvg dependency), and alpha-composites onto the base. Overlay is applied *after* grayscale so colored tracks stay visible on the `.gray.png`. Unit + CLI integration tests.
- **`-G` (`--include-tracks`) = PHP `include_gpx` has tile-layer variants**: each source's `MapSource.layers_gpx` mirrors `Stitcher::gettileurl()` — v3/2016 swap to the `*_nowp_nocache` source, NLSC/archival multiply the archive layer with the `happyman_nowp` overlay (`composite -compose Multiply`, via `compositor.composite_layers`). Multi-layer stitching now downloads each layer into its own `layer{i}/` subdir to avoid `{x}_{y}.png` collisions. Sources without a gpx variant fall back to normal tiles.
- **Print/PDF output parity**: like PHP (`$outimage_gray` for split/pages/PDF vs `$outimage` for GeoTIFF/KMZ), the pages/PDF are built from the *grayscale* image and GeoTIFF/KMZ from the *color* tagged image (`-c` keeps color). Verified end-to-end: `-G -v 2016` 7x5 TWD67 PDF is 100% grayscale and the base differs from the non-`-G` render (nowp trails added).
- **Progress notifications**: `notify.py` Notifier (background-threaded `websockets` client) pushes `step:<name>` + `ps%NN` messages to the frontend across the whole pipeline; `--ws-url/-l` arg on both `make` and `legacy`. Per-tile download progress, per-stage steps (download/base/style/gpx/grayscale/split/pdf/kmz/geotiff), and `err:<msg>` on failure. Loopback connections bypass the http(s)_proxy env so a same-host frontend isn't 403'd.
- Branch: `feat/python-mapgen`
- Tests: `uv run python -m pytest` (81 passing)
