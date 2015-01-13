<?php

require_once("../config.inc.php");
/*
  remove gpx from GIS
 */
$id = `id -u`;
if ($id != 30 ) {
        echo "Please run as wwwrun\n";
        exit;
}
  $opt = getopt("rm:c:");

  if (!isset($opt['m'])){
  	echo "Usage: $argv[0] -m 4 -d\n";
  	echo "       -r: real run, default is dry run\n";
  	echo "       -m map id: expire certain map id\n";
  	echo "		 -c cache_dir: clean also gpx image,note run as wwwrun\n";
  	exit(0);
  }
  $mid=$opt['m'];
  $realdo = 0;
  if (isset($opt['r'])){
  	$realdo = 1;
  }
  if ($opt['c'])
  	$cache_dir = $opt['c'];
  else
  	$cache_dir = "/mnt/nas/twmapcache/twmap_gpx";

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