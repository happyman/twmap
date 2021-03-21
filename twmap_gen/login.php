<?php
// $Id: login.php 302 2012-10-29 08:18:22Z happyman $
if (!file_exists("config.inc.php")) {
	exit("please create config.inc.php from config.inc.php.sample");
}
include_once("config.inc.php");
if(!isset($_SESSION)) { 
        session_start(); 
} 

if ( isset($_SESSION['loggedin']) && $_SESSION['loggedin'] == 1) {
	if ( isset($_SESSION['redirto'])) {
		unset($_SESSION['redirto']);
		session_write_close();
		header("Location: ". $_SESSION['redirto']);
	} else
		header("Location: main.php");
	exit;
}
if (isset($_GET['mid'])){
	// 從 show.php 登入
	$lastest_mid = "&mid=".$_GET['mid'];
} else {
	// 若沒參數就取最新的
	$row = map_get_lastest(1,0);
	if (count($row) == 1 )
		$lastest_mid = "&mid=".$row[0]['mid'];
}

$config = include("config-hybridauth.php");

$smarty->assign("fb_appid",$config['providers']['Facebook']['keys']['id']);
$smarty->assign("login_fb","lib/Hybrid/auth.php?provider=facebook");
$smarty->assign("loggedin", 0 );
$smarty->assign("user_icon", "imgs/icon-map.png");
$smarty->assign("lastest_mid", $lastest_mid );
$smarty->assign("site_root_url", $site_url . $site_html_root);
$smarty->assign("title", "登入");

echo $smarty->fetch('header.html');
$smarty->display("main.html");
