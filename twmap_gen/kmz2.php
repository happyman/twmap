<?php

// 1. 檢查是不是在 2013/9/6 - 2013/10/18 之間產生的地圖
// 如果是就重新產生,不然就直接 redirect
//
session_start();
require_once("config.inc.php");

$mid = $_GET['mid'];
$map = map_get_single($mid);

if ($map == null ) {
	echo "<h1>無此 map".print_r($_GET,true)."</h1>";
	exit(0);
}

$kmzfile = str_replace(".png",".kmz",$map['filename']);
$mtime = filemtime($kmzfile);

// 這一段時間有 bug
$start = strtotime("2013-9-6");
$end = strtotime("2013-10-18 23:00");

if ($mtime > $start && $mtime < $end) {
	unlink($kmzfile);
	error_log("remake...kmz");
	require_once("lib/garmin.inc.php");
	$kmz = new garminKMZ(3,3,$map['filename'],(strstr($map['filename'],'v3p'))?1:0);
	$kmz->doit();
}
$kmzname = basename($kmzfile);
$size = filesize($kmzfile);
header("Content-type: application/vnd.google-earth.kmz");
header("Content-Disposition: filename=\"$kmzname\"");
header("Content-Length: $size");

readfile($kmzfile);



