<?php
// $Id: backend_del.php 282 2012-05-17 08:49:51Z happyman $
// 1. check login
session_start([
        'read_and_close' => true,
]);

if (empty($_SESSION['loggedin'])) {
	header("Location: login.php");
	exit(0);
}

require_once("config.inc.php");
// 2. check _POST
$_inp = $_POST;
if (!isset($_inp['mid'])){
	error_out(print_r($_POST, true) . " requires mid");
}

// 3. 檢查 user 是否能刪除此檔
$map = map_get_single($_inp['mid']);
if ($map == null ) {
	error_out("no such map". $_inp['mid']);
}
if ($map['uid'] != $_SESSION['uid']) {
	error_out("you are not the owner");
}
// 3.1 正在搬移資料結構, 或重新整理
$block_msg  = map_blocked($out_root, $_SESSION['uid']);
if ($block_msg != null ) {
	        error_out($block_msg);
}

// 4. 真的刪除/回收
if (isset($_inp['op']) && $_inp['op'] == 'recycle') 
	$ok = map_expire($_inp['mid']);
else
	$ok = map_del($_inp['mid']);
if ($ok === FALSE) {
	error_out("delete/expire fail");
}
sleep(1);
$mid = $_inp['mid'];
ok_out("$mid deleted",$mid);
