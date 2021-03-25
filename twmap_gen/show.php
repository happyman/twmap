<?php
// $Id: show.php 362 2013-10-19 02:16:56Z happyman $
// https://ma.ttias.be/php-session-locking-prevent-sessions-blocking-in-requests/
session_start([
    'read_and_close' => true,
]);

require_once("config.inc.php");

$html_head = 1;
if (!isset($_GET['mid'])){
	echo "<h1>尚無圖可顯示</h1>";
	exit(0);
}       

if (isset($_GET['tab']) || isset($_GET['links']) || isset($_GET['zip'])) {
	$html_head = 0;
}

$mid = $_GET['mid'];
if ($mid < 0 ){
	$map = track_get_single($mid * -1);
} else {
	$map = map_get_single($mid);
}

if ($map == null ) {
	echo "<h1>無此 map".print_r($_GET,true)."</h1>";
	exit(0);
}       
// 美化一下 url
if (!isset($_GET['info']) && $html_head == 1){
	header("Location: ". pagelink($map));
	exit;
}
if ($mid < 0 ){
	$smarty->assign("title","檢視航跡:". $map['name']);
	$smarty->assign("description",sprintf("%s 航跡範圍: %s. ", $map['name'],$map['bbox']));
	$smarty->assign("site_root_url", $site_url . $site_html_root);
// $smarty->assign("ogimage", array("thumb.php?mid=$mid&size=s", "thumb.php?mid=$mid&size=m", "thumb.php?mid=$mid&size=l"));
	$smarty->assign("html_head", $html_head);
if ($html_head == 1 ) {
        echo $smarty->fetch("header.html");
        if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] == 1 ) {
                $smarty->assign("user_icon", 'imgs/icon_'.$_SESSION['mylogin']['type']. '.png');
                $smarty->assign("user_email", $_SESSION['mylogin']['email'] );
                $smarty->assign("user_nickname", $_SESSION['mylogin']['nick'] );
                $smarty->assign("lastest_mid", "&mid=$mid" );
                $smarty->assign("initial_tab", 3 );
                $smarty->assign("browser_url", $TWMAP3URL );
                $smarty->assign("loggedin", 1);
        } else { // 沒有登入
                // require_once('lib/fb/facebook.php');
                //require_once('lib/xuite.php');
                $smarty->assign("lastest_mid", "&mid=$mid" );
                $smarty->assign("initial_tab", 2 );
                $smarty->assign("showing", true );
                $smarty->assign("browser_url", $TWMAP3URL );
                $smarty->assign("loggedin", 0);
                // $smarty->assign("login_xuite", $xuite->getLoginUrl());
                /* $smarty->assign("login_fb",
                        $facebook->getLoginUrl(  array(
                                'canvas'    => 0,
                                'fbconnect' => 1,
                                'req_perms' => 'email'

                        )));
		*/
                $smarty->assign("user_icon", "imgs/icon-map.png");
        }
        echo $smarty->fetch("main.html");
        exit;
}

	$smarty->assign('map',$map);
	$gpx_link = $site_url . str_replace($out_root,$out_html_root,sprintf("%s%d/%s_p.gpx",$map['path'],$map['tid'],$map['md5name']));
	$fname = glob(sprintf("%s%d/%s_o.*",$map['path'],$map['tid'],$map['md5name']));
	$orig_link = $site_url . str_replace($out_root,$out_html_root,$fname[0]);
	$path_parts = pathinfo($map['name']);
	$orig_download_name=$path_parts['basename'];
	$gpx_download_name=$path_parts['filename']. "_p.gpx";
	$smarty->assign('gpx_link',$gpx_link);
	$smarty->assign('gpx_download_name',$gpx_download_name);
	$smarty->assign('orig_link',$orig_link);
	$smarty->assign('orig_download_name',$orig_download_name);
	// https://stackoverflow.com/questions/13307499/http-download-file-name
	$smarty->assign("show_link", $TWMAP3URL . "?skml_id=-".$map['tid']);
	$links['page'] = pagelink($map);
	$smarty->assign('links',$links);
	$smarty->display('show_track.html');
	exit(0);
}
// 3. 顯示地圖
// $html_root = $out_html_root . sprintf("/%06d/%d", $map['uid'],$mid);
$html_root = $out_html_root . str_replace($out_root, "", dirname($map['filename']));
// 產生 coord 
if (strstr($map['filename'],'v3p') || strstr($map['filename'],'v2016p')) {
	$ph = 1;
} else {
	$ph = 0;
}

$smarty->assign("title","檢視地圖:". $map['title']);
$smarty->assign("description",sprintf("%s 地圖資訊: %dx%d-%dx%d. ", $map['title'],$map['locX'],$map['locY'],$map['shiftX'],$map['shiftY']));
$smarty->assign("site_root_url", $site_url . $site_html_root);
// $smarty->assign("ogimage", array("thumb.php?mid=$mid&size=s", "thumb.php?mid=$mid&size=m", "thumb.php?mid=$mid&size=l"));
$smarty->assign("html_head", $html_head);
if ($html_head == 1 ) {
	echo $smarty->fetch("header.html");
	if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] == 1 ) {
		$smarty->assign("user_icon", 'imgs/icon_'.$_SESSION['mylogin']['type']. '.png');
		$smarty->assign("user_email", $_SESSION['mylogin']['email'] );
		$smarty->assign("user_nickname", $_SESSION['mylogin']['nick'] );
		$smarty->assign("lastest_mid", "&mid=$mid" );
		$smarty->assign("initial_tab", 3 );
		$smarty->assign("browser_url", $TWMAP3URL );
		$smarty->assign("loggedin", 1);
	} else { // 沒有登入
		//require_once('lib/fb/facebook.php');
		//require_once('lib/xuite.php');
		$smarty->assign("lastest_mid", "&mid=$mid" );
		$smarty->assign("initial_tab", 2 );
		$smarty->assign("showing", true );
		$smarty->assign("browser_url", $TWMAP3URL );
		$smarty->assign("loggedin", 0);
		// $smarty->assign("login_xuite", $xuite->getLoginUrl());
		/* $smarty->assign("login_fb",
			$facebook->getLoginUrl(  array(
				'canvas'    => 0,
				'fbconnect' => 1,
				'req_perms' => 'email'

			)));
 		*/
		$smarty->assign("user_icon", "imgs/icon-map.png");
	} 
	echo $smarty->fetch("main.html");
	exit;
}
// 如果是內嵌
switch($map['flag']) {
case 2:
	// 真正加總
	map_accessed($mid);
	$smarty->assign('map',$map);
	$smarty->display('show_deleted.html');
	exit;
	break;
case 1:
	// 真正加總
	map_accessed($mid);
	$links['page'] = pagelink($map);
	$links['fullmap'] = $site_url . $html_root . "/" . basename($map['filename']);
	if (file_exists(str_replace(".tag.png",".gpx",$map['filename']))) {
		$links['gpx'] = str_replace(".tag.png",".gpx", $links['fullmap']);
		$smarty->assign('gpx_link',$links['gpx']);
	}
	$smarty->assign('map',$map);
	$smarty->assign('links',$links);
	$smarty->display('show_expired.html');
	exit;
	break;
case 0:
default:
	//error_log($map['filename']);
	$files = map_files($map['filename']);
	$imgarr = array();
	foreach($files as $f ) {
		if (preg_match("/_\d+\.png/",$f)) {
			$imgarr[] = $f;
		}
	}
	//error_log(print_r($imgarr,true));
	// 排序一下
	if (count($imgarr)>0)
		usort($imgarr, 'indexcmp');
	//error_log(print_r($imgarr,true));
	// $imgarr 是下載圖檔連結
	if (isset($_GET['links']) && $_GET['links'] == 1) {
		foreach($imgarr as $imgs) {
			echo $site_url . $html_root . "/" . basename($imgs) . "\n";
		}
		exit(0);
	}
	if (isset($_GET['zip']) && $_GET['zip'] == 1) {
		$name = sprintf("/tmp/twmap-%d_%dx%s-%dx%d.zip",$mid,$map['locX'],$map['locY'],$map['shiftX'],$map['shiftY']);
		zipdownload($name,$imgarr);
		//register_shutdown_function('cleanupfile',$name);
		exit(0);
	}
	// 真正加總
	map_accessed($mid);

	if (strstr($map['filename'],'v3p')) {
		    $map['ph'] = 1;
	} else {
		    $map['ph'] = 0;
	}

	$links['page'] = pagelink($map);
	$links['download'] = $links['page'] . "&links=1";
	$links['zip'] = $links['page'] . "&zip=1";
	$links['fullmap'] = $site_url . $html_root . "/" . basename($map['filename']);
	$links['fullmap_path'] =  $site_url . $html_root;
	$links['download_link'] = $site_url . $site_html_root . "/show.php?mid=".  $map['mid'] . "&links=1";
	//$links['kmz'] = $site_url . $site_html_root . "/kmz.php?x=". $map['locX'] ."&y=" .$map['locY'] . "&tx=".$map['shiftX'] . "&ty=". $map['shiftY'] . "&title=".urlencode($map['title']). "&file=".$html_root."/".basename($map['filename'] . "&mid=$mid");
	if (map_file_exists($map['filename'], 'gpx')) {
		$links['gpx'] = $links['fullmap_path'] . "/". basename(map_file_name($map['filename'], 'gpx'));
	}
	if (map_file_exists($map['filename'], 'kmz')) {
		//$links['kmz'] = $links['fullmap_path'] . "/". basename(map_file_name($map['filename'], 'kmz'));
		$links['kmz'] = "kmz2.php?mid=". $map['mid'];
	}
	if (map_file_exists($map['filename'], 'pdf')) {
		$links['pdf'] = $links['fullmap_path'] . "/". basename(map_file_name($map['filename'], 'pdf'));
	}
	if (count($imgarr)>0) {
		foreach($imgarr as $imgs ) {
			$links['simgs'][] = $links['fullmap_path'] . "/" . basename($imgs);
			$tdata[] = sprintf("<a href='$html_root/%s' rel='gallery' class='pirobox_gall'><img border=0 src='$html_root/%s' width=%s></a>\n",basename($imgs),basename($imgs), round(500/$map['pageX']));
		}	
		$smarty->assign("imgdata",$tdata);
	} else {
		$smarty->assign("imgdata",array());
	}
	$smarty->assign("map",$map);
	$smarty->assign("links",$links);
	$smarty->display("show_ok.html");
	exit;
	// ad();
	//footer();
	break;
}
function pagelink($map) {
	global $site_url, $site_html_root,$mid;
	if ($mid < 0 ) {
		$info = sprintf("%s",$map['bbox']);
		return $site_url . $site_html_root . "/show.php?info=".urlencode($info) ."&mid=" . $mid;
	} 
	$info = sprintf("%dx%s-%dx%d",$map['locX'],$map['locY'],$map['shiftX'],$map['shiftY']);
	return  $site_url . $site_html_root . "/show.php?info=".urlencode($info)."&version=".$map['version']. "&mid=$mid";
}
function zipdownload($name, $imgs) {
	ignore_user_abort(1);
	set_time_limit(0);

	$zipFileName = $name;
	$cmd = sprintf("zip -D -j %s %s",$zipFileName, implode(" ",$imgs));
	exec($cmd,$out,$ret);
	if ($ret == 0 ) {
		header("Pragma: public");
		header("Expires: 0");
		header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
		header("Cache-Control: private",false);
		header("Content-Type: application/zip");
		header("Content-Disposition: attachment; filename=".basename($zipFileName).";" );
		header("Content-Transfer-Encoding: binary");
		header("Content-Length: ".filesize($zipFileName));
		readfile("$zipFileName");

	} 
	cleanupfile($zipFileName);
}
function cleanupfile($name) {
	unlink($name);
}

