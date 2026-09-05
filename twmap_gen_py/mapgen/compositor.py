"""Layer compositing for multi-layer map sources.

Supports ``multiply`` (matches the PHP `composite -compose Multiply`), plus
``alpha`` (standard Porter-Duff source-over) and ``overlay`` / ``screen``
modes for future transparency work.

GPX track overlays can be alpha-blended onto the base map, decoupling them
from the grayscale conversion of the background.
"""

from __future__ import annotations

import numpy as np


def _rgba_float(img: np.ndarray) -> tuple[np.ndarray, np.ndarray]:
    """Split an RGBA uint8 image into (rgb float32, alpha float32 0..1)."""
    if img.shape[-1] == 4:
        rgb = img[..., :3].astype(np.float32)
        alpha = img[..., 3].astype(np.float32) / 255.0
    else:
        rgb = img[..., :3].astype(np.float32)
        alpha = np.ones(img.shape[:2], dtype=np.float32)
    return rgb, alpha


def _to_rgba(img: np.ndarray) -> np.ndarray:
    """Ensure an array is RGBA uint8 with alpha channel at 255."""
    if img.ndim == 2:
        img = np.stack([img, img, img], axis=-1)
    if img.shape[-1] == 3:
        alpha = np.full(img.shape[:2], 255, dtype=np.uint8)
        return np.concatenate([img, alpha[..., None]], axis=-1)
    return img


def _from_rgba(rgb: np.ndarray, alpha: np.ndarray) -> np.ndarray:
    """Build RGBA uint8 from rgb float32 and alpha float32."""
    rgb_u = rgb.clip(0, 255).astype(np.uint8)
    alpha_u = (alpha.clip(0, 1) * 255).astype(np.uint8)
    return np.concatenate([rgb_u, alpha_u[..., None]], axis=-1)





def _screen(a_rgb: np.ndarray, b_rgb: np.ndarray) -> np.ndarray:
    return 255.0 - ((255.0 - a_rgb) * (255.0 - b_rgb)) / 255.0


def _overlay(a_rgb: np.ndarray, b_rgb: np.ndarray) -> np.ndarray:
    """Standard overlay blend."""
    low = (2.0 * a_rgb * b_rgb) / 255.0
    high = 255.0 - 2.0 * (255.0 - a_rgb) * (255.0 - b_rgb) / 255.0
    norm = a_rgb / 255.0
    mask = (norm < 0.5)[..., None]
    return np.where(mask, low, high)


def composite_layers(
    layers: list[np.ndarray],
    mode: str = "multiply",
    opacity: float = 1.0,
) -> np.ndarray:
    """Composite a list of RGBA images.

    ``mode`` is "multiply", "screen", "overlay", or "alpha".
    ``opacity`` scales the influence of the last layer during alpha blending.

    All layers are resized to the first layer's dimensions before compositing
    (they should already match since they're reprojected to the same region).

    Returns an RGBA uint8 array.
    """
    if not layers:
        raise ValueError("composite_layers requires at least one layer")

    base = _to_rgba(layers[0])
    ref_rgb, ref_alpha = _rgba_float(base)

    for layer in layers[1:]:
        lyr = _to_rgba(layer)
        # Resize to match base dimensions if needed
        if lyr.shape[:2] != base.shape[:2]:
            lyr = _resize(lyr, base.shape[:2])
        rgb, alpha = _rgba_float(lyr)

        if mode == "multiply":
            # IM `composite -compose Multiply` semantics: multiply colors where
            # the overlay is opaque, but show the base through transparent
            # overlay pixels (transparent overlay RGB is often 0, so a raw
            # multiply would blacken the whole image). The overlay adds no
            # coverage, so the base alpha is kept.
            blend = 1.0 + alpha[..., None] * (rgb / 255.0 - 1.0)
            ref_rgb = ref_rgb * blend
        elif mode == "screen":
            ref_rgb = _screen(ref_rgb, rgb)
        elif mode == "overlay":
            ref_rgb = _overlay(ref_rgb, rgb)
        elif mode == "alpha":
            # Source-over compositing: out = a*(1-a_b) + b*a_b
            a_contrib = ref_alpha * (1.0 - alpha) * opacity
            b_contrib = alpha
            total = a_contrib + b_contrib
            safe = np.where(total > 0, total, 1.0)
            ref_rgb = (
                ref_rgb * a_contrib[..., None] + rgb * b_contrib[..., None]
            ) / safe[..., None]
            ref_alpha = np.clip(a_contrib + b_contrib, 0, 1)
        else:
            raise ValueError(f"Unknown composite mode: {mode!r}")

    return _from_rgba(ref_rgb, ref_alpha)


def _resize(img: np.ndarray, shape: tuple[int, int]) -> np.ndarray:
    """Resize RGBA uint8 array to (h, w) using Pillow (Lanczos)."""
    from PIL import Image

    h, w = shape
    im = Image.fromarray(img)
    im = im.resize((w, h), Image.LANCZOS)
    return np.array(im)


def alpha_overlay_gpx(
    base: np.ndarray, overlay: np.ndarray, opacity: float = 0.9
) -> np.ndarray:
    """Alpha-blend a GPX overlay onto the base map.

    A future replacement for the current "bake GPX into the color image before
    grayscale" approach. ``overlay`` is an RGBA image; only its non-transparent
    pixels override the base.
    """
    return composite_layers([base, overlay], mode="alpha", opacity=opacity)


__all__ = ["composite_layers", "alpha_overlay_gpx"]
