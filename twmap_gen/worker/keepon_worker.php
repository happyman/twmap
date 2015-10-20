<?php
require_once(__DIR__ . "/../config.inc.php");
$GLOBALS['db_host'] = $db_host;
$GLOBALS['db_user'] = $db_user;
$GLOBALS['db_pass'] = $db_pass;
$GLOBALS['db_name'] = $db_name;
$GLOBALS['out_root'] = $out_root;
$GLOBALS['site_url'] = $site_url;
$GLOBALS['site_html_root'] = $site_html_root;

class keepon_worker
{
    public function run($job, &$log) {
        global $out_root, $site_url;

        $workload = $job->workload();
        $todo = unserialize($workload);

        $uid = 1;
        $limit = 20000;
        $puid = WWWRUN_UID;
        $cmd_make = realpath(__DIR__ . "/../cmd_make2.php");

        kcli_msglog($todo);

        $version = 3;
        $trk_label = 0;
        $wpt_label = 2;
        $shiftx = $todo['shiftx'];
        $shifty = $todo['shifty'];
        $startx = $todo['startx'];
        $starty = $todo['starty'];
        $xx = $startx * 1000;
        $yy = $starty * 1000;
        $ph = $todo['ph'];
        $gpxfile = $todo['gpx'];
        $keepon_id = $todo['id'];
        $title = trim($todo['title']);

        echo "Doing id=$keepon_id title=$title\n";

        // 1. 檢查是否已經處理過 keepon_id
        $map = keepon_map_exists($uid, $keepon_id);
        if ($map !== false) {
            if (!isset($map['mid']) || $map['mid'] == 0) {
                kcli_msglog("keepon_id: $keepon_id uid: $uid exists but return " . print_r($map));
                exit(0);
            }
            $ret_url = sprintf("http://map.happyman.idv.tw/twmap/show.php?mid=%d&info=%dx%d-%dx%d", $map['mid'], $map['locX'], $map['locY'], $map['shiftX'], $map['shiftY']);
            kok_out($keepon_id, "已經產生過", $ret_url, $map['cdate']);
            $log[] = "$keepon_id 已經產生過";
            return;
        }
        $block_msg = map_blocked($out_root, $uid);
        if ($block_msg != null) {
            kerror_out($keepon_id, $block_msg);
            $log[] = "$keepon_id 無法 block";
            return;
        }
        $outpath = sprintf("%s/%06d", $out_root, $uid);
        $outfile_prefix = sprintf("%s/%dx%d-%dx%d-v%d%s", $outpath, $startx * 1000, $starty * 1000, $shiftx, $shifty, $version, ($ph == 1) ? 'p' : "");
        $outimage = $outfile_prefix . ".tag.png";
        $outgpx = $outfile_prefix . ".gpx";

        $svg_params = "";

        // 終於可以把 gpx 存起來
        // if ($inp['gps'] == 1 ) {
        @mkdir($outpath, 0755, true);
        if (!copy($gpxfile, $outgpx)) {
            @unlink($gpxfile);
            kerror_out($keepon_id, "$outgpx 存入上傳檔案失敗");
            $log[] = "$keepon_id $gpxfile $outgpx 存入失敗";
            return;
        }
        @unlink($gpxfile);
        $svg_params = sprintf("-g %s:%d:%d", $outgpx, $trk_label, $wpt_label);

        $cmd = sprintf("php %s -r %d:%d:%d:%d -O %s -v %d -t '%s' -i %s -p %d %s",$cmd_make, $startx, $starty, $shiftx, $shifty, $outpath, $version, addslashes($title), "localhost", $ph, $svg_params);
        kcli_msglog($cmd);
        $output = array();
        exec($cmd, $output, $ret);

        if ($ret != 0) {
            kerror_out($keepon_id, implode("\n", $output));
            $log[] = "$keepon_id 產生錯誤" . implode("\n",$output);
            return;
        }

        // 限制數量
        if (map_full($uid, $limit, 0)) {
            $files = map_files($outimage);
            foreach ($files as $f) {
                @unlink($f);
            }
            kerror_out($keepon_id, "已經達到數量限制" . $limit);
            $log[] = "$keepon_id 已達數量限制";
            return;
        }
        $type = determine_type($shiftx, $shifty);
        $outx = ceil($shiftx / $tiles[$type]['x']);
        $outy = ceil($shifty / $tiles[$type]['y']);
        if (file_exists(str_replace(".tag.png", ".gpx", $outimage))) {
            $gpx = 1;
        }

        // 檢查是否重新連線
        //require("lib/db.inc.php");
        $mid = map_add($uid, $title, $xx, $yy, $shiftx, $shifty, $outx, $outy, "localhost", $outimage, map_size($outimage), $version, 1, $keepon_id);

        if ($mid === false) {

            // 防止錯誤發生需要重跑
            kcli_msglog(implode("|", array($title, $xx, $yy, $shiftx, $shifty, $keepon_id)));
            kerror_out($keepon_id, "新增錯誤");
            $log[] = "$keepon_id 新增錯誤";
            return;
        }

        // 最後搬移到正確目錄
        map_migrate($out_root, $uid, $mid);
        $okmsg = kcli_msglog("done");
        $ret_url = sprintf("%s%s/show.php?mid=%d&info=%dx%d-%dx%d", $site_url, $site_html_root, $mid, $xx, $yy, $shiftx, $shifty);
        kok_out($keepon_id, "done", $ret_url);
        $log[] = "$keepon_id 產生完畢";
    }
}
