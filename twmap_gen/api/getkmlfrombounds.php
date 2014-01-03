<?php

$uid = 1;
require_once("../config.inc.php");
if(!ob_start("ob_gzhandler")) ob_start();

$tlx = $_REQUEST['tlx'];
$tly = $_REQUEST['tly'];
$brx = $_REQUEST['brx'];
$bry = $_REQUEST['bry'];
$gpx = (isset($_REQUEST['gpx'])) ? intval($_REQUEST['gpx']) : 0 ;
$keys = (!empty($_REQUEST['keys'])) ? explode(",",$_REQUEST['keys']):array();
// 最多查幾筆
$maxkeys = ($_REQUEST['maxkeys']) ? intval($_REQUEST['maxkeys']) : 0;


if (empty($tlx) || empty($tly) || empty($brx) || empty($bry)) {
 ajaxerr("insufficent parameters");
}

$bounds = array("tlx" => $tlx, "tly" => $tly, "brx" => $brx, "bry" => $bry );

$data = map_overlap($bounds, $gpx, $maxkeys);
/*
    "mid": "10467",
    "uid": "1",
    "cdate": "2012-04-11 14:51:23",
    "ddate": "0000-00-00 00:00:00",
    "host": "localhost",
    "title": "二子三錐.gdb",
    "locX": "297000",
    "locY": "2685000",
    "shiftX": "16",
    "shiftY": "12",
    "pageX": "4",
    "pageY": "2",
    "filename": "/srv/www/htdocs/map/out/000001/10467/297000x2685000-16x12-v3.tag.png",
    "size": "81298771",
    "version": "3",
    "flag": "0",
    "count": "7",
    "gpx": "1",
    "keepon_id": "270"
*/
$mids = array();
$ret = array("add" => array(), "del" => array(), "all" => array(), "count"=> array("add" => 0 , "del" => 0 ));
foreach($data as $map) {
	if (!in_array($map['mid'],$keys)) {

		$content =  sprintf("<a href='%s%s/show.php?mid=%s&info=%s&version=%d' target=_twmap>%s<img src='img/map.gif' title='地圖產生器' border=0/></a>",$site_url,$site_html_root, $map['mid'], urlencode(sprintf("%dx%s-%dx%d",$map['locX'],$map['locY'],$map['shiftX'],$map['shiftY'])), $map['version'], $map['title']);
		if ($map['keepon_id'])
				$content .= sprintf("<a href='http://www.keepon.com.tw/DocumentHandler.ashx?id=%s' target='_keepon'>%s</a>",$map['keepon_id'],"連結登山補給站");


		$ret['add'][$map['mid']] = array('url' => sprintf('%s%s/api/getkml.php?mid=%d',$site_url, $site_html_root, $map['mid']),
			'desc' =>  $content );
	} 
	$ret['all'][] = $map['mid'];
	$mids[] = $map['mid'];
}
foreach($keys as $key) {
	if (!in_array($key, $mids)) {
			$ret['del'][$key] = 1;
	}
}

$ret['count']['add'] = count($ret['add']);
$ret['count']['del'] = count($ret['del']);
ajaxok($ret);
