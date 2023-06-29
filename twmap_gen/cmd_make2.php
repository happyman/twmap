<?php
/**
 * 地圖產生器出圖主程式
 */
define('__ROOT__', dirname(__FILE__). "/");
require_once(__ROOT__."lib/Twmap/Websocat.php");
require_once(__ROOT__."lib/Twmap/Proj.php");
require_once(__ROOT__."lib/Twmap/Stitcher.php");
require_once(__ROOT__."lib/Twmap/Splitter.php");
require_once(__ROOT__."lib/geoPHP/geoPHP.inc");
require_once(__ROOT__."lib/Twmap/Svg/Gpx2Svg.php");
require_once(__ROOT__."lib/Twmap/Export/GarminKmz.php");
require_once(__ROOT__."lib/Twmap/Export/Pdf.php");
require_once(__ROOT__."lib/slog/load.php");
$twmap_gen_version = trim(file_get_contents(__ROOT__."VERSION"));

ini_set("memory_limit","512M");
set_time_limit(0);
use Happyman\Twmap;
$opt = getopt("O:r:v:t:i:p:g:Ges:dSl:c3m:a:D:",array("agent:","logurl_prefix:","logfile:","getopt","check"));
// for command line parse
if (isset($opt['getopt'])){
	$options=$opt;
	unset($options['getopt']);
	echo json_encode($opt);
	exit(0);
}
if (isset($opt['check'])){
	Happyman\Twmap\Proj::check();
	Happyman\Twmap\Svg\Gpx2Svg::check();
	Happyman\Twmap\Export\Pdf::check();
	Happyman\Twmap\Export\GarminKmz::check();
	Happyman\Twmap\Stitcher::check();
	Happyman\Twmap\Splitter::check();
	Happyman\Twmap\Websocat::check();
	exit(0);
}
if (!isset($opt['r']) || !isset($opt['O'])){
	echo "Usage: $argv[0] -r 236:2514:6:4:TWD67 -O outdir -v 2016 [-t title][-p][-m /tmp][-g gpxfile:0:0][-c][-G][-e][-3][-d]\n";
	echo "必須參數:\n";
	echo "       -r params: startx:starty:shiftx:shifty:datum  datum:TWD67 or TWD97\n";
	echo "       -O outdir: /home/map/out/000003 輸出目錄\n";
	echo "       -v 3|2016: 經建3|魯地圖\n";
	echo "選用參數:\n";
	echo "       -t title: title of image\n";
	echo "       -p 1|0: 1 是澎湖 pong-hu\n";
	echo "       -g gpx_file:trk_label:wpt_label 利用GPX檔案產生\n";
	echo "       -c keep color 彩圖\n";
	echo "       -e draw 100M 格線\n";
	echo "       -G merge user track_logs 包含使用者行跡\n";
	echo "       -3 for A3 output 輸出A3\n";
	echo "       -D 5x7|4x6|3x4|2x3|1x2 等輸出 dimension, 可使用多次, 空則為 5x7\n";
	echo "       -m /tmp tmpdir 暫存檔存放目錄\n";
	echo "       --check 檢查相依執行程式\n";
	echo "除錯用 -d debug \n";
	echo "       -s 1-5: from stage 1: create_tag_png 2: split images 3: make simages 4: create kmz 5: create pdf.\n";
	echo "       -S use with -s, if -s 2 -S, means do only step 2\n";
	echo "以下地圖產生器使用:\n";
	echo "       -i ip: log remote ip address --agent myhost\n";
	echo "       -l log_channel --logurl_prefix for custom url, ex: ws://myhost:9002/twmap_\n";
	echo "       -a callback url when done\n";
	echo "       --logfile /tmp/log 若存在也 log 至 file\n";
	exit(0);
}
use \stange\logging\Slog;
// keep colorred stdout *and* file log
if (isset($opt['logfile']))
	$logger =  new Slog(['file'=>$opt['logfile']]);
else
	$logger = new Slog();
$logger->useDate(True);
$logger->info("params=" . implode(" ",$argv));
// parse param
list($startx,$starty,$shiftx,$shifty,$datum)=explode(":",$opt['r']);
if (empty($startx) || empty($starty)  || empty($shiftx)  || empty($shifty) || empty($datum))
	cli_error_out("參數錯誤",0);

$version=isset($opt['v'])?$opt['v']:2016;
if (!isset($opt['t'])) 
	$title = '我的地圖';
else
	$title=mb_decode_mimeheader($opt['t']);
$keep_color = (isset($opt['c']))? 1 : 0;
$ph = isset($opt['p'])? $opt['p'] : 0;
$jump = isset($opt['s'])? $opt['s'] : 1;
if (isset($opt['S'])) $jumpstop = $jump+1; else $jumpstop = 0;
$remote_ip = isset($opt['i'])? $opt['i'] : "localhost";
if (isset($opt['d'])) $debug_flag= 1; else $debug_flag = 0;
// add output dimension, allowed multiple dims
if (isset($opt['D'])) {
	if (is_array($opt['D'])) {
		$dim = $opt['D'];
	} else {
		// string
		$dim = [ $opt['D'] ];
	}
}
else 
	$dim = [ '5x7' ];
$dim = array_unique($dim, SORT_NUMERIC);
foreach($dim as $dimm) {
	if (!in_array($dimm, [ '1x2','2x3','3x4','4x6','5x7' ]))
		cli_error_out("unsupported dim $dimm");
}
// default 2016, remove version 1
if (!in_array($version, array(3,2016,'nlsc'))) $version=2016;
if (isset($opt['l'])) $log_channel = $opt['l']; else $log_channel = "";
if (isset($opt['logurl_prefix'])) 
	$logurl_prefix=$opt['logurl_prefix'];
else 
	$logurl_prefix="wss://ws.happyman.idv.tw/twmap_";
$outpath=$opt['O'];
if (!is_dir($outpath)){
	cli_error_out("wrong outpath -O $outpath");
}
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
	$tmpdir = '/dev/shm';
}
	
$outfile_prefix=sprintf("%s/%dx%d-%dx%d-v%s%s_%s",$outpath,$startx*1000,$starty*1000,$shiftx,$shifty,$version,($ph==1)?"p":"",$datum);
$outimage=$outfile_prefix . ".tag.png";
$outimage_orig=$outfile_prefix . ".orig.tag.png";
$outimage_gray=$outfile_prefix . ".gray.png";
$outtext=$outfile_prefix . ".txt";
$outsvg = $outfile_prefix . ".svg";
$outsvg_big = $outfile_prefix . ".svg2";
$merged_gpx = $outfile_prefix. ".gpx2";
$outpdf = $outfile_prefix . ".pdf";
// cmd
$outcmd = $outfile_prefix . ".cmd";
$logger->debug($outcmd . " created");
$stage = 1;
// 決定哪一種輸出省紙
if (isset($opt['3']))
	$paper = 'A3';
else
	$paper = 'A4';

$gparams = ['startx'=> $startx, 'starty'=> $starty, 'shiftx'=> $shiftx, 'shifty'=> $shifty, 'ph'=> $ph, 'datum'=>$datum, 'tmpdir'=>$tmpdir,
'debug' => $debug_flag, 'version'=> $version];

switch($version) {
	case 3:
		$g = new Happyman\Twmap\Stitcher($gparams);
		break;
	case 2016:
		$g = new Happyman\Twmap\Rudymap_Stitcher($gparams);
		break;
	case 'nlsc':
		$g = new Happyman\Twmap\NLSC_Stitcher($gparams);
		break;
}


if (!empty($g->err)){
	cli_error_out(implode(":",$g->err),0);
}

// $g->version=$version;
if (isset($opt['G'])) {
	$g->include_gpx = 1;
} 
// get port and pid
$port = 0;
$pid = 0;

if (!empty($log_channel)) {
	// persist websocket connection for slightly better performance 
	list ($pid,$port) = Happyman\Twmap\Websocat::persist($logurl_prefix,$log_channel);
	//$port=find_free_port();
	//$cmd = sprintf("websocat -t -1 -u tcp-l:127.0.0.1:%d reuse-raw:%s%s  >/dev/null 2>&1 & echo $!",$port,$logurl_prefix,$log_channel);
	//$pid = exec($cmd,$output);
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
}else{
	$g->setLogger($logger);
}
$pixel_per_km = $g->getPixelPerKm();

cli_msglog("ps%10");
showmem("after STB created");
if ($jump <= $stage ) {

	if (file_exists($outimage)) {
		// 如果 10 分鐘之前的 dead file, 清除之
	  if (time() - filemtime($outimage) > 600) {
		// map_files
	  	$files = glob($outfile_prefix . "*");
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
	// 這時再把 cmd 寫下來, 以免蓋掉前次的執行
	file_put_contents($outcmd,implode(" ",$argv)); 
	$im = $g->create_base_png(); // 產生
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
		$param['pixel_per_km'] = $pixel_per_km;

		cli_msglog("create SVG: $outsvg");
		$svg = new Happyman\Twmap\Svg\Gpx2Svg($param);
		$ret = $svg->process();
		if ($ret === false ) {
			@unlink($outimage_orig);
			cli_error_out("svg process fail: ".print_r($param,true));
		}
		$ret = $svg->output($outsvg);
		if ($ret === false ) {
			@unlink($outimage_orig);
			cli_error_out("gpx2svg fail: ". print_r($param,true));
		}
		cli_msglog("create PNG: $outimage");
		cli_msglog("ps%+3");
		list ($ret,$msg) = $svg->svg2png($outsvg, $outimage, array($shiftx*$pixel_per_km,$shifty*$pixel_per_km));
		if ($ret === false ) {
			@unlink($outimage_orig);
			@unlink($outimage);
			cli_error_out("svg2png fail: $msg");
		}
		@unlink($outimage_orig);
	} else {
		ImagePNG($im, $outimage);
		unset($im);

	}
	cli_msglog('optimize png...');
	$g->optimize_png($outimage);
	// 加上 grid
	if (isset($opt['e'])) {
		cli_msglog("add 100M grid to image...");
		$g->im_addgrid($outimage,  100);
		cli_msglog("ps%+3");
	}
	// 若是 moi_osm or TWD97 則加上 1000 格線 , 會使用到 logo
	if ($version != 3 || $datum == 'TWD97' ){
		$g->im_addgrid($outimage, 1000);
	}
	// happyman
	cli_msglog("ps%40");
	if ($keep_color==1) {
	cli_msglog("keep colorful image...");
		copy($outimage,$outimage_gray);
	} else {
		cli_msglog("grayscale image...");
	// 產生灰階圖檔
		$g->im_file_gray($outimage, $outimage_gray, $version);
	}
	$g->im_tagimage($outimage_gray,$startx,$starty);
	cli_msglog("ps%45");
	// 加上 tag
	cli_msglog("add tag to image...");
	$g->im_tagimage($outimage,$startx,$starty);
}
cli_msglog("ps%50");
$stage = 2;
showmem("after stage 1");
if ($stage == $jumpstop) {
	echo "stop by -S\n";
	exit(0);
}
$logger->info(print_r([$g->zoom, $g->pixel_per_km],true));
unset($g);
if ($stage >= $jump ) {
	cli_msglog("split image...");
	// shortcut to -s  2
	if (!file_exists($outimage_gray) && file_exists($outimage) ){
		copy($outimage,$outimage_gray);
	}
	if (!file_exists($outimage_gray)){ 
		echo "-s 2 requires $outimage_gray\n";
		exit(1);
	}
		
	$im = imagecreatefrompng($outimage_gray);
	$i=0;$overall_total=0;
	foreach($dim as $dimension) {
		$params = ["shiftx"=>$shiftx,"shifty"=>$shifty,"paper"=>$paper,"logger"=>$logger,'dim'=>$dimension, 'pixel_per_km'=>$pixel_per_km];
		try {
			$sp[$i] = new Happyman\Twmap\Splitter($params);
		} catch (Exception $e) {
			// undefined dimension
			$logger->info(sprintf("undefined dimension %s",$dimension));
			continue;
		}
		// 取得修正過的 dimension 
		list($dim_array[$i],$type_array[$i]) = $sp[$i]->getDimType();
		list ($simage[$i],$outx[$i],$outy[$i]) = $sp[$i]->splitimage($im, $outfile_prefix);
		// 計算總共小圖數量
		$count_array[$i] = count($simage[$i]);
		$overall_total+=count($simage[$i]);
		$i++;
	}
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
	for($i=0;$i<count($dim_array);$i++){
		$sp[$i]->make_simages($simage[$i],$outx[$i],$outy[$i],"cli_msglog",$overall_total);
	}
	// 做了哪些東西
	$outinfo = [ "dim"=>$dim_array,"paper"=>$type_array, 'count'=>$count_array ];
}
unset($sp);
showmem("after stage 3");
$stage = 4;
if ($stage == $jumpstop) {
	echo "stop by -S\n";
	exit(0);
}
cli_msglog("ps%70");
if ($stage >= $jump ) {
	cli_msglog("make kmz file...");
	//require_once("lib/garmin.inc.php");
	$kmz = new  Happyman\Twmap\Export\GarminKMZ(3,3,$outimage,$ph,$datum);
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
cli_msglog("ps%75");
if ($stage >= $jump) {
	// 產生 pdf
	$pdf = new Happyman\Twmap\Export\Pdf(array('title'=> $title, 'subject'=> basename($outfile_prefix),
		 'outfile' => $outpdf, 'infiles' => array_merge(...$simage), 'a3' => $a3, 'twmap_ver'=> $twmap_gen_version, 'logger'=>$logger ));
	$pdf->setBookmarkInfo($outinfo);
	cli_msglog("save to pdf format");
	$pdf->doit('cli_msglog');
	$logger->success("$outimage done");
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
	foreach(array_merge(...$simage) as $simage_file) {
		unlink($simage_file);
	}
	unlink($outimage_gray);
}
// save output dim and paper to txt file 
$outtext_content = sprintf("%s",json_encode($outinfo));
file_put_contents($outtext,$outtext_content);
$logger->info($outtext_content . " wrote to $outtext");
// not register db yet
if (!empty($callback)){
	cli_msglog("register this map");
	$url=sprintf('%s?ch=%s&status=ok&params=%s',$callback,$log_channel,urlencode(implode(" ",$argv)));
	// log
	if (isset($opt['agent']))
		$url.="&agent=".trim($opt['agent']);
	cli_msglog("call callback api");
	// $output = file_get_contents($url);
	$cmd = sprintf("curl --connect-timeout 2 --max-time 30 --retry 10 --retry-max-time 0 %s",escapeshellarg($url));
	// 把 cmd 寫下來
	$logger->debug($cmd);
	file_put_contents($outcmd,"\n\n$cmd\n",FILE_APPEND); 
	exec($cmd,$output,$ret);
	if ($ret != 0) {
		cli_error_out('callback failed '.$url);
	}
}
cli_msglog("ps%100");
$logger->success(sprintf("done params: %s",implode(" ",$argv)));
// 成功的話 cmd 會一起被搬至新目錄備查
exit(0);

function cli_notify_web($str){
	global $port, $logger, $log_channel;
	if (!empty($log_channel)) {
		Happyman\Twmap\Websocat::notify_web_nc($str,$port);
	}
}

function cli_msglog($str){
	global $logger;
	cli_notify_web($str);
	// Web means this message sent to websocket
	$logger->info("[Web] ".$str);
}
function cli_error_out($str, $exitcode=60) {
	global $argv, $logger;
	$logger->error($str);
	cli_notify_web("err:$str returns $exitcode");
	$logger->error(sprintf("params: %s",implode(" ",$argv)));
	// 給後端決定是否需要重跑 return 0 表示成功執行不重跑, 其他表示可能程式錯誤還有機會
	exit($exitcode);
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
function showmem($str){
	$mem = memory_get_usage();
	error_log(sprintf("memory %d KB %s\n", $mem / 1024,$str));
}
