"""Coordinate conversion: TWD67/TWD97 <-> WGS84, and tile XYZ math.

Replaces the PHP `Proj` class (which shelled out to `cs2cs`/`proj`).
Everything here uses `pyproj` transformers built once and cached.

Key coordinate systems:
- TWD97/TWD67: Taiwan Transverse Mercator (TM2). Central meridian 121°E for
  mainland, 119°E for Penghu. Units in meters.
- WGS84 (EPSG:4326): geographic lon/lat in degrees.
- EPSG:3857: Web Mercator, the tile grid used by map servers.
- Tile XYZ: slippy-map tile indices at a given zoom level.
"""

from __future__ import annotations

import math
from dataclasses import dataclass

from pyproj import Transformer

from .config import (
    CRS_MERCATOR,
    CRS_TWD67,
    CRS_TWD67_PH,
    CRS_TWD97,
    CRS_TWD97_PH,
    CRS_WGS84,
)

_cache: dict[tuple, Transformer] = {}

# EPSG 4326 has lon/lat in (lon, lat) x/y order for pyproj transformers created
# with always_xy=True. We consistently use (easting/lon, northing/lat).


def _transformer(src: str, dst: str) -> Transformer:
    key = (src, dst)
    if key not in _cache:
        _cache[key] = Transformer.from_crs(src, dst, always_xy=True)
    return _cache[key]


@dataclass(frozen=True)
class Region:
    """A rectangular TWD region in meters.

    ``x0``/``y0`` is the top-left (northwest) corner; ``x1``/``y1`` the
    bottom-right (southeast) corner. TWD Y increases northward.
    """

    x0: float
    y0: float
    x1: float
    y1: float
    datum: str = "TWD97"
    penghu: bool = False

    @property
    def width_m(self) -> float:
        return self.x1 - self.x0

    @property
    def height_m(self) -> float:
        return self.y0 - self.y1

    @property
    def crs(self) -> str:
        if self.datum == "TWD67":
            return CRS_TWD67_PH if self.penghu else CRS_TWD67
        return CRS_TWD97_PH if self.penghu else CRS_TWD97


def get_twd_crs(datum: str, penghu: bool = False) -> str:
    """Return the EPSG code for the given datum and region."""
    if datum.upper() == "TWD67":
        return CRS_TWD67_PH if penghu else CRS_TWD67
    return CRS_TWD97_PH if penghu else CRS_TWD97


def twd_to_wgs84(x: float, y: float, crs: str) -> tuple[float, float]:
    """Convert (easting, northing) in TWD to (lon, lat) in WGS84."""
    lon, lat = _transformer(crs, CRS_WGS84).transform(x, y)
    return float(lon), float(lat)


def wgs84_to_twd(lon: float, lat: float, crs: str) -> tuple[float, float]:
    """Convert (lon, lat) in WGS84 to (easting, northing) in TWD."""
    x, y = _transformer(CRS_WGS84, crs).transform(lon, lat)
    return float(x), float(y)


def twd_to_mercator(x: float, y: float, crs: str) -> tuple[float, float]:
    """Convert (easting, northing) in TWD to Web Mercator (EPSG:3857) meters."""
    mx, my = _transformer(crs, CRS_MERCATOR).transform(x, y)
    return float(mx), float(my)


def region_to_mercator_bounds(region: Region) -> tuple[float, float, float, float]:
    """Convert a TWD region to (minx, miny, maxx, maxy) in EPSG:3857 meters."""
    tl = twd_to_mercator(region.x0, region.y0, region.crs)
    br = twd_to_mercator(region.x1, region.y1, region.crs)
    # Mercator Y increases northward; TWD corners are top-left & bottom-right
    return tl[0], br[1], br[0], tl[1]


def region_to_wgs84_bounds(region: Region) -> tuple[float, float, float, float]:
    """Convert a TWD region to (minlon, minlat, maxlon, maxlat)."""
    tl = twd_to_wgs84(region.x0, region.y0, region.crs)
    br = twd_to_wgs84(region.x1, region.y1, region.crs)
    return tl[0], br[1], br[0], tl[1]


# --- Tile XYZ math ---


def latlon_to_tile(lon: float, lat: float, zoom: int) -> tuple[int, int]:
    """Convert lon/lat to slippy-map tile (x, y) at zoom."""
    n = 2**zoom
    x = int((lon + 180.0) / 360.0 * n)
    lat_rad = math.radians(lat)
    y = int((1.0 - math.log(math.tan(lat_rad) + 1.0 / math.cos(lat_rad)) / math.pi) / 2.0 * n)
    return x, y


def tile_to_latlon(x: int, y: int, zoom: int) -> tuple[float, float]:
    """Convert tile (x, y) to (lon, lat) of the tile's top-left corner."""
    n = 2**zoom
    lon = x / n * 360.0 - 180.0
    lat_rad = math.atan(math.sinh(math.pi * (1 - 2 * y / n)))
    lat = math.degrees(lat_rad)
    return lon, lat


def tile_bounds(x: int, y: int, zoom: int) -> tuple[float, float, float, float]:
    """Return (minlon, minlat, maxlon, maxlat) covering tile (x, y)."""
    lon0, lat0 = tile_to_latlon(x, y, zoom)
    lon1, lat1 = tile_to_latlon(x + 1, y + 1, zoom)
    return lon0, lat1, lon1, lat0


def region_to_tile_range(region: Region, zoom: int) -> tuple[int, int, int, int]:
    """Return (min_x, min_y, max_x, max_y) tile indices covering a region.

    The range is the minimal bounding box of tiles that fully covers the
    TWD region at the given zoom.
    """
    tl_lon, tl_lat = twd_to_wgs84(region.x0, region.y0, region.crs)
    br_lon, br_lat = twd_to_wgs84(region.x1, region.y1, region.crs)
    tx0, ty0 = latlon_to_tile(tl_lon, tl_lat, zoom)
    tx1, ty1 = latlon_to_tile(br_lon, br_lat, zoom)
    # Normalize ordering (region is NW -> SE, so tx0<=tx1 and ty0<=ty1)
    return min(tx0, tx1), min(ty0, ty1), max(tx0, tx1), max(ty0, ty1)


# --- Pixel-per-km lookup ---

def pixel_per_km_for_zoom(zoom: int) -> int:
    """Return the pixel density (px per km) for a tile zoom level.

    Matches the PHP mapping: 16 -> 315, 17 -> 630, 18 -> 1260.
    """
    if zoom == 18:
        return 1260
    if zoom == 17:
        return 630
    return 315


__all__ = [
    "Region",
    "get_twd_crs",
    "twd_to_wgs84",
    "wgs84_to_twd",
    "twd_to_mercator",
    "region_to_mercator_bounds",
    "region_to_wgs84_bounds",
    "latlon_to_tile",
    "tile_to_latlon",
    "tile_bounds",
    "region_to_tile_range",
    "pixel_per_km_for_zoom",
]
