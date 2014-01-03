<?php

$uid = 1;
require_once("../config.inc.php");

$kid = isset($_GET['kid'])? $_GET['kid'] : null;

if ($kid == null) {
	echo "require kid";
	exit;
}
$row = keepon_map_exists($uid, $kid);
if ($row === false ) {
	echo "map $kid not exists";
	exit;
}
$ret_url = sprintf("http://map.happyman.idv.tw/twmap/show.php?mid=%d&info=%dx%d-%dx%d",$row['mid'],$row['locX'],$row['locY'],$row['shiftX'],$row['shiftY']);


kok_out($kid, "renotify ok", $ret_url, $row['cdate']);
