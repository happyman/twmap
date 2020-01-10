<?php

//$Id: backend_make.php 356 2013-09-14 10:00:22Z happyman $
// 1. check login
session_start();
if (empty($_SESSION['loggedin'])) {
    header("Location: login.php");
    exit(0);
}

require_once ("config.inc.php");
ini_set("memory_limit", "512M");
ini_set("max_execution_time", "300");
ignore_user_abort(true);

// 2. check _POST
$inp = $_POST;

// $ERROROUT = "ajax";
if ($inp['gps'] == 1) {
    $ERROROUT = "post";
    // 2.1. 處理上傳檔案

    if ($_FILES['gpxfile']['error'] == UPLOAD_ERR_OK) {
        $tmp_file = $_FILES['gpxfile']['tmp_name'];
        msglog($tmp_file . " uploaded");
        $pa = pathinfo($_FILES['gpxfile']['name']);
        $ext = strtolower($pa['extension']);
        if ($ext == "gdb") {
            $tmp_gpx = tempnam("/tmp", "GPX") . ".gpx";
            $cmd = sprintf("/usr/bin/gpsbabel -i gdb -o gpx -f %s -F %s", $tmp_file, $tmp_gpx);
            exec($cmd, $out, $ret);
            msglog($cmd . " ret=" . print_r($out, true));
            if ($ret != 0) {
                @unlink($tmp_file);
                error_out("輸入的 gdb 檔轉檔失敗");
            }
        }
        else if ($ext == "gpx") {
            $tmp_gpx = $tmp_file;
        }
        else {
            unlink($tmp_file);
            error_out("不支援的格式");
        }

        // 如果原始檔案是特殊格式,則將檔名當作參數帶入
        // twmap_294_2677_7_5_0.gpx 則讀出
        if (preg_match("/^(\d+)x(\d+)-(\d+)x(\d+)-v\d([p]?)$/", $pa['filename'], $testnam)) {

            //$testnam = explode("_", $pa['filename']);
            //if ($testnam[0] == 'twmap') {
            $inp['startx'] = $testnam[1] / 1000;
            $inp['starty'] = $testnam[2] / 1000;
            $inp['shiftx'] = $testnam[3];
            $inp['shifty'] = $testnam[4];
            $inp['ph'] = ($testnam[5] == 'p') ? 1 : 0;

            //	error_out(print_r($inp,true));

        }
        else {

            // 輸入的是 gpx , 讀出邊界範圍, 本 svg 不做為轉檔之用, 因為尚未得到圖檔大小
            $svg = new gpxsvg(array("gpx" => $tmp_gpx, "width" => 1024, "fit_a4" => 1, "auto_shrink" => (isset($inp['auto_shrink'])) ? 1 : 0,
			"show_label_trk" => (isset($inp['trk_label'])) ? 1 : 0, "show_label_wpt" => $inp['wpt_label']));
            $ret = $svg->process();
            msglog("svg get_bound processed");
            if ($ret === false) {
                @unlink($tmp_gpx);
                error_out("讀取 gpx 失敗" . print_r($svg->_err, true));
            }
            $inp['startx'] = $svg->bound_twd67['tl'][0] / 1000;
            $inp['starty'] = $svg->bound_twd67['tl'][1] / 1000;
            $inp['shiftx'] = ($svg->bound_twd67['br'][0] - $svg->bound_twd67['tl'][0]) / 1000;
            $inp['shifty'] = ($svg->bound_twd67['tl'][1] - $svg->bound_twd67['br'][1]) / 1000;
            $inp['ph'] = $svg->bound_twd67['ph'];
            unset($svg);
        }
    }
    else {
        $msgarr = array(1 => "The uploaded file exceeds the upload_max_filesize directive in php.ini", 2 => "The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form", 3 => "The uploaded file was only partially uploaded", 4 => "No file was uploaded", 6 => "Missing a temporary folder", 7 => 'Failed to write file to disk.', 8 => 'A PHP extension stopped the file upload.',);
        error_out("上傳失敗" . $msgarr[$_FILES['gpxfile']['error']]);
    }
     // end of if
    // 2.1. 處理上傳檔案


}
else if ($inp['gps'] == 2) {

    // a. 從 mid 確認 expire 是不是 1, 然後檔案存不存在

    $row = map_get_single($inp['gpxmid']);
    if ($row['flag'] == 1 && $row['gpx'] == 1 && $row['uid'] == $_SESSION['uid']) {
        $tmp_gpx = str_replace(".tag.png", ".gpx", $row['filename']);
        if (!file_exists($tmp_gpx)) error_out("gpx 檔案已經消失");
    }
    else {
        error_out("gpx 資訊有誤");
    }
    $inp['startx'] = $row['locX'] / 1000;
    $inp['starty'] = $row['locY'] / 1000;
    $inp['shiftx'] = $row['shiftX'];
    $inp['shifty'] = $row['shiftY'];
    if (strstr($tmp_gpx, "p.gpx")) $inp['ph'] = 1;
    else $inp['ph'] = 0;
}
else {
    switch ($inp['kiss']) {
        case '2':
            $inp['shiftx'] = $inp['aashiftx'];
            $inp['shifty'] = $inp['aashifty'];
            break;

        case '3':
            $inp['shiftx'] = $inp['aarshiftx'];
            $inp['shifty'] = $inp['aarshifty'];
            break;

        case '1':
        default:
            $inp['shiftx'] = $inp['anyshiftx'];
            $inp['shifty'] = $inp['anyshifty'];
            break;
    }
}

if (empty($inp['startx']) || empty($inp['starty']) || empty($inp['shiftx']) || empty($inp['shifty']) || empty($inp['title']) || empty($inp['formid'])) {
    error_out("參數錯誤" . print_r($_POST, true));
}

//

$version = isset($inp['version']) ? $inp['version'] : "3";
// if ($version < 1 || $version > 3) $version = 3;
if ($version != 1 && $version != 3 && $version != 2016) $version = "3";
$shiftx = $inp['shiftx'];
$shifty = $inp['shifty'];
$title = $inp['title'];
$startx = $inp['startx'];
$starty = $inp['starty'];
$xx = $startx * 1000;
$yy = $starty * 1000;
$ph = $inp['ph'];
 // 澎湖
$gpx = ($inp['gps'] > 0) ? 1 : 0;

// error_log("$stbpath, $startx, $starty, $shiftx, $shifty");
// 1. 檢查產生地圖數量是否超過上限
$user = fetch_user($_SESSION['mylogin']);

// 1. 看看本圖是否為重新產生?
if (map_exists($_SESSION['uid'], $xx, $yy, $shiftx, $shifty, $version, $gpx)) {
    $recreate_flag = 1;
}
else {
    $recreate_flag = 0;
}
if (map_full($_SESSION['uid'], $user['limit'], $recreate_flag)) {
    error_out("$recreate_flag 已經達到數量限制" . $user['limit']);
}

$block_msg = map_blocked($out_root, $_SESSION['uid']);
if ($block_msg != null) {
    error_out($block_msg);
}
$datum=(isset($inp['97datum']))? 'TWD97': 'TWD67';
$outpath = sprintf("%s/%06d", $out_root, $_SESSION['uid']);
$outfile_prefix = sprintf("%s/%dx%d-%dx%d-v%d%s_%s", $outpath, $startx * 1000, $starty * 1000, $shiftx, $shifty, $version, ($ph == 1) ? 'p' : "", $datum);
$outimage = $outfile_prefix . ".tag.png";
$outgpx = $outfile_prefix . ".gpx";

$svg_params = "";

// 終於可以把 gpx 存起來
if ($inp['gps'] == 1 || $inp['gps'] == 2) {
    @mkdir(dirname($outgpx), 0755, true);
    if (!copy($tmp_gpx, $outgpx)) {
        if ($inp['gps'] == 1) {
            @unlink($_FILES['gpxfile']['tmp_file']);
            @unlink($tmp_gpx);
        }
        error_out("存入上傳檔案失敗");
    }
    @unlink($_FILES['gpxfile']['tmp_file']);
    @unlink($tmp_gpx);
    $svg_params = sprintf("-g %s:%d:%d", $outgpx, (isset($inp['trk_label'])) ? 1 : 0, $inp['wpt_label']);
}

// 呼叫 cmd_line make, 他也需要 gpx aware
// -l 傳入 email:formid 作為識別 channel 與 msg owner
showmem("before call cmd_make.php");
$cmd = sprintf("php cmd_make2.php -r %d:%d:%d:%d:%s -O %s -v %d -t '%s' -i %s -p %d %s -l %s:%s %s %s %s %s", $startx, $starty, $shiftx, $shifty, 
isset($inp['97datum'])? 'TWD97': 'TWD67',
$outpath, $version, addslashes($title), $_SERVER['REMOTE_ADDR'], $ph, $svg_params, $_SESSION['mylogin']['email'], $inp['formid'], isset($inp['grid_100M']) ? '-e' : '',
 // 是否包含 100M grid
isset($inp['inc_trace']) ? '-G' : '',
 //是否包含已知 gps trace
isset($inp['keep_color']) ? '-c' : '',
 // 是否輸出彩圖
 isset($inp['a3_paper']) ? '-3' : ''
 );
msglog($cmd);
exec($cmd, $output, $ret);

if ($ret != 0) {
    foreach ($output as $line) {
        if (strstr($line, "err:")) $errline.= substr($line, 4) . "\n";
    }
    error_out($errline);
}

// before register, check count again
if (map_full($_SESSION['uid'], $user['limit'], $recreate_flag)) {
    $files = map_files($outimage);
    foreach ($files as $f) {
        @unlink($f);
    }
    error_out("已經達到數量限制" . $user['limit']);
}
if (isset($inp['a3_paper']))
	$type = determine_type_a3($shiftx, $shifty);
else
	$type = determine_type($shiftx, $shifty);
$outx = ceil($shiftx / $tiles[$type]['x']);
$outy = ceil($shifty / $tiles[$type]['y']);
if (file_exists(str_replace(".tag.png", ".gpx", $outimage))) {
    $save_gpx = 1;
} else {
    $save_gpx = 0;
}
$mid = map_add($_SESSION['uid'], $title, $xx, $yy, $shiftx, $shifty, $outx, $outy, $_SERVER['REMOTE_ADDR'], $outimage, map_size($outimage), $version, $save_gpx, NULL, isset($inp['97datum'])? 97 : 67);

// 最後搬移到正確目錄
map_migrate($out_root, $_SESSION['uid'], $mid);


$okmsg = msglog("done");

// even here is exit(0)
// the ape client will still redirect user to the correct place?
// sleep(1);
// in case the http connection is broken. seealso js:twmap.js
// add finished!final_mid (注意 :, 看 notify_web in STB.inc.php)
$log_channel = sprintf("%s:%s",$_SESSION['mylogin']['email'], $inp['formid']);
msglog("notify web $log_channel with $mid");
notify_web($log_channel,array("finished!$mid"));
ok_out(implode("", $okmsg), $mid);



// function
//

function msglog($str) {
    static $msg = array();
    $msg[] = sprintf("%s|%s", date('Y/m/d H:i:s'), $str);
    error_log($str);
    return $msg;
}

