<?php

require_once("../config.inc.php");

if (empty($argv[1])) {
	echo "Usage: migrate.php uid\n";
	exit;
}
$uid = intval($argv[1]);
$maps = map_get_ids($uid,100);
foreach($maps as $map) {
	print_r($map);
 map_migrate($out_root, $uid, $map['mid']);
}

