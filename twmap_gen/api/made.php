<?php
//
// made.php
// callback for queue worker when map is done.
//
require_once("../config.inc.php");

if (!isset($_REQUEST['ch'] ) || !isset($_REQUEST['status'])){
	exit("require params");
}
$log_channel=$_REQUEST['ch'];

$param  = redis_get($log_channel);
// error_log(print_r([$log_channel,$param],true)); 

if ($param === FALSE) {
	my_error_out("no such channel");
}

if ($_REQUEST['status'] == 'ok') {
	finish_task($param);
} else {
	my_error_out($_GET['status']);
}
function my_error_out($msg){
	global $log_channel;
	notify_web($log_channel,array("err:".$msg));
	msglog("err:$msg");
	header("HTTP/1.0 400 Bad Request");
	printf("<h1>%s</h1>",print_r($msg,true));
	exit(0);
}
function finish_task($param) {
	global $out_root;
	//list ($uid, $limit, $recreate_flag,  $xx, $yy, $shiftx, $shifty, $datum,$version, $outx, $outy, $title,$outimage, $remote_ip,$log_channel,$paper) = json_decode($param, true);
	extract( json_decode($param, true));

	if (map_full($uid, $limit, $recreate_flag)) {
		$files = map_files($outimage);
		foreach ($files as $f) {
			@unlink($f);
		}
		my_error_out("已經達到數量限制" . $limit);
	}
	if (file_exists(str_replace(".tag.png", ".gpx", $outimage))) {
		$save_gpx = 1;
	} else {
		$save_gpx = 0;
	}
	$mid = map_add($uid, $title, $xx, $yy, $shiftx, $shifty, 0, 0, $remote_ip, $outimage, map_size($outimage), $version, $save_gpx, NULL, $datum);

	if ($mid === false ) {
		my_error_out("寫入資料庫失敗,請回報 $outimage");
	}
	// 最後搬移到正確目錄
	sleep(1);
	$ret = map_migrate($out_root, $uid, $mid);
	if ($ret == false) {
		msglog('error migrate directory');
	}
	// 寫入資料庫
	make_map_log($mid, $log_channel, $_REQUEST['agent'], $_REQUEST['params']);

	$okmsg = msglog("done $mid");
	// 刪除 redis key
	redis_delete($cmd_param_key);
	redis_delete($log_channel);
	
	msglog("notify web $log_channel with $mid");
	notify_web($log_channel,array("finished!$mid"));
	printf("<h1>%s</h1>",$okmsg[0]);
}



// function
//

function msglog($str) {
	static $msg = array();
	$msg[] = sprintf("%s|%s", date('Y/m/d H:i:s'), $str);
	error_log($str);
	return $msg;
}

