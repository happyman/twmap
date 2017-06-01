<?php
require_once("../config.inc.php");

$debug = 1;
$cli = 0;
$keepon_id = "";
if (php_sapi_name() != "cli"){
	list ($login,$uid) = userid();
	if ($login === false) {
		header("HTTP/1.1 403 Access Deined");
		echo "請登入";
		exit(1);
	}
} else {
	$uid = 1;
	$cli = 1;
	if (!isset($argv[1])) {
		echo "usage: upload.php gpx_file name [keepon_id]\n";
		exit(1);
	}
		
	$_FILES['file']['tmp_name'] = $argv[1];
	if (!isset($argv[2])) {
		$_FILES['file']['name'] = basename($argv[1]);
	} else {
		$_FILES['file']['name'] = $argv[2];
	}
	$_FILES['file']['error'] = UPLOAD_ERR_OK;
	if (isset($argv[3])){
			$keepon_id = $argv[3];
	}
	//print_r($_FILES);
}
if (!empty($_FILES)) {
     
    $tempFile = $_FILES['file']['tmp_name'];                      
    if (strstr($tempFile,"http")){
	exec(sprintf("wget -O /tmp/%s %s", basename($tempFile), $tempFile));
	$tempFile = "/tmp/".  basename($tempFile);
    }
      
	$pa = pathinfo($_FILES['file']['name']);
    $ext = strtolower($pa['extension']);
	if (!($ext == 'kml' || $ext == 'kmz' || $ext == 'gpx' || $ext == 'gdb')){
		header("HTTP/1.1 400 Error file extension");
		echo "不支援的檔案";
		exit(1);
	}
	$fname = md5(sprintf("%d_%s",$uid,$_FILES['file']['name']));
    $targetFile = sprintf("%s/%06d/track/tmp/%s/%s_o.%s", $out_root, $uid, $fname, $fname, $ext);
    
    if ($_FILES['file']['error'] == UPLOAD_ERR_OK){ 
		@mkdir(dirname($targetFile), 0755, true);
		if (!is_dir(dirname($targetFile))){
			header("HTTP/1.1 500 Internal Server Error");
			echo "無法建立目錄";
			exit(1);
		}
		// 1. check num of tracks
		$t = track_get($uid);
		if (count($t) >= 50 && $uid != 3 && $uid != 1) {
			header("HTTP/1.0 500 Internal Server Error");
			echo "超過數量限制";
			exit(1);
		}
		if ($cli == 0 )
			move_uploaded_file($tempFile,$targetFile);
		else
			rename($tempFile,$targetFile);
		// echo "move uploaded file $tempFile $targetFile\n";
		// 2. process track
		list ($st, $msg, $meta) = process_track($targetFile,$fname,$ext);		
		if ($st != 0 ){
			header("HTTP/1.0 500 Internal Server Error");
			echo $msg;
			print_r($meta);
			exit(1);
		
		}

		// 3. add metadata to database
		$tid = track_add(array("name"=>$_FILES['file']['name'],
		"uid"=>$uid, "status"=> $st, "path"=> sprintf("%s/%06d/track/",$out_root, $uid), 
		"md5name"=> $fname, "size"=>$meta['size'],
		"bbox"=>$meta['bbox'], "km_x"=>$meta['km_x'], "km_y"=> $meta['km_y'], "is_taiwan"=>$meta['is_taiwan'],"keepon_id" => $keepon_id));
		if (intval($tid) == 0 ){
			header("HTTP/1.0 500 Internal Server Error");
			echo "sql insert error";
			exit(1);
		
		}	
		$saveFile = sprintf("%s/%06d/track/%s/%s_o.%s",$out_root,$uid,$tid,$fname,$ext);
		migrate_track($targetFile,$saveFile);
		// if keepon_id then call keepon_api to report url happyman
		if ($st === 0) {
			echo "$targetFile\n";
			// update to keepon. hardcode to prevent dev url
			if (!empty($keepon_id)) {
				$url = "http://map.happyman.idv.tw/twmap/show.php?mid=-" . $tid;
				keepon_Update($keepon_id, 1, $url);
				echo "update keepon $keepon_id with url $url\n";
			}
		} else{
			header("HTTP/1.1 500 Internal Server Error");
			echo $msg;
			exit(1);
		}
		exit(0);
	}else {
		header("HTTP/1.1 500 Internal Server Error");
		echo "上傳失敗";
		exit(1);
	}
	
     
}
