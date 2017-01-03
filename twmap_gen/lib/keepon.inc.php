<?php
// keepon functions
// 使用者: api/keeponlist.php
// 
/***
 user  click 送往地圖產生器 -> KEEPON WEB ->  twmap api ->  queue (worker/keepon_worker.php)
 twmap -> send result to KEEPON WEB -> update button, map link
 after 2016.6 use 
 twmap -> KEEPON WEB List API -> make map
 twmap send result to KEEPON WEB

*/
// 使用在 worker 裡的 API
function kok_out($id, $msg, $url, $cdate=null) {
	// soap_call(true, $id, $msg, $url, $cdate);
	// keepon_MapResult(1, $id, $msg, $url, $cdate);
	keepon_Update($id, 1, $url);
}
// 取代 soap_call
function kerror_out($id,$msg) {
	//soap_call(false, $id, $msg);
	//keepon_MapResult(0, $id, $msg);
	// not necessary to call back
	return;
}
function keepon_MapDelete($id) {
	list ($st, $msg) = keepon_Update($id, 0, '');
 	if ($st === false ){
		echo "MapDelete update failed\n";
	}
}
function kcli_msglog($msg){
	global $debug;
	if ($debug == 0 ) return;
	if (is_array($msg))
		$str = print_r($msg, true);
	else
		$str = $msg;
	syslog(LOG_INFO, $str);
	printf("%s\n",$str);
}
/*
function keepon_MapResult($success, $id, $msg, $url=null, $cdate=null) {
	$kurl = "http://www.keepon.com.tw/api/MapGenerator/MapResult";
	$params = array(

			'Success'=> $success,
			'Identity'=> $id,
			'Date'=> ($cdate)? $cdate : date("Y-m-d H:i:s"),
			'ImageUrl'=> $url,
			'Message'=>$msg
		       );
	$result = request_curl($kurl, "POSTJSON", $params);
	//error_log("request $kurl with params".print_r($params,true) ."get $result");
	kcli_msglog("request $kurl with params".print_r($params,true) ."get $result");
	return array(true, $result);

}
function keepon_MapDelete($id) {
	$kurl = "http://www.keepon.com.tw/api/MapGenerator/MapDelete";
	$params = array(
			'Identity'=> $id );
	$result = request_curl($kurl, "POSTJSON", $params);
	error_log("request $kurl with params".print_r($params,true) ."get $result");
	kcli_msglog("request $kurl with params".print_r($params,true) ."get $result");
	return array(true, $result);

}
function soap_call($success, $id, $msg, $url=null, $cdate =null) {
	//建立SOAP
	// URL = http://www.keepon.com.tw/KeeponWS/Service1.asmx
	$soap = new SoapClient("http://www.keepon.com.tw/KeeponWebService.asmx?WSDL");
	//
	//    // 變數名稱必需與Web Service的變數名稱相同
	$params = array(

			'Success'=> $success,
			'Identity'=> $id,
			'Date'=> ($cdate)? $cdate : date("Y-m-d H:i:s"),
			'ImageUrl'=> $url,
			'Message'=>$msg
		       );

	try {	//                              //呼叫 MapResult 傳入$params

		$result = $soap->MapResult($params);
		//取得回傳值
		kcli_msglog(array($params,$result));
		return array(true, $result);

	} catch (SoapFault $exception) {
		//
		kcli_msglog(array($params,"expection: $exception"));
		return array(false,$exception);
	}
}
function soap_call_delete($id) {
	$soap = new SoapClient("http://www.keepon.com.tw/KeeponWebService.asmx?WSDL");
	$params = array(
			'Identity'=> $id );
	try {
		$result = $soap->MapDelete($params);
		kcli_msglog(array($params,$result));
		return array(true, $result);
	} catch (SoapFault $exception) {
		//
		kcli_msglog(array($params,"expection: $exception"));
		return array(false,$exception);
	}

}

*/


function keepon_List($start, $end, $page=1, $limit=100) {
	$kurl = 'http://www.keepon.com.tw/api/MapGenerator/List';
	$params = array(

			"Start" => date('Y-m-d',$start),
			"End"=> date('Y-m-d',$end),
			"Page" => $page,
			"Count" => $limit
		       );
	$result = request_curl($kurl, "POSTJSON", $params);
	//error_log("request $kurl with params".print_r($params,true) ."get $result");
	kcli_msglog("request $kurl with params".json_encode($params) ."get $result");
	$ret = json_decode($result,true);
	if ($ret !== false)
		return array(true, $ret);
	else	
		return array(false,"error return $result");
}
function keepon_Update($keepon_id, $status, $mapurl='' ) {
	$kurl = 'http://www.keepon.com.tw/api/MapGenerator/Update';
	$params['Data'][0] = array (
		'Id' => $keepon_id,
		'MapGenerated' => $status,
		'MapUrl' => $mapurl
	);
	
	try {
	$result = request_curl($kurl, "POSTJSON", $params);
	} catch (Exception $e) {
                        kcli_msglog("keepon_id $keepon_id update $status failed");
                        return array(false,"update failed");
        }

	//error_log("request $kurl with params".print_r($params,true) ."get $result");
	kcli_msglog("request $kurl with params".print_r($params,true) ."get $result");
	return array(true, $result);
}
/*
 {"Status":true,
 "Info":{"BaseUrl":"http://www.keepon.com.tw","MaxRecord":1,"CurrentPage":1,"CurrentCount":1},
 "Data":[{"Id":"34117388-e53a-e611-80c2-901b0e54a4e6","ThreadId":"33117388-e53a-e611-80c2-901b0e54a4e6",
 "ThreadTitle":"充滿汗水、檜木香跟歡笑聲的治茆山連走西巒大山順訪巒安堂。",
 "FileUrl":"/UploadFile/Thread/2016/54236/73cc29c5-3a2d-471b-bfae-dfb6c3fa5a0c.gpx",
 "MapGenerated":0,"MapUrl":null,"Description":"黑黑谷治茆山_龍哥紀錄.gpx"}]}
*/
function keepon_List_by_TId($thread_id) {
	$params = array();
	$kurl = 'http://www.keepon.com.tw/api/MapGenerator/ThreadMap/' . $thread_id;
	$result = request_curl($kurl, "GET", $params, array('Accept: application/json'));
	//error_log("request $kurl with params".print_r($params,true) ."get $result");
	kcli_msglog("request $kurl with params".print_r($params,true) ."get $result");
	$ret = json_decode($result,true);
	if ($ret !== false && $ret['Status'] === true)
		return array(true, $ret);
	else	
		return array(false,"error return $result");
}
function keepon_Id_to_URL($keepon_id){
	return sprintf("http://www.keepon.com.tw/redirectMap-%s.html", $keepon_id);
}
function keepon_Tid_to_URL($tid) {
	return sprintf("http://www.keepon.com.tw/thread-%s.html",$tid);
}
function keepon_Id_to_Tid($keepon_id) {
	$params = array();
	$kurl = sprintf("http://www.keepon.com.tw/redirectMap-%s.html", $keepon_id);
	$cmd = sprintf("wget    --max-redirect=1  %s  2>&1 |grep Location |grep thread",$kurl);
	exec($cmd, $out, $ret);
	
	if (preg_match("/thread-(.*)\.html/",$out[0],$mat)) {
		print_r($mat);
		kcli_msglog("request $kurl with params".print_r($params,true) ."get $mat[1]");
		return array(true, $mat[1]);
	}
	return array(false,"error");
}
function keepon_List_by_Id($kid) {
	list ($st, $tid) = keepon_Id_to_Tid($kid);
	if ($st !== true || empty($tid)) {
		return array(false, "no such tid");
	}
	list ($st, $res) = keepon_List_by_TId($tid);
	$data = keepon_Data_Format($res);
	$result = array();
	foreach($data as $d) {
		if ($d['Id'] == $kid) {
			$result[] = $d;
		}
	}
	return array(true, $result);
}
function keepon_Data_Format($res) {
	$baseurl = $res['Info']['BaseUrl'];
	foreach($res['Data'] as $data){
		$ret[] = array( "Id" => $data['Id'],
						"ThreadId" => $data['ThreadId'],
						"Title" => $data['Description'],
						"GpxUrl" => $baseurl . $data['FileUrl'],
						"ArticleUrl" => keepon_Tid_to_URL($data['ThreadId']),
						"MapGenerated" => $data['MapGenerated'],
						"MapUrl" => $data['MapUrl']
					);
	}
	return array_reverse($ret);
}
function GPX_bbox($gpxurl) {
		$svg = new gpxsvg(array("gpx"=> $gpxurl, "width"=>1024, "fit_a4" => 0, "auto_shrink" => 0,	"show_label_trk" => 0, "show_label_wpt" => 2));
		$ret =  $svg->detect_bbox();
		return $ret;
}
// TODO: add to track database if over size happyman
function GPX_enqueue($keepon_id,$title,$gpxurl,$auto_shrink=0){
	// 1. download gpx
		$tmp_gpx = tempnam("/tmp","GPX") . ".gpx";
		try {
			$data = request_curl($gpxurl);
		} catch (Exception $e) {
			kcli_msglog("keepon add: unable to download $gpxurl");
			return array(false,"unable to download gpx");
		}
		file_put_contents($tmp_gpx, $data);
	// 1.1 detect bbox, if over limit add to track database
	$ret = GPX_bbox($gpxurl);
	if ($ret[1]['is_taiwan'] != 0 && $ret[1]['over'] == 1) {
		$cmd = sprintf("php upload.php %s %s %s",$tmp_gpx, escapeshellarg($title. ".gpx"), escapeshellarg($keepon_id));
		exec($cmd, $out, $ret3);
		kcli_msglog("run $cmd return $ret3");
		@unlink($tmp_gpx);
		return array(($ret==0)? true : false, "upload to track database");
	}
	// 2. parse params
	$svg = new gpxsvg(array("gpx"=>$tmp_gpx, "width"=>1024, "fit_a4" => 1, "auto_shrink" => $auto_shrink,	"show_label_trk" => 0, "show_label_wpt" => 2));
	$ret = $svg->process();
	if ($ret === false ) {
		@unlink($tmp_gpx);
		kcli_msglog("unable to parse gpx: ". $svg->_err[0]);
		return array(false,"parse gpx error:" . $svg->_err[0]);
	}
	$TODO['gpx'] = $tmp_gpx;
	$TODO['url'] = $gpxurl;
	$TODO['id'] = $keepon_id;
	$TODO['title'] = $title;
	$TODO['startx'] = $svg->bound_twd67['tl'][0]/1000;
	$TODO['starty'] = $svg->bound_twd67['tl'][1]/1000;
	$TODO['shiftx'] = ($svg->bound_twd67['br'][0] - $svg->bound_twd67['tl'][0])/1000;
	$TODO['shifty'] = ($svg->bound_twd67['tl'][1] - $svg->bound_twd67['br'][1])/1000;
	$TODO['ph'] = $svg->bound_twd67['ph'];
	// 3. add to queue

	// gearman code
	$gmclient= new GearmanClient();
	$gmclient->addServer(GEARMAN_SERVER);
			//把 action 加入
	$workload = serialize($TODO);
	// 直接用 keepon id 帶入
	$job_handle = $gmclient->doBackground("keepon_worker", $workload, $keepon_id);
	if ($gmclient->returnCode() != GEARMAN_SUCCESS){
		kcli_msglog("error to add to queue");
		return array(false,'unable to enqueue');
	}
	// gearman code
	kcli_msglog("add to queue");
	return array(true,'done');

}

