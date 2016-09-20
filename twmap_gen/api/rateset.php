<?php
require_once("../config.inc.php");
if(!ob_start("ob_gzhandler")) ob_start();

$mid = $_REQUEST['mid'];
$score = $_REQUEST['score'];
// prevent XSS
$comment = htmlspecialchars($_REQUEST['comment'], ENT_QUOTES, 'utf-8');

if (empty($mid)) {
	echo "需要 mid 喔";
	exit;
}

list ($login,$uid) = userid();
if ($login === false) {
	echo "請登入";
	exit;
}
$map = map_get_single($mid);
$rank = new map_rank();

if ($score == 0 && empty($comment)){
	$st = $rank->del_rank($mid,$uid);
	if ($st === false)
		$msg = "刪除失敗";
} else {
	list ($st, $msg) = $rank->set_rank($mid,$uid,$score,$comment);
}
if ($st === false)
	echo $msg;
else
	echo "評價成功";
