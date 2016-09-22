<?php
// twmap_gpx layer handle
// action=add add mid to gpx layer
// action=del del mid from gpx layer
require_once("../config.inc.php");
// 1. check user permission
if (!is_admin()){
	ajaxerr("I love you!");
}
// 2. import / remove mid to gpx layer
$mid = $_REQUEST['mid'];
$action = $_REQUEST['action'];
if (empty($mid) || empty($action)) {
	ajaxerr('please input');
}

switch($action){
	case 'del':
		list ($status, $msg) =  remove_gpx_from_gis($mid);
		if ($status == true){
			list($st, $toclean) = tilestache_clean($mid, 1);
			ajaxok("ok");
		}
		break;
	case 'add':
		$exist_mid = is_gpx_imported($mid);
		if ($exist_mid === false) {
			list($status,$msg) = import_gpx_to_gis($mid);
			if ($status == true){
				list($st, $toclean) = tilestache_clean($mid, 1);
				ajaxok("ok");
			} else {
				ajaxerr("gpx import fail:" . $msg);
			}
		} else {
			ajaxerr("gpx imported");
		}
		break;
}
ajaxerr("error");