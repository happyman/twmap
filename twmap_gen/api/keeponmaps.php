<?php

$uid = 1;
require_once("../config.inc.php");
$cli = 1;
if (isset($_SERVER['REQUEST_URI']))
	$cli = 0;

$result = map_get_ok($uid);

/*Array
(
[mid] => 10945
[uid] => 1
[cdate] => 2012-04-19 02:09:25
[ddate] => 0000-00-00 00:00:00
[host] => localhost
[title] => 水社大山潭南線O走.gdb
[locX] => 241000
[locY] => 2639000
[shiftX] => 6
[shiftY] => 4
[pageX] => 1
[pageY] => 1
[filename] => /srv/www/htdocs/map/out/000001/10945/241000x2639000-6x4-v3.tag.png
[size] => 10385046
[version] => 3
[flag] => 0
[count] => 0
[gpx] => 1
[keepon_id] => 619
)*/
if (!$cli) {
?>
<html>
	<head>
		<title>
			keepon maps
		</title>
		<meta http-equiv="Content-Type" content="text/html; 
		charset=UTF-8" />
	</head>
<?php
}
$c =1;
foreach($result as $map) {
	if ($cli)
		printf("%d %d %d %s\n",$c,$map['mid'],$map['keepon_id'],$map['title']);
	else
		printf("<p>[%d]<a href='/twmap/show.php?mid=%d' target=twmap>%d</a>&nbsp;<a href='http://www.keepon.com.tw/DocumentHandler.ashx?id=%d' target=keepon>%d</a>&nbsp;<a href='renotify.php?kid=%d' target=_blank>renotify</a>&nbsp;%s",$c,
			$map['mid'], $map['mid'], $map['keepon_id'], $map['keepon_id'], $map['keepon_id'], $map['title']);
	$c++;
}
