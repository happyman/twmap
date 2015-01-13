<?php

require_once("../config.inc.php");
$id = `id -u`;
if ($id != 30 ) {
        echo "Please run as wwwrun\n";
        exit;
}


$opt=getopt("rc:");
if (isset($opt['r']))
	$real_do = 1;
else
	$real_do = 0;
if ($opt['c'])
	$cache_dir = $opt['c'];
else
	$cache_dir = "/mnt/nas/twmapcache/twmap_gpx";
$db=get_conn();
$sql = sprintf("select max(mid) from gpx_wp");
$rs = $db->getAll($sql);
// 取出最大 map id
$maxid = intval($rs[0][0])+1;
$sql = sprintf("select mid,title,filename FROM \"map\" WHERE gpx=1 AND flag <> 2 AND mid > %d ORDER BY cdate",$maxid);
$rs = $db->getAll($sql);
printf("Total %d, from mid %d\n",count($rs),$maxid);
if (count($rs) > 0 ) {
	foreach($rs as $row) {
		printf("doing mid %d %-30s",$row['mid'],$row['title']);
		if ($real_do == 1) {
			printf(" *\n");
			list($ret,$msg) = import_gpx_to_gis($row['mid']);
			if ($ret === true){
			// clean tile cache
				list($st, $toclean) = tilestache_clean($mid);
				if($st == true) {
					foreach($toclean as $line){
						$del = $cache_dir . "/" . $line;
						echo "rm $del\n";
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
printf("inport %d gpx %s\n",count($rs),$real_do?"done":"dryrun");
