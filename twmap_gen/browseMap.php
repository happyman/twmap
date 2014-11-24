<?php
// 1x1 km tile cache browser
// use with lib/cli_tile_regen.php

if (php_sapi_name() == "cli") {
	$x=$argv[1];
	$y=$argv[2];
	$sx=$argv[3];
	$sy=$argv[4];
	$ph=$argv[5];
} else {
	$x=$_REQUEST['x'];
	$y=$_REQUEST['y'];
	$sx=$_REQUEST['sx'];
	$sy=$_REQUEST['sy'];
	$ph = $_REQUEST['ph'];

	if ($_REQUEST['type'] == 'image') {
		outimage($x,$y,$ph);
		exit(0);
	} else if ($_REQUEST['type'] == 'image256') {
		outimage2($x,$y,$ph);
		exit(0);
	}
}
if ($x == 0 || $y == 0  || $sx == 0 || $sy == 0 ) {
	echo "error params";
	exit;
}
?>
<html>
<style>
td {
	width: 315px;
	height: 315px;
	background-size: 100%;
}
body, html {
	padding: 0px;
		margin: 0px;
			border: 0px none;
					}
</style>
	<script type="text/javascript" src="js/jquery-1.4.4.min.js"></script>
<body>
<?
// time xvfb-run  -a -s "-screen 0 640x480x16" wkhtmltoimage  --format png 
// --disable-smart-width --width 6300 --crop-h 6300 --crop-x 0 --crop-y 0 
// 'http://localhost/~happyman/twmap_gen/browseMap.php?x=304&y=2765&sx=20&sy=20&ph=0' 
// /tmp/kk1.png
//
$tw = $sx *315;
$ty = $sy *315;
echo "<table width=$tw height=$ty border=0 cellspacing=0 cellpadding=0>";
for($j=$y;$j>$y-$sy;$j--) {
	echo "<tr>";
	for($i=$x;$i<$x+$sx;$i++) {
		printf('<td style="background: url(\'browseMap.php?type=image&x=%d&y=%d&ph=%d\') center no-repeat; width: 315px; height: 315px;">%s</td>' . "\n",
			$i,$j,$ph , "<div class=reload><span class='gridx'>$i</span>,<span class='gridy'>$j</span></div></td>");
		//
	}
}
echo "</table>";
?>
<script>
$(".reload").click(function(){
		var x =$(this).find(".gridx").text();
		var y =$(this).find(".gridy").text();
		//console.log($(this).find(".gridx").text());
	//	alert(x + "," + y);
		var url = "lib/cli_tile_regen.php?x="+x+"&y="+y+"&sx=1&sy=1";
	console.log(url);
		window.open(url, "_blank");
});
</script>
</body></html>";
<?php
function outimage($x,$y,$ph) {
	$path = sprintf("/mnt/twmapcache/cache/16/%s/%d/%d_%d.png",($ph==1)?"ph":"tw",$x,$x,$y);
	if (file_exists($path)) {
		header("Content-type: image/png");
		header("X-file-location: $path");
		readfile($path);
	} else {
		header("Content-type: image/jpeg");
		readfile("imgs/null.jpg");
	}
}
function outimage2($ix,$iy,$ph=0) {
	$x=$ix+150;
	$y=2800-$iy;
	$path = sprintf("/mnt/twmapcache/cache/16/%s/%d/%d_%d.png",($ph==1)?"ph":"tw",$x,$x,$y);
	if (file_exists($path)) {
		header("Content-type: image/png");
		header("X-file-location: $path");
		readfile($path);
	} else {
		header("Content-type: image/jpeg");
		readfile("imgs/null.jpg");
	}
}
