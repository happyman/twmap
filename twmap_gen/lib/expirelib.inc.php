<?php
// expire 套件 (根目錄 expire.php) 的可測試元件
//
// 這裡只放「可以脫離 DB 跑」的元件:
//   - lock / state 檔 (flock 非阻塞, JSON 狀態)
//   - 檔案家族掃描 (map_files + 兜底)
//   - 正確性規則判定 (R1.1~R4.1, 與 api/migrate.php check 同一套)
//   - expire 候選排序與預算計算
//   - 清空目錄
// 副作用只限參數給的路徑; 需要 DB 的掃描放下面的 expire_fetch_maps。
//
// 測試: tests/ExpireTest.php

if (!defined("TWMAP_GIB")) define("TWMAP_GIB", 1073741824);

function expire_bytes_fmt($n) {
	$n = floatval($n);
	$names = array("B", "KB", "MB", "GB", "TB");
	$i = 0;
	while ($n >= 1024 && $i < count($names) - 1) {
		$n /= 1024;
		$i++;
	}
	return sprintf($i === 0 ? "%.0f %s" : "%.2f %s", $n, $names[$i]);
}

// PG timestamp 可能帶小數秒 ("...23.517747"), strtotime 不一定吃
function expire_ts($cdate) {
	if ($cdate === null || $cdate === "") return 0;
	if (is_numeric($cdate)) return intval($cdate);
	$ts = strtotime(preg_replace('/\.\d+$/', "", (string)$cdate));
	return ($ts === false) ? 0 : $ts;
}

// ---- lock ---------------------------------------------------------------
// 非阻塞取鎖; 取不到代表前一次還沒跑完。crash 時 kernel 會自動放鎖, 不會永久卡住。
function expire_lock_holder($fp) {
	if ($fp === null || !is_resource($fp)) return null;
	rewind($fp);
	$raw = stream_get_contents($fp);
	if ($raw === false || trim($raw) === "") return null;
	$j = json_decode($raw, true);
	return is_array($j) ? $j : array("raw" => trim($raw));
}

function expire_lock_acquire($lockfile) {
	// "c+" = 讀寫開啟但不截斷; 鎖要在 ftruncate 之前拿到才是 atomic
	// (純 "c" 是只寫, 讀 holder 資訊會 EBADF)
	$fp = @fopen($lockfile, "c+");
	if ($fp === false)
		return array("ok" => false, "fp" => null, "holder" => null, "why" => "open fail: $lockfile");
	if (!flock($fp, LOCK_EX | LOCK_NB)) {
		$holder = expire_lock_holder($fp);
		fclose($fp);
		return array("ok" => false, "fp" => null, "holder" => $holder, "why" => "locked");
	}
	ftruncate($fp, 0);
	rewind($fp);
	fwrite($fp, json_encode(array(
		"pid" => getmypid(),
		"started" => date("c"),
		"host" => gethostname(),
	)));
	fflush($fp);
	return array("ok" => true, "fp" => $fp, "holder" => null, "why" => "ok");
}

function expire_lock_release($lock) {
	$fp = is_array($lock) ? ($lock["fp"] ?? null) : $lock;
	if ($fp === null || !is_resource($fp)) return false;
	flock($fp, LOCK_UN);
	fclose($fp);
	return true;
}

// ---- state --------------------------------------------------------------
function expire_state_load($f) {
	if ($f === null || $f === "" || !is_file($f)) return array();
	$raw = @file_get_contents($f);
	if ($raw === false) return array();
	$j = json_decode($raw, true);
	return is_array($j) ? $j : array();
}

function expire_state_save($f, $st) {
	$r = @file_put_contents($f, json_encode($st, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
	return $r !== false;
}

// pid 還在嗎 (拿不準就當活著, 安全側)
function expire_pid_alive($pid) {
	$pid = intval($pid);
	if ($pid <= 0) return false;
	if (!function_exists("posix_kill")) return true;
	return @posix_kill($pid, 0);
}

// ---- 檔案家族 -----------------------------------------------------------
// map_files 只認 \d+x\d+-\d+x\d+ 開頭的檔名; 兜底用 tag.png 本身 (同 migrate.php)
function expire_row_files($filename) {
	$files = map_files($filename);
	if ($files === false || !is_array($files) || count($files) === 0) {
		$src = map_fs_path($filename);
		if (is_file($src)) $files = array($src);
	}
	return (is_array($files)) ? $files : array();
}

/**
 * 一列的檔案家族狀態
 *   exists       實際存在的絕對路徑
 *   any          有任一檔在
 *   bytes        存在檔案總位元組
 *   bytes_nongpx 會被 map_expire() 真正刪掉的位元組 (map_expire 保留含 ".gpx" 的檔)
 *   gpx_ext      家族裡有 gpx/svg (R2.3 判定用, 與 migrate check 一致)
 */
function expire_row_family($filename) {
	$out = array(
		"files" => array(), "exists" => array(), "any" => false,
		"bytes" => 0, "bytes_nongpx" => 0, "gpx_ext" => false,
	);
	foreach (expire_row_files($filename) as $f) {
		$out["files"][] = $f;
		if (!is_file($f)) continue;
		$base = basename($f);
		$s = @filesize($f);
		if ($s === false) $s = 0;
		$out["any"] = true;
		$out["exists"][] = $f;
		$out["bytes"] += $s;
		// map_expire() 的保留規則: strstr(basename, '.gpx') -> 繼續留著
		if (strpos($base, ".gpx") === false) $out["bytes_nongpx"] += $s;
		if (preg_match('/\.(gpx|GPX|svg)$/', $base)) $out["gpx_ext"] = true;
	}
	return $out;
}

// ---- 正確性規則 (與 api/migrate.php check 同一套) ------------------------
function expire_rule_desc($with_size_rule = false) {
	$desc = array(
		"R1.1" => "flag=0 但 filename 檔不存在",
		"R1.2" => "flag=0 但整個檔案家族都不在",
		"R1.3" => "flag=0 卻有 ddate",
		"R1.4" => "flag=0 卻有 edate",
		"R2.1" => "flag=1 卻沒有 edate",
		"R2.2" => "flag=1 且 gpx=0 卻還有殘檔",
		"R2.3" => "flag=1 且 gpx=1 卻沒有 gpx 檔",
		"R3.1" => "flag=2 卻沒有 ddate",
		"R3.2" => "flag=2 檔案卻還在",
	);
	if ($with_size_rule) $desc["R4.1"] = "flag=0 但 DB size 與實際檔案差 >64MB";
	return $desc;
}

// 這些規則只是提示, 不算 FAIL
function expire_rule_is_info($rule) {
	return in_array($rule, array("R2.3", "R4.1"), true);
}

// $row: flag/gpx/ddate/edate/size 需存在; $family: expire_row_family()
// $check_size=false 時不會產生 R4.1 (migrate check 沒撈 size 欄位)
function expire_eval_rules($row, $src_ok, $family, $check_size = false) {
	$hit = array();
	$flag = intval($row["flag"]);
	$gpx = intval($row["gpx"]);
	$any = !empty($family["any"]);
	if ($flag === 0) {
		if (!$src_ok) $hit[] = "R1.1";
		if (!$any) $hit[] = "R1.2";
		if (isset($row["ddate"]) && $row["ddate"] !== null) $hit[] = "R1.3";
		if (isset($row["edate"]) && $row["edate"] !== null) $hit[] = "R1.4";
		if ($check_size && isset($row["size"]) &&
			abs(intval($family["bytes"]) - intval($row["size"])) > 67108864) $hit[] = "R4.1";
	} elseif ($flag === 1) {
		if (!isset($row["edate"]) || $row["edate"] === null) $hit[] = "R2.1";
		if ($gpx === 0 && $any) $hit[] = "R2.2";
		if ($gpx === 1 && empty($family["gpx_ext"])) $hit[] = "R2.3";
	} elseif ($flag === 2) {
		if (!isset($row["ddate"]) || $row["ddate"] === null) $hit[] = "R3.1";
		if ($any) $hit[] = "R3.2";
	}
	return $hit;
}

// ---- DB 分頁 (mid 游標) -------------------------------------------------
function expire_fetch_maps($db, $opt, $after_mid, $chunk, $flag = null, $gpx = null) {
	$w = array("mid > " . intval($after_mid));
	if ($flag !== null) $w[] = "flag = " . intval($flag);
	if ($gpx !== null) $w[] = "gpx = " . intval($gpx);
	if (!empty($opt["uid"])) $w[] = "uid = " . intval($opt["uid"]);
	if (!empty($opt["mid"])) $w[] = "mid = " . intval($opt["mid"]);
	$sql = sprintf('SELECT mid, uid, filename, flag, gpx, cdate, ddate, edate, "count", size, title'
		. ' FROM "map" WHERE %s ORDER BY mid ASC LIMIT %d',
		implode(" AND ", $w), max(1, intval($chunk)));
	$rs = $db->GetAll($sql);
	return (is_array($rs)) ? $rs : array();
}

// flag=0 全掃, 逐列算家族 bytes; 回傳每列的中繼資料 + 總量 (accounting / 候選用)
function expire_active_scan($db, $opt) {
	$chunk = max(1, intval($opt["chunk"]));
	$limit = intval($opt["limit"]);
	$after = intval($opt["from_mid"]);   // fetch 為 mid > after (同 migrate.php)
	$rows = array();
	$totals = array("rows" => 0, "files" => 0, "bytes" => 0, "bytes_nongpx" => 0, "no_family" => 0);
	while (true) {
		$batch = expire_fetch_maps($db, $opt, $after, $chunk, 0);
		if (count($batch) === 0) break;
		foreach ($batch as $row) {
			$mid = intval($row["mid"]);
			$after = $mid;
			$fam = expire_row_family($row["filename"]);
			$rows[$mid] = array(
				"mid" => $mid,
				"uid" => intval($row["uid"]),
				"filename" => $row["filename"],
				"count" => intval($row["count"]),
				"cdate" => $row["cdate"],
				"cdate_ts" => expire_ts($row["cdate"]),
				"size" => intval($row["size"]),
				"title" => $row["title"],
				"family" => $fam,
			);
			$totals["rows"]++;
			$totals["files"] += count($fam["exists"]);
			$totals["bytes"] += $fam["bytes"];
			$totals["bytes_nongpx"] += $fam["bytes_nongpx"];
			if (!$fam["any"]) $totals["no_family"]++;
			if ($limit && $totals["rows"] >= $limit) return array("rows" => $rows, "totals" => $totals, "last_mid" => $after);
		}
		if (count($batch) < $chunk) break;
	}
	return array("rows" => $rows, "totals" => $totals, "last_mid" => $after);
}

// ---- expire 候選 / 預算 -------------------------------------------------
// $rows 來自 expire_active_scan()["rows"]; 回傳依 cdate 最舊在前的 [mid => bytes_nongpx]
function expire_candidates($rows, $now, $opts) {
	$min_days = isset($opts["min_days"]) ? intval($opts["min_days"]) : 0;
	$protect_count = isset($opts["protect_count"]) ? intval($opts["protect_count"]) : 0;
	$protect = isset($opts["protect_uids"]) ? $opts["protect_uids"] : array();
	$out = array();
	foreach ($rows as $mid => $r) {
		$bytes = isset($r["family"]["bytes_nongpx"]) ? intval($r["family"]["bytes_nongpx"]) : 0;
		if ($bytes <= 0) continue;                       // 沒東西可刪, 不必動 DB
		if ($min_days > 0 && intval($r["cdate_ts"]) > $now - $min_days * 86400) continue;
		if ($protect_count > 0 && intval($r["count"]) >= $protect_count) continue;
		if (count($protect) && isset($protect[intval($r["uid"])])) continue;
		$out[intval($mid)] = $bytes;
	}
	$keys = array_keys($out);
	usort($keys, function ($a, $b) use ($rows) {
		$da = intval($rows[$a]["cdate_ts"]); $db = intval($rows[$b]["cdate_ts"]);
		if ($da !== $db) return $da - $db;
		return $a - $b;
	});
	$sorted = array();
	foreach ($keys as $k) $sorted[$k] = $out[$k];
	return $sorted;
}

// 由最舊開始挑到降到預算內; 回傳 mids / 預計釋放 / 剩餘量
function expire_plan($total_bytes, $cap_bytes, $candidates, $max_expire = 0) {
	$todo = array();
	$freed = 0;
	$rem = intval($total_bytes);
	foreach ($candidates as $mid => $bytes) {
		if ($rem <= $cap_bytes) break;
		if ($max_expire > 0 && count($todo) >= $max_expire) break;
		$todo[] = intval($mid);
		$freed += intval($bytes);
		$rem -= intval($bytes);
	}
	return array(
		"mids" => $todo,
		"freed" => $freed,
		"remaining" => $rem,
		"ok" => ($rem <= $cap_bytes),
	);
}

// "3" / "3,67441" / "" -> array(3 => true, ...)
function expire_parse_uids($s) {
	$out = array();
	if ($s === null) return $out;
	foreach (explode(",", (string)$s) as $p) {
		$p = trim($p);
		if ($p === "") continue;
		$out[intval($p)] = true;
	}
	return $out;
}

// ---- 目錄清理 -----------------------------------------------------------
// 允許刪檔/清目錄的根目錄 (fs_root + 歷史 out_roots)
function expire_roots() {
	$cand = array();
	foreach (array("fs_root", "out_root") as $k) {
		if (isset($GLOBALS[$k])) $cand[] = $GLOBALS[$k];
	}
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
function expire_path_ok($path, $roots = null) {
	if ($roots === null) $roots = expire_roots();
	$rp = realpath($path);
	if ($rp === false) return false;
	foreach ($roots as $root) {
		$root = rtrim($root, "/");
		if ($root !== "" && strpos($rp, $root . "/") === 0) return true;
	}
	return false;
}

// 圖檔刪完後把空目錄清掉 (由最底往上, 只刪空的, 不會刪到 root 本身)
function expire_cleanup_dirs($filename) {
	$rel = map_rel_path($filename);
	if ($rel === null || $rel === "") return 0;
	if (strpos($rel, "..") !== false) return 0;
	$rel = dirname($rel);
	if ($rel === "." || $rel === "/" || $rel === "") return 0;
	$root = rtrim(map_fs_root(), "/");
	if ($root === "") return 0;
	$prefix = $root . "/";
	$dir = $root . "/" . $rel;
	$removed = 0;
	while (strpos($dir, $prefix) === 0) {      // 不會刪到 root 本身
		if (!is_dir($dir)) break;
		$left = @scandir($dir);
		if ($left === false) break;
		if (count(array_diff($left, array(".", ".."))) > 0) break;
		if (!@rmdir($dir)) break;
		$removed++;
		$parent = dirname($dir);
		if ($parent === $dir) break;
		$dir = $parent;
	}
	return $removed;
}
