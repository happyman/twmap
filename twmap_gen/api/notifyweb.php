<?php 
require_once("../config.inc.php");

//$opt = getopt('c:');

$channel = $_REQUEST['c'];

notify_web($channel, array("Hello.. channel is ". $channel));
echo "Hello";
