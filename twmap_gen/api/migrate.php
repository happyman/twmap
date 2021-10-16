<?php

require_once("../config.inc.php");

if (!isset($argv[1])) {
	echo "Usage: migrate.php uid\n";
	exit;
}
$uid = intval($argv[1]);
printf("processing %s...\n",$uid);
$maps = map_get_ids($uid,300);
foreach($maps as $map) {
	// print_r($map);
 map_migrate($out_root, $uid, $map['mid']);
}

## check directory of tracks
$db=get_conn();
$tracks = track_get_all($uid);
foreach($tracks as $row){
	$newpath = sprintf("%s/%s/%06d/track/",$out_root,gethashdir($uid), $uid);
	if ($row['path'] == $newpath) continue;
	$cmd = sprintf("mv %s %s",$row['path'],$newpath);
	echo "$cmd\n";
	exec($cmd);
	$sql=sprintf("update \"track\" set path='%s' where uid=%s",$newpath, $uid);
	$rs = $db->Execute($sql);
	echo "$sql\n";
	break;
}
