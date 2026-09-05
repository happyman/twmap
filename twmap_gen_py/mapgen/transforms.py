"""Composable image transform pipeline.

Each transform operates on a numpy array (HxWxC for color, HxW for grayscale)
with dtype uint8 in the range 0-255. Transforms are pure functions of the image,
making them easy to test and compose.

This replaces the ImageMagick ``convert`` pipelines used in the PHP version.
"""

from __future__ import annotations

from abc import ABC, abstractmethod

import numpy as np

# RGB weights for ITU-R BT.601 luma (matches ImageMagick's -colorspace gray)
_LUMA = np.array([0.299, 0.587, 0.114], dtype=np.float32)


def _as_float(img: np.ndarray) -> np.ndarray:
    """Convert uint8 image to float32 in 0..255 range."""
    return img.astype(np.float32)


class Transform(ABC):
    """Base class for image transforms. Subclasses must implement `apply`."""

    @abstractmethod
    def apply(self, img: np.ndarray) -> np.ndarray:
        """Apply the transform and return a new image array."""
        raise NotImplementedError

    def __call__(self, img: np.ndarray) -> np.ndarray:
        return self.apply(img)


class Noop(Transform):
    def apply(self, img: np.ndarray) -> np.ndarray:
        return img


class Equalize(Transform):
    """Histogram equalization, matching `-equalize`.

    ImageMagick's `-equalize` derives a single lookup table from the image
    histogram and applies it to every color channel, preserving the original
    channel balance (it does not neutralize per-channel histograms). The LUT
    here is built from the pooled RGB histogram. An alpha channel is preserved
    untouched.
    """

    def apply(self, img: np.ndarray) -> np.ndarray:
        if img.ndim == 2:
            return _equalize_channel(img)
        rgb = img[..., :3]
        hist = np.bincount(rgb.ravel(), minlength=256).astype(np.float64)
        lut = _equalize_lut(hist)
        out = img.copy()
        out[..., :3] = lut[rgb]
        return out


def _equalize_lut(hist: np.ndarray) -> np.ndarray:
    """Build a 256-entry equalization LUT from a channel histogram."""
    cdf = hist.cumsum()
    cdf_min = cdf[cdf > 0].min() if cdf.any() else 0.0
    total = hist.sum()
    # A constant channel has no range to stretch; leaving it untouched avoids
    # the degenerate case where cdf_min == total (which would map it to 0).
    if cdf_min >= total:
        return np.arange(256, dtype=np.uint8)
    return ((cdf - cdf_min) / (total - cdf_min) * 255).astype(np.uint8)


def _equalize_channel(ch: np.ndarray) -> np.ndarray:
    """Equalize a single 2D channel via its histogram."""
    return _equalize_lut(np.bincount(ch.ravel(), minlength=256).astype(np.float64))[ch]


class Gamma(Transform):
    """Gamma correction, matching `-gamma value`.

    ImageMagick applies ``pixel^(1/gamma)``. A gamma of 2.2 raises midtones
    (brightens), a gamma < 1 darkens. Applied to the RGB channels only; an
    alpha channel is preserved untouched (ImageMagick's default channel set).
    """

    def __init__(self, value: float):
        self.value = float(value)

    def apply(self, img: np.ndarray) -> np.ndarray:
        if self.value <= 0:
            raise ValueError("gamma must be > 0")
        if img.ndim == 2:
            f = _as_float(img) / 255.0
            f = np.power(f, 1.0 / self.value)
            return (f * 255.0).clip(0, 255).astype(np.uint8)
        f = _as_float(img[..., :3]) / 255.0
        f = np.power(f, 1.0 / self.value)
        out = img.copy()
        out[..., :3] = (f * 255.0).clip(0, 255).astype(np.uint8)
        return out


class Contrast(Transform):
    """Linear contrast stretch with black and white points.

    Matches ImageMagick's `-level black_point%,white_point%,gamma`.
    Values are given as fractions of the input range (0.0-1.0).
    """

    def __init__(
        self,
        black: float = 0.0,
        white: float = 1.0,
        gamma: float = 1.0,
    ):
        self.black = float(black)
        self.white = float(white)
        self.gamma = float(gamma)

    def apply(self, img: np.ndarray) -> np.ndarray:
        # aliased as Level for convenience (ImageMagick name)
        return self.level(img)

    def level(self, img: np.ndarray) -> np.ndarray:
        f = _as_float(img)
        lo = self.black * 255.0
        hi = self.white * 255.0
        span = max(hi - lo, 1e-6)
        # Remap so that `lo` -> 0 and `hi` -> 255 (ImageMagick -level semantics)
        f = (f - lo) / span * 255.0
        # Optional gamma adjustment on the 0..255 result
        if self.gamma != 1.0:
            g = f / 255.0
            g = np.power(g.clip(0, 1), self.gamma)
            f = g * 255.0
        return f.clip(0, 255).astype(np.uint8)


# Convenience alias: ImageMagick calls this operation "-level"
Level = Contrast


class Normalize(Transform):
    """Stretch dynamic range to 0-255 (per-channel), matching `-normalize`."""

    def apply(self, img: np.ndarray) -> np.ndarray:
        if img.ndim == 2:
            return _normalize_channel(img)
        out = img.copy()
        for c in range(img.shape[2]):
            out[..., c] = _normalize_channel(img[..., c])
        return out


def _normalize_channel(ch: np.ndarray) -> np.ndarray:
    f = _as_float(ch)
    lo = f.min()
    hi = f.max()
    span = hi - lo
    if span <= 0:
        return ch
    return ((f - lo) / span * 255.0).clip(0, 255).astype(np.uint8)


class GrayscaleSimple(Transform):
    """Luminance-weighted RGB->gray, matching `-colorspace gray`."""

    def apply(self, img: np.ndarray) -> np.ndarray:
        if img.ndim == 2:
            return img
        f = _as_float(img[..., :3])
        gray = np.dot(f, _LUMA).astype(np.uint8)
        return gray


class Brightness(Transform):
    """Additive brightness adjustment. `value` added to each pixel (0-255)."""

    def __init__(self, value: float):
        self.value = float(value)

    def apply(self, img: np.ndarray) -> np.ndarray:
        f = _as_float(img) + self.value
        return f.clip(0, 255).astype(np.uint8)


class ContrastAdjust(Transform):
    """Multiplicative contrast adjustment around mid-gray (128).

    ImageMagick's `-brightness-contrast` contrast: `(pixel-128)*scale+128`.
    `value` is a percentage (5 -> 1.05x).
    """

    def __init__(self, value: float):
        self.value = float(value)

    def apply(self, img: np.ndarray) -> np.ndarray:
        factor = 1.0 + self.value / 100.0
        f = _as_float(img)
        f = (f - 128.0) * factor + 128.0
        return f.clip(0, 255).astype(np.uint8)


class Tint(Transform):
    """Warm/cool tint by scaling color channels. `value` in degrees (0-360).

    ImageMagick's `-tint` shears color. For the warm sepia effect used by
    version 3, `value=40` gives a subtle warm cast. A positive value shifts
    toward yellow (warm), negative toward blue (cool).
    """

    def __init__(self, value: float):
        self.value = float(value)
        # Precompute a small color-shift matrix for the hue rotation.
        rad = np.deg2rad(self.value)
        # Simple approximation: boost R and depress B for warm (positive)
        self.r_scale = 1.0 + 0.15 * np.sin(rad)
        self.b_scale = 1.0 - 0.15 * np.sin(rad)

    def apply(self, img: np.ndarray) -> np.ndarray:
        if img.ndim == 2:
            return img
        f = _as_float(img[..., :3])
        out = f.copy()
        out[..., 0] = (f[..., 0] * self.r_scale).clip(0, 255)
        out[..., 1] = f[..., 1]
        out[..., 2] = (f[..., 2] * self.b_scale).clip(0, 255)
        return out.astype(np.uint8)


class GrayscaleEnhanced(Transform):
    """Enhanced grayscale matching version 3's `im_file_gray`.

    Converts to gray then applies brightness, contrast, and warm tint.
    """

    def __init__(self, brightness: float = 20, contrast: float = 5, tint: float = 40):
        self.brightness = float(brightness)
        self.contrast = float(contrast)
        self.tint = float(tint)

    def apply(self, img: np.ndarray) -> np.ndarray:
        gray = GrayscaleSimple().apply(img)
        gray = Brightness(self.brightness).apply(gray)
        gray = ContrastAdjust(self.contrast).apply(gray)
        # Tint on a grayscale image: replicate to 3 channels then tint
        rgb = np.stack([gray, gray, gray], axis=-1)
        tinted = Tint(self.tint).apply(rgb)
        return GrayscaleSimple().apply(tinted)


class AdaptiveThreshold(Transform):
    """Adaptive (local) threshold for historical scanned maps.

    Port of the PHP ``im_file_gray_at()`` 6-step ImageMagick pipeline
    (fmwconcepts local threshold method 2):

        1. convert to grayscale
        2. local mean      = blur(sigma)
        3. local variance  = blur(a*a) - blur(a)^2, then ``v^(1/gamma)``
           (IM ``-gamma 2`` raises to the reciprocal power, i.e. sqrt -> the
           local standard deviation, NOT a squared variance)
        4. IM ``-compose minus`` is ``max(0, second - first)``, so the final
           subtraction clips at zero:
           score = max(0, (a - mean) - k * stdev)
           binary = score >= 2.55 ? white : black

    ``2.55`` is IM ``-threshold 1`` (1% of the 0..255 range). Produces a
    binary (black/white) image, ideal for uneven paper scans.
    """

    def __init__(self, sigma: float = 6.66667, k: float = 0.03, gamma: float = 2.0):
        self.sigma = float(sigma)
        self.k = float(k)
        self.gamma = float(gamma)

    def apply(self, img: np.ndarray) -> np.ndarray:
        from scipy.ndimage import gaussian_filter

        gray = GrayscaleSimple().apply(img).astype(np.float32)
        mean = gaussian_filter(gray, sigma=self.sigma, mode="reflect")
        mean_sq = gaussian_filter(gray * gray, sigma=self.sigma, mode="reflect")
        variance = np.clip(mean_sq - mean * mean, 0, None)
        # IM -gamma 2 applies pixel^(1/2): variance -> standard deviation.
        # The clipped non-negative variance avoids NaNs from negative roots.
        stdev = np.power(variance, 1.0 / self.gamma)
        score = np.maximum((gray - mean) - self.k * stdev, 0.0)
        binary = np.where(score >= 2.55, 255, 0).astype(np.uint8)
        return binary


# --- Pipeline composition helpers ---


class Pipeline:
    """Apply a sequence of transforms to an image."""

    def __init__(self, transforms: list[Transform]):
        self.transforms = list(transforms)

    def apply(self, img: np.ndarray) -> np.ndarray:
        out = img
        for t in self.transforms:
            out = t.apply(out)
        return out

    def __call__(self, img: np.ndarray) -> np.ndarray:
        return self.apply(img)

    def __len__(self) -> int:
        return len(self.transforms)


def build_pipeline(specs: list) -> Pipeline:
    """Build a Pipeline from a list of transform specs.

    Each spec is either a Transform instance or a dict/iterable such as
    ``{"gamma": 2.2}``. This allows sources to be defined declaratively.
    """
    transforms: list[Transform] = []
    for spec in specs:
        if isinstance(spec, Transform):
            transforms.append(spec)
        elif isinstance(spec, dict):
            for name, args in spec.items():
                transforms.append(_build_from_name(name, args))
        else:
            raise TypeError(f"Unsupported transform spec: {spec!r}")
    return Pipeline(transforms)


def _build_from_name(name: str, args) -> Transform:
    name = name.lower().replace("-", "").replace("_", "")
    if name in ("equalize",):
        return Equalize()
    if name in ("gamma",):
        return Gamma(args if isinstance(args, (int, float)) else args[0])
    if name in ("level", "contrast", "normalize"):
        if isinstance(args, (list, tuple)):
            return Level(*args)
        return Level(args) if args is not None else Level()
    if name in ("normalizefull",):
        return Normalize()
    if name in ("grayscalesimple", "gray"):
        return GrayscaleSimple()
    if name in ("grayscaleenhanced",):
        kw = dict(args) if isinstance(args, dict) else {}
        return GrayscaleEnhanced(**kw)
    if name in ("adaptivethreshold", "at"):
        kw = dict(args) if isinstance(args, dict) else {}
        return AdaptiveThreshold(**kw)
    raise ValueError(f"Unknown transform: {name!r}")


__all__ = [
    "Transform",
    "Noop",
    "Equalize",
    "Gamma",
    "Contrast",
    "Level",
    "Normalize",
    "GrayscaleSimple",
    "GrayscaleEnhanced",
    "Brightness",
    "ContrastAdjust",
    "Tint",
    "AdaptiveThreshold",
    "Pipeline",
    "build_pipeline",
]
