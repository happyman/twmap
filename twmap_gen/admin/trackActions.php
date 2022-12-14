<?php
require_once("header.inc");
require_once("../config.inc.php");

list($st, $uid) = userid();
if ($st !== true){
	header("Location: ". $site_html_root . "/login.php");
	exit();
}

$action = $_GET['action'];

switch($action){
	case 'delete':
			$rs = track_expire($uid, $_REQUEST['tid']);
			break;
	case 'update':
			$rs = track_update($uid,$_REQUEST['tid'],$_REQUEST['name'],$_REQUEST['contribute'],is_admin());
			break;
	case 'list':
	default:
			$rs = track_get($uid,$_GET['jtSorting'],is_admin());
			break;
}
if ($rs === false) {
		$jTableResult = array();
		$jTableResult['Result'] = "ERROR";
		$jTableResult['Message'] = "sql error";
		print json_encode($jTableResult);
		return;
	}
$jTableResult = array();
$jTableResult['Result'] = "OK";
if ($_REQUEST['action'] == 'update')
		$jTableResult['Record'] = track_get_single($_REQUEST['tid']);
else
	$jTableResult['Records'] = track_get($uid,$_GET['jtSorting'],is_admin());
print json_encode($jTableResult);

//$trks = track_get($uid);
