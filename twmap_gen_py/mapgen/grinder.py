"""Grid lines, coordinate tags, and logo — Pillow-based rendering.

Replaces ImageMagick `-draw line`, `label:... -composite`, and logo
compositing. Operates on numpy RGBA/grayscale arrays.

Coordinate tag layout (matching PHP ``im_tagimage``):
- TWD X-coordinates along bottom (increasing left->right) and top (+1 offset)
- TWD Y-coordinates along left (decreasing top->bottom) and right (-1 offset)
- 44px font at zoom 17, 22px at zoom 16
"""

from __future__ import annotations

import logging
from pathlib import Path

import numpy as np
from PIL import Image, ImageDraw, ImageFont

logger = logging.getLogger(__name__)

# Pixel step between grid lines
def grid_step_px(px_per_km: float, step_m: int) -> float:
    """Pixel spacing between grid lines.

    PHP: step = pixel_per_km / (1000 / step_m). E.g. 100m grid at 315px/km ->
    31.5 px.
    """
    return px_per_km / (1000.0 / step_m)


def draw_grid_lines(
    img: np.ndarray,
    px_per_km: float,
    step_m: int,
    color=(0, 0, 0),
    line_width: int = 1,
) -> np.ndarray:
    """Draw a grid of lines on the image at every ``step_m`` meters.

    Returns a new array. The grid is aligned to multiples of ``step_m``
    meters from the image origin (top-left = TWD (x0, y0)).
    """
    return _draw_lines(img, px_per_km, step_m, color, line_width)


def _draw_lines(
    img: np.ndarray, px_per_km: float, step_m: int, color, line_width
) -> np.ndarray:
    out = _as_rgba_image(img)
    step = grid_step_px(px_per_km, step_m)
    if step < 1:
        step = 1
    w, h = out.size
    draw = ImageDraw.Draw(out)
    x = 0.0
    while x < w:
        draw.line([(x, 0), (x, h)], fill=color, width=line_width)
        x += step
    y = 0.0
    while y < h:
        draw.line([(0, y), (w, y)], fill=color, width=line_width)
        y += step
    return np.array(out)


def _default_font(size: int) -> ImageFont.FreeTypeFont | ImageFont.ImageFont:
    """Find a default font, preferring a found CJK-capable TTF."""
    candidates = [
        "/usr/share/fonts/opentype/noto/NotoSerifCJKtc-Regular.ttc",
        "/usr/share/fonts/opentype/noto/NotoSansCJK-Regular.ttc",
        "/usr/share/fonts/truetype/noto/NotoSansCJK-Regular.ttc",
        "/usr/share/fonts/truetype/wqy/wqy-zenhei.ttc",
        "/usr/share/fonts/wenquanyi/wqy-zenhei/wqy-zenhei.ttc",
    ]
    for path in candidates:
        if Path(path).exists():
            return ImageFont.truetype(path, size)
    return ImageFont.load_default()


def tag_coordinates(
    img: np.ndarray,
    region,
    px_per_km: float,
    font_size: int | None = None,
    label_color=(0, 0, 0),
    bg_color=(255, 255, 255),
) -> np.ndarray:
    """Add TWD coordinate labels around all four edges of the image.

    ``region`` is a ``mapgen.proj.Region`` (TWD corners in meters).
    ``px_per_km`` is the image's density. Returns a new array.

    Labels are drawn at each 1km boundary matching the PHP spacing, with a
    white margin box for legibility.
    """

    out = _as_rgba_image(img)
    w, h = out.size
    if font_size is None:
        font_size = 44 if px_per_km >= 630 else 22
    font = _default_font(font_size)
    draw = ImageDraw.Draw(out)

    step_px = px_per_km  # 1km => px_per_km pixels

    def _measure(draw, text, font):
        """Return (width, ink_top, ink_bottom) of ``text``.

        The ink extents are relative to the ``y`` passed to ``_draw_label``
        (i.e. relative to the label's top-left). FreeType renders ink a few px
        away from ``textbbox``, so placement must probe the real ink instead of
        trusting the metrics, or edge labels get clipped (bottom X labels
        previously extended past the image bottom edge).
        """
        bb = draw.textbbox((0, 0), text, font=font)
        pw, ph = max(1, bb[2] - bb[0]), max(1, bb[3] - bb[1])
        probe = Image.new("L", (pw, ph), 255)
        ImageDraw.Draw(probe).text((-bb[0], -bb[1]), text, font=font, fill=0)
        a = np.array(probe)
        ink = a < 128
        rows = np.where(ink.any(axis=1))[0]
        if not len(rows):
            return bb[2] - bb[0], 0, max(1, ph)
        return bb[2] - bb[0], 2 * bb[1] + int(rows.min()), 2 * bb[1] + int(rows.max())

    # Bottom edge: X coordinates increasing left->right
    x_km = region.x0 / 1000.0
    x_px = 0.0
    while x_px < w:
        label = f"{int(round(x_km)):d}"
        tw, _, ink_bottom = _measure(draw, label, font)
        # Place the label to the RIGHT of its vertical grid line so the line
        # never runs through the text (PHP puts it at i+1).
        bx = x_px + 2
        # Ink bottom sits `bottom_margin` px above the image edge so the
        # digits are fully visible (the old h - th - 2 pushed them past it).
        bottom_margin = 4
        by = h - bottom_margin - ink_bottom
        _draw_label(draw, label, font, bx, by, label_color, bg_color)
        x_px += step_px
        f = int(x_km) + 1
        x_km = float(f)

    # Top edge: X coordinates offset +1 (same labels, avoid bottom duplicates)
    x_km = region.x0 / 1000.0 + 1.0
    x_px = step_px  # start one km over to differ from bottom column
    while x_px < w:
        label = f"{int(round(x_km)):d}"
        tw, _, _ = _measure(draw, label, font)
        bx = x_px + 2
        _draw_label(draw, label, font, bx, 0, label_color, bg_color)
        x_px += step_px
        f = int(x_km) + 1
        x_km = float(f)

    # Left edge: Y coordinates decreasing top->bottom
    y_km = region.y0 / 1000.0
    y_px = 0.0
    while y_px < h:
        label = f"{int(round(y_km)):d}"
        tw, ink_top, ink_bottom = _measure(draw, label, font)
        # Below the horizontal grid line (PHP places it at i+1); the label
        # x stays at the image's left edge.
        by = y_px + 2
        _draw_label(draw, label, font, 2, by, label_color, bg_color)
        y_px += step_px
        f = int(y_km) - 1
        y_km = float(f)

    # Right edge: Y coordinates offset -1
    y_km = region.y0 / 1000.0 - 1.0
    y_px = step_px
    while y_px < h:
        label = f"{int(round(y_km)):d}"
        tw, ink_top, ink_bottom = _measure(draw, label, font)
        by = y_px + 2
        _draw_label(draw, label, font, w - tw - 2, by, label_color, bg_color)
        y_px += step_px
        f = int(y_km) - 1
        y_km = float(f)

    return np.array(out)


def _draw_label(draw, text, font, x, y, color, bg_color):
    bbox = draw.textbbox((0, 0), text, font=font)
    tw = bbox[2] - bbox[0]
    th = bbox[3] - bbox[1]
    pad = 3
    draw.rectangle(
        [(x - pad, y - pad + bbox[1]), (x + tw + pad, y + th + pad + bbox[1])],
        fill=bg_color,
    )
    draw.text((x + bbox[0], y + bbox[1]), text, font=font, fill=color)


def composite_logo(
    img: np.ndarray,
    text: str,
    font_path: str | None = None,
    font_size: int = 26,
    line_spacing: int | None = None,
) -> np.ndarray:
    """Stamp a text logo in the northeast (top-right) corner.

    ``text`` may contain ``\\n`` to stack multiple lines (e.g. datum on the
    first line, source label beneath it). ``line_spacing`` sets the vertical
    gap between lines (default ~60% of the font size, wider than the old
    ``font_size // 8`` so two-line titles like ``TWD67\\n魯地圖`` breathe).
    Returns a new array.
    """
    out = _as_rgba_image(img)
    w, h = out.size
    if font_path:
        font = ImageFont.truetype(font_path, font_size)
    else:
        font = _default_font(font_size)
    draw = ImageDraw.Draw(out)
    if line_spacing is None:
        line_spacing = max(6, int(font_size * 0.6))
    lines = [line for line in text.split("\n") if line]
    widths = []
    heights = []
    for line in lines:
        bbox = draw.textbbox((0, 0), line, font=font)
        widths.append(bbox[2] - bbox[0])
        heights.append(bbox[3] - bbox[1])
    tw = max(widths)
    th = sum(heights) + line_spacing * (len(lines) - 1)
    pad = 8
    x0 = w - tw - pad * 2
    y0 = pad
    draw.rectangle(
        [(x0, y0), (x0 + tw + pad * 2, y0 + th + pad * 2)], fill=(255, 255, 255)
    )
    cy = y0 + pad
    for line, lh in zip(lines, heights):
        draw.text((x0 + pad, cy), line, font=font, fill=(0, 0, 0))
        cy += lh + line_spacing
    return np.array(out)


def optimize_png(path: Path, pngquant: str | None = None) -> None:
    """Optimize a PNG in place using pngquant if available.

    ``pngquant`` is the binary path/name; if None or not found, the file is
    left unchanged (no crash). Mirrors the PHP pipeline: quantize only when the
    result still meets a quality 65-95 bar, otherwise leave the file untouched
    (colorful maps like v3 keep full color depth; pngquant exits non-zero).
    """
    import shutil
    import subprocess
    import tempfile

    pngquant = pngquant or shutil.which("pngquant")
    if not pngquant:
        logger.info("pngquant not found, skipping PNG optimization")
        return
    with tempfile.TemporaryDirectory() as td:
        out = Path(td) / "opt.png"
        try:
            subprocess.run(
                [pngquant, "--speed", "1", "--quality", "65-95", "--output", str(out), str(path)],
                check=True,
                capture_output=True,
            )
            if out.exists():
                import os

                os.replace(out, path)
                logger.info("Optimized %s with pngquant", path)
        except subprocess.CalledProcessError:
            logger.info("pngquant skipped %s (below quality 65)", path)


def _as_rgba_image(arr: np.ndarray) -> Image.Image:
    """Convert numpy array (any channel count) to an RGBA PIL Image."""
    if arr.ndim == 2:
        arr = np.stack([arr, arr, arr], axis=-1)
    if arr.shape[-1] == 3:
        alpha = np.full(arr.shape[:2], 255, dtype=np.uint8)
        arr = np.concatenate([arr, alpha[..., None]], axis=-1)
    return Image.fromarray(arr, "RGBA")


__all__ = [
    "draw_grid_lines",
    "grid_step_px",
    "tag_coordinates",
    "composite_logo",
    "optimize_png",
]
