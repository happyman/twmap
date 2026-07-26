#!/usr/bin/env python3
import json
import os
import random
import subprocess
import sys

from config import (
    HAPPYMAN_ADMIN_EMAIL,
    HAPPYMAN_BASE,
    HAPPYMAN_CUR_VER_FPATH,
    HAPPYMAN_FPATH,
    HAPPYMAN_LAYERS,
    HAPPYMAN_TILESTACHE_CFG,
    HAPPYMAN_ZOOMS,
)


def main():
    version_only = "-v" in sys.argv

    r = random.randint(0, 999999)
    print(f"check {HAPPYMAN_BASE} for new release")
    result = subprocess.run(
        ["curl", "-sL", f"{HAPPYMAN_BASE}&{r}"], capture_output=True, text=True
    )
    v = json.loads(result.stdout)
    ver = v[0]["version"]

    os.makedirs(HAPPYMAN_FPATH, mode=0o755, exist_ok=True)

    cur_ver = ""
    if os.path.exists(HAPPYMAN_CUR_VER_FPATH):
        with open(HAPPYMAN_CUR_VER_FPATH) as f:
            cur_ver = f.read().strip()
    if not cur_ver:
        cur_ver = "v0.0"

    print(f"cur_ver = {cur_ver} ", end="")

    if cur_ver != ver:
        print(f";online_ver = {ver}")
        if version_only:
            sys.exit(0)
        do_update(v[0], ver, cur_ver)
        subprocess.run(
            ["mail", "-s", "Happyman.map updated!", HAPPYMAN_ADMIN_EMAIL],
            input=f"version is now {ver}",
            text=True,
        )
    else:
        print("no update required..")
        subprocess.run(
            ["mail", "-s", "Happyman.map checked", HAPPYMAN_ADMIN_EMAIL],
            input=f"version is now {cur_ver}",
            text=True,
        )


def do_update(data, ver, old_ver):
    ver_dir = os.path.join(HAPPYMAN_FPATH, ver)

    def my_system(cmd):
        result = subprocess.run(cmd)
        if result.returncode != 0:
            subprocess.run(["rm", "-rf", ver_dir])
            print(f"cmd fails: {cmd}")
            sys.exit(1)

    # 1. download and unzip
    os.makedirs(ver_dir, mode=0o755, exist_ok=True)
    os.chdir(ver_dir)
    my_system(["wget", "-O", "Happyman.map.zip", data["url"]])
    my_system(["unzip", "-o", "Happyman.map.zip"])
    os.chdir(HAPPYMAN_FPATH)

    # 2. flush memcached
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

    # 3. symlink cur -> ver
    cur_link = os.path.join(HAPPYMAN_FPATH, "cur")
    if os.path.lexists(cur_link):
        os.remove(cur_link)
    os.symlink(ver, cur_link)

    # 4. update VERSION
    print("update VERSION file")
    with open(HAPPYMAN_CUR_VER_FPATH, "w") as f:
        f.write(ver)

    # 5. restart java tile server
    print("restart java tile server...")
    subprocess.run(["killall", "java"], capture_output=True)

    print(f"Happyman map update from {old_ver} to {ver}")


if __name__ == "__main__":
    main()
