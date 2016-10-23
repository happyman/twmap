<?php
require_once("../config.inc.php");

$debug = 1;
list ($login,$uid) = userid();
if ($login === false) {
	header("HTTP/1.1 403 Access Deined");
	echo "請登入";
	exit;
}
 
if (!empty($_FILES)) {
     
    $tempFile = $_FILES['file']['tmp_name'];                      
      
	$pa = pathinfo($_FILES['file']['name']);
    $ext = strtolower($pa['extension']);
	if (!($ext == 'kml' || $ext == 'kmz' || $ext == 'gpx' || $ext == 'gdb')){
		header("HTTP/1.1 400 Error file extension");
		echo "不支援的檔案";
		exit(0);
	}
	$fname = md5(sprintf("%d_%s",$uid,$_FILES['file']['name']));
    $targetFile = sprintf("%s/%06d/track/tmp/%s/%s_o.%s", $out_root, $uid, $fname, $fname, $ext);
    
    if ($_FILES['file']['error'] == UPLOAD_ERR_OK){ 
		@mkdir(dirname($targetFile), 0755, true);
		if (!is_dir(dirname($targetFile))){
			header("HTTP/1.1 500 Internal Server Error");
			echo "無法建立目錄";
			exit(0);
		}
		// 1. check num of tracks
		$t = track_get($uid);
		if (count($t) >= 50 ) {
			header("HTTP/1.0 500 Internal Server Error");
			echo "超過數量限制";
			exit(0);
		}
		move_uploaded_file($tempFile,$targetFile);
		// 2. process track
		list ($st, $msg, $meta) = process_track($targetFile,$fname,$ext);		
		if ($st != 0 ){
			header("HTTP/1.0 500 Internal Server Error");
			echo $msg;
			exit(0);
		
		}

		// 3. add metadata to database
		$tid = track_add(array("name"=>$_FILES['file']['name'],
		"uid"=>$uid, "status"=> $st, "path"=> sprintf("%s/%06d/track/",$out_root, $uid), 
		"md5name"=> $fname, "size"=>$meta['size'],
		"bbox"=>$meta['bbox'], "km_x"=>$meta['km_x'], "km_y"=> $meta['km_y'], "is_taiwan"=>$meta['is_taiwan']));
		if (intval($tid) == 0 ){
			header("HTTP/1.0 500 Internal Server Error");
			echo "sql insert error";
			exit(0);
		
		}	
		$saveFile = sprintf("%s/%06d/track/%s/%s_o.%s",$out_root,$uid,$tid,$fname,$ext);
		migrate_track($targetFile,$saveFile);
		if ($st === 0)
			echo "$targetFile";
		else{
			header("HTTP/1.1 500 Internal Server Error");
			echo $msg;
		}
		exit(0);
	}else {
		header("HTTP/1.1 500 Internal Server Error");
		echo "上傳失敗";
		exit(0);
	}
	
     
}
