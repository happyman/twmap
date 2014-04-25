<?php

require_once("config.inc.php");
if (!isset($_GET['mid'])){
	echo "<h1>尚無圖可顯示</h1>";
	exit(0);
}

$mid = $_GET['mid'];
$map = map_get_single($mid);


if ($map == null ) {
	echo "<h1>無此 map".print_r($_GET,true)."</h1>";
	exit(0); 
}
$html_root = $out_html_root . str_replace($out_root, "", dirname($map['filename']));
$full_map_link = $site_url . $html_root . "/" . basename($map['filename']);
$size = getImageSize($map['filename']);
$smarty->assign("img_src", $full_map_link);
$smarty->assign("img_size", $size[3]);
$smarty->assign("map", $map);

$smarty->display('browseImage.html');

