"""Command-line interface.

New Pythonic interface (``mapgen make ...``) plus legacy ``cmd_make2.py``
-compatible aliases (-r/-O/-v/-g/-e/-G/-3/-D/-c/-p). Also provides helpers:
``list-sources``, ``test-source``, ``compare-sources``.
"""

from __future__ import annotations

import argparse
import asyncio
import json
import logging
import sys
import tempfile
from pathlib import Path

import numpy as np

from . import __version__

logger = logging.getLogger("mapgen")


def build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(
        prog="mapgen",
        description="Taiwan topographic map generator",
    )
    parser.add_argument("--version", action="version", version=__version__)
    sub = parser.add_subparsers(dest="command", required=False)

    # --- `make` (new Pythonic) ---
    make = sub.add_parser("make", help="Generate a printable map")
    _add_make_args(make, legacy=False)
    make.set_defaults(func=cmd_make)

    # --- Legacy-compatible fallback: `cmd_make2.py -r ...` ---
    legacy = sub.add_parser(
        "legacy", help="Legacy cmd_make2.php-compatible interface"
    )
    _add_make_args(legacy, legacy=True)
    legacy.set_defaults(func=cmd_make)

    # --- Helpers ---
    sub.add_parser("list-sources", help="List registered map sources").set_defaults(
        func=cmd_list_sources
    )

    test = sub.add_parser("test-source", help="Test a source's preprocessing")
    test.add_argument("source", help="Map source key, e.g. rudymap")
    test.add_argument(
        "--tile",
        help="Single tile z/x/y to test (skips full region download)",
    )
    test.add_argument("--region", help="TWD region x0,y0,shiftx,shifty[,datum]")
    test.add_argument(
        "--penghu", type=int, default=0, choices=(0, 1),
        help="1 for Penghu (uses lon_0=119 central meridian)",
    )
    test.add_argument("--show-steps", action="store_true", help="Save each pipeline step")
    test.add_argument("-o", "--output", default="./debug/", help="Output directory")
    test.set_defaults(func=cmd_test_source)

    compare = sub.add_parser(
        "compare-sources", help="Compare two sources side by side"
    )
    compare.add_argument("sources", help="Comma-separated source keys")
    compare.add_argument("--region", required=True, help="TWD region x0,y0,shiftx,shifty[,datum]")
    compare.add_argument("-o", "--output", default="./compare/", help="Output directory")
    compare.set_defaults(func=cmd_compare_sources)

    # If the first positional doesn't match a subcommand, fall back to legacy `make`.
    return parser


def _add_make_args(p: argparse.ArgumentParser, legacy: bool) -> None:
    p.add_argument(
        "--region",
        "-r",
        default=None,
        help=(
            "Region as x0,y0,shiftx,shifty[,datum]; legacy uses "
            "x:y:w:h:datum"
        ),
    )
    p.add_argument(
        "--output",
        "-O",
        default="./out/",
        help="Output directory (default ./out/)",
    )
    p.add_argument(
        "--map-type",
        "-v",
        default="2016",
        help="Map source key (default 2016/魯地圖)",
    )
    p.add_argument("--title", "-t", default="我的地圖", help="Map title")
    p.add_argument(
        "--keep-color",
        "-c",
        action="store_true",
        help="Keep color (skip grayscale conversion)",
    )
    p.add_argument(
        "--penghu",
        "-p",
        type=int,
        default=0,
        choices=(0, 1),
        help="1 for Penghu (uses lon_0=119 central meridian)",
    )
    p.add_argument(
        "--gpx",
        "-g",
        default=None,
        help="GPX file with trk_label and wpt_label, e.g. file:label:1",
    )
    p.add_argument(
        "--grid-100m",
        "-e",
        action="store_true",
        help="Draw 100m grid lines",
    )
    p.add_argument(
        "--include-tracks",
        "-G",
        action="store_true",
        help="Include user track-log overlay layer",
    )
    p.add_argument(
        "--a3",
        "-3",
        action="store_true",
        help="Use A3 paper size",
    )
    # Legacy -D dim (multiple)
    dim_help = "Output page dimension (5x7,4x6,3x4,2x3,1x2)"
    if legacy:
        p.add_argument("-D", action="append", dest="dims", default=[], help=dim_help)
        p.add_argument(
            "-m", "--tmpdir", default="/dev/shm", help="Temp directory"
        )
        p.add_argument(
            "-d", "--debug", action="store_true", help="Debug flag"
        )
    else:
        p.add_argument("--dims", "-D", action="append", default=[], help=dim_help)
        p.add_argument(
            "--tmpdir", "-m", default="/dev/shm", help="Temp directory"
        )
        p.add_argument("--debug", "-d", action="store_true", help="Debug")

    # WebSocket progress reporting to the frontend.
    p.add_argument(
        "--ws-url",
        "-l",
        default=None,
        help=(
            "WebSocket URL for progress (ws://host:9002/twmap_<channel>), or "
            "in legacy queue mode the bare channel name when --logurl_prefix "
            "is given"
        ),
    )
    # Legacy queue-mode args (job payload from backend_make.php + worker).
    p.add_argument(
        "-i",
        "--remote-ip",
        default=None,
        help="Remote IP of the requester (logged; used by the PHP frontend)",
    )
    p.add_argument(
        "-a",
        "--callback",
        default=None,
        help="Callback URL invoked when the map is done (api/made.php)",
    )
    p.add_argument(
        "--agent",
        default=None,
        help="Agent name reported to the frontend/callback",
    )
    p.add_argument(
        "--logurl_prefix",
        default=None,
        help="WebSocket scheme+prefix; combined with -l channel",
    )
    p.add_argument(
        "--logfile",
        default=None,
        help="Also write logs to this file",
    )


def _parse_region(spec: str) -> dict:
    """Parse both 'x0,y0,w,h[,datum]' and legacy 'x:y:w:h:datum' formats.

    The modern comma format uses metre coordinates (``307000,2677000,12,6``).
    The legacy colon format matches the PHP ``cmd_make2.php`` ``-r`` argument,
    where ``startx``/``starty`` are in **kilometres** (the frontend queue
    payload e.g. ``-r 274:2639:3:3:TWD97``), so they are scaled by 1000.
    """
    is_colon = ":" in spec
    sep = ":" if is_colon else ","
    parts = [p for p in spec.split(sep) if p != ""]
    if len(parts) not in (4, 5):
        raise SystemExit(
            f"Invalid region: {spec!r}. Use x0,y0,shiftx,shifty[,datum] "
            "or legacy x0:y0:shiftx:shifty:datum"
        )
    x0, y0, sx, sy = (float(parts[0]), float(parts[1]), int(parts[2]), int(parts[3]))
    datum = parts[4].upper() if len(parts) == 5 else "TWD97"
    if is_colon:
        x0 *= 1000.0
        y0 *= 1000.0
    return {"x0": x0, "y0": y0, "shiftx": sx, "shifty": sy, "datum": datum}


def _region_from_args(args):
    from .proj import Region

    spec = args.region
    if spec is None:
        raise SystemExit("--region/-r is required")
    p = _parse_region(spec)
    # shiftx/shifty are in 1-km units; region spans them
    return Region(
        x0=p["x0"],
        y0=p["y0"],
        x1=p["x0"] + p["shiftx"] * 1000,
        y1=p["y0"] - p["shifty"] * 1000,
        datum=p["datum"],
        penghu=bool(args.penghu),
    )


def cmd_make(args) -> None:
    from .config import get_source

    source = get_source(args.map_type)
    region = _region_from_args(args)

    from .grinder import optimize_png
    from .stitcher import build_base_image

    logging.basicConfig(
        level=logging.DEBUG if args.debug else logging.INFO,
        format="%(asctime)s %(levelname)s %(name)s: %(message)s",
    )
    args.title = _decode_mime_title(args.title)
    if getattr(args, "logfile", None):
        fh = logging.FileHandler(args.logfile)
        fh.setFormatter(logging.Formatter("%(asctime)s %(levelname)s %(message)s"))
        logger.addHandler(fh)
    if getattr(args, "agent", None):
        logger.info("Agent %s Roger that ^_^", args.agent.strip())

    outdir = Path(args.output)
    outdir.mkdir(parents=True, exist_ok=True)
    tmpdir = Path(args.tmpdir)
    tmpdir.mkdir(parents=True, exist_ok=True)

    # Start the frontend progress notifier (best-effort).
    notifier = _make_notifier(args)
    notifier.start()
    _report(notifier, "step:start")
    _report(notifier, "ps%0")

    workdir = Path(tempfile.mkdtemp(dir=str(tmpdir), prefix="twmap_"))
    try:
        logger.info("Generating %s over %s", source.name, region)

        # Download + stitch + reproject: maps build progress (0..1) to 0..40%.
        _report(notifier, "step:download")
        if notifier.enabled:
            def build_progress(frac: float) -> None:
                notifier.progress(0.40 * frac)
        else:
            build_progress = None

        base = asyncio.run(
            build_base_image(
                region,
                source,
                workdir=workdir,
                include_gpx=bool(getattr(args, "include_tracks", False)),
                on_progress=build_progress,
            )
        )
        _report(notifier, "step:base", 40)

        # Parse GPX if provided
        gpx_param = _parse_gpx_arg(args.gpx) if args.gpx else None

        # Build color image: base + grid/logo/tags, then GPX on top so the
        # elevation-colored tracks are not obscured by the map content.
        _report(notifier, "step:style")
        img_color = _apply_map_decorations(base, region, source, args)
        if gpx_param:
            _report(notifier, "step:gpx")
            img_color = _apply_gpx_to_base(img_color, region, source, gpx_param)

        # Grayscale version (if not keep_color). The GPX is composited *after*
        # grayscale so the elevation-colored tracks stay visible on the gray map.
        if args.keep_color:
            img_gray = img_color.copy()
        else:
            _report(notifier, "step:grayscale")
            gray_img = _to_grayscale(base, source)
            img_gray = _apply_map_decorations(gray_img, region, source, args)
            if gpx_param:
                img_gray = _apply_gpx_to_base(
                    img_gray, region, source, gpx_param
                )
        _report(notifier, "ps%60")

        # Output files
        prefix = _output_prefix(region, args)
        tag_png = outdir / f"{prefix}.tag.png"
        gray_png = outdir / f"{prefix}.gray.png"
        outcmd = outdir / f"{prefix}.cmd"
        outtext = outdir / f"{prefix}.txt"

        _save_png(img_color, tag_png)
        _save_png(img_gray, gray_png)

        optimize_png(tag_png)
        optimize_png(gray_png)

        logger.info("Wrote %s and %s", tag_png, gray_png)

        # Mirror PHP: record the full invocation into `{prefix}.cmd` for later
        # debugging (the callback curl line is appended below).
        outcmd.write_text(" ".join(sys.argv), encoding="utf-8")

        # Split into pages + export. Mirrors PHP: pages/PDF come from the
        # grayscale image ($outimage_gray), KMZ/GeoTIFF from the color tagged
        # image ($outimage).
        _report(notifier, "step:export")
        outinfo = _handle_export(
            img_color, img_gray, region, source, args, outdir, prefix,
            notifier=notifier,
        )

        # Clean up the temporary gray image (mirrors PHP `unlink($outimage_gray)`)
        try:
            gray_png.unlink()
        except OSError:
            pass

        # Mirror PHP: save the split dim/paper/count metadata to `{prefix}.txt`.
        outtext.write_text(
            json.dumps(outinfo, ensure_ascii=False), encoding="utf-8"
        )
        logger.info("%s wrote to %s", json.dumps(outinfo), outtext)

        # Mirror PHP: register the map with the frontend (api/made.php) before
        # declaring 100% done. On failure notifier.error() runs + nonzero exit,
        # so the queue worker releases the job for a retry.
        _handle_callback(args, outcmd=outcmd)
        _report(notifier, "ps%100")

    except Exception as exc:  # noqa: BLE001
        logger.error("Fatal: %s", exc, exc_info=True)
        notifier.error(str(exc))
        raise
    finally:
        import shutil

        shutil.rmtree(workdir, ignore_errors=True)
        notifier.stop()


def _make_notifier(args) -> object:
    from .notify import Notifier

    return Notifier(url=_effective_ws_url(args))


def _effective_ws_url(args) -> str | None:
    """Resolve the frontend websocket URL.

    ``-l`` accepts a full URL (``ws://host:9002/twmap_<channel>``) or, in the
    legacy queue payload, a bare channel name combined with the worker-supplied
    ``--logurl_prefix`` (``ws://twmap:9002/twmap_`` + ``<channel>``).
    """
    value = getattr(args, "ws_url", None)
    if not value:
        return None
    if value.startswith(("ws://", "wss://")):
        return value
    prefix = getattr(args, "logurl_prefix", None)
    if prefix:
        return prefix + value
    return None


def _extract_channel(args) -> str:
    """Derive the log_channel from -l (bare channel or full ws URL)."""
    value = args.ws_url
    if not value:
        return ""
    if value.startswith(("ws://", "wss://")):
        return value.rstrip("/").rpartition("/")[2]
    return value


class _CallbackHttpError(Exception):
    """HTTP-status failure from ``_callback_get`` (status >= 400)."""

    def __init__(self, status: int, body: bytes):
        super().__init__(f"HTTP {status}")
        self.status = status
        self.body = body


def _callback_get(
    url: str, read_timeout: float = 30.0, connect_timeout: float = 2.0
):
    """GET ``url`` with curl-like timeouts.

    Connects within ``connect_timeout`` (fast-fail on dead hosts), then once
    connected gives the request up to ``read_timeout`` to complete — mirrors
    PHP's ``curl --connect-timeout 2 --max-time 30``.

    Returns ``(status, body)`` for any HTTP response; raises ``OSError`` on
    network failures and ``_CallbackHttpError`` for HTTP status >= 400.
    """
    import http.client
    import ssl
    import urllib.parse

    parts = urllib.parse.urlsplit(url)
    scheme = parts.scheme.lower()
    if scheme not in ("http", "https"):
        raise OSError(f"unsupported callback scheme: {scheme}")
    host = parts.hostname or "localhost"
    port = parts.port or (443 if scheme == "https" else 80)
    cls = (
        http.client.HTTPSConnection
        if scheme == "https"
        else http.client.HTTPConnection
    )
    kwargs: dict = {}
    if scheme == "https":
        kwargs["context"] = ssl.create_default_context()
    conn = cls(host, port, timeout=connect_timeout, **kwargs)
    try:
        conn.connect()
        conn.sock.settimeout(read_timeout)
        path = parts.path or "/"
        if parts.query:
            path += "?" + parts.query
        conn.request("GET", path)
        resp = conn.getresponse()
        body = resp.read()
    finally:
        conn.close()
    if resp.status >= 400:
        raise _CallbackHttpError(resp.status, body)
    return resp.status, body


def _handle_callback(args, outcmd: Path | None = None) -> None:
    """Invoke the frontend callback (api/made.php) when the map is done.

    Mirrors the PHP ``curl --fail-with-body --connect-timeout 2 --max-time 30
    --retry 10`` call: GET ``<callback>?ch=<channel>&status=ok&params=<argv>&
    agent=<agent>``. The equivalent curl command is appended to ``outcmd``
    (the ``{prefix}.cmd`` file) for later debugging, exactly like PHP writes
    it. Timeouts mirror curl: a 2s *connect* limit, but a connected request is
    given the full 30s to finish, so a made.php still busy running
    ``finish_task`` (sleep + DB + migrate) is never abandoned mid-flight.
    Abandoning it would re-send the same ``status=ok``, which arrives only
    *after* the first call already deleted the channel key -> made.php's
    "no such channel: ok". Like curl ``--retry``, only transient errors
    (connection) and 5xx are retried within the 30s deadline; a 4xx
    (e.g. made.php "no such channel" when the channel key was already
    consumed) is treated as final, since retrying a dead channel can never
    succeed. Raises on final failure so the caller exits nonzero.
    """
    import shlex
    import time
    import urllib.parse

    callback = getattr(args, "callback", None)
    if not callback:
        return
    channel = _extract_channel(args)
    params_str = " ".join(sys.argv)
    url = (
        f"{callback}?ch={urllib.parse.quote(channel)}&status=ok"
        f"&params={urllib.parse.quote(params_str)}"
    )
    agent = getattr(args, "agent", None)
    if agent:
        url += f"&agent={urllib.parse.quote(agent.strip())}"
    logger.info("call callback: %s", url)

    # Mirror the PHP file_put_contents($outcmd, "\n\n$cmd\n", FILE_APPEND)
    if outcmd is not None:
        curl_cmd = (
            "curl --fail-with-body --connect-timeout 2 --max-time 30 "
            f"--retry 10 --retry-max-time 0 {shlex.quote(url)}"
        )
        try:
            with open(outcmd, "a", encoding="utf-8") as fh:
                fh.write(f"\n\n{curl_cmd}\n")
        except OSError as exc:
            logger.warning("could not append callback cmd to %s: %s", outcmd, exc)

    deadline = time.monotonic() + 30.0
    last: Exception | None = None
    while True:
        try:
            status, body = _callback_get(
                url, read_timeout=30.0, connect_timeout=2.0
            )
            if status < 400:
                logger.info("callback ok (HTTP %d: %r)", status, body[:80])
                return
            last = RuntimeError(f"callback HTTP {status}")
        except _CallbackHttpError as exc:
            if 400 <= exc.status < 500:
                logger.warning(
                    "callback rejected with HTTP %d (%s); treating as final",
                    exc.status,
                    exc.body.decode("utf-8", "replace").strip(),
                )
                return
            last = RuntimeError(f"callback HTTP {exc.status}")
        except OSError as exc:  # noqa: PERF203
            last = exc
        remaining = deadline - time.monotonic()
        if remaining <= 1:
            break
        time.sleep(min(2.0, remaining))
    raise RuntimeError(f"callback failed: {url} ({last})")


def _report(notifier, message: str, pct: int | None = None) -> None:
    """Log a pipeline step and mirror it to the frontend.

    ``message`` is either ``step:<name>`` or ``ps%NN``. When ``pct`` is given,
    also send a ``ps%NN`` progress message, keeping the log and the websocket
    in sync with the actual stage being executed.
    """
    logger.info("%s", message)
    if notifier is None:
        return
    notifier.send(message)
    if pct is not None:
        notifier.progress(pct / 100.0)


def _apply_map_decorations(img, region, source, args) -> np.ndarray:
    """Apply grid lines, logo, and coordinate tags to an image in place order."""
    from .grinder import composite_logo, draw_grid_lines, tag_coordinates

    out = img
    if args.grid_100m:
        out = draw_grid_lines(out, source.pixel_per_km, step_m=100)
    # 1000m grid is always drawn except v3+TWD67
    if not (args.map_type == "3" and region.datum == "TWD67"):
        out = draw_grid_lines(out, source.pixel_per_km, step_m=1000)
    out = composite_logo(out, f"{region.datum}\n{source.label}")
    out = tag_coordinates(out, region, source.pixel_per_km)
    return out


def _parse_gpx_arg(gpx_spec: str) -> dict:
    """Parse the -g argument: ``file:show_label_trk:show_label_wpt``.

    Returns a dict with keys path, label_trk (int), label_wpt (int).
    """
    parts = gpx_spec.split(":")
    path = parts[0]
    label_trk = int(parts[1]) if len(parts) > 1 and parts[1] else 0
    label_wpt = int(parts[2]) if len(parts) > 2 and parts[2] else 0
    if not Path(path).exists():
        raise SystemExit(f"unable to read gpx file: {path}")
    return {"path": path, "label_trk": label_trk, "label_wpt": label_wpt}


def _apply_gpx_to_base(img, region, source, gpx_param) -> np.ndarray:
    """Parse the GPX, render its overlay, and alpha-composite it onto the image."""
    from .gpx2svg import apply_gpx_overlay, parse_gpx, render_overlay_to_image

    width_px = img.shape[1]
    height_px = img.shape[0]
    ov = parse_gpx(
        gpx_param["path"],
        region_px=(width_px, height_px),
        region=region,
        label_trk=gpx_param["label_trk"],
        label_wpt=gpx_param["label_wpt"],
    )
    overlay = render_overlay_to_image(ov, width_px, height_px)
    composed = apply_gpx_overlay(img, overlay)
    logger.info("GPX overlay applied (%d segments, %d waypoints)",
                len(ov.track_segments), len(ov.waypoints))
    return composed


def _to_grayscale(img, source):
    from .transforms import (
        AdaptiveThreshold,
        GrayscaleEnhanced,
        GrayscaleSimple,
    )

    if source.grayscale == "enhanced":
        return GrayscaleEnhanced(**source.grayscale_params).apply(img)
    if source.grayscale == "adaptive_threshold":
        return AdaptiveThreshold().apply(img)
    return GrayscaleSimple().apply(img)


def _save_png(arr, path: Path) -> None:
    from PIL import Image

    if arr.dtype != "uint8":
        arr = arr.astype("uint8")
    im = Image.fromarray(arr)
    if im.mode != "RGBA":
        im = im.convert("RGBA")
    im.save(path)


def _decode_mime_title(title: str) -> str:
    """Decode an RFC-2047 encoded title (``=?UTF-8?B?...?=``) as produced by
    the PHP backend's ``_mb_mime_encode()``. Plain text passes through."""
    if not title or "=?" not in title or "?=" not in title:
        return title
    from email.header import decode_header

    decoded = []
    for payload, charset in decode_header(title):
        if isinstance(payload, bytes):
            decoded.append(payload.decode(charset or "utf-8", errors="replace"))
        else:
            decoded.append(payload)
    return "".join(decoded)


def _output_prefix(region, args) -> str:
    # Prefix like: 307000x2677000-12x6-v2016_TWD67 (Penghu gets a 'p' marker,
    # matching the PHP sprintf "-v%s%s" with ph in {"", "p"}).
    x0 = int(region.x0)
    y0 = int(region.y0)
    sx = int(region.width_m / 1000)
    sy = int(region.height_m / 1000)
    datum = region.datum
    ph = "p" if getattr(args, "penghu", 0) else ""
    return f"{x0}x{y0}-{sx}x{sy}-v{args.map_type}{ph}_{datum}"


def _handle_export(
    color_img, gray_img, region, source, args, outdir: Path, prefix: str,
    notifier=None,
) -> dict:
    """Split into pages and produce PDF/KMZ/GeoTIFF exports.

    Mirrors the PHP output layout: one ``{prefix}.pdf`` (all dimensions merged),
    ``{prefix}.tag.kmz`` and ``{prefix}.tag.tiff``, with the temporary page
    PNGs deleted afterwards. Returns the ``outinfo`` dict used for the
    ``{prefix}.txt`` metadata file::

        {"dim": ["5x7"], "paper": ["A4"], "count": [1]}

    Like PHP, the print pages/PDF are split from the *grayscale* image
    (``$outimage_gray``) while the GeoTIFF/KMZ are written from the color
    tagged image (``$outimage``).
    """
    from .config import PAPER_TYPES
    from .export.geotiff import write_geotiff
    from .export.kmz import write_kmz
    from .export.pdf import pages_to_pdf
    from .splitter import determine_type, make_simage, split_grid, split_image

    px_per_km = source.pixel_per_km

    def step(name: str) -> None:
        _report(notifier, name)

    # Determine paper type
    paper = "A3" if args.a3 else "A4"
    dims = [d for d in args.dims] if args.dims else []
    if not dims:
        dims = ["5x7"] if paper == "A4" else ["7x10"]

    paper_cfg = PAPER_TYPES[paper]

    # Split image for each requested dimension; collect pages for the PDF.
    all_page_files: list[Path] = []
    outinfo = {"dim": [], "paper": [], "count": []}
    for dim in dims:
        if dim not in paper_cfg["dimensions"]:
            logger.warning("Unknown dimension %s for %s; skipping", dim, paper)
            continue
        tiles_w, tiles_h, (pw, ph), (pw_l, ph_l) = paper_cfg["dimensions"][dim]
        page_tw, page_th, landscape = determine_type(
            int(region.width_m / 1000), int(region.height_m / 1000),
            tiles_w, tiles_h,
        )
        px_w, px_h = (pw_l, ph_l) if landscape else (pw, ph)

        step(f"step:split:{dim}")
        page_w, page_h = int(page_tw * px_per_km), int(page_th * px_per_km)
        cols, rows = split_grid(gray_img.shape[1], gray_img.shape[0], page_w, page_h)
        pages = split_image(gray_img, region, px_per_km, page_tw, page_th)
        logger.info(
            "Split %s (%dx%d) into %d page(s) [%s]",
            dim, page_tw, page_th, len(pages),
            "landscape" if landscape else "portrait",
        )

        # Resize each page to paper px and write page PNG files
        page_files = []
        for i, page in enumerate(pages):
            page_img = make_simage(
                page, px_w, px_h, page_tw, page_th, px_per_km,
                grid_info={
                    "row": i // cols,
                    "col": i % cols,
                    "total_cols": cols,
                    "total_rows": rows,
                },
            )
            pf = outdir / f"{prefix}_{dim}_{i + 1}.png"
            _save_png(page_img, pf)
            page_files.append(pf)

        all_page_files.extend(page_files)
        outinfo["dim"].append(dim)
        outinfo["paper"].append(paper)
        outinfo["count"].append(len(pages))

    # PDF: all dims merged into a single `{prefix}.pdf` (mirrors the PHP
    # `array_merge(...$simage)` into one outfile).
    step("step:pdf")
    pdf_path = outdir / f"{prefix}.pdf"
    pages_to_pdf(all_page_files, pdf_path, title=args.title)
    logger.info("Wrote %s (%d pages)", pdf_path, len(all_page_files))

    # Clean up the temporary page images (mirrors the PHP `unlink` loop).
    for pf in all_page_files:
        try:
            pf.unlink()
        except OSError:
            pass

    # KMZ (3km tiles from tagged image)
    step("step:kmz")
    kmz_path = outdir / f"{prefix}.tag.kmz"
    write_kmz(color_img, region, px_per_km, kmz_path)

    # GeoTIFF (of the tagged image, like PHP `Geotiff::out($outimage)`)
    step("step:geotiff")
    tiff_path = outdir / f"{prefix}.tag.tiff"
    write_geotiff(color_img, region, px_per_km, tiff_path)

    return outinfo


# --- Helper commands ---


def cmd_list_sources(args) -> None:
    from .config import list_sources

    print(f"{'Key':<8} {'Label':<10} {'Zoom':<5} {'px/km':<6} {'Grayscale'}")
    print("-" * 50)
    for key, src in list_sources():
        print(
            f"{key:<8} {src.label:<10} {src.zoom:<5} {src.pixel_per_km:<6} {src.grayscale}"
        )


def cmd_test_source(args) -> None:
    from .config import get_source
    from .transforms import Pipeline

    source = get_source(args.source)
    outdir = Path(args.output)
    outdir.mkdir(parents=True, exist_ok=True)

    if args.tile:
        # Download a single tile and show preprocessing steps
        z, x, y = map(int, args.tile.split("/"))
        layer = source.layer_defs()[0]
        url = _tile_url(layer.url, layer.tile_order, z, x, y)
        logger.info("Fetching %s", url)
        _download_one(url, outdir / "01_raw_tile")
        # Apply pre_merge steps
        img = np_img(outdir / "01_raw_tile" / "tile.png")
        steps = [("01_raw_tile", img)]
        pre = Pipeline(source.pre_merge)
        step_img = img
        for i, t in enumerate(pre.transforms):
            step_img = t.apply(step_img)
            steps.append((f"{i + 2:02d}_pre_{type(t).__name__.lower()}", step_img))
        # Grayscale
        gray = _to_grayscale(step_img, source)
        steps.append(("grayscale", gray))
        for name, arr in steps:
            _save_png(arr, outdir / f"{name}.png")
        print(f"Wrote {len(steps)} step images to {outdir}")
    elif args.region:
        # Run the full pipeline over a region and save intermediate stages.

        _run_full_test(source, args, outdir)
    else:
        print(
            "Provide --tile z/x/y or --region x0,y0,w,h[,datum]"
            " to test preprocessing"
        )


def np_img(path: Path):
    from PIL import Image

    return Image.open(path).convert("RGBA")


def _tile_url(url_tmpl, tile_order, z, x, y):
    from .stitcher import _tile_url

    return _tile_url(url_tmpl, tile_order, z, x, y)


def _download_one(url: str, dest: Path) -> None:
    """Download a single tile to ``dest/tile.png`` (dest is a directory)."""
    import urllib.request

    dest.mkdir(parents=True, exist_ok=True)
    fname = dest / "tile.png"
    req = urllib.request.Request(
        url, headers={"User-Agent": "twmap-gen/0.1"}
    )
    with urllib.request.urlopen(req, timeout=30) as resp:
        data = resp.read()
    fname.write_bytes(data)


def _run_full_test(source, args, outdir: Path) -> None:
    """Build the base image for a region and save staged PNG outputs."""
    import tempfile

    from .stitcher import build_base_image

    region = _region_from_args(args)
    workdir = Path(tempfile.mkdtemp(prefix="twmap_test_"))
    try:
        base = asyncio.run(build_base_image(region, source, workdir=workdir))
        # Save intermediate outputs
        gray = _to_grayscale(base, source)
        for name, arr in (("base", base), ("grayscale", gray)):
            _save_png(arr, outdir / f"{source.name}_{name}.png")
        print(f"Wrote base/grayscale previews to {outdir}")
    finally:
        import shutil

        shutil.rmtree(workdir, ignore_errors=True)


def cmd_compare_sources(args) -> None:
    import tempfile

    from .config import get_source
    from .proj import Region
    from .stitcher import build_base_image

    keys = [k.strip() for k in args.sources.split(",") if k.strip()]
    if not keys:
        raise SystemExit("No sources to compare")
    p = _parse_region(args.region)
    region = Region(
        x0=p["x0"], y0=p["y0"],
        x1=p["x0"] + p["shiftx"] * 1000,
        y1=p["y0"] - p["shifty"] * 1000,
        datum=p["datum"], penghu=False,
    )
    outdir = Path(args.output)
    outdir.mkdir(parents=True, exist_ok=True)

    for key in keys:
        source = get_source(key)
        workdir = Path(tempfile.mkdtemp(prefix="twmap_cmp_"))
        try:
            base = asyncio.run(build_base_image(region, source, workdir=workdir))
            _save_png(base, outdir / f"{key}.png")
            logger.info("Wrote %s", outdir / f"{key}.png")
        finally:
            import shutil
            shutil.rmtree(workdir, ignore_errors=True)


def main(argv: list[str] | None = None) -> int:
    argv = list(argv) if argv is not None else sys.argv[1:]

    # Handle root-level --version / --help before subcommand rewriting.
    if "--version" in argv or "-V" in argv:
        print(__version__)
        return 0
    if (argv == ["--help"] or argv == ["-h"] or argv == []):
        parser = build_parser()
        parser.print_help()
        return 0

    # Detect subcommand: if the first non-flag token is a known subcommand,
    # dispatch to it; otherwise treat everything as the `make` command
    # (supports both `mapgen make ...` and bare legacy `mapgen -r ...`).
    first = next((a for a in argv if not a.startswith("-")), None)
    known = {"make", "legacy", "list-sources", "test-source", "compare-sources"}
    if first in known:
        argv = argv  # already has subcommand
    else:
        argv = ["make", *argv]

    parser = build_parser()
    args = parser.parse_args(argv)

    func = getattr(args, "func", None)
    if func is None:
        parser.print_help()
        return 0
    try:
        func(args)
    except KeyboardInterrupt:
        return 80
    except SystemExit:  # noqa: PERF203
        raise
    except Exception as exc:  # noqa: BLE001
        logger.error("Fatal: %s", exc, exc_info=True)
        return 1
    return 0


def _run_make(args) -> int:
    try:
        cmd_make(args)
        return 0
    except KeyboardInterrupt:
        return 80
    except Exception as exc:  # noqa: BLE001
        logger.error("Fatal: %s", exc, exc_info=True)
        return 1


if __name__ == "__main__":
    sys.exit(main())
