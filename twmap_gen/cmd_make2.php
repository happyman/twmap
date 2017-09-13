<?php
//$Id: cmd_make.php 291 2012-06-20 06:10:01Z happyman $
// 1. check login
require_once("config.inc.php");

ini_set("memory_limit","512M");
set_time_limit(0);

$opt = getopt("O:r:v:t:i:p:g:Ges:dSl:c3");
if (!isset($opt['r']) || !isset($opt['O'])|| !isset($opt['t'])){
	echo "Usage: $argv[0] -r 236:2514:6:4 [-g gpx:0:0] [-c] [-G]-O dir [-e] -v 1|3 -t title -i localhost\n";
	echo "       -r params: startx:starty:shiftx:shifty\n";
	echo "       -O outdir: /home/map/out/000003\n";
	echo "       -v 1|3|2016: version of map,default 3\n";
	echo "       -t title: title of image\n";
	echo "       -i ip: log remote address\n";
	echo "       -p 1|0: 1 if is pong-hu\n";
	echo "       -g gpx_fpath:trk_label:wpt_label \n";
	echo "       -c keep color\n";
	echo "       -d debug\n";
	echo "       -e draw 100M grid\n";
	echo "       -s 1-5: stage 1: create_tag_png 2: split images 3: make simages 4: create txt/kmz 5: create pdf. debug purpose\n";
	echo "          1 is done then go to 2, 3 ..\n";
	echo "       -S use with -s, if -s 2 -S, means do only step 2\n";
	echo "       -G merge user track_logs\n";
	echo "       -l channel:uniqid to notify web, email from web interface\n";
	exit(1);
}
// parse param
list($startx,$starty,$shiftx,$shifty)=explode(":",$opt['r']);
if (empty($startx) || empty($starty)  || empty($shiftx)  || empty($shifty) )
	cli_error_out("參數錯誤");

$version=$opt['v'];
$title=$opt['t'];
$keep_color = (isset($opt['c']))? 1 : 0;
$ph = isset($opt['p'])? $opt['p'] : 0;
$jump = isset($opt['s'])? $opt['s'] : 1;
if (isset($opt['S'])) $jumpstop = $jump+1; else $jumpstop = 0;
$remote_ip = isset($opt['i'])? $opt['i'] : "localhost";
if (isset($opt['d'])) $BETA = 1; else $BETA = 0;
if ($version != 1 && $version != 3 && $version != 2016) 
	$version = 3;
if (isset($opt['l'])) $log_channel = $opt['l']; else $log_channel = "";
$outpath=$opt['O'];
if (!file_exists($outpath)) {
	$ret = mkdir($outpath, 0755, true);
	if ($ret === FALSE) {
		cli_error_out("無法建立 $outpath");
	}
}
$outfile_prefix=sprintf("%s/%dx%d-%dx%d-v%d%s",$outpath,$startx*1000,$starty*1000,$shiftx,$shifty,$version,($ph==1)?"p":"");
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

// add version 3
switch($version){
	case 1:
	if ($ph == 1 ) {
		cli_error_out("無澎湖圖資");
	}
	$g = new STB($stbpath, $startx, $starty, $shiftx, $shifty);
	break;
	case 3:
	$g = new STB2($tilepath, $startx, $starty, $shiftx, $shifty, $ph);
	$g->version = 3;
	break;
	case 2016:
	$g = new STB2($tilepath, $startx, $starty, $shiftx, $shifty, $ph);
	$g->version = 2016;
	break;
}
if (isset($opt['G'])) {
	$g->include_gpx = 1;
} 
if (!empty($log_channel)) {
	$g->setLog($log_channel);
	// cli_msglog("setup log channel ".md5($log_channel));
	cli_msglog("start log here ^_^");
	cli_msglog("ps%0");
}
if (!empty($g->err)) 
	cli_error_out(print_r($g->err,true));


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
		cli_error_out("若發生此問題, 通常表示上一個出圖過程 crash 殘留檔案, 請回報此路徑 $outimage");
		echo "$outimage there";
		exit(0);
	  }
	}

	$im = $g->createpng(0,0,0,1,1,$BETA); // 產生
	if ($im === FALSE) cli_error_out(implode(":",$g->err));
	showmem("after image created");
	cli_msglog("ps%30");

	// 如果有 gpx 相關參數
	if (isset($opt['g'])) {
		list($param['gpx'],$param['show_label_trk'],$param['show_label_wpt'])=explode(":",$opt['g']);
		if (!file_exists($param['gpx'])) {
			cli_error_out("unable to read gpx file");
		}
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
		write_and_forget($im,$outimage,$BETA);
	}
	/*
	if (isset($opt['G'])) {
		cli_msglog("add GPX layer to PNG");
		// 直接從 gis 資料庫取得 svg
		$bbox[0] =  array($startx * 1000,$starty * 1000);
		$bbox[1] =  array(($startx+$shiftx)*1000, ($starty-$shifty)*1000);
		$bbox[2] =  array($shiftx * 315, $shifty * 315);
		list($ret, $msg) = mapnik_svg_gen($bbox, $outimage, $outsvg_big);
		if ($ret == false) {
				@unlink($outimage_orig);
				@unlink($outimage);
				cli_error_out("mapnik_svg2_gen fail: $msg");
			}
		list ($ret,$msg) = svg2png($outsvg_big, $outimage, $bbox[2]);
			if ($ret == false) {
				@unlink($outimage_orig);
				@unlink($outimage);
				cli_error_out("svg2png fail: $msg");
			}
		 cli_msglog("convert svg to png success");
		 cli_msglog("ps%+3");
	} // end of -G
	*/
	// 加上 grid
	if (isset($opt['e'])) {
		cli_msglog("add 100M grid to image...");
		im_addgrid($outimage, 100, $version);
		cli_msglog("ps%+3");
	}
	// 若是 moi_osm 則加上 1000 
	if ($version == 2016){
		im_addgrid($outimage, 1000, $version);
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
	$desc=new ImageDesc( basename($outimage), $title, $startx*1000, $starty*1000, $shiftx, $shifty, $simage, $outx, $outy, $remote_ip, $version );
	$desc->save($outtext);
	cli_msglog("make kmz file...");
	require_once("lib/garmin.inc.php");
	$kmz = new garminKMZ(3,3,$outimage,$ph);
	if ($BETA == 1 )
		$kmz->setDebug(1);
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
	$pdf = new print_pdf(array('title'=> $title, 'subject'=> basename($outfile_prefix), 'outfile' => $outpdf, 'infiles' => $simage));
	$pdf->print_cmd = 0;
	$pdf->doit();
	cli_msglog("save pdf for print...");
	echo "$outimage done";
}
showmem("after stage 5");
$stage = 6;
if ($stage == $jumpstop) {
	echo "stop by -S\n";
	exit(0);
}
cli_msglog("ps%95");
cli_msglog("almost done,cleanup...");
// 如果有給 -s, 就不刪圖檔
if ($stage >= $jump && !isset($opt['s'])) {
	foreach($simage as $simage_file) {
		unlink($simage_file);
	}
	unlink($outimage_gray);
}
// not register db yet
cli_msglog("ps%100");
exit(0);

function cli_msglog($str){
	global $log_channel, $BETA;
	if (!empty($log_channel))
		notify_web($log_channel,array($str),$BETA);
	printf("%s\n",$str);
	//error_log($str);
}
function cli_error_out($str) {
	cli_msglog("err:$str");
	exit(-1);
}
