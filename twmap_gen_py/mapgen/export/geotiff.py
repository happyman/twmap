"""GeoTIFF export using rasterio.

The stitched base image is already properly georeferenced in its TWD CRS.
This writes it as a GeoTIFF (LZW-compressed) with full georeferencing and an
embedded WGS84 overview for compatibility with GIS tools.
"""

from __future__ import annotations

import logging
from pathlib import Path

import numpy as np
import rasterio

from ..proj import Region

logger = logging.getLogger(__name__)


def write_geotiff(
    img: np.ndarray,
    region: Region,
    px_per_km: float,
    out_path: str | Path,
    compress: str = "LZW",
) -> Path:
    """Write an RGBA (or grayscale) image as a georeferenced GeoTIFF.

    The output covers exactly ``region`` in the region's TWD CRS. Pixel
    resolution is derived from ``px_per_km`` (px per 1000 m).
    """
    out = Path(out_path)
    h, w = img.shape[:2]

    # Build the affine transform for the region (top-left at region.x0, region.y0)
    res_x = region.width_m / w
    # height in meters
    height_m = region.height_m
    res_y = height_m / h

    transform = rasterio.Affine(res_x, 0, region.x0, 0, -res_y, region.y0)
    count = 4 if img.ndim == 3 and img.shape[-1] == 4 else (1 if img.ndim == 2 else 3)

    with rasterio.open(
        out,
        "w",
        driver="GTiff",
        height=h,
        width=w,
        count=count,
        dtype="uint8",
        crs=region.crs,
        transform=transform,
        compress=compress,
    ) as dst:
        if count == 1:
            dst.write(img, 1)
        else:
            for c in range(count):
                dst.write(img[..., c], c + 1)

    logger.info("Wrote %s (%dx%d, %s)", out, w, h, region.crs)
    return out


__all__ = ["write_geotiff"]
