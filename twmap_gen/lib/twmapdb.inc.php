<?php
// 當 login 之後, 必須註冊到 db 裡面
// $Id: twmapdb.inc.php 356 2013-09-14 10:00:22Z happyman $
//
require_once("adodb_lite/adodb.inc.php");

function get_conn() {
	global $db_conn,$db_user, $db_pass, $db_name;

	if ($db_conn != null && $db_conn->IsConnected())
		return $db_conn;

	error_log("new conection");
	$db_conn = ADONewConnection('mysqli');
	$status = $db_conn->PConnect('localhost', $db_user, $db_pass, $db_name);

	if ($status === true ) {
		if ($db_conn->IsConnected() !== true) {
			error_log("db can't connect");
			//return false;
			exit("no db connection");
		} 
		//$db_conn->SetFetchMode(ADODB_FETCH_ASSOC);
		$ADODB_FETCH_MODE = 'ADODB_FETCH_ASSOC';
		$db_conn->debug = false;
		return $db_conn;
	} else {
		error_log("db can't connect");
		exit("no db connection");
	}

}
function fetch_user($mylogin) {
	$sql = sprintf("select * from user where email='%s' and type='%s'", $mylogin['email'],$mylogin['type']);
	$db = get_conn();
	$rs = $db->GetAll($sql);
	if (count($rs) == 0) return false;
	//$res = mysql_query($sql);
	//$row = mysql_fetch_array($res);
	//return $row;
	return $rs[0];
}
function login_user($mylogin) {
	$row = fetch_user($mylogin);
	//error_log("row=".print_r($row, true));
	$db = get_conn();
	// 尚未註冊, 註冊
	if ($row === FALSE || count($row) == 0 ) {
		$sql = sprintf("INSERT INTO `twmap`.`user` (`uid`, `email`, `type`, `name`, `limit`, `cdate`, `login`) VALUES (NULL, '%s', '%s', '%s',  30, CURRENT_TIMESTAMP, 1)", $mylogin['email'],$mylogin['type'],$mylogin['nick']);

		//$res = mysql_query($sql);
		//error_log("sql=".print_r($sql, true));
		$rs = $db->Execute($sql);
	} else {
		// 新增 counter
		$sql = sprintf("update `twmap`.`user` SET `login`=%d, `name`='%s' WHERE `user`.`uid`=%d",$row['login']+1, $mylogin['nick'],$row['uid']);
		//$res = mysql_query($sql);
		$rs = $db->Execute($sql);
	}
	// 是否加上 login record ?
	//
	return fetch_user($mylogin);
}
function map_exists($uid,$startx,$starty,$shiftx,$shifty,$version,$gpx=0) {
	$db = get_conn();
	$sql = sprintf("SELECT `mid` from `twmap`.`map` WHERE `uid`='%s' AND `locX`=%d AND `locY`=%d AND `shiftX`=%d and `shiftY`=%d and `version`=%d and `gpx` = %d",$uid,$startx,$starty,$shiftx,$shifty,$version,$gpx);
	$rs = $db->GetAll($sql);
	//$res = mysql_query($sql);
	//error_log("to find existing mid: $mid");
	//if (!$res) return false;
	//$row = mysql_fetch_array($res);
	//if ($row===false) return false;
	if (count($rs) == 0 ) return false;
	return $rs[0];
}
function keepon_map_exists($uid,$keepon_id){
	$db=get_conn();
	$sql = sprintf("SELECT * from  `twmap`.`map` WHERE `uid`= '%s' AND `keepon_id`=%d",$uid,$keepon_id);
	$rs = $db->GetAll($sql);
	if (count($rs) == 0 ) return false;
	return $rs[0];
	//$res = mysql_query($sql);
	//if (!$res) return false;
	//$row = mysql_fetch_array($res);
	//if ($row===false) return false;
	//return $row;
}
// 寫到 map table
function map_add($uid,$title,$startx,$starty,$shiftx,$shifty,$px,$py,$host="localhost",$file,$size=0,$version=1,$gpx=0,$keepon_id=0) {

	// 若不是 keepon 來的, 檢查是否已經有同樣參數的地圖,有的話表示是重新產生
	// 不更新 mid, 只更新 size, version, title, cdate, flag 等參數
	$row = map_exists($uid,$startx,$starty,$shiftx,$shifty,$version,$gpx);
	$db=get_conn();
	if ($row === FALSE || $keepon_id != 0 ) {
		// 新地圖
		$sql = sprintf("INSERT INTO `twmap`.`map` (`mid`,`uid`,`cdate`,`host`,`title`,`locX`,`locY`,`shiftX`,`shiftY`,`pageX`,`pageY`,`filename`, `size`,`version`,`gpx`,`keepon_id`) VALUES (NULL, %d, CURRENT_TIMESTAMP, '%s', '%s', %d, %d, %d, %d, %d, %d, '%s', %d, %d, %d, %s)", $uid, $host, $title, $startx, $starty, $shiftx, $shifty, $px, $py, $file, $size, $version,$gpx,($keepon_id==0)?'NULL':$keepon_id);
		$res = $db->Execute($sql);
		//$res = mysql_query($sql);
		// error_log("sql=".priont_r($sql, true));
		if (!$res) {
			error_log("err sql: $sql");
			return FALSE;
		}
		//return mysql_insert_id();
		return $db->Insert_ID();
	} else {
		// 重新產生的地圖, 連檔名都要更新
		$mid = $row[0];
		$sql = sprintf("UPDATE `twmap`.`map` SET `locX`=%d,`locY`=%d,`shiftX`=%d,`shiftY`=%d,`size`=%d,`flag`=0,`cdate`=CURRENT_TIMESTAMP,`title`='%s',`version`=%d, `filename`='%s', `gpx`=%d WHERE `mid`=%d",$startx, $starty, $shiftx, $shifty, $size,$title,$version,$file, $gpx, $mid);
		//$res = mysql_query($sql);
		$res = $db->Execute($sql);
		error_log("update mid sql=$sql");
		if (!$res) return FALSE;
		return $mid;
	}
}
// 取出所有 uid 產生的地圖
function map_get($uid) {
	$db=get_conn();
	$sql = "select * from `twmap`.`map` where `uid`=$uid";
	return $db->GetAll($sql);
	//$res = mysql_query($sql);
	//error_log($sql);
	//if ($res === false ) return FALSE;
	//while($row = mysql_fetch_array($res, MYSQL_ASSOC)){
	//		$result[] = $row;
	//	}
	//return $result;

}
// 取 ok, expired  flag = 0 or 1 的地圖, 用來算限制
function map_list_get($uid) {
	$db=get_conn();
	$sql = "select * from `twmap`.`map` where `uid`=$uid AND (`flag` = 1 or `flag` = 0)";
	return $db->GetAll($sql);
}
function map_list_count($uid) {
	$db=get_conn();
	$sql = "select count(*) from `twmap`.`map` where `uid`=$uid AND (`flag` = 1 or `flag` = 0)";
	$row = $db->GetAll($sql);
	//error_log("$sql" . print_r($row, true));
	return $row[0][0];
}
// 只取 ok 的, 用在 recreate
function map_get_ok($uid) {
	$db=get_conn();
	$sql = "select * from `twmap`.`map` where `uid`=$uid AND `flag` = 0";
	return $db->GetAll($sql);
	/*
	$res = mysql_query($sql);
	//error_log($sql);
	if (!$res) return FALSE;
	$result=array();
	while($row = mysql_fetch_array($res, MYSQL_ASSOC)){
		$result[] = $row;
	}
	return $result;
	 */
}
// 是否地圖滿了?
function map_full($uid,$limit,$mapexist=0) {
	if ($mapexist==1) { // 重新產生
		$t=map_get_ok($uid);
	} else {
		$t=map_list_get($uid);
	}
	if (count($t) >= $limit) {
		return true;
	}
	return false;
}
// 只取一張地圖
function map_get_single($mid){
	$db=get_conn();
	$sql = "select * from `twmap`.`map` where `mid`=$mid";
	$res = $db->GetAll($sql);
	error_log(print_r($res,true));
	return $res[0];
	/*
	$res = mysql_query($sql);
	if (!$res) return FALSE;
	return mysql_fetch_array($res, MYSQL_ASSOC);
	 */
}
function map_accessed($mid) {
	$db=get_conn();
	$sql = "update `twmap`.`map` SET `count`=`count`+1 where `mid`=$mid";
	return $db->Execute($sql);
	/*
	$res = mysql_query($sql);
	if (!$res) return FALSE;
	return true;
	 */
}
function map_get_hot($num) {
	$db=get_conn();
	$sql = "SELECT * FROM `map` WHERE flag !=2  and host != \"210.59.147.226\" and count > 0 order by count desc limit 0, $num";
	return $db->GetAll($sql);
	/*
	$res = mysql_query($sql);
	//error_log($sql);
	if (!$res) return FALSE;
	while($row = mysql_fetch_array($res, MYSQL_ASSOC)){
		$result[] = $row;
	}
	return $result;
	 */
}
function map_get_gpx($num) {
	return map_get_lastest($num,1);
}

function map_get_lastest($num,$gpx=0) {
	$db = get_conn();
	$where = "and gpx = $gpx";
	$sql = "SELECT * FROM `map` WHERE flag=0 and count > 0 $where order by cdate desc limit 0, $num";
	return $db->GetAll($sql);
	/*
	$res = mysql_query($sql);
	//error_log($sql);
	if (!$res) return FALSE;
	$result = array();
	while($row = mysql_fetch_array($res, MYSQL_ASSOC)){
		$result[] = $row;
	}
	return $result;
	 */
}
function map_get_lastest_by_uid($num,$uid) {
	if ($uid == 0 ) return null;
	$db=get_conn();
	$where = "and uid=$uid ";

	$sql = "SELECT * FROM `map` WHERE flag=0 $where order by cdate desc limit 0, $num";
	return $db->GetAll($sql);
	/*
	$res = mysql_query($sql);
	//error_log($sql);
	if (!$res) return FALSE;
	$result = array();
	while($row = mysql_fetch_array($res, MYSQL_ASSOC)){
		$result[] = $row;
	}
	return $result;
	 */
}

// map files
function map_files($outimage) {
	// 可能是 -v1.tag.png or -v3.tag.png, 或者沒有
	$out_prefix = str_replace(".tag.png","",basename($outimage));
	if (preg_match("/^(\d+x\d+\-\d+x\d+).*/",basename($outimage), $regs)) {
		//	$files = glob( dirname($outimage) ."/".$regs[1] . "*");
		$glob_pattern = dirname($outimage) . "/" . $out_prefix . "*";
		$files = glob( $glob_pattern );
		// error_log("$glob_pattern => ".print_r($files, true));
		sort($files);
		return $files;
	}
	return null;
}
/**
 * map_file_exists 
 *  dir structure
 * @param mixed $outimage 
 * @param mixed $ftype 
 * @access public
 * @return void
 */
function map_file_exists($outimage, $ftype) {
	return file_exists(map_file_name($outimage, $ftype));
}
function map_file_name($outimage, $ftype) {
	switch($ftype) {
	case 'pdf':
		$fname = str_replace(".tag.png",".pdf",$outimage);
		break;
	case 'kmz':
		$fname = str_replace(".png",".kmz",$outimage);
		break;
	case 'txt':
		$fname = str_replace(".tag.png",".txt",$outimage);
		break;
	case 'gpx':
		$fname = str_replace(".tag.png",".gpx",$outimage);
		break;
	case 'image':
		$fname = $outimage;
		break;
	}
	return $fname;

}
// 建立好之後,改變 structure 到 uid/mid/files
function map_migrate($root,$uid,$mid) {
	// 0. 檢查是新的結構?
	$dir = sprintf("%s/%06d/%d",$root,$uid,$mid);
	// if (file_exists($dir) && is_dir($dir)) return true;
	// 1. 建立目錄
	@mkdir($dir,0755,true); 
	$row = map_get_single($mid);
	// 檢查檔案是否在正確目錄
	$newfilename = sprintf("%s/%s",$dir, basename($row['filename']));
	if ($row['filename'] == $newfilename ) {
		return true;
	}
	map_block($root,$uid,1);
	$files = map_files($row['filename']);
	//$files = map_files(sprintf("%s/%06d/%s",$root,$uid,basename($row['filename'])));
	// 2. 搬移檔案
	foreach($files as $f) {
		$cmd = "/bin/mv $f $dir";
		exec($cmd);
		error_log("migrate $mid:$cmd");
	}
	//$newfilename = sprintf("%s/%s",$dir, basename($row['filename']));
	// 3. 更新資料庫
	$db=get_conn();
	$sql = "update `twmap`.`map` set `filename` = '$newfilename' where `mid` = $mid";
	//$res = mysql_query($sql);
	$rs = $db->Execute($sql);
	error_log("migrate $mid:$sql");
	map_block($root,$uid,0);
	return true;

}
// 檢查是否動作: 刪除/新增 不准做
function map_blocked($root, $uid) {
	$blockfile = sprintf("%s/%06d/.block",$root,$uid);

	if (file_exists($blockfile)){
		return "出圖或資料結構更新中..請稍候再試";
	}
	return null;
}
function map_block($root, $uid, $action=1) {
	$blockfile = sprintf("%s/%06d/.block",$root,$uid);
	if ($action == 1 ) {
		$ret = touch($blockfile);
	} else {
		$ret = unlink($blockfile);
	}
	return $ret;
}
function map_size($outimage) {
	$total = 0;
	$files = map_files($outimage);
	if ($files == null ) return 0;
	foreach($files as $f) {
		$total += filesize($f);
	}
	return $total;
}
// delete map file, db entry AND disk files
function map_del($mid) {
	$row = map_get_single($mid);
	if ($row === FALSE) return FALSE;
	// remove files
	$files = map_files($row['filename']);
	foreach($files as $f) {
		$ret = unlink($f);
		if ($ret === false ) return false;
	}
	// update db
	$db=get_conn();
	$sql = "update `twmap`.`map` set `flag` = 2, `size`= 0, `ddate`=NOW()  where `mid` = $mid";
	return $db->Execute($sql);
	/*
	$res = mysql_query($sql);
	if (!$res) return FALSE;
	return true;
	 */
}
function map_expire($mid) {
	$row = map_get_single($mid);
	if ($row === FALSE) return FALSE;
	// $sql = "delete from `twmap`.`map` where `mid`=$mid";
	// remove files
	$files = map_files($row['filename']);
	foreach($files as $f) {
		// 不刪除 gpx 檔案
		if(strstr(basename($f),'.gpx')) continue;
		$ret = unlink($f);
		if ($ret === false ) {
			return false;
		}
	}
	if ($row['keepon_id'] > 0 ) {
		soap_call_delete($row['keepon_id']);
	}
	$db=get_conn();
	// update db
	$sql = "update `twmap`.`map` set `flag` = 1,`size`=0  where `mid` = $mid";
	return $db->Execute($sql);
	/*
	$res = mysql_query($sql);
	if (!$res) return FALSE;
	return true;
	 */
}
function get_old_maps($days) {
	//$sql = sprintf("select * from `twmap`.`map` where flag = 0 AND TIME_TO_SEC(TIMEDIFF(NOW(),`cdate`))> %d",$howlong);
	// uid == 1 是 keepon 
	$tdiff = time() - $days*86400;
	$db=get_conn();
	$sql = sprintf("select * from `twmap`.`map` where flag = 0 AND unix_timestamp(`cdate`) < %s  and `count` < 100 and size > 10240000",$tdiff);
	return $db->GetAll($sql);
	/*
	$res = mysql_query($sql);
	if (!$res) return FALSE;
	$result = array();
	while($row = mysql_fetch_array($res, MYSQL_ASSOC)){
		$result[] = $row;
	}
	echo $sql . "\ncount=". count($result) ."\n";
	return $result;
	 */
}
function do_expire($days = 180, $realdo = 0) {
	$maps = get_old_maps($days);
	$size = 0;
	foreach($maps as $map) {
		$size += $map['size'];
		if ($realdo == 1 )
			map_expire($map['mid']);
	}
	// expire how many maps, and how much space freed
	return array(count($maps),$size);

}
function map_totalsize() {
	$db=get_conn();
	$sql = "select sum(size) as totalsize from `twmap`.`map`";
	$res = $db->GetAll($sql);
	return $res[0]['totalsize'];
	/*
	$res = mysql_query($sql);
	$row = mysql_fetch_array($res);
	return $row[0];
	 */
}
function mrtg($type) {
	switch($type) {
	case 'disk':
		$size = map_totalsize();
		return array($size);
		break;
	case 'map':
	default:
		$sql = sprintf("SELECT *
			FROM `twmap`.`map`
			WHERE TIME_TO_SEC( timediff( NOW( ) , cdate ) ) < %d
			OR TIME_TO_SEC( timediff( NOW( ) , ddate ) ) < %d", 300,300);
		$res = mysql_query($sql);
		$c=0;$d=0;
		while($row = mysql_fetch_array($res, MYSQL_ASSOC)){
			if ($row['flag'] == 2) $d++;
			else if ($row['flag'] == 0) $c++;
		}
		return array($c,$d);
		break;

	}

}
function stats() {
	$size = 0;
	$total_maps = 0;
	$maxmid = 0;
	$db=get_conn();
	$sql = "select size,mid from `twmap`.`map` where flag <> 2";
	$rs = $db->GetAll($sql);
	foreach($rs as $row) {
		$size+=$row['size'];
		if ($row['mid'] > $maxmid) $maxmid = $row['mid'];
		$total_maps++;
	}
	/*
	$res = mysql_query($sql);
	$size = 0;
	$total_maps = 0;
	$maxmid = 0;
	while($row = mysql_fetch_array($res, MYSQL_ASSOC)){
		$size+=$row['size'];
		if ($row['mid'] > $maxmid) $maxmid = $row['mid'];
		$total_maps++;

	}
	 */
	$sql = "select count(distinct(uid)) as num_user  from `twmap`.`map`";
	$rs = $db->GetAll($sql);
	$active_users = $rs[0]['num_user'];
	/*
	$res = mysql_query($sql);
	$row = mysql_fetch_row($res);
	$active_users = $row[0];
	 */
	$sql = "select count(*) from `twmap`.`user`";
	$rs = $db->GetAll($sql);
	$registerred_users = $rs[0][0];

	/*
	$res = mysql_query($sql);
	$row = mysql_fetch_row($res);
	$registerred_users = $row[0];
	 */
	return array($total_maps, $size, $registerred_users, $active_users, $maxmid);
}

function humanreadable($size)
{
	$size = floatval($size);
	$names = array('B', 'KB', 'MB', 'GB', 'TB');
	$times = 0;
	while($size>1024)
	{
		$size = round(($size*100)/1024)/100;
		$times++;
	}
	return "$size " . $names[$times];
} //function humanreadable($size)

// UI

function hot_block($type=0, $target="_blank") {
	switch($type) {
		//case 1:
		//	$maps = map_get_lastest(30);
		//	$size = "1.0em";
		//	$name = "最新地圖";
		//	break;
	case 1:
		$maps1 = map_get_gpx(15);
		$maps2 = map_get_lastest(20);
		$maps = array_merge($maps1,$maps2);
		$size = "1.0em";
		$name = "最新地圖(gpx)";
		break;
	default:
		$maps = map_get_hot(20);
		$size = "1.2em";
		$name = "熱門地圖";
		break;
	}
	$ret[0] = "<table style='width: 330px; '><tr>";
	$ret[] = "<th>$name<tr><td>";
	//error_log(print_r($maps, true));
	foreach($maps as $map) {
		$link = sprintf("show.php?mid=%d&info=%dx%d-%dx%d",$map['mid'],$map['locX'],$map['locY'],$map['shiftX'],$map['shiftY']);
		if ($map['gpx'] == 1 ) {
			$ret[] = sprintf("<img src='%s' /><img src='%s' /><a href='%s' target=%s style='font-size: %s'>%s</a>&nbsp;",name_to_icon($map), "imgs/gpx.png",$link,$target,$size,$map['title']);
			// error_log("mid=".$map['mid']);
		}
		else
			$ret[] = sprintf("<img src='%s' /><a href='%s' target=%s style='font-size: %s'>%s</a>&nbsp;",name_to_icon($map),$link,$target,$size,$map['title']);
	}
	$ret[] = "</table>";
	return implode("\n",$ret);
}
function name_to_icon($map) {
	$name = $map['title'];
	if (!empty($map['keepon_id']))
		return 'icons/note.gif';
	if (strstr($name,"湖")|| strstr($name,"溪")||strstr($name,"潭")||strstr($name,"島")|| strstr($name,"海岸")) 
		$img="icons/fish.gif";
	else if (strstr($name,"林道")|| strstr($name,"縱走"))
		$img="icons/logging.gif";
	else if (strstr($name,"山")||strstr($name,"洞")||strstr($name,"稜")) 
		$img="icons/scat.gif";
	else if (strstr($name,"溫泉")||strstr($name,"湯")) 
		$img="icons/hotspring.gif";
	else
		$img="icons/angel.gif";
	return $img;
}
// keepon functions

function kok_out($id, $msg, $url, $cdate=null) {
	soap_call(true, $id, $msg, $url, $cdate);
}

function kerror_out($id,$msg) {
	soap_call(false, $id, $msg);
}
function soap_call($success, $id, $msg, $url=null, $cdate =null) {
	//建立SOAP
	// URL = http://www.keepon.com.tw/KeeponWS/Service1.asmx
	$soap = new SoapClient("http://www.keepon.com.tw/KeeponWebService.asmx?WSDL");
	//
	//    // 變數名稱必需與Web Service的變數名稱相同
	$params = array(

		'Success'=> $success,
		'Identity'=> $id,
		'Date'=> ($cdate)? $cdate : date("Y-m-d H:i:s"),
		'ImageUrl'=> $url,
		'Message'=>$msg
	);

	try {	//                              //呼叫 MapResult 傳入$params

		$result = $soap->MapResult($params);
		//取得回傳值
		kcli_msglog(array($params,$result));
		return array(true, $result);

	} catch (SoapFault $exception) {
		//
		kcli_msglog(array($params,"expection: $exception"));
		return array(false,$exception);
	}
}
function soap_call_delete($id) {
	$soap = new SoapClient("http://www.keepon.com.tw/KeeponWebService.asmx?WSDL");
	$params = array(
		'Identity'=> $id );
	try {
		$result = $soap->MapDelete($params);
		kcli_msglog(array($params,$result));
		return array(true, $result);
	} catch (SoapFault $exception) {
		//
		kcli_msglog(array($params,"expection: $exception"));
		return array(false,$exception);
	} 

}
function kcli_msglog($msg){
	if (is_array($msg))
		$str = print_r($msg, true);
	else
		$str = $msg;
	syslog(LOG_INFO, $str);
	printf("%s\n",$str);
}
function ajaxerr($msg) {
	$ret['ok'] = false;
	$ret['rsp'] = array('msg' => $msg );
	header('Content-Type: application/json');
	echo json_encode($ret);
	exit(0);
}


function ajaxok($response) {
	$ret['ok'] = true;
	$ret['rsp'] = $response;
	header('Content-Type: application/json');
	echo json_encode($ret);
	exit(0);
}
/**
 *  四個角落點只要在裡面就算 互相
 */
function map_overlap($bounds, $gpx=1, $max=0){
	// 四個端點  航跡圖的範圍落在 viewport  或者 viewport 落在航跡圖範圍裡頭
	 /*
	$sql = sprintf("SELECT * FROM `map` WHERE gpx=%d and (((locX BETWEEN %s AND %s) AND ((locY BETWEEN %s AND %s) OR (locY-shiftY*1000 BETWEEN %s AND %s))) OR ((locX+shiftX*1000 BETWEEN %s AND %s) AND ((locY BETWEEN %s AND %s) OR (locY-shiftY*1000 BETWEEN %s AND %s))) OR ((%s BETWEEN  locX AND locX+shiftX*1000 AND %s BETWEEN locY-shiftY*1000 AND locY) OR (%s BETWEEN  locX AND locX+shiftX*1000 AND %s BETWEEN locY-shiftY*1000 AND locY) OR 
		(%s BETWEEN  locX AND locX+shiftX*1000 AND %s BETWEEN locY-shiftY*1000 AND locY) OR (%s BETWEEN  locX AND locX+shiftX*1000 AND %s BETWEEN locY-shiftY*1000 AND locY)))", 
		$gpx, $bounds['tlx'],$bounds['brx'],$bounds['bry'],$bounds['tly'], $bounds['bry'],$bounds['tly'], $bounds['tlx'],$bounds['brx'], $bounds['bry'], $bounds['tly'], $bounds['bry'], $bounds['tly'] ,
		$bounds['tlx'],$bounds['tly'],$bounds['brx'],$bounds['bry'],$bounds['tlx'],$bounds['bry'],$bounds['brx'],$bounds['tly']);

		*/
	// 聰明的條件滿足
	//http://stackoverflow.com/questions/306316/determine-if-two-rectangles-overlap-each-other
	//return ((R1.BR.y <= R2.TL.y)
	//(R1.BR.x >= R2.TL.x) &&
	//(R1.TL.x <= R2.BR.x) &&
	//(R1.TL.y >= R2.BR.y) && 
	$db=get_conn();
	$sql = sprintf("SELECT * FROM `map` WHERE gpx=%d and ( locX < %s and locX+shiftX*1000 > %s and locY > %s  and locY-shiftY*1000 < %s)", $gpx, $bounds['brx'], $bounds['tlx'],$bounds['bry'],$bounds['tly']);

	if ($max > 0 ) {
		$sql .= " LIMIT $max";
	}
	//$res = mysql_query($sql);
	$res = $db->GetAll($sql);
	return $res;
	/*
	if (!$res) {
		error_log("err sql:".$sql);
		return FALSE;
	}
	$result = array();
	while($row = mysql_fetch_array($res, MYSQL_ASSOC)){
		$result[] = $row;
	}
	//error_log("$sql " . count($result) );
	return $result;
	 */
}

/**
 * geocoder 
 * 存取 geocoder table
 * @param mixed $op 
 * @param mixed $data 
 * @access public
 * @return void
 */
function geocoder($op, $data) {
	 $db=get_conn();
	 switch($op) {
	 case 'get':
		 $sql = sprintf("select * from `twmap`.`geocoder` where `address`='%s'",$data['address']);
		 $res = $db->GetAll($sql);
		 if (count($res) == 1)
			return array(1, $res[0]);
		 else if ($res === false)
			 return array(-1, "error");
		 else
			 return array(0, 'no result' . $sql);
		 break;
	 case 'set':
		 list ($ret, $msg )= geocoder('get', array('address' => $data['address']));
		 if ($ret == 0)
			 $sql = sprintf("insert into `twmap`.`geocoder` (`address`,`lat`,`lng`,`is_tw`,`exact`,`faddr`,`name`) values ('%s',%f,%f,%d,%d,'%s','%s')",$data['address'],$data['lat'],$data['lng'],$data['is_tw'],$data['exact'],$data['faddr'],$data['name']);
		 else if ($ret == 1)
			 $sql = sprintf("update `twmap`.`geocoder` set `address`='%s', `lat`=%f, `lng`=%d, `is_tw`=%d, `exact`=%d, `faddr`='%s', `name`='%s'",$data['address'],$data['lat'],$data['lng'],$data['is_tw'],$data['exact'],$data['faddr'],$data['name']);
		 else
			 return array($ret, $msg);
		 $res = $db->Execute($sql);
		 if ($res == true )
			 return array(1,"ok $sql");
		 break;
	 }
	 return array(0,"no set / get ");
}
