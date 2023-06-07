<?php
// auth.php use hybridauth3
// 2021.3.18

require_once("../../config.inc.php");
$config = require("../../config-hybridauth.php");
use Hybridauth\Hybridauth;
use Hybridauth\HttpClient;
use Hybridauth\Storage\Session;

// 設定為開啟新視窗
if ( isset($_REQUEST['action']) && $_REQUEST['action'] != "logout" && (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] == 1)) {

if (isset($_SESSION['redirto']) && !empty($_SESSION['redirto'])) 
	out_ok("redir", $_SESSION['redirto']);
else
	out_ok("ok", "../../main.php");
	exit();
}

$hybridauth = new Hybridauth($config);
$storage = new Session();

$mylogin = array();
$provider = isset($_SESSION['mylogin']['type']) ? $_SESSION['mylogin']['type'] : (isset($_REQUEST['provider']) ? $_REQUEST['provider'] : "");
if (!empty($provider)){
	$storage->set('provider', $provider);
}
if ($provider = $storage->get('provider')) {
	$hybridauth->authenticate($provider);
	$storage->set('provider', null);
}

$adapter = $hybridauth->getAdapter($provider);

if ( isset($_REQUEST['action']) && $_REQUEST['action'] == 'logout' ) {
	$adapter->disconnect();
	out_ok("登出","../../logout.php");
	exit;
}

// return Hybrid_User_Profile object intance
$user_profile = $adapter->getUserProfile();

if (isset($user_profile->email) && !empty($user_profile->email) && isset($user_profile->displayName)) {
	$mylogin['email'] = $user_profile->email;
	$mylogin['type'] = $provider;
	$mylogin['nick'] = $user_profile->displayName;

} else {
	$adapter->disconnect();
	out_err("沒有 email 資訊, 登入失敗 " . print_r($user_profile, true));
}
$_SESSION['loggedin'] = 1;
$_SESSION['mylogin'] = $mylogin;
$row = login_user($mylogin);
if ($row === false){
	out_err('登入資訊有誤 ' . print_r($mylogin, true));
}
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
exit();
}
function out_ok($str,$url) {
	header("Location: $url");
?>
<?php
	echo "<h1>$str</h1>";
}
