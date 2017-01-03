<?php
/*
 將某個 mid 的 gpx 匯入 postgis 中
*/
require_once("../config.inc.php");
$id = `id -u`;
$opt=getopt("rd:m:c:hkt:");
if (isset($opt['h'])){
	echo $argv[0] . " [-c count] [-r][-d cache_dir][-m start_mid|-t start_tid][-h]\n";
	echo "      -c count: do how many records\n";
	echo "      -r : real do\n";
	echo "      -d dir: default to /home/nas/twmapcache/twmap_gpx\n";
	echo "      -m mid: jump to do from this mid. -m 0 will auto select mid\n";
	echo "      -t tid: jump to do from this tid. -t 0 will auto select tid\n";
	echo "      -k : keepon only\n";
	echo "      -h: this help\n";
	echo "      must run as www user\n\n";
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

if ($real_do == 1 && $id != WWWRUN_UID ) {
        echo "Please run as www user\n";
        exit;
}

$do_count = 0;
if (isset($opt['c'])){
		$do_count = intval($opt['c']);
	} if ( $do_count <1 ) {
		$do_count = 0;
}
if (isset($opt['t'])){
	$start_tid = $opt['t'];
	$db=get_conn();
	if ($start_tid == 0 ) {
		$sql = sprintf("select min(mid) from gpx_trk");
		$rs = $db->getAll($sql);
		// 取出最小的 map id
		$maxid = intval($rs[0][0]);
		if ($maxid < 0)
			$maxid = -1 * intval($rs[0][0])+1;	
		else
			$maxid = 1;
	} else {
		$maxid = $start_tid;
	}
	$keepon_only = (isset($opt['k']))? "AND keepon_id != 'NULL'" : "";
	$sql = sprintf("select tid,name as title,keepon_id FROM \"track\" WHERE status = 0 AND is_taiwan = 't' AND tid >= %d %s ORDER BY cdate",$maxid,$keepon_only);
	//echo $sql;
	$rs = $db->getAll($sql);
	printf("Total %d, from tid %d\n",count($rs),$maxid);
} else {
	$start_mid = (isset($opt['m']))? intval($opt['m']) : -1;
	$db=get_conn();
	$sql = sprintf("select max(mid) from gpx_wp");
	$rs = $db->getAll($sql);
	// 取出最大 map id
	$maxid = intval($rs[0][0])+1;

	if ($start_mid > 0)
		$maxid = $start_mid;

	$keepon_only = (isset($opt['k']))? "AND keepon_id != 'NULL'" : "";
	$sql = sprintf("select mid,title,filename,keepon_id FROM \"map\" WHERE gpx=1 AND flag = 0  AND mid >= %d %s ORDER BY cdate",$maxid,$keepon_only);
	$rs = $db->getAll($sql);
	printf("Total %d, from mid %d\n",count($rs),$maxid);
}
$i=1;
if (count($rs) > 0 ) {
	foreach($rs as $row) {
	// debug==	三筆就好 if ($i++ > 3) break;
		if ($do_count > 0 && $i > $do_count) break;
		if ($row['keepon_id'] != 'NULL' && $row['keepon_id'] != '')
			$url = keepon_Id_to_Url($row['keepon_id']);
		else
			$url = '';
		if (!isset($row['mid']))
			$row['mid'] = -1 * $row['tid'];
			
		printf("%s %d %d %-30s %s",(is_gpx_in_gis($row['mid']))? "i": "-",$i++, $row['mid'],$row['title'],$url);
		if ($real_do == 1) {
			printf(" *\n");
			list($ret,$msg) = import_gpx_to_gis($row['mid']);
			if ($ret === true){
			// clean tile cache
				list($st, $toclean) = tilestache_clean($row['mid'], 1);
				printf("%d %s imported ok\n",$row['mid'],$row['title']);
			}
			else
				printf("%d failed %s\n",$row['mid'],$msg);
		} else {
			printf("\n");
		}
	}
}
printf("total %d gpx %s\n",count($rs),$real_do?"done":"dryrun");
