<?php

require_once("../config.inc.php");
/*
   remove gpx from database
   將某個 mid 的圖, 從 gpx database 中刪除
 */
$id = `id -u`;
if ($id != WWWRUN_UID ) {
	echo "Please run as www user\n";
	exit;
}
$opt = getopt("rm:d:");

if (!isset($opt['m'])){
	echo "Usage: $argv[0] -m mid [-r -d]\n";
	echo "       -r: real run, default is dry run\n";
	echo "       -d cache_dir: default to /home/nas/twmapcache/twmap_gpx\n";
	echo "       -m map id: expire certain map id\n";
	echo "note must run as www user\n";
	echo "\n";
	exit(0);
}
$mid=$opt['m'];
$realdo = 0;
if (isset($opt['r']))
$realdo = 1;

if (isset($opt['d']))
$cache_dir = $opt['d'];
else
$cache_dir = "/home/nas/twmapcache/twmap_gpx";

echo ($realdo==1)?"Do ":"Test (without -r)";
echo "remove map from GIS:$mid " . date('Y-m-d H:i:s') ."\n";
if ($realdo) {
	list ($status, $msg) =  remove_gpx_from_gis($mid);
	if ($status == true){
		// clean tile cache
		list($st, $toclean) = tilestache_clean($mid);
		if($st == true) {
			foreach($toclean as $line){
				$del = $cache_dir . "/" . $line;
				echo "rm $del\n";
				@unlink($del);
			}
		}
	}
} else {
	$status = true;
}
if ($status === false ) {
	echo "Failed\n";
	exit(1);
}

echo "Done\n";
