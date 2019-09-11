<?php
// $Id: pointdata.php 102 2010-11-28 06:02:03Z happyman $
// 讀取 data, 及使用在 autocomplete 的 search
require_once("preheader.php");

// 登入了嘛? 如果沒登入就不要顯示編輯選項
$result=array();
if (isset($_GET['q'])) {
	$q = $_GET['q'];
}
if (isset($_GET['id'])) {
	$id = $_GET['id'];
}

$sql="select * from point ";
// 為了使原點疊在上面
$order=" order by number,class desc ";
if (!empty($q)) {
	$sql .= " where name like '%" . addslashes($q) . "%'";
	$order = " order by length(name)";
} else if (!empty($id)) {
	$sql .= " where id = ".pg_escape_string($id);
	$order = "";
}

$sql.=$order;
$res = mysqli_query($db, $sql);
$result = array();

if ($res) {
	while($row = mysqli_fetch_array($res, MYSQL_ASSOC)){
		$result[] = $row;
	}
}

if(!ob_start("ob_gzhandler")) ob_start();
if (empty($q))  {
	header('Content-Type: application/json');
	if (!empty($id)) 
		$result[0]['story'] = tell_story($result[0],$_GET);
	else {
		unset($row['status']);
		unset($row['type_desc']);
		unset($row['comment']);
	}
	echo json_encode($result);
	exit;

} else {
	foreach($result as $data) {
		if (preg_match("/$q/",$data['name'])){
			echo $data['name'] . "\n";
		}
	}
}
function tell_story($d,$p=array()){
	// 類別
	$a = sprintf("<br>類別: %s", $d['type']);
	// 如果是原點
	if ($d['class'] > 1 && $d['class'] < 4 ) {
		$a .= sprintf("%d-%d", $d['class'],$d['number']);
	}
	if (strstr($d['type'],'點'))
		$a .= sprintf("<br>狀態: %s", $d['status']);
	if (!empty($d['alias']))
		$a .= sprintf("<br>別名: %s", $d['alias']);

	if ($d['mt100']>0) {
		$a .= "<br>我是";
		$astr=array();
	if ($d['mt100'] & 1 )
		$astr[] = "百岳";
	if ($d['mt100'] & 2 ) 
		$astr[] = "小百岳";
	if ($d['mt100'] & 4 )
		$astr[] = "百名山";
	$a .= sprintf("%s",implode(",",$astr));
	}
	if (!empty($d['comment']))
		$a .= sprintf("<br>註解: %s", $d['comment']);
	if (is_admin()) {
		if ($p['beta']) {
			$a .= sprintf("<br><a href=# onClick=\"showmeerkat('data/ajaxCRUD/index.php?id=%d',{});return false\">編輯</a> %s",$d['id'],($d['checked'])? "ok" : "請編輯");

		} else {
			$a .= sprintf("<a href='data/ajaxCRUD/index.php?id=%d' target=edit><br>編輯</a> %s",$d['id'], ($d['checked'])? "ok" : "請編輯");
		}
	} else {
		$a .= sprintf("%s",($d['checked'])? "" : "(待查)");
	}
	return $a;
}
