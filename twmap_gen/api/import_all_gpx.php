<?php

require_once("../config.inc.php");
$id = `id -u`;
if ($id != WWWRUN_UID ) {
        echo "Please run as www user\n";
        exit;
}
$opt=getopt("rd:m:c:hk");
if (isset($opt['h'])){
	echo $argv[0] . " [-c count] [-r][-d cache_dir][-m start_mid][-h]\n";
	echo "      -c count: do how many records\n";
	echo "      -r : real do\n";
	echo "      -d dir: default to /home/nas/twmapcache/twmap_gpx\n";
	echo "      -m mid: jump to do from this mid\n";
	echo "      -k : keepon only\n";
	echo "      -h: this help\n";
	exit(0);
}

if (isset($opt['r']))
	$real_do = 1;
else
	$real_do = 0;
if (isset($opt['d']))
	$cache_dir = $opt['d'];
else
	$cache_dir = "/home/nas/twmapcache/twmap_gpx";

$start_mid = (isset($opt['m']))? intval($opt['m']) : 0;

$do_count = 0;
if (isset($opt['c'])){
	$do_count = intval($opt['c']);
} if ( $do_count <1 ) {
	$do_count = 0;
}
$db=get_conn();
$sql = sprintf("select max(mid) from gpx_wp");
$rs = $db->getAll($sql);
// 取出最大 map id
$maxid = intval($rs[0][0])+1;
if ($start_mid > $maxid)
	$maxid = $start_mid;
$keepon_only = (isset($opt['k']))? "AND keepon_id != 'NULL'" : "";
$sql = sprintf("select mid,title,filename,keepon_id FROM \"map\" WHERE gpx=1 AND flag <> 2 AND mid >= %d %s ORDER BY cdate",$maxid,$keepon_only);
$rs = $db->getAll($sql);
printf("Total %d, from mid %d\n",count($rs),$maxid);
$i=1;
if (count($rs) > 0 ) {
	foreach($rs as $row) {
	// debug==	三筆就好 if ($i++ > 3) break;
		if ($do_count > 0 && $i > $do_count) break;
		printf("%d doing mid %d %-30s %s",$i++, $row['mid'],$row['title'],$row['keepon_id']);
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
