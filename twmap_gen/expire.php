<?php
/**
 * 每日地圖空間管理 + 檔案正確性檢查 (取代舊的 "-t days" 版本)
 *
 * 用法:
 *   php expire.php [options]        檢查 + 依預算 expire 最舊的圖 (預設 dry-run, 只報表)
 *   php expire.php --apply [options] 真的修復違規、真的刪檔 expire
 *
 * 選項:
 *   --limit-gb=N       map files 磁碟預算, GB (預設 150; 0 = 不做空間管理)
 *   --min-days=N       保護 cdate 在 N 天內的新圖 (預設 30)
 *   --protect-count=N  保護 count >= N 的熱門圖 (預設 100)
 *   --protect-uid=LIST 保護的 uid, 逗號分隔 (預設 3; 空字串 = 不保護)
 *   --max-expire=N     單次最多 expire N 列 (預設 0 = 不限)
 *   --apply            真的動檔案/DB (不加 = 全部 dry-run)
 *   --dry-run          明確指定 dry-run (與不加 --apply 相同, 給習慣 migrate 的人)
 *   --no-check         跳過正確性檢查
 *   --no-fix           跳過修復階段
 *   --no-expire        跳過空間管理階段
 *   --chunk=N          DB 分頁大小 (預設 1000)
 *   --state=FILE       狀態檔 JSON (預設 /tmp/twmap_expire.state.json)
 *   --lock=FILE        鎖檔 (預設 /tmp/twmap_expire.lock)
 *   --uid= --mid= --from-mid= --limit=N   列篩選 (測試用)
 *   -h, --help         列印本說明
 *
 * 階段 (依序, 前一個不做就不跑):
 *   1. flock 非阻塞取鎖: 取不到 = 上一次還沒跑完 -> 印出持有者 pid/開始時間, exit 0 (本次不跑)
 *      取得到但狀態檔寫著 running 而 pid 已死 = 上次 crash, 警告後接手 (flock crash 會自動釋放)
 *   2. check  : R1.1~R4.1 檔案與 DB 是否一致        -> <state>.check
 *   3. fix    : F1~F4 修復 (--apply 才真的動)        -> <state>.fix
 *   4. usage  : 掃 flag=0 檔案家族的實際 bytes (不看 DB size, DB 會低估 ~20%)
 *   5. expire : 超過 --limit-gb 就由 cdate 最舊開始 expire -> <state>.expire
 *      (map_expire(): flag=1 + edate + size=0, 保留 .gpx, 有 keepon_id 會通知 keepon)
 *   6. 寫狀態檔 status=done, 列印摘要
 *
 * fix 動作 (每列明細寫到 <state>.fix; 未加 --apply 只報表不動手):
 *   F1  flag=0 且 filename 檔不存在  -> map_del($mid): 刪整個家族檔 + flag=2 + size=0 + ddate=NOW()
 *                                       (先失效 memcached, 再清空空目錄)
 *   F2  flag=0 卻殘留 ddate/edate    -> UPDATE 設成 NULL (含 memcached 失效)
 *   F3  flag=1 且 gpx=0 還有殘檔     -> 只 unlink 圖檔, 列不動 (仍 flag=1 + edate); 清空空目錄
 *   F4  flag=2 卻還有檔案            -> 只 unlink 檔案, 列不動 (仍 flag=2 + ddate); 清空空目錄
 *   路徑不在允許根目錄 (fs_root + 歷史 out_roots) 底下的檔一律略過, 不刪
 *
 * cron (每天 03:30):
 *   30 3 * * * /usr/bin/php /home/happyman/projects/dev/twmap_gen/expire.php --limit-gb=150 --min-days=30 --apply >> /home/happyman/twmapcache/tmp/expire.log 2>&1
 *
 * exit code: 0 = ok / 無事可做 / 上一次還沒跑完; 2 = check 有 FAIL; 3 = fix/expire 有失敗
 */

require_once(__DIR__ . "/config.inc.php");
require_once(__DIR__ . "/lib/expirelib.inc.php");

function usage_exit($msg = "", $rc = 1) {
	if ($msg !== "") fwrite(STDERR, "ERROR: $msg\n\n");
	foreach (file(__FILE__) as $l) {
		fwrite(STDERR, rtrim($l, "\n") . "\n");
		if (strpos($l, "*/") !== false) break;
	}
	exit($rc);
}

// ---- 參數解析 -----------------------------------------------------------
$args = array_slice($_SERVER["argv"], 1);
$opt = array(
	"limit_gb"      => 150.0,
	"min_days"      => 30,
	"protect_count" => 100,
	"protect_uid"   => "3",
	"max_expire"    => 0,
	"apply"         => false,
	"no_check"      => false,
	"no_fix"        => false,
	"no_expire"     => false,
	"chunk"         => 1000,
	"state"         => "/tmp/twmap_expire.state.json",
	"lock"          => "/tmp/twmap_expire.lock",
	"uid"           => 0,
	"mid"           => 0,
	"from_mid"      => 0,
	"limit"         => 0,
);
foreach ($args as $a) {
	if ($a === "-h" || $a === "--help") usage_exit("", 0);   // 列印說明, exit 0
	if (substr($a, 0, 2) !== "--") usage_exit("無法解析參數: $a");
	$kv = substr($a, 2);
	$eq = strpos($kv, "=");
	if ($eq === false) { $name = $kv; $val = true; }
	else { $name = substr($kv, 0, $eq); $val = substr($kv, $eq + 1); }
	switch ($name) {
		case "state": case "lock": case "protect-uid":
			$opt[str_replace("-", "_", $name)] = (string)$val; break;
		case "limit-gb": $opt["limit_gb"] = floatval($val); break;
		case "min-days": case "protect-count": case "max-expire": case "chunk":
		case "uid": case "mid": case "from-mid": case "limit":
			$opt[str_replace("-", "_", $name)] = intval($val); break;
		case "apply": case "dry-run": case "no-check": case "no-fix": case "no-expire":
			$opt[str_replace("-", "_", $name)] = true;
			if ($name === "dry-run") $opt["apply"] = false;
			break;
		default: usage_exit("未知選項: --$name");
	}
}
if ($opt["state"] === "" || $opt["state"] === null) $opt["state"] = "/tmp/twmap_expire.state.json";
if ($opt["lock"] === "" || $opt["lock"] === null) $opt["lock"] = "/tmp/twmap_expire.lock";
$do = $opt["apply"];

// ---- 鎖: 上一次沒跑完就不跑 --------------------------------------------
$prev = expire_state_load($opt["state"]);
$lock = expire_lock_acquire($opt["lock"]);
if (!$lock["ok"]) {
	fwrite(STDOUT, "SKIP: 上一次 expire 還沒跑完, 本次不執行 (" . date("Y-m-d H:i:s") . ")\n");
	fwrite(STDOUT, "      lock: " . $opt["lock"] . "\n");
	$h = $lock["holder"];
	if (is_array($h)) {
		$pid = isset($h["pid"]) ? $h["pid"] : "?";
		$started = isset($h["started"]) ? $h["started"] : "?";
		$alive = expire_pid_alive($pid) ? "運行中" : "已不存在(鎖未釋放)";
		fwrite(STDOUT, "      holder: pid=$pid started=$started ($alive)\n");
	} else if (isset($prev["status"]) && $prev["status"] === "running") {
		fwrite(STDOUT, "      last run: pid=" . (isset($prev["pid"]) ? $prev["pid"] : "?")
			. " started=" . (isset($prev["started"]) ? $prev["started"] : "?") . " 未寫完\n");
	}
	exit(0);
}

// 取到鎖: 狀態檔還寫著 running 但 pid 已死 = 上次中斷
if (isset($prev["status"]) && $prev["status"] === "running") {
	$pid = isset($prev["pid"]) ? $prev["pid"] : 0;
	if (!expire_pid_alive($pid))
		fwrite(STDERR, "WARN: 上次 run 沒寫完就中斷 (pid=$pid 已不存在), 本次接手\n");
}

$st = array(
	"status"  => "running",
	"pid"     => getmypid(),
	"host"    => gethostname(),
	"started" => date("c"),
	"argv"    => implode(" ", $_SERVER["argv"]),
	"lock"    => $opt["lock"],
	"opts"    => array(
		"limit_gb" => $opt["limit_gb"], "min_days" => $opt["min_days"],
		"protect_count" => $opt["protect_count"], "protect_uid" => $opt["protect_uid"],
		"max_expire" => $opt["max_expire"], "apply" => $do,
	),
);
expire_state_save($opt["state"], $st);
register_shutdown_function(function () use (&$st, $opt, $lock) {
	if (isset($st["finished"])) return;          // 正常收尾時已寫過
	$st["status"] = "aborted";
	$st["finished"] = date("c");
	expire_state_save($opt["state"], $st);
	expire_lock_release($lock);
});

echo ($do) ? "Do " : "Test (without --apply) ";
echo "expire start: " . date("Y-m-d H:i:s") . "\n";
printf("opts: limit-gb=%s min-days=%d protect-count=%d protect-uid='%s' max-expire=%d\n",
	expire_bytes_fmt($opt["limit_gb"] * TWMAP_GIB), $opt["min_days"],
	$opt["protect_count"], $opt["protect_uid"], $opt["max_expire"]);

// ---- phase 1: check -----------------------------------------------------
function expire_run_check($db, $opt, $listf) {
	$desc = expire_rule_desc(true);
	$hits = array_fill_keys(array_keys($desc), 0);
	$chunk = max(1, intval($opt["chunk"]));
	$limit = intval($opt["limit"]);
	$after = intval($opt["from_mid"]);
	$scanned = 0; $batches = 0;
	$t0 = time();
	$lf = @fopen($listf, "w");
	if ($lf === false) { fwrite(STDERR, "ERROR: 無法寫 $listf\n"); exit(1); }
	fwrite($lf, "rule\tmid\tflag\tgpx\tsrc_ok\tfam_ok\tgpx_ext\tbytes\tsize\tddate\tedate\ttitle\tfilename\n");
	while (true) {
		$batch = expire_fetch_maps($db, $opt, $after, $chunk, null, null);
		if (count($batch) === 0) break;
		foreach ($batch as $row) {
			$mid = intval($row["mid"]);
			$after = $mid;
			$scanned++;
			$fam = expire_row_family($row["filename"]);
			$src_ok = is_file(map_fs_path($row["filename"]));
			foreach (expire_eval_rules($row, $src_ok, $fam, true) as $r) {
				$hits[$r]++;
				fwrite($lf, sprintf("%s\t%d\t%d\t%d\t%d\t%d\t%d\t%d\t%d\t%s\t%s\t%s\t%s\n",
					$r, $mid, intval($row["flag"]), intval($row["gpx"]),
					$src_ok ? 1 : 0, $fam["any"] ? 1 : 0, $fam["gpx_ext"] ? 1 : 0,
					$fam["bytes"], intval($row["size"]),
					$row["ddate"] === null ? "" : $row["ddate"],
					$row["edate"] === null ? "" : $row["edate"],
					str_replace(array("\t", "\n"), " ", (string)$row["title"]),
					$row["filename"]));
			}
		}
		$batches++;
		if ($batches % 20 === 0) printf("[check] scanned=%d last_mid=%d\n", $scanned, $after);
		flush();
		if ($limit && $scanned >= $limit) break;
		if (count($batch) < $chunk) break;
	}
	fclose($lf);

	$fail = 0;
	printf("\n=== CHECK ===\nscanned=%d elapsed=%ds\n", $scanned, max(1, time() - $t0));
	printf("  %-6s %8s  %-5s %s\n", "rule", "hits", "state", "desc");
	foreach ($desc as $r => $d) {
		$st = expire_rule_is_info($r) ? "INFO" : (($hits[$r] > 0) ? "FAIL" : "ok");
		if ($st === "FAIL") $fail++;
		printf("  %-6s %8d  %-5s %s\n", $r, $hits[$r], $st, $d);
	}
	printf("list: %s\n", $listf);
	return array("hits" => $hits, "scanned" => $scanned, "fail" => $fail, "listf" => $listf);
}

// ---- phase 2: fix (F1~F4, 與 api/migrate.php fix 同邏輯) ----------------
function expire_fix_line($lf, $action, $row, $bytes, $path) {
	fwrite($lf, sprintf("%s\t%d\t%d\t%d\t%d\t%s\n",
		$action, intval($row["mid"]), intval($row["flag"]), intval($row["gpx"]),
		intval($bytes), str_replace(array("\t", "\n"), " ", (string)$path)));
}

function expire_run_fix($db, $opt, $do, $listf) {
	$roots = expire_roots();
	$chunk = max(1, intval($opt["chunk"]));
	$st = array(
		"F1" => array("rows" => 0, "done" => 0, "fail" => 0, "bytes" => 0),
		"F2" => array("rows" => 0, "done" => 0, "fail" => 0, "bytes" => 0),
		"F3" => array("rows" => 0, "done" => 0, "fail" => 0, "bytes" => 0),
		"F4" => array("rows" => 0, "done" => 0, "fail" => 0, "bytes" => 0),
	);
	$lf = @fopen($listf, "w");
	if ($lf === false) { fwrite(STDERR, "ERROR: 無法寫 $listf\n"); exit(1); }
	fwrite($lf, "action\tmid\tflag\tgpx\tbytes\tpath\n");
	$t0 = time();

	// 依 flag/gpx 分頁掃描, 逐列呼叫 $cb (不留在記憶體: 199k 列也照樣流過去)
	$each = function ($flag, $gpx, $cb) use ($db, $opt, $chunk) {
		$after = intval($opt["from_mid"]);
		$n = 0;
		$limit = intval($opt["limit"]);
		while (true) {
			$batch = expire_fetch_maps($db, $opt, $after, $chunk, $flag, $gpx);
			if (count($batch) === 0) break;
			foreach ($batch as $row) {
				$after = intval($row["mid"]);
				$n++;
				$cb($row);
				if ($limit && $n >= $limit) return;
			}
			if (count($batch) < $chunk) break;
		}
	};

	// F1: flag=0 但 filename 檔不存在 -> map_del (家族檔 + flag=2 + size=0 + ddate=NOW)
	$each(0, null, function ($row) use ($lf, $do, $roots, &$st) {
		if (is_file(map_fs_path($row["filename"]))) return;
		$fam = expire_row_family($row["filename"]);
		$st["F1"]["rows"]++;
		$st["F1"]["bytes"] += $fam["bytes"];
		expire_fix_line($lf, "map_del", $row, $fam["bytes"], $row["filename"]);
		if (!$do) return;
		// map_del() -> map_get_single() 在 memcached 命中時不會 close 連線, 先失效走 DB 路徑
		memcached_delete(sprintf("map_get_single_%s", intval($row["mid"])));
		$rs = map_del(intval($row["mid"]));      // 內部自開自關
		if ($rs === false) { $st["F1"]["fail"]++; return; }
		$st["F1"]["done"]++;
		memcached_delete(sprintf("map_get_single_%s", intval($row["mid"])));
		expire_cleanup_dirs($row["filename"]);
	});

	// F2: flag=0 殘留 ddate/edate -> NULL
	$w = array("flag = 0", "(ddate IS NOT NULL OR edate IS NOT NULL)");
	if (!empty($opt["uid"])) $w[] = "uid = " . intval($opt["uid"]);
	if (!empty($opt["mid"])) $w[] = "mid = " . intval($opt["mid"]);
	if (!empty($opt["from_mid"])) $w[] = "mid > " . intval($opt["from_mid"]);
	$f2 = $db->GetAll(sprintf('SELECT mid, uid, filename, flag, gpx, ddate, edate, size, title FROM "map" WHERE %s ORDER BY mid ASC',
		implode(" AND ", $w)));
	if (!is_array($f2)) $f2 = array();
	if ($opt["limit"]) $f2 = array_slice($f2, 0, $opt["limit"]);
	foreach ($f2 as $row) {
		$st["F2"]["rows"]++;
		expire_fix_line($lf, "clear_ts", $row, 0,
			"ddate=" . ($row["ddate"] === null ? "" : $row["ddate"]) .
			" edate=" . ($row["edate"] === null ? "" : $row["edate"]));
		if (!$do) continue;
		$rs = $db->Execute(sprintf('UPDATE "map" SET ddate=NULL, edate=NULL WHERE mid=%d AND flag=0', intval($row["mid"])));
		if ($rs === false) { $st["F2"]["fail"]++; continue; }
		$st["F2"]["done"]++;
		memcached_delete(sprintf("map_get_single_%s", intval($row["mid"])));
	}

	// F3/F4: 只刪檔案, 列保留
	foreach (array(array("F3", 1, 0), array("F4", 2, null)) as $act) {
		list($tag, $flag, $gpx) = $act;
		$each($flag, $gpx, function ($row) use ($lf, $do, $roots, &$st, $tag) {
			$fam = expire_row_family($row["filename"]);
			if (!$fam["any"]) return;
			$st[$tag]["rows"]++;
			$st[$tag]["bytes"] += $fam["bytes"];
			$left = 0;
			foreach ($fam["exists"] as $f) {
				if (!expire_path_ok($f, $roots)) {
					expire_fix_line($lf, "SKIP_OUTSIDE", $row, @filesize($f), $f);
					$st[$tag]["fail"]++;
					continue;
				}
				expire_fix_line($lf, "unlink", $row, @filesize($f), $f);
				if (!$do) { $left++; continue; }
				if (@unlink($f)) $st[$tag]["done"]++;
				else { $st[$tag]["fail"]++; $left++; }
			}
			if ($do && $left === 0) expire_cleanup_dirs($row["filename"]);
		});
	}
	fclose($lf);

	$fail = $st["F1"]["fail"] + $st["F2"]["fail"] + $st["F3"]["fail"] + $st["F4"]["fail"];
	printf("\n=== FIX%s ===  elapsed=%ds\n", $do ? "" : " (dry-run)", max(1, time() - $t0));
	printf("  F1 flag=0 缺檔 -> map_del  : rows=%d done=%d fail=%d bytes=%s\n", $st["F1"]["rows"], $st["F1"]["done"], $st["F1"]["fail"], expire_bytes_fmt($st["F1"]["bytes"]));
	printf("  F2 flag=0 清 ddate/edate   : rows=%d done=%d fail=%d\n", $st["F2"]["rows"], $st["F2"]["done"], $st["F2"]["fail"]);
	printf("  F3 flag=1 gpx=0 清殘檔     : rows=%d files=%d fail=%d bytes=%s\n", $st["F3"]["rows"], $st["F3"]["done"], $st["F3"]["fail"], expire_bytes_fmt($st["F3"]["bytes"]));
	printf("  F4 flag=2 清殘檔            : rows=%d files=%d fail=%d bytes=%s\n", $st["F4"]["rows"], $st["F4"]["done"], $st["F4"]["fail"], expire_bytes_fmt($st["F4"]["bytes"]));
	printf("list: %s\n", $listf);
	return array("stats" => $st, "fail" => $fail, "listf" => $listf);
}

// ---- phase 3+4: usage + expire ------------------------------------------
function expire_run_expire($db, $opt, $act, $do, $listf) {
	$cap_gb = floatval($opt["limit_gb"]);
	$cap = ($cap_gb > 0) ? (int)round($cap_gb * TWMAP_GIB) : 0;
	$t = $act["totals"];
	$total = intval($t["bytes"]);
	$out = array(
		"rows" => $t["rows"], "files" => $t["files"], "bytes" => $total,
		"cap" => $cap, "expired" => 0, "freed" => 0, "fail" => 0,
		"remaining" => $total, "ok" => true, "applied" => $do,
	);

	// DB size 只當參考 (會低估, 帳面一律用實際掃描值)
	$dbsum = $db->GetRow('SELECT sum(size) AS s, count(*) AS n FROM "map" WHERE flag=0');
	$dbsize = (is_array($dbsum) && $dbsum["s"] !== null) ? intval($dbsum["s"]) : 0;

	printf("\n=== USAGE ===\n");
	printf("  active rows      : %d (無家族檔 %d)\n", $t["rows"], $t["no_family"]);
	printf("  files            : %d\n", $t["files"]);
	printf("  actual disk      : %s\n", expire_bytes_fmt($total));
	printf("  DB sum(size)     : %s (僅參考, 低估 %s)\n",
		expire_bytes_fmt($dbsize), expire_bytes_fmt(max(0, $total - $dbsize)));
	printf("  cap (--limit-gb) : %s\n", $cap > 0 ? expire_bytes_fmt($cap) : "off");

	$lf = @fopen($listf, "w");
	if ($lf === false) { fwrite(STDERR, "ERROR: 無法寫 $listf\n"); exit(1); }
	fwrite($lf, "mid\tuid\tcdate\tcount\tbytes\tmode\ttitle\tfilename\n");

	if ($cap <= 0) {
		printf("  -> 空間管理關閉 (--limit-gb=0), 不 expire\n");
		fclose($lf);
		$out["listf"] = $listf;
		return $out;
	}
	if ($total <= $cap) {
		printf("  -> 在預算內 (餘額 %s), 不 expire\n", expire_bytes_fmt($cap - $total));
		fclose($lf);
		$out["listf"] = $listf;
		return $out;
	}

	$need = $total - $cap;
	$cands = expire_candidates($act["rows"], time(), array(
		"min_days" => $opt["min_days"],
		"protect_count" => $opt["protect_count"],
		"protect_uids" => expire_parse_uids($opt["protect_uid"]),
	));
	$plan = expire_plan($total, $cap, $cands, intval($opt["max_expire"]));
	printf("  -> 超出 %s; 受保護候選 %d 列, 可 expire %d 列, 預計釋放 %s\n",
		expire_bytes_fmt($need), count($cands), count($plan["mids"]), expire_bytes_fmt($plan["freed"]));
	if (!count($plan["mids"])) {
		printf("  -> 沒有可 expire 的候選 (新圖/熱門圖/指定 uid 或 --max-expire=0?), 交給下次\n");
	} else if (!$plan["ok"]) {
		printf("  -> 受保護的圖太多, 這次只能降到 %s (還是超過預算)\n", expire_bytes_fmt($plan["remaining"]));
	}

	$t0 = time();
	$freed_total = 0; $done = 0; $fail = 0; $skip = 0;
	foreach ($plan["mids"] as $mid) {
		$r = $act["rows"][$mid];
		$freed = intval($r["family"]["bytes_nongpx"]);
		$line = sprintf("%d\t%d\t%s\t%d\t%d\t%s\t%s\t%s\n",
			$mid, $r["uid"], $r["cdate"], $r["count"], $freed,
			$do ? "expire" : "dry-run",
			str_replace(array("\t", "\n"), " ", (string)$r["title"]), $r["filename"]);
		if (!$do) {
			fwrite($lf, $line); $done++; $freed_total += $freed; continue;
		}
		// 掃描到現在可能已經被別的程式刪掉/過期, 先確認還是 flag=0
		$row = $db->GetRow(sprintf('SELECT mid, flag FROM "map" WHERE mid=%d', $mid));
		if (!is_array($row) || intval($row["flag"]) !== 0) { $skip++; continue; }
		// 同 F1: 先失效 memcached, 避免 map_expire() 的命中分支漏連線
		memcached_delete(sprintf("map_get_single_%s", $mid));
		$db->close();
		$rs = map_expire($mid);                 // 保留 .gpx, 設 flag=1/edate/size=0
		$db = get_conn();
		if ($rs === false) { $fail++; continue; }
		expire_cleanup_dirs($r["filename"]);
		fwrite($lf, $line);
		$done++; $freed_total += $freed;
		if (($done % 50) === 0) printf("[expire] done=%d freed=%s last_mid=%d\n",
			$done, expire_bytes_fmt($freed_total), $mid);
		flush();
	}
	fclose($lf);

	$out["expired"] = $done;
	$out["freed"] = $freed_total;
	$out["fail"] = $fail;
	$out["skip"] = $skip;
	$out["remaining"] = $total - $freed_total;
	$out["ok"] = ($fail === 0);
	printf("  -> %s: expired=%d skipped=%d fail=%d freed=%s, usage -> %s\n",
		$do ? "EXPIRE" : "DRY-RUN", $done, $skip, $fail,
		expire_bytes_fmt($freed_total), expire_bytes_fmt($out["remaining"]));
	printf("  elapsed=%ds\n", max(1, time() - $t0));
	printf("list: %s\n", $listf);
	$out["listf"] = $listf;
	return $out;
}

// ---- main ---------------------------------------------------------------
$t_start = time();
$db = get_conn();
$rc = 0;
$check = null; $fix = null; $usage = null; $exp = null;

if (!$opt["no_check"]) {
	$check = expire_run_check($db, $opt, $opt["state"] . ".check");
	if ($check["fail"] > 0) $rc = 2;
}
if (!$opt["no_fix"]) {
	$fix = expire_run_fix($db, $opt, $do, $opt["state"] . ".fix");
	if ($fix["fail"] > 0) $rc = 3;
	// --apply 之後規則可能已被修掉, 重掃一次當結論 (check 相關的 rc 也跟著重算)
	if ($do && $check !== null) {
		printf("\n-- re-check after fix --\n");
		$check = expire_run_check($db, $opt, $opt["state"] . ".check");
		$rc = ($check["fail"] > 0) ? 2 : 0;
		if ($fix["fail"] > 0) $rc = 3;      // 修復本身失敗比殘留違規更嚴重
	}
}
if (!$opt["no_expire"]) {
	// fix 階段叫過 map_del(), 連線狀態不明, 換個乾淨 handle 給後面掃描用
	$db->close();
	$db = get_conn();
	$act = expire_active_scan($db, $opt);
	$exp = expire_run_expire($db, $opt, $act, $do, $opt["state"] . ".expire");
	if (!$exp["ok"]) $rc = 3;
	$usage = array("rows" => $exp["rows"], "files" => $exp["files"],
		"bytes" => $exp["bytes"], "cap" => $exp["cap"]);
}

$st["status"] = "done";
$st["finished"] = date("c");
$st["rc"] = $rc;
$st["elapsed"] = time() - $t_start;
if ($check !== null) $st["check"] = array("scanned" => $check["scanned"], "fail" => $check["fail"], "hits" => $check["hits"]);
if ($fix !== null) $st["fix"] = $fix["stats"];
if ($usage !== null) $st["usage"] = $usage;
if ($exp !== null) $st["expire"] = $exp;
expire_state_save($opt["state"], $st);

printf("\n=== EXPIRE %s ===  rc=%d elapsed=%ds\n", $do ? "(apply)" : "(dry-run)", $rc, $st["elapsed"]);
if ($check !== null) printf("  check: scanned=%d fail=%d -> %s\n", $check["scanned"], $check["fail"], $check["listf"]);
if ($fix !== null) printf("  fix  : rows=%d fail=%d -> %s\n",
	$fix["stats"]["F1"]["rows"] + $fix["stats"]["F2"]["rows"] + $fix["stats"]["F3"]["rows"] + $fix["stats"]["F4"]["rows"],
	$fix["fail"], $fix["listf"]);
if ($exp !== null) printf("  usage: %s / cap %s, expired=%d freed=%s -> %s\n",
	expire_bytes_fmt($exp["bytes"]), $exp["cap"] > 0 ? expire_bytes_fmt($exp["cap"]) : "off",
	$exp["expired"], expire_bytes_fmt($exp["freed"]), $exp["listf"]);
printf("state: %s\n", $opt["state"]);

expire_lock_release($lock);
exit($rc);
