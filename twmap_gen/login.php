<?php
// $Id: login.php 302 2012-10-29 08:18:22Z happyman $
if (!file_exists("config.inc.php")) {
	exit("please create config.inc.php from config.inc.php.sample");
}
include_once("config.inc.php");
session_start();

if ( isset($_SESSION['loggedin']) && $_SESSION['loggedin'] == 1) {
	header("Location: main.php");
	exit;
}
if (isset($_GET['mid'])){
	// 從 show.php 登入
	$lastest_mid = "&mid=".$_GET['mid'];
} else {
	// 若沒參數就取最新的
	$row = map_get_gpx(1);
	if (count($row) == 1 )
		$lastest_mid = "&mid=".$row[0]['mid'];
}



$any_login = 'nothing';
// Create our Application instance (replace this with your appId and secret).
$me = null;

//try {
if(!isset($_GET['openid_mode'])) {
	if(isset($_GET['login'])) {
		$openid = new LightOpenID;
		if (isset($_POST['provider'])) {
			switch($_POST['provider']){ 
			case 'yahoo':
				$openid->identity = 'http://me.yahoo.com/';  
				break;
			case 'google':
				$openid->identity = 'https://www.google.com/accounts/o8/id';  
				break;
			}
			$openid->required = array('namePerson/friendly', 'namePerson/guid','namePerson/first', 'namePerson/last', 'contact/email', 'pref/language');

			header('Location: ' . $openid->authUrl());
		}
	}
	if (isset($_GET['access_token']) && $xuite->session()) {
		echo "xuite";
		$any_login = 'xuite';

	} else if ($_GET['fbtest']) {
		$fbuser = $facebook->getUser();
		if ($fbuser) {
			try {
				$any_login = 'fb';
			} catch ( OAuthException $e) {
				// do nothing
			}
		}
	}

	//	login_html();

} elseif($_GET['openid_mode'] == 'cancel') {
	echo 'User has canceled authentication!';
} else {
	$openid = new LightOpenID;
	if ($openid->validate()) {
		$any_login = "openid";
		if (strstr($openid->identity,"yahoo")) {
			$any_login = "yahoo";
		} else {
			$any_login = "google";
		}

	}
}
// login
// save to login session
$mylogin = array();
switch ($any_login) {
case 'fb':
	$uid = $facebook->getUser();
	$me = $facebook->api('/me');
?>
			<!--
			<pre><?php print_r($me); ?></pre>
			-->
<?php
	echo "facebook";
	$mylogin['email'] = $me['email'];
	if (!strstr($mylogin['email'],'@')) {
?>
			<img src="https://graph.facebook.com/<?php echo $uid; ?>/picture">
<?php
		echo "Sorry ". $me['name'] ." can't retrieve email, so please use googoe or yahoo";
		echo "<a href='login.php'>Login again</a>";
		error_log($me['email']);
		exit;
	}
	$mylogin['type'] = 'facebook';
	$mylogin['nick'] = $me['name'];
	break;
case 'google':
	echo "You login by google";
		/*
			 Array
			 (
			 [namePerson/first] => Happyman
			 [contact/email] => happyman.eric@gmail.com
			 [pref/language] => zh-TW
			 [namePerson/last] => Chiu
			 )
			 echo '<pre>';
			 print_r($openid->getAttributes());
			 echo '</pre>';
		 */
	$attr = $openid->getAttributes();
	$mylogin['email'] = $attr['contact/email'];
	$mylogin['type'] = 'google';
	$mylogin['nick'] = sprintf("%s %s",$attr['namePerson/first'],$attr['namePerson/last']);
	break;
case 'yahoo':
		/*
			 echo "You login by yahoo";
			 echo '<pre>';
			 print_r($openid->getAttributes());
			 echo '</pre>';
			 $attr = $openid->getAttributes();
		 */
	echo "You login by yahoo";
	$attr = $openid->getAttributes();
	$mylogin['email'] = $attr['contact/email'];
	$mylogin['type'] = 'yahoo';
	$mylogin['nick'] = sprintf("%s",$attr['namePerson/friendly']);
	break;
case 'xuite':
	echo "You login by xuite";
	$attr = $xuite->getMe();
	$mylogin['email'] = sprintf("%s@xuite.net",$attr['rsp']['login_user_id']);
	$mylogin['type'] = 'xuite';
	$mylogin['nick'] = $attr['rsp']['login_user_id'];
	$mylogin['session'] = $xuite->session();
	break;
default:
	//echo "必須登入上述任一帳號才可使用";

	$smarty->assign("loggedin", 0 );
	$smarty->assign("login_xuite", $xuite->getLoginUrl($site_url . "/". $_SERVER['REQUEST_URI']));
	$smarty->assign("login_fb",
		$facebook->getLoginUrl(  array(
			'canvas'    => 0,
			'fbconnect' => 1,
			'req_perms' => 'email',
			'redirect_uri' => $site_url . $site_html_root . "/login.php?fbtest=1"

		)));
	$smarty->assign("user_icon", "imgs/icon-map.png");
	$smarty->assign("lastest_mid", $lastest_mid );
	$smarty->assign("site_root_url", $site_url . $site_html_root);


	echo $smarty->fetch('header.html');
	$smarty->display("main.html");
	//login_html();
	//
	//echo "<div align=center>v$twmap_gen_version happyman<br>";
	//echo "</div></body>";
	exit(0);
	break;
}
$_SESSION['loggedin'] = 1;
$_SESSION['mylogin'] = $mylogin;
$row = login_user($mylogin);
$_SESSION['uid'] = $row['uid'];
//
// after login hook
$maps = map_get($row['uid']);
foreach($maps as $map) {
	map_migrate($out_root, $row['uid'], $map['mid']);
}
if (isset($_SESSION['redirto'])) {
	header("Location: ".$_SESSION['redirto']);
	unset($_SESSION['redirto']);
} else {
	header("Location: main.php");
}
function login_html() {
	global $twmap_gen_version, $facebook, $xuite, $site_url, $site_html_root;
?>
<html>
<head>
<title>地圖產生器 v<?php echo $twmap_gen_version; ?></title>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
</head>
<body>
<div align=center>
<h1>歡迎使用地圖產生器</h1>
請登入使用
			<form action="?login" method="post">
			<button type=button style="background: url(imgs/openid-logos.png) repeat scroll -1px -187px rgb(255, 255, 255); height:63px; width:100px" name=provider value=xuite onClick="window.location='<?php echo $xuite->getLoginUrl(); ?>'"></button>
			<button style="background: url(imgs/openid-logos.png) repeat scroll -1px -1px rgb(255, 255, 255); height:63px; width:100px" name=provider value=google>
			</button>
			<button style="background: url(imgs/openid-logos.png) repeat scroll -1px -63px rgb(255, 255, 255); height:63px; width:100px" name=provider value=yahoo>
			</button>

			<button type=button style="background: url(imgs/openid-logos.png) repeat scroll -1px -456px rgb(255, 255, 255); height:63px; width:100px" onClick="window.location='<?php echo $facebook->getLoginUrl(  array(
				'canvas'    => 0,
				'fbconnect' => 1,
				'req_perms' => 'email'

			));?>'" >

			</button>

			</form>
</div>

			<script type="text/javascript">

			var _gaq = _gaq || [];
	_gaq.push(['_setAccount', 'UA-19949015-1']);
	_gaq.push(['_trackPageview']);

	(function() {
		var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
		ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
		var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
	})();

	</script>
<?php
			include("othermap.php");
}
