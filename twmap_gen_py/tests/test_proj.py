"""Tests for coordinate conversion (proj.py)."""

import pytest

from mapgen import config
from mapgen.proj import (
    Region,
    get_twd_crs,
    latlon_to_tile,
    pixel_per_km_for_zoom,
    region_to_tile_range,
    region_to_wgs84_bounds,
    tile_bounds,
    tile_to_latlon,
    twd_to_wgs84,
    wgs84_to_twd,
)


def test_known_point_taipei_roundtrip():
    """A known point near Taipei should roundtrip TWD97 <-> WGS84."""
    crs = get_twd_crs("TWD97", penghu=False)
    # Approx Taipei City Hall: TWD97 ~ (304400, 2770630)
    x, y = 304400.0, 2770630.0
    lon, lat = twd_to_wgs84(x, y, crs)
    # Should be around lon 121.56, lat 25.04
    assert 121.5 < lon < 121.6
    assert 25.0 < lat < 25.1
    x2, y2 = wgs84_to_twd(lon, lat, crs)
    assert x2 == pytest.approx(x, abs=1e-3)
    assert y2 == pytest.approx(y, abs=1e-3)


def test_penghu_crs_differs():
    assert get_twd_crs("TWD97", penghu=False) != get_twd_crs("TWD97", penghu=True)
    assert get_twd_crs("TWD67", penghu=False) == config.CRS_TWD67
    assert get_twd_crs("TWD67", penghu=True) == config.CRS_TWD67_PH


def test_twd67_uses_hu_tzu_shan_datum():
    """TWD67 must use Taiwan's 7-param Hu-Tzu-Shan -> WGS84 shift.

    PROJ's EPSG:3828 maps to WGS84 with a *null* transform (TWD67 georeferenced
    ~like TWD97), which moves every feature ~700 m east / 100 m south of its
    true TWD67 position. The explicit +towgs84 (matching PHP lib/Twmap/Proj.php)
    at (294000, 2748000) must land on (121.4436491, 24.8369669).
    """
    lon, lat = twd_to_wgs84(294000.0, 2748000.0, config.CRS_TWD67)
    assert lon == pytest.approx(121.4436491, abs=1e-6)
    assert lat == pytest.approx(24.8369669, abs=1e-6)
    x, y = wgs84_to_twd(lon, lat, config.CRS_TWD67)
    assert x == pytest.approx(294000.0, abs=0.05)
    assert y == pytest.approx(2748000.0, abs=0.05)


def test_region_properties():
    r = Region(300000, 2774000, 305000, 2769000, datum="TWD67")
    assert r.width_m == 5000
    assert r.height_m == 5000
    assert r.crs == config.CRS_TWD67


def test_tile_math_roundtrip():
    lon, lat = 121.5, 25.0
    z = 16
    x, y = latlon_to_tile(lon, lat, z)
    lon2, lat2 = tile_to_latlon(x, y, z)
    # The top-left of the tile should be <= the input point
    assert lon2 <= lon
    assert lat2 >= lat


def test_tile_bounds_valid():
    x, y, z = 54898, 28050, 16
    minlon, minlat, maxlon, maxlat = tile_bounds(x, y, z)
    assert minlon < maxlon
    assert minlat < maxlat


def test_region_to_tile_range_contains_region():
    r = Region(304400, 2770630, 305400, 2769630)  # 1x1 km
    z = 16
    minx, miny, maxx, maxy = region_to_tile_range(r, z)
    assert maxx >= minx
    assert maxy >= miny
    # Should be a small tile range for 1km (~1-2 tiles)
    assert (maxx - minx + 1) <= 3
    assert (maxy - miny + 1) <= 3


def test_region_to_wgs84_bounds():
    r = Region(304400, 2770630, 305400, 2769630)
    minlon, minlat, maxlon, maxlat = region_to_wgs84_bounds(r)
    assert minlon < maxlon
    assert minlat < maxlat
    # Small 1km region in Taipei should span a small degree extent
    assert (maxlon - minlon) < 0.02
    assert (maxlat - minlat) < 0.02


def test_pixel_per_km():
    assert pixel_per_km_for_zoom(16) == 315
    assert pixel_per_km_for_zoom(17) == 630
    assert pixel_per_km_for_zoom(18) == 1260
    assert pixel_per_km_for_zoom(12) == 315  # fallback
