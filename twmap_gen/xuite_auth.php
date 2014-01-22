<?php
require_once "config.inc.php";
session_start();

if ($_GET['logout'] == 1) {
	unset($_SESSION['cloud_print_session']);
	unset($_SESSION['cloud_print_expire']);
	unset($_SESSION['cloud_print_mid']);
	echo "bye";
	exit;
}
if (isset($_GET['access_token']) && $xuite->session()) {
	$session = $xuite->session();
	$_SESSION['cloud_print_session'] = $xuite;
	$_SESSION['cloud_print_expire'] = $session['expire_in'];
	header("Location: cloudprint.php?mid=". $_SESSION['cloud_print_mid']);
	exit;
}

$url = $xuite->getLoginUrl($site_url . $_SERVER['REQUEST_URI']);
header("Location: $url");


