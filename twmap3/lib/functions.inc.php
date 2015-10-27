<?php
require_once(dirname(dirname(__FILE__))."/config.inc.php");
function is_admin() {
	global $CONFIG;
	$admin = $CONFIG['admin'];
  if (!isset($_SESSION['mylogin'])) return false;
	if (!in_array($_SESSION['uid'],$admin)){
		return false;
	}
	return true;
}

function login_info() {
	global $CONFIG;
	if (!isset($_SESSION['mylogin'])) return array(false,"not logged in");
	return array(true, array(
"uid" => $_SESSION['uid'],
"user_icon"=> $CONFIG['site_twmap_html_root'] . 'imgs/icon_'.$_SESSION['mylogin']['type']. '.png',
"user_email"=> $_SESSION['mylogin']['email'],
"user_nickname"=>$_SESSION['mylogin']['nick'] ));

}
