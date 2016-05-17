<?php
// 當 login 之後, 必須註冊到 db 裡面
// $Id: twmapdb.inc.php 356 2013-09-14 10:00:22Z happyman $
//
require_once("adodb5/adodb.inc.php");

function get_conn() {
	global $db_host, $db_conn,$db_user, $db_pass, $db_name;

	if ($db_conn != null && $db_conn->IsConnected())
		return $db_conn;

	// error_log("new conection");
    // db_conn = ADONewConnection('mysqli');
	//$status = $db_conn->PConnect('localhost', $db_user, $db_pass, $db_name);
	$db_conn = ADONewConnection('postgres9');
	$status = $db_conn->PConnect($db_host, $db_user, $db_pass, $db_name);

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
function logsql($sql,$rs){
	$debug = 0;
	if ($debug == 0 ) return;
	$trace = getCallingFunctionName(true);
	if ($rs===false){
		$msg = "return FALSE";
	} else if (empty($rs)) {
		$msg= "return EMPTY";
	} else {
		$msg= "return ok" . print_r($rs,true);
	}
	error_log("$trace run $sql". $msg);
}
function fetch_user($mylogin) {
	$sql = sprintf("select * from \"user\" where email='%s' and type='%s'", $mylogin['email'],$mylogin['type']);
	$db = get_conn();
	$rs = $db->GetAll($sql);
	logsql($sql,$rs);
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
		$sql = sprintf("INSERT INTO \"user\" (\"uid\", \"email\", \"type\", \"name\", \"limit\", \"cdate\", \"login\") VALUES (DEFAULT, '%s', '%s', '%s',  30, CURRENT_TIMESTAMP, 1)", $mylogin['email'],$mylogin['type'],$mylogin['nick']);
		$rs = $db->Execute($sql);
		logsql($sql,$rs);
	} else {
		// 新增 counter
		$sql = sprintf("update \"user\" SET \"login\"=%d, \"name\"='%s' WHERE \"uid\"=%d",$row['login']+1, pg_escape_string($mylogin['nick']),$row['uid']);
		//$res = mysql_query($sql);
		$rs = $db->Execute($sql);
		logsql($sql,$rs);
	}
	// 是否加上 login record ?
	//
	return fetch_user($mylogin);
}
function map_exists($uid,$startx,$starty,$shiftx,$shifty,$version,$gpx=0) {
	$db = get_conn();
	$sql = sprintf("SELECT \"mid\" from \"map\" WHERE \"uid\"='%s' AND \"locX\"=%d AND \"locY\"=%d AND \"shiftX\"=%d and \"shiftY\"=%d and \"version\"=%d and \"gpx\"=%d",$uid,$startx,$starty,$shiftx,$shifty,$version,$gpx);
	$rs = $db->GetAll($sql);
	logsql($sql,$rs);
	if (count($rs) == 0 ) return false;
	return $rs[0];
}
function keepon_map_exists($uid,$keepon_id){
	$db=get_conn();
	$sql = sprintf("SELECT * from  \"map\" WHERE \"uid\"='%s' AND \"keepon_id\"='%s' AND \"flag\" <> 2",$uid,$keepon_id);
	$rs = $db->GetAll($sql);
	logsql($sql,$rs);
	if (count($rs) == 0 ) return false;
	return $rs[0];
}
function keepon_map_exists2($uid,$mid){
	$db=get_conn();
	$sql = sprintf("SELECT * from  \"map\" WHERE \"uid\"='%s' AND \"mid\"='%s' AND \"flag\" <> 2",$uid,$mid);
	$rs = $db->GetAll($sql);
	logsql($sql,$rs);
	if (count($rs) == 0 ) return false;
	return $rs[0];
}
// TODO? maybe still buggy
function is_gpx_imported($mid) {
	$db=get_conn();
	$row = map_get_single($mid);
	$title = str_replace("-GPX自動轉檔","%",$row['title']);
	$sql = sprintf("SELECT \"mid\",\"title\" FROM \"map\" WHERE \"mid\" IN (SELECT DISTINCT \"mid\" FROM \"gpx_wp\") AND \"mid\" <> %d AND \"locX\"=%d AND \"locY\"=%d AND \"shiftX\"=%d and \"shiftY\"=%d and \"version\"=%d and \"gpx\"=1 AND \"title\" LIKE '%s'",$mid, $row['locX'],$row['locY'],$row['shiftX'],$row['shiftY'],$row['version'],pg_escape_string($title));
	$rs = $db->GetAll($sql);
	logsql($sql,$rs);
	if (count($rs) == 0 ) return false;
	return $rs[0];
}
// 寫到 map table
function map_add($uid,$title,$startx,$starty,$shiftx,$shifty,$px,$py,$host="localhost",$file,$size=0,$version=1,$gpx=0,$keepon_id=NULL) {

	// 若不是 keepon 來的, 檢查是否已經有同樣參數的地圖,有的話表示是重新產生
	// 不更新 mid, 只更新 size, version, title, cdate, flag 等參數
	$row = map_exists($uid,$startx,$starty,$shiftx,$shifty,$version,$gpx);
	$db=get_conn();
	if ($row === FALSE || $keepon_id != NULL ) {
		// 新地圖
		// 使用 postgresql 要改 default
		$sql = sprintf("INSERT INTO \"map\" (\"mid\",\"uid\",\"cdate\",\"host\",\"title\",\"locX\",\"locY\",\"shiftX\",\"shiftY\",\"pageX\",\"pageY\",\"filename\",\"size\",\"version\",\"gpx\",\"keepon_id\") VALUES (DEFAULT, %d, CURRENT_TIMESTAMP, '%s', '%s', %d, %d, %d, %d, %d, %d, '%s', %d, %d, %d, '%s') returning mid", $uid, $host, $title, $startx, $starty, $shiftx, $shifty, $px, $py, $file, $size, $version,$gpx,($keepon_id==NULL)?'NULL':$keepon_id);
		$rs = $db->getAll($sql);
		logsql($sql,$rs);
		if (!isset($rs[0]['mid'])) {
			//error_log("err sql: $sql");
			return FALSE;
		}
		//return $db->Insert_ID();
		return $rs[0]['mid'];
	} else {
		// 重新產生的地圖, 連檔名都要更新
		$mid = $row[0];
		$sql = sprintf("UPDATE \"map\" SET \"locX\"=%d,\"locY\"=%d,\"shiftX\"=%d,\"shiftY\"=%d,\"size\"=%d,\"flag\"=0,\"cdate\"=CURRENT_TIMESTAMP,\"title\"='%s',\"version\"=%d,\"filename\"='%s',\"gpx\"=%d WHERE \"mid\"=%d",$startx, $starty, $shiftx, $shifty, $size,$title,$version,$file, $gpx, $mid);
		$rs = $db->Execute($sql);
		logsql($sql,$rs);
		if (!$rs) return FALSE;
		return $mid;
	}
}
// 取出所有 uid 產生的地圖
function map_get($uid) {
	$db=get_conn();
	$sql = sprintf("select * from \"map\" where uid=%d",$uid);
	$rs=$db->GetAll($sql);
	logsql($sql,$rs);
	return $rs;
}
// 取 ok, expired  flag = 0 or 1 的地圖, 用來算限制
function map_list_get($uid) {
	$db=get_conn();
	$sql = sprintf("select * from \"map\" where \"uid\"=%s AND (flag = 1 or flag = 0) ORDER BY mid",$uid);
	$rs = $db->GetAll($sql);
	logsql($sql,$rs);
	return $rs;
}
function map_list_count($uid) {
	$db=get_conn();
	$sql = sprintf("select count(*) from \"map\" WHERE \"uid\"=%d AND (flag = 1 or flag = 0)",$uid);
	$row = $db->GetAll($sql);
	logsql($sql,$row);
	return $row[0][0];
}
// 只取 ok 的, 用在 recreate
function map_get_ok($uid) {
	$db=get_conn();
	$sql = sprintf("select * from \"map\" WHERE \"uid\"=%d AND flag=0",$uid);
	$rs = $db->GetAll($sql);
	logsql($sql,$rs);
	return $rs;

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
	$sql = sprintf("select * from \"map\" WHERE \"mid\"=%d",$mid);
	$res = $db->GetAll($sql);
	logsql($sql,$res);
	if (count($res) == 0)
		return null;
	else
		return $res[0];
}
function map_accessed($mid) {
	$db=get_conn();
	$sql = sprintf("update \"map\" SET \"count\"=\"count\"+1 WHERE \"mid\"=%s",$mid);
	$rs = $db->Execute($sql);
	logsql($sql,$rs);
	return $rs;
}
function map_get_hot($num) {
	$db=get_conn();
	$sql = sprintf("SELECT * FROM \"map\" WHERE \"flag\" !=2  AND \"host\" != '210.59.147.226' and \"count\" > 0 ORDER BY \"count\" DESC LIMIT %d",$num);
	$rs =$db->GetAll($sql);
	logsql($sql,$rs);
	return $rs;
}
function map_get_gpx($num) {
	return map_get_lastest($num,1);
}

function map_get_lastest($num,$gpx=0) {
	$db = get_conn();
	$where = "and gpx = $gpx";
	// postgresql use LIMIT num OFFSET 0
	//  MySQL use LIMIT 0 num
	$sql = sprintf("SELECT * FROM \"map\" WHERE \"flag\"=0 and \"count\" > 0 %s ORDER BY \"cdate\" DESC LIMIT %d",$where,$num);
	$rs =$db->GetAll($sql);
	logsql($sql,$rs);
	return $rs;

}
function map_get_lastest_by_uid($num,$uid) {
	if ($uid == 0 ) return null;
	$db=get_conn();
	$where = "AND \"uid\"=$uid";
	$sql = sprintf("SELECT * FROM \"map\" WHERE \"flag\"=0 %s ORDER BY \"cdate\" DESC LIMIT %d",$where,$num);
	$rs = $db->GetAll($sql);
	logsql($sql,$rs);
	return $rs;
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
	if ($row == false) return false;
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
	$sql = sprintf("update \"map\" set \"filename\"='%s' WHERE \"mid\" = %d",pg_escape_string($newfilename),$mid);
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
	if ($row === null) return FALSE;
	// remove files
	$files = map_files($row['filename']);
	foreach($files as $f) {
		$ret = unlink($f);
		if ($ret === false ) return false;
	}
	// update db
	$db=get_conn();
	$sql = sprintf("update \"map\" set \"flag\" = 2, \"size\"= 0, \"ddate\"=NOW()  WHERE \"mid\" = %d",$mid);
	$rs =$db->Execute($sql);
	logsql($sql,$rs);
	return $rs;
}
function map_expire($mid) {
	$row = map_get_single($mid);
	if ($row === FALSE) return FALSE;
	// $sql = "delete from map where mid=$mid";
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
	if (!empty($row['keepon_id']) && $row['keepon_id'] != 'NULL') {
		//soap_call_delete($row['keepon_id']);
		keepon_MapDelete($row['keepon_id']);
	}
	$db=get_conn();
	// update db
	$sql = sprintf("UPDATE \"map\" set \"flag\" = 1,\"size\"=0  WHERE \"mid\" = %d",$mid);
	$rs= $db->Execute($sql);
	logsql($sql,$rs);
	return $rs;

}
function get_old_maps($days) {
	//$sql = sprintf("select * from map where flag = 0 AND TIME_TO_SEC(TIMEDIFF(NOW(),cdate))> %d",$howlong);
	// uid == 1 是 keepon
	$tdiff = time() - $days*86400;
	$db=get_conn();
	// 刪除 days 天之前的地圖, 不管大小了
	$sql = sprintf("select * from \"map\" WHERE \"flag\" = 0 AND EXTRACT(EPOCH FROM cdate) < %s  and count < 100 and \"uid\" != 3 ",$tdiff);
	$rs= $db->GetAll($sql);
	logsql($sql,$rs);
	return $rs;

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
	$sql = sprintf("select sum(size) as totalsize from \"map\"");
	$res = $db->GetAll($sql);
	logsql($sql,$res);
	return $res[0]['totalsize'];
}
// still broken
function mrtg($type) {
	switch($type) {
		case 'disk':
			$size = map_totalsize();
			return array($size);
			break;
		case 'map':
		default:
			$sql = sprintf("SELECT *
					FROM map
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
	$sql = sprintf("select size,mid from \"map\" where flag <> 2");
	$rs = $db->GetAll($sql);
	logsql($sql,$rs);
	foreach($rs as $row) {
		$size+=$row['size'];
		if ($row['mid'] > $maxmid) $maxmid = $row['mid'];
		$total_maps++;
	}

	$sql = sprintf("select count(distinct(uid)) as num_user  from \"map\"");
	$rs = $db->GetAll($sql);
	logsql($sql,$rs);
	$active_users = $rs[0]['num_user'];
	/*
	   $res = mysql_query($sql);
	   $row = mysql_fetch_row($res);
	   $active_users = $row[0];
	 */
	$sql = sprintf("select count(*) as count from \"user\"");
	$rs2 = $db->GetAll($sql);
	logsql($sql,$rs2);
	$all_users = intval($rs2[0]['count']);

	return array($total_maps, $size, $all_users, $active_users, $maxmid);
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
		return 'icons/boobies.gif';
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
	//soap_call(true, $id, $msg, $url, $cdate);
	keepon_MapResult(1, $id, $msg, $url, $cdate);
}
// 取代 soap_call
function kerror_out($id,$msg) {
	//soap_call(false, $id, $msg);
	keepon_MapResult(0, $id, $msg);
}
function keepon_MapResult($success, $id, $msg, $url=null, $cdate=null) {
	$kurl = "http://www.keepon.com.tw/api/MapGenerator/MapResult";
	$params = array(

			'Success'=> $success,
			'Identity'=> $id,
			'Date'=> ($cdate)? $cdate : date("Y-m-d H:i:s"),
			'ImageUrl'=> $url,
			'Message'=>$msg
		       );
	$result = request_curl($kurl, "POSTJSON", $params);
	//error_log("request $kurl with params".print_r($params,true) ."get $result");
	kcli_msglog("request $kurl with params".print_r($params,true) ."get $result");
	return array(true, $result);

}
function keepon_MapDelete($id) {
	$kurl = "http://www.keepon.com.tw/api/MapGenerator/MapDelete";
	$params = array(
			'Identity'=> $id );
	$result = request_curl($kurl, "POSTJSON", $params);
	error_log("request $kurl with params".print_r($params,true) ."get $result");
	kcli_msglog("request $kurl with params".print_r($params,true) ."get $result");
	return array(true, $result);

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
	   $sql = sprintf("SELECT * FROM map WHERE gpx=%d and (((locX BETWEEN %s AND %s) AND ((locY BETWEEN %s AND %s) OR (locY-shiftY*1000 BETWEEN %s AND %s))) OR ((locX+shiftX*1000 BETWEEN %s AND %s) AND ((locY BETWEEN %s AND %s) OR (locY-shiftY*1000 BETWEEN %s AND %s))) OR ((%s BETWEEN  locX AND locX+shiftX*1000 AND %s BETWEEN locY-shiftY*1000 AND locY) OR (%s BETWEEN  locX AND locX+shiftX*1000 AND %s BETWEEN locY-shiftY*1000 AND locY) OR
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
	$sql = sprintf("SELECT * FROM \"map\" WHERE \"flag\" <> 2 and \"gpx\"=%d and ( \"locX\" < %s AND \"locX\"+\"shiftX\"*1000 > %s AND \"locY\" > %s  AND \"locY\"-\"shiftY\"*1000 < %s)", $gpx, $bounds['brx'], $bounds['tlx'],$bounds['bry'],$bounds['tly']);

	if ($max > 0 ) {
		$sql .= " LIMIT $max";
	}
	//$res = mysql_query($sql);
	$res = $db->GetAll($sql);
	logsql($sql,$rs);
	return $res;
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
			$sql = sprintf("select * from \"geocoder\" where address='%s'",$data['address']);
			$res = $db->GetAll($sql);
			logsql($sql,$res);
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
				$sql = sprintf("Insert_ID into \"geocoder\" (\"address\",\"lat\",\"lng\",\"is_tw\",\"exact\",\"faddr\",\"name\") values ('%s',%f,%f,%d,%d,'%s','%s')",$data['address'],$data['lat'],$data['lng'],$data['is_tw'],$data['exact'],$data['faddr'],$data['name']);
			else if ($ret == 1)
				$sql = sprintf("update \"geocoder\" set \"address\"='%s',\"lat\"=%f, \"lng\"=%d, \"is_tw\"=%d, \"exact\"=%d, \"faddr\"='%s', \"name\"='%s'",$data['address'],$data['lat'],$data['lng'],$data['is_tw'],$data['exact'],$data['faddr'],$data['name']);
			else
				return array($ret, $msg);
			$res = $db->Execute($sql);
			logsql($sql,$res);
			if ($res == true )
				return array(1,"ok $sql");
			break;
	}
	return array(0,"no set / get ");
}
function getCallingFunctionName($completeTrace=false) {
	$trace=debug_backtrace();
	$caller=$trace[count($trace)-1];

	if (isset($caller['file'])) {
		$str = $caller['file'] . ' ';
	} else {
		$str = '';
	}
	if($completeTrace) {
		foreach($trace as $caller) {
			// skip unnecessary trace
			if ($caller['function'] == 'getCallingFunctionName' || $caller['function'] == 'doLog')
				continue;
			if (isset($caller['class']))
				$str .= sprintf(" -- Called by %s::%s", $caller['class'], $caller['function']);
			else
				$str .= " -- Called by {$caller['function']}";
		}
	} else {
		$str .= "Called by {$caller['function']}";
		if (isset($caller['class']))
			$str .= " From Class {$caller['class']}";
	}
	return $str;
}
/**
  GIS functions
  depends on mapnik (nik4), gdal (ocr2ocr)
  測試 code:
  因為目前 production server 的 postgres library 太舊, 只好借用別台 172.31.39.193
 */
function ogr2ogr_import_gpx($mid, $gpx_file, $type='waypoints'){
	global $db_name,$db_user,$db_pass,$db_host;
	// 1. 檢查 table 存在與否
	if ($type=='waypoints')
		$table='gpx_wp';
	else
		$table='gpx_trk';
	$db=get_conn();
	$sql = sprintf("SELECT relname FROM pg_class WHERE relname = '%s'",$table);
	$rs = $db->getAll($sql);
	if (isset($rs[0]['relname']) && $rs[0]['relname'] == $table) {
		// 1. delete mid from table (prevent dup)
		$sql = sprintf("DELETE FROM \"%s\" WHERE mid=%s",$table,$mid);
		$db->Execute($sql);
		// 2. add data by ogr2ogr
		//  沒辦法 因為 server 上 postgres library < 9.0 無法使用
		//$cmd = sprintf("ssh 172.31.39.193 'ogr2ogr -update -append -f PostgreSQL \"PG:dbname=%s user=%s password=%s host=%s\" %s -sql \"select %s.*,%d as mid from %s %s\"'",
		$cmd = sprintf("ogr2ogr -update -append -f PostgreSQL \"PG:dbname=%s user=%s password=%s host=%s\" %s -sql \"select %s.*,%d as mid from %s %s\"",
				$db_name,$db_user,$db_pass,$db_host,$gpx_file,$table,$mid,$type,$table);

	} else {
		// 1. append
		//$cmd = sprintf("ssh 172.31.39.193 'ogr2ogr -append -f PostgreSQL \"PG:dbname=%s user=%s password=%s host=%s\" %s -sql \"select %s.*,%d as mid from %s %s\"'",
		$cmd = sprintf("ogr2ogr -append -f PostgreSQL \"PG:dbname=%s user=%s password=%s host=%s\" %s -sql \"select %s.*,%d as mid from %s %s\"",
				$db_name,$db_user,$db_pass,$db_host,$gpx_file,$table,$mid,$type, $table);
	}
	//echo $cmd . "\n";
	exec($cmd,$out,$ret);
	return $ret;
}
/**  do import 
 */
function import_gpx_to_gis($mid){
	// table gpx_waypoints
	global $db_name,$db_user,$db_pass,$db_host;
	// 0. 先檢查 gpx 存在與否
	$row = map_get_single($mid);
	if ($row==null) 
		return array(false, "mid incorrect");
	$gpx_file = map_file_name($row['filename'], 'gpx');
	// check 172.31.39.193 mount path
	// $gpx_file = str_replace("/srv/www/htdocs/","/mnt/nas/",$gpx_file);
	if (!file_exists($gpx_file))
		return array(false, "$gpx_file  not exists");
	$ret1 = ogr2ogr_import_gpx($mid, $gpx_file, 'waypoints');
	$ret2 = ogr2ogr_import_gpx($mid, $gpx_file, 'tracks');
	if ($ret1 == 0 && $ret2 == 0)
		return array(true,"success");
	else
		return array(false,"fail import");
}
function mapnik_svg_gen($tw67_bbox,$background_image_path, $outpath) {
	$tl = proj_67toge($tw67_bbox[0]);
	$br = proj_67toge($tw67_bbox[1]);
	$imgsize = $tw67_bbox[2];
	$tmpsvg = tempnam("/tmp","SVG") . ".svg";
	//$cmd = sprintf('ssh 172.31.39.193 "nik4.py -b %s %s %s %s -x %d %d ~wwwrun/etc/gpx.xml %s && cat %s && rm %s" > %s',$tl[0],$tl[1],$br[0],$br[1],$imgsize[0],$imgsize[1],$tmpsvg,$tmpsvg,$tmpsvg,$tmpsvg);
	$cmd = sprintf('nik4.py -b %s %s %s %s -x %d %d ~www-data/etc/gpx.xml %s',$tl[0],$tl[1],$br[0],$br[1],$imgsize[0],$imgsize[1],$tmpsvg);
	exec($cmd,$out,$ret);
	if ($ret == 0) {
		// replace 
		$count=0;
		if (($fq=fopen($outpath,"w")) != true) {
			return array(false, "unable to write outpath");
		}
		if (($fp = fopen($tmpsvg,"r")) != true) {
			return array(false, "unable to read remote svg");
		}
		while(!feof($fp)){
			$line=fgets($fp);
			if ($count == 0) {
				if (!strstr($line,"xml")) {
					@unlink($tmpsvg);
					return array(false, "error get svg");
				}
			}
			if ($count++==2) {
				// 加上一行
				$bgimg_line = sprintf('<g id="background image" opacity="1" transform="translate(0,0)"><image  id="background map" opacity="1" width="%d" height="%d" x="0" y="0" xlink:href="%s" /></g>',
						$imgsize[0],$imgsize[1],$background_image_path);
				//echo "add one line: $bgimg_line\n";
				fwrite($fq,$bgimg_line);
			}
			fwrite($fq,$line);
		} // while
		@unlink($tmpsvg);
		// optimize svg: fail safe
		$cmd = sprintf("svgo %s",$outpath);
		exec($cmd,$out,$ret);
		echo "$outpath  created\n";
		return array(true,"file written");
		//
	} // ret == 0
	return array(false,"err exec $cmd");
}
/**
 * [tilestache_clean 新增/刪除 mid 的時候 hook
 * @param  [type] $mid [description]
 * @return [type]      [description]
 */
function tilestache_clean($mid){
	$row = map_get_single($mid);
	//print_r($row);
	echo "clean ". $row['title'] . "\n";
	if ($row==null){
		return array(false,"no such map");
	}
	$tl = proj_67toge(array($row['locX'],$row['locY']));
	$br = proj_67toge(array($row['locX']+$row['shiftX']*1000, $row['locY']-$row['shiftY']*1000));

	//$cmd = sprintf("ssh 172.31.39.193 'tilestache-clean.py -c ~wwwrun/etc/tilestache.cfg -l twmap_gpx -b %f %f %f %f 10 11 12 13 14 15 16 17 18 2>&1'",$tl[1],$tl[0],$br[1],$br[0]);
	$cmd = sprintf("tilestache-clean.py -c ~www-data/etc/tilestache.cfg -l twmap_gpx -b %f %f %f %f 10 11 12 13 14 15 16 17 18 2>&1",$tl[1],$tl[0],$br[1],$br[0]);
	error_log("tilestache_clean: ". $cmd);
	/*
	   利用 tilestache-clean 的 output 來砍另一層 cache 

	   10164 of 10192... twmap_gpx/18/219563/112348.png
	   10165 of 10192... twmap_gpx/18/219564/112348.png
	   10166 of 10192... twmap_gpx/18/219565/112348.png
	   10167 of 10192... twmap_gpx/18/219566/112348.png
	   10168 of 10192... twmap_gpx/18/219567/112348.png
	   10169 of 10192... twmap_gpx/18/219568/112348.png
	   10170 of 10192... twmap_gpx/18/219569/112348.png
	   10171 of 10192... twmap_gpx/18/219570/112348.png
	   10172 of 10192... twmap_gpx/18/219571/112348.png
	   10173 of 10192... twmap_gpx/18/219572/112348.png
	   10174 of 10192... twmap_gpx/18/219573/112348.png
	   10175 of 10192... twmap_gpx/18/219574/112348.png
	 */
	exec($cmd,$out,$ret);
	if ($ret == 0){
		foreach($out as $line){
			list($a,$png) = preg_split("/\.\.\./",$line);
			$clean[] = trim($png);
		}
		return array(true,$clean);
	}
	else
		return array(false,implode("\n",$out));
}

function remove_gpx_from_gis($mid){
	$sql[] = sprintf("DELETE FROM gpx_wp WHERE mid=%d",$mid);
	$sql[] = sprintf("DELETE FROM gpx_trk WHERE mid=%d",$mid);
	$db=get_conn();
	$db->StartTrans();
	foreach ($sql as $sql_str) {
		$db->Execute($sql_str);
	}
	$result = $db->CompleteTrans();
	if ($result === false) {
		return array(false,"sql transaction fail");
	}
	return array(true,"done");
}
// 取得範圍內的 gpx waypoints
function get_waypoint($x,$y,$r=10,$detail=0){
	$db=get_conn();
	if ($detail == 0)
		$sql = sprintf("SELECT DISTINCT \"gpx_wp.name\" AS name from gpx_wp WHERE ST_DWithin(wkb_geometry,ST_GeomFromText('POINT(%f %f)',4326) , %f ) ORDER BY name",$x,$y,$r/1000/111.325);
	else
		$sql = sprintf("SELECT DISTINCT \"gpx_wp.name\" AS name,\"gpx_wp.ele\" AS ele,ST_AsText(wkb_geometry) as loc,A.mid as mid,map.uid,map.flag,map.title from gpx_wp A, map WHERE  ST_DWithin(wkb_geometry,ST_GeomFromText('POINT(%f %f)',4326) , %f ) AND A.mid = map.mid  ORDER BY map.title",$x,$y,$r/1000/111.325);
	// error_log($sql);
	$rs = $db->getAll($sql);	
	return $rs;
}
function get_track($x,$y,$r=10,$detail=0){
        $db=get_conn();
        if ($detail == 0)
                $sql = sprintf("SELECT DISTINCT on (wkb_geometry) \"gpx_trk.name\" AS name FROM gpx_trk WHERE ST_Crosses(  wkb_geometry, ST_Buffer(ST_MakePoint(%f,%f)::geography,%d)::geometry)", $x,$y,$r);

        else
                $sql = sprintf("SELECT DISTINCT on (A.wkb_geometry)  A.\"gpx_trk.name\" AS name,ST_AsText(A.wkb_geometry) as loc,A.mid as mid,map.uid,map.flag,map.title from gpx_trk A,map WHERE ST_Crosses(  wkb_geometry, ST_Buffer(ST_MakePoint(%f,%f)::geography,%d)::geometry) AND A.mid = map.mid ", $x,$y,$r);
        // error_log($sql);
        $rs = $db->getAll($sql);
        return $rs;
}


// 取得面積
// http://gis.stackexchange.com/questions/44914/how-do-i-getthe-area-of-a-wgs84-polygon-in-square-meters
function get_AREA($wkt_str) {
	$db=get_conn();
	$sql =  sprintf("SELECT ST_Area( ST_Transform( ST_SetSRID( ST_GeomFromText( '%s' ) , 4326) , 900913))", $wkt_str);
	// error_log($sql);
	$rs = $db->getAll($sql);	
	echo $db->errorMsg();
	return $rs;
}

function get_point($id='ALL',$is_admin=false) {
	$db=get_conn();
	if ($id !== 'ALL') 
		$where = " WHERE id=$id";
	else
		$where = "";
	$sql = sprintf("SELECT id,name,alias,type,class,number,status,ele,mt100,checked,comment,ST_X(coord) AS x,ST_Y(coord) AS y,owner FROM point3 %s ORDER BY number,class DESC", $where);
	$db->SetFetchMode(ADODB_FETCH_ASSOC); 
	return $db->getAll($sql);

}
function get_lastest_point($num=5) {
	$db=get_conn();
	$sql = sprintf("SELECT id,name,alias,type,class,number,status,ele,mt100,checked,comment,ST_X(coord) AS x,ST_Y(coord) AS y,owner FROM point3 WHERE owner=0 ORDER BY id DESC LIMIT %d", $num);
	$db->SetFetchMode(ADODB_FETCH_ASSOC); 
	return $db->getAll($sql);
}
function userid() {
	global $CONFIG;
	//       $admin = $CONFIG['admin'];
	if (!isset($_SESSION['mylogin'])|| !isset($_SESSION['uid']))
		return array(false, "please login");
	return array(true, $_SESSION['uid']);
}

function is_admin() {
	global $CONFIG;
	if (!isset($_SESSION['mylogin'])|| !isset($_SESSION['uid']))
		return false;
	if(in_array($_SESSION['uid'], $CONFIG['admin']))
		return true;
	return false;
}

function get_elev($twDEM_path, $lat,$lon, $cache=1) {
	if ($cache) {
	$mem = new Memcached;
	$mem->addServer('localhost',11211);
	$key=sprintf("ele_%.06f_%.06f",$lat,$lon);
	$ele = $mem->get($key);
	if ($ele !== FALSE ) {
		return $ele;
	}
	$cmd = sprintf("gdallocationinfo -valonly -geoloc %s %s %s",$twDEM_path, $lon, $lat);
	exec($cmd, $out, $ret);
	$ele = trim($out[0]);
	if ($ret != 0) {
		return -20000;
	}
	if (empty($ele)) $ele = -10000;
	if ($cache)
		$mem->set($key, $ele, 86400);
	}
	return $ele;
}
function get_administration($x,$y,$type="town") {
	$db=get_conn();
	if ($type == "nature_park") {
		$table = "nature_parks";
	} else if ($type == "nature_reserve") {
		$table = "nature_reserve";
	} else {
		$table = "tw_town_2014";
	}
	$sql = sprintf("select * from \"%s\" where ST_intersects(geom, ST_Buffer(ST_MakePoint(%f,%f)::geography,%d)::geometry)=true",$table, $x,$y,10);
	$db->SetFetchMode(ADODB_FETCH_ASSOC); 
	return $db->getAll($sql);
}
