<?php

require_once("../config.inc.php");

$op = $_REQUEST['op'];
$data = $_REQUEST['data'];

if (($op != 'get' && $op != 'set') || empty($data)) {
	ajaxerr("insufficent parameters");
}
$da = json_decode($data,true);
$mem = new Memcached;
$mem->addServer('localhost',11211);
$key=md5($data['address']);

switch($op) {
case 'get':
	// 查詢結果, 如果沒有設定 memcache, 當作加入 cache 的依據
	list($result, $msg) = geocoder($op, $da);
	if ($result == 0 ) {
		$mem->set($key, $data['address'],3600);
		ajaxerr($msg);
	}  else {
		ajaxok($msg);
	}
	break;
case 'set':
	if ($mem->get($key) == $data['address']) {
		 list($result, $msg) = geocoder($op, $da);
		 if ($result > 0 ) {
			 $mem->delete($key);
			 ajaxok($msg);
		 } else{
			 ajaxerr($msg);
		 }
	} else {
		ajaxerr("you can't update cache");
	}
	break;
}
