<?php
// $Id: main.php 297 2012-06-27 04:32:26Z happyman $
session_start();
if (empty($_SESSION['loggedin'])) {
	// 如果從地圖瀏覽器導過來
	$_SESSION['redirto'] = $_SERVER["REQUEST_URI"];
	header("Location: login.php");
	exit(0);
}
// 如果從地圖瀏覽器導過來
if (isset($_GET['tab'])) {
	$jump = intval($_GET['tab']);
	if ($jump < 0 || $jump > 4 ) $jump = 1;

	$_SESSION['makeparam'] = $_GET;
	$_SESSION['initial_tab'] = $jump;
	echo '<script>location.replace("main.php")</script>';
	exit;
}

require_once("config.inc.php");
$smarty->assign("twmap_gen_version", $twmap_gen_version);
$smarty->assign("site_root_url", $site_url . $site_html_root);
echo $smarty->fetch("header.html");

// main body

if (isset($_SESSION['initial_tab']) && isset($_SESSION['makeparam']['mid'])){
	$lastest_mid = "&mid=".$_SESSION['makeparam']['mid'];
	$initial_tab = $_SESSION['initial_tab'];
} else {
	// default 顯示最新地圖
	$maps = map_get_lastest_by_uid(1,$_SESSION['uid']);
	if (count($maps)==1) 
		$lastest_mid = "&mid=".$maps[0]['mid'];
	else
		$lastest_mid = "";
	$initial_tab =  0;
}
$smarty->assign("user_icon", 'imgs/icon_'.$_SESSION['mylogin']['type']. '.png');
$smarty->assign("user_email", $_SESSION['mylogin']['email'] );
$smarty->assign("user_nickname", $_SESSION['mylogin']['nick'] );
$smarty->assign("lastest_mid", $lastest_mid );
$smarty->assign("initial_tab", $initial_tab );
$smarty->assign("browser_url", $TWMAP3URL );
$smarty->assign("loggedin", $_SESSION['loggedin'] );

echo $smarty->fetch("main.html");
