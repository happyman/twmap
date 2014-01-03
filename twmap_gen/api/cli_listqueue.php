<?php


require_once("../lib/memq.inc.php");


$a = MEMQ::listqueue("keepon");

echo count($a) . "筆\n";
foreach($a as $val) {

	print_r($val);
}


