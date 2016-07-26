<?php
$uid = 1;
require_once("../config.inc.php");
//if(!ob_start("ob_gzhandler")) ob_start();

if (empty($_REQUEST['x']) || empty($_REQUEST['y'])) {
	ajaxerr("insufficent parameters");
}
$lon = $_REQUEST['x'];
$lat = $_REQUEST['y'];
$z = (isset($_REQUEST['z'])) ? $_REQUEST['z'] : 0;

$lon1 = isset($_REQUEST['x1'])? $_REQUEST['x1']: null;
$lat1 = isset($_REQUEST['y1'])? $_REQUEST['y1']: null;
$z1 = (isset($_REQUEST['z1'])) ? $_REQUEST['z1'] : 0;

// 1. 檢查點位是否在台灣澎湖
tw_chk($lon,$lat);

if ($lon1 !== null) {
	// 2. 檢查兩點是否可通視
	tw_chk($lon1,$lat1);
	list($st, $end_point, $msg) = line_of_sight(array($lon,$lat,$z),array($lon1,$lat1,$z1));
	ajaxok(array($st,$end_point,$msg));
} else {
	// 2. get_points_from_center
	$points = get_points_from_center(array($lon,$lat), 32000);
	// 3. 計算
	foreach($points as $point){
		list ($st, $end_point, $msg) = line_of_sight(array($lon,$lat,$z),array($point['x'],$point['y'],$point['ele'],32000));
		$result[] = array($st,$end_point,$point['name'],$point['ele'],$msg);

	}
	ajaxok($result);
}
function tw_chk($lon,$lat){
if ($lon < 119.31 || $lon > 124.56 || $lat < 21.88 || $lat >25.31  ) {
		ajaxerr("out of range");

} 
}