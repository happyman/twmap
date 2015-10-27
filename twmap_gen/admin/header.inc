<?php

require_once("../config.inc.php");

if (empty($_SESSION['loggedin'])) {
	// 如果從地圖瀏覽器導過來
	$_SESSION['redirto'] = $_SERVER["REQUEST_URI"];
	header("Location: ". $site_html_root . "/login.php");
	exit(0);
}

