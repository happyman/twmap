"""Tests for compositor, grinder, and splitter (Pillow/numpy)."""

import numpy as np
import pytest

from mapgen.compositor import composite_layers
from mapgen.proj import Region
from mapgen.splitter import _pad_to, determine_type, make_simage, split_image


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


def test_multiply_keeps_base_through_transparent_overlay():
    # Transparent overlay pixels often have RGB 0; a raw RGB multiply would
    # blacken the base. IM `composite -compose Multiply` shows the base where
    # the overlay is transparent (NLSC/archival -G layers).
    base = _rgba(10, 10, fill=200)
    overlay = np.zeros((10, 10, 4), dtype=np.uint8)  # fully transparent
    out = composite_layers([base, overlay], mode="multiply")
    assert np.all(out[..., :3] == 200)
    assert np.all(out[..., 3] == 255)


def test_multiply_opaque_overlay_darkens():
    base = _rgba(10, 10, fill=200)
    overlay = _rgba(10, 10, fill=100)
    out = composite_layers([base, overlay], mode="multiply")
    assert out[0, 0, 0] == pytest.approx((200 * 100) / 255, abs=1)


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
    page + overlap (42px) extending right/bottom, and every page -- including
    the partial last row/column -- is padded to the full page canvas (PHP
    `cropimage`), so the 2x2 tiling is four 357x357 pages with the clamped
    content kept at top-left and white elsewhere.
    """
    px = 315
    ov = 42
    img = _rgba(px * 2, px * 2, fill=100)  # 2x2 km, 630x630
    region = Region(300000, 2774000, 302000, 2772000)
    pages = split_image(img, region, px, tiles_w=1, tiles_h=1, overlap_px=ov)
    assert len(pages) == 4
    for page in pages:
        assert page.shape == (px + ov, px + ov, 4)
    # Partial page (1,1): 315x315 content at top-left, white pad right/bottom.
    last = pages[-1]
    assert np.all(last[:px, :px, :3] == 100)
    assert np.all(last[px:, :, :3] == 255)
    assert np.all(last[:, px:, :3] == 255)


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


def test_determine_type_picks_landscape_for_wide_region():
    """A 7km-wide x 5km-tall region with 5x7 pages must print landscape."""
    tw, th, landscape = determine_type(7, 5, 5, 7)
    assert landscape is True
    assert (tw, th) == (7, 5)  # grid rotated to fit one landscape page


def test_determine_type_portrait_for_small_region():
    tw, th, landscape = determine_type(3, 3, 5, 7)
    assert landscape is False
    assert (tw, th) == (5, 7)


def test_make_simage_keeps_scale_for_small_map():
    """A region smaller than a page keeps its print scale and lands near the
    top-left of the paper (PHP parity), never floating centered or stretched.

    split_image pads the page to the full page canvas (content top-left +
    white right/bottom); center-placing that padded page pins the 2x2 km map
    to the paper's top-left area at the same 92% layout ratio as full pages.
    """
    px = 315
    page = _pad_to(_rgba(2 * px, 2 * px, fill=100), 5 * px + 42, 7 * px + 42)
    out = make_simage(page, 1492, 2110, 5, 7, px)
    assert out.shape == (2110, 1492, 4)
    # Content is the non-white region
    ys, xs = np.where(out[..., :3].min(axis=-1) != 255)
    h = ys.max() - ys.min() + 1
    w = xs.max() - xs.min() + 1
    # 630 * 92% = 580 (aspect preserved, square)
    assert abs(h - 580) <= 3 and abs(w - 580) <= 3
    # Near the top-left corner, NOT floating on the paper center
    assert xs.min() < 60 and ys.min() < 80
    assert xs.max() < 1492 // 2 and ys.max() < 2110 // 2


def test_make_simage_full_page_same_scale():
    """A full 5x7 page crop must land at the same 92% layout ratio."""
    px = 315
    page = _rgba(7 * px, 5 * px + 42, fill=100)  # full height page + overlap
    out = make_simage(page, 1492, 2110, 5, 7, px)
    ys, xs = np.where(out[..., :3].min(axis=-1) != 255)
    # (5*315+42)*92% = 1488 wide; 7*315*92% = 2029 tall (aspect preserved)
    assert abs((xs.max() - xs.min() + 1) - 1488) <= 3
    assert abs((ys.max() - ys.min() + 1) - 2029) <= 3


def test_make_simage_multi_page_aligns_northwest():
    """Multi-page layouts paste each page at the top-left (PHP NorthWest).

    Partial pages (a small last-column map) must start from the paper's
    corner, so neighboring tiles paste together at the same reference corner
    instead of each floating centered on its own page.
    """
    px = 315
    page = _pad_to(_rgba(3 * px, 2 * px, fill=100), 5 * px + 42, 7 * px + 42)
    out = make_simage(
        page, 1492, 2110, 5, 7, px,
        grid_info={"row": 0, "col": 1, "total_cols": 2, "total_rows": 1},
    )
    mask = out[..., :3].min(axis=-1) != 255
    # Exclude the 42px paste-strip area (the SE junction index lives there)
    ys, xs = np.where(mask[: 2110 - 42, : 1492 - 42])
    # Top-left pinned (NW), not centered; 2x3 km content at 92% = 580x869
    assert xs.min() <= 1 and ys.min() <= 1
    assert abs((xs.max() - xs.min() + 1) - 580) <= 3
    assert abs((ys.max() - ys.min() + 1) - 869) <= 3


def _dark_page_mask(out):
    return out[..., :3].sum(axis=-1) < 400


def test_single_page_has_no_paste_marks_or_index():
    """A one-page map must be printed bare (no paste markers, no index),
    mirroring PHP `make_simages` early-return for a single page."""
    page = _rgba(7 * 315, 5 * 315 + 42, fill=255)
    out = make_simage(page, 1492, 2110, 5, 7, 315,
                      grid_info={"row": 0, "col": 0, "total_cols": 1, "total_rows": 1})
    assert _dark_page_mask(out).sum() < 100


def test_multi_page_paste_markers_centered_and_index_drawn():
    """Multi-page maps get centered paste markers (bottom/right) plus a
    page-grid index confined to the SE paste-strip junction; markers must not
    appear on the last row/column, and the index must not cover the map."""
    page = _rgba(5 * 315, 5 * 315, fill=255)
    h, w = 2110, 1492

    # First page (row=0, col=0): both markers + index.
    out = make_simage(page, 1492, 2110, 5, 5, 315,
                      grid_info={"row": 0, "col": 0, "total_cols": 3, "total_rows": 2})
    dark = _dark_page_mask(out)
    # Bottom marker: ink near the bottom edge, horizontally centered.
    bottom = dark[h - 38:h - 10]
    xs = np.where(bottom[:, :w - 44].any(axis=0))[0]
    assert abs((xs.min() + xs.max()) / 2 - w / 2) <= 8
    # Right marker: ink near the right edge, vertically centered.
    right = dark[:, w - 44:w - 8]
    ys = np.where(right[:h - 44].any(axis=1))[0]
    assert abs((ys.min() + ys.max()) / 2 - h / 2) <= 8
    # The page-grid index lives fully inside the 32px strip junction
    # (w-40..w-8, h-40..h-8): some dark cell grid there...
    junction = dark[h - 40:h - 8, w - 40:w - 8]
    assert 0.03 < junction.mean() < 0.5
    # ...and NOTHING dark between the junction and the map content edge:
    # the 100m band just inside the strips (h-140..h-44 / w-140..w-44) stays
    # blank on this white page.
    assert dark[h - 140:h - 44, w - 140:w - 44].sum() < 100

    # Last-row page (row=1, col=0): no bottom marker, right marker still there.
    out = make_simage(page, 1492, 2110, 5, 5, 315,
                      grid_info={"row": 1, "col": 0, "total_cols": 3, "total_rows": 2})
    dark = _dark_page_mask(out)
    xs = np.where(dark[h - 38:h - 10, :w - 44].any(axis=0))[0]
    assert len(xs) == 0
    ys = np.where(dark[:, w - 44:w - 8][:h - 44].any(axis=1))[0]
    assert len(ys) > 0


def test_composite_logo_multiline_spacing():
    """A two-line logo must render with a wider-than-old inter-line gap."""
    from mapgen.grinder import composite_logo

    base = np.full((240, 320, 3), 200, np.uint8)
    out = composite_logo(base, "TWD67\n魯地圖", font_size=26)
    dark = out.sum(axis=-1) < 400
    cols = np.where(np.any(dark, axis=0))[0]
    band = dark[:, cols.min():cols.max() + 1]
    hits = band.any(axis=1)
    # Two text bands separated by a white gap of at least 8px (the old
    # `font_size // 8` = 3px gap would fail this).
    gaps = []
    in_white = False
    start = 0
    for i, v in enumerate(hits):
        if not v and not in_white:
            in_white, start = True, i
        elif v and in_white:
            gaps.append(i - start)
            in_white = False
    assert len(gaps) >= 2          # top pad + inter-line gap
    assert gaps[-2] >= 8           # the inter-line gap itself
