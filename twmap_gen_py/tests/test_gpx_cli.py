"""Integration tests for wiring the GPX overlay into ``cmd_make``.

These avoid the (flaky) live tile download by stubbing ``build_base_image``
so the whole CLI pipeline, including GPX parse -> render -> composite, runs
deterministically on a synthetic base image.
"""

import numpy as np

from mapgen import cli

_GPX = """<?xml version="1.0" encoding="UTF-8"?>
<gpx version="1.1" creator="opencode" xmlns="http://www.topografix.com/GPX/1/1">
  <trk>
    <name>測試路線</name>
    <trkseg>
      <trkpt lat="24.79" lon="121.004"><ele>500</ele></trkpt>
      <trkpt lat="24.795" lon="121.009"><ele>600</ele></trkpt>
      <trkpt lat="24.80" lon="121.014"><ele>700</ele></trkpt>
    </trkseg>
  </trk>
  <wpt lat="24.793" lon="121.005"><name>甲</name></wpt>
</gpx>
"""


async def _fake_base_image(region, source, workdir, *a, **k):
    # A 315x315 solid-color RGB base in the region's pixel space.
    h = w = 315
    base = np.full((h, w, 3), 128, np.uint8)
    return base


def _make_args(output, gpx, region="250000,2743650,2,2,TWD67"):
    return cli.build_parser().parse_args(
        [
            "make",
            "--region",
            region,
            "--output",
            output,
            "--map-type",
            "2016",
            "--gpx",
            gpx,
            "--tmpdir",
            output,
        ]
    )


def test_cmd_make_applies_gpx(tmp_path, monkeypatch, capsys):
    from PIL import Image

    import mapgen.stitcher as stitcher

    monkeypatch.setattr(stitcher, "build_base_image", _fake_base_image)
    # Quiet the INFO logs.
    import logging

    logging.disable(logging.CRITICAL)

    gpx_path = tmp_path / "track.gpx"
    gpx_path.write_text(_GPX, encoding="utf-8")

    out = tmp_path / "out"
    args = _make_args(str(out), f"{gpx_path}:1:3")

    cli.cmd_make(args)

    # Mirrors the PHP output set: tag.png + exports + cmd/txt metadata.
    tag = out / "250000x2743650-2x2-v2016_TWD67.tag.png"
    pdf = out / "250000x2743650-2x2-v2016_TWD67.pdf"
    kmz = out / "250000x2743650-2x2-v2016_TWD67.tag.kmz"
    tiff = out / "250000x2743650-2x2-v2016_TWD67.tag.tiff"
    cmd = out / "250000x2743650-2x2-v2016_TWD67.cmd"
    txt = out / "250000x2743650-2x2-v2016_TWD67.txt"
    gray = out / "250000x2743650-2x2-v2016_TWD67.gray.png"
    assert tag.exists()
    assert pdf.exists()
    assert kmz.exists()
    assert tiff.exists()
    assert cmd.exists()
    assert txt.exists()

    # Gray PNG is a temp image and is cleaned up (like PHP).
    assert not gray.exists()

    # The `.cmd` file records the invocation (the callback curl line is only
    # appended when `-a` is present, matching PHP).
    cmd_text = cmd.read_text(encoding="utf-8")
    assert cmd_text.strip(), "cmd file should record the invocation"

    # The `.txt` holds the split metadata JSON.
    import json as _json

    info = _json.loads(txt.read_text(encoding="utf-8"))
    assert {"dim", "paper", "count"} <= set(info)

    # The tagged map still contains the colored (elevation) GPX overlay.
    g = np.array(Image.open(tag).convert("RGB"))
    colored = np.count_nonzero(
        (np.abs(g[..., 0].astype(int) - g[..., 1]) + np.abs(g[..., 1].astype(int) - g[..., 2])) > 20
    )
    assert colored > 0, "GPX overlay not visible on the tagged output"

    import logging

    logging.disable(logging.NOTSET)


def test_gpx_arg_parsing(tmp_path):
    gpx_path = tmp_path / "t.gpx"
    gpx_path.write_text(_GPX, encoding="utf-8")
    spec = f"{gpx_path}:1:2"
    p = cli._parse_gpx_arg(spec)
    assert p["path"] == str(gpx_path)
    assert p["label_trk"] == 1
    assert p["label_wpt"] == 2
