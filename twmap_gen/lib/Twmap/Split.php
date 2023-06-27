<?php
Namespace Happyman\Twmap;
/*
將 Map 分成小圖 
*/
Class Splitter {
    var $shiftx,$shifty;
    var $type;
    var $tiles;
    var $pixels;
    var $pasteb, $paster;
    var $tmpdir = '/dev/shm';
    // fixed 
    var $pixel_per_km = 315;
    var $dimension = '5x7';

    function __construct($opt){
        // A4 size: 210mm, 297mm 

        if (isset($opt['dim'])){
            $this->dimension = $opt['dim'];
            list($xx,$yy)=explode("x",$this->dimension);
            // 強迫用小邊在前面
            if ($xx > $yy)
                $this->dimension=sprintf("%dx%d",$yy,$xx);
        }
        if (isset($opt['pixel_per_km']))
            $this->pixel_per_km = $opt['pixel_per_km'];
        // A4 短/長 比例  1.414
        switch($this->dimension){
            case '4x6': //1.5
                $tiles['A4'] = array('x'=>4, 'y'=>6);
                $tiles['A3'] = array('x'=>6, 'y'=>8);
                break;
            case '3x4': // 1.3
                $tiles['A4'] = array('x'=>3, 'y'=>4);
                $tiles['A3'] = array('x'=>4, 'y'=>6);
                break;
            case '2x3': // 1.5
                $tiles['A4'] = array('x'=>2, 'y'=>3);
                $tiles['A3'] = array('x'=>3, 'y'=>4);
                break;
            case '1x2': // 2
                $tiles['A4'] = array('x'=>1, 'y'=>2);
                $tiles['A3'] = array('x'=>2, 'y'=>2);
                break;
            case '5x7': // 1.4
                $tiles['A4'] = array('x'=>5, 'y'=>7);
                $tiles['A3'] = array('x'=>7, 'y'=>10);
                break;
            default:
                throw new Exception("Undefined dimension");
                break;
        }

        $tiles['A4R'] = array('x'=>$tiles['A4']['y'], 'y'=>$tiles['A4']['x']);
        $pixels['A4'] = array("x"=>1492, "y"=>2110);
        $pixels['A4R'] = array("x"=>$pixels['A4']['y'], "y"=>$pixels['A4']['x']);
        $tiles['A3R'] = array('x'=>$tiles['A3']['y'], 'y'=>$tiles['A3']['x']);
        $pixels['A3'] = array("x"=>2110, "y"=>2984);
        $pixels['A3R'] = array("x"=>$pixels['A3']['y'], "y"=>$pixels['A3']['x']);
        $this->shiftx = $opt['shiftx'];
        $this->shifty = $opt['shifty'];
        $this->tiles = $tiles;
        $this->pixels = $pixels;
        if (!in_array($opt['paper'],['A3','A4']))
            return false;
        if (isset($opt['logger']))
            $this->logger = $opt['logger'];
        if (isset($opt['tmpdir']) && is_dir($opt['tmpdir']))
            $this->tmpdir = $opt['tmpdir'];
        $this->type = $this->determine_type($opt['paper']);
        list ($this->pasteb, $this->paster) = $this->make_paste_image();
	    $this->doLog(print_r([$this->dimension,$this->pixel_per_km,$this->type,$this->pixels[$this->type],$this->tiles[$this->type]],true),'debug');
    }
    function setLogger($logger){
		$this->logger = $logger;
	}
    function doLog($msg,$type='info') {
        if ($this->logger)
            $this->logger->$type($msg);
        echo "$msg\n";
    }
    function determine_type($type='A4') {
        $x = $this->shiftx;
        $y = $this->shifty;
        if ($type=='A3'){
            $pageX = $this->tiles['A3']['x'];
            $pageY = $this->tiles['A3']['y'];
        } else {
            $pageX = $this->tiles['A4']['x'];
            $pageY = $this->tiles['A4']['y'];
        }
        $a4 = ceil($x/$pageX) * ceil($y/$pageY);
        $a4r = ceil($x/$pageY) * ceil($y/$pageX);
        if($a4-$a4r > 0 ) {
              return $type . "R";
        }
        return $type;
    }
    function getDimType(){
        return [$this->dimension,$this->type];
    }
    function make_paste_image(){
        $pasteb = sprintf("%s/%s", $this->tmpdir , "/5x7_paste_b.png");
        $paster = sprintf("%s/%s", $this->tmpdir , "/5x7_paste_r.png");
        // 每次都產生
        $cmd = sprintf("convert -pointsize 100 -gravity Center pango:'黏             貼             處' -font Noto-Serif-CJK-TC -resize x56  -crop 340x40+0+2\! %s",
            $pasteb);
        $cmd.= sprintf("&& convert -resize 40x -pointsize 40 -gravity Center pango:'黏\n\n\n\n貼\n\n\n\n處' -font Noto-Serif-CJK-TC  %s",
            $paster);
        exec($cmd);
        $this->doLog($cmd, 'debug');
        return [$pasteb, $paster];
    }
    function cropimage($im, $fname, $startx, $starty, $sizex, $sizey) {

        $this->doLog("cropyimage im fname $startx $starty $sizex $sizey");
            $dst=imageCreatetruecolor($sizex, $sizey);
            $bgcolor = imagecolorallocate($dst, 255, 255, 255);
            imagefill($dst,0,0,$bgcolor);
            $realsizex = imagesx($im) - $startx;
            $realsizey = imagesy($im) - $starty;
            imageCopy($dst,$im,0,0,$startx,$starty,$realsizex, $realsizey);
            // imagePng($dst,"$outfile.png");
        $this->doLog("cropimage imagecopy(0,0,$startx,$starty,$realsizex,$realsizey) $sizex $sizey");
            //return $dst;
        imagePNG($dst,$fname);
    }
    /* 分成小圖 
	回傳 images, x ,y 
	*/
	function splitimage($im, $outfile, $fuzzy=0) {
        	$sizex = $this->tiles[$this->type]['x'] * $this->pixel_per_km;
        	$sizey = $this->tiles[$this->type]['y'] * $this->pixel_per_km;
		$w=imagesX($im); 
		$h=imagesY($im);
		$count=0; $py = 0;
		//$total=ceil(($w-$fuzzy)/$sizex) * ceil(($h-$fuzzy)/$sizey);
		$this->doLog("spliteimage $sizex $sizey original $w $h");
		for ($j=0; $j< $h - $fuzzy ; $j+= $sizey) {
			$py++;
			for ($i=0; $i< $w - $fuzzy ; $i+= $sizex) {
				$outfname= sprintf("%s_%s_%d.png",$outfile,$this->dimension,$count);
				$this->cropimage($im,$outfname,$i,$j,$sizex+$fuzzy,$sizey+$fuzzy);
				$imgs[$count++]=$outfname;
				$this->doLog($outfname ." created");

			}
		}
		$ret = [$imgs,$count/$py,$py];
		$this->doLog(print_r($ret,true),'debug');
		return $ret;
	}
    function im_simage_resize($fpath, $outpath, $gravity="NorthWest" ) {
        // 92% is for 5x7,  1492 -42 / 315*5 = 92%    用 y  2110-42 / 315*7 = 2068 / 2205 = 93.7%
	    $ratio_x = ($this->pixels[$this->type]['x'] - 42) /  ($this->tiles[$this->type]['x'] * $this->pixel_per_km) * 100;
        $ratio_y = ($this->pixels[$this->type]['y'] - 42) /  ($this->tiles[$this->type]['y'] * $this->pixel_per_km) * 100;
        // A4 紙張固定比例，取較小比例。1x2 才會正常。
        if ($ratio_x < $ratio_y) 
            $ratio=floor($ratio_x); 
        else 
            $ratio=floor($ratio_y);
        $this->doLog("ratio=$ratio",'debug');
        $cmd = sprintf("convert %s -resize %.02f%% -background white -compose Copy -gravity %s -extent %dx%d  miff:- |convert - -gravity center -extent %dx%d %s", 
        $fpath, $ratio, $gravity, $this->pixels[$this->type]['x'], $this->pixels[$this->type]['y'], $this->pixels[$this->type]['x']+2, $this->pixels[$this->type]['y']+2, $outpath);
        $this->doLog($cmd);
        exec($cmd,$out,$ret);
        return $ret;
    
    }
    /* 3x3 1  right button
	 o o o  pp pp cp 0 1 2
	 o o o  pp pp cp 3 4 5
	 o o o  pc pc cc 6 7 8
	 if x==$x cut right, y==$y cut button
    */
    function imageindex($x, $y, $idx, $sizex, $sizey) {
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
        $outfile = sprintf("%s/index-%d.png",$this->tmpdir,$i);
        imagepng($im, $outfile);
        return $outfile;
    }
    function im_addborder($fpath, $outpath,  $overlap, $idxfile) {

        if ($overlap['right'] == 1)
                $param[] = sprintf(" -compose bumpmap -gravity East %s -composite ", $this->paster);
        if ($overlap['buttom'] == 1)
                $param[] = sprintf(" -compose bumpmap -gravity South %s -composite ", $this->pasteb);
        if (file_exists($idxfile)) {
                $param[] = sprintf(" -compose bumpmap -gravity SouthEast %s -composite ", $idxfile);
        }
        if (count($param) == 0 )
            return true;
        $cmd=sprintf("convert %s %s %s",$fpath, implode(" ", $param), $outpath);
        $this->doLog($cmd);
        exec($cmd, $out, $ret);
        return $ret;
    }
    // wrap
    function make_simages($simage,$outx,$outy,$callback,$overall_total) {
        $total=count($simage);
        for($i=0;$i<$total;$i++) {
            if ($total == 1) {
                $this->im_simage_resize($simage[$i], $simage[$i], 'Center');
                break;
            }
            $callback(sprintf("%d / %d",$i+1,$total));
            $this->im_simage_resize($simage[$i], $simage[$i]);
            $idxfile = $this->imageindex($outx,$outy,$i, 80, 80);
            $overlap=array('right'=>0,'buttom'=>0);
            if (($i+1) % $outx != 0) 
                $overlap['right'] = 1;
            if ($i < $outx * ($outy -1)) 
                $overlap['buttom'] = 1;
            $callback("small image border added ...");
            $this->im_addborder($simage[$i], $simage[$i], $overlap, $idxfile);
            unlink($idxfile);
            $callback(sprintf("ps%%+%.02f", 20 * ($i+1)/$overall_total));
        }
    }
}
