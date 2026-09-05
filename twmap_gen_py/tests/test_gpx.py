"""Tests for the GPX overlay (gpx2svg.py)."""

import numpy as np
import pytest

from mapgen.gpx2svg import (
    apply_gpx_overlay,
    parse_gpx,
    render_overlay_to_image,
    render_overlay_to_svg,
)
from mapgen.proj import Region

# A tiny GPX with one track (2 segments) and 2 waypoints, all inside the region.
_GPX = """<?xml version="1.0" encoding="UTF-8"?>
<gpx version="1.1" creator="opencode" xmlns="http://www.topografix.com/GPX/1/1">
  <trk>
    <name>測試路線</name>
    <trkseg>
      <trkpt lat="24.784" lon="121.011"><ele>500</ele></trkpt>
      <trkpt lat="24.789" lon="121.016"><ele>600</ele></trkpt>
      <trkpt lat="24.794" lon="121.021"><ele>700</ele></trkpt>
    </trkseg>
  </trk>
  <wpt lat="24.786" lon="121.012"><name>甲</name></wpt>
  <wpt lat="24.7915" lon="121.017"><name>乙</name></wpt>
</gpx>
"""


@pytest.fixture(scope="module")
def region():
    return Region(250000, 2743650, 252000, 2741650, datum="TWD67")


@pytest.fixture(scope="module")
def overlay(region):
    _write(_GPX)
    return parse_gpx(
        "/tmp/opencode_test.gpx",
        region_px=(200, 200),
        region=region,
        label_trk=1,
        label_wpt=3,
    )


def _write(text: str):
    from pathlib import Path

    Path("/tmp/opencode_test.gpx").write_text(text, encoding="utf-8")


def test_parse_gpx_counts(overlay):
    assert len(overlay.track_segments) == 1
    assert len(overlay.track_points) == 3
    assert len(overlay.waypoints) == 2
    assert overlay.label_trk_text == "測試路線"
    assert overlay.bbox_wgs84 is not None


def test_points_project_inside_image(overlay):
    for seg in overlay.track_segments:
        for x, y, ele in seg:
            assert 0 <= x <= 200
            assert 0 <= y <= 200
            assert ele >= 0
    for wpt in overlay.waypoints:
        x, y = wpt["px"]
        assert 0 <= x <= 200
        assert 0 <= y <= 200


def test_render_overlay_has_colored_pixels(overlay):
    img = render_overlay_to_image(overlay, 200, 200, draw_labels=True)
    assert img.shape == (200, 200, 4)
    assert img.dtype == np.uint8
    n = np.count_nonzero(img[..., 3] > 0)
    assert n > 0, "no track/waypoint pixels rendered"


def test_render_overlay_no_points_is_blank(region):
    _write('<?xml version="1.0"?><gpx version="1.1"></gpx>')
    ov = parse_gpx(
        "/tmp/opencode_test.gpx", region_px=(200, 200), region=region, label_trk=0, label_wpt=0
    )
    img = render_overlay_to_image(ov, 200, 200)
    assert np.count_nonzero(img[..., 3] > 0) == 0


def test_apply_overlay_composites_on_base(overlay):
    base = np.full((200, 200, 3), 200, np.uint8)
    ov_img = render_overlay_to_image(overlay, 200, 200, draw_labels=True)
    out = apply_gpx_overlay(base, ov_img)
    assert out.ndim == 3 and out.shape[2] == 4

    mask = ov_img[..., 3] > 0
    diff = np.abs(out[..., :3].astype(int) - 200).sum(axis=2)
    assert np.count_nonzero(mask & (diff > 10)) > 0, "tracks not drawn on output"
    # Background must be untouched where there is no overlay.
    assert np.count_nonzero(out[..., :3] == 200) == 3 * 200 * 200 - 3 * np.count_nonzero(mask)


def test_render_overlay_to_svg_contains_lines(overlay):
    svg = render_overlay_to_svg(overlay, 200, 200)
    assert "<svg" in svg
    assert "<line" in svg
    assert "<text" in svg
    assert "乙" in svg


def test_elevation_colors_map(overlay):
    from mapgen.gpx2svg import _elevation_color

    assert _elevation_color(500, 500, 700) == (255, 0, 255)  # t=0 -> magenta
    assert _elevation_color(700, 500, 700) == (255, 0, 255)  # t=1 -> magenta
    assert _elevation_color(550, 500, 700) == (255, 128, 0)  # t=0.25 halfway red->yellow
