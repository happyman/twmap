<?php

// find 16 -type f -a \! -links 0 |xargs -n 1 -P 16 php genfakezoom.php 
$zoom = 17;
$fname = $argv[1];
if (is_link($fname) || filesize($fname)==0) {
	exit("do nothing for $fname");
}
list ($x,$y) = explode("_",str_replace(".png","",basename($fname)));

printf("x=%d y=%d\n",$x,$y);
// see http://www.maptiler.org/google-maps-coordinates-tile-bounds-projection/
// 
$newname = sprintf("%d_%d_%%02d.jpg",$x * 2, $y * 2);
// x,y先放大兩倍, 再切成四個檔案
$cmd = sprintf("convert -resize 512x512 -crop 256x256 +repage jpg:%s %d/%s;",$fname, $zoom, $newname);
exec($cmd,$out,$ret);
if ($ret == 0) {
	$cmd = sprintf("mv %d/%d_%d_00.jpg %d/%d_%d.png;",$zoom, $x*2,$y*2, $zoom, $x*2, $y*2);
	$cmd .= sprintf("mv %d/%d_%d_01.jpg %d/%d_%d.png;",$zoom, $x*2,$y*2, $zoom, $x*2+1, $y*2);
	$cmd .= sprintf("mv %d/%d_%d_02.jpg %d/%d_%d.png;",$zoom, $x*2,$y*2, $zoom, $x*2, $y*2+1);
	$cmd .= sprintf("mv %d/%d_%d_03.jpg %d/%d_%d.png;",$zoom, $x*2,$y*2, $zoom, $x*2+1, $y*2+1);
	// echo $cmd . "\n";
	exec($cmd);
} else {
	exit("error $fname");
}
