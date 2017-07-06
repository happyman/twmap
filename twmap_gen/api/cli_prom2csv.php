#!/usr/bin/php
<?php
require_once("../config.inc.php");
if(!ob_start("ob_gzhandler")) ob_start();


$opt=getopt("i:");
if (!isset($opt['i'])){
	echo "Usage: $argv[0] -i input  > prom_u8.csv\n";
	exit(0);
}
$lines = file($opt['i']);


//csv: 120.2614  22.6417  355  343 120.2786  22.6744   12 120.9575  23.4700 3939
//isl: 120.9575  23.4700 3939 3939
$r = 60;
echo "peak_x,peak_y,check,peak_h,peak_name,prominence,col_x,col_y,col_h,parent_x,parent_y,parent_h,parent_name,remark\n";

foreach($lines as $line){
	list($type, $peak_x,$peak_y,$peak_h,$prom,$col_x,$col_y,$col_h, $parent_x,$parent_y,$parent_h) = preg_split("/\s+/",trim($line));
	
	// 1. verify height
	$mark =0;
	
	//if ($peak_x && $col_x){
	//	$ele = get_elev(twDEM_path, $peak_y, $peak_x, 1);
	//	if (abs($ele - $peak_h ) > 100) $mark = 1;
	//}
	
	
	$parent_data = array();
	$peak_data = array();
	$peak_data = get_points_from_center(array($peak_x,$peak_y),$r);	
	if ($type!== 'isl:')
		$parent_data = get_points_from_center(array($parent_x,$parent_y),$r);	
	
	if (isset($peak_data[0]['name']))
		$peak_name = $peak_data[0]['name'];
	else { 
		$peak_name = "";
		// to add whole record
		$mark=1;
	}
	if ($mark==0 && $peak_data[0]['prominence']==0){
		// to add prominence data
		$mark=2;
	}
	if (isset($parent_data[0]['name']))
		$parent_name = $parent_data[0]['name'];
	else
		$parent_name = "";
	
	$result[]=array($peak_x,$peak_y,"$peak_y $peak_x",$peak_h,process_name($peak_name),$prom, $col_x,$col_y,$col_h,$parent_x,$parent_y,$parent_h,process_name($parent_name),$mark);
	// echo implode(",",$result) . "\n";
	if ($mark == 2){
		// printf("Update point3 SET prominence=%d WHERE id=%d;\n",$prom,$peak_data[0]['id']);
	}
	
	
}
// sort by prominence
usort($result,"mysort");
function mysort($a,$b) {
	return $b[5]-$a[5];
}

foreach($result as $line){
	echo implode(",",$line) . "\n";
}
//print_r($result);
function process_name($name){
	// 取代 -2 
	$newname = preg_replace("/\-2$/","",$name);
	return $newname;
}

