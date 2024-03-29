<?php
// $Id: STB.inc.php 365 2013-11-21 05:41:28Z happyman $

function dumpvars($obj) {
	$arr = get_object_vars($obj);
	while (list($prop, $val) = each($arr))
		echo "\t$prop = $val\n";
}

Class STB {
	var $startx, $starty; //輸入的參數
	var $shiftx, $shifty; //輸入的參數
	var $x, $y, $f; // 相關 array
	var $arrayfile; // 暫時存下 sorted array 
	var $stbindex, $stbdir;  // STB 目錄
	var $movX, $movY;  // 剪裁的位移
	var $tileX, $tileY; // 幾 乘 幾 merge 用
	var $err = array();
	var $log_channel = "";
	var $logurl_prefix="";
	var $datum;
	var $tmpdir = "/dev/shm";
	var $debug=0;
	var $logger=null;

	function __construct($stbdir, $startx, $starty, $sx, $sy, $datum='TWD67',$tmpdir="") {
		if ($sx > 35 || $sy > 35) {
			$this->err[] = "Sorry We Cannot create too big map";
			return false;
		}
		if ($this->is_taiwan($startx,$startx+$sx,$starty-$sy,$starty,0) === false){
			$this->err[] = "不在台澎範圍內";
			return false;
		}
		$testfd = @fopen($stbdir . "/stb-index","r");
		if ($testfd) {
			$this->startx = $startx;
			$this->starty = $starty;
			$this->shiftx = $sx;
			$this->shifty = $sy;
			$this->datum = $datum;
			if (!empty($tmpdir)){
				$this->tmpdir = $tmpdir;
			}
			if ($this->datum == 'TWD97'){
				$this->stbindex= $stbdir . "/stb-index-97" ;
				$this->arrayfile = $this->tmpdir . "/sorted_array_web-97";
			} else {
				$this->stbindex= $stbdir . "/stb-index";
				$this->arrayfile = $this->tmpdir . "/sorted_array_web";
			}
			$this->stbdir= $stbdir;

			// print_r($this);
		} else {
			$this->err[] =	"No Index file... bye";
			return false;
		}
	} 
	// bound check
	function is_taiwan($minx,$maxx,$miny,$maxy,$ph){
		if ($ph == 1 ){
			if ($minx >= 280 && $maxx <= 330 && $miny >= 2500 && $maxy <= 2630 )
			return true;
		}else{
			if ($minx >= 150 && $maxx <= 355 && $miny >= 2420 && $maxy <= 2800 )
			return true; 
		}
		return false;
	}
	/**
	 * setLog 
	 *  打開 log
	 * @param mixed $channel 
	 * @access public
	 * @return void
	 */
	function setDebug($flag){
		$this->debug = $flag;
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

			notify_web($this->log_channel, array($msg),$this->logurl_prefix,$this->websocat_port,$this->debug);
		}
	}
	function load_index() {
		// try to get sorted array it from file
		// $this->doLog("try to load ". $this->arrayfile . " from " . $this->stbindex);
		if (arrayfile($this->arrayfile,$array,"GET") === false ) {
			// actually load
			$i = 0;
			$fp=fopen($this->stbindex,"r");
			if ($fp === false) {
				$this->err[] = "cannot open stbindex!\n";
				$this->doLog("cannot open ".$this->stbindex);
				return false;
			}
			while($line=fgets($fp,128)) {
				list($array[$i]['x'],$array[$i]['y'],$array[$i]['f'])=preg_split("/\s+/",trim($line),3);
				$i++;
				// echo "$i =" . $this->x[$i-1][0] . "$line";
			}
			fclose($fp);
			$B = new mdasort;
			$B->aData = $array;
			$B->aSortKeys = array( array('x','ASC'), array('y','DESC'));
			$B->sort();
			// echo "get it from $file\n";
			if (arrayfile($this->arrayfile,$array,"DUMP") === false ) {
				$this->err[] = "$arrayfile write failed\n";
				$this->doLog("cannot write ".$this->arrayfile);
				return false;
			}
		}
		for ($j=0;$j<count($array);$j++) {
			$this->x[$j]=$array[$j]['x'];
			$this->y[$j]=$array[$j]['y'];
			$this->f[$j]=$array[$j]['f'];
		}
		$this->doLog("index loaded..". $this->arrayfile . "\n");
		return TRUE;
	}
	// whichmap given x,y => return png index
	function whichimage($targetX,$targetY,$shortcut=0) {
		$boxX=3251.2;
		$boxY=3251.25;
		$total=count($this->x);
		for ($i=$shortcut; $i< $total; $i++) {
			$diffX= $targetX - $this->x[$i];
			$diffY= $this->y[$i] - $targetY;
			if ($diffX >= 0 && $diffY >= 0 &&
				$diffX < $boxX && $diffY < $boxY )  {
					// echo "found $targetX $targetY in ". $this->x[$i] . $this->y[$i] . $this->f[$i]."\n";
					$found=1;
					// echo "found $i\n"shortcut;
					return $i;
				}
		}
		return false;
	}
	// getimagefilenames will fill movX movY tileX tileY and 
	// return filenames of images to merge
	function getimagefilenames() {
		if ($this->load_index() === false ) {
			$this->err[] = "load_index() return false\n";
			return false;
		}
		$map=array();
		$img=array();
		$this->tileX=0;
		$found = 0; 
		$shortcut=0; $z=0;
		for ($j=0; $j<= $this->shifty; $j++) {
			if ($j==1) $this->tileX=$z;
			for ($i=0; $i<= $this->shiftx; $i++) {
				$locX=$this->startx * 1000 +$i*1000;
				$locY=$this->starty * 1000 -$j*1000;
				// echo "search $locX $locY\n";
				// 找出右下角座標
				if ($found==1 &&
					( $locX < $this->x[$d]+3251.20 && $locY > $this->y[$d]-3251.25))
					continue;
				// echo "really search $locX $locY\n";
				if ($d=$this->whichimage($locX,$locY,$shortcut)) {
					if (array_search($this->f[$d], $map) === false) {
						if ($found==0) {
							$this->movX=intval(($locX-$this->x[$d]) * 1024 / 3251.203125);
							$this->movY=intval(($this->y[$d]-$locY) * 1024 / 3251.25);
							$found=1;
							$shortcut=$d;
						}
						$map[$z++]=$this->f[$d];
					}
				} else {
					$this->err[] = "out of range..$locX $locY";
					return false;
				}
			}
		}
		for ($i=0; $i< $z; $i++) {
			$pic=$this->stbdir . "/" . $map[$i];
			$img[$i]=$pic;
			// good debug: echo "$i $pic\n";
		}
		$this->tileY= $z / $this->tileX;
		return $img;
	} // eo getimagefilenames
	// tag = 2 處理縮圖
	var $createfromim; // != 0 表示圖形已經有了
	var $im;
	function setim($im, $fname="") {
		$this->createfromim = 1;
		if ($fname && file_exists($fname)) {
			$this->im = imagecreatefrompng($fname);
		} else {
			$this->im = $im;
		}
		// 背景 = 白色
		$bgcolor = imagecolorallocate($this->im, 255, 255, 255);


	}
	function unsetim() {
		$this->createfromim = 0;
	}
	function createpng($tag=0, $gray=0, $fuzzy=0, $x=1, $y=1, $d=0, $borders=array()) {
		if ($this->datum=="TWD97")
				$this->v3img = dirname(__FILE__) . "/../imgs/v1image97.png";
			else
				$this->v3img = dirname(__FILE__) . "/../imgs/v1image.png";
		if ($this->createfromim == 1 ) { // just load image from im or filename
			$cim=$this->im;
		} else {  // load from STB files
			$images= $this->getimagefilenames();
			$this->doLog("ps%+10");
			// dumpvars($this);
			// now crop it
			if ($images === false) {
				return false;
			}
			// if stbdir contains http, download from remote
			$im=merge3($this->tileX, $this->tileY, $images);
			$this->doLog("image marged..");
			$cim=cropimage($im,$this->movX,$this->movY,
				$this->shiftx * 315 + $fuzzy, 
				$this->shifty * 315 + $fuzzy );
		}
		if ($tag == 1 ) {
			// echo "tag it ...\n";
			$this->doLog("tag image..");
			$cim=tagimage($cim, $this->startx, $this->starty, $fuzzy);
		} else if ($tag == 2 ) {
			if ($this->outsizex==0 || $this->outsizey==0) { 
				$this->err[]= "Please call setoutsize() first\n"; return false;}
					if ($this->createfromim ==0 && ($this->shiftx < $this->outsizex || $this->shifty < $this->outsizey) ) {
						// echo "resizing image...\n";
						$dst=imageCreate($this->outsizex * 315 + $fuzzy , $this->outsizey * 315 + $fuzzy);
						$bgcolor = imagecolorallocate($dst, 255, 255, 255);
						imagefill($dst, 0, 0, $bgcolor);
						imageCopy($dst,$cim,0,0,0,0,imageSX($cim), imageSY($cim));
						$cim=$dst;
					}
				// refer to X,Y, I
			// debug: echo "addtagbprder2 cim $x $y $d $this->startx, $this->starty, 4x6, $this->shiftx, $this->shifty $fuzzy \n";
				$cim=addtagborder2($cim, $x, $y, $d,  $this->startx, $this->starty, 
					array("x"=>$this->outsizex,"y"=>$this->outsizey), 
					$this->shiftx, $this->shifty, $fuzzy);
				$this->doLog("add border and index ..");
				if (!($x==1 && $y==1)) {
					// echo "add boder to cut or paste...\n";
					$cim=addborder2($cim, $x, $y, $d, $borders);
					$this->doLog("add border ..");
				}
		}
		if ($gray == 1 ) {
			// 
			// echo "making it gray...\n";
			$cim = grayscale($cim);
		} else if ( $gray == 2 ) {
			error_log( "making it bright more and gray...");
			if (function_exists('imagefilter')) {
				// echo "using imagefilter...\n";
				error_log("using imagefilter");
				// imagefilter($cim, IMG_FILTER_BRIGHTNESS, 21);
				imagefilter($cim, IMG_FILTER_CONTRAST, 100);
			} else {

				$cim = grayscale($cim);
				$this->doLog("grayscle image ..");
			}

		}
		return $cim;
	}
	function getoutx() {
		return ceil($this->shiftx / $this->outsizex);
	}
	function getouty() {
		return ceil($this->shifty / $this->outsizey);
	}
	var $outsizex, $outsizey;
	function setoutsize($sx,$sy) {
		$this->outsizex= $sx;
		$this->outsizey= $sy;
	}
	function getsimages() {
		// input 4x6
		$sx=$this->outsizex; // 4 
		$sy=$this->outsizey; // 6 
		$out=array();
		$c=0;
		$outx = $this->getoutx();
		$outy = $this->getouty();
		for ($j=0; $j< $outy; $j++) {
			for($i=0; $i< $outx; $i++) {
				$out[$c]["x"]=$this->startx + $i*$sx;
				$out[$c]["y"]=$this->starty - $j*$sy;
				if ( ($i+1)*$sx > $this->shiftx )
					$out[$c]["shx"]=$this->shiftx % $sx;
				else
					$out[$c]["shx"]=$sx;
				if ( ($j+1)*$sy > $this->shifty )
					$out[$c]["shy"]=$this->shifty % $sy;
				else
					$out[$c]["shy"]=$sy;
				// print_r($out[$c]);
				$c++;
			}
		} 
		return $out;
	}
} //eo class
class ImageDesc {
	var $desc;
	function __construct($file,$title,$startx,$starty,$shiftx,$shifty,
		$imgs,$px,$py,$host="localhost",$version=3,$datum="TWD67") {
			$this->desc = array( "file"=> $file, "title" => $title,
				"locX"=> $startx, "locY"=> $starty, "host" => $host,
				"tileX"=>$shiftx, "tileY"=>$shifty,
				"date"=>date("d-M-Y H:i"),
				"imgs"=>$imgs, "px"=>$px, "py"=>$py, 
				"version"=>$version, "datum"=>$datum );
		}
	function save($file) {
		arrayfile($file, $this->desc, "DUMP");
	}
	function load($file) {
		arrayfile($file, $this->desc, "GET");
		return $this->desc;
	}
}   

// frontend functions
//
function error_out($str) {
	global $ERROROUT;
	if ($ERROROUT == 'ajax') {
		$data['error'] = $str;
		$data['status'] = "error";
		echo json_encode($data);
	} else {
		error_log("error_out $str");
?>
<html><head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
</head><body>
		<script type="text/javascript">
		function goBack()
		{
			window.history.back()
		}
		</script>
<h1><?php echo $str; ?></h1>
<input type="button" value="回到上頁" onclick="goBack()" />
</body></html>
<?php
		exit(0);
	}
	exit(0);
}
function ok_out($str,$insert_id) {
	global $ERROROUT;
	if ($ERROROUT == 'ajax') {

		header("Content-Type: application/json");
		$data['msg'] = $str;
		$data['id'] = $insert_id;
		$data['status'] = "ok";
		echo json_encode($data);
		//error_log("ok_out".$str);
		flush();
		exit(0);
	} else {
		echo "<script>location.replace('main.php?tab=3&mid=$insert_id');</script>";
		exit(0);
	}
}
require_once("tiles.inc.php");
Class STB2 extends STB {
	var $startx, $starty; //輸入的參數
	var $shiftx, $shifty; //輸入的參數
	var $x, $y, $f; // 相關 array
	var $arrayfile; // 暫時存下 sorted array 
	var $stbindex, $stbdir;  // STB 目錄
	var $movX, $movY;  // 剪裁的位移
	var $tileX, $tileY; // 幾 乘 幾 merge 用
	var $err = array();
	var $outsizex, $outsizey;
	var $createfromim; // != 0 表示圖形已經有了
	var $im;
	var $ph; // 澎湖
	var $version = 3; // 那個圖
	var $include_gpx = 0; // 是否包含 gpx
	var $datum;
	var $v3img; 
	var $tmpdir = "/dev/shm";
	var $logger = null;

	private $zoom = 16;

	function __construct($basedir, $startx, $starty, $sx, $sy, $ph=0, $datum='TWD67', $tmpdir="") {
		if ($sx > 35 || $sy > 35) {
			$this->err[] = "Sorry We Cannot create too big map";
			return false;
		}
		if ($this->is_taiwan($startx,$startx+$sx,$starty-$sy,$starty,$ph) === false){
			$this->err[] = "不在台澎範圍內";
			return false;
		}
		$this->stbdir = $basedir;
		$this->startx = $startx;
		$this->starty = $starty;
		$this->shiftx = $sx;
		$this->shifty = $sy;
		$this->ph = $ph;
		$this->datum = $datum;
		if (!empty($tmpdir)) {
			$this->tmpdir = $tmpdir;
		}
			return TRUE;
	} 
	// tag = 2 處理縮圖
	function setLogger($logger){
		$this->logger = $logger;
	}
	function createpng($tag=0, $gray=0, $fuzzy=0, $x=1, $y=1, $debug_flag=0, $borders=array()) {
		// global $tmppath;
		// global $tilecachepath;
		if ($this->version == 3) {
			if ($this->datum=="TWD97")
				$v3img = dirname(__FILE__) . "/../imgs/v3image97.png";
			else
				$v3img = dirname(__FILE__) . "/../imgs/v3image2.png";
			$image_ps_args = array("-equalize  -gamma 2.2");
		}else if ($this->version == 2016){
			if ($this->datum=="TWD97")
				$v3img = dirname(__FILE__) . "/../imgs/v2016image97.png";
			else
				$v3img = dirname(__FILE__) . "/../imgs/v2016image.png";
			$image_ps_args = array("-normalize");
		}
		// 給外面 access
		$this->v3img = $v3img;
		// $this->doLog( "logo image: " . $this->v3img);
		if ($this->createfromim == 1 ) { // just load image from im or filename
			$cim=$this->im;
		} else {  
			$pscount = 1; $pstotal = $this->shiftx * $this->shifty;
			$this->doLog( "check tiles...");
			for($j=$this->starty; $j>$this->starty-$this->shifty; $j--){
				for($i=$this->startx; $i<$this->startx+$this->shiftx; $i++){
					$tileurl = $this->gettileurl();
					$options=array("tile_url"=> $tileurl, "image_ps_args"=> $image_ps_args, "tmpdir"=> $this->tmpdir, "datum"=> $this->datum, "logger"=>$this->logger);
					// tmppath => /dev/shm
					list ($status, $fname) =img_from_tiles3($i*1000, $j*1000, 1, 1, $this->zoom , $this->ph, $debug_flag , $options); // "/dev/shm", $tileurl, $image_ps_args);
					// 產生 progress
					$this->doLog( sprintf("nbr:%s/%s ",$pscount,$pstotal));
					$this->doLog( sprintf("nbr:ps%%+%d", 20 * $pscount/$pstotal));
					$pscount++;
					if ($status === false ) {
						error_log("error $fname");
						$this->err[] = $fname;
						return false;
					}
					$fn[] = $fname;
				}
			}
			//print_r($fn);
			// 合併
			$this->doLog( "merge tiles...");
			$outi = $outimage = tempnam($this->tmpdir,"MTILES");
			$montage_bin = "montage";
			$cmd = sprintf("$montage_bin %s -mode Concatenate -tile %dx%d miff:-| composite -gravity northeast %s - miff:-| convert - -resize %dx%d\! png:%s",
				implode(" ",$fn), $this->shiftx ,$this->shifty, $this->v3img, $this->shiftx*315, $this->shifty*315, $outi);
			if ($debug_flag)
				$this->doLog( $cmd );
			exec($cmd);
			$cim = imagecreatefrompng($outi);
			exec("rm ".implode(" ", $fn) . " $outi");
			//	$cim=cropimage($im,$this->movX,$this->movY,
			//		$this->shiftx * 315 + $fuzzy, 
			//		$this->shifty * 315 + $fuzzy );
		}
		if ($tag == 1 ) {
			// echo "tag it ...\n";
			$this->doLog( "tagimage ...");
			$cim=tagimage($cim, $this->startx, $this->starty, $fuzzy);
		} else if ($tag == 2 ) {
			if ($this->outsizex==0 || $this->outsizey==0) { 
				$this->err[]= "Please call setoutsize() first\n"; return false;}
					if ($this->createfromim ==0 && ($this->shiftx < $this->outsizex || $this->shifty < $this->outsizey) ) {
						// echo "resizing image...\n";
						$dst=imageCreate($this->outsizex * 315 + $fuzzy , $this->outsizey * 315 + $fuzzy);
						$bgcolor = imagecolorallocate($dst, 255, 255, 255);
						imagefill($dst, 0, 0, $bgcolor);
						imageCopy($dst,$cim,0,0,0,0,imageSX($cim), imageSY($cim));
						$cim=$dst;
					}
				// refer to X,Y, I
				// debug: echo "addtagbprder2 cim $x $y $d $this->startx, $this->starty, 4x6, $this->shiftx, $this->shifty $fuzzy \n";
				$this->doLog( "add borders ...");
				$cim=addtagborder2($cim, $x, $y, $debug_flag,  $this->startx, $this->starty, 
					array("x"=>$this->outsizex,"y"=>$this->outsizey), 
					$this->shiftx, $this->shifty, $fuzzy);
				if (!($x==1 && $y==1)) {
					// echo "add boder to cut or paste...\n";
					$cim=addborder2($cim, $x, $y, $debug_flag, $borders);
				}
		}
		if ($gray >= 1 ) {
			// $cim = grayscale($cim);
			//grayscale2($cim);
			$this->doLog( "grayscale image ...");
			$cim = im_grayscale($cim);

		}
		return $cim;
	}
	function gettileurl() {
	switch($this->version){
		case 3:
			if ($this->include_gpx==0){
				return 'http://make.happyman.idv.tw/map/tw25k2001/%s/%s/%s.png';
			} else 
				return 'http://make.happyman.idv.tw/map/twmap_happyman_nowp_nocache/%s/%s/%s.png';
		break;
		case 2016:
			if ($this->include_gpx==0){
				return 'http://make.happyman.idv.tw/map/moi_nocache/%s/%s/%s.png';
			} else 
				return 'http://make.happyman.idv.tw/map/moi_happyman_nowp_nocache/%s/%s/%s.png';
		break;
	}
}
} //eo class

function request_curl($url, $method='GET', $params=array(),$hdr=array()) {
	$params_line = http_build_query($params, '', '&');
	$curl = curl_init($url . ($method == 'GET' && $params_line ? '?' . $params_line : ''));
	$headers = array();
	curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
//	curl_setopt($curl, CURLOPT_PROXY, "192.168.168.17:3128");
	curl_setopt($curl, CURLOPT_HEADER, false);
	curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($curl, CURLOPT_DNS_USE_GLOBAL_CACHE, false );
	curl_setopt($curl, CURLOPT_DNS_CACHE_TIMEOUT, 1 );


        if ($method == 'POST') {
                curl_setopt($curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
                curl_setopt($curl, CURLOPT_POST, true);
                curl_setopt($curl, CURLOPT_POSTFIELDS, $params_line);

        } elseif ($method == 'POSTFILE') {
                curl_setopt($curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
                curl_setopt($curl, CURLOPT_POST, true);
                curl_setopt($curl, CURLOPT_POSTFIELDS, $params);
                curl_setopt($curl, CURLINFO_HEADER_OUT, true);
        } elseif ($method == "GETFILE") {
                curl_setopt($curl,CURLOPT_FILE, $params['fd']);
        } elseif ($method == 'POSTJSON') {
                $data_string = json_encode($params);
                curl_setopt($curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
                curl_setopt($curl, CURLOPT_POST, true);
                curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
				// for Keepon API
				curl_setopt($curl, CURLOPT_COOKIE, 'AspxAutoDetectCookieSupport=1');
				$headers =  array( 'Content-Type: application/json','Content-Length: ' . strlen($data_string),'Accept: application/json');
        } elseif ($method == 'HEAD') {
                curl_setopt($curl, CURLOPT_HEADER, true);
                curl_setopt($curl, CURLOPT_NOBODY, true);
				curl_setopt($curl, CURLOPT_FOLLOWLOCATION, false);
		} else {
                curl_setopt($curl, CURLOPT_HTTPGET, true);
        }
		curl_setopt($curl, CURLOPT_HTTPHEADER, array_merge($hdr , $headers) );
	
        $response = curl_exec($curl);
	if($method == 'HEAD') {
		$headers = array();
		foreach(explode("\n", $response) as $header) {
			$pos = strpos($header,':');
			$name = strtolower(trim(substr($header, 0, $pos)));
			$headers[$name] = trim(substr($header, $pos+1));
		}
		return $headers;
	}
	$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
	if ($httpCode != 200 && $httpCode != 302) {
		throw new ErrorException("HTTP return $httpCode", $httpCode );
	}
	// error_log("curl $method $url" . print_r(array_merge($hdr,$headers),true));

	if (curl_errno($curl)) {
		throw new ErrorException(curl_error($curl), curl_errno($curl));
	}

	return $response;
}

function MyErrorLog($ident, $data) {
	error_log("== $ident ==\n");
	if (!is_string($data)) {
		error_log(print_r($data, true));

	} else
		error_log($data);
	error_log("== $ident ==\n");
}

// websocket client: https://github.com/vi/websocat
// cmd_make will persist port 
function notify_web($channel,$msg_array,$logurl_prefix="ws://twmap:9002/twmap_",$reuse_port=0,$debug=0){
	if ($reuse_port != 0)
		$cmd = sprintf("/usr/bin/echo -n %s |nc 127.0.0.1 %d",escapeshellarg($msg_array[0]),$reuse_port);
	else
		$cmd = sprintf("/usr/bin/echo '%s' |base64 -d | /usr/bin/websocat --no-line -1 -t -  %s%s",base64_encode($msg_array[0]),$logurl_prefix,$channel);
	if ($debug == 1)
		echo "$cmd\n";
	exec($cmd);
}
