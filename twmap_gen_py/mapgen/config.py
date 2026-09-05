"""Map source registry and shared configuration.

Each map source (經建三, 魯地圖, 堡圖1904, ...) is a ``MapSource`` dataclass.
Adding a new source is simply a new entry in the ``SOURCES`` dict (or an
external JSON file), no subclassing required.

The ``pre_merge`` transforms are applied to each tile before merging; the
``post_merge`` transforms are applied to the merged, reprojected image.
Grayscale conversion is selected per source by name + params.
"""

from __future__ import annotations

from dataclasses import dataclass, field
from typing import Optional

from .transforms import (
    Equalize,
    Gamma,
    Level,
    Normalize,
    Transform,
)


@dataclass
class LayerDef:
    """A single tile layer within a map source.

    ``tile_url`` is a template with ``{z}/{x}/{y}`` placeholders.
    ``pre_merge`` applies per-tile processing specific to this layer
    (overrides the source-level ``pre_merge`` when set).
    """

    url: str
    tile_order: str = "xyz"
    pre_merge: Optional[list[Transform]] = None


@dataclass
class MapSource:
    """Definition of a stitched map source."""

    name: str
    label: str
    tile_url: str
    zoom: int = 16
    pixel_per_km: int = 315
    tile_order: str = "xyz"
    layers: Optional[list[LayerDef]] = None
    layers_gpx: Optional[list[LayerDef]] = None
    pre_merge: list[Transform] = field(default_factory=list)
    post_merge: list[Transform] = field(default_factory=list)
    grayscale: str = "simple"
    grayscale_params: dict = field(default_factory=dict)

    def layer_defs(self, include_gpx: bool = False) -> list[LayerDef]:
        """Return the effective tile-layer definitions.

        With ``include_gpx`` the PHP ``-G`` variant is used when available: a
        swapped ``*_nowp_nocache`` tile source (v3/2016) or the archive layer
        multiplied with the ``happyman_nowp`` overlay (NLSC / archival maps).

        If explicit layers are given, use them; otherwise synthesize a single
        layer from ``tile_url`` (with source-level pre_merge). Per-layer
        ``pre_merge`` takes precedence over the source-level one.
        """
        if include_gpx and self.layers_gpx:
            return self.layers_gpx
        if self.layers:
            return self.layers
        return [
            LayerDef(
                url=self.tile_url,
                tile_order=self.tile_order,
                pre_merge=list(self.pre_merge) if self.pre_merge else None,
            )
        ]


# Built-in registry. Keyed by the CLI map-type/version string.
# The `layers_gpx` variants mirror the PHP `include_gpx` (flag -G) tile URLs:
# single swapped `*_nowp_nocache` sources for v3/2016, and the archive layer
# multiplied with the `happyman_nowp` overlay for NLSC / archival maps.
_NOWP_GPX = "http://make.happyman.idv.tw/map/happyman_nowp/{z}/{x}/{y}.png"

SOURCES: dict[str, MapSource] = {
    "3": MapSource(
        name="3",
        label="經建三",
        tile_url="http://make.happyman.idv.tw/map/tw25k2001/{z}/{x}/{y}.png",
        zoom=16,
        pixel_per_km=315,
        pre_merge=[Equalize(), Gamma(2.2)],
        layers_gpx=[
            LayerDef(
                url="http://make.happyman.idv.tw/map/twmap_happyman_nowp_nocache/{z}/{x}/{y}.png",
                pre_merge=[Equalize(), Gamma(2.2)],
            ),
        ],
        grayscale="enhanced",
        grayscale_params={"brightness": 20, "contrast": 5, "tint": 40},
    ),
    "2016": MapSource(
        name="2016",
        label="魯地圖",
        tile_url="http://make.happyman.idv.tw/map/moi_nocache/{z}/{x}/{y}.png",
        zoom=16,
        pixel_per_km=315,
        layers_gpx=[
            LayerDef(
                url="http://make.happyman.idv.tw/map/moi_happyman_nowp_nocache/{z}/{x}/{y}.png",
            ),
        ],
        post_merge=[Normalize()],
        grayscale="simple",
    ),
    "nlsc": MapSource(
        name="nlsc",
        label="NLSC",
        tile_url="https://wmts.nlsc.gov.tw/wmts/EMAPX99/default/EPSG:3857/{z}/{y}/{x}",
        tile_order="yzx",
        zoom=17,
        pixel_per_km=630,
        pre_merge=[Level(0.25, 1.0, 0.1)],
        layers_gpx=[
            LayerDef(
                url="https://wmts.nlsc.gov.tw/wmts/EMAPX99/default/EPSG:3857/{z}/{y}/{x}",
                tile_order="yzx",
                pre_merge=[Level(0.25, 1.0, 0.1)],
            ),
            LayerDef(url=_NOWP_GPX),
        ],
        grayscale="simple",
    ),
    "1904": MapSource(
        name="1904",
        label="堡圖1904",
        tile_url=(
            "https://gis.sinica.edu.tw/tileserver/"
            "file-exists.php?img=JM20K_1904-jpg-{z}-{x}-{y}"
        ),
        zoom=16,
        pixel_per_km=315,
        layers_gpx=[
            LayerDef(
                url=(
                    "https://gis.sinica.edu.tw/tileserver/"
                    "file-exists.php?img=JM20K_1904-jpg-{z}-{x}-{y}"
                ),
            ),
            LayerDef(url=_NOWP_GPX),
        ],
        post_merge=[Normalize()],
        grayscale="adaptive_threshold",
    ),
    "1916": MapSource(
        name="1916",
        label="蕃地1916",
        tile_url=(
            "https://gis.sinica.edu.tw/tileserver/"
            "file-exists.php?img=JM50K_1916-jpg-{z}-{x}-{y}"
        ),
        zoom=16,
        pixel_per_km=315,
        layers_gpx=[
            LayerDef(
                url=(
                    "https://gis.sinica.edu.tw/tileserver/"
                    "file-exists.php?img=JM50K_1916-jpg-{z}-{x}-{y}"
                ),
            ),
            LayerDef(url=_NOWP_GPX),
        ],
        post_merge=[Normalize()],
        grayscale="simple",
    ),
    "1921": MapSource(
        name="1921",
        label="堡圖1921",
        tile_url=(
            "https://gis.sinica.edu.tw/tileserver/"
            "file-exists.php?img=JM20K_1921-jpg-{z}-{x}-{y}"
        ),
        zoom=16,
        pixel_per_km=315,
        pre_merge=[Level(0.25)],
        layers_gpx=[
            LayerDef(
                url=(
                    "https://gis.sinica.edu.tw/tileserver/"
                    "file-exists.php?img=JM20K_1921-jpg-{z}-{x}-{y}"
                ),
                pre_merge=[Level(0.25)],
            ),
            LayerDef(url=_NOWP_GPX),
        ],
        grayscale="adaptive_threshold",
    ),
    "1924": MapSource(
        name="1924",
        label="陸測1924",
        tile_url=(
            "https://gis.sinica.edu.tw/tileserver/"
            "file-exists.php?img=JM50K_1924_new-jpg-{z}-{x}-{y}"
        ),
        zoom=16,
        pixel_per_km=315,
        layers_gpx=[
            LayerDef(
                url=(
                    "https://gis.sinica.edu.tw/tileserver/"
                    "file-exists.php?img=JM50K_1924_new-jpg-{z}-{x}-{y}"
                ),
            ),
            LayerDef(url=_NOWP_GPX),
        ],
        grayscale="adaptive_threshold",
    ),
}


# --- Paper / output configuration (from PHP cmd_make2.php) ---

PAPER_TYPES = {
    # paper: (width_tiles, height_tiles, portrait_px, landscape_px)
    "A4": {
        "dimensions": {
            "5x7": (5, 7, (1492, 2110), (2110, 1492)),
            "4x6": (4, 6, (1492, 2110), (2110, 1492)),
            "3x4": (3, 4, (1492, 2110), (2110, 1492)),
            "2x3": (2, 3, (1492, 2110), (2110, 1492)),
            "1x2": (1, 2, (1492, 2110), (2110, 1492)),
        }
    },
    "A3": {
        "dimensions": {
            "7x10": (7, 10, (2110, 2984), (2984, 2110)),
            "6x8": (6, 8, (2110, 2984), (2984, 2110)),
            "4x6": (4, 6, (2110, 2984), (2984, 2110)),
            "3x4": (3, 4, (2110, 2984), (2984, 2110)),
            "2x2": (2, 2, (2110, 2984), (2984, 2110)),
        }
    },
}

# Maximum region size in pixels before auto-chunking kicks in
MAX_CHUNK_PX = 15000

# Overlap (px) included on page edges for joining printed pages
PAGE_OVERLAP_PX = 42


# --- Geographic bounds validation (TWD67, km) ---

TAIWAN_BOUNDS = {
    "taiwan": {"x": (150, 355), "y": (2420, 2800)},
    "penghu": {"x": (280, 330), "y": (2500, 2630)},
}

# TWD97/67 coordinate systems.
# TWD97 (EPSG:3826/3825) sits on GRS80, ~a few metres from WGS84, so PROJ's
# database transforms are correct.
# TWD67 (Hu-Tzu-Shan, ellipsoid aust_SA=GRS67) has NO real registered datum
# shift in PROJ: EPSG:3828 maps to WGS84 with a null transform (i.e. TWD67 is
# georeferenced almost identically to TWD97 there), which puts every feature
# ~700 m east of its true TWD67 position. Taiwan's standard TWD67->WGS84 is
# the 7-parameter Helmert used by the original PHP stitcher (Proj.php), so we
# define it explicitly with +towgs84 (lon_0 = 121 mainland / 119 Penghu).
CRS_TWD97 = "EPSG:3826"  # TWD97 / TM2 zone 121 (Taiwan mainland)
CRS_TWD97_PH = "EPSG:3825"  # TWD97 / TM2 zone 119 (Penghu)
_TWD67_TOWGS84 = "-764.558,-361.229,-178.374,-.0000011698,.0000018398,.0000009822,.00002329"
CRS_TWD67 = (
    "+proj=tmerc +lat_0=0 +lon_0=121 +k=0.9999 +x_0=250000 +y_0=0 "
    f"+ellps=aust_SA +towgs84={_TWD67_TOWGS84} +units=m +no_defs"
)  # TWD67 / TM2 zone 121 (Taiwan mainland), datum via +towgs84
CRS_TWD67_PH = (
    "+proj=tmerc +lat_0=0 +lon_0=119 +k=0.9999 +x_0=250000 +y_0=0 "
    f"+ellps=aust_SA +towgs84={_TWD67_TOWGS84} +units=m +no_defs"
)  # TWD67 / TM2 zone 119 (Penghu), datum via +towgs84
CRS_WGS84 = "EPSG:4326"
CRS_MERCATOR = "EPSG:3857"


def get_source(name: str) -> MapSource:
    """Return a MapSource by CLI map-type string, raising KeyError if unknown."""
    try:
        return SOURCES[name]
    except KeyError:
        known = ", ".join(SOURCES)
        raise KeyError(
            f"Unknown map source: {name!r}. Known sources: {known}"
        ) from None


def list_sources() -> list[tuple[str, MapSource]]:
    """Return sorted (key, MapSource) pairs for display."""
    return sorted(SOURCES.items(), key=lambda kv: kv[0])


def load_json_sources(path: str) -> None:
    """Merge external map source definitions from a JSON file.

    The optional external file (``map_sources.json``) lets users add sources
    at runtime without editing code. Field names match the ``MapSource``
    dataclass; transforms are specified as dicts understood by
    ``transforms.build_pipeline``.
    """
    import json

    from .transforms import build_pipeline

    with open(path, "r", encoding="utf-8") as fh:
        data = json.load(fh)

    for key, obj in data.items():
        # Convert declarative transform lists into Pipeline-able lists
        if "pre_merge" in obj and isinstance(obj["pre_merge"], list):
            obj["pre_merge"] = build_pipeline(obj["pre_merge"]).transforms
        if "post_merge" in obj and isinstance(obj["post_merge"], list):
            obj["post_merge"] = build_pipeline(obj["post_merge"]).transforms
        SOURCES[key] = MapSource(**obj)


__all__ = [
    "MapSource",
    "LayerDef",
    "SOURCES",
    "PAPER_TYPES",
    "MAX_CHUNK_PX",
    "PAGE_OVERLAP_PX",
    "TAIWAN_BOUNDS",
    "CRS_TWD67",
    "CRS_TWD97",
    "CRS_TWD67_PH",
    "CRS_TWD97_PH",
    "CRS_WGS84",
    "CRS_MERCATOR",
    "get_source",
    "list_sources",
    "load_json_sources",
]
