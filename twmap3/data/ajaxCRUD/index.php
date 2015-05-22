<?php

require_once('preheader.php');
/*
$admin = array("wine.chuang@mic.com.tw","happyman@colocation.hinet.net");
if (!in_array($_SESSION['mylogin']['email'],$admin)){
	exit("你不能編輯喔");
}
 */
if (empty($_SESSION['loggedin'])) {
	// 如果從地圖瀏覽器導過來
	$_SESSION['redirto'] = $_SERVER["REQUEST_URI"];
	header("Location: /twmap/login.php");
	exit(0);
}

if (!is_admin()) {
	exit("你不能編輯喔");
}


#the code for the class
include ('ajaxCRUD.class.php');
#

if (!empty($_GET['id'])) {
	$where = sprintf("WHERE id = %d",$_GET['id']);
	$limit = 1;
} else if (!empty($_GET['name'])) {
	$where = "WHERE name like '%" . addslashes(trim($_GET['name'])) . "%'";
	$limit = 10;
} else if  (!empty($_GET['cond'])) { // 特殊條件
	switch($_GET['cond']) {
	case 'mt100': // 百岳
		$where = sprintf("WHERE mt100 & 1 ");
		$limit = 100;
		break;
	case 'smt100': // 小百岳
		$where = sprintf("WHERE mt100 & 2 ");
		$limit = 100;
		break;
	case 'wkmt100':
		$where = sprintf("WHERE mt100 & 4 ");
		$limit = 100;
		break;
	case 'todo':
		$where = "WHERE checked = 0 ";
		$limit = 10;
		break;
	default:
		$where = "";
		$limit = 10;
		break;
	}

} else {
	$where = "";
	$limit = 10;
}	

$tbl = new ajaxCRUD("點位", "point", "id");
//$tbl->omitPrimaryKey();
$tbl->disallowEdit("id");
//$tbl->turnOffAjaxAdd();
// $tbl->turnOffAjaxEditing();
if (!empty($where))
	$tbl->addWhereClause($where);
$tbl->setLimit($limit);
$tbl->omitField("owner");
$tbl->displayAs("name", "名稱");
$tbl->displayAs("alias", "別名");
$tbl->displayAs("type", "種類");
$tbl->formatFieldWithFunction('type', 'showIcon');
$tbl->displayAs("class", "等");
$tbl->displayAs("number", "號碼");
$tbl->displayAs("status", "狀態");
$tbl->displayAs("ele", "高度");
$tbl->displayAs("comment", "註解");
$tbl->displayAs("mt100", "百岳");
$tbl->displayAs("checked", "檢查");

$tbl->defineCheckbox('checked','1','0');
//$tbl->defineCheckbox('mt100',1,0);

if ($_GET['x'] && $_GET['y']) {
	$tbl->setInitialAddFieldValue('x', $_GET['x']);
	$tbl->setInitialAddFieldValue('y', $_GET['y']);
	$tbl->displayAddFormTop();
}
if ($_GET['name'])
	$tbl->setInitialAddFieldValue('name', $_GET['name']);
$tbl->showTable();


function showIcon($val){
	return sprintf("%s<img src='http://map.happyman.idv.tw/icon/%s.png'>",$val, urlencode($val));
}
?>
<div align=center class="note">
<b>管理者您好</b><br>
<p>
ps: 大百岳=1 小百岳=2 百名山=4 若兩者皆是 1+4=5 
<p>
<form action="" method="GET">
查詢 name <input name=name>
輸入 id <input name="id">
<br>
<button>送出</button>

<a href="index.php?cond=mt100">百岳</a>,
<a href="index.php?cond=smt100">小百岳</a>,
<a href="index.php?cond=wkmt100">百名山</a>,
<a href="index.php?cond=todo">還沒檢查的</a>
</form>
</div>
