<?php

// release dir:
$release_base = "/home/mountain/public_html/gpx_map";


$opt = getopt("d:f");
if (!isset($opt['d'])){
	echo "dir required\n";
	exit();
}
// -f force release
$dd = $opt['d'];
$version = basename($dd);
$release_dir = $release_base . "/" . $version;
if (!isset($opt['f']) && file_exists($release_dir)) {
	echo "$version in $release_dir released before\n";
	exit(0);
}
$happyman_map = $dd . '/Happyman.map';
$happyman_map_md5 = $happyman_map . ".md5";
$happyman_map_zip = $happyman_map . ".zip";

if (file_exists($happyman_map)) {
	// 1. make md5 
	$cmd = sprintf("md5sum %s > %s",$happyman_map, $happyman_map_md5);
	exec($cmd);
	echo "md5 created\n";
	
	$cmd = sprintf("zip -j %s %s %s",$happyman_map_zip, $happyman_map, $happyman_map_md5);
	exec($cmd);
	echo "zip created\n";
	// 2. copy to dest dir
	$cmd = sprintf("mkdir -p %s; cp %s %s %s %s",$release_dir,$happyman_map, $happyman_map_md5, $happyman_map_zip, $release_dir); 
	exec($cmd);
	echo "$cmd\nfile copied\n";
}

