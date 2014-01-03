<?php


require_once("../lib/memq.inc.php");


$a = MEMQ::dequeue("keepon");


print_r($a);


