<?php
// rangelib.php
// image functions
// $Id: rangelib.php 340 2013-08-26 13:28:06Z happyman $
// arrayfile is used for save/load an array to a file
// usage: arrayfile ( $file, $array, GET|DUMP)
function arrayfile($file,&$arr,$op) {
	$str="";
	if ($op == "GET" ) {
		if ($fp=@fopen($file,"r")) {
			while(!feof($fp)) {
				$str.=fread($fp,512);
			}
			fclose($fp);
			$arr=unserialize($str);
			return TRUE;
		}
		return FALSE;
	} else if ($op == "DUMP" ) {
		if ($fp=fopen($file,"w")) {
			$str=serialize($arr);
			fwrite($fp,$str,strlen($str));
			fclose($fp);
			return TRUE;
		}
		return FALSE;
	}
	return FALSE;
}

class mdasort {
	var $aData;//the array we want to sort.
	var $aSortkeys;//the order in which we want the array to be sorted.

	function _sortcmp($a, $b, $i=0) {
		$r = strnatcmp($a[$this->aSortkeys[$i][0]],$b[$this->aSortkeys[$i][0]]);
		if ($this->aSortkeys[$i][1] == "DESC") $r = $r * -1;
		if($r==0) {
			$i++;
			if ($this->aSortkeys[$i]) $r = $this->_sortcmp($a, $b, $i);
		}
		return $r;
	}

	function sort() {
		if(count((array)$this->aSortkeys)) {
			usort($this->aData,array($this,"_sortcmp"));
		}
	}
}

function stb_loaddata($stbindex, &$x, &$y, &$f , $arrayfile) {
	$i=0;
	// try to get sorted array it from file
	if (arrayfile($arrayfile,$array,"GET") === FALSE ) {
		// actually load
		if(!$fp=fopen($file,"r")) {
			error_log("stb_loaddata cannot open $file!\n");
			return FALSE;
		}
		while($line=fgets($fp,128)) {
			//list ($x[$i],$y[$i],$f[$i])=split(" ",$line,3);
			list($array[$i]['x'],$array[$i]['y'],$array[$i]['f'])=split(" ",trim($line),3);
			$i++;
			// echo "$i =" . $x[$i-1][0] . "$line";
		}
		fclose($fp);
		$B = new mdasort;
		$B->aData = $array;
		$B->aSortKeys = array( array('x','ASC'), array ('y','DESC'));
		$B->sort();
		echo "get it from $file\n";
		if (arrayfile($arrayfile,$array,"DUMP") === FALSE ) {
			error_log("$arrayfile write failed\n");
		}
	}
	for ($j=0;$j<count($array);$j++) {
		$x[$j]=$array[$j]['x'];
		$y[$j]=$array[$j]['y'];
		$f[$j]=$array[$j]['f'];
	}
	return TRUE;
}

function merge3($tileX,$tileY,$imgs) {
	$total=count($imgs);
	$src = imageCreateFromPng($imgs[0]);
	$xx=imageSX($src);
	$yy=imageSY($src);
	$width=$xx * $tileX;
	$height=$yy * $tileY;
	// create image
	$im = imageCreate($width,$height);
	$co=0;
	for ($i=0;$i<$total;$i++) {
		// if load from remote
		if (preg_match('/^http/',$imgs[$i])){
			$ch = curl_init();
    		curl_setopt($ch, CURLOPT_URL, $imgs[$i]); 
  			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // good edit, thanks!
    		curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1); // also, this seems wise considering output is image.
    		$data = curl_exec($ch);
   			curl_close($ch);
			$src = imagecreatefromstring($data);   
		} else {
			$src = imageCreateFromPng($imgs[$i]);
		}
		// status bar
		//if (($bar=intval($i/$total*10))!=$lastbar) {
		//	echo "= " . $bar*10 . "% "; $lastbar=$bar;
		//	flush();
		//}
		if ($i % $tileX == 0 ) {
			$locX=0; $locY=$yy*$co++;
		}
		// debug: echo "paste x=$locX y=$locY $imgs[$i]\n";
		imageCopyMerge($im,$src,$locX,$locY,0,0,$xx,$yy,100);
		$locX+=$xx;
	}
	// imagePng($im,"$outfile.tmp.png");
	imagedestroy($src);
	// echo " done\n";
	return $im;
}
function cropimage($im, $startx, $starty, $sizex, $sizey) {
	$dst=imageCreatetruecolor($sizex, $sizey);
	$bgcolor = imagecolorallocate($dst, 255, 255, 255);
	imagefill($dst,0,0,$bgcolor);
	$realsizex = imagesx($im) - $startx;
	$realsizey = imagesy($im) - $starty;
	imageCopy($dst,$im,0,0,$startx,$starty,$realsizex, $realsizey);
	// imagePng($dst,"$outfile.png");
	return $dst;
}
function createtag($string) {
	$font  = 5;
	$bigger = 1.5;
	$width  = ImageFontWidth($font) * strlen($string);
	$height = ImageFontHeight($font);
	$im = @imagecreate ($width,$height);
	$background_color = imagecolorallocate ($im, 255, 255, 255); //white background
	$text_color = imagecolorallocate ($im, 0, 0,0);//black text
	imagestring ($im, $font, 0, 0,  $string, $text_color);
	// imagepng ($im);
	$neww=$width*$bigger;
	$newh=$height*$bigger;
	$newim = imagecreate($neww, $newh);
	imagecopyresized($newim,$im,0,0,0,0,$neww,$newh,$width,$height);
	imagedestroy($im);
	return $newim;
}

function im_tagimage($fpath, $inp_startx, $inp_starty) {
	list ($w, $h) = getimagesize($fpath);
	// tag X
	$startx = $inp_startx;
	$starty = $inp_starty;
	$fontsize = 30;
	// 下面
	for($i=0; $i<$w; $i+=315) {
		$label[] = sprintf(" -pointsize $fontsize label:%d -trim +repage  -bordercolor white -border 3 -geometry +%d+%d -composite ",$startx++,$i+1,$h-$fontsize);
	}
	// 左邊
	for($i=0; $i<$h; $i+=315) {
		$label[] = sprintf(" -pointsize $fontsize label:%d -trim +repage  -bordercolor white  -border 3 -geometry +%d+%d -composite ",$starty--,1,$i+1);
	}
	$startx = $inp_startx+1;
	$starty = $inp_starty-1;
	// 上面
	for ($i=315; $i<$w; $i+=315) {
		$label[] = sprintf(" -pointsize $fontsize label:%d -trim +repage  -bordercolor white  -border 3 -geometry +%d+%d -composite ",$startx++,$i+1, 1);
	}
	// 右邊
	for($i=315; $i<$h; $i+=315) {
		$label[] = sprintf(" -pointsize $fontsize label:%d -trim +repage  -bordercolor white  -border 3  -geometry +%d+%d -composite ",$starty--,$w- $fontsize * 2.3,$i+1);
	}


	$cmd=sprintf("convert %s %s %s",$fpath, implode("",$label),$fpath);
	echo "$cmd\n";
	// -compose bumpmap
	exec($cmd,$out,$ret);
	return ($ret==0)?true:false;

}
/**
 * im_addgrid 加上 100M 的格線
 * @param  [type]  $fpath  [description]
 * @param  integer $step_m [description]
 * @param  integer $ver    [description]
 * @return [type]          [description]
 */
function im_addgrid($fpath, $v3img, $step_m = 100, $ver=3) {
	list ($w, $h) = getimagesize($fpath);
	/*
	if ($ver == 3)
		$v3img = dirname(__FILE__) . "/../imgs/v3image2.png";
	else if ($ver == 2016)
		$v3img = dirname(__FILE__) . "/../imgs/v2016image.png";
	*/
	$step = 315 / (1000 / $step_m );
	for($i=0; $i<$w; $i+=$step) {
			$poly[] = sprintf(" -draw 'line %d,%d %d,%d'", round($i),0 ,round($i),$h);
	}
	for($i=0; $i<$h; $i+=$step) {
			$poly[] = sprintf(" -draw 'line %d,%d %d,%d'", 0,round($i), $w, round($i));
	}
//	if ($ver == 3) {
		// 因為格線會蓋掉 logo, 再蓋回去
		$cmd = sprintf("convert %s -fill none -stroke black %s -alpha off - | composite -gravity northeast %s - png:%s", $fpath, implode("", $poly),$v3img, $fpath);
//	} else {
		// TODO
//		$cmd = sprintf("convert %s -fill none -stroke black -alpha off %s png:%s", $fpath, implode("", $poly), $fpath);
//	}
	echo "$cmd\n";
	exec($cmd,$out,$ret);
	return ($ret==0)?true:false;

}
function tagimage($oim, $startx, $starty, $fuzzy) {
	$w=imagesX($oim); $h=imagesY($oim);
	$im = imagecreate($w, $h);
	imageCopyMerge($im, $oim, 0,0,0,0, $w, $h, 100);
	// echo "tag x\n";
	for ($i=0; $i<$w; $i+=315) {
		$tag=createtag($startx++);
		$locY=$h - imagesY($tag) -1 - $fuzzy;
		imageCopyMerge($im,$tag,$i+1,$locY,0,0,imagesX($tag),imagesY($tag),100);
	}
	// echo "tag y\n";
	for ($i=0; $i<$h; $i+=315) {
		$tag=createtag($starty--);
		imageCopyMerge($im,$tag,1,$i+1,0,0,imagesX($tag),imagesY($tag),100);
	}
	return $im;
	// echo "output to file\n";
	// imagepng($im,"$outfile.tag.png");
	// imagedestroy($im);
}

function splitimage($im, $sizex, $sizey, $outfile, &$px, &$py, $fuzzy) {
	$w=imagesX($im); $h=imagesY($im);
	$count=0; $py = 0;
	$total=ceil(($w-$fuzzy)/$sizex) * ceil(($h-$fuzzy)/$sizey);
	for ($j=0; $j< $h - $fuzzy ; $j+= $sizey) {
		$py++;
		for ($i=0; $i< $w - $fuzzy ; $i+= $sizex) {
			$outfname= $outfile . "_" . $count .".png";
			// overwrite   if (!file_exists($outfname)) {
			$dst=cropimage($im,$i,$j,$sizex+$fuzzy,$sizey+$fuzzy);
			imagePNG($dst,$outfname);
			imageDestroy($dst);
			// overwrite    }
			$imgs[$count++]=$outfname;
			//if (($bar=intval($count/$total*10))!=$lastbar) {
			//	echo "= " . $bar*10 . "% "; $lastbar=$bar;
			//	flush();
			//}
		}
	}
	//echo "done\n";
	$px = $count / $py;
	return $imgs;
}
function colorgray($a) {

	$t = ($a["red"]+$a["green"]+$a["blue"])/3*1.5;;
	$gray= array("red" => $t, "green" => $t, "blue" => $t );
	$black= array("red" => 0, "green" => 0, "blue" => 0 );
	$white= array("red"=>255, "green" => 255, "blue" => 255);

	// 93,119,80 // 255,255,255 // 48,146,145
	// to white
	$colors[0]=array ( "red" => 93, "green" => 119, "blue" => 80);
	$colors[1]=array ( "red" => 148, "green" => 146, "blue" => 145);
	$colors[2]=array ("red"=>255, "green" => 255, "blue" => 255);
	// to black
	// $river[0]=array ( "red" => 84, "green" => 122, "blue" => 136);
	// 淺藍色不換
	$colors[3]=array ( "red" => 0, "green" => 67, "blue" => 119);

	// to array
	$swapto[0] = $white;
	$swapto[1] = $white;
	$swapto[2] = $white;
	$swapto[3] = $black;

	for($i=0; $i<count($colors); $i++) {
		if ($a["red"] == $colors[$i]["red"] &&
			$a["green"] == $colors[$i]["green"] &&
			$a["blue"] == $colors[$i]["blue"] ) return $swapto[$i];
	}
	return $gray;
}


function toprintable2($fname,$newname) {
	$im=imageCreateFromPng($fname);
	$total=imagecolorstotal($im);
	for ($i=0; $i<$total; $i++) {
		$arr = imageColorsForIndex($im, $i);
		$r = colorgray($arr);
		imageColorSet($im, $i, $r["red"], $r["green"], $r["blue"]);
	}
	imagePNG($im,$newname);
}
function toprintable3($fname) {
	$im=imageCreateFromPng($fname);
	$total=imagecolorstotal($im);
	for ($i=0; $i<$total; $i++) {
		$arr = imageColorsForIndex($im, $i);
		$r = colorgray($arr);
		imageColorSet($im, $i, $r["red"], $r["green"], $r["blue"]);
	}
	return $im;
}
// grayscale by Imagemagick
function im_grayscale($im, $tmpdir="/dev/shm") {
	$fname = tempnam($tmpdir,"GR");
	$newfname = tempnam($tmpdir,"GRAYED");
	if (imagepng($im, $fname, 0)) {
		$cmd=sprintf("convert png:%s -colorspace gray png:%s",$fname,$newfname);
		exec($cmd,$out,$ret);
		if ($ret == 0 ) {
			$nim = imagecreatefrompng($newfname);
			unlink($newfname);
			unlink($fname);
			return $nim;
		} else {
			error_log("error $cmd");
		}

	}
	error_log("error write $fname");
	unlink($newfname);
	unlink($fname);
	return $im;
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
	echo "$cmd\n";
	exec($cmd,$out,$ret);
	return $ret;
}
function im_simage_resize($type, $fpath, $outpath, $gravity="NorthWest" ) {
	global $pixels;

	$cmd = sprintf("convert %s  -resize 92%% -background white -compose Copy -gravity %s -extent %dx%d  miff:- |convert - -gravity center -extent %dx%d %s", $fpath, $gravity, $pixels[$type]['x'], $pixels[$type]['y'], $pixels[$type]['x']+2, $pixels[$type]['y']+2, $outpath);
	echo "$cmd\n";
	exec($cmd,$out,$ret);
	return $ret;

}
function im_addborder($fpath, $outpath, $type,  $overlap, $idxfile) {
        global $stitch;
        if ($overlap['right'] == 1)
                $param[] = sprintf(" -compose bumpmap -gravity East %s -composite ", $stitch[$type]['right']);
        if ($overlap['buttom'] == 1)
                $param[] = sprintf(" -compose bumpmap -gravity South %s -composite ", $stitch[$type]['buttom']);
	if (file_exists($idxfile)) {
                $param[] = sprintf(" -compose bumpmap -gravity SouthEast %s -composite ", $idxfile);
	}
        if (count($param) == 0 )
                return true;
        $cmd=sprintf("convert %s %s %s",$fpath, implode(" ", $param), $outpath);
        echo $cmd;
        exec($cmd, $out, $ret);
        return $ret;
}

function grayscale(&$im) {
	$total=imagecolorstotal($im);
	for ($i=0; $i<$total; $i++) {
		$arr = imageColorsForIndex($im, $i);
		$r = colorgray($arr);
		imageColorSet($im, $i, $r["red"], $r["green"], $r["blue"]);
	}
	return $im;
}
/* 3x3 1  right button
	 o o o  pp pp cp 0 1 2
	 o o o  pp pp cp 3 4 5
	 o o o  pc pc cc 6 7 8
	 if x==$x cut right, y==$y cut button
 */
function imageindex($x, $y, $idx, $sizex, $sizey ) {
	$im = imageCreate($sizex, $sizey );

	$width = intval (($sizex-2) / $x );
	$height = intval (($sizey-2) / $y );
	$white = imageColorAllocate ($im, 0xff, 0xff, 0xff);
	$black = imageColorAllocate ($im, 0x00, 0x00, 0x00);
	$count=0;
	for ($j = 0; $j < $y; $j++ ) {
		for ($i=0; $i< $x; $i++ ) {
			$pointx = $i * $width;
			$pointy = $j * $height;
			if ($count++ == $idx )
				imageFilledRectangle($im, $pointx, $pointy, $pointx+ $width, $pointy+$height, $black);
			else
				imageRectangle($im, $pointx, $pointy, $pointx+ $width, $pointy+$height, $black);
		}
	}
	return $im;
}
function tagXline($startx, $movX ) {
	// 54 |x  x+1 x+2 x+3 | 54
	$im = imageCreate(  $movX * 315 + 54 * 2 , 22 );
	$white = imageColorAllocate ($im, 0xff, 0xff, 0xff);
	for ($i=0; $i< $movX; $i++ ) {
		$tag=createtag($startx + $i);
		imageCopyMerge($im,$tag,54 + ($i*315)+1,0,0,0,imagesX($tag),imagesY($tag),100);
	}
	imageDestroy($tag);
	return $im;
}
/* XXXX 54x22
	 XXX  40x22
 */
function tagYline($starty, $movY) {
	$im = imageCreate( 54, 22 * 2 + 315 * $movY );
	$white = imageColorAllocate ($im, 0xff, 0xff, 0xff);
	for ($i=0; $i< $movY; $i++ ) {
		$tag=createtag($starty - $i);
		imageCopyMerge($im,$tag,0, 22 + ($i*315),0,0,imagesX($tag),imagesY($tag),100);
	}
	imageDestroy($tag);
	return $im;
}
/*
			22
	 54 + x x x  + 54
			22
		image add fuzzy
		加上黏貼/剪下邊
 */
function addborder2($oim,$x,$y,$i, $imglib ) {
	// echo "calling addborder2( oim, newim, $x , $y, $i ) \n";
	// if (($i+1) % $x == 0)  $rightimg = "imgs/cut-r.png"; else $rightimg="imgs/paste-r.png";
	// if (($i+1) % $y == 0)  $buttonimg = "imgs/cut-b.png"; else $buttonimg="imgs/paste-b.png";
	if (($i+1) % $x == 0)  $rightimg = $imglib[0];  else $rightimg= $imglib[1];
	if ($i >= $x * ($y-1))  $buttonimg = $imglib[2]; else $buttonimg=$imglib[3];
	$width = imagesX($oim);
	$height = imagesY($oim);
	// 因為支援 6x4 的關係, 變動加上的邊框大小
	list ($rimx, $junk) = getimagesize($rightimg);
	list ($junk, $bimy) = getimagesize($buttonimg);
	error_log("addcopypasteborder2 ". sprintf("original: %d x %d => new size: %d x %d\n", $width, $height, $width + $rimx, $height + $bimy));
	$newim = imageCreate($width + $rimx,$height + $bimy);
	$white = imageColorAllocate ($newim, 0xff, 0xff, 0xff);
	$black = imageColorAllocate ($newim, 0x00, 0x00, 0x00);

	// $im = ImageCreateFromPng($fname);
	$im = $oim;
	imageCopyMerge($newim, $im, 0, 0, 0, 0, imagesX($im), imagesY($im), 100);
	imageDestroy($im);

	$rim = imagecreatefrompng($rightimg);
	imageCopyMerge($newim, $rim, $width+1, 0, 0, 0, imagesX($rim), imagesY($rim), 100 );
	imageDestroy($rim);

	$bim = imagecreatefrompng($buttonimg);
	imageCopyMerge($newim, $bim, 0, $height+1, 0, 0, imagesX($bim), imagesY($bim), 100 );
	imageDestroy($bim);

	$idxim = imageindex($x, $y, $i, 95, 95 );
	//imageCopyMerge($newim, $idxim, $width+2, $height+2, 0, 0, imagesX($idxim), imagesY($idxim), 100);
	imageCopyMerge($newim, $idxim, $width+$rimx-98, $height+$bimy-98, 0, 0, imagesX($idxim), imagesY($idxim), 100);
	imageDestroy($idxim);

	// imagePNG($newim,$newname);
	return $newim;
}
//  tag
function addtagborder2($oim, $x, $y, $i, $startx, $starty, $outputsize, $shiftx, $shifty, $fuzzy ) {
	$width = imagesX($oim);
	$height = imagesY($oim);
	if ($fuzzy < 54*2 ) $addx = 54*2 + 4; else $addx =0;
	if ($fuzzy < 22*2 ) $addy = 22*2 + 4 ; else $addy =0;
	error_log(sprintf("addtagborder2: original: %d x %d => new size: %d x %d\n", $width, $height, $width + $addx, $height + $addy));
	$im = imagecreate($width + $addx, $height + $addy );
	$white = imageColorAllocate ($im, 0xff, 0xff, 0xff);
	imageCopyMerge($im, $oim, 55, 23, 0, 0, imagesX($oim), imagesY($oim), 100);
	// 不需要算序號
	$tagx = $startx;
	$tagy = $starty;
	if ( $i < $x ) {
		// add top
		$topim = tagXline($tagx, $outputsize['x']);
		imageCopyMerge($im, $topim, 0, 0, 0, 0, imagesX($topim), imagesY($topim), 100);
		imagedestroy($topim);
	}
	if ( $i >= $x * ($y-1)) {
		// add buttom
		$topim = tagXline($tagx, $outputsize['x']);
		$ozy =  $outputsize['y'];
		$tty = 22 + ((($shifty % $ozy) == 0 )? $ozy : ($shifty % $ozy)) *315 + 2;
		// echo "tty = $tty $tileY % $ozy \n";
		imageCopyMerge($im, $topim, 0, $tty, 0, 0, imagesX($topim), imagesY($topim), 100);
		imagedestroy($topim);

	}
	if ( $i % $x == 0 ) {
		// add left
		$topim = tagYline($tagy, $outputsize['y']);
		imageCopyMerge($im, $topim, 0, 0, 0, 0, imagesX($topim), imagesY($topim), 100);
		imagedestroy($topim);
	}
	if ( ($i+1) % $x == 0 ) {
		// add right
		$topim = tagYline($tagy, $outputsize['y']);
		$ozx =  $outputsize['x'];
		$ttx = 54 + ((($shiftx % $ozx) == 0 )? $ozx : ($shiftx % $ozx)) *315 + 2;
		// echo "ttx = $ttx $tileX % $ozx \n";
		imageCopyMerge($im, $topim, $ttx, 0, 0, 0, imagesX($topim), imagesY($topim), 100);
		imagedestroy($topim);
	}
	return $im;
}

function write_and_forget(&$im, $fname, $debug=0) {
	ImagePNG($im, $fname);
	imagedestroy($im);
	// debug 時不要 optimize 以加快測試時間
	if ($debug==0){
	// http://pointlessramblings.com/posts/pngquant_vs_pngcrush_vs_optipng_vs_pngnq/
	// 縮小 png
		if (file_exists('/usr/bin/pngquant')) {
			exec(sprintf("pngquant --speed 1 -f --quality 65-95 -o '%s' '%s'",$fname,$fname));
		} else if (file_exists("/usr/bin/advpng")) {
		// optimize the size
			exec("advpng -4 -q -z $fname");
		}
	}
}
function showmem($str){
	$mem = memory_get_usage();
	error_log(sprintf("memory %d KB %s\n", $mem / 1024,$str));
}

// function printableimage($fname,
/*
require_once("thumb.php");
function thumb($fname, $savefile ) {
	 $th = new thumbnail($fname);
	 $th->size_auto(390);
	 $th->save($savefile);
}
 */
/**
 * determine_type
 *
 * @param mixed $x
 * @param mixed $y
 * @access public
 * @return void
 */
function determine_type($x, $y, $page46=0) {
  if ($page46 == 1 ) {
		$pageX = 4;
		$pageY = 6;
	} else {
		$pageX = 5;
		$pageY = 7;
	}
	$a4 = ceil($x/$pageX) * ceil($y/$pageY);
	$a4r = ceil($x/$pageY) * ceil($y/$pageX);
	if($a4-$a4r > 0 ) {
		return 'A4R';
	}
	// all A4 橫式
	return 'A4';
}
function determine_type_a3($x, $y) {

		$pageX = 7;
		$pageY = 10;

	$a4 = ceil($x/$pageX) * ceil($y/$pageY);
	$a4r = ceil($x/$pageY) * ceil($y/$pageX);
	if($a4-$a4r > 0 ) {
		return 'A3R';
	}
	// all A4 橫式
	return 'A3';
}
/**
 * resizeA4
 * 強制 A4 大小
 * @param mixed $fpath
 * @param mixed $type
 * @access public
 * @return void
 */
function resizeA4($fpath, $type) {
	list($w, $h) = getimagesize($fpath);
	if ($type == 'A4')  {
		$newxx = 1492;
		$newyy = 2110;
	} else  {
		$newxx = 2110;
		$newyy = 1492;
	}

	$cmd = sprintf("convert %s -background white -gravity center -extent '%sx%s!' %s",$fpath,$newxx,$newyy,$fpath);
	error_log($cmd);
	exec($cmd,$ret);
}
function resizeA42($fpath, $type) {
	//list($w, $h) = getimagesize($fpath);
	if ($type == 'A4')  {
		$newxx = 1492;
		$newyy = 2110;
	} else  {
		$newxx = 2110;
		$newyy = 1492;
	}
	$cmd = sprintf("convert %s -resize 91.5%% png:-|convert - -background white -gravity center -extent '%sx%s' %s",$fpath,$newxx,$newyy,$fpath);
	error_log($cmd);
	exec($cmd,$ret);

}

function word_split($str, $width) {
	$len = mb_strlen($str,'utf-8');
	for($i=1;$i<=$len;$i++){
		$word[$i-1] = mb_substr($str, $i-1, 1, 'utf-8');

	}
	$newstr = $word[0];
	$teststr = "";
	$lastlen = mb_strwidth($newstr, 'utf-8');
	for($i=1;$i<$len;$i++){
		// echo "word $i = " . $word[$i] . "\n";
		$teststr = $newstr . $word[$i];
		$testlen = mb_strwidth($teststr,'utf-8');
		if ($testlen % $width == 0 ) {
			$newstr .= $word[$i];
			$newstr .= "\n";
		} else if ($testlen % $width == 1 ) {
			$newstr .= "\n".$word[$i];
		} else {
			$newstr .= $word[$i];
		}
	}
	return explode("\n",trim($newstr));
	//echo $len;
}
function indexcmp($a,$b){
  preg_match("/_(\d+).png/",$a,$aa);
	  preg_match("/_(\d+).png/",$b,$bb);
		  return intval($aa[1]) - intval($bb[1]);
}

