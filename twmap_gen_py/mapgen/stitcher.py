"""Tile download, merge, reproject, and crop — the rasterio core.

Replaces the PHP stitcher which used curl + montage + a hardcoded 0.3° affine
rotation. This version downloads EPSG:3857 tiles, merges them into a mosaic
with correct georeferencing, then uses ``rasterio.warp.reproject`` to convert
to the target TWD97/TWD67 projection. No approximation hacks.
"""

from __future__ import annotations

import logging
import math
import tempfile
from dataclasses import dataclass
from pathlib import Path

import numpy as np
import rasterio
from rasterio.warp import Resampling, reproject

from .config import (
    CRS_MERCATOR,
    MAX_CHUNK_PX,
    MapSource,
)
from .proj import Region

logger = logging.getLogger(__name__)

# Web Mercator tile extent (EPSG:3857) at zoom 0 is ±20037508.342789244 m
_MERC_MAX = 20037508.342789244

# Nominally every tile is 256x256 px
TILE_SIZE = 256


@dataclass
class DownloadOptions:
    """Options controlling tile download behavior."""

    max_concurrent: int = 16
    timeout: float = 30.0
    retries: int = 4
    user_agent: str = "twmap-gen/0.1"


def _tile_url(layer_url: str, tile_order: str, z: int, x: int, y: int) -> str:
    """Fill a URL template with tile coordinates.

    ``tile_order`` is "xyz" (standard) or "yzx" (WMTS-style, e.g. NLSC).
    """
    if tile_order == "yzx":
        return layer_url.format(z=z, y=x, x=y)
    return layer_url.format(z=z, x=x, y=y)


async def download_tiles(
    urls: list[tuple[str, int, int]],  # (url, x, y)
    dest: Path,
    opts: DownloadOptions | None = None,
    on_progress=None,
) -> None:
    """Download tiles in parallel into ``dest`` as ``{x}_{y}.png``.

    ``urls`` is a list of (url, x_index, y_index). Files are written as
    ``{x}_{y}.png``. Skips already-downloaded non-empty files.

    ``on_progress``, if given, is called with the fraction of tiles already
    available (cached or freshly downloaded) as they complete, so a live
    frontend can show per-tile download progress.
    """
    import asyncio

    import aiohttp

    opts = opts or DownloadOptions()
    dest.mkdir(parents=True, exist_ok=True)
    sem = asyncio.Semaphore(opts.max_concurrent)
    headers = {"User-Agent": opts.user_agent}
    total = len(urls)
    done = 0
    done_lock = asyncio.Lock()

    def report() -> None:
        nonlocal done
        if on_progress is not None:
            on_progress(done / total if total else 1.0)

    async def fetch_one(url: str, x: int, y: int) -> None:
        nonlocal done
        fname = dest / f"{x}_{y}.png"
        if fname.exists() and fname.stat().st_size > 0:
            async with done_lock:
                done += 1
            report()
            return
        for attempt in range(opts.retries):
            try:
                async with sem:
                    async with aiohttp.ClientSession(headers=headers) as session:
                        async with session.get(
                            url, timeout=aiohttp.ClientTimeout(total=opts.timeout)
                        ) as resp:
                            resp.raise_for_status()
                            data = await resp.read()
                if not data:
                    raise RuntimeError("empty response")
                fname.write_bytes(data)
                async with done_lock:
                    done += 1
                report()
                return
            except Exception as exc:  # noqa: BLE001
                if attempt == opts.retries - 1:
                    raise RuntimeError(f"failed to download {url}: {exc}") from exc
                await asyncio.sleep(0.5 * (attempt + 1))

    await asyncio.gather(*(fetch_one(u, x, y) for u, x, y in urls))


def _pixel_size_at_zoom(zoom: int) -> float:
    """Meters per pixel for a tile at the given zoom in EPSG:3857."""
    return 2.0 * _MERC_MAX / (2**zoom) / TILE_SIZE


def _tile_mercator_bounds(x: int, y: int, zoom: int) -> tuple[float, float, float, float]:
    """Return (minx, miny, maxx, maxy) in EPSG:3857 for tile (x, y)."""
    res = _pixel_size_at_zoom(zoom)
    minx = -_MERC_MAX + x * TILE_SIZE * res
    maxy = _MERC_MAX - y * TILE_SIZE * res
    return minx, maxy - TILE_SIZE * res, minx + TILE_SIZE * res, maxy


def _mosaic_origin(min_tile_x: int, min_tile_y: int, zoom: int) -> tuple[float, float]:
    """Top-left corner (minx, maxy) in EPSG:3857 of the mosaic, whose origin
    is the top-left corner of tile (min_tile_x, min_tile_y)."""
    res = _pixel_size_at_zoom(zoom)
    minx = -_MERC_MAX + min_tile_x * TILE_SIZE * res
    maxy = _MERC_MAX - min_tile_y * TILE_SIZE * res
    return minx, maxy


def _read_tile_as_array(path: Path) -> np.ndarray | None:
    """Read a tile PNG/JPEG into an RGBA uint8 array (HxWx4)."""
    from PIL import Image

    try:
        with Image.open(path) as im:
            # Coerce to RGBA so transparent/JPEG tiles are consistent
            return np.array(im.convert("RGBA"))
    except Exception:  # noqa: BLE001
        return None


async def stitch_to_mosaic(
    region: Region,
    source: MapSource,
    zoom: int,
    workdir: Path,
    download: DownloadOptions | None = None,
    layer_index: int = 0,
    include_gpx: bool = False,
    on_progress=None,
) -> Path:
    """Download tiles and stitch them into a georeferenced EPSG:3857 GeoTIFF.

    Returns the path to the mosaic GeoTIFF. Applies per-layer ``pre_merge``
    transforms to each tile before writing. ``include_gpx`` selects the
    PHP ``-G`` tile layers. Each layer downloads into its own subdirectory so
    multi-layer sources don't collide on ``{x}_{y}.png`` names.
    """
    from .transforms import Pipeline

    layer = source.layer_defs(include_gpx)[layer_index]
    layer_dir = workdir / f"layer{layer_index}"
    url_tmpl = layer.url
    tile_order = layer.tile_order or source.tile_order

    min_tx, min_ty, max_tx, max_ty = _calc_tile_range(region, zoom, source)
    ncols = max_tx - min_tx + 1
    nrows = max_ty - min_ty + 1

    logger.info(
        "Stitching layer %d: %d x %d tiles (zoom %d) over %s",
        layer_index, ncols, nrows, zoom, region,
    )

    # Build download list
    urls = []
    for ty in range(min_ty, max_ty + 1):
        for tx in range(min_tx, max_tx + 1):
            url = _tile_url(url_tmpl, tile_order, zoom, tx, ty)
            urls.append((url, tx, ty))

    logger.info("Downloading %d tiles for layer %d", len(urls), layer_index)
    await download_tiles(urls, layer_dir, download, on_progress=on_progress)
    logger.info("Downloaded %d tiles for layer %d", len(urls), layer_index)

    # Read tiles into a full mosaic buffer (RGBA in EPSG:3857 grid)
    mosaic = np.zeros((nrows * TILE_SIZE, ncols * TILE_SIZE, 4), dtype=np.uint8)
    pre_pipeline = Pipeline(layer.pre_merge) if layer.pre_merge else None

    for ty in range(min_ty, max_ty + 1):
        for tx in range(min_tx, max_tx + 1):
            fname = layer_dir / f"{tx}_{ty}.png"
            arr = _read_tile_as_array(fname)
            if arr is None:
                logger.warning("Missing/empty tile %s", fname)
                continue
            if pre_pipeline is not None:
                arr = pre_pipeline(arr)
            row0 = (ty - min_ty) * TILE_SIZE
            col0 = (tx - min_tx) * TILE_SIZE
            mosaic[row0 : row0 + TILE_SIZE, col0 : col0 + TILE_SIZE] = arr

    # Write mosaic directly to a georeferenced GeoTIFF on disk
    res = _pixel_size_at_zoom(zoom)
    minx, maxy = _mosaic_origin(min_tx, min_ty, zoom)
    # Affine(xscale, xskew, x_origin, yskew, yscale, y_origin) — NB NOT the
    # GDAL geotransform order (x_origin, xscale, ...) that `geotransform` holds.
    mosaic_transform = rasterio.Affine(res, 0.0, minx, 0.0, -res, maxy)
    out_path = workdir / f"mosaic_layer{layer_index}.tif"
    with rasterio.open(
        out_path,
        "w",
        driver="GTiff",
        height=mosaic.shape[0],
        width=mosaic.shape[1],
        count=4,
        dtype="uint8",
        crs=CRS_MERCATOR,
        transform=mosaic_transform,
    ) as dst:
        # Rearrange HxWxC -> CxHxW
        for c in range(4):
            dst.write(mosaic[..., c], c + 1)

    logger.info("Stitched layer %d mosaic (%dx%d)", layer_index, ncols, nrows)
    if on_progress:
        on_progress(1.0)
    return out_path


def _calc_tile_range(region: Region, zoom: int, source: MapSource):
    """Compute the tile bounding box covering the region at the given zoom,
    matching the PHP approach (convert TWD -> WGS84 -> tile indices)."""
    from .proj import latlon_to_tile, twd_to_wgs84

    tl_lon, tl_lat = twd_to_wgs84(region.x0, region.y0, region.crs)
    br_lon, br_lat = twd_to_wgs84(region.x1, region.y1, region.crs)
    # Top-left -> bottom-right
    a = latlon_to_tile(tl_lon, tl_lat, zoom)  # (x, y)
    b = latlon_to_tile(br_lon, br_lat, zoom)
    min_tx, max_tx = min(a[0], b[0]), max(a[0], b[0])
    min_ty, max_ty = min(a[1], b[1]), max(a[1], b[1])
    return min_tx, min_ty, max_tx, max_ty


def reproject_to_twd(
    mosaic_path: Path,
    region: Region,
    out_shape: tuple[int, int],
    resampling: Resampling = Resampling.bilinear,
) -> np.ndarray:
    """Reproject an EPSG:3857 mosaic to the region's TWD CRS.

    ``out_shape`` is (height, width) in pixels of the output (target pixel_count).
    Returns an RGBA uint8 numpy array in TWD coordinates covering exactly
    the ``region`` bounding box.

    The destination transform is built directly from the region bounds in its
    TWD CRS (``x0, y0`` top-left, positive-east / negative-north scales derived
    from the target pixel size), so the whole output samples the mosaic tiles.
    GDAL maps each destination pixel back into the EPSG:3857 source grid.

    Note on dateline/antimeridian: Taiwan is far from ±180 so a single
    transform range is fine.
    """
    src_crs = CRS_MERCATOR
    dst_crs = region.crs  # TWD97 or TWD67

    with rasterio.open(mosaic_path) as src:
        dst_height, dst_width = out_shape
        res_x = region.width_m / dst_width
        res_y = region.height_m / dst_height
        dst_transform = rasterio.Affine(res_x, 0, region.x0, 0, -res_y, region.y0)

        dst = np.zeros((dst_height, dst_width, src.count), dtype=np.uint8)
        for c in range(src.count):
            reproject(
                source=src.read(c + 1),
                destination=dst[..., c],
                src_transform=src.transform,
                src_crs=src_crs,
                src_nodata=0,
                dst_transform=dst_transform,
                dst_crs=dst_crs,
                dst_nodata=0,
                resampling=resampling,
            )
    return dst


def chunk_region(region: Region, px_per_km: float, max_px: int = MAX_CHUNK_PX) -> list[Region]:
    """Split a region into sub-regions that each fit within ``max_px`` pixels.

    ``px_per_km`` is the target pixel density (315, 630, ...). Returns a list
    of sub-regions covering the input (no overlap, edge-to-edge).
    """
    width_px = region.width_m / 1000.0 * px_per_km
    height_px = region.height_m / 1000.0 * px_per_km
    if width_px <= max_px and height_px <= max_px:
        return [region]

    nx = max(1, math.ceil(width_px / max_px))
    ny = max(1, math.ceil(height_px / max_px))
    chunks = []
    for j in range(ny):
        for i in range(nx):
            x0 = region.x0 + i * (region.width_m / nx)
            x1 = region.x0 + (i + 1) * (region.width_m / nx)
            y1 = region.y1 + j * (region.height_m / ny)
            y0 = region.y1 + (j + 1) * (region.height_m / ny)
            chunks.append(
                Region(x0, y0, x1, y1, datum=region.datum, penghu=region.penghu)
            )
    return chunks


def _stitch_chunks_horizontal(chunks: list[np.ndarray]) -> np.ndarray:
    """Concatenate a row of RGBA arrays left-to-right."""
    return np.concatenate(chunks, axis=1)


async def build_base_image(
    region: Region,
    source: MapSource,
    workdir: Path | None = None,
    download: DownloadOptions | None = None,
    include_gpx: bool = False,
    on_progress=None,
) -> np.ndarray:
    """Full pipeline: download + merge every layer + reproject to TWD.

    Returns an RGBA uint8 numpy array sized to (region_height,
    region_width) in pixels at the source's pixel-per-km density.

    Auto-chunks regions larger than MAX_CHUNK_PX. Multi-layer sources are
    composited via the compositor. ``include_gpx`` selects the PHP ``-G``
    tile layers.
    """

    px_per_km = source.pixel_per_km
    own_tmp = False
    if workdir is None:
        workdir = Path(tempfile.mkdtemp(prefix="twmap_"))
        own_tmp = True

    try:
        chunks = chunk_region(region, px_per_km)
        logger.info("Region split into %d chunk(s)", len(chunks))

        if len(chunks) == 1:
            return await _build_single(
                region, source, px_per_km, workdir, download, include_gpx, on_progress
            )

        # Multi-chunk: build each chunk, then stitch the grids back together.
        # Rows of chunks (east-west within each row of the original region).
        nx = max(1, math.ceil(region.width_m / 1000.0 * px_per_km / MAX_CHUNK_PX))
        ny = max(1, math.ceil(region.height_m / 1000.0 * px_per_km / MAX_CHUNK_PX))
        total = nx * ny
        rows = []
        built = 0
        for j in range(ny):
            row_imgs = []
            for i in range(nx):
                idx = j * nx + i
                chunk = chunks[idx]

                def scaled(frac: float, _start=built / total, _end=(built + 1) / total):
                    if on_progress is not None:
                        on_progress(_start + (_end - _start) * frac)

                img = await _build_single(
                    chunk, source, px_per_km, workdir / f"chunk_{idx}",
                    download, include_gpx, scaled,
                )
                built += 1
                row_imgs.append(img)
            rows.append(_stitch_chunks_horizontal(row_imgs))
        full = np.concatenate(rows, axis=0)
        return full
    finally:
        if own_tmp:
            import shutil

            shutil.rmtree(workdir, ignore_errors=True)


async def _build_single(
    region: Region,
    source: MapSource,
    px_per_km: int,
    workdir: Path,
    download: DownloadOptions | None,
    include_gpx: bool = False,
    on_progress=None,
) -> np.ndarray:
    """Build the base image for a single (non-chunked) region."""
    from .compositor import composite_layers
    from .transforms import Pipeline

    n_layers = len(source.layer_defs(include_gpx))

    # Merge all layers into one RGBA stack, then composite. Each layer's
    # stitch progress is scaled into its share of the 0..1 window.
    layer_imgs = []
    for i in range(n_layers):
        if on_progress is not None:
            start = i / n_layers
            end = (i + 1) / n_layers

            def scaled(frac: float, _s=start, _e=end) -> None:
                on_progress(_s + (_e - _s) * frac)

        else:
            scaled = None
        mosaic = await stitch_to_mosaic(
            region, source, source.zoom, workdir, download, i,
            include_gpx=include_gpx, on_progress=scaled,
        )
        layer_rgba = _reproject_layer(mosaic, region, px_per_km)
        layer_imgs.append(layer_rgba)

    if len(layer_imgs) == 1:
        base = layer_imgs[0]
    else:
        base = composite_layers(layer_imgs, mode="multiply")

    # Apply post_merge transforms
    post = Pipeline(source.post_merge)
    if len(post):
        base = post(base)

    if on_progress:
        on_progress(1.0)
    return base


def _reproject_layer(
    mosaic_path: Path, region: Region, px_per_km: int
) -> np.ndarray:
    """Reproject a single-layer mosaic to RGBA in the region's TWD CRS."""
    width_px = max(1, round(region.width_m / 1000.0 * px_per_km))
    height_px = max(1, round(region.height_m / 1000.0 * px_per_km))
    return reproject_to_twd(mosaic_path, region, (height_px, width_px))


__all__ = [
    "DownloadOptions",
    "download_tiles",
    "stitch_to_mosaic",
    "reproject_to_twd",
    "build_base_image",
    "chunk_region",
    "reproject_to_twd",
]
