<?php

require_once("../config.inc.php");

//$mid=(isset($argv[1]))? intval($argv[1]) : 56000;
$opt=getopt("r");
if (isset($opt['r']))
	$real_do = 1;
else
	$real_do = 0;
$db=get_conn();
$sql = sprintf("select max(mid) from gpx_wp");
$rs = $db->getAll($sql);
// 取出最大 map id
$maxid = intval($rs[0][0])+1;
$sql = sprintf("select mid,title,filename FROM \"map\" WHERE gpx=1 AND flag <> 2 AND mid > %d ORDER BY cdate",$maxid);
$rs = $db->getAll($sql);
printf("Total %d, from mid %d\n",count($rs),$maxid);
foreach($rs as $row) {
	printf("doing mid %d %-30s",$row['mid'],$row['title']);
	if ($real_do == 1) {
		printf(" *\n");
		list($ret,$msg) = import_gpx($row['mid']);
		if ($ret === true)
			printf("%d %s imported ok\n",$row['mid'],$row['title']);
		else
			printf("%d failed %s\n",$row['mid'],$msg);
	} else {
		printf("\n");
	}
}
printf("inport %d gpx %s\n",count($rs),$real_do?"done":"dryrun");
