# 複製此檔為 config.py 並填入你的實際設定
# config.py 已被 .gitignore 排除，不會上傳到 GitHub

# --- 共用設定 ---
ADMIN_EMAIL = "your_local_user"
CF_ZONE_ID = "your-zone-id"
CF_EMAIL = "your-email@example.com"
CF_API_KEY = "your-cloudflare-api-key"

# --- RudyMap ---
RUDY_ADMIN_EMAIL = "your_local_user"
RUDY_FPATH = "/path/to/rudymap"
RUDY_CUR_VER_FPATH = f"{RUDY_FPATH}/VERSION"
RUDY_TILESTACHE_CFG = "/path/to/tile_main.cfg"
RUDY_TILESTACHE_MOIOSM_CFG = "/path/to/tile_moiosm.cfg"
RUDY_ZOOMS = [str(i) for i in range(7, 21)]
RUDY_BASE = "https://your-domain.com/rudy/"
RUDY_WANT = [
    "MOI_OSM_Taiwan_TOPO_Rudy.map.zip",
    "MOI_OSM_Taiwan_TOPO_Rudy.poi.zip",
    "MOI_OSM_Taiwan_TOPO_Rudy_style.zip",
]
RUDY_RSYNC_REMOTE = "your-host:rudy/static/"
RUDY_MAIN_LAYERS = ["moi_osm", "moi_osm_gpx", "rudy_default"]
RUDY_NOCACHE_LAYERS = ["moi_happyman_nowp_nocache", "twmap_happyman_nowp_nocache"]

# --- Happyman ---
HAPPYMAN_ADMIN_EMAIL = "your-email@example.com"
HAPPYMAN_FPATH = "/path/to/happyman"
HAPPYMAN_CUR_VER_FPATH = f"{HAPPYMAN_FPATH}/VERSION"
HAPPYMAN_TILESTACHE_CFG = "/path/to/tile_moiosm.cfg"
HAPPYMAN_ZOOMS = [str(i) for i in range(7, 18)]
HAPPYMAN_BASE = "https://your-domain.com/~user/gpx_map/?type=json"
HAPPYMAN_LAYERS = ["happyman_nowp", "moi_happyman_nowp_nocache", "twmap_happyman_nowp_nocache"]
