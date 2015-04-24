<?php
// $Id: logout.php 302 2012-10-29 08:18:22Z happyman $
session_start();

require_once("config.inc.php");
// logout facebook
//
// print("[".$_SESSION['mylogin']['type']."]");
if ($_SESSION['mylogin']['type'] == 'facebook') {
	unset($_SESSION['mylogin']['type']);
	// 不真的去登出 facebook
	//$session = $facebook->getUser();
	//	if ($session) {
	//		header("Location: ".$facebook->getLogOutUrl( array('next'=> $site_url . $site_html_root . "/login.php")));
	//	} 

} else if ($_SESSION['mylogin']['type'] == 'xuite') {
//	$session = $_SESSION['mylogin']['session'];
//	$xuite->setData($session);
//	header("Location: ". $xuite->getRefreshURL());
}

$_SESSION = array();
$_SESSION['loggedin']=false;
session_destroy();
session_commit();
?>
<html>
<head>
<meta http-equiv="REFRESH" content="1;url=login.php">
<title>Log Out</title>
</head>
<body>
Logout...
</body>
