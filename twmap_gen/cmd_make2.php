<?php
//$Id: cmd_make.php 291 2012-06-20 06:10:01Z happyman $
// 1. check login
require_once("config.inc.php");
require_once("lib/slog/load.php");

ini_set("memory_limit","512M");
set_time_limit(0);

$opt = getopt("O:r:v:t:i:p:g:Ges:dSl:c3m:a:",array("agent:","logurl_prefix:"));
if (!isset($opt['r']) || !isset($opt['O'])|| !isset($opt['t'])){
	echo "Usage: $argv[0] -r 236:2514:6:4:TWD67 -O outdir -v 2016 -t title [-p][-m /tmp][-g gpxfile:0:0][-c][-G][-e][-3][-d]\n";
	echo "必須參數:\n";
	echo "       -r params: startx:starty:shiftx:shifty:datum  datum:TWD67 or TWD97\n";
	echo "       -O outdir: /home/map/out/000003 輸出目錄\n";
	echo "       -v 3|2016: 經建3|魯地圖\n";
	echo "       -t title: title of image\n";
	echo "選用參數:\n";
	echo "       -p 1|0: 1 是澎湖 pong-hu\n";
	echo "       -g gpx_file:trk_label:wpt_label 利用GPX檔案產生\n";
	echo "       -c keep color 彩圖\n";
	echo "       -e draw 100M 格線\n";
	echo "       -G merge user track_logs 包含使用者行跡\n";
	echo "       -3 for A3 output 輸出A3\n";
	echo "       -m /tmp tmpdir 暫存檔存放目錄\n";
	echo "除錯用 -d debug \n";
	echo "       -s 1-5: from stage 1: create_tag_png 2: split images 3: make simages 4: create txt/kmz 5: create pdf.\n";
	echo "       -S use with -s, if -s 2 -S, means do only step 2\n";
	echo "以下地圖產生器使用:\n";
	echo "       -i ip: log remote ip address --agent myhost\n";
	echo "       -l log_channel --logurl_prefix for custom url, ex: ws://myhost:9002/twmap_\n";
	echo "       -a callback url when done\n";
	exit(0);
}
use \stange\logging\Slog;
// keep colorred stdout *and* file log
$logger =  new Slog(['file'=>"/tmp/cmd_make.log"]);
$logger->useDate(True);
// parse param
list($startx,$starty,$shiftx,$shifty,$datum)=explode(":",$opt['r']);
if (empty($startx) || empty($starty)  || empty($shiftx)  || empty($shifty) || empty($datum))
	cli_error_out("參數錯誤",0);

$version=isset($opt['v'])?$opt['v']:2016;
$title=mb_decode_mimeheader($opt['t']);
$keep_color = (isset($opt['c']))? 1 : 0;
$ph = isset($opt['p'])? $opt['p'] : 0;
$jump = isset($opt['s'])? $opt['s'] : 1;
if (isset($opt['S'])) $jumpstop = $jump+1; else $jumpstop = 0;
$remote_ip = isset($opt['i'])? $opt['i'] : "localhost";
if (isset($opt['d'])) $debug_flag= 1; else $debug_flag = 0;
// default 2016, remove version 1
if (!in_array($version, array(3,2016))) $version=2016;
if (isset($opt['l'])) $log_channel = $opt['l']; else $log_channel = "";
if (isset($opt['logurl_prefix'])) 
	$logurl_prefix=$opt['logurl_prefix'];
else 
	$logurl_prefix="wss://ws.happyman.idv.tw/twmap_";
$outpath=$opt['O'];
$a3 = (isset($opt['3']))? 1 : 0;
$callback=(isset($opt['a']))?$opt['a']:"";
if (!file_exists($outpath)) {
	$ret = mkdir($outpath, 0755, true);
	if ($ret === false) {
		cli_error_out("無法建立 $outpath");
	}
}
switch($datum){
	case 'TWD97':
		break;
	case 'TWD67':
	default:
		$datum = 'TWD67';
		break;
}
if (isset($opt['m'])){
	$tmpdir = $opt['m'];
} else {
	$tmpdir = '/tmp';
}
	
$outfile_prefix=sprintf("%s/%dx%d-%dx%d-v%d%s_%s",$outpath,$startx*1000,$starty*1000,$shiftx,$shifty,$version,($ph==1)?"p":"",$datum);
$outimage=$outfile_prefix . ".tag.png";
$outimage_orig=$outfile_prefix . ".orig.tag.png";
$outimage_gray=$outfile_prefix . ".gray.png";
$outtext=$outfile_prefix . ".txt";
$outsvg = $outfile_prefix . ".svg";
$outsvg_big = $outfile_prefix . ".svg2";
$merged_gpx = $outfile_prefix. ".gpx2";
$outpdf = $outfile_prefix . ".pdf";

$stage = 1;
// 決定哪一種輸出省紙
if (isset($opt['3']))
	$type = determine_type_a3($shiftx, $shifty);
else
	$type = determine_type($shiftx, $shifty);

$g = new STB2("", $startx, $starty, $shiftx, $shifty, $ph, $datum, $tmpdir);
if (!empty($g->err)){
	cli_error_out(implode(":",$g->err),0);
}
$g->version=$version;

if (isset($opt['G'])) {
	$g->include_gpx = 1;
} 
if (isset($debug_flag)){
	$g->setDebug(1);
}
// get port and pid
$port = 0;
$pid = 0;
if (!empty($log_channel)) {
	// persist websocket connection for slightly better performance 
	$port=find_free_port();
	$cmd = sprintf("websocat -t -1 -u tcp-l:127.0.0.1:%d reuse-raw:%s%s  >/dev/null 2>&1 & echo $!",$port,$logurl_prefix,$log_channel);
	$pid = exec($cmd,$output);
	$g->setLog($log_channel,$logurl_prefix,$port,$logger);
	if (isset($opt['agent']))
		$agent=$opt['agent'];
	else
		$agent="";
	// important to cleanup this websocket daemon
	register_shutdown_function('shutdown');
	pcntl_signal(SIGTERM, 'sigint');
	pcntl_signal(SIGINT, "sigint");
	// OK. let's start.
	cli_msglog(sprintf("Agent %s Roger that ^_^",$agent));
	cli_msglog("ps%0");
}

$g->setoutsize($tiles[$type]['x'],$tiles[$type]['y']);
$out=$g->getsimages();
// debug: print_r($out);
$outx=$g->getoutx(); // 5
$outy=$g->getouty();  // 7
$total=count($out);
for ($i=0; $i< $total; $i++) {
	$todo[] = $i;
	$simage[$i] = sprintf("%s_%d.png",$outfile_prefix,$i);
}
//
//
cli_msglog("ps%10");
showmem("after STB created");
if ($jump <= $stage ) {

	if (file_exists($outimage)) {
		// 如果 10 分鐘之前的 dead file, 清除之
	  if (time() - filemtime($outimage) > 600) {
	  	$files = map_files($outimage);
		foreach($files as $f) {
			$ret = unlink($f);
		}
	  } else {
	  	cli_msglog("$outimage exists");
		cli_error_out("若發生此問題, 通常表示上一個出圖過程 crash 殘留檔案, 請回報此路徑 $outimage",0);
	  }
	}
	// 先檢查是否有 gpx 存在與否
	if (isset($opt['g'])) {
		list($param['gpx'],$param['show_label_trk'],$param['show_label_wpt'])=explode(":",$opt['g']);
		if (!file_exists($param['gpx'])) {
			cli_error_out("unable to read gpx file",0);
		}
	}
	
	$im = $g->createpng(0,0,0,1,1,$debug_flag); // 產生
	// 產生不出來
	if ($im === false) 
		cli_error_out(implode(":",$g->err));
	showmem("after image created");
	cli_msglog("ps%30");

	// 如果有 gpx 相關參數
	if (isset($opt['g'])) {
		//list($param['gpx'],$param['show_label_trk'],$param['show_label_wpt'])=explode(":",$opt['g']);
		//if (!file_exists($param['gpx'])) {
		//	cli_error_out("unable to read gpx file");
		//}
		$param['width'] = imagesx($im);
		ImagePNG($im, $outimage_orig);
		if (file_exists($outimage_orig)) {
			cli_msglog("create PNG: $outimage_orig done");
			cli_msglog("ps%+3");
		}
		$param['logotext'] = $title;
		$param['bgimg'] = $outimage_orig;
		$param['fit_a4'] = 0;
		// no more detection
		$param['input_bound67'] = array("x" => $startx * 1000, 'y'=> $starty * 1000, 'x1' => ($startx+$shiftx)*1000, 'y1' => ($starty-$shifty)*1000, 'ph' => $ph);
		$param['datum']=$datum;

		cli_msglog("create SVG: $outsvg");
		list($ret,$msg) = gpx2svg($param, $outsvg);
		if ($ret === false ) {
			@unlink($outimage_orig);
			cli_error_out("gpx2svg fail: ". print_r($msg,true). print_r($param,true));
		}
		cli_msglog("create PNG: $outimage");
		cli_msglog("ps%+3");
		list ($ret,$msg) = svg2png($outsvg, $outimage, array($shiftx*315,$shifty*315));
		if ($ret === false ) {
			@unlink($outimage_orig);
			@unlink($outimage);
			cli_error_out("svg2png fail: $msg");
		}

		// outimage 已生成
		//$im = imagecreatefrompng($outimage);
		// 這不要了
		@unlink($outimage_orig);
	} else {
		write_and_forget($im,$outimage,$debug_flag);
	}

	// 加上 grid
	if (isset($opt['e'])) {
		cli_msglog("add 100M grid to image...");
		im_addgrid($outimage, $g->v3img,  100, $version);
		cli_msglog("ps%+3");
	}
	// 若是 moi_osm 則加上 1000 or TWD97  and logo
	if ($version == 2016 || $datum == 'TWD97' ){
		im_addgrid($outimage, $g->v3img, 1000, $version);
	}
	// happyman
	cli_msglog("ps%40");
	if ($keep_color==1) {
	cli_msglog("keep colorful image...");
	copy($outimage,$outimage_gray);
	} else {
	cli_msglog("grayscale image...");
	// 產生灰階圖檔
	im_file_gray($outimage, $outimage_gray, $version);
	// im_tagimage($outimage_gray,$startx,$starty);
	}
	im_tagimage($outimage_gray,$startx,$starty);
	cli_msglog("ps%45");
	// 加上 tag
	cli_msglog("add tag to image...");
	im_tagimage($outimage,$startx,$starty);
	//cli_msglog("$outimage created");
	unset($g);
}
cli_msglog("ps%50");
$stage = 2;
showmem("after stage 1");
if ($stage == $jumpstop) {
	echo "stop by -S\n";
	exit(0);
}
if ($stage >= $jump ) {
	cli_msglog("split image...");
	$im = imagecreatefrompng($outimage_gray);
	splitimage($im, $tiles[$type]['x']*315 , $tiles[$type]['y']*315 , $outfile_prefix, $px, $py, $fuzzy);
	unset($im);
}
cli_msglog("ps%60");
showmem("after stage 2");
$stage = 3;
if ($stage == $jumpstop) {
	echo "stop by -S\n";
	exit(0);
}
if ($stage >= $jump ) {
	// 做各小圖
	showmem("after free STB");
	for($i=0;$i<$total;$i++) {
		// im_file_gray($simage[$i], $simage[$i], $version);
		// 如果只有一張的情況 
		if ($total == 1) {
		 im_simage_resize($type, $simage[$i], $simage[$i], 'Center');
		 break;
		}
	  cli_msglog(sprintf("%d / %d",$i+1,$total));
		im_simage_resize($type, $simage[$i], $simage[$i]);
	  cli_msglog("resize small image...");
		$idxfile = sprintf("%s/index-%d.png",$outpath,$i);	
		$idximg = imageindex($outx,$outy,$i, 80, 80);
		imagepng($idximg,$idxfile);
	  cli_msglog("create index image...");
		$overlap=array('right'=>0,'buttom'=>0);
		if (($i+1) % $outx != 0) $overlap['right'] = 1;
		if ($i < $outx * ($outy -1)) $overlap['buttom'] = 1;
		im_addborder($simage[$i], $simage[$i], $type,  $overlap, $idxfile);
		unlink($idxfile);
	  cli_msglog("small image border added ...");
		cli_msglog("ps:+".sprintf("%d", 20 * $i+1/$total));
	}
}
showmem("after stage 3");
$stage = 4;
if ($stage == $jumpstop) {
	echo "stop by -S\n";
	exit(0);
}
cli_msglog("ps%80");
if ($stage >= $jump ) {
	cli_msglog("save description...");
	$desc=new ImageDesc( basename($outimage), $title, $startx*1000, $starty*1000, $shiftx, $shifty, $simage, $outx, $outy, $remote_ip, $version, $datum );
	$desc->save($outtext);
	cli_msglog("make kmz file...");
	require_once("lib/garmin.inc.php");
	$kmz = new garminKMZ(3,3,$outimage,$ph,$datum);
	//if ($debug_flag == 1 )
	//	$kmz->setDebug(1);
	// 加上行跡資料
	if (isset($opt['g'])) {
		$kmz->addgps("gpx", $param['gpx']);
	}
	$kmz->doit();
}
showmem("after stage 4");
$stage = 5;
if ($stage == $jumpstop) {
	echo "stop by -S\n";
	exit(0);
}
cli_msglog("ps%90");
if ($stage >= $jump) {
	// 產生 pdf
	require_once("lib/print_pdf.inc.php");
	$pdf = new print_pdf(array('title'=> $title, 'subject'=> basename($outfile_prefix), 'outfile' => $outpdf, 'infiles' => $simage, 'a3' => $a3 ));
	$pdf->print_cmd = 0;
	cli_msglog("save to pdf format");
	$pdf->doit();
	$logger->info("$outimage done");
}
showmem("after stage 5");
$stage = 6;
if ($stage == $jumpstop) {
	echo "stop by -S\n";
	exit(0);
}
cli_msglog("ps%95");
// 如果有給 -s, 就不刪圖檔
if ($stage >= $jump && !isset($opt['s'])) {
	cli_msglog("almost done,cleanup...");
	foreach($simage as $simage_file) {
		unlink($simage_file);
	}
	unlink($outimage_gray);
}
// not register db yet
if (!empty($callback)){
	cli_msglog("register this map");
	//cli_msglog("call $callback");
	//$r = request_curl($callback,"GET",["ch"=>$log_channel,"status"=>"ok"]);
	$url=sprintf('%s?ch=%s&status=ok&params=%s',$callback,$log_channel,urlencode(implode(" ",$argv)));
	// log
	if (isset($opt['agent']))
		$url.="&agent=".trim($opt['agent']);
	cli_msglog("call callback api");
	// $output = file_get_contents($url);
	try {
		$output = request_curl($url);
	} catch (Exception $e) {
		// 連不上 or 有意外
		cli_error_out('callback failed '.$url);
	}
	// error_log(print_r($output,true));
}
cli_msglog("ps%100");
$logger->success(sprintf("done params: %s",implode(" ",$argv)));
exit(0);

function cli_notify_web($str){
	global $log_channel,$logurl_prefix, $debug_flag, $port;
	if (!empty($log_channel))
		notify_web($log_channel,array(str_replace("\n","<br>",$str)),$logurl_prefix,$port,$debug_flag);
}

function cli_msglog($str){
	global $logger;
	cli_notify_web($str);
	$logger->info($str);
}
function cli_error_out($str, $exitcode=60) {
	global $argv, $logger;
	$logger->error($str);
	cli_notify_web("err:$str returns $exitcode");
	$logger->error(sprintf("params: %s",implode(" ",$argv)));
	// 給後端決定是否需要重跑 return 0 表示成功執行不重跑, 其他表示可能程式錯誤還有機會
	exit($exitcode);
}

function find_free_port() {
    $sock = socket_create_listen(0);
    socket_getsockname($sock, $addr, $port);
    socket_close($sock);
    return $port;
}
// 抓到 signal 
function sigint(){
	exit(80);
}
function shutdown() {
	global $pid,$logger;
	$msg="shutdown $pid";
	if ($pid == 0 ) 
		return;
	$r = posix_kill($pid,9);
	if ($r === true) {
		$msg.=" successed!";
	} else {
		$msg.=" failed";
	}
	$logger->success($msg);
}
