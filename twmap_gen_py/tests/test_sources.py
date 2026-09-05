"""Tests for the map source registry (config.py) and stitcher helpers."""

import pytest

from mapgen import config
from mapgen.config import get_source, list_sources
from mapgen.proj import Region
from mapgen.stitcher import _mosaic_origin, _pixel_size_at_zoom, chunk_region


def test_all_sources_present():
    for key in ("3", "2016", "nlsc", "1904", "1916", "1921", "1924"):
        assert key in config.SOURCES


def test_get_source_known():
    s = get_source("2016")
    assert s.label == "魯地圖"
    assert s.pixel_per_km == 315
    assert s.zoom == 16


def test_get_source_unknown_raises():
    with pytest.raises(KeyError):
        get_source("does_not_exist")


def test_list_sources_sorted():
    keys = [k for k, _ in list_sources()]
    assert keys == sorted(keys)


def test_1904_threshold_grayscale():
    s = get_source("1904")
    assert s.grayscale == "adaptive_threshold"


def test_3_enhanced_grayscale():
    s = get_source("3")
    assert s.grayscale == "enhanced"
    assert s.grayscale_params["brightness"] == 20


def test_nlsc_tile_order_yzx():
    s = get_source("nlsc")
    assert s.tile_order == "yzx"
    assert s.zoom == 17
    assert s.pixel_per_km == 630


def test_layer_defs_single():
    s = get_source("2016")
    layers = s.layer_defs()
    assert len(layers) == 1
    assert layers[0].url == s.tile_url


def test_layer_defs_gpx_single_source():
    # PHP `include_gpx` (-G): v3/2016 swap to the `*_nowp_nocache` tile set.
    s = get_source("2016")
    layers = s.layer_defs(include_gpx=True)
    assert len(layers) == 1
    assert layers[0].url == (
        "http://make.happyman.idv.tw/map/moi_happyman_nowp_nocache/{z}/{x}/{y}.png"
    )
    s3 = get_source("3")
    g3 = s3.layer_defs(include_gpx=True)
    assert g3[0].url == (
        "http://make.happyman.idv.tw/map/twmap_happyman_nowp_nocache/{z}/{x}/{y}.png"
    )
    assert len(g3[0].pre_merge) == 2  # Equalize + Gamma kept from pre_merge


def test_layer_defs_gpx_dual_layer():
    # NLSC / archival maps multiply the archive layer with happyman_nowp.
    for key, k0 in (
        ("nlsc", "wmts.nlsc.gov.tw"),
        ("1904", "JM20K_1904"),
        ("1916", "JM50K_1916"),
        ("1921", "JM20K_1921"),
        ("1924", "JM50K_1924"),
    ):
        layers = get_source(key).layer_defs(include_gpx=True)
        assert len(layers) == 2, key
        assert k0 in layers[0].url, key
        assert "happyman_nowp" in layers[1].url, key
        assert layers[1].pre_merge in (None, [])
    # The NLSC archive layer keeps its Level transform in gpx mode; 1921 too.
    nlsc = get_source("nlsc").layer_defs(include_gpx=True)
    assert len(nlsc[0].pre_merge) >= 1 and nlsc[0].tile_order == "yzx"


def test_layer_defs_ignores_gpx_without_variant():
    custom = config.MapSource(name="x", label="X", tile_url="http://x/{z}/{x}/{y}.png")
    layers = custom.layer_defs(include_gpx=True)
    assert len(layers) == 1
    assert layers[0].url == "http://x/{z}/{x}/{y}.png"


def test_chunk_region_single_when_small():
    r = Region(300000, 2774000, 320000, 2754000)  # 20x20km @315 = 6300px
    chunks = chunk_region(r, 315, max_px=15000)
    assert len(chunks) == 1
    assert chunks[0] == r


def test_chunk_region_splits_when_large():
    r = Region(300000, 2774000, 380000, 2694000)  # 80x80km @315 = 25200px
    chunks = chunk_region(r, 315, max_px=15000)
    assert len(chunks) > 1
    # Chunks should tile the original exactly
    total_area = sum(c.width_m * c.height_m for c in chunks)
    assert total_area == pytest.approx(r.width_m * r.height_m)


def test_chunk_region_cover_bounds():
    r = Region(300000, 2774000, 380000, 2694000)
    chunks = chunk_region(r, 315, max_px=15000)
    assert min(c.x0 for c in chunks) == r.x0
    assert max(c.x1 for c in chunks) == r.x1
    assert max(c.y0 for c in chunks) == r.y0
    assert min(c.y1 for c in chunks) == r.y1


def test_pixel_size_at_zoom():
    # Zoom 0 tile is 40075016.68 m wide; at 256px that's ~156543 m/px
    res = _pixel_size_at_zoom(0)
    assert res == pytest.approx(156543.03, rel=1e-3)


def test_mosaic_geotransform_origin():
    minx, maxy = _mosaic_origin(0, 0, 0)
    # Top-left of tile (0,0) at zoom 0 is (-20037508.34, 20037508.34)
    assert minx == pytest.approx(-_pixel_size_at_zoom(0) * 128, rel=1e-3)
    assert maxy == pytest.approx(_pixel_size_at_zoom(0) * 128, rel=1e-3)
