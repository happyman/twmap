#!/usr/bin/env python3
import json
import os
import random
import subprocess
import sys

from config import (
    CF_API_KEY,
    CF_EMAIL,
    CF_ZONE_ID,
    RUDY_ADMIN_EMAIL as ADMIN_EMAIL,
    RUDY_BASE as BASE,
    RUDY_CUR_VER_FPATH as CUR_VER_FPATH,
    RUDY_FPATH as FPATH,
    RUDY_MAIN_LAYERS as MAIN_LAYERS,
    RUDY_NOCACHE_LAYERS as NOCACHE_LAYERS,
    RUDY_RSYNC_REMOTE as RSYNC_REMOTE,
    RUDY_TILESTACHE_CFG as TILESTACHE_CFG,
    RUDY_TILESTACHE_MOIOSM_CFG as TILESTACHE_MOIOSM_CFG,
    RUDY_WANT as WANT,
    RUDY_ZOOMS as ZOOMS,
)


def main():
    version_only = "-v" in sys.argv

    r = random.randint(0, 999999)
    result = subprocess.run(
        ["curl", "-sL", f"{BASE}/index.json?{r}"], capture_output=True, text=True
    )
    v = json.loads(result.stdout)

    cur_ver = ""
    if os.path.exists(CUR_VER_FPATH):
        with open(CUR_VER_FPATH) as f:
            cur_ver = f.read().strip()
    if not cur_ver:
        cur_ver = "v0.0"

    print(f"cur_ver = {cur_ver} ", end="")

    dirty = False
    online_ver = None
    for vv in v:
        if vv["name"] in WANT:
            if (
                vv["name"] == "MOI_OSM_Taiwan_TOPO_Rudy.map.zip"
                and vv["version"] != cur_ver
            ):
                dirty = True
                online_ver = vv["version"]

    if dirty:
        print(f";online_ver = {online_ver}")
        if version_only:
            sys.exit(0)
        do_update(online_ver, cur_ver)
    else:
        print("no update required..")
        subprocess.run(
            ["mail", "-s", "rudy map checked", ADMIN_EMAIL],
            input=f"version is now {cur_ver}",
            text=True,
        )


def do_update(ver, old_ver):
    ver_dir = os.path.join(FPATH, ver)

    def my_system(cmd):
        result = subprocess.run(cmd)
        if result.returncode != 0:
            subprocess.run(["rm", "-rf", ver_dir])
            print(f"cmd fails: {cmd}")
            sys.exit(1)

    # 1. download and unzip
    os.makedirs(ver_dir, mode=0o755, exist_ok=True)
    os.chdir(ver_dir)

    zips = [
        "MOI_OSM_Taiwan_TOPO_Rudy.map.zip",
        "MOI_OSM_Taiwan_TOPO_Rudy.poi.zip",
        "MOI_OSM_twmap_style.zip",
        "MOI_OSM_bn_style.zip",
        "MOI_OSM_dn_style.zip",
        "MOI_OSM_Taiwan_TOPO_Rudy_style.zip",
        "MOI_OSM_tn_style.zip",
    ]
    for zip_name in zips:
        my_system(["rsync", "-avP", f"{RSYNC_REMOTE}{zip_name}", f"./{zip_name}"])
        my_system(["unzip", "-o", zip_name])

    # 2. verify version
    my_system(
        [
            "bash",
            "-c",
            f"strings MOI_OSM_Taiwan_TOPO_Rudy.map | grep -e 'RuMAP {ver}'",
        ]
    )

    os.chdir(FPATH)

    # 3. flush memcached
    memcached_hosts = ["localhost:11211", "192.168.98.111:11211"]
    for host in memcached_hosts:
        print(f"flushing memcached {host}...")
        result = subprocess.run(
            ["bash", "-c", f"echo 'flush_all' | nc -N {host}"],
            capture_output=True, text=True,
        )
        if result.returncode != 0:
            print(f"warning: memcached flush for {host} failed (exit {result.returncode})")
        elif "OK" not in result.stdout:
            print(f"warning: memcached flush for {host} response: {result.stdout.strip()}")

    # 4. symlink cur -> ver
    os.chdir(FPATH)
    cur_link = os.path.join(FPATH, "cur")
    if os.path.lexists(cur_link):
        os.remove(cur_link)
    os.symlink(ver, cur_link)

    # 5. update VERSION
    print("update VERSION file")
    with open(CUR_VER_FPATH, "w") as f:
        f.write(ver)

    # 6. restart java tile server
    print("restart java tile server...")
    subprocess.run(["killall", "-9", "java"], capture_output=True)

    # 7. purge cloudflare cache
    purge_cache()

    # 8. email
    subprocess.run(
        ["mail", "-s", "rudy map updated!", ADMIN_EMAIL],
        input=f"version is now {ver}",
        text=True,
    )


def purge_cache():
    print("purging cloudflare cache...")
    result = subprocess.run(
        [
            "curl", "-X", "POST",
            f"https://api.cloudflare.com/client/v4/zones/{CF_ZONE_ID}/purge_cache",
            "-H", f"X-Auth-Email: {CF_EMAIL}",
            "-H", f"X-Auth-Key: {CF_API_KEY}",
            "-H", "Content-Type: application/json",
            "--data", '{"purge_everything":true}',
        ],
        capture_output=True,
        text=True,
    )
    if result.returncode != 0:
        print(f"warning: cloudflare purge failed (exit {result.returncode}): {result.stderr}")
    else:
        print(result.stdout)


if __name__ == "__main__":
    main()
