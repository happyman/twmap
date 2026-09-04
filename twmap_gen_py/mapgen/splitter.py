"""Split a large stitched image into print-sized page tiles.

Replaces the PHP ``Splitter`` class (GD imagecopy). Each page is a
``tiles_w x tiles_h``-km region of the source image at the source's
pixel-per-km density. A small overlap is included on page edges so printed
pages can be taped/joined.
"""

from __future__ import annotations

import logging
import math
from dataclasses import dataclass

import numpy as np
from PIL import Image

from .config import PAGE_OVERLAP_PX

logger = logging.getLogger(__name__)


@dataclass
class PaperSpec:
    """A print paper's configuration within a region."""

    name: str  # e.g. "5x7", "4x6"
    tiles_w: int
    tiles_h: int
    landscape: bool  # True if using A4R/A3R orientation
    px_w: int  # page width in pixels after resize
    px_h: int  # page height in pixels after resize


def determine_type(
    shiftx: int, shifty: int, tiles_w: int, tiles_h: int
) -> tuple[int, int, bool]:
    """Choose portrait or landscape orientation based on fewer pages.

    PHP logic: try portrait (tiles_w x tiles_h per page) vs landscape
    (swapped). Returns (page_tiles_w, page_tiles_h, landscape_flag).
    """
    # Portrait: a page fits tiles_w columns x tiles_h rows
    portrait_pages_w = math.ceil(shiftx / tiles_w)
    portrait_pages_h = math.ceil(shifty / tiles_h)

    # Landscape: page is rotated, so tiles_w & tiles_h swap roles
    landscape_pages_w = math.ceil(shiftx / tiles_h)
    landscape_pages_h = math.ceil(shifty / tiles_w)

    portrait_total = portrait_pages_w * portrait_pages_h
    landscape_total = landscape_pages_w * landscape_pages_h

    if landscape_total < portrait_total:
        return tiles_h, tiles_w, True
    return tiles_w, tiles_h, False


def split_image(
    img: np.ndarray,
    region,
    px_per_km: float,
    tiles_w: int,
    tiles_h: int,
    overlap_px: int = PAGE_OVERLAP_PX,
) -> list[np.ndarray]:
    """Split a full image (covering ``region``) into page tiles.

    ``tiles_w``/``tiles_h`` are the number of 1-km tiles per page. Returns a
    flat list of page arrays in row-major (left->right, top->bottom) order.
    """

    page_w = int(tiles_w * px_per_km)
    page_h = int(tiles_h * px_per_km)
    img_h, img_w = img.shape[:2]

    # Page origins step by the full nominal page size; each page crop is
    # page + overlap (overlap extends right/bottom for taping pages together).
    # Loop while the origin stays within `size - overlap` of the image edge
    # (matches the PHP `for $i < w - fuzzy` boundary).
    cols, rows = 0, 0
    if img_w > overlap_px:
        cols = max(1, ((img_w - overlap_px - 1) // page_w) + 1)
    if img_h > overlap_px:
        rows = max(1, ((img_h - overlap_px - 1) // page_h) + 1)

    pages: list[np.ndarray] = []
    for r in range(rows):
        for c in range(cols):
            x0 = c * page_w
            y0 = r * page_h
            # Crop size includes the join overlap (page + overlap), clamped to
            # the image extent for the last page.
            x1 = min(x0 + page_w + overlap_px, img_w)
            y1 = min(y0 + page_h + overlap_px, img_h)
            crop = img[max(0, y0) : max(0, y1), max(0, x0) : max(0, x1)]
            pages.append(_pad_to(crop, x1 - x0, y1 - y0))
    return pages


def _pad_to(img: np.ndarray, w: int, h: int) -> np.ndarray:
    """Pad an image with white to exactly (w, h) (for last-page edges)."""
    cur_h, cur_w = img.shape[:2]
    if cur_w == w and cur_h == h:
        return img
    pad_w = max(0, w - cur_w)
    pad_h = max(0, h - cur_h)
    out = img
    if pad_w > 0 or pad_h > 0:
        if img.ndim == 2:
            pads = np.full((cur_h + pad_h, cur_w + pad_w), 255, dtype=np.uint8)
        else:
            pads = np.full(
                (cur_h + pad_h, cur_w + pad_w, img.shape[2]), 255, dtype=np.uint8
            )
        pads[:cur_h, :cur_w] = img
        out = pads
    return out


def make_simage(
    page: np.ndarray,
    px_w: int,
    px_h: int,
    grid_info=None,
    index_img: np.ndarray | None = None,
) -> np.ndarray:
    """Resize a page image to exact paper dimensions and add borders/index.

    ``px_w``/``px_h`` are the target paper pixel dimensions (e.g. A4 1492x2110).
    ``grid_info`` (when provided) adds paste-alignment marks and the page's
    grid index in the corner. ``index_img`` is an optional small index overlay.
    """
    im = Image.fromarray(page)
    im = im.resize((px_w, px_h), Image.LANCZOS)
    out = np.array(im)

    if grid_info is not None:
        out = _add_borders(out, grid_info)
    if index_img is not None:
        out = _overlay_index(out, index_img)
    return out


def _add_borders(img: np.ndarray, grid_info) -> np.ndarray:
    """Add paste-alignment markers and grid index to a page image."""
    from PIL import Image, ImageDraw

    from .grinder import _default_font

    im = Image.fromarray(img if img.ndim == 3 else np.stack([img] * 3, -1))
    w, h = im.size
    draw = ImageDraw.Draw(im)
    font = _default_font(24)

    row, col, total_cols, total_rows = (
        grid_info.get("row", 0),
        grid_info.get("col", 0),
        grid_info.get("total_cols", 1),
        grid_info.get("total_rows", 1),
    )

    # Right edge: "黏貼處" vertical marker (not on last column)
    if col < total_cols - 1:
        text = "黏\n\n\n\n貼\n\n\n\n處"
        draw.rectangle([w - 40, 40, w - 8, h - 40], fill=(255, 255, 255))
        _draw_multiline(draw, text, font, w - 34, 60, (0, 0, 0))

    # Bottom edge: horizontal marker (not on last row)
    if row < total_rows - 1:
        text = "黏             貼             處"
        draw.rectangle([40, h - 40, w - 40, h - 8], fill=(255, 255, 255))
        draw.text((60, h - 32), text, font=font, fill=(0, 0, 0))

    # Grid index in SE corner
    idx = row * total_cols + col + 1
    total = total_cols * total_rows
    idx_text = f"{idx}/{total}"
    bbox = draw.textbbox((0, 0), idx_text, font=font)
    tw = bbox[2] - bbox[0]
    th = bbox[3] - bbox[1]
    draw.rectangle([w - tw - 20, h - th - 16, w - 8, h - 8], fill=(255, 255, 255))
    draw.text((w - tw - 12, h - th - 12), idx_text, font=font, fill=(0, 0, 0))

    return np.array(im)


def _draw_multiline(draw, text: str, font, x, y, color):
    line_h = 0
    for line in text.split("\n"):
        draw.text((x, y + line_h), line, font=font, fill=color)
        bbox = draw.textbbox((0, 0), line, font=font)
        line_h += bbox[3] - bbox[1] + 4


def _overlay_index(img: np.ndarray, index_img: np.ndarray) -> np.ndarray:
    """Overlay a small index image onto the page (top-left)."""
    im = Image.fromarray(img)
    idx = Image.fromarray(index_img)
    im.paste(idx, (10, 10), idx if idx.mode == "RGBA" else None)
    return np.array(im)


__all__ = [
    "PaperSpec",
    "determine_type",
    "split_image",
    "make_simage",
]
