"""GPX track/waypoint overlay.

Replaces the PHP `Gpx2Svg` + Inkscape rasterization. This parses a GPX file
with gpxpy, projects points into the map's pixel space, generates an SVG
overlay that embeds the base map as a background, and rasterizes it via
cairosvg.

As an alternative (preferred going forward), the overlay can be rendered
directly and alpha-composited onto the base image at full resolution,
decoupled from grayscale conversion (see ``compositor.alpha_overlay_gpx``).
"""

from __future__ import annotations

import logging
from dataclasses import dataclass, field
from pathlib import Path

import numpy as np

logger = logging.getLogger(__name__)


@dataclass
class GpxOverlay:
    """Parsed GPX content positioned in the map's pixel space."""

    track_points: list[tuple[float, float, float]] = field(default_factory=list)
    # list of (px_x, px_y, elevation) in map pixel coords
    track_segments: list[list[tuple[float, float, float]]] = field(default_factory=list)
    waypoints: list[dict] = field(default_factory=list)
    label_trk: str = ""
    label_wpt: int = 0
    bbox_wgs84: tuple[float, float, float, float] | None = None


def parse_gpx(
    path: str | Path,
    region_px,
    region,
    label_trk: str = "",
    label_wpt: int = 0,
) -> GpxOverlay:
    """Parse a GPX file and map tracks/waypoints into pixel coordinates.

    ``region_px`` is (width_px, height_px) of the base image.
    ``region`` is the mapgen.proj.Region (TWD bounds).
    """
    import gpxpy

    from .proj import wgs84_to_twd

    gpx = gpxpy.parse(Path(path).read_text(encoding="utf-8"))

    ov = GpxOverlay(label_trk=label_trk, label_wpt=label_wpt)

    # Bounding box from all points
    lons, lats = [], []
    for trk in gpx.tracks:
        for seg in trk.segments:
            seg_points = []
            for pt in seg.points:
                # Filter points inside/around region
                x_m, y_m = wgs84_to_twd(pt.longitude, pt.latitude, region.crs)
                px_x = (x_m - region.x0) / region.width_m * region_px[0]
                px_y = (region.y0 - y_m) / region.height_m * region_px[1]
                seg_points.append((px_x, px_y, pt.elevation or 0.0))
                lons.append(pt.longitude)
                lats.append(pt.latitude)
            if seg_points:
                ov.track_segments.append(seg_points)
                ov.track_points.extend(seg_points)

    for wpt in gpx.waypoints:
        x_m, y_m = wgs84_to_twd(wpt.longitude, wpt.latitude, region.crs)
        px_x = (x_m - region.x0) / region.width_m * region_px[0]
        px_y = (region.y0 - y_m) / region.height_m * region_px[1]
        ov.waypoints.append(
            {
                "px": (px_x, px_y),
                "name": wpt.name or "",
                "text": wpt.name or "",
            }
        )
        lons.append(wpt.longitude)
        lats.append(wpt.latitude)

    if lons:
        ov.bbox_wgs84 = (min(lons), min(lats), max(lons), max(lats))

    return ov


def _elevation_color(ele: float, min_ele: float, max_ele: float) -> tuple[int, int, int]:
    """Map elevation to an RGB color along the PHP rainbow spectrum."""
    span = max(max_ele - min_ele, 1e-6)
    t = (ele - min_ele) / span
    # Simple 6-stop rainbow (magenta -> red -> yellow -> green -> cyan -> blue)
    if t < 1 / 6:
        return _lerp((255, 0, 255), (255, 0, 0), (t - 0) * 6)
    if t < 2 / 6:
        return _lerp((255, 0, 0), (255, 255, 0), (t - 1 / 6) * 6)
    if t < 3 / 6:
        return _lerp((255, 255, 0), (0, 255, 0), (t - 2 / 6) * 6)
    if t < 4 / 6:
        return _lerp((0, 255, 0), (0, 255, 255), (t - 3 / 6) * 6)
    if t < 5 / 6:
        return _lerp((0, 255, 255), (0, 0, 255), (t - 4 / 6) * 6)
    return _lerp((0, 0, 255), (255, 0, 255), (t - 5 / 6) * 6)


def _lerp(c1, c2, t):
    return tuple(int(round(a + (b - a) * max(0.0, min(1.0, t)))) for a, b in zip(c1, c2))


def render_overlay_to_svg(
    ov: GpxOverlay,
    width_px: int,
    height_px: int,
    base_image_path: str | Path | None = None,
    font_path: str | None = None,
) -> str:
    """Render the overlay as an SVG string.

    If ``base_image_path`` is provided, embeds it as the background image
    (so cairosvg can rasterize base + overlay in one pass). Otherwise returns
    an SVG with transparent background suitable for alpha composition.
    """
    parts = [
        f'<svg xmlns="http://www.w3.org/2000/svg" width="{width_px}" height="{height_px}" '
        f'viewBox="0 0 {width_px} {height_px}">'
    ]
    if base_image_path:
        parts.append(
            f'<image width="{width_px}" height="{height_px}" '
            f'xlink:href="{base_image_path}" />'
        )

    # Track segments with elevation color
    if ov.track_segments:
        all_ele = [p[2] for seg in ov.track_segments for p in seg]
        min_ele = min(all_ele)
        max_ele = max(all_ele)
        for seg in ov.track_segments:
            for i in range(len(seg) - 1):
                x1, y1, e1 = seg[i]
                x2, y2, e2 = seg[i + 1]
                r, g, b = _elevation_color(e1, min_ele, max_ele)
                parts.append(
                    f'<line stroke="rgb({r},{g},{b})" stroke-width="2" opacity="0.9" '
                    f'x1="{x1:.2f}" y1="{y1:.2f}" x2="{x2:.2f}" y2="{y2:.2f}" />'
                )

    # Waypoints as black circles
    for i, wpt in enumerate(ov.waypoints):
        x, y = wpt["px"]
        parts.append(
            f'<circle cx="{x:.2f}" cy="{y:.2f}" r="2.5" fill="black" stroke="black" />'
        )
        if ov.label_wpt and wpt["name"]:
            txt = wpt["name"]
            parts.append(
                f'<text x="{x + 8:.2f}" y="{y - 4:.2f}" font-size="14" fill="black">{txt}</text>'
            )

    parts.append("</svg>")
    return "\n".join(parts)


def svg_to_png(svg_str: str, width_px: int, height_px: int) -> np.ndarray:
    """Rasterize an SVG string to a numpy RGBA array via cairosvg."""
    import cairosvg

    png = cairosvg.svg2png(
        bytestring=svg_str.encode("utf-8"),
        output_width=width_px,
        output_height=height_px,
    )
    from io import BytesIO

    from PIL import Image

    return np.array(Image.open(BytesIO(png)).convert("RGBA"))


__all__ = ["GpxOverlay", "parse_gpx", "render_overlay_to_svg", "svg_to_png"]
