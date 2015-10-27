<?php
require_once("../config.inc.php");
if(!ob_start("ob_gzhandler")) ob_start();


$id = (isset($_REQUEST['id']))? $_REQUEST['id'] : NULL;
// id == ALL
$result = get_point($id);

/*
角色
管理者: 回傳所有點, 可編輯所有點
使用者: 回傳公用點,個人點, 編輯個人點
*/
list($st,$info) = userid();
if ($id !== 'ALL') {
	$result[0]['story'] = tell_story($result[0]);
	if ($st === true) 
		$result[0]['info'] = $info;
} else {
	
}
// 載入哪些點?
foreach($result as $row) {
	unset($row['coord']);
	// 已登入
	if ($st === true ) {
		if (is_admin()) {
			$rows[] = $row;
		} else if ($row['owner'] == $info || $row['owner'] == 0 ) {
			$rows[] = $row;
		}
	} else {
		if ($row['owner'] == 0 )
			$rows[] = $row;
	}
}

header('Content-Type: application/json');
echo json_encode($rows);
exit(0);


function tell_story($d) {
	global $site_html_root;
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
	$a .= sprintf("<br>資料: %s",($d['checked'])? "ok" : "待查");
	list($st,$info) = userid();
	// 1. 未登入
	if ($st === false) {
		// do nothing
	} else {
		if (is_admin() == 1 || $info==$d['owner'])  {
		$a .= sprintf("<br>編輯<a href=# onClick=\"showmeerkat('%s/admin/index.php?id=%d',{});return false\">本點</a>",$site_html_root,$d['id']);
		$a .= sprintf("<br><a href=# onClick=\"showmeerkat('%s/admin/index.php',{});return false\">我的興趣點</a>",$site_html_root);
		} else {
		$a .= sprintf("<br><a href=# onClick=\"showmeerkat('%s/admin/index.php',{});return false\">我的興趣點</a>",$site_html_root);
		}
	}
	return $a;
}
