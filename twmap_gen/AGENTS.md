# AGENTS.md — twmap_gen (PHP 地圖產生器)

## What this is
Taiwan map generator: web UI + queue workers + CLI that stitch raster tiles into
printable maps (PDF/KMZ/GeoTIFF/PNG). PHP 8.1, PostgreSQL, adodb, beanstalkd queue.
Served as `/gen` (`/home/happyman/projects/dev/gen` -> this dir). Not to be confused with the
Python rewrite tracked in `/home/happyman/projects/dev/AGENTS.md` (repo root).

## Commands
- Tests: `vendor/bin/phpunit` (46 tests; bootstrap = `tests/bootstrap.php`)
- Lint: `php -l <file>` (no linter/formatter configured)
- Dependency check: `php cmd_make2.php --check` (verifies montage/composite/pngquant/curl/gdal…)
- Dry-run maintenance: `php api/migrate.php check` / `php expire.php` (both default dry-run)

## Architecture / key files
- `cmd_make2.php` — CLI entry for map generation. `getopt("O:r:v:t:…")`;
  `-r startx:starty:w:h:datum -O outdir -v version`. Never called from web directly.
- `backend_make.php` — web form handler; puts job on beanstalkd (`xobotyi/beansclient`) tube `make_map`.
- `worker/make_map/` — queue worker; runs `cmd_make2.php` (locally or via docker).
  `make_map_local.php` (same host) / `make_map.php` (docker), `stats.php -d` to inspect queue.
- `api/made.php` — worker callback; `finish_task()` registers the finished map.
- `lib/Twmap/Stitcher.php` — base class + subclasses per tile source
  (`Rudymap_`, `NLSC_`, `JM20K1904_`, `JM50K1916_`, `JM20K1921_`, `JM50K1924_`).
  Requires external binaries (imagemagick `montage`/`composite`, `pngquant`, `curl`).
- `lib/Twmap/` others: `Proj.php` (TWD67/97 projection), `Splitter.php` (page split),
  `Websocat.php` (WS progress), `Export/{Pdf,Geotiff,GarminKmz}.php`, `Svg/Gpx2Svg.php`.
- `lib/twmapdb.inc.php` — DB layer (`get_conn()` adodb/postgres), cache
  (`memcached_*`, `redis_*`), path helpers, `map_del()`/`map_expire()`.
- `expire.php` — daily disk-budget + consistency job (flock, dry-run default,
  `--apply` to act, exit 0/2/3). Cron: `30 3 * * * … --limit-gb=150 --apply`.
- `api/migrate.php` — path migration + maintenance: modes
  `plan|copy|verify|purge|residue|check|fix`; shares R1–R4 rules with expire.
- `lib/expirelib.inc.php` — testable parts shared by both (lock/state, file-family
  scan, rule evaluation, expiry candidates/budget, dir cleanup).
- `config.inc.php` — **not in git** (see `.gitignore`); copy from `.sample`.
  Defines `$fs_root`, `$out_roots`, DB DSN, cache/queue config.

## Conventions
- Paths in DB are **relative** now; use `map_rel_path()`/`map_fs_path()`/`map_fs_root()`.
  `$out_root` (absolute, `/srv/www/htdocs/map/out`) is legacy-only — do not write new code against it.
- Delete only under roots returned by `expire_roots()`; check with `expire_path_ok()`.
- New code: tabs, `array()`, Chinese comments/docblocks, `usage_exit()` help pattern
  (print docblock until `*/`).
- Tests: namespace `Tests`, strict types, promote config globals to `$GLOBALS` in
  bootstrap (PHPUnit loads it in function scope).

## Gotchas
- `get_conn()` opens a **new** connection every call. `map_get_single()`'s
  memcached-hit path never closes it — call `memcached_delete("map_get_single_<mid>")`
  before `map_del()`/`map_expire()` so the DB path (which closes) is taken.
- `map_expire()` keeps any file whose basename contains `.gpx` (case-sensitive: `.GPX` is deleted).
- Check/fix runs stream rows mid-cursor (`mid > after`); don't accumulate full tables in memory.
- `migrate.php` check has no `size` column → `expire_eval_rules(..., false)` skips R4.1.
- Live production DB is on localhost (`user=docker`, `dbname=twmap`); maintenance scripts
  default to full-table scans (~250k rows, ~30 s) — use `--uid/--mid/--limit` to scope tests.
