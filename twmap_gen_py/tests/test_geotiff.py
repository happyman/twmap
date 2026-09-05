"""Ensure exported GeoTIFFs are LZW-compressed and carry the region CRS.

The frontend (`show.php`) only re-generates a TIFF when ``file`` does not
report ``LZW``; writing compressed output here keeps that check satisfied.
"""

import numpy as np
import pytest
import rasterio
from rasterio.enums import Compression

from mapgen.export.geotiff import write_geotiff
from mapgen.proj import Region


@pytest.fixture()
def region():
    return Region(307000, 2677000, 307000 + 12 * 1000, 2677000 - 6 * 1000, datum="TWD97")


def test_write_geotiff_lzw_compression(tmp_path, region):
    img = np.full((210, 315, 4), 180, dtype=np.uint8)
    out = tmp_path / "m.tag.tiff"
    write_geotiff(img, region, px_per_km=315, out_path=out)
    with rasterio.open(out) as dst:
        assert dst.count == 4
        assert dst.compression == Compression.lzw
        # Region CRS survives the round trip (PHP Geotiff forces EPSG:4326;
        # ours intentionally keeps TWD so coordinates match the map grid).
        assert dst.crs == region.crs


def test_write_geotiff_grayscale_lzw(tmp_path, region):
    img = np.full((105, 210), 100, dtype=np.uint8)
    out = tmp_path / "g.tag.tiff"
    write_geotiff(img, region, px_per_km=315, out_path=out)
    with rasterio.open(out) as dst:
        assert dst.count == 1
        assert dst.compression == Compression.lzw
