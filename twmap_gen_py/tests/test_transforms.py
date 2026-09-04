"""Tests for the composable transform pipeline."""

import numpy as np
import pytest

from mapgen.transforms import (
    AdaptiveThreshold,
    Equalize,
    Gamma,
    GrayscaleEnhanced,
    GrayscaleSimple,
    Level,
    Normalize,
    Pipeline,
)


def _test_image(size=(20, 20), seed=0):
    rng = np.random.default_rng(seed)
    return rng.integers(0, 255, size=(*size, 3), dtype=np.uint8)


def test_gamma_brightens_midtones():
    # A mid-gray (128) should be brightened by gamma 2.2
    img = np.full((5, 5, 3), 128, dtype=np.uint8)
    out = Gamma(2.2).apply(img)
    assert out[0, 0, 0] > 128


def test_gamma_darkens_with_sub_1():
    img = np.full((5, 5, 3), 128, dtype=np.uint8)
    out = Gamma(0.5).apply(img)
    assert out[0, 0, 0] < 128


def test_gamma_rejects_nonpositive():
    with pytest.raises(ValueError):
        Gamma(0).apply(np.zeros((2, 2, 3), dtype=np.uint8))


def test_equalize_trivial():
    # Constant image equalizes (mostly) to itself
    img = np.full((5, 5, 3), 100, dtype=np.uint8)
    out = Equalize().apply(img)
    assert out.shape == img.shape
    assert out.dtype == np.uint8


def test_level_stretches():
    img = np.full((5, 5, 3), 128, dtype=np.uint8)
    # black=0.25 -> black point at 64, so 128 stretches to ~85
    out = Level(0.25).apply(img)
    assert out[0, 0, 0] == pytest.approx(85, abs=2)


def test_normalize_uses_full_range():
    img = np.zeros((5, 5, 3), dtype=np.uint8)
    # R channel: only 0 and 200 across the image (plus nothing else nonzero)
    img[0, 0] = [200, 200, 50]
    img[4, 4] = [200, 200, 100]
    out = Normalize().apply(img)
    # R/G hold {0,200}: 200 -> 255
    assert out[0, 0, 0] == 255
    assert out[0, 0, 1] == 255
    # B holds {0,50,100}: 50 -> 127, 100 (at 4,4) -> 255
    assert out[0, 0, 2] == 127
    assert out[4, 4, 2] == 255


def test_grayscale_simple_luma():
    img = np.zeros((1, 1, 3), dtype=np.uint8)
    # Pure red should map to 0.299*255 ≈ 76
    img[0, 0] = [255, 0, 0]
    out = GrayscaleSimple().apply(img)
    assert out[0, 0] == 76


def test_grayscale_enhanced_produces_warm():
    img = np.full((5, 5, 3), 128, dtype=np.uint8)
    out = GrayscaleEnhanced(brightness=20, contrast=5, tint=40).apply(img)
    assert out.shape == (5, 5)
    assert np.all(out > 0)


def test_pipeline_composes():
    pipe = Pipeline([Equalize(), Gamma(2.2), Normalize()])
    img = _test_image()
    out = pipe(img)
    assert out.shape == img.shape
    assert out.dtype == np.uint8


def test_adaptive_threshold_extracts_line():
    """A dark line on a gradient background must become black after threshold."""
    img = np.zeros((100, 100, 3), dtype=np.uint8)
    yy, xx = np.mgrid[0:100, 0:100]
    bg = (120 + yy * 0.5).astype(np.uint8)
    img[..., 0] = bg
    img[..., 1] = bg
    img[..., 2] = bg
    img[40:45, :, :] = 30  # dark line
    bw = AdaptiveThreshold().apply(img)
    assert bw.ndim == 2
    assert bw.dtype == np.uint8
    # The line region must be black (0)
    assert np.all(bw[41:44, :] == 0)
