<?php

require_once("../config.inc.php");
if (php_sapi_name() != "cli"){
exit("cli only");
}

$debug = 1;
$opt = getopt("a:k:m:t:s:e:rg:u:c:f");
$action = (isset($opt['a'])) ? $opt['a'] : '';
if (empty($action)) {
	printf("%s -a listall|enqueue|list|delete [-k keepon_id] [-t thread_id] [-m 0|1]\n",$argv[0]);
	printf("	listall -s 'Y-m-d' -e 'Y-m-d' -m 0 : start, end, mapgenerated [-r]: do queue [-c]: count\n");
	printf("	enqueue -k keepon_id -t thread_id [-f]: force auto_shrink\n");
	printf("	listt -t thread_id\n");
	printf("	listk -k keepon_id\n");
	printf("	listm -k keepon_id [-r]: -r renotify\n");
	printf("	detect -g gpxurl\n");
	exit(0);
}

switch($action) {
	
case 'listall':

	// example code
	/* list all default: this month*/
	$start = (isset($opt['s']))? strtotime($opt['s']) : strtotime(date('01-m-Y',strtotime('this month')));
	$end = (isset($opt['e'])) ? strtotime($opt['e']) :  strtotime('yesterday') ;
	$docount = (isset($opt['c']))? intval($opt['c']) : 100;
	list ($st,$res) =  keepon_List($start,$end,1,100);
	$data = Keepon_Data_Format($res);
	$map_exists = 2;
	if (isset($opt['m'])) {
		$map_exists = (isset($opt['m']))? intval($opt['m']) : 0;
	}
	$count =0;
	foreach($data as $d) {
		if ($d['MapGenerated'] == $map_exists || $map_exists == 2) {
			printf("%d:\n",++$count);
			if ($count > $docount)  {
				echo "count reached.. break\n";
				break;
			}
			print_r($d);
			if (isset($opt['r'])) {
				if ($d['MapGenerated'] == 1) {
					echo "skip $kid\n";
					continue;
				}
			list ($enq_ret, $msg ) = GPX_enqueue($d['Id'], str_replace(".gpx","",$d['Title']),$d['GpxUrl']);
			if ($enq_ret === true ) {
				echo "[1;33menqueued[m\n";
			}
			}
		}
	}
	break;

/*
Array
(
    [Id] => 6bd0da7d-cd27-e611-80c2-901b0e54a4e6
    [ThreadId] => 6ad0da7d-cd27-e611-80c2-901b0e54a4e6
    [Title] => 內烏嘴煤源20160526.gpx
    [GpxUrl] => http://www.keepon.com.tw/UploadFile/Thread/2016/9055/46352254-645e-46bd-b954-3d28a34b3128.gpx
    [MapGenerated] => 1
    [MapUrl] => http://map.happyman.idv.tw/show.php?mid=84772&info=276000x2739000-5x7
)
*/
case 'listt':
	// list single 
	$tid = (isset($opt['t']))? $opt['t'] : '';
	if (empty($tid)) {
		echo "not enough params\n";
		exit(1);
	}	
	list ($st,$res) = keepon_List_by_TId($tid);
	print_r(keepon_Data_Format($res));
	//print_r($res);
	break;
	
case 'listk':
	$kid =  (isset($opt['k']))? $opt['k'] : '';
	if (empty($kid)) {
		echo "not enough params\n";
		exit(1);
	}	
	list ($st, $res) = keepon_List_by_Id($kid);
	print_r($res);
	break;
case 'listm':
	$kid = (isset($opt['k'])) ? $opt['k'] : '';
	if (empty($kid)) {
		echo "not enough params\n";
		exit(1);
	}
	$data = keepon_map_exists(14803, $kid);
	// print_r($data);
	$html_root = $out_html_root . str_replace($out_root, "", dirname($data['filename']));
	$url =  $site_url . $html_root . "/" . basename($data['filename']);
	if (isset($data['mid'])) {
		printf("mid: %d\nkid: %s\nurl: %s\n",$data['mid'],$kid,$url);
		// renotify
		if (isset($opt['r'])) {
			echo "renotify:\n";
			keepon_Update($kid, 1, $url);
			
		}
	}
	break;
case 'enqueue':
	$kid = (isset($opt['k']))? $opt['k'] : '';
	$tid = (isset($opt['t']))? $opt['t'] : '';
	$auto_shrink = (isset($opt['f']))? 1 : 0;
	if (empty($kid) || empty($tid)) {
		echo "not enough params \n";
		exit(1);
	}
	//list ($st, $tid) = keepon_Id_to_Tid($kid);
	list ($st,$res) = keepon_List_by_TId($tid);
	$data = keepon_Data_Format($res);
	foreach($data as $d) {
		if ($d['Id'] == $kid) {
			if ($d['MapGenerated'] == 1) {
				echo "skip $kid\n";
				continue;
			}
			GPX_enqueue($d['Id'],$d['Title'],$d['GpxUrl'],$auto_shrink);
		}
	}

	break;

case 'delete':
	$kid = $opt['k'];
	keepon_MapDelete($kid);

	list ($st,$res) = keepon_List_by_Id($kid);
	print_r($res);
	break;
	
case 'detect':
	$gpxurl = $opt['g'];
	$ret = GPX_bbox($gpxurl);
	print_r($ret);
	break;
case 'mupdate':
	$kid = $opt['k'];
	$url = $opt['u'];
	keepon_Update($kid, 1, $url);
	break;
}


