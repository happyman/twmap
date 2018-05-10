#!/usr/bin/php
<?php

/// check version and donwload the weekly release
$admin_email ="happyman";
$fpath = "/home/mountain/mapsforge";
$cpath = "/home/nas/twmapcache/tmp/stache";
$cur_ver_fpath = $fpath . "/VERSION";
$want = array("MOI_OSM_Taiwan_TOPO_Rudy.map.zip","MOI_OSM_Taiwan_TOPO_Rudy.poi.zip");
$base = "http://rudy.basecamp.tw/";
// prevent cache
$ver_data = file_get_contents($base . "/index.json". '?'.mt_rand());
$dirty = 0;
$v = json_decode($ver_data, true);

if (file_exists($cur_ver_fpath)) {
	$cur_ver = trim(file_get_contents($cur_ver_fpath));
} else {
	$cur_ver = "v0.0";
}

echo "cur_ver = $cur_ver ";
// print_r($v);
foreach($v as $vv) {
	if (in_array($vv['name'], $want)) {
		if ($vv['version'] != $cur_ver ) {
			$new_ver = $vv['version'];
			$dirty = 1;
		}
	}
	
}
if ($dirty == 1 ) {
echo ";online_ver = $new_ver\n";
	$ver = $new_ver;
	do_update($base);
} else {
	echo "no update required..\n";
	mail($admin_email,"rudy map checked", "version is now $cur_ver");
}
//print_r($v);
function do_update($base) {
	// 1. download  and unzip 
	global $fpath,$ver,$cpath,$admin_email;
	@mkdir($fpath . "/$ver", 0755, true);
	chdir($fpath . "/$ver");
	$zips = array("MOI_OSM_Taiwan_TOPO_Rudy.map.zip","MOI_OSM_Taiwan_TOPO_Rudy.poi.zip","MOI_OSM_twmap_style.zip");
	foreach($zips as $zip) {
		my_system(sprintf("wget -O %s %s",$zip , $base . $zip));
		my_system("unzip -o $zip");
	}
	chdir($fpath);
       // 3. clean tile cache
	echo "tilestache clean...\n";
	$layers = array("moi_osm","moi_osm_gpx");
	foreach($layers as $layer) {
	// slow method:	my_system(sprintf("tilestache-clean -q -c /var/www/etc/tilestache.cfg -l %s -b 25.31 119.31 21.88 124.56 15 16 17 18 19",$layer));
		echo "cleaning $layer...\n";
		exec("rm -r $cpath/$layer/16");
		
	}
	// 4. serve it 
	chdir($fpath);
	exec("rm cur; ln -s $ver cur");
	// 5. update VERSION
	echo "update VERSION file\n";
	file_put_contents($fpath . "/VERSION", $ver);
	// 6. restart java
	echo "restart java tile server...\n";
	system("killall java");
	// 7. email me
	mail($admin_email,"rudy map updated!", "version is now $ver");

}
function my_system($cmd) {
	global $ver;
	exec($cmd, $out, $ret);
	if ($ret != 0 ){
		exec("rm -r $fpath/$ver");
		echo "cmd fails: $cmd\n";
		exit(1);
	}
}
