"""Tests for compositor, grinder, and splitter (Pillow/numpy)."""

import numpy as np
import pytest

from mapgen.compositor import composite_layers
from mapgen.proj import Region
from mapgen.splitter import _pad_to, split_image


def _rgba(h, w, fill=255):
    img = np.zeros((h, w, 4), dtype=np.uint8)
    img[..., 0] = fill
    img[..., 1] = fill
    img[..., 2] = fill
    img[..., 3] = 255
    return img


def test_multiply_composite_darkens():
    a = _rgba(10, 10, fill=128)
    b = _rgba(10, 10, fill=128)
    out = composite_layers([a, b], mode="multiply")
    # (128*128)/255 ≈ 64
    assert out[0, 0, 0] == pytest.approx(64, abs=1)


def test_multiply_white_is_identity():
    a = _rgba(10, 10, fill=100)
    white = _rgba(10, 10, fill=255)
    out = composite_layers([a, white], mode="multiply")
    assert np.all(out[..., :3] == 100)


def test_alpha_composite():
    base = _rgba(10, 10, fill=0)  # black base
    overlay = _rgba(10, 10, fill=255)  # white overlay, opaque
    out = composite_layers([base, overlay], mode="alpha")
    # Fully opaque overlay -> white
    assert np.all(out[..., :3] == 255)


def test_unknown_mode_raises():
    a = _rgba(10, 10)
    b = _rgba(10, 10)
    with pytest.raises(ValueError):
        composite_layers([a, b], mode="nonsense")


def test_composite_no_layers_raises():
    with pytest.raises(ValueError):
        composite_layers([])


def test_split_image_pages_cover():
    """Splitting a 2x2 km image at 315px/km into 1x1km pages yields 4 pages.

    Match PHP: page origins step by the full page size (315px), each crop is
    page + overlap (42px) extending right/bottom, clamped to image extent.
    So the 2x2 tiling is:
        (0,0) 357x357   (1,0) 315x357
        (0,1) 357x315   (1,1) 315x315
    """
    px = 315
    ov = 42
    img = _rgba(px * 2, px * 2)  # 2x2 km, 630x630
    region = Region(300000, 2774000, 302000, 2772000)
    pages = split_image(img, region, px, tiles_w=1, tiles_h=1, overlap_px=ov)
    assert len(pages) == 4
    expected_shapes = [(px + ov, px + ov), (px, px + ov), (px + ov, px), (px, px)]
    for page, (ew, eh) in zip(pages, expected_shapes):
        assert page.shape[0] == eh
        assert page.shape[1] == ew


def test_split_image_no_overlap():
    """With overlap=0, splitting a 2x2km into 1x1 gives four 315px pages."""
    px = 315
    img = _rgba(px * 2, px * 2)
    region = Region(300000, 2774000, 302000, 2772000)
    pages = split_image(img, region, px, tiles_w=1, tiles_h=1, overlap_px=0)
    assert len(pages) == 4
    for p in pages:
        assert p.shape[0] == px
        assert p.shape[1] == px


def test_pad_to_preserves_content():
    img = _rgba(10, 10, fill=50)
    padded = _pad_to(img, 12, 12)
    assert padded.shape == (12, 12, 4)
    # Top-left content preserved (RGB)
    assert np.all(padded[:10, :10, :3] == 50)
    # Padding is white (255)
    assert np.all(padded[10:, :, :3] == 255)
