<?php

// exportkml.php: show KML donwload page & 
// ?kml=1 => ALL kml
// ?kml=1&bound=x,y,x1,y1
// ?bound=x,y,x1,y1, show page with bound link

require_once("../config.inc.php");

if (isset($_REQUEST['bound'])){
	$bound = 1;
	list($x,$y,$x1,$y1)=explode(",",$_REQUEST['bound']);
	$bound_str = sprintf("%.06f %.06f %.06f %.06f",$y,$x,$y1,$x1);
	// TOP=25.24917 BOTTOM=25.11332 LEFT=121.48012 RIGHT=121.63218 for Rudy's tool
	// https://github.com/alpha-rudy/taiwan-topo/commit/02b13b14ac562b38eac7638183e6de8730f796a5
	$bound_str2 = sprintf("TOP=%.06f BOTTOM=%.06f LEFT=%.06f RIGHT=%.06f",$y,$y1,$x,$x1);
	$bound_cmd = sprintf("%.06f,%.06f,%.06f,%.06f",$x,$y,$x1,$y1);
}else{
	$bound = 0;
}
// pass owner option	
list ($st,$uid) = userid();
if ($st === true)
	$owner = $uid;
else
	$owner = 0;

if (isset($_REQUEST['kml'])) {
	// output kml format
	$outfile=tempnam("/tmp", "exportkml_");
	$cmd = sprintf("php cli_point2kml.php %s -o %d > %s",($bound)?" -b $bound_cmd" : "",$owner,$outfile);
	exec($cmd);
	switch ($_REQUEST['kml']) {
		case 1:
			if (!isset($_REQUEST['debug'])) {
				header('Content-type: application/vnd.google-earth.kml+xml');
				header('Cache-Control: ');  //leave blank to avoid IE errors
				header('Pragma: ');  //leave blank to avoid IE errors
				header('Content-Disposition: attachment; filename="twmap_export.kml"');
				header('Content-Transfer-Encoding: binary');
			}
			// system($cmd);
			readfile($outfile);
			unlink($outfile);
			if (isset($_REQUEST['debug'])){
				printf( "/* %s \n*/\n",$cmd);
			}
		break;
		case 2:
			// output GPX
			$outfile_gpx = $outfile . ".gpx";
			gpsbabel_convert($outfile,"kml",$outfile_gpx,"gpx");
			header('Content-Type: application/gpx+xml;');
			header('Cache-Control: ');  //leave blank to avoid IE errors
			header('Pragma: ');  //leave blank to avoid IE errors
			header('Content-Disposition: attachment; filename="twmap_export.gpx"');
			header('Content-Transfer-Encoding: binary');
			readfile($outfile_gpx);
			unlink($outfile);
			unlink($outfile_gpx);
	}
	//echo $cmd;
} else {
	
	?>
	<html>
	<head><title>TWMAP points data exporter</title><meta charset="UTF-8">
	<body>
	<p>
	<hr>
	<h2>下載圖資</h2>
	<p>地圖產生器的興趣點圖資是由小花 2010 年整裡的日治時代原點為基礎, 持續更新點位狀態及新增登山的各類興趣點, 期望提供讓山域活動者有更多有用資訊。
	<ul>
	<li>下載所有點位資料 <a href="?kml=1" target='kml'>kml 格式</a>、<a href="?kml=2" target='kml'>gpx 格式</a>
	<?php
	if ($bound){
		printf("<li>下載範圍內的點位資料 <a href='?kml=1&bound=%s' target=kml>kml</a>、<a href='?kml=2&bound=%s' target=kml>gpx</a>",
			$_REQUEST['bound'],$_REQUEST['bound']);	
		// printf("  (%s)",$bound_str);
		printf("<li><b>Bound(taiwan-topo)</b>: %s\n",$bound_str2);
		printf("<li><b>Bound(tilestache-clean)</b>: %s\n",$bound_str);
	}
	?>
	<li><a href="https://tile.happyman.idv.tw/~happyman/gpx_map/" target=_blank>Happyman.map(離線圖資)</a>
	</ul>
	<hr>
<?php

list ($login,$uid) = userid();
if ($login === false) {
        printf("<a href='%s/main.php?return=twmap3' target=_top>請登入</a>",$site_html_root);
} else {
?>
	<a href="uploadpage.php">上傳行跡</a>
<?php
}
?>
	</body>
	</html>
	<?php
}
