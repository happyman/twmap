<?php
$config = require("../../config-hybridauth.php");
require_once("load.inc.php");
session_start();

// 設定為開啟新視窗
if ( isset($_REQUEST['action']) && $_REQUEST['action'] != "logout" && (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] == 1)) {

if (isset($_SESSION['redirto']) && !empty($_SESSION['redirto'])) 
	out_ok("redir", $_SESSION['redirto']);
else
	out_ok("ok", "../../main.php");
	exit();
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
if ( isset($_REQUEST['action']) && $_REQUEST['action'] == 'logout' ) {
	$adapter->logout();
	out_ok("登出","../../logout.php");
	exit;
}
//$adapter = $hybridauth->authenticate( "Xuite" );
//  "https://yahoo.com/"))

// return Hybrid_User_Profile object intance
$user_profile = $adapter->getUserProfile();

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
// 看看是不是有漏搬的檔案
$maps = map_get_ids($row['uid'],10);
foreach($maps as $map) {
  map_migrate($out_root, $row['uid'], $map['mid']);
}

if (isset($_SESSION['redirto']) && !empty($_SESSION['redirto'])) {
        out_ok("redir", $_SESSION['redirto']);
	unset($_SESSION['redirto']);
}
else
        out_ok("ok", "../../main.php");
exit();


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
