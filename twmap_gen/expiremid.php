<?php

require_once("config.inc.php");

$id = `id -u`;
if ($id != 0) {
	echo "Please run as root\n";
	exit;
}
$opt = getopt("rm:");

if (!isset($opt['m'])){
	echo "Usage: $argv[0] -m 4 -d\n";
	echo "       -r: real run, default is dry run\n";
	echo "       -m map id: expire certain map id\n";
	exit(0);
}
$mid=$opt['m'];
$realdo = 0;
if (isset($opt['r'])){
	$realdo = 1;
}
echo ($realdo==1)?"Do ":"Test (without -r)";
echo "expire map:$mid " . date('Y-m-d H:i:s') ."\n";
if ($realdo) {
$a = map_expire($mid);
} else {
	$a = true;
}
if ($a === false ) {
	echo "Failed\n";
	exit(1);
}
echo "Done\n";
//list ($file_expired, $size_freed)  = do_expire($tt,$realdo);

//echo "File expired: $file_expired\n";
//echo "Size Freed: $size_freed\n";
//echo "Total Size: " . map_totalsize() . "\n";
/*
$a =map_expire($mid);

if ($a === false) {
	echo "failed\n";
} else {
	echo "done\n";
}
 */
