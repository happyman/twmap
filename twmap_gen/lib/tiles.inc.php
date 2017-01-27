<?php
// $Id: tiles.inc.php 364 2013-10-31 12:31:29Z happyman $
// get image from tiles
require_once("proj_lib.php");

/**
 * img_from_tiles 
 * 
 * @param mixed $base_dir 
 * @param mixed $x 
 * @param mixed $y 
 * @param mixed $shiftx 
 * @param mixed $shifty 
 * @param mixed $zoom 
 * @param int $ph 
 * @param int $debug 
 * @param string $tmpdir 
 * @param int $warm  => 1 表示不產生 /dev/shm 檔案, 2 表示 reload cache
 * @access public
 * @return void
 */
function img_from_tiles($base_dir, $x, $y, $shiftx, $shifty, $zoom, $ph=0, $debug=0, $tmpdir="/dev/shm", $cache_dir="/mnt/twmapcache/cache", $warm = 0) {
	
	//$cache_dir = "/mnt/twmapcache/cache";
	$montage_bin = "montage";
	$cache_filename = "";
	// 如果是 1x1
	if ($shiftx == 1 && $shifty == 1 ) {

		$cache_filename = sprintf("%s/%d/%s/%d/%d_%d.png",$cache_dir,$zoom,($ph)?"ph":"tw",$x/1000,$x/1000,$y/1000);
		if (file_exists($cache_filename) && $warm != 2) {
			$outimage = tempnam($tmpdir,"MTILES");
			if (!$warm) {
				// 選擇 copy 或者 symlink
				//copy($cache_filename,$outimage);
				unlink($outimage);
				symlink($cache_filename, $outimage);
			}
			if ($debug)  {
				$msg = sprintf("cache: $cache_filename read %s", is_link($cache_filename)? "[link]" : "");
				if ($warm > 0 )
					echo "$msg\n";
				else
					error_log($msg);
			}
			return array(TRUE, $outimage, "cached: $cache_filename");
		} 
	}
	// 左上
	$dir = $base_dir . "/". $zoom;
	@mkdir($dir,0755,true);
	// 右下
	$x1 = $x + $shiftx * 1000;
	$y1 = $y - $shifty * 1000;

	if ($debug) {
		error_log("img_from_tiles($base_dir, $x, $y, $shiftx, $shifty, $zoom,$ph, $debug); \n");
	}
	if ($ph == 0 ) {
		// 台灣本島 proj_67toge2 使用 cs2cs 把 67 轉 97
		list ($tl_lon,$tl_lat) = proj_67toge2(array($x,$y));
		$a = LatLong2XYZ($tl_lon, $tl_lat, $zoom);
		list ($br_lon,$br_lat) = proj_67toge2(array($x1,$y1));
		$b = LatLong2XYZ($br_lon, $br_lat, $zoom);
	} else {
		list ($tl_lon,$tl_lat) = ph_proj_67toge2(array($x,$y));
		$a = LatLong2XYZ($tl_lon, $tl_lat, $zoom);
		list ($br_lon,$br_lat) = ph_proj_67toge2(array($x1,$y1));
		$b = LatLong2XYZ($br_lon, $br_lat, $zoom);
	}

	if ($debug) {
		error_log("x=$x y=$y x1=$x1 y1=$y1 tl=$tl_lon,$tl_lat br=$br_lon,$br_lat");
		error_log("a=".print_r($a,true)." b=".  print_r($b,true));
	}

	$xx = $b[0]-$a[0]+1;
	$yy = $b[1]-$a[1]+1;

	// 先偷抓一次看看
	for($j=$a[1];$j<=$b[1];$j++) {
		for ($i=$a[0]; $i<=$b[0]; $i++) {
			$imgname = sprintf("%d_%d.png",$i,$j);
			if (!file_exists("$dir/$imgname")) {
				// create tile cache from Internet
				exec(sprintf("wget -q -O %s 'http://rs.happyman.idv.tw/map/tw25k2001/zxy/${zoom}_${i}_${j}.png'","$dir/$imgname"));
			}
		}
	}
	$img = array();
	for($j=$a[1];$j<=$b[1];$j++) {
		for ($i=$a[0]; $i<=$b[0]; $i++) {
			$imgname = sprintf("%d_%d.png",$i,$j);
			if (file_exists("$dir/$imgname") && !is_link("$dir/$imgname")) {
				if ($debug) {
					error_log("$dir/$imgname ok ". filesize("$dir/$imgname"));
				}
				$img[] =  $imgname;
			} else {
				if ($debug) {
					error_log("$dir/$imgname not exist");
				}
				return array(FALSE, "超出圖資範圍", false);
			}
		}
	}
	if ($debug) {
		//	error_log(print_r($img, true));
		error_log(print_r("$xx x $yy", true));
	}
	// 左上點所在的 tile, 
	$rect = getLatLonXYZ($a[0],$a[1],$zoom);
	if ($debug) {
		error_log("getLatLonXYZ($a[0],$a[1],$zoom)");
		error_log(print_r($rect, true));
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
	$outimage = tempnam($tmpdir,"MTILES");
	$cmd = sprintf("cd %s; %s %s -mode Concatenate -tile %dx%d png:%s",
		$dir,
		$montage_bin,
		implode(" ",$img),
		$xx, $yy, $outimage);
	if ($debug) {
		error_log("cmd=". $cmd );
	}
	exec($cmd, $out, $ret);
	if ($debug) {
		error_log("ret=". implode("",$out) );
	}
	if ($ret != 0 ) {
		unlink($outimage);
		return array(FALSE, "err:".$cmd."ret=".implode("",$out), false);
	}

	$cropimage = tempnam($tmpdir,"CTILES");
	$resize=sprintf("%dx%d\!",315*$shiftx, 315*$shifty);

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
	//$afrotate = realpath(dirname(__FILE__)) . "/affine_rotate";
	$cmd = sprintf("convert %s -matte -virtual-pixel Transparent \
		-affine %s -transform +repage %s", $outimage, $affine, $outimage);
	exec($cmd, $out, $ret);
	if ($debug)
		error_log($cmd);
	//$cmd=sprintf("convert %s -crop %dx%d+%d+%d -adaptive-resize %s -contrast-stretch 1x1%% -sharpen 1.5x1.5 miff:- | composite -gravity northeast %s - png:%s",$outimage,
	$cmd=sprintf("convert %s -crop %dx%d+%d+%d -adaptive-resize %s -contrast-stretch 1x1%% -sharpen 1.5x1.5 png:%s",$outimage,
		ceil($px_width), ceil($px_height),
		round($px_shiftx)+$offset_x, round($px_shifty)+$offset_y,
		$resize,
		$cropimage);
	if ($debug) {
		error_log("cmd=". $cmd );
	}
	exec($cmd, $out, $ret);
	unlink($outimage);
	if ($ret == 0 ) {
		// 1x1 則 cache
		if (!empty($cache_filename)) {
			@mkdir(dirname($cache_filename),0755,true);
			copy($cropimage,$cache_filename);
		}
		if ($debug) 
			error_log("cache: $cache_filename created");
		return array(TRUE,$cropimage, "made");
	} else {
		unlink($cropimage);
		return array(FALSE, "err:".$cmd. "ret=".implode("",$out), false);
	}
}
function img_from_tiles_lonlat($base_dir, $tl_lon, $tl_lat, $br_lon, $br_lat, $zoom, $ph=0, $debug=0, $tmpdir="/dev/shm") {
	// 左上
	$dir = $base_dir . "/". $zoom;
	// 右下
	//$x1 = $x + $shiftx * 1000;
	//$y1 = $y - $shifty * 1000;

	$v3image = "imgs/v3.png";
	if ($debug) {
		error_log("img_from_tiles_lonlat($base_dir, $$tl_lon, $tl_lat, $br_lon, $br_lat, $zoom,$ph, $debug); \n");
	}
	if ($ph == 0 ) {
		// 台灣本島 proj_67toge2 使用 cs2cs 把 67 轉 97
		//list ($tl_lon,$tl_lat) = proj_67toge2(array($x,$y));
		$a = LatLong2XYZ($tl_lon, $tl_lat, $zoom);
		//list ($br_lon,$br_lat) = proj_67toge2(array($x1,$y1));
		$b = LatLong2XYZ($br_lon, $br_lat, $zoom);
		list ($x, $y) = proj_geto672(array($tl_lon, $tl_lat));
		list ($x1, $y1) = proj_geto672(array($br_lon, $br_lat));
	} else {
		//list ($tl_lon,$tl_lat) = ph_proj_67toge2(array($x,$y));
		$a = LatLong2XYZ($tl_lon, $tl_lat, $zoom);
		//list ($br_lon,$br_lat) = ph_proj_67toge2(array($x1,$y1));
		$b = LatLong2XYZ($br_lon, $br_lat, $zoom);
		list ($x, $y) = ph_proj_geto672(array($tl_lon, $tl_lat));
		list ($x1, $y1) = ph_proj_geto672(array($br_lon, $br_lat));
	}
	$shiftx = ($x1 - $x)/1000;
	$shifty = ($y - $y1)/1000;

	if ($debug) {
		error_log("x=$x y=$y x1=$x1 y1=$y1 tl=$tl_lon,$tl_lat br=$br_lon,$br_lat");
		error_log("a=".print_r($a,true)." b=".  print_r($b,true));
	}

	$xx = $b[0]-$a[0]+1;
	$yy = $b[1]-$a[1]+1;

	// 先偷抓一次看看
	for($j=$a[1];$j<=$b[1];$j++) {
		for ($i=$a[0]; $i<=$b[0]; $i++) {
			$imgname = sprintf("%d_%d.png",$i,$j);
			if (!file_exists("$dir/$imgname"))
				exec("wget -q -O /dev/null 'http://rs.happyman.idv.tw/map/tw25k2001/zxy/16_$i_$j.png'");
		}
	}
	$img = array();
	for($j=$a[1];$j<=$b[1];$j++) {
		for ($i=$a[0]; $i<=$b[0]; $i++) {
			$imgname = sprintf("%d_%d.png",$i,$j);
			if (file_exists("$dir/$imgname")) {
				if ($debug) {
					error_log("$dir/$imgname ok");
				}
				$img[] =  $imgname;
			} else {
				if ($debug) {
					error_log("$dir/$imgname not exist");
				}
				return array(FALSE, "超出圖資範圍");
			}
		}
	}
	if ($debug) {
		error_log(print_r($img, true));
		error_log(print_r("$xx x $yy", true));
	}


	// 左上點所在的 tile, 
	$rect = getLatLonXYZ($a[0],$a[1],$zoom);
	if ($debug) {
		error_log("getLatLonXYZ($a[0],$a[1],$zoom)");
		error_log(print_r($rect, true));
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
	$outimage = tempnam($tmpdir,"MTILES");
	$cmd = sprintf("cd %s; montage %s -mode Concatenate -tile %dx%d png:%s",
		$dir,
		implode(" ",$img),
		$xx, $yy, $outimage);
	if ($debug) {
		error_log("cmd=". $cmd );
	}
	exec($cmd, $out, $ret);
	if ($ret != 0 ) {
		return array(FALSE, "err:".$cmd);
	}


	$cropimage = tempnam($tmpdir,"CTILES");
	$resize=sprintf("-adaptive-resize %dx%d\!",315*$shiftx, 315*$shifty);

	// $resize = "";
	$cmd=sprintf("convert %s -crop %dx%d+%d+%d %s -contrast-stretch 1x1%% -sharpen 1.5x1.5 miff:- | composite -compose bumpmap -gravity northeast %s - png:%s",$outimage,
		ceil($px_width), ceil($px_height),
		round($px_shiftx), round($px_shifty),
		$resize,
		$v3image,
		$cropimage);
	if ($debug) {
		error_log("cmd=". $cmd );
	}
	exec($cmd, $out, $ret);
	unlink($outimage);
	if ($ret == 0 ) {
		return array(TRUE,$cropimage);
	} else {
		return array(FALSE, "err:".$cmd);
	}
}
function tempdir($dir=NULL,$prefix=NULL) {
  $template = "{$prefix}XXXXXX";
  if (($dir) && (is_dir($dir))) { $tmpdir = "--tmpdir=$dir"; }
  else { 
  $tmpdir = '--tmpdir=' . sys_get_temp_dir(); }
  return exec("mktemp -d " . escapeshellarg($tmpdir) . " $template");
}

// pass tile url pattern for flexbility
function img_from_tiles2($x, $y, $shiftx, $shifty, $zoom, $ph=0, $debug=0, $tmpdir="/dev/shm", $tileurl, $image_ps_args=array() ) {
	
	$montage_bin = "montage";
	$cache_filename = "";

	// 左上
	// $dir = $base_dir . "/". $zoom;
	$dir = tempdir($tmpdir, "MDIRXXXXX");
	// 右下
	$x1 = $x + $shiftx * 1000;
	$y1 = $y - $shifty * 1000;

	if ($debug) {
		error_log("img_from_tiles($base_dir, $x, $y, $shiftx, $shifty, $zoom,$ph, $debug); \n");
	}
	if ($ph == 0 ) {
		// 台灣本島 proj_67toge2 使用 cs2cs 把 67 轉 97
		list ($tl_lon,$tl_lat) = proj_67toge2(array($x,$y));
		$a = LatLong2XYZ($tl_lon, $tl_lat, $zoom);
		list ($br_lon,$br_lat) = proj_67toge2(array($x1,$y1));
		$b = LatLong2XYZ($br_lon, $br_lat, $zoom);
	} else {
		list ($tl_lon,$tl_lat) = ph_proj_67toge2(array($x,$y));
		$a = LatLong2XYZ($tl_lon, $tl_lat, $zoom);
		list ($br_lon,$br_lat) = ph_proj_67toge2(array($x1,$y1));
		$b = LatLong2XYZ($br_lon, $br_lat, $zoom);
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
				// create tile cache from Internet
				// exec(sprintf("wget -q -O %s 'http://rs.happyman.idv.tw/map/tw25k2001/zxy/${zoom}_${i}_${j}.png'","$dir/$imgname"));
				$download[] = sprintf("wget -c --tries=0 --read-timeout=20 -q -O %s $tileurl","$dir/$imgname",$zoom,$i,$j);
				//exec(sprintf("wget -q -O %s $tileurl","$dir/$imgname",$zoom,$i,$j));
			}
		}
	}
	// run in parallel
	file_put_contents("$dir/dl.txt",implode("\n",$download));
	if ($debug) {
		error_log("run parallel -j 10 -- < $dir/dl.txt");
	}
	putenv("SHELL=/bin/sh");
	putenv("HOME=/tmp");
	exec("parallel -j 10 -- < $dir/dl.txt");
	$img = array();
	for($j=$a[1];$j<=$b[1];$j++) {
		for ($i=$a[0]; $i<=$b[0]; $i++) {
			$imgname = sprintf("%d_%d.png",$i,$j);
			if (file_exists("$dir/$imgname") && !is_link("$dir/$imgname")) {
				if ($debug) {
					error_log("$dir/$imgname ok ". filesize("$dir/$imgname"));
				}
				$img[] =  $imgname;
			} else {
				if ($debug) {
					error_log("$dir/$imgname not exist");
				}
				// clean tmpdir
				exec("rm -r $dir");
				return array(FALSE, "超出圖資範圍", false);
			}
		}
	}
	if ($debug) {
		//	error_log(print_r($img, true));
		error_log(print_r("$xx x $yy", true));
	}
	// 左上點所在的 tile, 
	$rect = getLatLonXYZ($a[0],$a[1],$zoom);
	if ($debug) {
		error_log("getLatLonXYZ($a[0],$a[1],$zoom)");
		error_log(print_r($rect, true));
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
	$outimage = tempnam($tmpdir,"MTILES");
	$cmd = sprintf("cd %s; %s %s -mode Concatenate -tile %dx%d png:%s",
		$dir,
		$montage_bin,
		implode(" ",$img),
		$xx, $yy, $outimage);
	if ($debug) {
		error_log("cmd=". $cmd );
	}
	exec($cmd, $out, $ret);
	if ($debug) {
		error_log("ret=". implode("",$out) );
	}
	if ($ret != 0 ) {
		unlink($outimage);
		return array(FALSE, "err:".$cmd."ret=".implode("",$out), false);
	}

	$cropimage = tempnam($tmpdir,"CTILES");
	$resize=sprintf("%dx%d\!",315*$shiftx, 315*$shifty);
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
	if ($debug)
		error_log($cmd);
	$str_image_ps_arg = implode(" ",$image_ps_args);
	//$cmd=sprintf("convert %s -crop %dx%d+%d+%d -adaptive-resize %s -contrast-stretch 1x1%% -sharpen 1.5x1.5 miff:- | composite -gravity northeast %s - png:%s",$outimage,
// -white-threshold 85%% p
	$cmd=sprintf("convert %s -crop %dx%d+%d+%d -adaptive-resize %s %s  png:%s",
		$outimage,
		ceil($px_width), ceil($px_height),
		round($px_shiftx)+$offset_x, round($px_shifty)+$offset_y,
		$resize,
		//(!empty($str_image_ps_arg))?escapeshellarg($str_image_ps_arg):"",
		$str_image_ps_arg, 
		$cropimage);
	if ($debug) {
		error_log("cmd=". $cmd );
	}
	exec($cmd, $out, $ret);
	unlink($outimage);
	// clean tmpdir
	exec("rm -r $dir");
	if ($ret == 0 ) {
		return array(TRUE,$cropimage, "made");
	} else {
		unlink($cropimage);
		return array(FALSE, "err:".$cmd. "ret=".implode("",$out), false);
	}
}
