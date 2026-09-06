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


def split_grid(
    img_w: int, img_h: int, page_w: int, page_h: int,
    overlap_px: int = PAGE_OVERLAP_PX,
) -> tuple[int, int]:
    """Number of (cols, rows) pages covering an image at a given page size.

    Page origins step by the full nominal page size; a page is emitted while
    its origin stays within ``size - overlap`` of the image edge (matches the
    PHP `for $i < w - fuzzy` boundary).
    """
    cols, rows = 0, 0
    if img_w > overlap_px:
        cols = max(1, ((img_w - overlap_px - 1) // page_w) + 1)
    if img_h > overlap_px:
        rows = max(1, ((img_h - overlap_px - 1) // page_h) + 1)
    return cols, rows


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

    cols, rows = split_grid(img_w, img_h, page_w, page_h, overlap_px)

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
            # Every page is padded to the full page canvas (page + overlap),
            # like PHP `cropimage`: a partial last row/column gets its white
            # right/bottom fill baked in, so `make_simage` places the (padded)
            # page and the small map lands at the top-left of the paper instead
            # of floating centered.
            pages.append(_pad_to(crop, page_w + overlap_px, page_h + overlap_px))
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
    tiles_w: int,
    tiles_h: int,
    px_per_km: float,
    grid_info=None,
    index_img: np.ndarray | None = None,
) -> np.ndarray:
    """Fit a page image onto a paper pixel canvas at a fixed print scale.

    Mirrors PHP ``im_simage_resize``: a single uniform ratio is derived from
    the page layout (``tiles_w``x``tiles_h`` km) and the paper pixel size, so
    every page of the same dimension prints at the same map scale. The page
    image is resized by that ratio (aspect preserved, no per-axis stretch).

    The resized page is placed on the white canvas like PHP ``make_simages``:
    - multi-page layouts use the ``NorthWest`` gravity (top-left) so every
      page shares the same reference corner and the tiles paste together;
    - a single map (region within one page) uses ``Center``, which after
      split_image's full-page padding still pins small maps to the top-left
      area of the paper.
    ``grid_info`` (when provided) adds paste-alignment marks and the page's
    grid index in the corner. ``index_img`` is an optional small overlay.
    """
    ratio_x = (px_w - PAGE_OVERLAP_PX) / (tiles_w * px_per_km)
    ratio_y = (px_h - PAGE_OVERLAP_PX) / (tiles_h * px_per_km)
    ratio = max(1, int(math.floor(min(ratio_x, ratio_y) * 100)))

    im = Image.fromarray(page).convert("RGBA")
    if ratio != 100:
        nw = max(1, round(im.width * ratio / 100))
        nh = max(1, round(im.height * ratio / 100))
        im = im.resize((nw, nh), Image.LANCZOS)

    canvas = Image.new("RGBA", (px_w, px_h), (255, 255, 255, 255))
    multi = grid_info is not None and (
        grid_info.get("total_cols", 1) * grid_info.get("total_rows", 1) > 1
    )
    if multi:
        canvas.paste(im, (0, 0), im)  # NorthWest: all pages share one corner
    else:
        canvas.paste(im, ((px_w - im.width) // 2, (px_h - im.height) // 2), im)
    out = np.array(canvas)

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

    # Right edge: "黏 貼 處" vertical marker (not on last column), vertically
    # centered on the edge. Mirrors PHP `pango:'黏\n\n\n\n貼\n\n\n\n處'` with
    # `-gravity East`.
    if col < total_cols - 1:
        text = "黏\n\n\n\n\n\n貼\n\n\n\n\n\n處"
        draw.rectangle([w - 40, 40, w - 8, h - 40], fill=(255, 255, 255))
        _draw_multiline_centered(
            draw, text, font, x_center=w - 24, y_center=h / 2, color=(0, 0, 0)
        )

    # Bottom edge: horizontal marker (not on last row), horizontally centered.
    # Mirrors PHP `pango:'黏             貼             處'` with `-gravity South`.
    if row < total_rows - 1:
        text = "黏" + "\u3000" * 8 + "貼" + "\u3000" * 8 + "處"
        draw.rectangle([40, h - 40, w - 40, h - 8], fill=(255, 255, 255))
        _draw_text_centered(
            draw, text, font, x_center=w / 2, y_center=h - 24, color=(0, 0, 0)
        )

    # Page-index grid in the SE corner, confined to the 32px junction where the
    # bottom and right paste strips overlap so it never covers the map.
    # Mirrors PHP `Splitter::imageindex` (grid of page cells, current filled).
    if total_cols * total_rows > 1:
        _draw_page_index(draw, row, col, total_cols, total_rows, w, h)

    return np.array(im)


def _draw_multiline_centered(draw, text, font, x_center, y_center, color):
    """Draw a multi-line string centered around (x_center, y_center)."""
    lines = text.split("\n")
    heights = []
    for line in lines:
        bbox = draw.textbbox((0, 0), line, font=font)
        heights.append(bbox[3] - bbox[1])
    block_h = sum(heights) + 4 * (len(lines) - 1)
    y = y_center - block_h / 2
    for i, line in enumerate(lines):
        bbox = draw.textbbox((0, 0), line, font=font)
        tw = bbox[2] - bbox[0]
        draw.text((x_center - tw / 2 - bbox[0], y - bbox[1]), line, font=font, fill=color)
        y += heights[i] + 4


def _draw_text_centered(draw, text, font, x_center, y_center, color):
    """Draw a single-line string centered around (x_center, y_center)."""
    bbox = draw.textbbox((0, 0), text, font=font)
    tw = bbox[2] - bbox[0]
    th = bbox[3] - bbox[1]
    draw.text(
        (x_center - tw / 2 - bbox[0], y_center - th / 2 - bbox[1]),
        text, font=font, fill=color,
    )


def _draw_page_index(draw, row, col, total_cols, total_rows, w, h):
    """Draw the PHP-style page-index grid diagram in the SE corner.

    Confined to the 32px paste-strip junction (bottom + right strips overlap
    at ``[w-40, w-8] x [h-40, h-8]``) so it never covers the map content.
    Mirrors ``Splitter::imageindex()``: an white grid of page cells with the
    current page filled black and the rest outlined.
    """
    x0 = w - 40
    y0 = h - 40
    inner = 24  # grid area inside the junction, with a small margin
    cell_w = max(1, inner // total_cols)
    cell_h = max(1, inner // total_rows)
    grid_w = cell_w * total_cols
    grid_h = cell_h * total_rows
    gx = x0 + (32 - grid_w) // 2
    gy = y0 + (32 - grid_h) // 2
    draw.rectangle([gx, gy, gx + grid_w - 1, gy + grid_h - 1], fill=(255, 255, 255))
    for j in range(total_rows):
        for i in range(total_cols):
            cx = gx + i * cell_w
            cy = gy + j * cell_h
            if j == row and i == col:
                draw.rectangle(
                    [cx, cy, cx + cell_w - 1, cy + cell_h - 1], fill=(0, 0, 0)
                )
            else:
                draw.rectangle(
                    [cx, cy, cx + cell_w - 1, cy + cell_h - 1], outline=(0, 0, 0)
                )


def _overlay_index(img: np.ndarray, index_img: np.ndarray) -> np.ndarray:
    """Overlay a small index image onto the page (top-left)."""
    im = Image.fromarray(img)
    idx = Image.fromarray(index_img)
    im.paste(idx, (10, 10), idx if idx.mode == "RGBA" else None)
    return np.array(im)


__all__ = [
    "PaperSpec",
    "determine_type",
    "split_grid",
    "split_image",
    "make_simage",
]
