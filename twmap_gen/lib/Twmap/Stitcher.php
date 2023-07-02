<?php
Namespace Happyman\Twmap;
/*
requires: montage, curl, mktemp, convert, rm
目的 拼出 base image
*/

Class Stitcher {
	var $startx, $starty; //輸入的參數
	var $shiftx, $shifty; //輸入的參數
	var $err = array();
	var $im;
	var $ph; // 澎湖
	var $version = 3; // 那個圖
	var $include_gpx = 0; // 是否包含 gpx
	var $datum = 'TWD97';
	// var $v3img; 改成 logoimg
	var $logoimg; 
	var $tmpdir = "/dev/shm";
	var $logger = null;
	var $log_channel;
	var $debug = 0;
	var $zoom = 16;
	var $pixel_per_km = 315;
	var $tile_zyx = 0;
	
	static function check(){
		$req=[ 'montage' => [ 'package'=>'imagemagick', 'test'=>'--help'] , 
		       'composite' => [ 'package'=>'imagemagick','test'=>'--help'],
			   'pngquant' => ['package'=>'pngquant', 'test'=> '--help'],
			   'advpng' => ['package'=> 'advancecomp', 'test'=>'-h', 'optional'=>1],
			   'mktemp' => ['package'=>'coreutils', 'test'=>''], // rm, echo 
			   'curl'=>['package'=>'curl','test'=>'--help']];

		$err=0;
		$classname=get_called_class();
		foreach($req as $bin=>$meta){
			$cmd=sprintf("%s %s",$bin,$meta['test']);
			exec($cmd,$out,$ret);
			if ($ret!=0){
				printf("[%s] %s not installed, please install %s",$classname,$bin,$meta['package']);
				if (!isset($meta['optional']))
					$err++;
			}else{
				printf("[%s] %s installed %s\n",$classname,$bin,isset($meta['optional'])?"(optional)":"");
			}
		}
		if ($err>0)
			return false;
		else
			return true;
	}
	function __construct($options) {
		if (!isset($options['startx']) || !isset($options['starty']) ||!isset($options['shiftx']) ||!isset($options['starty'])){
			$this->err[] = "Not enough parameters";
			return false;
		}
		$this->startx = $options['startx'];
		$this->starty = $options['starty'];
		$this->shiftx = $options['shiftx'];
		$this->shifty = $options['shifty'];
		if (isset($options['ph']))
			$this->ph = $options['ph'];
		if (isset($options['datum']))
			$this->datum = $options['datum'];
		if (isset($options['version']))
			$this->version = $options['version'];

		if ($this->shiftx > 35 || $this->shifty > 35) {
			$this->err[] = "Sorry We Cannot create too big map";
			return false;
		}
		if ($this->is_taiwan() === false){
			$this->err[] = "不在台澎範圍內";
			return false;
		}
		if (!empty($options['tmpdir'])) {
			$this->tmpdir = $options['tmpdir'];
		}
		if (isset($options['debug']))
			$this->debug = $options['debug'];
		return TRUE;
	}

	function is_taiwan() {
		$minx = $this->startx;
		$maxx = $this->startx + $this->shiftx;
		$miny = $this->starty - $this->shifty;
		$maxy = $this->starty;
	
		if ($this->ph == 1 ){
			if ($minx >= 280 && $maxx <= 330 && $miny >= 2500 && $maxy <= 2630 )
			return true;
		}else{
			if ($minx >= 150 && $maxx <= 355 && $miny >= 2420 && $maxy <= 2800 )
			return true; 
		}
		return false;
	}
	// tag = 2 處理縮圖
	function setLogger($logger){
		$this->logger = $logger;
	}

	function setLog($channel,$logurl_prefix="wss://ws.happyman.idv.tw/twmap_",$port=0,$logger=null) {
		$this->log_channel = $channel;
		$this->logurl_prefix = $logurl_prefix;
		$this->websocat_port = $port;
		$this->logger=$logger;

	}
	function doLog($msg) {
		//if (empty($this->log_channel)
		echo $msg;
		if ($this->logger)
			$this->logger->info($msg);
		if ($this->log_channel) {
			if (preg_match("/nbr:(.*)/",$msg,$mat)){ 
				$msg = $mat[1];
			} else {
				$msg.= "<br>";	
			}

			Websocat::notify_web($this->log_channel, $msg ,$this->logurl_prefix,$this->websocat_port,$this->debug);
		}
	}


	/**
	 * depends on Noto font
	 */
	function logo($mylogotext=''){
		if (!empty($mylogotext))
			$logotext = $mylogotext;
		$fpath = sprintf("%s/%s_%s_%d.png",$this->tmpdir,$this->datum,$logotext,$this->zoom);
		if (file_exists($fpath)) { 
			$this->logger->info("logo $fpath returned");
			return $fpath;
		}
		if ($this->zoom> 16)
			$width = "-resize 170x -pointsize 60";
		else
			$width = "-resize 85 -pointsize 30";
		$cmd = sprintf("convert %s -gravity Center pango:'%s\n%s' -font Noto-Serif-CJK-TC  %s",$width, $this->datum, $logotext, $fpath);
		$this->logger->info($cmd);
		exec($cmd,$out,$ret);
		if ($ret == 0)
			return $fpath;
		else {
			$this->logger->error($cmd . " failed");
			return false;
		}
	}
	function getPixelPerKm(){
		if ($this->zoom == 18)	
			return 1260;
		else if ($this->zoom==17)
			return 630;
		else
			return 315;
	}
	function blit($fname){
		if (count($fname)==1)
			return $fname[0];
		$newname=$fname[0] . "_merged";
		$cmd = sprintf("composite  %s -compose Multiply %s",implode(" ",$fname),$newname);
		$this->logger->info($cmd);
		exec($cmd);
		//exit;
		exec("rm ". implode(" ",$fname));
		return $newname;
	}
	// main
	/* 
	create logo image, base image
	*/
	function create_base_png() {
		$this->logoimg = $this->logo($this->getlogotext());
		// 
		$this->pixel_per_km = $this->getPixelPerKm();
		$pscount = 1; 
		$pstotal = $this->shiftx * $this->shifty;

		$this->doLog( "check tiles...");
		$layers = $this->gettileurl();

			for($j=$this->starty; $j>$this->starty-$this->shifty; $j--){
				for($i=$this->startx; $i<$this->startx+$this->shiftx; $i++){
					//$tileurl = $this->gettileurl();
					//$options=array("tile_url"=> $tileurl, "image_ps_args"=> $this->getProcessParams());
					// tmppath => /dev/shm
					foreach($layers as $idx=>$layer){
						if (isset($layer['process']))
							$pre_args = $layer['process'];
						else
							$pre_args = $this->getProcessParams('premerge');
						list ($status, $fname[$idx]) = $this->img_from_tiles($i*1000, $j*1000, 1, 1, $this->zoom ,$this->ph, $layer['url'], $pre_args); 
						if ($status === false ) {
							$this->err[] = sprintf("%s failed\n",$fname[$idx]);
							return false;
						}
					}
					$fpath = $this->blit($fname);
					// 產生 progress
					$this->doLog( sprintf("nbr:%s/%s ",$pscount,$pstotal));
					$this->doLog( sprintf("nbr:ps%%+%d", 20 * $pscount/$pstotal));
					$pscount++;
					
					$fn[] = $fpath;
				}
			}

		if ($this->debug)
			$this->logger->debug(print_r($fn, true));
		// 合併
		$this->doLog( "merge tiles...");
		$outi = $outimage = tempnam($this->tmpdir,"MTILES");
		$montage_bin = "montage";
		// 加上 logo
		$psstr = $this->getProcessParams('postmerge');
		$cmd = sprintf("$montage_bin %s -mode Concatenate -tile %dx%d miff:-| composite -gravity northeast %s - miff:-| convert - -resize %dx%d\! %s png:%s",
			implode(" ",$fn), $this->shiftx ,$this->shifty, $this->logoimg, $this->shiftx*$this->pixel_per_km, $this->shifty*$this->pixel_per_km, $psstr,$outi);
		if ($this->debug)
			$this->doLog( $cmd );
		
		exec($cmd);
		// remove all temp files
		$cim = imagecreatefrompng($outi);
		$fn[] = $outi;
		foreach($fn as $fname)
			unlink($fname);
		
		return $cim;
	}
	// pass TW97 or TW67 datum
	function tempdir($dir=NULL,$prefix=NULL) {
		$template = "{$prefix}XXXXXX";
		if (($dir) && (is_dir($dir))) { $tmpdir = "--tmpdir=$dir"; }
		else { 
		$tmpdir = '--tmpdir=' . sys_get_temp_dir(); }
		return exec("mktemp -d " . escapeshellarg($tmpdir) . " $template");
	}
	// 從 web 下載圖磚來拚
	function img_from_tiles($x, $y, $shiftx, $shifty, $zoom, $ph, $tileurl,$pre_args) {
		
		$montage_bin = "montage";
		$debug = $this->debug;
		$cache_filename = "";
		$tmpdir = $this->tmpdir;
		//$tileurl = $this->gettileurl();
		$datum = $this->datum;
		$logger = $this->logger;
		// 左上
		// $dir = $base_dir . "/". $zoom;
		$dir = $this->tempdir($this->tmpdir, "MDIRXXXXX");
		// 右下
		$x1 = $x + $shiftx * 1000;
		$y1 = $y - $shifty * 1000;

		if ($debug) {
			$logger->info("img_from_tiles($x, $y, $shiftx, $shifty, $zoom, $ph, $tileurl, $pre_args");
		}
		// 輸入的座標是 TWD97 or TWD67
		if ($datum == 'TWD97'){
			$proj_func = "Happyman\Twmap\Proj::proj_97toge2";
			$ph_proj_func = "Happyman\Twmap\Proj::ph_proj_97toge2";
		}	else {
			$proj_func = "Happyman\Twmap\Proj::proj_67toge2";
			$ph_proj_func = "Happyman\Twmap\Proj::ph_proj_67toge2";
		}
		
		if ($ph == 0 ) {
			// 台灣本島 proj_67toge2 使用 cs2cs 把 67 轉 97
			list ($tl_lon,$tl_lat) = $proj_func(array($x,$y));
			$a = Proj::LatLong2XYZ($tl_lon, $tl_lat, $zoom);
			list ($br_lon,$br_lat) = $proj_func(array($x1,$y1));
			$b = Proj::LatLong2XYZ($br_lon, $br_lat, $zoom);
		} else {
			list ($tl_lon,$tl_lat) = $ph_proj_func(array($x,$y));
			$a = Proj::LatLong2XYZ($tl_lon, $tl_lat, $zoom);
			list ($br_lon,$br_lat) = $ph_proj_func(array($x1,$y1));
			$b = Proj::LatLong2XYZ($br_lon, $br_lat, $zoom);
		}

		if ($debug) {
			error_log("x=$x y=$y x1=$x1 y1=$y1 tl=$tl_lon,$tl_lat br=$br_lon,$br_lat");
			error_log("a=".print_r($a,true)." b=".  print_r($b,true));
		}

		$xx = $b[0]-$a[0]+1;
		$yy = $b[1]-$a[1]+1;

		// 先抓圖
		$download = array();
		for($j=$a[1];$j<=$b[1];$j++) {
			for ($i=$a[0]; $i<=$b[0]; $i++) {
				$imgname = sprintf("%d_%d.png",$i,$j);
				if (!file_exists("$dir/$imgname")) {
					// template
					$url = str_replace(array('{x}','{y}','{z}'), array($i,$j,$zoom),$tileurl);
					$download[] = sprintf("url=\"$url\"\noutput=\"%s\"","$dir/$imgname");
				}
			}
		}
		// run in parallel
		file_put_contents("$dir/dl.txt",implode("\n",$download));
		if ($debug) {
			//error_log("run parallel -j 4 -- < $dir/dl.txt");
			//error_log("run curl -Z --config $dir/dl.txt");
			$logger->info("run curl -Z --config $dir/dl.txt");
		}

		while(1) {
			$cmd = sprintf("curl -s -Z -L --connect-timeout 2 --max-time 30 --retry 99 --retry-max-time 0 --config %s","$dir/dl.txt");
			exec($cmd);
			$img = array();
			for($j=$a[1];$j<=$b[1];$j++) {
				for ($i=$a[0]; $i<=$b[0]; $i++) {
					$imgname = sprintf("%d_%d.png",$i,$j);
					if (file_exists("$dir/$imgname") && !is_link("$dir/$imgname")) {
						// 有錯
						if (filesize("$dir/$imgname") == 0 )
							continue;
						if ($debug) {
							error_log("$dir/$imgname ok ". filesize("$dir/$imgname"));
							$logger->info("$dir/$imgname ok ". filesize("$dir/$imgname"));
						}
						$img[] =  $imgname;
					} else {
						if ($debug) {
							error_log("$dir/$imgname not exist");
							$logger->error("$dir/$imgname not exist");
						}
						// clean tmpdir
						exec("rm -r $dir");
						return array(FALSE, "超出圖資範圍", false);
					}
				}
			}
			break;
		}
		if ($debug) {
			//	error_log(print_r($img, true));
			error_log(print_r("$xx x $yy", true));
			$logger->debug(print_r("$xx x $yy", true));
		}
		// 左上點所在的 tile, 
		$rect = Proj::getLatLonXYZ($a[0],$a[1],$zoom);
		if ($debug) {
			$logger->info("getLatLonXYZ($a[0],$a[1],$zoom)");
			$logger->debug(print_r($rect,true));
		}
		$rx = 256 / $rect->width;
		$ry = 256 / $rect->height;

		$px_shiftx = ($tl_lon - $rect->x) * $rx;
		if ($debug) {
			error_log(sprintf("tl y: %f rect->y %f\n",$tl_lat, $rect->y));
		}
		$px_shifty = 256 - (($tl_lat - $rect->y) * $ry);

		if ($debug) {
			error_log(sprintf("px_shiftx,y = %f %f\n",$px_shiftx, $px_shifty));
		}

		// 要取的範圍 width px
		$px_width = ($br_lon - $tl_lon) * $rx;
		$px_height = ($tl_lat - $br_lat) * $ry;

		if ($debug) {
			error_log(sprintf("px_width,y = %f %f\n",$px_width, $px_height));
		}
		// 拼圖
		$outimage = tempnam($this->tmpdir,"MTILES");
		$cmd = sprintf("cd %s; %s %s -mode Concatenate -tile %dx%d png:%s",
			$dir,
			$montage_bin,
			implode(" ",$img),
			$xx, $yy, $outimage);
		exec($cmd, $out, $ret);
		if ($debug) {
			$logger->info("run $cmd");
		}
		// 檢查拼起來是否正確
		if (!@is_array( getimagesize($outimage)) ) {
			unlink($outimage);
			$logger->error("ret=".implode("",$out));
			return array(FALSE, "err:".$cmd."ret=".implode("",$out), false);
		}

		$cropimage = tempnam($this->tmpdir,"CROP");
		$resize=sprintf("%dx%d\!",$this->pixel_per_km*$shiftx, $this->pixel_per_km*$shifty);
		// tw 121 以東，轉 -0.3 度
		if (($ph == 0 && $tl_lon > 121 ) || ($ph == 1 && $tl_lon > 119 )) {
			$rotate_angle = "-0.3";
			$offset_x = 0;
			$offset_y = 2;
			// equals to `affline_rotate $rotate_angle`
			$affine = "0.999986,-0.005236,0.005236,0.999986,0.000000,0.000000";
		}
		else {
			$rotate_angle = "0.3";
			$offset_x = 2;
			$offset_y = 0;
			$affine = "0.999986,0.005236,-0.005236,0.999986,0.000000,0.000000";

		}
		// just calc by http://www.imagemagick.org/Usage/scripts/affine_rotate
		//$afrotate = realpath(dirname(__FILE__)) . "/affine_rotate";
		//$cmd = sprintf("convert %s -matte -virtual-pixel Transparent -affine %s -transform +repage %s", $outimage, $affine, $outimage);
		$cmd = sprintf("convert %s -matte -virtual-pixel Transparent +distort AffineProjection %s -transform +repage %s", $outimage, $affine, $outimage);
		exec($cmd, $out, $ret);
		if ($debug){
			error_log($cmd);
			$logger->info($cmd);
		}
		// pre-merge process args
		$cmd=sprintf("convert %s -crop %dx%d+%d+%d -adaptive-resize %s %s  png:%s",
			$outimage,
			ceil($px_width), ceil($px_height),
			round($px_shiftx)+$offset_x, round($px_shifty)+$offset_y,
			$resize,
			$pre_args, 
			$cropimage);

		if ($debug) {
			$logger->info($cmd);
			error_log("cmd=". $cmd );
		}
		exec($cmd, $out, $ret);
		unlink($outimage);
		// clean tmpdir
		exec("rm -r $dir");
		if ($ret == 0 ) {
			$logger->success("$cropimage created");
			return array( true ,$cropimage, "made");
		} else {
			unlink($cropimage);
			$logger->error("ret=".implode("",$out));
			return array( false, "err:".$cmd. "ret=".implode("",$out), false);
		}
	}
	// to override
	function getlogotext() {
		return '經建三';
	}
	/* used from img_from_tile */
	function getProcessParams($when="premerge") {
		if ($when=='premerge')
			return "-equalize  -gamma 2.2";
		else
			return "";
	}
	function gettileurl() {
		if ($this->include_gpx==0){
			return [['url'=>'http://make.happyman.idv.tw/map/tw25k2001/{z}/{x}/{y}.png' ]];
		} else 
			return [[ "url"=>'http://make.happyman.idv.tw/map/twmap_happyman_nowp_nocache/{z}/{x}/{y}.png' ]];

	}
	function im_addgrid($fpath, $step_m = 100) {
		list ($w, $h) = getimagesize($fpath);

		$step = $this->pixel_per_km / (1000 / $step_m );
		for($i=0; $i<$w; $i+=$step) {
				$poly[] = sprintf(" -draw 'line %d,%d %d,%d'", round($i), 0 ,round($i),$h);
		}
		for($i=0; $i<$h; $i+=$step) {
				$poly[] = sprintf(" -draw 'line %d,%d %d,%d'", 0, round($i), $w, round($i));
		}

			// 因為格線會蓋掉 logo, 再蓋回去
		$cmd = sprintf("convert %s -fill none -stroke black %s -alpha off - | composite -gravity northeast %s - png:%s",
				 $fpath, implode("", $poly),$this->logoimg, $fpath);
		if ($this->debug)
			$this->logger->debug($cmd);
		exec($cmd,$out,$ret);
		return ($ret==0)?true:false;
	
	}
	function im_tagimage($fpath, $inp_startx, $inp_starty) {
		list ($w, $h) = getimagesize($fpath);
		// tag X
		$startx = $inp_startx;
		$starty = $inp_starty;
		if ($this->zoom == 17)
			$fontsize = 60;
		else
			$fontsize = 30;
		// 下面
		for($i=0; $i<$w; $i+=$this->pixel_per_km) {
			$label[] = sprintf(" -pointsize $fontsize label:%d -trim +repage  -bordercolor white -border 3 -geometry +%d+%d -composite ",$startx++,$i+1,$h-$fontsize);
		}
		// 左邊
		for($i=0; $i<$h; $i+=$this->pixel_per_km) {
			$label[] = sprintf(" -pointsize $fontsize label:%d -trim +repage  -bordercolor white  -border 3 -geometry +%d+%d -composite ",$starty--,1,$i+1);
		}
		$startx = $inp_startx+1;
		$starty = $inp_starty-1;
		// 上面
		for ($i=$this->pixel_per_km; $i<$w; $i+=$this->pixel_per_km) {
			$label[] = sprintf(" -pointsize $fontsize label:%d -trim +repage  -bordercolor white  -border 3 -geometry +%d+%d -composite ",$startx++,$i+1, 1);
		}
		// 右邊
		for($i=$this->pixel_per_km; $i<$h; $i+=$this->pixel_per_km) {
			$label[] = sprintf(" -pointsize $fontsize label:%d -trim +repage  -bordercolor white  -border 3  -geometry +%d+%d -composite ",$starty--,$w- $fontsize * 2.3,$i+1);
		}
	
	
		$cmd=sprintf("convert %s %s %s",$fpath, implode("",$label),$fpath);
		if ($this->debug)
			$this->logger->debug($cmd);
		// -compose bumpmap
		exec($cmd,$out,$ret);
		return ($ret==0)?true:false;
	
	}
	function im_file_gray($fpath, $outpath,  $ver=3) {
		if ($ver == 1) {
			$param = "-opaque 'rgb(93,119,80)' -fill white -opaque 'rgb(148,146,145)' -fill white     -fuzz 50%  -fill black -opaque blue  -colorspace gray";
		} else if ($ver == 3) {
			$param = "-colorspace gray miff:-|convert miff:- -brightness-contrast 20x5 -tint 40";
		}  else {	
			$param = "-colorspace gray";
		}
		$cmd = sprintf("convert %s %s %s",$fpath, $param, $outpath);
		if ($this->debug)
			$this->logger->debug($cmd);
		exec($cmd,$out,$ret);
		return $ret;
	}
	function optimize_png($fname){
		// http://pointlessramblings.com/posts/pngquant_vs_pngcrush_vs_optipng_vs_pngnq/
		// 縮小 png
		if (file_exists('/usr/bin/pngquant')) {
			$cmd = sprintf("pngquant --speed 1 -f --quality 65-95 -o %s %s",escapeshellarg($fname),escapeshellarg($fname));
		} else if (file_exists("/usr/bin/advpng")) {
		// optimize the size
			$cmd = sprintf("advpng -4 -q -z %s",escapeshellarg($fname));
		}
		$this->logger->info($cmd);
		exec($cmd,$out,$ret);
		return $ret;
	}
} //eo class
class Rudymap_Stitcher extends Stitcher {
	var $version = 2016;
	var $zoom = 16;
	function getlogotext() {
		return '魯地圖';
	}
	function getProcessParams($when = 'premerge') {
		if ($when == 'postmerge')
			return "-normalize";
		else
			return "";
	}
	function gettileurl() {
		if ($this->include_gpx==0){
			return [['url'=>'http://make.happyman.idv.tw/map/moi_nocache/{z}/{x}/{y}.png']];
		} else 
			return [[ 'url'=>'http://make.happyman.idv.tw/map/moi_happyman_nowp_nocache/{z}/{x}/{y}.png']];
	}
}

// still unuseable.
class NLSC_Stitcher extends Stitcher {
	var $zoom = 17; 
	var $tile_zyx = 1; // swap x and y, normaly z/x/y.png
	function getlogotext() {
			return 'NLSC';
	}
	function getProcessParams($when = 'premerge') {
		if ($when == 'postmerge')
			return "";
		else
			return "";
	}
	function gettileurl() {
			//return 'https://wmts.nlsc.gov.tw/wmts/EMAP15/default/EPSG:3857/%s/%s/%s';
			//return 'https://wmts.nlsc.gov.tw/wmts/MOI_CONTOUR/default/EPSG:3857/%s/%s/%s';
			if ($this->include_gpx==1){
				return [
					['url'=> 'https://wmts.nlsc.gov.tw/wmts/EMAPX99/default/EPSG:3857/{z}/{y}/{x}',
					  'process' => '-level 25%%,100%%,0.1'	],
					['url'=> 'http://make.happyman.idv.tw:8088/happyman_nowp/{z}/{x}/{y}.png',
					  'process' => ''] # '-monochrome'
				];
			}
			return [['url'=> 'https://wmts.nlsc.gov.tw/wmts/EMAPX99/default/EPSG:3857/{z}/{y}/{x}',
					'process' => '-level 25%%,100%%,0.1'	]];
	}
}
