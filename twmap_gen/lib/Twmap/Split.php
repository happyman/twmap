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
    var $real_pixel_per_km = 290;

    function __construct($opt){
        // A4 size: 210mm, 297mm 

        if ($opt['4x6']){
            $tiles['A4'] = array('x'=>4, 'y'=>6);
            $tiles['A3'] = array('x'=>6, 'y'=>8);
            $this->pixel_per_km = 315;
            $this->real_pixel_per_km = 437;
        }else{
            $tiles['A4'] = array('x'=>5, 'y'=>7);
            $tiles['A3'] = array('x'=>7, 'y'=>10);
            $this->pixel_per_km = 315;
            $this->real_pixel_per_km = 290;
        }
        $tiles['A4R'] = array('x'=>$tiles['A4']['y'], 'y'=>$tiles['A4']['x']);
        $pixels['A4'] = array("x"=>1492, "y"=>2110);
        $pixels['A4R'] = array("x"=>2110, "y"=>1492);
        $tiles['A3R'] = array('x'=>$tiles['A3']['y'], 'y'=>$tiles['A3']['x']);
        $pixels['A3'] = array("x"=>2110, "y"=>2984);
        $pixels['A3R'] = array("x"=>2984, "y"=>2110);
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
    }
    function setLogger($logger){
		$this->logger = $logger;
	}
    function doLog($msg,$type='info') {
        if ($this->logger)
            $this->logger->$type($msg);
        echo $msg;
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
        // all A4 橫式
        return $type;
    }
    function make_paste_image(){
        $pasteb = sprintf("%s/%s", $this->tmpdir , "/5x7_paste_b.png");
        $paster = sprintf("%s/%s", $this->tmpdir , "/5x7_paste_r.png");
        if (!file_exists($pasteb)) { 
            $cmd = sprintf("convert -resize x40 -pointsize 40 -gravity Center pango:'黏             貼             處' -font Noto-Serif-CJK-TC %s",
                $pasteb);
            $cmd.= sprintf("&& convert -resize 40x -pointsize 30 -gravity Center pango:'黏\n\n\n\n貼\n\n\n\n處' -font Noto-Serif-CJK-TC  %s",
            $paster);
            exec($cmd);
            $this->doLog($cmd, 'debug');
        }
        return [$pasteb, $paster];
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
    /* 分成小圖 
	回傳 images, x ,y 
	*/
	function splitimage($im, $outfile, $fuzzy) {
        $sizex = $this->tiles[$this->type]['x'] * $this->pixel_per_km;
        $sizey = $this->tiles[$this->type]['y'] * $this->pixel_per_km;
		$w=imagesX($im); $h=imagesY($im);
		$count=0; $py = 0;
		$total=ceil(($w-$fuzzy)/$sizex) * ceil(($h-$fuzzy)/$sizey);
		for ($j=0; $j< $h - $fuzzy ; $j+= $sizey) {
			$py++;
			for ($i=0; $i< $w - $fuzzy ; $i+= $sizex) {
				$outfname= $outfile . "_" . $count .".png";
				$dst=$this->cropimage($im,$i,$j,$sizex+$fuzzy,$sizey+$fuzzy);
				imagePNG($dst,$outfname);
				imageDestroy($dst);
				$imgs[$count++]=$outfname;

			}
		}
		return [$imgs,$count/$py,$py];
	}
    function im_simage_resize($fpath, $outpath, $gravity="NorthWest" ) {
        // 92% is for 5x7,  315*92% = 289.8 ~290px per km.  (1492,2110) => (290x5+40 (2), 290x7+ 80)
        $ratio = $this->real_pixel_per_km / $this->pixel_per_km;
        $cmd = sprintf("convert %s -resize %f%% -background white -compose Copy -gravity %s -extent %dx%d  miff:- |convert - -gravity center -extent %dx%d %s", 
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
}
