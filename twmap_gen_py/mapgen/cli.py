"""Command-line interface.

New Pythonic interface (``mapgen make ...``) plus legacy ``cmd_make2.py``
-compatible aliases (-r/-O/-v/-g/-e/-G/-3/-D/-c/-p). Also provides helpers:
``list-sources``, ``test-source``, ``compare-sources``.
"""

from __future__ import annotations

import argparse
import asyncio
import logging
import sys
import tempfile
from pathlib import Path

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


def _parse_region(spec: str) -> dict:
    """Parse both 'x0,y0,w,h[,datum]' and legacy 'x:y:w:h:datum' formats."""
    sep = ":" if ":" in spec else ","
    parts = [p for p in spec.split(sep) if p != ""]
    if len(parts) not in (4, 5):
        raise SystemExit(
            f"Invalid region: {spec!r}. Use x0,y0,shiftx,shifty[,datum] "
            "or legacy x0:y0:shiftx:shifty:datum"
        )
    x0, y0, sx, sy = (float(parts[0]), float(parts[1]), int(parts[2]), int(parts[3]))
    datum = parts[4].upper() if len(parts) == 5 else "TWD97"
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

    from .grinder import composite_logo, draw_grid_lines, optimize_png, tag_coordinates
    from .stitcher import build_base_image

    logging.basicConfig(
        level=logging.DEBUG if args.debug else logging.INFO,
        format="%(asctime)s %(levelname)s %(name)s: %(message)s",
    )

    outdir = Path(args.output)
    outdir.mkdir(parents=True, exist_ok=True)
    tmpdir = Path(args.tmpdir)
    tmpdir.mkdir(parents=True, exist_ok=True)

    # Build the base image (download + merge + reproject)
    logger.info("Generating %s over %s", source.name, region)
    workdir = Path(tempfile.mkdtemp(dir=str(tmpdir), prefix="twmap_"))
    try:
        base = asyncio.run(
            build_base_image(region, source, workdir=workdir)
        )

        # Apply post-reproject operations: grid, logo, tags
        img = base
        if args.grid_100m:
            img = draw_grid_lines(img, source.pixel_per_km, step_m=100)
        # 1000m grid is always drawn except v3+TWD67
        if not (args.map_type == "3" and region.datum == "TWD67"):
            img = draw_grid_lines(img, source.pixel_per_km, step_m=1000)
        img = composite_logo(img, source.label)
        img = tag_coordinates(img, region, source.pixel_per_km)

        if not args.keep_color:
            img = _to_grayscale(img, source)

        # Output files
        prefix = _output_prefix(region, args)
        tag_png = outdir / f"{prefix}.tag.png"
        gray_png = outdir / f"{prefix}.gray.png"

        if args.keep_color:
            _save_png(img, tag_png)
            _save_png(img, gray_png)
        else:
            _save_png(img, gray_png)
            _save_png(img, tag_png)  # color copy (tagged)

        optimize_png(tag_png)
        optimize_png(gray_png)

        logger.info("Wrote %s and %s", tag_png, gray_png)

        # GPX overlay handled in a later stage (alpha compositing)
        if args.gpx:
            logger.info("GPX overlay requested but not yet wired (next stage)")

        # Split into pages + export
        _handle_export(img, region, source, args, outdir, prefix)

    finally:
        import shutil

        shutil.rmtree(workdir, ignore_errors=True)


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


def _output_prefix(region, args) -> str:
    # Prefix like: 307000x2677000-12x6-v2016_TWD67
    x0 = int(region.x0)
    y0 = int(region.y0)
    sx = int(region.width_m / 1000)
    sy = int(region.height_m / 1000)
    datum = region.datum
    return f"{x0}x{y0}-{sx}x{sy}-v{args.map_type}_{datum}"


def _handle_export(img, region, source, args, outdir: Path, prefix: str) -> None:
    """Split into pages and produce PDF/KMZ/GeoTIFF exports."""
    from .config import PAPER_TYPES
    from .export.geotiff import write_geotiff
    from .export.kmz import write_kmz
    from .export.pdf import pages_to_pdf
    from .splitter import determine_type, make_simage, split_image

    px_per_km = source.pixel_per_km

    # Determine paper type
    paper = "A3" if args.a3 else "A4"
    dims = [d for d in args.dims] if args.dims else []
    if not dims:
        dims = ["5x7"] if paper == "A4" else ["7x10"]

    paper_cfg = PAPER_TYPES[paper]

    # Split image and generate PDF for each requested dimension
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

        pages = split_image(img, region, px_per_km, tiles_w, tiles_h)

        # Resize each page to paper px and write page PNG files
        page_files = []
        for i, page in enumerate(pages):
            page_img = make_simage(
                page, px_w, px_h,
                grid_info={
                    "row": i // _page_cols(pages),
                    "col": i % _page_cols(pages),
                    "total_cols": _page_cols(pages),
                    "total_rows": _page_rows(pages),
                },
            )
            pf = outdir / f"{prefix}_{dim}_{i + 1}.png"
            _save_png(page_img, pf)
            page_files.append(pf)

        # PDF
        pdf_path = outdir / f"{prefix}_{dim}.pdf"
        pages_to_pdf(page_files, pdf_path, title=args.title)

    # KMZ (3km tiles from tagged image)
    kmz_path = outdir / f"{prefix}.kmz"
    write_kmz(img, region, px_per_km, kmz_path)

    # GeoTIFF
    tiff_path = outdir / f"{prefix}.tiff"
    write_geotiff(img, region, px_per_km, tiff_path)


def _page_cols(pages) -> int:
    # Simple heuristic: assume square-ish grid; refined by splitter in future
    import math
    return int(math.ceil(math.sqrt(len(pages))))


def _page_rows(pages) -> int:
    import math
    n = len(pages)
    cols = _page_cols(pages)
    return max(1, math.ceil(n / cols))


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
