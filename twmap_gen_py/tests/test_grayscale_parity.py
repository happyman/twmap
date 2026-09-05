"""Grayscale transform parity against ImageMagick (skipped when `convert` absent).

The PHP worker shells out to ImageMagick 6 for every grayscale conversion
(Twmap/Stitcher.php::im_file_gray*). These tests replicate those exact
command pipelines and assert the numpy transforms produce the same result.
"""

from __future__ import annotations

import shutil
import subprocess
import tempfile
from pathlib import Path

import numpy as np
import pytest
from PIL import Image

from mapgen.transforms import AdaptiveThreshold

IM = shutil.which("convert")
pytestmark = pytest.mark.skipif(IM is None, reason="ImageMagick `convert` not available")


def _run_php_adaptive_gray(path_in: Path, path_out: Path) -> None:
    """Replicate Twmap/Stitcher.php::im_file_gray_at() (fmwconcepts method 2).

    Uses MIFF intermediates so each built-in op works in Q16 precision, the
    same fidelity PHP gets from ``tempnam()+".mpc"``.
    """
    with tempfile.TemporaryDirectory() as td:
        td = Path(td)
        a = td / "a.miff"
        m = td / "m.miff"
        s = td / "s.miff"

        def im(*args: str) -> None:
            subprocess.run([IM, *args], check=True, capture_output=True)

        im("-quiet", str(path_in), "-colorspace", "gray", "-alpha", "off", "+repage", str(a))
        im(str(a), "-blur", "0x6.66667", str(m))
        im(
            "(", str(a), str(a), "-compose", "multiply", "-composite",
            "-blur", "0x6.66667", ")",
            "(", str(m), str(m), "-compose", "multiply", "-composite", ")",
            "+swap", "-compose", "minus", "-composite", "-gamma", "2", str(s),
        )
        im(
            str(a), str(m), "+swap", "-compose", "minus", "-composite",
            "(", str(s), "-evaluate", "multiply", "0.03", ")",
            "+swap", "-compose", "minus", "-composite", "-threshold", "1", str(path_out),
        )


def _synthetic_historical_tile() -> np.ndarray:
    """A sepia scanned-map lookalike: noisy paper, dark ink lines, stains."""
    rng = np.random.default_rng(20250101)
    n = 240
    paper = rng.normal(212, 7, (n, n)).clip(0, 255).astype(np.uint8)
    img = np.repeat(paper[:, :, None], 3, axis=2)
    sepia = np.array([224.0, 210.0, 178.0])
    img = (img.astype(np.float32) * (sepia / 255.0)).clip(0, 255).astype(np.uint8)
    rr, cc = np.mgrid[0:n, 0:n]
    ink = (
        ((rr % 45) < 3)
        | ((cc % 37) < 2)
        | ((np.abs(rr - 60) < 1.5) & (cc > 90))
        | (np.abs(cc - rr) < 1.5)
    )
    img[ink] = rng.integers(25, 85, size=(int(ink.sum()), 3))
    img[30:38, 120:170] = rng.integers(40, 70, size=(8, 50, 3))  # stain block
    return img


def test_adaptive_threshold_matches_php_pipeline():
    img = _synthetic_historical_tile()
    with tempfile.TemporaryDirectory() as td:
        td = Path(td)
        src = td / "tile.png"
        php_out = td / "php.png"
        Image.fromarray(img).save(src)
        _run_php_adaptive_gray(src, php_out)

        ours = AdaptiveThreshold().apply(img)
        php = np.array(Image.open(php_out).convert("L"))

    assert ours.shape == php.shape
    ours_b = (ours > 127).astype(np.uint8)
    php_b = (php > 127).astype(np.uint8)
    agreement = float((ours_b == php_b).mean())
    assert agreement >= 0.95, f"only {agreement:.2%} binary pixels match PHP/IM"
    # Sanity: it must be a two-tone image (the black-output regression).
    assert 0.05 < ours_b.mean() < 0.95
