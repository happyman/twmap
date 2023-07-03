<?php
// 當 login 之後, 必須註冊到 db 裡面
// $Id: twmapdb.inc.php 356 2013-09-14 10:00:22Z happyman $
//
require_once("adodb5/adodb.inc.php");
require_once("azimuth.php");

function get_conn() {
	//global $db_host, $db_conn,$db_user, $db_pass, $db_name, $db_port;
	global $db_dsn,$db_conn;

	//if ($db_conn != null && $db_conn->IsConnected())
	//	return $db_conn;

	// error_log("new conection");
    	// db_conn = ADONewConnection('mysqli');
	//$status = $db_conn->PConnect('localhost', $db_user, $db_pass, $db_name);
	//$dsn = sprintf("postgres9://%s:%s@%s:%s/%s?persist",$db_user,$db_pass,$db_host,$db_port,$db_name);
	$db_conn = ADONewConnection($db_dsn);
	//$status = $db_conn->PConnect($db_host, $db_user, $db_pass, $db_name);

	//if ($status === true ) {
	if ($db_conn->IsConnected() !== true) {
			error_log("db can't connect");
			//return false;
			exit("no db connection");
	}
		//$db_conn->SetFetchMode(ADODB_FETCH_ASSOC);
		//$ADODB_FETCH_MODE = 'ADODB_FETCH_ASSOC';
	$db_conn->debug = false;
	return $db_conn;

}
function logsql($sql,$rs){
	// happyman 需要再開
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

function memcached_query($key) {
	global $CONFIG;
	$mem = new Memcached;
	$mem->addServer($CONFIG['memcache_server'],$CONFIG['memcache_port']);
	return $mem->get($key);
}
function memcached_set($key, $data, $ttl=86400){
	global $CONFIG;
	$mem = new Memcached;
	$mem->addServer($CONFIG['memcache_server'],$CONFIG['memcache_port']);
	$mem->set($key, $data, $ttl);
	return $data;
}
function memcached_delete($key) {
	global $CONFIG;
	$mem = new Memcached;
	$mem->addServer($CONFIG['memcache_server'],$CONFIG['memcache_port']);
	$mem->delete($key);
}

function fetch_user($mylogin) {
	if (!isset($mylogin['email']) || !isset($mylogin['type']))
		return false;
	if (empty($mylogin['email']) || empty($mylogin['type']))
		return false;
	$sql = sprintf("select * from \"user\" where email='%s' and type='%s'", $mylogin['email'],$mylogin['type']);
	$db = get_conn();

	$key=sprintf("fetch_user_%s_%s",$mylogin['email'],$mylogin['type']);
	$answer = memcached_query($key);
	if ($answer !== FALSE ) {
		return $answer;
	}
	$rs = $db->GetAll($sql);
	$db->close();
	logsql($sql,$rs);
	if (count($rs) == 0) return false;
	return memcached_set($key,$rs[0]);
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
		$memkey=sprintf("fetch_user_%s_%s",$mylogin['email'],$mylogin['type']);
		$sql = sprintf("update \"user\" SET \"login\"=%d, \"name\"='%s' WHERE \"uid\"=%d",$row['login']+1, pg_escape_string($mylogin['nick']),$row['uid']);
		//$res = mysql_query($sql);
		$rs = $db->Execute($sql);
		memcached_delete($memkey);
		logsql($sql,$rs);
	}
	// 是否加上 login record ?
	//
	$db->close();
	return fetch_user($mylogin);
}
function get_user($uid){
	$db=get_conn();
	$sql = sprintf("select * from \"user\" where uid=%s",$uid);
	$rs = $db->getAll($sql);
	$db->close();
	if (count($rs) == 1 )
		return $rs[0];
	else
		return array();
}
// 應再看是否已經 expire 了。TODO
function map_exists($uid,$startx,$starty,$shiftx,$shifty,$version,$gpx=0) {
	$db = get_conn();
	$sql = sprintf("SELECT \"mid\" from \"map\" WHERE \"uid\"='%s' AND \"locX\"=%d AND \"locY\"=%d AND \"shiftX\"=%d and \"shiftY\"=%d and \"version\"=%d and \"gpx\"=%d",$uid,$startx,$starty,$shiftx,$shifty,$version,$gpx);
	$rs = $db->GetAll($sql);
	logsql($sql,$rs);
	$db->close();
	if (count($rs) == 0 ) return false;
	return $rs[0];
}
function keepon_map_exists($uid,$keepon_id){
	$db=get_conn();
	$sql = sprintf("SELECT * from  \"map\" WHERE \"uid\"='%s' AND \"keepon_id\"='%s' AND \"flag\" <> 2",$uid,$keepon_id);
	$rs = $db->GetAll($sql);
	$db->close();
	logsql($sql,$rs);
	if (count($rs) == 0 ) return false;
	return $rs[0];
}

// TODO? maybe still buggy, to remove
function is_gpx_imported($mid) {
	$db=get_conn();
	$row = map_get_single($mid);
	$title = str_replace("-GPX自動轉檔","%",$row['title']);
	$sql = sprintf("SELECT \"mid\",\"title\" FROM \"map\" WHERE \"mid\" IN (SELECT DISTINCT \"mid\" FROM \"gpx_wp\") AND \"mid\" <> %d AND \"locX\"=%d AND \"locY\"=%d AND \"shiftX\"=%d and \"shiftY\"=%d and \"version\"=%d and \"gpx\"=1 AND \"title\" LIKE '%s'",$mid, $row['locX'],$row['locY'],$row['shiftX'],$row['shiftY'],$row['version'],pg_escape_string($title));
	$rs = $db->GetAll($sql);
	$db->close();
	logsql($sql,$rs);
	if (count($rs) == 0 ) return false;
	return $rs[0];
}
// 寫到 map table
function map_add($uid,$title,$startx,$starty,$shiftx,$shifty,$px,$py,$host,$file,$size,$version,$gpx,$keepon_id=NULL,$datum='TWD67') {

	// 若不是 keepon 來的, 檢查是否已經有同樣參數的地圖,有的話表示是重新產生
	// 不更新 mid, 只更新 size, version, title, cdate, flag 等參數
	$row = map_exists($uid,$startx,$starty,$shiftx,$shifty,$version,$gpx);
	$db=get_conn();
	if ($row === FALSE || $keepon_id != NULL ) {
		// 新地圖
		// 使用 postgresql 要改 default
		$sql = sprintf("INSERT INTO \"map\" (\"mid\",\"uid\",\"cdate\",\"host\",\"title\",\"locX\",\"locY\",\"shiftX\",\"shiftY\",\"pageX\",\"pageY\",\"filename\",\"size\",\"version\",\"gpx\",\"keepon_id\",\"datum\") VALUES (DEFAULT, %d, CURRENT_TIMESTAMP, '%s', '%s', %d, %d, %d, %d, %d, %d, '%s', %d, %d, %d, '%s', %s) returning mid", $uid, $host, pg_escape_string($title), $startx, $starty, $shiftx, $shifty, $px, $py, $file, $size, $version,$gpx,($keepon_id==NULL)?'NULL':$keepon_id,$datum);
		$rs = $db->getAll($sql);
		logsql($sql,$rs);
	$db->close();
		if (!isset($rs[0]['mid'])) {
			//error_log("err sql: $sql");
			return FALSE;
		}
		//return $db->Insert_ID();
		return $rs[0]['mid'];
	} else {
		// 重新產生的地圖, 連檔名都要更新
		$mid = $row[0];
		$sql = sprintf("UPDATE \"map\" SET \"locX\"=%d,\"locY\"=%d,\"shiftX\"=%d,\"shiftY\"=%d,\"size\"=%d,\"flag\"=0,\"cdate\"=CURRENT_TIMESTAMP,\"title\"='%s',\"version\"=%d,\"filename\"='%s',\"gpx\"=%d,\"datum\"=%s WHERE \"mid\"=%d",$startx, $starty, $shiftx, $shifty, $size, pg_escape_string($title),$version,$file, $gpx, $datum, $mid);
		$rs = $db->Execute($sql);
		$key=sprintf("map_get_single_%s",$mid);
		memcached_delete($key);
		logsql($sql,$rs);
	$db->close();
		if (!$rs) return FALSE;
		return $mid;
	}
}
// 寫入 log table
function make_map_log($mid,$channel,$agent,$params) {
        $db=get_conn();
        $sql = sprintf("INSERT INTO \"make_map\" (mid, channel, agent, params) VALUES (%d, '%s', '%s','%s')",$mid ,$channel,$agent,pg_escape_string($params));
        $rs=$db->GetAll($sql);
        logsql($sql,$rs);
        $db->close();
        return $rs;
}
// 取出所有 uid 產生的地圖 id
function map_get_ids($uid, $limit = 10) {
	$db=get_conn();
	$sql = sprintf("select mid from \"map\" where uid=%d ORDER BY \"cdate\" DESC LIMIT %d",$uid, $limit);
	$rs=$db->GetAll($sql);
	logsql($sql,$rs);
	$db->close();
	return $rs;
}
// 取 ok, expired  flag = 0 or 1 的地圖, 用來算限制
function map_list_get($uid, $order='') {
	$db=get_conn();
	$sql = sprintf("select * from \"map\" where \"uid\"=%s AND (flag = 1 or flag = 0) ORDER BY mid %s",$uid,$order);
	$rs = $db->GetAll($sql);
	$db->close();
	logsql($sql,$rs);
	return $rs;
}
function map_list_count($uid) {
	$db=get_conn();
	$sql = sprintf("select count(*) from \"map\" WHERE \"uid\"=%d AND (flag = 1 or flag = 0)",$uid);
	$row = $db->GetAll($sql);
	$db->close();
	logsql($sql,$row);
	return $row[0][0];
}
// 只取 ok 的, 用在 recreate
function map_get_ok($uid) {
	$db=get_conn();
	$sql = sprintf("select * from \"map\" WHERE \"uid\"=%d AND flag=0",$uid);
	$rs = $db->GetAll($sql);
	$db->close();
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
	$key=sprintf("map_get_single_%s",$mid);
	$answer = memcached_query($key);
	if ($answer !== FALSE ) {
		return $answer;
	}
	$sql = sprintf("select * from \"map\" WHERE \"mid\"=%d",$mid);
	$res = $db->GetAll($sql);
	$db->close();
	logsql($sql,$res);
	if (count($res) == 0)
		return null;
	else {
		return memcached_set($key,$res[0]);
	}
}
function map_accessed($mid) {
	$db=get_conn();
	$sql = sprintf("update \"map\" SET \"count\"=\"count\"+1 WHERE \"mid\"=%s",$mid);
	$rs = $db->Execute($sql);
	$db->close();
	logsql($sql,$rs);
	return $rs;
}
function map_get_hot($num) {
	$db=get_conn();
	$sql = sprintf("SELECT * FROM \"map\" WHERE \"flag\" !=2  AND \"host\" != '210.59.147.226' and \"count\" > 0 ORDER BY \"count\" DESC LIMIT %d",$num);
	$rs =$db->GetAll($sql);
	$db->close();
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
	$db->close();
	logsql($sql,$rs);
	return $rs;

}
function map_get_lastest_by_uid($num,$uid) {
	if ($uid == 0 ) return null;
	$db=get_conn();
	$where = "AND \"uid\"=$uid";
	$sql = sprintf("SELECT * FROM \"map\" WHERE \"flag\"=0 %s ORDER BY \"cdate\" DESC LIMIT %d",$where,$num);
	$rs = $db->GetAll($sql);
	$db->close();
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
}////'''''
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
function gethashdir($i) {
        $s = sprintf("%04s",dechex($i % 65535));
        $l1 = substr($s,0,2);
        $l2 = substr($s,2,2);
        return sprintf("%02s/%02s",$l1,$l2);
}

// 建立好之後,改變 structure 到 uid/mid/files
function map_migrate($root,$uid,$mid) {
	// 0. 檢查是新的結構?
	#$dir = sprintf("%s/%06d/%d",$root,$uid,$mid);
	$dir = sprintf("%s/%s/%06d/%d",$root,gethashdir($uid),$uid,$mid);
	// if (file_exists($dir) && is_dir($dir)) return true;

	$row = map_get_single($mid);
	if ($row == false) return false;
	// 檢查檔案是否在正確目錄

	$newfilename = sprintf("%s/%s",$dir, basename($row['filename']));
	if ($row['filename'] == $newfilename ) {
		return true;
	}
	// 1. 建立目錄
	@mkdir($dir,0755,true);
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
	$db->close();
	error_log("migrate $mid:$sql");
    $key=sprintf("map_get_single_%s",$mid);
	memcached_delete($key);

	map_block($root,$uid,0);
	return true;

}
// 檢查是否動作: 刪除/新增 不准做
function map_blocked($root, $uid) {
	$blockfile = sprintf("%s/%s/%06d/.block",$root,gethashdir($uid),$uid);

	if (file_exists($blockfile)){
		return "出圖或資料結構更新中..請稍候再試";
	}
	return null;
}
function map_block($root, $uid, $action=1) {
	$blockfile = sprintf("%s/%s/%06d/.block",$root,gethashdir($uid),$uid);
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
	$key=sprintf("map_get_single_%s",$mid);
	memcached_delete($key);
	$db->close();
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
		// 不刪除 cmd 跟 txt for reference
		if(strstr(basename($f),'.txt') || strstr(basename($f),".cmd")) continue;
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
	// update db, add expire date col
	$sql = sprintf('UPDATE "map" set "flag" = 1,"size"=0,"edate"=NOW()  WHERE "mid" = %d',$mid);
	$rs= $db->Execute($sql);
	$db->close();
	$key=sprintf("map_get_single_%s",$mid);
	memcached_delete($key);
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
	$db->close();
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
	$db->close();
	logsql($sql,$res);
	return $res[0]['totalsize'];
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

	$db->close();
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
require_once("keepon.inc.php");

function ajaxerr($msg) {
	$ret['ok'] = false;
	$ret['rsp'] = array('msg' => $msg );
	list($st,$info) = userid();
	if ($st === true)
		$ret['rsp']['info']=$info;
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
	$db->close();
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
				$sql = sprintf("INSERT into \"geocoder\" (\"address\",\"lat\",\"lng\",\"is_tw\",\"exact\",\"faddr\",\"name\") values ('%s',%f,%f,%d,%d,'%s','%s')",$data['address'],$data['lat'],$data['lng'],$data['is_tw'],$data['exact'],$data['faddr'],$data['name']);
			else if ($ret == 1)
				$sql = sprintf("UPDATE \"geocoder\" set \"address\"='%s',\"lat\"=%f, \"lng\"=%d, \"is_tw\"=%d, \"exact\"=%d, \"faddr\"='%s', \"name\"='%s'",$data['address'],$data['lat'],$data['lng'],$data['is_tw'],$data['exact'],$data['faddr'],$data['name']);
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
 */
function ogr2ogr_import_gpx($mid, $gpx_file, $type='waypoints'){
	global $gdal_dsn;
	// 1. 檢查 table 存在與否
	if ($type=='waypoints')
		$table='gpx_wp';
	else
		$table='gpx_trk';
	$db=get_conn();
	$sql = sprintf("SELECT relname FROM pg_class WHERE relname = '%s'",$table);
	$rs = $db->getAll($sql);
	//$db->close();
	if (isset($rs[0]['relname']) && $rs[0]['relname'] == $table) {
		// 1. delete mid from table (prevent dup)
		$sql = sprintf("DELETE FROM \"%s\" WHERE mid=%s",$table,$mid);
		$db->Execute($sql);
		// 2. add data by ogr2ogr
		$cmd = sprintf("ogr2ogr -update -append -f PostgreSQL \"%s\" %s -sql \"select %s.*,%d as mid from %s %s\"",
				$gdal_dsn,$gpx_file,$table,$mid,$type,$table);
		echo $cmd . "\n";
	} else {
		// 1. append
		$cmd = sprintf("ogr2ogr -append -f PostgreSQL \"%s\" %s -sql \"select %s.*,%d as mid from %s %s\"",
				$gdal_dsn,$gpx_file,$table,$mid,$type, $table);
		echo $cmd . "\n";
	}
	//echo $cmd . "\n";
	exec($cmd,$out,$ret);
	return $ret;
}
/**  do import 
 */
function import_gpx_to_gis($mid){
	// table gpx_waypoints
	// 0. 先檢查 gpx 存在與否
	if ($mid > 0 ){
		$row = map_get_single($mid);
		if ($row==null) 
			return array(false, "mid incorrect");
		$gpx_file = map_file_name($row['filename'], 'gpx');
	} else {
		// 找出對應的 gpx file, mid < 0 is track
		$tid = -1 * $mid;
		$rs = track_get_single($tid);
		if ($rs !== null ){
			$gpx_file_tmp = sprintf("%s/%d/%s_p.gpx",$rs['path'],$rs['tid'],$rs['md5name']);
			$gpx_file = sprintf("%s/%d/%s_x.gpx",$rs['path'],$rs['tid'],$rs['md5name']);
			$cmd = sprintf("gpsbabel -i gpx -f %s -x discard,matchcmt=base64 -o gpx -F %s",$gpx_file_tmp,$gpx_file);
			echo $cmd . "\n";
			exec($cmd,$ret,$out);
		} else 
			return array(false, "tid incorrect");
	}
	if (!file_exists($gpx_file))
		return array(false, "$gpx_file  not exists");
	$ret1 = ogr2ogr_import_gpx($mid, $gpx_file, 'waypoints');
	$ret2 = ogr2ogr_import_gpx($mid, $gpx_file, 'tracks');
	if ($ret1 == 0 && $ret2 == 0)
		return array(true,"success");
	else
		return array(false,"fail import");
}
function is_gpx_in_gis($mid){
	$db = get_conn();
	$sql = sprintf("SELECT count(*) from gpx_trk WHERE mid=%d",$mid);
	$rs = $db->getAll($sql);
	$db->close();
	if ($rs[0][0] == 0 ) {
		return false;
	}
	return true;
}
function mapnik_svg_gen($tw67_bbox,$background_image_path, $outpath) {
	$tl = proj_67toge($tw67_bbox[0]);
	$br = proj_67toge($tw67_bbox[1]);
	$imgsize = $tw67_bbox[2];
	$tmpsvg = tempnam("/tmp","SVG") . ".svg";
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
 */
function tilestache_clean($mid, $realdo = 1){
	if ($mid < 0 ){
		$tid = $mid * -1;
		$rs = track_get_single($tid);
		if ($rs === null ){
			return array(false, "no such track");
		}
		// 直接將 bound 從 db 取出
		list ($tl[1],$tl[0],$br[1],$br[0]) = preg_split("/\s/",$rs['bbox']);
		$title = $rs['name'];
	} else {
		$row = map_get_single($mid);
		//print_r($row);
		if ($row==null){
			return array(false,"no such map");
		}
		$tl = proj_67toge(array($row['locX'],$row['locY']));
		$br = proj_67toge(array($row['locX']+$row['shiftX']*1000, $row['locY']-$row['shiftY']*1000));
		$title = $row['title'];
		}
	// moi_osm_gpx 
	$cmd2 = sprintf("ssh happyman@twmap tilestache-clean.py -c /home/happyman/etc/tile_main_8089.cfg -l gpxtrack -b %f %f %f %f 10 11 12 13 14 15 16 17 18 2>&1 > /dev/null ",$tl[1],$tl[0],$br[1],$br[0]);
	exec($cmd2);
	error_log("tilestache_clean: ". $cmd2);
	return array(true, "gpxtrack cleaned");
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
	$db->close();
	if ($result === false) {
		return array(false,"sql transaction fail");
	}
	return array(true,"done");
}
// 取得範圍內的 gpx waypoints
function get_waypoint($x,$y,$r=10,$detail=0){
	$db=get_conn();
	if ($detail == 0)
		$sql = sprintf("SELECT DISTINCT \"gpx_wp.name\" AS name from gpx_wp WHERE ST_DWithin(wkb_geometry,ST_GeomFromEWKT('SRID=4326;POINT(%f %f)') , %f ) ORDER BY name",$x,$y,$r/1000/111.325);
	else {
		$sql = sprintf("SELECT DISTINCT \"gpx_wp.name\" AS name,\"gpx_wp.ele\" AS ele,ST_AsText(wkb_geometry) as loc,A.mid as mid,map.uid,map.flag,map.title,map.keepon_id,map.filename,map.path,map.md5name from gpx_wp A, meta as map WHERE  ST_DWithin(wkb_geometry,ST_GeomFromEWKT('SRID=4326;POINT(%f %f)') , %f ) AND A.mid = map.idid  ORDER BY map.title",$x,$y,$r/1000/111.325);
	}
	// error_log($sql);
	$rs = $db->getAll($sql);	
	$db->close();
	return $rs;
}
function get_track($x,$y,$r=10,$detail=0){
        $db=get_conn();
        if ($detail == 0)
                $sql = sprintf("SELECT \"gpx_trk.name\" AS name FROM gpx_trk WHERE ST_Crosses(wkb_geometry, ST_Buffer(ST_MakePoint(%f,%f)::geography,%d)::geometry)", $x,$y,$r);

        else
                $sql = sprintf("SELECT A.\"gpx_trk.name\" AS name,A.mid as mid,map.uid,map.flag,map.title,map.keepon_id,map.filename,map.path,map.md5name from gpx_trk A,meta as map WHERE ST_Crosses(  wkb_geometry, ST_Buffer(ST_MakePoint(%f,%f)::geography,%d)::geometry) AND A.mid = map.idid ", $x,$y,$r);
       // error_log($sql);
        $rs = $db->getAll($sql);
	$db->close();
        return $rs;
}


// 取得面積
// http://gis.stackexchange.com/questions/44914/how-do-i-getthe-area-of-a-wgs84-polygon-in-square-meters
function get_AREA($wkt_str) {
	$db=get_conn();
	$sql =  sprintf("SELECT ST_Area(ST_Transform( ST_GeomFromEWKT('SRID=4326;%s'),3857))", $wkt_str);
	// error_log($sql);
	$rs = $db->getAll($sql);	
	$db->close();
	//echo $db->errorMsg();
	return $rs;
}

function get_point($id='ALL',$is_admin=false) {
	if ($id !== 'ALL') 
		$where = " WHERE id=$id";
	else
		$where = "";
	$sql = sprintf("SELECT id,name,alias,type,class,number,status,ele,mt100,checked,comment,ST_X(coord) AS x,ST_Y(coord) AS y,owner,prominence,prominence_index,fzone,fclass,sname,cclass FROM point3 %s ORDER BY number,class DESC", $where);
	$key=sprintf("get_point_%s",$id);
	$answer = memcached_query($key);
	if ($answer !== FALSE ) {
		return $answer;
	}
	$db=get_conn();
	$db->SetFetchMode(ADODB_FETCH_ASSOC); 
	$answer = $db->getAll($sql);
	$db->close();
	return memcached_set($key, $answer);
}
// 取出　class_num 等基石, 官方點, 沒有用到
function get_point_by_class($class_num) {
	$db=get_conn();
	$where = sprintf("WHERE class='%d' AND owner=0",$class_num);
	$sql = sprintf("SELECT id,name,alias,type,class,number,status,ele,mt100,checked,comment,ST_X(coord) AS x,ST_Y(coord) AS y,owner,prominence,prominence_index,fzone,fclass,sname,cclass  FROM point3 %s ORDER BY number,ST_XMin(coord)", $where);
	$db->SetFetchMode(ADODB_FETCH_ASSOC); 
	// echo $sql;
	$answer = $db->getAll($sql);
	$db->close();
	return $answer;
}
/* 取出範圍內所有 features, 官方點 */
function get_points_from_center($center, $r_in_meters) {
	$db = get_conn();
	$sql = sprintf("SELECT id,name,class,number,ele,ST_X(coord) AS x, ST_Y(coord) AS y,prominence,prominence_index FROM point3 WHERE owner = 0 AND ST_DWithin(coord, ST_GeomFromEWKT('SRID=4326;POINT(%f %f)') , %f ) ORDER BY number,ST_XMin(coord)",$center[0],$center[1],$r_in_meters/1000/111.325);
	// echo $sql;
	$db->SetFetchMode(ADODB_FETCH_ASSOC);
	$data = $db->getAll($sql);
	$db->close();
        return $data;
}
function get_lastest_point($num=5) {
	$db=get_conn();
	$sql = sprintf("SELECT id,name,alias,type,class,number,status,ele,mt100,checked,comment,ST_X(coord) AS x,ST_Y(coord) AS y,owner,prominence,prominence_index,fzone,fclass,cclass,sname FROM point3 WHERE owner=0 ORDER BY id DESC LIMIT %d", $num);
	$db->SetFetchMode(ADODB_FETCH_ASSOC); 
	$data = $db->getAll($sql);
	$db->close();
	return $data;
}
function userid() {
	global $CONFIG;
	if (!isset($_SESSION) ) session_start();
	//       $admin = $CONFIG['admin'];
	if (!isset($_SESSION['mylogin'])|| !isset($_SESSION['uid']))
		return array(false, "please login");
	return array(true, $_SESSION['uid']);
}

function is_admin() {
	global $CONFIG;
	if (!isset($_SESSION) ) session_start();
	if (!isset($_SESSION['mylogin'])|| !isset($_SESSION['uid']))
		return false;
	if(in_array($_SESSION['uid'], $CONFIG['admin']))
		return true;
	return false;
}
function request_rest_api($url, $data) {
		
		$content=json_encode($data);
		//$content = http_build_query($params, '', '&');
		$header = array(
			"Content-Type: application/x-www-form-urlencoded",
			"Content-Length: ".strlen($content)
		);
		$options = array(
			'http' => array(
				'method' => 'POST',
				'content' => $content,
				'header' => implode("\r\n", $header)
			)
			
		);
		return file_get_contents($url, false, stream_context_create($options));
}

function get_elev_moidemd($lat,$lon) {
	$data = array(array((float)$lon,(float)$lat))	;
	$ret = request_rest_api("http://127.0.0.1:8895/v1/elevations",$data);
	if ($ret === false) return -20000;
	$data = json_decode($ret, true);
	if (isset($data[0])) return $data[0];
	return -10000;
}
function get_elev($twDEM_path, $lat,$lon, $cache=1) {
	if ($cache) {
	$key=sprintf("ele_%.06f_%.06f",$lat,$lon);
	$ele = memcached_query($key);
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
		memcached_set($key, $ele);
	}
	return $ele;
}
function get_elev_multi($twDEM_path, $points ) {
/* space seperated */
	foreach($points as $pp) {
		$p = explode(" ",$pp);
		$data[] = sprintf("%f %f",$p[0],$p[1]);
	}
	$cmd = sprintf("printf \"%s\n\" | gdallocationinfo -valonly -wgs84 %s", implode("\\n",$data),$twDEM_path);
	// echo $cmd;
	exec($cmd, $out, $ret);
	if ($ret == 0 ) {
		return $out;
	}
	return false;
}
function get_elev_moidemd_multi($points) {
	foreach($points as $pp) {
		$p = explode(" ",$pp);
		$data[] = array((float)$p[0],(float)$p[1]);
	}	
	//$data = array(array((float)$lon,(float)$lat))	;
	$ret = request_rest_api("http://127.0.0.1:8895/v1/elevations",$data);
	if ($ret === false) return false;
	return json_decode($ret, true);

}
function get_distance_postgis($a,$b) {
	$db = get_conn();
	$sql = sprintf("SELECT ST_distance_Sphere(ST_setSRID(ST_makepoint(%f,%f),4326),ST_setSRID(ST_makepoint(%f,%f),4326)) as distance",
			$a[0],$a[1],$b[0],$b[1]);
	//echo $sql;
	$db->SetFetchMode(ADODB_FETCH_ASSOC);
	$result = $db->getAll($sql);
	$db->close();
	//print_r($result);
	return $result[0]['distance'];
}
function get_distance($a,$b) {
 $lat1 = $a[1];
 $lon1 = $a[0];
 $lat2 = $b[1]; $lon2 = $b[0];
  $rad = M_PI / 180;
  return acos(sin($lat2*$rad) * sin($lat1*$rad) + cos($lat2*$rad) * cos($lat1*$rad) * cos($lon2*$rad - $lon1*$rad)) * 6371 * 1000;// meters
}
function line_of_sight($a, $b, $distance_limit = 32000, $cache = 1) {
	$debug = 0;
//	$twDEM_path = "../db/DEM/twdtm_asterV2_30m.tif";
	$twDEM_path = twDEM_path;
	// 1. get distance between p and p1
	$distance = get_distance($a, $b);
	$start_ele = (isset($a[2]) && $a[2]>0) ? $a[2] : get_elev($twDEM_path,$a[1], $a[0]);
	$end_ele = (isset($b[2]) && $b[2]>0) ? $b[2] : get_elev($twDEM_path,$b[1], $b[0]);
	if ($distance == 0 )
		return array(false,$a, "same point");
	else if ($distance > $distance_limit)
		return array(false,$a, "exceed distance limit");
	// plus 2, human height
	$start_ele+=2;
	// load from cache
	if ($cache) {
		$key=sprintf("los_%.06f_%.06f_%d-%.06f_%.06f_%d-%d",$a[0],$a[1],$start_ele,$b[0],$b[1],$end_ele,$distance_limit);
		$answer = memcached_query($key);
		// $answer = FALSE;
		if ($answer !== FALSE ) {
			// echo "[cached] ";
			return $answer;
		}
	}
	$Y = $end_ele - $start_ele;
	$ratio = $Y / $distance;
	if ($debug) {
		echo "distance: $distance\n";
		echo "ratio: $ratio\n";
	}
	$step = 60.0 / 1000 / 111.325;

	$sql = sprintf("select st_astext( ST_Segmentize(st_makeline(ST_setSRID(ST_makepoint(%f,%f),4326),ST_setSRID(ST_makepoint(%f,%f),4326)), %f)) as linestring" ,$a[0],$a[1],$b[0],$b[1],$step);
	//echo $sql;
	$db = get_conn();
	$db->SetFetchMode(ADODB_FETCH_ASSOC);
	$res = $db->getAll($sql);
	$db->close();
	//print_r($res);
	if (!preg_match("/LINESTRING\((.*)\)/",$res[0]['linestring'],$mat)) {
		return array(false, $a, "error query $sql ".print_r($res,true));
	}
	$points = explode(",",$mat[1]);
		$elev_data = get_elev_multi($twDEM_path,$points);
	
	for($i=1;$i<count($points);$i++) {
		$point = explode(" ",$points[$i]);
		$ele = $elev_data[$i];
		$dist = get_distance($a, $point);
		//$dist = $i * 60;
		$expect_ele = round($dist * $ratio + $start_ele);
		if ($debug)
			printf("%.06f,%.06f => dist: $dist ele: $ele, expect: $expect_ele\n",$point[1],$point[0]);
		if ($ele > round($expect_ele)) {
		// echo "ERR\n";
		$ret = array(false, $point, "stop");
		if ($cache) 
				memcached_set($key,$ret);
		
		return $ret;
		break;
		}
	}
	$ret = array(true, $b, "OK");
	if ($cache)
		memcached_set($key,$ret);
	return $ret;

}
function line_of_sight2($a, $b, $distance_limit = 32000, $cache = 1) {
	$debug = 0;
//	$twDEM_path = "../db/DEM/twdtm_asterV2_30m.tif";
	$twDEM_path = twDEM_path;
	// 1. get distance between p and p1
	$distance = get_distance($a, $b);
	$start_ele = (isset($a[2]) && $a[2]>0) ? $a[2] : get_elev_moidemd($a[1], $a[0]);
	$end_ele = (isset($b[2]) && $b[2]>0) ? $b[2] : get_elev_moidemd($b[1], $b[0]);
	if ($distance == 0 )
		return array(false,$a, "same point");
	else if ($distance > $distance_limit)
		return array(false,$a, "exceed distance limit");
	// plus 2, human height
	$start_ele+=2;
	// load from cache
	if ($cache) {
		$key=sprintf("los_%.06f_%.06f_%d-%.06f_%.06f_%d-%d",$a[0],$a[1],$start_ele,$b[0],$b[1],$end_ele,$distance_limit);
		$answer = memcached_query($key);
		// $answer = FALSE;
		if ($answer !== FALSE ) {
			// echo "[cached] ";
			return $answer;
		}
	}
	$Y = $end_ele - $start_ele;
	$ratio = $Y / $distance;
	if ($debug) {
		echo "distance: $distance\n";
		echo "ratio: $ratio\n";
	}
	$step = 60.0 / 1000 / 111.325;

	$sql = sprintf("select st_astext( ST_Segmentize(st_makeline(ST_setSRID(ST_makepoint(%f,%f),4326),ST_setSRID(ST_makepoint(%f,%f),4326)), %f)) as linestring" ,$a[0],$a[1],$b[0],$b[1],$step);
	//echo $sql;
	$db = get_conn();
	$db->SetFetchMode(ADODB_FETCH_ASSOC);
	$res = $db->getAll($sql);
	$db->close();
	//print_r($res);
	if (!preg_match("/LINESTRING\((.*)\)/",$res[0]['linestring'],$mat)) {
		return array(false, $a, "error query $sql ".print_r($res,true));
	}
	$points = explode(",",$mat[1]);
		$elev_data = get_elev_moidemd_multi($points);
	
	for($i=1;$i<count($points);$i++) {
		$point = explode(" ",$points[$i]);
		$ele = $elev_data[$i];
		$dist = get_distance($a, $point);
		//$dist = $i * 60;
		$expect_ele = round($dist * $ratio + $start_ele);
		if ($debug)
			printf("%.06f,%.06f => dist: $dist ele: $ele, expect: $expect_ele\n",$point[1],$point[0]);
		if ($ele > round($expect_ele)) {
		// echo "ERR\n";
		$ret = array(false, $point, "stop");
		if ($cache) 
				memcached_set($key,$ret);
		
		return $ret;
		break;
		}
	}
	$ret = array(true, $b, "OK");
	if ($cache)
		memcached_set($key,$ret);
	return $ret;

}
/* 利用高度用在算真正走的距離　2017.3.30 */
function get_distance2($wkt_str, $twDEM_path){
	$db = get_conn();
	$step = 20.0 / 1000 / 111.325;
			
	$sql = sprintf("select st_astext( ST_Segmentize(ST_GeomFromText('%s',4326), %f)) as linestring" ,$wkt_str,$step);
	// echo $sql;
	$db->SetFetchMode(ADODB_FETCH_ASSOC);
	$res = $db->getAll($sql);
	$db->close();
	// print_r($res);
	if (!preg_match("/LINESTRING\((.*)\)/",$res[0]['linestring'],$mat)) {
		return array(false, "error query $sql ".print_r($res,true));
	}
	
	$points = explode(",",$mat[1]);
	$elev_data = get_elev_moidemd_multi($points);
	$maxele = 0;
	$minele = 9999;
	$sumele = 0;
	$descent = 0;
	$ascent = 0;
	$outofrange = 0;
	if ($elev_data[0] > $maxele) $maxele = $elev_data[0];
	if ($elev_data[0] < $minele) $minele = $elev_data[0];
	$sumele = $elev_data[0];
	for($i=1;$i<count($points);$i++) {
		$point = explode(" ",$points[$i]);
		$ele = $elev_data[$i];
		$dist[$i] = get_distance(explode(" ",$points[$i-1]), $point);
		$elediff[$i] = $elev_data[$i] - $elev_data[$i-1];
		// if we need azimuth between points
		//$ppoint = explode(" ",$points[$i-1]);
		//$azi_result = Calculate(array("lon"=>$ppoint[0],"lat"=>$ppoint[1],"elv"=>$elev_data[$i-1]),
		//							array("lon"=>$point[0],"lat"=>$point[1],"elv"=>$elev_data[$i]));
		//$azimuth[$i] = $azi_result["azimuth"];
	
		//  利用 dem 的 empty return 當作超出範圍 
		if (empty($elev_data[$i])) $outofrange = 1;
		// 算最高 最低海拔
		if ($elev_data[$i] > $maxele) $maxele = $elev_data[$i];
		if ($elev_data[$i] < $minele) $minele = $elev_data[$i];
		// 算上升下降多少
		if ($elediff[$i] > 0 )
			$ascent+=$elediff[$i];
		else
			$descent+=abs($elediff[$i]);
		$sumele+= $elev_data[$i];
		$dist2[$i] = sqrt(pow($dist[$i],2) + pow($elediff[$i],2));
		
	}
	/* 計算頭尾點的 azimuth */
	$ppoint = explode(" ",$points[0]);$point=explode(" ",$points[count($points)-1]);
	$azi_result = Calculate(array("lon"=>$ppoint[0],"lat"=>$ppoint[1],"elv"=>$elev_data[0]),
							array("lon"=>$point[0],"lat"=>$point[1],"elv"=>$elev_data[count($points)-1]));
    /* 畫出斜率 y=ax+b */
	$sum=0;
	for($i=0;$i<count($points);$i++) {
		$sum+=$dist[$i];
	}
	$a = ($elev_data[count($points)-1]-$elev_data[0])/($sum);
	$b = $elev_data[count($points)-1] - $a * $sum;
	/*準備好 */
	$sum2=0;$sum=0;$cross = 0;
	for($i=0;$i<count($points);$i++) {
		$sum+=$dist[$i];
		$sum2+=$dist2[$i];
		// $sum2+=$dist2[$i];
		$msg.=sprintf("<pre>%d %s %d h=%.02f d=%.02f d1=%.02f  \n",$i,$points[$i],$elev_data[$i],$elediff[$i],$dist[$i],$dist2[$i]);
		//if ($i>0) {
			$slope_y = $a*$sum+$b;
			if ($elev_data[$i] > $slope_y) $cross = 1;
			$charts[] =sprintf("[%.02f,%.02f,%.02f]",$sum,$elev_data[$i], $slope_y);
			
		//}
	}
	return array(true,array("step"=>20, "d"=>$sum, "d1"=>$sum2, "avgele" => $sumele / count($points),
	"ascent"=>$ascent, "descent"=>$descent, "outofrange" => $outofrange, "maxele"=> $maxele, "minele" => $minele, "chart"=>implode(",",$charts), "azimuth"=>$azi_result['azimuth'], "cross"=>$cross));
}
// update to 2020 version, with 飛地
function get_administration($x,$y,$type="town") {
	$db=get_conn();
	if ($type == "nature_park") {
		$table = "nature_parks";
	} else if ($type == "nature_reserve") {
		$table = "nature_reserve";
	} else {
		$table = "tw_town_2020";
	}
	$sql = sprintf("select * from \"%s\" where ST_intersects(geom, ST_Buffer(ST_MakePoint(%f,%f)::geography,%d)::geometry)=true",$table, $x,$y,10);
	$db->SetFetchMode(ADODB_FETCH_ASSOC); 
	$data = $db->getAll($sql);
	$db->close();
	return $data;
}
// from cwb V8 (tribe_home table)
function get_tribe_weather_url($key){
	$db=get_conn();
	$sql = sprintf("select * from tribe_home where tribe_town='%s'",$key);
	$db->SetFetchMode(ADODB_FETCH_ASSOC); 
	$data =  $db->getAll($sql);
	$db->close();
	$ret = array();
	if (count($data) > 0) {
		foreach($data as $d)
		$ret[]= sprintf("<a href=%s target=cwb>%s</a>",$d['cwb_link'],$d['tribe_name']);
		return implode($ret,",");
	}
	return "";
}
// use to export points to KML 
// fpath is output geojson file. 
// bound is xmin,ymin,xmax,ymax: bounding box
// owner 
function ogr2ogr_export_points($fpath, $bound, $owner=0) {
		global $gdal_dsn;
		if (empty($fpath)) return array(false, "file can't be empty");
		// 如果 output already exists, 檢查是否為最新, 否的話更新. output file permission not check.
		if (file_exists($fpath)){
			$db=get_conn();
			$sql = sprintf("select * from point3 where owner=0 AND mdate >= '%s'",date('Y-m-d H:i:s',filemtime($fpath)));
			$result = $db->getAll($sql);
	$db->close();
			if (count($result) > 0 )
				unlink($fpath);
			else
				return array(true, "ok from cache");
		}
		if ($owner > 0) {
			$owner_str = sprintf("(owner = 0 OR owner = %d )",$owner);
		} else {
			$owner_str = sprintf("owner = 0");
		}
		if (isset($bound[0])){
			//$spat = sprintf("-spat %.06f %.06f %.06f %.06f",$bound[0],$bound[1],$bound[2],$bound[3]);
			$spat = sprintf('AND (coord && ST_MakeEnvelope(%.06f,%.06f,%.06f,%.06f))',$bound[0],$bound[1],$bound[2],$bound[3]);
		} else 
			$spat = "";
		//$cmd = sprintf("ogr2ogr -f GeoJSON -dsco GPX_USE_EXTENSIONS=YES -lco FORCE_GPX_TRACK=YES  %s \"%s\" -where \"%s %s\"  point3 ", $fpath, $gdal_dsn, $owner_str, $spat);
		$cmd = sprintf("ogr2ogr -f GeoJSON  %s \"%s\" -where \"%s %s\"  point3 ", $fpath, $gdal_dsn, $owner_str, $spat);
		// echo $cmd;
		exec($cmd, $out, $ret);
		if ($ret == 0 ) return array(true, "ok");
		else return array(false, implode( "", $out ));
}

function ogr2ogr_export_gpx($mid, $merged_gpx) {
	global $gdal_dsn;
	$trk_gpx =  tempnam("/tmp","EXPT") . ".gpx";
	$cmd = sprintf("ogr2ogr -f GPX -dsco GPX_USE_EXTENSIONS=YES -lco FORCE_GPX_TRACK=YES %s %s -where \"mid=%d\" gpx_trk ", $trk_gpx, $gdal_dsn, $mid);
	// echo $cmd;
	exec($cmd, $out, $ret);
	if ($ret != 0 ) return array(false, "export trk failed");
	$wpt_gpx =  tempnam("/tmp","EXPW") . ".gpx";
	$cmd2 = sprintf("ogr2ogr -f GPX -dsco GPX_USE_EXTENSIONS=YES -lco FORCE_GPX_TRACK=YES %s %s -where \"mid=%d\" gpx_wp ", $wpt_gpx, $gdal_dsn, $mid);
	// echo $cmd2;
	exec($cmd2, $out, $ret);
	if ($ret != 0 ) return array(false, "export wpt failed");
	// $merged_gpx = tempnam("/tmp","EXPM") . ".gpx";
	$cmd3 = sprintf("gpsbabel -i gpx -f %s -f %s -o gpx,gpxver=1.1 -F %s",$trk_gpx,$wpt_gpx,$merged_gpx);
	exec($cmd3, $out, $ret);
	if ($ret != 0 ) return array(false, "merge gpx failed: $cmd3");
	unlink($wpt_gpx);unlink($trk_gpx);
	return array(true, "ok");
}
// map ranking system
class map_rank {
	var $score_text = array("無","糟糕","不佳","普通","好","精選");
	function get_rank($mid,$uid){
		$db=get_conn();
		$sql = sprintf("select * from map_rank WHERE mid=%d AND uid=%d",$mid,$uid);
		$data =  $db->getAll($sql);
		$db->close();
		return $data;
	}
	function del_rank($mid,$uid){
		$db=get_conn();
		$sql = sprintf("delete from map_rank where mid=%d AND uid=%d",$mid,$uid);
		// echo $sql;
		$ret =  $db->Execute($sql);
		$db->close();
		return $ret;
	}
	function set_rank($mid,$uid,$score,$comment) {
		$db=get_conn();
		$ret=$this->get_rank($mid,$uid);
		if ($ret && count($ret) > 0 ) {
			$sql = sprintf("update map_rank SET score=%f, comment='%s', rdate=now() WHERE mid=%d AND uid=%d",$score,pg_escape_string($comment),$mid,$uid);
		} else {
			$sql = sprintf("insert into map_rank(\"uid\",\"mid\",\"score\",\"comment\") VALUES (%d,%d,%f,'%s')",$uid,$mid,$score,pg_escape_string($comment));
		}
		$rs = $db->Execute($sql);
		$db->close();
		if (!$rs)
			return array(false,"fail $sql");
		else
			return array(true,$this->get_rank($mid,$uid));
	}
	function stats($mid){
		$db=get_conn();
		$sql = sprintf("select COUNT(*) as count, AVG(score) as score FROM map_rank WHERE mid=%d",$mid);
		$avg = $db->getAll($sql);
		$db->close();
		// $score_text = array("無","糟糕","不佳","普通","好","精選");
		if ($avg[0]['count'] == 0) {
			return array("count"=>0,"score"=>NULL,"text"=>"無", "icon"=>"rate_0.png");
		} else {
			return array("count"=>$avg[0]['count'],"score"=>$avg[0]['score'],"text"=>$this->score_text[round($avg[0]['score'])], "icon"=> "rate_".round($avg[0]['score']) . ".png" );
		}
	}
	function get_comment($mid){
		$db=get_conn();
		$sql = sprintf("select A.uid,A.type,A.name,A.email,B.score,B.comment FROM \"user\" A, map_rank B WHERE B.mid = %s AND A.uid=B.uid",$mid);
		$res  = $db->getAll($sql);
		if (count($res) > 0 ){
			foreach($res as $row){
				$row['text'] = $this->score_text[round($row['score'])];
				$res2[] = $row;
			}
			return $res2;
		}
		return array();
	}
}

function sanitize_output($buffer) {
	if ($buffer)
      return  PHPWee\Minify::html($buffer) . "<!--\n" . file_get_contents( __ROOT__ ."/pages/buddha.txt") . "\n-->\n";
}

// track table handling functions
// 
require_once("track.inc.php");
require_once("export/php-export-data.class.php");
