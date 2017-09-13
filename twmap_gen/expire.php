<?php

require_once("config.inc.php");

//$id = `id -u`;
//if ($id != 0 ) {
//	echo "Please run as root\n";
//	exit;
//}
$opt = getopt("t:r");

if (!isset($opt['t'])){
	echo "Usage: $argv[0] -t 180 -d\n";
	echo "       -r: real run, default is dry run\n";
	echo "       -t days: expire maps xx days ago\n";
	exit(0);
}
$tt=$opt['t'];
$realdo = 0;
if (isset($opt['r'])){
	$realdo = 1;
}
echo ($realdo==1)?"Do ":"Test (without -r)";
echo "expire maps start: " . date('Y-m-d H:i:s') ."\n";
list ($file_expired, $size_freed)  = do_expire($tt,$realdo);
echo "Expire from ". date('Y-m-d H:i',time()-$tt*86400) . "\n";

echo "File expired: $file_expired\n";
echo "Size Freed: ". humanreadable($size_freed)."\n";
echo "Total Size: " . humanreadable(map_totalsize()) . "\n";
/*
$a =map_expire($mid);

if ($a === false) {
	echo "failed\n";
} else {
	echo "done\n";
}
 */
