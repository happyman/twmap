<?php

// exportkml.php: show KML donwload page & 
// ?kml=1 => ALL kml
// ?kml=1&bound=x,y,x1,y1
// ?bound=x,y,x1,y1, show page with bound link

require_once("../config.inc.php");

if (isset($_REQUEST['bound'])){
	$bound = 1;
	list($x,$y,$x1,$y1)=explode(",",$_REQUEST['bound']);
	$bound_str = sprintf("%06f,%.06f,%.06f,%.06f",$x,$y,$x1,$y1);
}else{
	$bound = 0;
}
// pass owner option	
list ($st,$uid) = userid();
if ($st === true)
	$owner = $uid;
else
	$owner = 0;

if (isset($_REQUEST['kml']) && $_REQUEST['kml'] == 1) {
	// output kml format
	$cmd = sprintf("php cli_point2kml.php %s -o %d",($bound)?" -b $bound_str" : "",$owner);
	if (!isset($_REQUEST['debug'])) {
		header('Content-type: application/vnd.google-earth.kml+xml');
		header('Cache-Control: ');  //leave blank to avoid IE errors
		header('Pragma: ');  //leave blank to avoid IE errors
		header('Content-Disposition: attachment; filename="twmap_export.kml"');
		header('Content-Transfer-Encoding: binary');
	}
	system($cmd);
	if (isset($_REQUEST['debug'])){
		printf( "/* %s \n*/\n",$cmd);
	}
	//echo $cmd;
} else {
	
	?>
	<html>
	<head><title>TWMAP points data exporter</title><meta charset="UTF-8">
	<body>
	<p>
	<hr>
	<h1>下載圖資</h1>
	<p>地圖產生器的圖資是由小花 2010 年整裡的日治時代原點為基礎, 持續更新點位狀態及新增登山的各類興趣點, 期望提供讓山域活動者有更多有用資訊。
	<ul>
	<li><a href="?kml=1" target='kml'>下載所有點位資料</a>
	<?php
	if ($bound){
		printf("<li><a href='?kml=1&bound=%s' target=kml>下載範圍內的點位資料</a>",$_REQUEST['bound']);	
		printf("  (%s)",$bound_str);
	}
	?>
	</ul>
	<a href="uploadpage.php">上傳行跡</a>(beta)
	</body>
	</html>
	<?php
}
