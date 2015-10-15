<?php
$config = require("../../config-hybridauth.php");
require_once("Hybrid/Auth.php");
session_start();

// 設定為開啟新視窗
if ( $_REQUEST['action'] != "logout" && (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] == 1)) {
	out_ok("ok","../../main.php");
	exit;
}

$hybridauth = new Hybrid_Auth( $config );

$mylogin = array();
$provider = isset($_SESSION['mylogin']['type']) ? $_SESSION['mylogin']['type'] : $_REQUEST['provider'];
switch($provider) {
case 'google':
	$adapter = $hybridauth->authenticate( "Google" );
	break;
case 'yahoo':
	$adapter = $hybridauth->authenticate( "OpenID", array( "openid_identifier" => "https://me.yahoo.com"));
	break;
case 'facebook':
	$adapter = $hybridauth->authenticate( "Facebook" );
	break;
case 'xuite':
	$adapter = $hybridauth->authenticate( "Xuite" );
	break;
default:
	out_err("不正確的登入種類");	
	break;
}
if ($_REQUEST['action'] == 'logout' ) {
	$adapter->logout();
	out_ok("登出","../../logout.php");
	exit;
}
//$adapter = $hybridauth->authenticate( "Xuite" );
//  "https://yahoo.com/"))

// return Hybrid_User_Profile object intance
$user_profile = $adapter->getUserProfile($config['providers']['Xuite']);

require_once("../../config.inc.php");

if (isset($user_profile->email) && isset($user_profile->displayName)) {
	$mylogin['email'] = $user_profile->email;
	$mylogin['type'] = $_REQUEST['provider'];
	$mylogin['nick'] = $user_profile->displayName;

} else {
	$adapter->logout();
	out_err("沒有 email 資訊, 登入失敗");
}
$_SESSION['loggedin'] = 1;
$_SESSION['mylogin'] = $mylogin;
$row = login_user($mylogin);
$_SESSION['uid'] = $row['uid'];
//
//// after login hook
$maps = map_get($row['uid']);
foreach($maps as $map) {
  map_migrate($out_root, $row['uid'], $map['mid']);
  }
  if (isset($_SESSION['redirto'])) {
      header("Location: ".$_SESSION['redirto']);
      unset($_SESSION['redirto']);
  } else {
      header("Location: ../../main.php");
}

function out_err($str="") {
?>
<html>
<h1>認證失敗 <?php echo $str;?></h1>
<?php
}
function out_ok($str,$url) {
	header("Location: $url");
?>
<?php
	echo "<h1>$str</h1>";
}
