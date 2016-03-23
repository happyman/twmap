<?php
require_once "../config.inc.php";
require_once  "tiles.inc.php";

if (php_sapi_name() == "cli") { 
	$x = isset($argv[1]) ? intval($argv[1]) : 0;
	$y = isset($argv[2]) ? intval($argv[2]) : 0;
	$sx = isset($argv[3]) ? intval($argv[3]) : 0;
	$sy = isset($argv[4]) ? intval($argv[4]) : 0;
	$debug = 1;
} else {
	$x = $_GET['x'];
	$y = $_GET['y'];
	$sx = $_GET['sx'];
	$sy = $_GET['sy'];
	$debug = 1;
}

if ($x == 0 || $y == 0 ) {
	echo "$argv[0] x y sx sy\n";
	echo " regenerate v3 1x1 tile cache\n";
	exit(0);
}
for ($i=0 ; $i<1000 ; $i++) {
	    echo ".";
			    ob_flush();
			    flush();
					//    sleep(1);
}
for ($i=$x;$i<$x+$sx;$i++){
	for($j=$y;$j>$y-$sy;$j--) {
		warn($i*1000,$j*1000);
	}
}
function warn($x,$y) {
	global $tilepath;
	global $debug; 
	// 2 force regen
	list ($ret, $img, $cached) = img_from_tiles($tilepath, $x, $y, 1, 1, 16, 0, $debug,  $tmppath, $tilecachepath,  2);
	if ($ret === true ) {
		//@unlink($img);
		if ($cached == "cached") return;
		echo "$x $y ok\n";
	}
	else
		echo "$x $y skip\n";
}
