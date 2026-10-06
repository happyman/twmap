<?php
/**
 * map / track 路徑遷移工具 (DB-driven, 不做 find/ls 掃全碟)
 *
 * 用法:
 *   php api/migrate.php plan     [options]  只列統計, 不動任何東西
 *   php api/migrate.php copy     [options]  一列一列複製到 --dest, 同時把 DB 改成相對路徑 (不刪除來源)
 *   php api/migrate.php verify   [options]  檢查 --dest 是否齊全
 *   php api/migrate.php purge    [options]  刪除無用的 map 列 (同步刪 gpx_trk/gpx_wp/map_rank)
 *   php api/migrate.php residue  [options]  列出 --dest 上沒被 DB 引用的檔案; 加 --delete 才真的刪
 *   php api/migrate.php check    [options]  依 flag 規則檢查 DB 與檔案是否一致 (只讀, 列出違規)
 *   php api/migrate.php fix      [options]  修復違規; 預設 dry-run, 加 --delete 才真的動
 *
 * 通用選項:
 *   --dest=DIR     目標根目錄 (預設 /home/happyman/maproot/out)
 *   --src=DIR      來源根目錄 (預設 $fs_root)
 *   --uid=N        只處理某 uid
 *   --mid=N        只處理單一 mid
 *   --from-mid=N   從 mid > N 開始
 *   --since=TS     只處理 cdate > TS 的 map 列 (例: "2026-10-06 14:00"); 增量同步用
 *   --limit=N      最多處理 N 列 (0 = 不限)
 *   --chunk=N      每 N 列回報一次 (預設 1000)
 *   --state=FILE   進度檔 (預設 /tmp/twmap_migrate.<mode>.state)
 *   --resume       讀進度檔, 從上次的 mid 繼續
 *   --dry-run      只計算, 不寫檔也不寫 DB
 *   --tracks       一併處理 track
 *   --delete       residue 專用: 真的刪除殘檔 (搭配 --dry-run 可先試算)
 *
 * purge 額外選項:
 *   --flag=N       只挑 flag=N (0=ok 1=expired 2=deleted)
 *   --gpx=0|1      只挑 gpx=0 或 gpx=1
 *   --missing      只挑檔案完全不存在的列 (預設, 安全)
 *   --all          不檢查檔案, 依 --flag/--gpx 條件全刪 (需 --force)
 *   --force        搭配 --all 使用
 *
 * check 規則 (每項違規寫一份明細到 <state>.check):
 *   R1.1  flag=0 但 filename 指向的檔不存在     -> 應做 delete 操作 (flag=2 + ddate)
 *   R1.2  flag=0 但整個檔案家族都不在           -> 同上
 *   R1.3  flag=0 卻有 ddate                     -> 應為 NULL
 *   R1.4  flag=0 卻有 edate                     -> 應為 NULL
 *   R2.1  flag=1 卻沒有 edate                   -> 應有 expire time
 *   R2.2  flag=1 且 gpx=0 卻還有殘檔            -> 應清空
 *   R2.3  flag=1 且 gpx=1 卻沒有 gpx 檔         -> INFO: 依決定保留, 不算 fail
 *   R3.1  flag=2 卻沒有 ddate                   -> 應有 delete time
 *   R3.2  flag=2 檔案卻還在                     -> 應清空
 *
 * fix 動作 (依序執行, 每列明細寫到 <state>.fix):
 *   F1  flag=0 且 filename 不在  -> map_del($mid) (刪家族檔 + flag=2 + size=0 + ddate=NOW())
 *   F2  flag=0 且有 ddate/edate  -> 清成 NULL (含 memcached 失效)
 *   F3  flag=1 且 gpx=0 有殘檔   -> 只刪檔案, 列保留 (仍 flag=1 + edate)
 *   F4  flag=2 且還有檔案        -> 只刪檔案, 列保留 (仍 flag=2 + ddate)
 */

require_once(__DIR__ . "/../config.inc.php");

$MODES = array("plan", "copy", "verify", "purge", "residue", "check", "fix");

function usage_exit($msg = "") {
	if ($msg !== "") fwrite(STDERR, "ERROR: $msg\n\n");
	foreach (file(__FILE__) as $l) {
		fwrite(STDERR, rtrim($l, "\n") . "\n");
		if (strpos($l, "*/") !== false) break;
	}
	exit(1);
}

// ---- 參數解析 -----------------------------------------------------------
$args = array_slice($_SERVER['argv'], 1);
if (count($args) === 0) usage_exit("缺少 mode");
$mode = array_shift($args);
if (!in_array($mode, $MODES)) usage_exit("未知 mode: $mode");

$opt = array(
	"dest"     => "/home/happyman/maproot/out",
	"src"      => null,
	"uid"      => 0,
	"mid"      => 0,
	"from_mid" => 0,
	"since"    => null,
	"limit"    => 0,
	"chunk"    => 1000,
	"state"    => null,
	"resume"   => false,
	"dry_run"  => false,
	"tracks"   => false,
	"flag"     => null,
	"gpx"      => null,
	"missing"  => false,
	"all"      => false,
	"force"    => false,
	"delete"   => false,
);

foreach ($args as $a) {
	if (substr($a, 0, 2) !== "--") usage_exit("無法解析參數: $a");
	$kv = substr($a, 2);
	$eq = strpos($kv, "=");
	if ($eq === false) {
		$name = $kv;
		$val = true;
	} else {
		$name = substr($kv, 0, $eq);
		$val = substr($kv, $eq + 1);
	}
	switch ($name) {
		case "dest": case "src": case "state": case "since": $opt[$name] = $val; break;
		case "uid": case "mid": case "from-mid": case "limit": case "chunk":
			$opt[str_replace("-", "_", $name)] = intval($val); break;
		case "flag": $opt["flag"] = intval($val); break;
		case "gpx": $opt["gpx"] = intval($val); break;
		case "resume": case "dry-run": case "tracks": case "missing":
		case "all": case "force": case "delete":
			$opt[str_replace("-", "_", $name)] = true; break;
		default: usage_exit("未知選項: --$name");
	}
}
if ($opt["src"] === null) $opt["src"] = map_fs_root();
if ($opt["src"] === "") $opt["src"] = $out_root;
if ($opt["state"] === null || $opt["state"] === "") $opt["state"] = "/tmp/twmap_migrate.$mode.state";
$opt["src"] = rtrim($opt["src"], "/");
$opt["dest"] = rtrim($opt["dest"], "/");

if ($mode === "purge") {
	if ($opt["all"] && !$opt["force"]) usage_exit("--all 必須搭配 --force");
	if (!$opt["all"] && !$opt["missing"]) $opt["missing"] = true;
	if ($opt["missing"]) $opt["all"] = false;
}

// ---- 進度檔 -------------------------------------------------------------
function state_load($f) {
	if (!is_file($f)) return array();
	$j = json_decode(file_get_contents($f), true);
	return is_array($j) ? $j : array();
}
function state_save($f, $st) {
	@file_put_contents($f, json_encode($st, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

// ---- 資料列舉 (以 mid 游標分頁, 一次拿 $chunk 列) -------------------------
function fetch_maps($db, $opt, $after_mid, $chunk) {
	$w = array("mid > " . intval($after_mid));
	if ($opt["uid"])    $w[] = "uid = " . intval($opt["uid"]);
	if ($opt["mid"])    $w[] = "mid = " . intval($opt["mid"]);
	if ($opt["flag"] !== null) $w[] = "flag = " . intval($opt["flag"]);
	if ($opt["gpx"] !== null)  $w[] = "gpx  = " . intval($opt["gpx"]);
	if ($opt["since"] !== null && $opt["since"] !== "")
		$w[] = "cdate > '" . pg_escape_string($opt["since"]) . "'";
	$sql = sprintf("SELECT mid, uid, filename, flag, gpx, cdate, ddate, edate, \"count\", title FROM \"map\" WHERE %s ORDER BY mid ASC LIMIT %d",
		implode(" AND ", $w), $chunk);
	$rs = $db->GetAll($sql);
	return ($rs === false) ? array() : $rs;
}

function fetch_tracks($db, $opt, $after_tid, $chunk) {
	$w = array("tid > " . intval($after_tid));
	if ($opt["uid"]) $w[] = "uid = " . intval($opt["uid"]);
	$sql = sprintf("SELECT tid, uid, path, md5name FROM \"track\" WHERE %s ORDER BY tid ASC LIMIT %d",
		implode(" AND ", $w), $chunk);
	$rs = $db->GetAll($sql);
	return ($rs === false) ? array() : $rs;
}

// 一列底下的所有檔案 (絕對路徑, 來源端)
function row_files($filename) {
	$files = map_files($filename);
	if ($files === false || count($files) === 0) {
		// map_files 只認 \d+x\d+-\d+x\d+ 開頭; 兜底用 tag.png 本身
		$src = map_fs_path($filename);
		if (is_file($src)) $files = array($src);
	}
	return ($files === false) ? array() : $files;
}

function track_files_of($path, $tid) {
	$dir = map_fs_path(rtrim($path, "/") . "/" . intval($tid));
	if (!is_dir($dir)) return array();
	$g = glob($dir . "/*.*");
	return ($g === false) ? array() : $g;
}

// 單檔複製: 已存在且大小一致就跳過 (resume 用)
function copy_one($src, $dst, $dry_run) {
	if (!is_file($src)) return array(false, 0, "missing source");
	$ss = filesize($src);
	if (is_file($dst) && filesize($dst) === $ss) return array(true, 0, "already");
	if ($dry_run) return array(true, $ss, "would copy");
	$ddir = dirname($dst);
	if (!is_dir($ddir) && !@mkdir($ddir, 0755, true)) return array(false, 0, "mkdir fail");
	$tmp = $dst . ".part";
	if (!@copy($src, $tmp)) {
		@unlink($tmp);
		return array(false, 0, "copy fail");
	}
	if (!@rename($tmp, $dst)) {
		@unlink($tmp);
		return array(false, 0, "rename fail");
	}
	clearstatcache(true, $dst);
	if (filesize($dst) !== $ss) return array(false, 0, "size mismatch after copy");
	return array(true, $ss, "copied");
}

function rel_of($abs, $roots) {
	foreach ($roots as $r) {
		$r = rtrim($r, "/");
		if ($r === "") continue;
		if (strpos($abs, $r . "/") === 0) return substr($abs, strlen($r) + 1);
	}
	return null;
}

function bytes_fmt($n) {
	foreach (array("B", "KB", "MB", "GB", "TB") as $u) {
		if ($n < 1024) return sprintf("%.1f %s", $n, $u);
		$n /= 1024;
	}
	return sprintf("%.1f PB", $n);
}

// ---- plan / copy / verify 共用的主迴圈 ----------------------------------
function run_transfer($mode, $db, $opt, $state) {
	global $out_root;
	$roots = array_merge(array($opt["src"]), map_roots(), array($out_root, map_fs_root()));
	$st = $state;
	$st["mode"] = $mode;
	$st["last_mid"] = isset($st["last_mid"]) ? $st["last_mid"] : 0;
	if ($opt["from_mid"]) $st["last_mid"] = max($st["last_mid"], $opt["from_mid"]);
	$st["t0"] = time();

	$cnt = array("rows"=>0, "rows_ok"=>0, "rows_no_file"=>0, "rows_bad_rel"=>0,
	             "files"=>0, "bytes"=>0, "already"=>0, "copied"=>0, "failed"=>0);
	$failed = array();

	$done = false;
	while (!$done) {
		$rows = fetch_maps($db, $opt, $st["last_mid"], $opt["chunk"]);
		if (count($rows) === 0) break;
		foreach ($rows as $row) {
			$st["last_mid"] = intval($row["mid"]);
			$cnt["rows"]++;
			if ($opt["limit"] && $cnt["rows"] >= $opt["limit"]) $done = true;

			$rel = map_rel_path($row["filename"]);
			if ($rel === null) { $cnt["rows_bad_rel"]++; }

			$files = row_files($row["filename"]);
			if (count($files) === 0) $cnt["rows_no_file"]++;

			$row_ok = true;
			foreach ($files as $f) {
				$frel = rel_of($f, $roots);
				if ($frel === null) { $row_ok = false; continue; }
				$dst = $opt["dest"] . "/" . $frel;
				$cnt["files"]++;
				list($ok, $bytes, $why) = copy_one($f, $dst, ($mode !== "copy") || $opt["dry_run"]);
				if ($why === "already") { $cnt["already"]++; continue; }
				if (!$ok) {
					$cnt["failed"]++;
					$row_ok = false;
					if (count($failed) < 50) $failed[] = sprintf("mid=%d %s -> %s (%s)", $row["mid"], $f, $dst, $why);
					continue;
				}
				$cnt["bytes"] += $bytes;
				$cnt["copied"]++;
			}

			// copy 模式: 檔案都到位才把 DB 改成相對路徑
			if ($mode === "copy" && $row_ok && $rel !== null && $rel !== $row["filename"] && !$opt["dry_run"]) {
				$sql = sprintf("UPDATE \"map\" SET \"filename\"='%s' WHERE mid=%d AND \"filename\"='%s'",
					pg_escape_string($rel), intval($row["mid"]), pg_escape_string($row["filename"]));
				$db->Execute($sql);
				$cnt["rows_ok"]++;
			} else if ($mode === "copy" && $rel !== null && $rel === $row["filename"]) {
				$cnt["rows_ok"]++;
			}

			if ($done) break;
		}
		if (count($rows) < $opt["chunk"] && $opt["since"] === null) $done = true;
		$elapsed = max(1, time() - $st["t0"]);
		printf("[%s] rows=%d files=%d done=%d new=%s fail=%d last_mid=%d  %.0f rows/s %.1f MB/s\n",
			$mode, $cnt["rows"], $cnt["files"], $cnt["already"] + $cnt["copied"],
			bytes_fmt($cnt["bytes"]), $cnt["failed"], $st["last_mid"],
			$cnt["rows"] / $elapsed, $cnt["bytes"] / 1048576 / $elapsed);
		flush();
		if ($mode === "copy" && !$opt["dry_run"]) state_save($opt["state"], $st);
	}

	if ($mode === "copy" && !$opt["dry_run"]) state_save($opt["state"], $st);
	$elapsed = max(1, time() - $st["t0"]);

	$action = ($mode === "copy") ? "copied" : (($mode === "verify") ? "missing on dest" : "to copy");
	if ($mode === "copy" && $opt["dry_run"]) $action = "would copy";
	printf("\n=== %s%s ===\n", strtoupper($mode), $opt["dry_run"] ? " (dry-run)" : "");
	printf("rows processed        : %d\n", $cnt["rows"]);
	printf("  already relative     : %d\n", $cnt["rows_ok"]);
	printf("  no file at source    : %d\n", $cnt["rows_no_file"]);
	printf("  unknown root (skip)  : %d\n", $cnt["rows_bad_rel"]);
	printf("files seen            : %d\n", $cnt["files"]);
	printf("  present/identical    : %d\n", $cnt["already"]);
	printf("  %-20s : %d (%s)\n", $action, $cnt["copied"], bytes_fmt($cnt["bytes"]));
	printf("  failed               : %d\n", $cnt["failed"]);
	printf("elapsed                : %ds (%.0f rows/s, %.1f MB/s)\n",
		$elapsed, $cnt["rows"] / $elapsed, $cnt["bytes"] / 1048576 / $elapsed);
	if (count($failed)) {
		print "-- failures --\n";
		foreach ($failed as $f) print "  $f\n";
	}
	return ($cnt["failed"] === 0) ? 0 : 2;
}

// ---- track 搬移 ---------------------------------------------------------
function run_tracks($mode, $db, $opt) {
	global $out_root;
	$roots = array_merge(array($opt["src"]), map_roots(), array($out_root, map_fs_root()));
	$after = $opt["from_mid"];
	$rows_scanned = 0;
	$files = 0; $bytes = 0; $fail_cnt = 0;
	$fail_msgs = array();
	while (true) {
		$rows = fetch_tracks($db, $opt, $after, $opt["chunk"]);
		if (count($rows) === 0) break;
		foreach ($rows as $row) {
			$after = intval($row["tid"]);
			$rows_scanned++;
			$rel = map_rel_path(rtrim($row["path"], "/"));
			$srcfiles = track_files_of($row["path"], $row["tid"]);
			$row_ok = true;
			foreach ($srcfiles as $f) {
				$frel = rel_of($f, $roots);
				if ($frel === null) { $row_ok = false; continue; }
				$dst = $opt["dest"] . "/" . $frel;
				$files++;
				list($ok, $b, $why) = copy_one($f, $dst, ($mode !== "copy") || $opt["dry_run"]);
				if ($why === "already") continue;
				if (!$ok) { $fail_cnt++; $row_ok = false;
					if (count($fail_msgs) < 20) $fail_msgs[] = sprintf("tid=%d %s (%s)", $row["tid"], $f, $why);
					continue; }
				$bytes += $b;
			}
			if ($mode === "copy" && $row_ok && $rel !== null && $rel !== rtrim($row["path"], "/") && !$opt["dry_run"]) {
				$db->Execute(sprintf("UPDATE \"track\" SET path='%s' WHERE tid=%d",
					pg_escape_string($rel . "/"), intval($row["tid"])));
			}
		}
		printf("[tracks/%s] rows=%d files=%d (%s) last_tid=%d\n",
			$mode, $rows_scanned, $files, bytes_fmt($bytes), $after);
		flush();
		if (count($rows) < $opt["chunk"]) break;
	}
	printf("track rows=%d files=%d (%s) failed=%d\n", $rows_scanned, $files, bytes_fmt($bytes), $fail_cnt);
	foreach ($fail_msgs as $m) print "  $m\n";
	return ($fail_cnt === 0) ? 0 : 2;
}

// ---- purge --------------------------------------------------------------
function run_purge($db, $opt) {
	global $out_root;
	if ($opt["dry_run"]) print "*** DRY-RUN, 只統計不刪除 ***\n";
	$after = $opt["from_mid"];
	if ($opt["resume"]) {
		$prev = state_load($opt["state"]);
		if (isset($prev["last_mid"])) $after = max($after, intval($prev["last_mid"]));
	}
	$scanned = 0; $purged = 0; $kept = 0; $fail_cnt = 0;
	$roots = array_merge(array($opt["src"]), map_roots(), array($out_root, map_fs_root()));
	$listf = $opt["state"] . ".purge";
	$lf = fopen($listf, "w");
	fwrite($lf, "mid\tuid\tflag\tgpx\tcdate\tddate\tcount\ttitle\tfilename\n");

	while (true) {
		$rows = fetch_maps($db, $opt, $after, $opt["chunk"]);
		if (count($rows) === 0) break;
		foreach ($rows as $row) {
			$after = intval($row["mid"]);
			$scanned++;
			if ($opt["missing"]) {
				$files = row_files($row["filename"]);
				$exists = false;
				foreach ($files as $f) { if (is_file($f)) { $exists = true; break; } }
				if ($exists) { $kept++; continue; }
			}
			$mid = intval($row["mid"]);
			fwrite($lf, sprintf("%d\t%d\t%d\t%d\t%s\t%s\t%s\t%s\t%s\n",
				$row["mid"], $row["uid"], $row["flag"], $row["gpx"],
				$row["cdate"], $row["ddate"] === null ? "" : $row["ddate"],
				$row["count"], str_replace(array("\t", "\n"), " ", (string)$row["title"]),
				$row["filename"]));
			if (!$opt["dry_run"]) {
				$db->StartTrans();
				$db->Execute(sprintf("DELETE FROM gpx_wp  WHERE mid=%d", $mid));
				$db->Execute(sprintf("DELETE FROM gpx_trk WHERE mid=%d", $mid));
				$db->Execute(sprintf("DELETE FROM map_rank WHERE mid=%d", $mid));
				$db->Execute(sprintf("DELETE FROM \"map\" WHERE mid=%d", $mid));
				if ($db->CompleteTrans() === false) { $fail_cnt++; continue; }
				memcached_delete(sprintf("map_get_single_%s", $mid));
			}
			$purged++;
		}
		printf("[purge] scanned=%d purged=%d kept=%d last_mid=%d\n", $scanned, $purged, $kept, $after);
		flush();
		if (!$opt["dry_run"]) state_save($opt["state"], array("mode" => "purge", "last_mid" => $after));
		if (count($rows) < $opt["chunk"]) break;
	}
	fclose($lf);
	printf("\n=== PURGE%s ===\nscanned=%d kept(files exist)=%d purged=%d failed=%d\nlist: %s\n",
		$opt["dry_run"] ? " (dry-run)" : "", $scanned, $kept, $purged, $fail_cnt, $listf);
	return ($fail_cnt === 0) ? 0 : 2;
}

// ---- residue ------------------------------------------------------------
function run_residue($db, $opt) {
	$dest = $opt["dest"];
	if (!is_dir($dest)) { fwrite(STDERR, "dest 不存在: $dest\n"); return 1; }

	// 建立 dir -> prefix 清單 (map 的檔案是 dir/prefix*)
	$ref = array();
	$after = 0;
	while (true) {
		$sql = sprintf("SELECT mid, filename FROM \"map\" WHERE mid > %d ORDER BY mid ASC LIMIT %d", $after, $opt["chunk"]);
		$rows = $db->GetAll($sql);
		if (count($rows) === 0) break;
		foreach ($rows as $r) {
			$after = intval($r["mid"]);
			$rel = map_rel_path($r["filename"]);
			if ($rel === null) continue;
			$dir = dirname($rel);
			$prefix = str_replace(".tag.png", "", basename($rel));
			$ref[$dir][$prefix] = 1;
		}
		if (count($rows) < $opt["chunk"]) break;
	}
	if ($opt["tracks"]) {
		$after = 0;
		while (true) {
			$sql = sprintf("SELECT tid, path FROM \"track\" WHERE tid > %d ORDER BY tid ASC LIMIT %d", $after, $opt["chunk"]);
			$rows = $db->GetAll($sql);
			if (count($rows) === 0) break;
			foreach ($rows as $r) {
				$after = intval($r["tid"]);
				$rel = map_rel_path(rtrim($r["path"], "/"));
				if ($rel === null) continue;
				$ref[$rel . "/" . intval($r["tid"])]["*"] = 1;
			}
			if (count($rows) < $opt["chunk"]) break;
		}
	}

	printf("referenced dirs: %d\n", count($ref));
	$it = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($dest, FilesystemIterator::SKIP_DOTS),
		RecursiveIteratorIterator::LEAVES_ONLY);

	$seen = 0; $res = 0; $bytes = 0;
	$del = 0; $del_fail = 0; $skip_new = 0; $dirs = array();
	$do_del = $opt["delete"] && !$opt["dry_run"];
	$t0 = time();
	$out = fopen($opt["state"] . ".residue", "w");
	foreach ($it as $file) {
		$seen++;
		$rel = substr($file->getPathname(), strlen($dest) + 1);
		$dir = dirname($rel);
		$name = basename($rel);
		$hit = false;
		if (isset($ref[$dir])) {
			foreach (array_keys($ref[$dir]) as $prefix) {
				if ($prefix === "*") { $hit = true; break; }
				if (strpos($name, $prefix) === 0) { $hit = true; break; }
			}
		}
		if ($hit) continue;
		$res++;
		$bytes += $file->getSize();
		fwrite($out, $rel . "\n");
		if ($do_del) {
			// 安全閘: 掃描開始後才出現的檔不動 (可能是剛生成的地圖)
			if ($file->getMTime() >= $t0) { $skip_new++; continue; }
			if (@unlink($file->getPathname())) { $del++; $dirs[$dir] = 1; }
			else { $del_fail++; }
		}
	}
	fclose($out);
	if ($do_del) {
		$anc = array();
		foreach (array_keys($dirs) as $d) {
			$parts = explode("/", $d);
			for ($i = 1; $i <= count($parts); $i++) $anc[implode("/", array_slice($parts, 0, $i))] = 1;
		}
		$cand = array_keys($anc);
		usort($cand, function ($a, $b) { return substr_count($b, "/") - substr_count($a, "/"); });
		$rm = 0;
		foreach ($cand as $p) if (is_dir("$dest/$p") && @rmdir("$dest/$p")) $rm++;
		printf("deleted files=%d fail=%d skipped(too new)=%d rmdir=%d\n", $del, $del_fail, $skip_new, $rm);
	}
	printf("scanned=%d residue=%d (%s)\nlist: %s\n", $seen, $res, bytes_fmt($bytes), $opt["state"] . ".residue");
	return ($del_fail === 0) ? 0 : 2;
}

// ---- check --------------------------------------------------------------
// 一列的檔案家族狀態: (存在的檔, 是否有任一檔, 總位元組, 是否含 gpx/svg)
function row_family_info($filename) {
	$files = row_files($filename);
	$exists = array(); $bytes = 0; $gpx_ext = false; $any = false;
	foreach ($files as $f) {
		if (!is_file($f)) continue;
		$any = true;
		$exists[] = $f;
		$s = @filesize($f);
		if ($s !== false) $bytes += $s;
		if (preg_match('/\.(gpx|GPX|svg)$/', basename($f))) $gpx_ext = true;
	}
	return array($exists, $any, $bytes, $gpx_ext);
}

function run_check($db, $opt) {
	$desc = array(
		"R1.1" => "flag=0 但 filename 檔不存在",
		"R1.2" => "flag=0 但整個檔案家族都不在",
		"R1.3" => "flag=0 卻有 ddate",
		"R1.4" => "flag=0 卻有 edate",
		"R2.1" => "flag=1 卻沒有 edate",
		"R2.2" => "flag=1 且 gpx=0 卻還有殘檔",
		"R2.3" => "flag=1 且 gpx=1 卻沒有 gpx 檔 (INFO 保留)",
		"R3.1" => "flag=2 卻沒有 ddate",
		"R3.2" => "flag=2 檔案卻還在",
	);
	$info = array("R2.3" => true);
	$hits = array_fill_keys(array_keys($desc), 0);
	$scanned = 0; $after = $opt["from_mid"];
	$listf = $opt["state"] . ".check";
	$lf = fopen($listf, "w");
	fwrite($lf, "rule\tmid\tflag\tgpx\tsrc_ok\tfam_ok\tgpx_ext\tbytes\tddate\tedate\ttitle\tfilename\n");
	while (true) {
		$rows = fetch_maps($db, $opt, $after, $opt["chunk"]);
		if (count($rows) === 0) break;
		foreach ($rows as $row) {
			$after = intval($row["mid"]);
			$scanned++;
			$flag = intval($row["flag"]); $gpx = intval($row["gpx"]);
			$src_ok = is_file(map_fs_path($row["filename"]));
			list($fs, $fam_ok, $bytes, $gpx_ext) = row_family_info($row["filename"]);
			$hit = array();
			if ($flag === 0) {
				if (!$src_ok) $hit[] = "R1.1";
				if (!$fam_ok) $hit[] = "R1.2";
				if ($row["ddate"] !== null) $hit[] = "R1.3";
				if ($row["edate"] !== null) $hit[] = "R1.4";
			} elseif ($flag === 1) {
				if ($row["edate"] === null) $hit[] = "R2.1";
				if ($gpx === 0 && $fam_ok) $hit[] = "R2.2";
				if ($gpx === 1 && !$gpx_ext) $hit[] = "R2.3";
			} elseif ($flag === 2) {
				if ($row["ddate"] === null) $hit[] = "R3.1";
				if ($fam_ok) $hit[] = "R3.2";
			}
			if (count($hit) === 0) continue;
			foreach ($hit as $r) {
				$hits[$r]++;
				fwrite($lf, sprintf("%s\t%d\t%d\t%d\t%d\t%d\t%d\t%d\t%s\t%s\t%s\t%s\n",
					$r, $row["mid"], $flag, $gpx, $src_ok ? 1 : 0, $fam_ok ? 1 : 0,
					$gpx_ext ? 1 : 0, $bytes,
					$row["ddate"] === null ? "" : $row["ddate"],
					$row["edate"] === null ? "" : $row["edate"],
					str_replace(array("\t", "\n"), " ", (string)$row["title"]),
					$row["filename"]));
			}
		}
		printf("[check] scanned=%d last_mid=%d\n", $scanned, $after);
		flush();
		if ($opt["limit"] && $scanned >= $opt["limit"]) break;
		if (count($rows) < $opt["chunk"]) break;
	}
	fclose($lf);
	$fail = 0;
	printf("\n=== CHECK ===\nscanned=%d\n", $scanned);
	foreach ($desc as $r => $d) {
		$st = isset($info[$r]) ? "INFO" : (($hits[$r] > 0) ? "FAIL" : "ok");
		if ($st === "FAIL") $fail++;
		printf("  %-6s %-38s %8d  %s\n", $r, $d, $hits[$r], $st);
	}
	printf("list: %s\n", $listf);
	return ($fail > 0) ? 2 : 0;
}

// ---- fix ----------------------------------------------------------------
// 允許刪檔的根目錄 (同 run_purge 的 $roots 概念)
function fix_roots($opt) {
	global $out_root;
	$cand = array($opt["src"], $out_root, map_fs_root());
	foreach (map_roots() as $r) $cand[] = $r;
	$out = array();
	foreach ($cand as $r) {
		if ($r === null || $r === "") continue;
		$r = rtrim($r, "/");
		if ($r !== "" && !in_array($r, $out, true)) $out[] = $r;
	}
	return $out;
}

// 檔案必須真的落在允許的根目錄底下, 否則拒絕刪除
function fix_path_ok($path, $roots) {
	$rp = realpath($path);
	if ($rp === false) return false;
	foreach ($roots as $root) if (strpos($rp, $root . "/") === 0) return true;
	return false;
}

function fix_rel($path) {
	$rel = map_rel_path($path);
	return ($rel === null) ? $path : $rel;
}

// 依 flag/gpx 分頁掃描 map 列, 逐列呼叫 $cb (row)
function fix_each($db, $opt, $flag, $gpx, $cb) {
	$o = $opt;
	$o["flag"] = $flag;
	$o["gpx"] = $gpx;
	$o["from_mid"] = 0;
	$o["resume"] = false;
	$after = 0; $n = 0;
	while (true) {
		$rows = fetch_maps($db, $o, $after, $opt["chunk"]);
		if (count($rows) === 0) break;
		foreach ($rows as $row) {
			$after = intval($row["mid"]);
			$n++;
			$cb($row);
			if ($opt["limit"] && $n >= $opt["limit"]) return $n;
		}
		if (count($rows) < $opt["chunk"]) break;
	}
	return $n;
}

function fix_line($lf, $action, $row, $bytes, $path) {
	fwrite($lf, sprintf("%s\t%d\t%d\t%d\t%d\t%s\n",
		$action, $row["mid"], intval($row["flag"]), intval($row["gpx"]),
		$bytes, str_replace(array("\t", "\n"), " ", (string)$path)));
}

function run_fix($db, $opt) {
	$do = $opt["delete"] && !$opt["dry_run"];
	if (!$do) print "*** DRY-RUN: 只列清單不動任何東西; 加 --delete 才執行 ***\n";
	$roots = fix_roots($opt);
	$st = array(
		"F1" => array("rows" => 0, "done" => 0, "fail" => 0, "bytes" => 0),
		"F2" => array("rows" => 0, "done" => 0, "fail" => 0, "bytes" => 0),
		"F3" => array("rows" => 0, "done" => 0, "fail" => 0, "bytes" => 0),
		"F4" => array("rows" => 0, "done" => 0, "fail" => 0, "bytes" => 0),
	);
	$listf = $opt["state"] . ".fix";
	$lf = fopen($listf, "w");
	fwrite($lf, "action\tmid\tflag\tgpx\tbytes\tpath\n");
	$dirs = array();

	// ---- F1: flag=0 但檔案不在 -> map_del (家族檔 + flag=2 + size=0 + ddate=NOW)
	$f1 = array();
	fix_each($db, $opt, 0, null, function ($row) use (&$f1) {
		if (is_file(map_fs_path($row["filename"]))) return;
		$f1[] = $row;
	});
	foreach ($f1 as $row) {
		$st["F1"]["rows"]++;
		list($fs, $fam_ok, $bytes, $gpx_ext) = row_family_info($row["filename"]);
		$st["F1"]["bytes"] += $bytes;
		fix_line($lf, "map_del", $row, $bytes, $row["filename"]);
		if (!$do) continue;
		$rs = map_del(intval($row["mid"]));   // 會 close 連線
		if ($rs === false) $st["F1"]["fail"]++;
		else { $st["F1"]["done"]++; memcached_delete(sprintf("map_get_single_%s", intval($row["mid"]))); }
	}
	if ($do && count($f1)) $db = get_conn();
	printf("[fix] F1 flag=0 缺檔 -> delete 操作: %d 列 (%s)\n", count($f1), bytes_fmt($st["F1"]["bytes"]));

	// ---- F2: flag=0 殘留 ddate/edate -> NULL
	$f2 = $db->GetAll("SELECT mid, flag, gpx, ddate, edate, filename FROM \"map\" WHERE flag=0 AND (ddate IS NOT NULL OR edate IS NOT NULL) ORDER BY mid");
	if ($opt["limit"]) $f2 = array_slice($f2, 0, $opt["limit"]);
	foreach ($f2 as $row) {
		$st["F2"]["rows"]++;
		$mid = intval($row["mid"]);
		fix_line($lf, "clear_ts", $row, 0, "ddate=" . ($row["ddate"] === null ? "" : $row["ddate"]) . " edate=" . ($row["edate"] === null ? "" : $row["edate"]));
		if (!$do) continue;
		$rs = $db->Execute(sprintf("UPDATE \"map\" SET ddate=NULL, edate=NULL WHERE mid=%d AND flag=0", $mid));
		if ($rs === false) { $st["F2"]["fail"]++; continue; }
		$st["F2"]["done"]++;
		memcached_delete(sprintf("map_get_single_%s", $mid));
	}
	printf("[fix] F2 flag=0 清殘留 ddate/edate: %d 列\n", $st["F2"]["rows"]);

	// ---- F3/F4: 只刪檔案, 列保留
	foreach (array(array("F3", 1, 0), array("F4", 2, null)) as $act) {
		list($tag, $flag, $gpx) = $act;
		fix_each($db, $opt, $flag, $gpx, function ($row) use ($lf, $roots, $do, &$st, &$dirs, $tag) {
			list($fs, $fam_ok, $bytes, $gpx_ext) = row_family_info($row["filename"]);
			if (!$fam_ok) return;
			$st[$tag]["rows"]++;
			$st[$tag]["bytes"] += $bytes;
			foreach ($fs as $f) {
				if (!fix_path_ok($f, $roots)) {
					fix_line($lf, "SKIP_OUTSIDE", $row, @filesize($f), $f);
					$st[$tag]["fail"]++;
					continue;
				}
				fix_line($lf, "unlink", $row, @filesize($f), fix_rel($f));
				if (!$do) { $st[$tag]["done"]++; continue; }
				if (@unlink($f)) {
					$st[$tag]["done"]++;
					$dirs[dirname($f)] = 1;
				} else $st[$tag]["fail"]++;
			}
		});
		printf("[fix] %s 殘檔: %d 列, %d 檔 (%s)\n",
			$tag, $st[$tag]["rows"], $st[$tag]["done"] + $st[$tag]["fail"], bytes_fmt($st[$tag]["bytes"]));
	}
	fclose($lf);

	// 清掉被清空的目錄 (同 run_residue 的做法; rmdir 只會移除空目錄)
	$rm = 0;
	if ($do && count($dirs)) {
		$anc = array();
		foreach (array_keys($dirs) as $d) {
			$anc[$d] = 1;
			foreach ($roots as $root) {
				if (strpos($d, $root . "/") !== 0) continue;
				$p = $root;
				foreach (explode("/", substr($d, strlen($root) + 1)) as $seg) {
					$p .= "/" . $seg;
					$anc[$p] = 1;
				}
			}
		}
		$cand = array_keys($anc);
		usort($cand, function ($a, $b) { return substr_count($b, "/") - substr_count($a, "/"); });
		foreach ($cand as $p) if (is_dir($p) && @rmdir($p)) $rm++;
	}

	$fail = $st["F1"]["fail"] + $st["F2"]["fail"] + $st["F3"]["fail"] + $st["F4"]["fail"];
	printf("\n=== FIX%s ===\n", $do ? "" : " (dry-run)");
	printf("  F1 flag=0 缺檔 -> map_del   : rows=%d done=%d fail=%d bytes=%s\n", $st["F1"]["rows"], $st["F1"]["done"], $st["F1"]["fail"], bytes_fmt($st["F1"]["bytes"]));
	printf("  F2 flag=0 清 ddate/edate    : rows=%d done=%d fail=%d\n", $st["F2"]["rows"], $st["F2"]["done"], $st["F2"]["fail"]);
	printf("  F3 flag=1 gpx=0 清殘檔      : rows=%d files=%d fail=%d bytes=%s\n", $st["F3"]["rows"], $st["F3"]["done"], $st["F3"]["fail"], bytes_fmt($st["F3"]["bytes"]));
	printf("  F4 flag=2 清殘檔             : rows=%d files=%d fail=%d bytes=%s\n", $st["F4"]["rows"], $st["F4"]["done"], $st["F4"]["fail"], bytes_fmt($st["F4"]["bytes"]));
	if ($do) printf("  rmdir=%d\n", $rm);
	printf("list: %s\n", $listf);
	return ($fail === 0) ? 0 : 2;
}

// ---- main ---------------------------------------------------------------
$state = $opt["resume"] ? state_load($opt["state"]) : array("mode" => $mode);
$db = get_conn();
$rc = 0;

switch ($mode) {
	case "plan":
	case "copy":
	case "verify":
		$rc = run_transfer($mode, $db, $opt, $state);
		if ($opt["tracks"]) $rc |= run_tracks($mode, $db, $opt);
		break;
	case "purge":
		$rc = run_purge($db, $opt);
		break;
	case "residue":
		$rc = run_residue($db, $opt);
		break;
	case "check":
		$rc = run_check($db, $opt);
		break;
	case "fix":
		$rc = run_fix($db, $opt);
		break;
}
exit($rc);
