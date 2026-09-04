"""KMZ export: Garmin-compatible KML GroundOverlay + cropped tiles in a zip.

Replaces the PHP `GarminKmz` class (zip + per-3km KML overlays).

The tagged PNG is cropped into 3km x 3km tiles, each with a KML
GroundOverlay spanning its WGS84 bounds, then everything is zipped into
the .kmz file.
"""

from __future__ import annotations

import logging
import zipfile
from pathlib import Path
from typing import Sequence

import numpy as np
from PIL import Image

from ..proj import Region, twd_to_wgs84

logger = logging.getLogger(__name__)

TILE_KM = 3  # KMZ tile size in km (matches PHP)


def kml_groundoverlay(
    name: str,
    wgs84_bounds: tuple[float, float, float, float],
    image_file: str,
) -> str:
    """Build a single KML GroundOverlay document."""
    minlon, minlat, maxlon, maxlat = wgs84_bounds
    return f"""<?xml version="1.0" encoding="UTF-8"?>
<kml xmlns="http://www.opengis.net/kml/2.2">
  <Document>
    <name>{name}</name>
    <GroundOverlay>
      <name>{name}</name>
      <Icon>
        <href>{image_file}</href>
      </Icon>
      <LatLonBox>
        <north>{maxlat}</north>
        <south>{minlat}</south>
        <east>{maxlon}</east>
        <west>{minlon}</west>
      </LatLonBox>
    </GroundOverlay>
  </Document>
</kml>
"""


def crop_3km_tiles(
    img: np.ndarray,
    region: Region,
    px_per_km: float,
) -> list[tuple[np.ndarray, str, tuple[float, float, float, float]]]:
    """Crop the tagged image into 3km x 3km tiles.

    Returns list of (tile_image, tile_name, wgs84_bounds).
    ``tile_name`` is like "3km-H1-V2" — unique within a map batch.
    """
    tiles: list[tuple[np.ndarray, str, tuple[float, float, float, float]]] = []
    tile_px = int(TILE_KM * px_per_km)
    img_h, img_w = img.shape[:2]

    # How many tiles in each direction
    cols = max(1, -(-img_w // tile_px))
    rows = max(1, -(-img_h // tile_px))

    for r in range(rows):
        for c in range(cols):
            x0 = c * tile_px
            y0 = r * tile_px
            x1 = min(x0 + tile_px, img_w)
            y1 = min(y0 + tile_px, img_h)
            tile = img[y0:y1, x0:x1]

            # WGS84 bounds for this tile (from TWD region + pixel offsets)
            # pixel -> TWD meters: x_m = x0 + col_px * 1000/px_per_km
            t_x0 = region.x0 + x0 * 1000.0 / px_per_km
            t_x1 = region.x0 + x1 * 1000.0 / px_per_km
            t_y0 = region.y0 - y0 * 1000.0 / px_per_km
            t_y1 = region.y0 - y1 * 1000.0 / px_per_km

            top_left = twd_to_wgs84(t_x0, t_y0, region.crs)
            bottom_right = twd_to_wgs84(t_x1, t_y1, region.crs)
            bounds = (top_left[0], bottom_right[1], bottom_right[0], top_left[1])

            name = f"3km-H{c+1}-V{r+1}"
            tiles.append((tile, name, bounds))

    return tiles


def write_kmz(
    img: np.ndarray,
    region: Region,
    px_per_km: float,
    out_path: str | Path,
    doc_title: str = "twmap",
) -> Path:
    """Crop the image to 3km tiles, write KML + zipped tiles to a KMZ file."""
    out = Path(out_path)
    tiles = crop_3km_tiles(img, region, px_per_km)

    with zipfile.ZipFile(out, "w", zipfile.ZIP_DEFLATED) as zf:
        doc_files = []
        for i, (tile, name, bounds) in enumerate(tiles):
            tile_path = f"{name}.jpg"
            zf.writestr(tile_path, _encode_jpeg(tile))
            kml_name = f"{name}.kml"
            zf.writestr(
                kml_name,
                kml_groundoverlay(name, bounds, tile_path),
            )
            doc_files.append(kml_name)

        # A single root doc.kml referencing all tiles is appended last as resource
        zf.writestr("doc.kml", _doc_kml(doc_title, doc_files))

    logger.info("Wrote %s (%d tiles)", out, len(tiles))
    return out


def _encode_jpeg(arr: np.ndarray) -> bytes:
    im = Image.fromarray(arr)
    if im.mode != "RGB":
        im = im.convert("RGB")
    import io

    buf = io.BytesIO()
    im.save(buf, "JPEG", quality=85)
    return buf.getvalue()


def _doc_kml(title: str, tile_kmls: Sequence[str]) -> str:
    """Build a root KML that includes the per-tile overlays."""
    entries = "\n".join(f'    <link><href>{k}</href></link>' for k in tile_kmls)
    return f"""<?xml version="1.0" encoding="UTF-8"?>
<kml xmlns="http://www.opengis.net/kml/2.2">
  <Document>
    <name>{title}</name>
    <NetworkLink>
      <name>Tiles</name>
{entries}
    </NetworkLink>
  </Document>
</kml>
"""


__all__ = ["crop_3km_tiles", "write_kmz", "kml_groundoverlay"]
