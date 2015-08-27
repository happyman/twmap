<?php

require_once("../config.inc.php");
$id = `id -u`;
if ($id != WWWRUN_UID ) {
        echo "Please run as wwwrun\n";
        exit;
}


$opt=getopt("rc:");
if (isset($opt['r']))
	$real_do = 1;
else
	$real_do = 0;
if (isset($opt['c']))
	$cache_dir = $opt['c'];
else
	$cache_dir = "/home/nas/twmapcache/twmap_gpx";
$db=get_conn();
$sql = sprintf("select max(mid) from gpx_wp");
$rs = $db->getAll($sql);
// 取出最大 map id
$maxid = intval($rs[0][0])+1;
$sql = sprintf("select mid,title,filename,keepon_id FROM \"map\" WHERE gpx=1 AND flag <> 2 AND mid > %d ORDER BY cdate",$maxid);
$rs = $db->getAll($sql);
printf("Total %d, from mid %d\n",count($rs),$maxid);
// debug== $i=0;
if (count($rs) > 0 ) {
	foreach($rs as $row) {
	// debug==	if ($i++ > 3) break;
		printf("doing mid %d %-30s %s",$row['mid'],$row['title'],$row['keepon_id']);
		if ($real_do == 1) {
			$exist_mid = is_keepon_map_imported($row['mid']);
			if ($exist_mid) {
				printf("skip.. ".$exist_mid['mid']."\n");
				continue;
			}
			printf(" *\n");
			list($ret,$msg) = import_gpx_to_gis($row['mid']);
			if ($ret === true){
			// clean tile cache
				list($st, $toclean) = tilestache_clean($row['mid']);
				if($st == true) {
					foreach($toclean as $line){
						$del = $cache_dir . "/" . $line;
						// if need debug, uncomment
						// echo "rm $del\n";
						@unlink($del);
					}
				}
				printf("%d %s imported ok\n",$row['mid'],$row['title']);
			}
			else
				printf("%d failed %s\n",$row['mid'],$msg);
		} else {
			printf("\n");
		}
	}
}
printf("import %d gpx %s\n",count($rs),$real_do?"done":"dryrun");
