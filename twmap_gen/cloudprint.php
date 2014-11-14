<?php
require_once "config.inc.php";
session_start();
?>
<html>
  <head>
	    <title>地圖產生器-雲端列印</title>
		  <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
 </head>
<body>
<?php
if (!isset($_GET['mid'])){
	echo "<h1>需要 mid 喔</h1>";
	exit(0);
}
if (!isset($_SESSION['cloud_print_session']) || !(isset($_SESSION['cloud_print_session']) && time() < $_SESSION['cloud_print_expire'])) {
	$_SESSION['cloud_print_mid'] = $_GET['mid'];
	header("location: xuite_auth.php");
	exit;
}
//

$mid = $_GET['mid'];
$map = map_get_single($mid);
if ($map == null ) {
	echo "<h1>無此 map".print_r($_GET,true)."</h1>";
	exit(0);
}
$map_filename = str_replace(".tag.png",".pdf",$map['filename']);
if (!file_exists($map_filename)) {
	echo "檔案不存在";
	exit;
}

$xuite = $_SESSION['cloud_print_session'];
//$attr = $xuite->getMe();
print_r($attr);
// 1. 取得目錄資訊: /地圖產生器/print
$folder_info = $xuite->getMetadata("/地圖產生器/print",'folder');
if (empty($folder_info['rsp']['self'])) {
	//  1.1 建立地圖產生器目錄: 
	echo "mkdir";
	$result = $xuite->mkdir_p("/地圖產生器/print");
	//print_r($result);
	$folder_info = $xuite->getMetadata("/地圖產生器/print",'folder');
	//print_r($folder_info);
	if (empty($folder_info['rsp']['self'])) {
		echo "failed to create folder";
		exit;
	}
}
// 檢查是否檔案已經存在
$file_info = $xuite->getMetadata("/地圖產生器/print/".basename($map_filename),'file');
if (empty($file_info['rsp']['file'])) {
	//print_r($folder_info);
	$parent = $folder_info['rsp']['self'][0]['key'];
	//echo $parent;
	$prepared = $xuite->prepare_upload($parent);
	if ($prepared['ok'] != 1) {
		echo "failed to prepare to upload";
		exit;
	}
	// 2. 上傳檔案
	$uploaded = $xuite->upload($prepared, $map_filename);
	//print_r($uploaded);
}

// 3. 取得咧印碼
$file_info = $xuite->getMetadata("/地圖產生器/print/".basename($map_filename),'file');
$filekey = $file_info['rsp']['file'][0]['key'];
$rsp = $xuite->print_code_get($filekey);
$code = $rsp['rsp']['code'];
$expire = $rsp['rsp']['expire'];
echo "<img src=imgs/HAMI.png>";
echo "<h1>本圖的列印碼是 $code 可用到 ". date("Y-m-d H:i", $expire) . "</h1>";
echo "請到 ibon 去列印吧! 不會請參考";
echo "<a href='http://blog.xuite.net/xuite.net/xuite/65797181-%E3%80%8A%E6%95%99%E5%AD%B8%E3%80%8B%E9%9B%B2%E7%AB%AF%E8%B3%87%E6%96%99%E6%AB%83%20-%20ibon%E5%88%97%E5%8D%B0%20%E4%BD%BF%E7%94%A8%E8%AA%AA%E6%98%8E' target=_blank>說明</a>.";
echo "<a href='xuite_auth.php?logout=1'>登出</a>";
exit;


