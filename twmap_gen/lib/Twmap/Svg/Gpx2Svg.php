<?php
Namespace Happyman\Twmap\Svg;

use Happyman\Twmap\Proj;
/**
 * depends on GeoPHP class
 */
class Gpx2Svg {

	var $width; // pixel
	var $height;
	var $bgimg; // background image
	var $bound = array();
	var $bound_twdtm2 = array();
	var $ratio=array(); // 經緯度差 * $ratio = px
	var $track;
	var $fontsize;
	var $default_fontsize = 16;
	var $waypoint;
	var $ele_bound;
	var $show_label_wpt = 0;
	var $show_label_trk= 0;
	var $input_bound67;
	var $logotext;
	var $taiwan;
	var $do_fit_a4 = 1;
	var $colorize = 1;
	var $initparams; // 初始參數
	var $auto_shrink = 0; // 1 表示自動更縮小到可以產生的範圍, 在 keepon 有大範圍地圖適用
	var $limit = array("km_x" => 24001, "km_y" => 24001); // 24x24 格
	var $datum = 'TWD67';
	var $pixel_per_km;
	var $_err;

	function __construct($params) {
		$this->width = $params['width'];
		$this->bgimg = (isset($params['bgimg'])) ? $params['bgimg'] : "";
		$this->gpx = $params['gpx'];
		$this->show_label_trk =(isset($params['show_label_trk'])) ? $params['show_label_trk'] :0;
		$this->show_label_wpt =(isset($params['show_label_wpt'])) ? $params['show_label_wpt'] : 0;
		$this->input_bound67 = (isset($params['input_bound67'])) ? $params['input_bound67'] : array();
		$this->do_fit_a4 = (isset($params['fit_a4']) && $params['fit_a4'] == 1 ) ? 1 : 0;
		$this->logotext = (isset($params['logotext']))? $params['logotext'] : "";
		$this->initparams = array("width"=>$this->width, "bgimg"=> $this->bgimg, "logotext" => $this->logotext , "gpx"=>$this->gpx , "input_bound67"=> $this->input_bound67, "show_label_trk"=>$this->show_label_trk, "show_label_wpt" =>  $this->show_label_wpt , "do_fit_a4" => $this->do_fit_a4 );
		$this->colorize = (isset($params['colorize']))? $params['colorize'] : "";
		$this->auto_shrink = (isset($params['auto_shrink']))? $params['auto_shrink'] : 0;
		$this->datum = (isset($params['datum']))? $params['datum'] : 'TWD67';
		$this->pixel_per_km = (isset($params['pixel_per_km']))? $params['pixel_per_km'] : 315;
	

	}
	static function check(){
		$req=[ 'convert' => [ 'package'=>'imagemagick', 'test'=>''] , 
		'inkscape' => [ 'package'=>'inkscape','test'=>'--help', 'optional'=>1 ]];
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
	function coordtotm2($a, $ph) {
		if ($ph == 1) {
			if ($this->datum == 'TWD97')
				return Proj::proj_geto97_ph($a);
			else
				return Proj::proj_geto672_ph($a);
		}
		if ($this->datum == 'TWD97')
			return Proj::proj_geto97($a);
		else
			return Proj::proj_geto672($a);
	}
	function tm2tocoord($a, $ph) {
		if ($ph == 1) {
			if ($this->datum == 'TWD97')
				return Proj::ph_proj_97toge2($a);
		else
				return Proj::proj_67toge2_ph($a);
		}
		if ($this->datum == 'TWD97')
			return Proj::proj_97toge2($a);
		else
			return Proj::proj_67toge2($a);
	}
	
	function dump() {
		print_r($this);
	}
	// 利用 geoPHP 取得 bbox, 不用自己 parse 半天
	// 取得本圖的 bounds
	function get_bbox($gpx_str){
		try {
		$geom = \geoPHP::load($gpx_str, 'gpx');
		$bbox = $geom->getBBox();
		$tl = $this->is_taiwan($bbox['minx'], $bbox['maxy']);
		$br = $this->is_taiwan($bbox['maxx'], $bbox['miny']);
		if ($tl != $br) 
			$this->taiwan = 0;
		else
			$this->taiwan = $tl; // 0 or 1 or 2
		//echo "lon $minlon $maxlon, lat $maxlat $minlat\n"; exit;
		return array($bbox['minx'], $bbox['maxy'], $bbox['maxx'], $bbox['miny']);
		}   catch (Exception $e) {
			// parse gpx error
			$this->taiwan = 0;
			$this->over = 1;
			return false;
		}
	}
	// 偵測到底 gpx 範圍多大
    // used in track.inc.php
    function detect_bbox(){
		list($x,$y,$x1,$y1) = $this->get_bbox(file_get_contents($this->gpx));
		//if ($this->taiwan == 0)
		//      return array(false,"not in Taiwan or Pong Hu");
		if ($this->taiwan == 1 ) {
		list($tx, $ty) = Proj::proj_geto672(array($x,$y));
		list($tx1, $ty1) = Proj::proj_geto672(array($x1,$y1));
		} else {
		list($tx, $ty) = Proj::proj_geto672_ph(array($x,$y));
		list($tx1, $ty1) = Proj::proj_geto672_ph(array($x1,$y1));
		}
		// get tl and br
		$tl = array( floor($tx / 1000)*1000, ceil($ty / 1000)*1000);
		$br = array( ceil($tx1 / 1000)*1000, floor($ty1 / 1000)*1000);
		//
		$this->bound_twdtm2 = array("tl" => $tl, "br" => $br , "ph"=> ($this->taiwan==2)?1:0);
		if ($br[0] - $tl[0] >= $this->limit['km_x'] || $tl[1] - $br[1] >= $this->limit['km_y'] ) {
				$over = 1;
		} else {
				$over = 0;
		}
		return array(true, array("is_taiwan"=> $this->taiwan,
		"x"=> ($br[0] - $tl[0])/1000 , "y"=> ($tl[1] - $br[1])/1000, "over" => $over, "bbox" => "$y $x $y1 $x1"));
    }
	function fit_a4($tl, $br) {

		$x = ($br[0] - $tl[0])/1000;
		$y = ($tl[1] - $br[1])/1000;

		$a4 = ceil($x/5) * ceil($y/7);
		$a4r = ceil($x/7) * ceil($y/5);
		if($a4-$a4r > 0 ) {
			//return array(6,4);
			//a4r
			$tile = array(7,5);
		} else {
			$tile = array(5,7);
		}
		//error_log(print_r(array($tl,$br,$x,$y), true));
		//error_log(print_r($tile, true));
		//置中
		$remain_x = $tile[0] * ceil($x / $tile[0]) - $x;
		if ($remain_x != 0 ) {
			if ($remain_x % 2  == 0 ) {
				$tl[0] = $tl[0] -  ($remain_x/2)*1000;
				$br[0] = $br[0] +  ($remain_x/2)*1000;
			} else {
				$tl[0] = $tl[0] - (($remain_x-1)/2+1)*1000;
				$br[0] = $br[0] + ($remain_x-1)/2*1000;
			}
		}
		$remain_y = $tile[1] * ceil($y / $tile[1]) - $y;
		if ($remain_y != 0 ) {
			if ($remain_y % 2  == 0 ) {
				$tl[1] = $tl[1] +  ($remain_y/2)*1000;
				$br[1] = $br[1] -  ($remain_y/2)*1000;
			} else {
				$tl[1] = $tl[1] + (($remain_y-1)/2+1)*1000;
				$br[1] = $br[1] - ($remain_y-1)/2*1000;
			}
		}
		//error_log(print_r(array($tl,$br,$remain_x,$remain_y), true));
		$xx = ($br[0] - $tl[0])/1000;
		$yy = ($tl[1] - $br[1])/1000;
		//error_log(print_r(array($x,$y), true));
		error_log("fit_a4:$x,$y->$xx,$yy");
		return array($tl, $br);

	}
	
	function process() {
		if (!isset($this->gpx) || !file_exists($this->gpx)) {
			$this->_err[] = "no gpx file input";
			return false;
		}
		// LIBXML_NOCDATA parse CDATA correctly
		$xml = simplexml_load_file($this->gpx, null, LIBXML_NOCDATA);
		$arr = obj2array($xml);
		// 1. 取得 bounds, 轉換成 twd67 最近的 bounds

		//list($x,$y,$x1,$y1) = $this->get_bound($arr);
		list($x,$y,$x1,$y1) = $this->get_bbox(file_get_contents($this->gpx));

		if ($this->taiwan == 0 ) {
			$this->_err[] = "超出台澎範圍或檔案剖析有誤,請回報";
			return false;
		} 
		if ($this->taiwan == 1 ) {
			list($tx, $ty) = $this->coordtotm2(array($x,$y),0);
			list($tx1, $ty1) = $this->coordtotm2(array($x1,$y1),0);
		} else {
			list($tx, $ty) = $this->coordtotm2(array($x,$y),1);
			list($tx1, $ty1) = $this->coordtotm2(array($x1,$y1),1);
		}
		// cmd_make 帶入已算參數, 免重算
		if (isset($this->input_bound67['x'])) {
			// 免算
			$tl = array($this->input_bound67['x'], $this->input_bound67['y']);
			$br = array($this->input_bound67['x1'], $this->input_bound67['y1']);
		} else {
			// 如果需要多 index 的話, 才 expend
			// 並且不是範圍已經輸入了
			if ($this->show_label_wpt == 1)
				$expend = 1000;
			else
				$expend = 0;

			/* 四邊都 expend
			   $tl = array( floor($tx / 1000)*1000 - $expend, ceil($ty / 1000)*1000 + $expend);
			   $br = array( ceil($tx1 / 1000)*1000 + $expend, floor($ty1 / 1000)*1000 - $expend);
			 */
			// 只在右手邊 expend
			$tl = array( floor($tx / 1000)*1000, ceil($ty / 1000)*1000);
			$br = array( ceil($tx1 / 1000)*1000 + $expend, floor($ty1 / 1000)*1000);

			// 如果自動縮放, 過大範圍自動調整 br 的座標
			if ($this->auto_shrink == 1 ) {
				if ($br[0] - $tl[0] >= $this->limit['km_x']) {
					$br[0] = $tl[0] + 20000;
				}
				if ($tl[1] - $br[1] >= $this->limit['km_y']) {
					$br[1] = $tl[1] - 20000;
				}
			}
			if ($this->do_fit_a4 == 1 ) {
				list($tl,$br) = $this->fit_a4($tl,$br);
			}
		}
		// 檢查一下是不是太大範圍
		if ($br[0] - $tl[0] >= $this->limit['km_x'] || $tl[1] - $br[1] >= $this->limit['km_y'] ) {
			$this->_err[] = sprintf("超出範圍: x=%d y=%d (請刪除不需要之航跡)",$br[0] - $tl[0] , $tl[1] - $br[1]);
			return false;
		}
		// 計算字型比例 依照若是標準地圖產生器出圖, 18px (1km 315px)
		$this->fontsize = intval(18 * ($this->width / (($br[0] - $tl[0])/1000) / $this->pixel_per_km));
		// 若是產生縮圖
		if ($this->fontsize < $this->default_fontsize)
			$this->fontsize = $this->default_fontsize;
		// 存入 bounds
		$this->bound_twdtm2 = array("tl" => $tl, "br" => $br , "ph"=> ($this->taiwan==2)?1:0);

		if ($this->taiwan == 1) {
			$this->bound = array("tl" => $this->tm2tocoord($tl,0), "br"=>  $this->tm2tocoord($br,0));
		} else {
			$this->bound = array("tl" => $this->tm2tocoord($tl,1), "br"=>  $this->tm2tocoord($br,1));
		}

		// 計算比例
		$this->ratio['x'] = $this->width / ($this->bound['br'][0] - $this->bound['tl'][0]);
		// 經緯度比例這樣會出錯
		//$this->height = round(($this->bound['tl'][1] - $this->bound['br'][1])*$this->ratio);
		$this->height = ($tl[1]-$br[1])/1000 *  $this->pixel_per_km;
		$this->ratio['y'] = $this->height / ($this->bound['tl'][1] - $this->bound['br'][1]);


		// 2. 取得所有 trk point 的高度 作為 colorize trk 的依據
		// $this->dump();
		// 像 oruxmap 會產生只有一層的 trk, 而不會有 trk 的 array => 為了不想重寫 parser, 放到 array 去
		if (isset($arr['trk']['trkseg']))
			$arr['trk'][0] = $arr['trk'];
		// 共有多少 tracks?
		$total_tracks = isset($arr['trk'])?count($arr['trk']):0;
		$min=8000;
		$max=0;
		for($i=0;$i<$total_tracks;$i++) {
			if (!isset($arr['trk'][$i]['name'])) {
				// skip track without "name"
				// echo "no name:" . var_dump($arr['trk']);
				continue;
			}
			$this->track[$i] = array("name"=> $arr['trk'][$i]['name']);

			$j=0;
			foreach($arr['trk'][$i]['trkseg']['trkseg'] as $trk_point) {
				// skip route/track without '@attributes'
				// echo "get point:";
				// print_r($trk_point);
				if (!isset($trk_point['@attributes']['lon'])){
					if (!isset($trk_point['trkpt']['lon']))
						continue;
					else {
						$trk_point['@attributes']['lon'] = $trk_point['trkpt']['lon'];
						$trk_point['@attributes']['lat'] = $trk_point['trkpt']['lat'];
					}

				}

				if($trk_point['@attributes']['lon'] > $this->bound['br'][0] ||
					$trk_point['@attributes']['lon']  < $this->bound['tl'][0] ||
					$trk_point['@attributes']['lat'] > $this->bound['tl'][1] ||
					$trk_point['@attributes']['lat']  < $this->bound['br'][1] ){
					// echo "oob!!!!!\n";	
					continue;
				}
				if (isset($trk_point['ele'])) {
					// 如果高度小於 0 
					if ($trk_point['ele'] < 0 ) {
						$trk_point['ele'] =0;
						$arr['trk'][$i]['trkseg']['trkseg']['ele'] = 0;
					}
					if ($trk_point['ele'] < $min) 
						$min = $trk_point['ele'];
					else if ($trk_point['ele'] > $max) 
						$max = $trk_point['ele'];
				} else {
					$trk_point['ele'] = -100;
				}
				$this->track[$i]['point'][$j] = $trk_point;
				$this->track[$i]['point'][$j]['rel'] = $this->rel_px($trk_point['@attributes']['lon'],$trk_point['@attributes']['lat']);
				$j++;
			}
			// 處理 track 的高度
		}
		$this->ele_bound = array(($min<0)?0:$min, ($max<0)?0:$max);
		//$this->dump();
		//$this->waypoint = $arr['wpt'];
		$j = 0;
		if (isset($arr['wpt'])){
			foreach($arr['wpt'] as $waypoint) {
				if($waypoint['@attributes']['lon'] > $this->bound['br'][0] ||
					$waypoint['@attributes']['lon']  < $this->bound['tl'][0] ||
					$waypoint['@attributes']['lat'] > $this->bound['tl'][1] ||
					$waypoint['@attributes']['lat']  < $this->bound['br'][1] )
					continue;
				print_r($waypoint);
				$this->waypoint[$j] = $waypoint;
				$this->waypoint[$j]['rel'] = $this->rel_px($waypoint['@attributes']['lon'],$waypoint['@attributes']['lat']);
				if ($this->taiwan == 1)
					$this->waypoint[$j]['tw67'] = $this->coordtotm2(array($waypoint['@attributes']['lon'],$waypoint['@attributes']['lat']),0);
				else
					$this->waypoint[$j]['tw67'] = $this->coordtotm2(array($waypoint['@attributes']['lon'],$waypoint['@attributes']['lat']),1);
				$j++;
			}
		}
		unset($arr);
		if (empty($this->track) && empty($this->waypoint))
			return false;
		//$this->dump();
		return true;
	}
	function output($outsvg) {
		if (!empty($this->_err)) {
			print_r($this->_err);
			exit;
		}
		ob_start();
		// 1. header
		$this->header();
		// 2. 背景
		$this->out_background();
		// 3. tracks 3.1 points 3.2 label
		$this->out_tracks();
		// 4. wpts 3.1 points 3.2 label
		$this->out_waypoints();
		// 5. comment
		$this->out_comment();
		// footer
		$this->footer();
		$ret = file_put_contents($outsvg,ob_get_contents());
		ob_end_clean();
		return $ret;
	
	}
	function out_comment() {
		printf("<!-- debug: %s-->\n",print_r($this->initparams,true));
		printf("<!-- width: %d height: %d -->\n",$this->width,$this->height);
		printf("<!-- bgimg: %s -->\n",$this->bgimg);
		printf("<!-- ratio: %f %f -->\n",$this->ratio['x'], $this->ratio['y']);
		printf("<!-- bound: \n".
			'$tl_lat = %.f; $tl_lon = %.f;' . "\n".
			'$br_lat = %.f; $br_lon = %.f;' . "\n".
			'-r %d:%d:%d:%d ph=%d'."\n".
			"-->\n",
			$this->bound['tl'][1], $this->bound['tl'][0],
			$this->bound['br'][1], $this->bound['br'][0],
			$this->bound_twdtm2['tl'][0], $this->bound_twdtm2['tl'][1],
			($this->bound_twdtm2['br'][0]-$this->bound_twdtm2['tl'][0])/1000, 
			($this->bound_twdtm2['tl'][1]-$this->bound_twdtm2['br'][1])/1000,
			($this->taiwan==2)?1:0);
		printf("<!-- bound_ele: %f %f -->\n",$this->ele_bound[0], $this->ele_bound[1]);
	}
	function color($ele) {
	$color_spectrum = array("ff00fe"
	,"ff00fd","ff00fc","ff00fb","ff00fa","ff00f9","ff00f8"
	,"ff00f7","ff00f6","ff00f5","ff00f4","ff00f3","ff00f2"
	,"ff00f1","ff00f0","ff00ef","ff00ee","ff00ed","ff00ec"
	,"ff00eb","ff00ea","ff00e9","ff00e8","ff00e7","ff00e6"
	,"ff00e5","ff00e4","ff00e3","ff00e2","ff00e1","ff00e0"
	,"ff00df","ff00de","ff00dd","ff00dc","ff00db","ff00da"
	,"ff00d9","ff00d8","ff00d7","ff00d6","ff00d5","ff00d4"
	,"ff00d3","ff00d2","ff00d1","ff00d0","ff00cf","ff00ce"
	,"ff00cd","ff00cc","ff00cb","ff00ca","ff00c9","ff00c8"
	,"ff00c7","ff00c6","ff00c5","ff00c4","ff00c3","ff00c2"
	,"ff00c1","ff00c0","ff00bf","ff00be","ff00bd","ff00bc"
	,"ff00bb","ff00ba","ff00b9","ff00b8","ff00b7","ff00b6"
	,"ff00b5","ff00b4","ff00b3","ff00b2","ff00b1","ff00b0"
	,"ff00af","ff00ae","ff00ad","ff00ac","ff00ab","ff00aa"
	,"ff00a9","ff00a8","ff00a7","ff00a6","ff00a5","ff00a4"
	,"ff00a3","ff00a2","ff00a1","ff00a0","ff009f","ff009e"
	,"ff009d","ff009c","ff009b","ff009a","ff0099","ff0098"
	,"ff0097","ff0096","ff0095","ff0094","ff0093","ff0092"
	,"ff0091","ff0090","ff008f","ff008e","ff008d","ff008c"
	,"ff008b","ff008a","ff0089","ff0088","ff0087","ff0086"
	,"ff0085","ff0084","ff0083","ff0082","ff0081","ff0080"
	,"ff007f","ff007e","ff007d","ff007c","ff007b","ff007a"
	,"ff0079","ff0078","ff0077","ff0076","ff0075","ff0074"
	,"ff0073","ff0072","ff0071","ff0070","ff006f","ff006e"
	,"ff006d","ff006c","ff006b","ff006a","ff0069","ff0068"
	,"ff0067","ff0066","ff0065","ff0064","ff0063","ff0062"
	,"ff0061","ff0060","ff005f","ff005e","ff005d","ff005c"
	,"ff005b","ff005a","ff0059","ff0058","ff0057","ff0056"
	,"ff0055","ff0054","ff0053","ff0052","ff0051","ff0050"
	,"ff004f","ff004e","ff004d","ff004c","ff004b","ff004a"
	,"ff0049","ff0048","ff0047","ff0046","ff0045","ff0044"
	,"ff0043","ff0042","ff0041","ff0040","ff003f","ff003e"
	,"ff003d","ff003c","ff003b","ff003a","ff0039","ff0038"
	,"ff0037","ff0036","ff0035","ff0034","ff0033","ff0032"
	,"ff0031","ff0030","ff002f","ff002e","ff002d","ff002c"
	,"ff002b","ff002a","ff0029","ff0028","ff0027","ff0026"
	,"ff0025","ff0024","ff0023","ff0022","ff0021","ff0020"
	,"ff001f","ff001e","ff001d","ff001c","ff001b","ff001a"
	,"ff0019","ff0018","ff0017","ff0016","ff0015","ff0014"
	,"ff0013","ff0012","ff0011","ff0010","ff000f","ff000e"
	,"ff000d","ff000c","ff000b","ff000a","ff0009","ff0008"
	,"ff0007","ff0006","ff0005","ff0004","ff0003","ff0002"
	,"ff0001","ff0000","ff0000","ff0100","ff0200","ff0300"
	,"ff0400","ff0500","ff0600","ff0700","ff0800","ff0900"
	,"ff0a00","ff0b00","ff0c00","ff0d00","ff0e00","ff0f00"
	,"ff1000","ff1100","ff1200","ff1300","ff1400","ff1500"
	,"ff1600","ff1700","ff1800","ff1900","ff1a00","ff1b00"
	,"ff1c00","ff1d00","ff1e00","ff1f00","ff2000","ff2100"
	,"ff2200","ff2300","ff2400","ff2500","ff2600","ff2700"
	,"ff2800","ff2900","ff2a00","ff2b00","ff2c00","ff2d00"
	,"ff2e00","ff2f00","ff3000","ff3100","ff3200","ff3300"
	,"ff3400","ff3500","ff3600","ff3700","ff3800","ff3900"
	,"ff3a00","ff3b00","ff3c00","ff3d00","ff3e00","ff3f00"
	,"ff4000","ff4100","ff4200","ff4300","ff4400","ff4500"
	,"ff4600","ff4700","ff4800","ff4900","ff4a00","ff4b00"
	,"ff4c00","ff4d00","ff4e00","ff4f00","ff5000","ff5100"
	,"ff5200","ff5300","ff5400","ff5500","ff5600","ff5700"
	,"ff5800","ff5900","ff5a00","ff5b00","ff5c00","ff5d00"
	,"ff5e00","ff5f00","ff6000","ff6100","ff6200","ff6300"
	,"ff6400","ff6500","ff6600","ff6700","ff6800","ff6900"
	,"ff6a00","ff6b00","ff6c00","ff6d00","ff6e00","ff6f00"
	,"ff7000","ff7100","ff7200","ff7300","ff7400","ff7500"
	,"ff7600","ff7700","ff7800","ff7900","ff7a00","ff7b00"
	,"ff7c00","ff7d00","ff7e00","ff7f00","ff8000","ff8100"
	,"ff8200","ff8300","ff8400","ff8500","ff8600","ff8700"
	,"ff8800","ff8900","ff8a00","ff8b00","ff8c00","ff8d00"
	,"ff8e00","ff8f00","ff9000","ff9100","ff9200","ff9300"
	,"ff9400","ff9500","ff9600","ff9700","ff9800","ff9900"
	,"ff9a00","ff9b00","ff9c00","ff9d00","ff9e00","ff9f00"
	,"ffa000","ffa100","ffa200","ffa300","ffa400","ffa500"
	,"ffa600","ffa700","ffa800","ffa900","ffaa00","ffab00"
	,"ffac00","ffad00","ffae00","ffaf00","ffb000","ffb100"
	,"ffb200","ffb300","ffb400","ffb500","ffb600","ffb700"
	,"ffb800","ffb900","ffba00","ffbb00","ffbc00","ffbd00"
	,"ffbe00","ffbf00","ffc000","ffc100","ffc200","ffc300"
	,"ffc400","ffc500","ffc600","ffc700","ffc800","ffc900"
	,"ffca00","ffcb00","ffcc00","ffcd00","ffce00","ffcf00"
	,"ffd000","ffd100","ffd200","ffd300","ffd400","ffd500"
	,"ffd600","ffd700","ffd800","ffd900","ffda00","ffdb00"
	,"ffdc00","ffdd00","ffde00","ffdf00","ffe000","ffe100"
	,"ffe200","ffe300","ffe400","ffe500","ffe600","ffe700"
	,"ffe800","ffe900","ffea00","ffeb00","ffec00","ffed00"
	,"ffee00","ffef00","fff000","fff100","fff200","fff300"
	,"fff400","fff500","fff600","fff700","fff800","fff900"
	,"fffa00","fffb00","fffc00","fffd00","fffe00","ffff00"
	,"feff00","fdff00","fcff00","fbff00","faff00","f9ff00"
	,"f8ff00","f7ff00","f6ff00","f5ff00","f4ff00","f3ff00"
	,"f2ff00","f1ff00","f0ff00","efff00","eeff00","edff00"
	,"ecff00","ebff00","eaff00","e9ff00","e8ff00","e7ff00"
	,"e6ff00","e5ff00","e4ff00","e3ff00","e2ff00","e1ff00"
	,"e0ff00","dfff00","deff00","ddff00","dcff00","dbff00"
	,"daff00","d9ff00","d8ff00","d7ff00","d6ff00","d5ff00"
	,"d4ff00","d3ff00","d2ff00","d1ff00","d0ff00","cfff00"
	,"ceff00","cdff00","ccff00","cbff00","caff00","c9ff00"
	,"c8ff00","c7ff00","c6ff00","c5ff00","c4ff00","c3ff00"
	,"c2ff00","c1ff00","c0ff00","bfff00","beff00","bdff00"
	,"bcff00","bbff00","baff00","b9ff00","b8ff00","b7ff00"
	,"b6ff00","b5ff00","b4ff00","b3ff00","b2ff00","b1ff00"
	,"b0ff00","afff00","aeff00","adff00","acff00","abff00"
	,"aaff00","a9ff00","a8ff00","a7ff00","a6ff00","a5ff00"
	,"a4ff00","a3ff00","a2ff00","a1ff00","a0ff00","9fff00"
	,"9eff00","9dff00","9cff00","9bff00","9aff00","99ff00"
	,"98ff00","97ff00","96ff00","95ff00","94ff00","93ff00"
	,"92ff00","91ff00","90ff00","8fff00","8eff00","8dff00"
	,"8cff00","8bff00","8aff00","89ff00","88ff00","87ff00"
	,"86ff00","85ff00","84ff00","83ff00","82ff00","81ff00"
	,"80ff00","7fff00","7eff00","7dff00","7cff00","7bff00"
	,"7aff00","79ff00","78ff00","77ff00","76ff00","75ff00"
	,"74ff00","73ff00","72ff00","71ff00","70ff00","6fff00"
	,"6eff00","6dff00","6cff00","6bff00","6aff00","69ff00"
	,"68ff00","67ff00","66ff00","65ff00","64ff00","63ff00"
	,"62ff00","61ff00","60ff00","5fff00","5eff00","5dff00"
	,"5cff00","5bff00","5aff00","59ff00","58ff00","57ff00"
	,"56ff00","55ff00","54ff00","53ff00","52ff00","51ff00"
	,"50ff00","4fff00","4eff00","4dff00","4cff00","4bff00"
	,"4aff00","49ff00","48ff00","47ff00","46ff00","45ff00"
	,"44ff00","43ff00","42ff00","41ff00","40ff00","3fff00"
	,"3eff00","3dff00","3cff00","3bff00","3aff00","39ff00"
	,"38ff00","37ff00","36ff00","35ff00","34ff00","33ff00"
	,"32ff00","31ff00","30ff00","2fff00","2eff00","2dff00"
	,"2cff00","2bff00","2aff00","29ff00","28ff00","27ff00"
	,"26ff00","25ff00","24ff00","23ff00","22ff00","21ff00"
	,"20ff00","1fff00","1eff00","1dff00","1cff00","1bff00"
	,"1aff00","19ff00","18ff00","17ff00","16ff00","15ff00"
	,"14ff00","13ff00","12ff00","11ff00","10ff00","0fff00"
	,"0eff00","0dff00","0cff00","0bff00","0aff00","09ff00"
	,"08ff00","07ff00","06ff00","05ff00","04ff00","03ff00"
	,"02ff00","01ff00","00ff00","00ff01","00ff02","00ff03"
	,"00ff04","00ff05","00ff06","00ff07","00ff08","00ff09"
	,"00ff0a","00ff0b","00ff0c","00ff0d","00ff0e","00ff0f"
	,"00ff10","00ff11","00ff12","00ff13","00ff14","00ff15"
	,"00ff16","00ff17","00ff18","00ff19","00ff1a","00ff1b"
	,"00ff1c","00ff1d","00ff1e","00ff1f","00ff20","00ff21"
	,"00ff22","00ff23","00ff24","00ff25","00ff26","00ff27"
	,"00ff28","00ff29","00ff2a","00ff2b","00ff2c","00ff2d"
	,"00ff2e","00ff2f","00ff30","00ff31","00ff32","00ff33"
	,"00ff34","00ff35","00ff36","00ff37","00ff38","00ff39"
	,"00ff3a","00ff3b","00ff3c","00ff3d","00ff3e","00ff3f"
	,"00ff40","00ff41","00ff42","00ff43","00ff44","00ff45"
	,"00ff46","00ff47","00ff48","00ff49","00ff4a","00ff4b"
	,"00ff4c","00ff4d","00ff4e","00ff4f","00ff50","00ff51"
	,"00ff52","00ff53","00ff54","00ff55","00ff56","00ff57"
	,"00ff58","00ff59","00ff5a","00ff5b","00ff5c","00ff5d"
	,"00ff5e","00ff5f","00ff60","00ff61","00ff62","00ff63"
	,"00ff64","00ff65","00ff66","00ff67","00ff68","00ff69"
	,"00ff6a","00ff6b","00ff6c","00ff6d","00ff6e","00ff6f"
	,"00ff70","00ff71","00ff72","00ff73","00ff74","00ff75"
	,"00ff76","00ff77","00ff78","00ff79","00ff7a","00ff7b"
	,"00ff7c","00ff7d","00ff7e","00ff7f","00ff80","00ff81"
	,"00ff82","00ff83","00ff84","00ff85","00ff86","00ff87"
	,"00ff88","00ff89","00ff8a","00ff8b","00ff8c","00ff8d"
	,"00ff8e","00ff8f","00ff90","00ff91","00ff92","00ff93"
	,"00ff94","00ff95","00ff96","00ff97","00ff98","00ff99"
	,"00ff9a","00ff9b","00ff9c","00ff9d","00ff9e","00ff9f"
	,"00ffa0","00ffa1","00ffa2","00ffa3","00ffa4","00ffa5"
	,"00ffa6","00ffa7","00ffa8","00ffa9","00ffaa","00ffab"
	,"00ffac","00ffad","00ffae","00ffaf","00ffb0","00ffb1"
	,"00ffb2","00ffb3","00ffb4","00ffb5","00ffb6","00ffb7"
	,"00ffb8","00ffb9","00ffba","00ffbb","00ffbc","00ffbd"
	,"00ffbe","00ffbf","00ffc0","00ffc1","00ffc2","00ffc3"
	,"00ffc4","00ffc5","00ffc6","00ffc7","00ffc8","00ffc9"
	,"00ffca","00ffcb","00ffcc","00ffcd","00ffce","00ffcf"
	,"00ffd0","00ffd1","00ffd2","00ffd3","00ffd4","00ffd5"
	,"00ffd6","00ffd7","00ffd8","00ffd9","00ffda","00ffdb"
	,"00ffdc","00ffdd","00ffde","00ffdf","00ffe0","00ffe1"
	,"00ffe2","00ffe3","00ffe4","00ffe5","00ffe6","00ffe7"
	,"00ffe8","00ffe9","00ffea","00ffeb","00ffec","00ffed"
	,"00ffee","00ffef","00fff0","00fff1","00fff2","00fff3"
	,"00fff4","00fff5","00fff6","00fff7","00fff8","00fff9"
	,"00fffa","00fffb","00fffc","00fffd","00fffe","00ffff"
	,"00feff","00fdff","00fcff","00fbff","00faff","00f9ff"
	,"00f8ff","00f7ff","00f6ff","00f5ff","00f4ff","00f3ff"
	,"00f2ff","00f1ff","00f0ff","00efff","00eeff","00edff"
	,"00ecff","00ebff","00eaff","00e9ff","00e8ff","00e7ff"
	,"00e6ff","00e5ff","00e4ff","00e3ff","00e2ff","00e1ff"
	,"00e0ff","00dfff","00deff","00ddff","00dcff","00dbff"
	,"00daff","00d9ff","00d8ff","00d7ff","00d6ff","00d5ff"
	,"00d4ff","00d3ff","00d2ff","00d1ff","00d0ff","00cfff"
	,"00ceff","00cdff","00ccff","00cbff","00caff","00c9ff"
	,"00c8ff","00c7ff","00c6ff","00c5ff","00c4ff","00c3ff"
	,"00c2ff","00c1ff","00c0ff","00bfff","00beff","00bdff"
	,"00bcff","00bbff","00baff","00b9ff","00b8ff","00b7ff"
	,"00b6ff","00b5ff","00b4ff","00b3ff","00b2ff","00b1ff"
	,"00b0ff","00afff","00aeff","00adff","00acff","00abff"
	,"00aaff","00a9ff","00a8ff","00a7ff","00a6ff","00a5ff"
	,"00a4ff","00a3ff","00a2ff","00a1ff","00a0ff","009fff"
	,"009eff","009dff","009cff","009bff","009aff","0099ff"
	,"0098ff","0097ff","0096ff","0095ff","0094ff","0093ff"
	,"0092ff","0091ff","0090ff","008fff","008eff","008dff"
	,"008cff","008bff","008aff","0089ff","0088ff","0087ff"
	,"0086ff","0085ff","0084ff","0083ff","0082ff","0081ff"
	,"0080ff","007fff","007eff","007dff","007cff","007bff"
	,"007aff","0079ff","0078ff","0077ff","0076ff","0075ff"
	,"0074ff","0073ff","0072ff","0071ff","0070ff","006fff"
	,"006eff","006dff","006cff","006bff","006aff","0069ff"
	,"0068ff","0067ff","0066ff","0065ff","0064ff","0063ff"
	,"0062ff","0061ff","0060ff","005fff","005eff","005dff"
	,"005cff","005bff","005aff","0059ff","0058ff","0057ff"
	,"0056ff","0055ff","0054ff","0053ff","0052ff","0051ff"
	,"0050ff","004fff","004eff","004dff","004cff","004bff"
	,"004aff","0049ff","0048ff","0047ff","0046ff","0045ff"
	,"0044ff","0043ff","0042ff","0041ff","0040ff","003fff"
	,"003eff","003dff","003cff","003bff","003aff","0039ff"
	,"0038ff","0037ff","0036ff","0035ff","0034ff","0033ff"
	,"0032ff","0031ff","0030ff","002fff","002eff","002dff"
	,"002cff","002bff","002aff","0029ff","0028ff","0027ff"
	,"0026ff","0025ff","0024ff","0023ff","0022ff","0021ff"
	,"0020ff","001fff","001eff","001dff","001cff","001bff"
	,"001aff","0019ff","0018ff","0017ff","0016ff","0015ff"
	,"0014ff","0013ff","0012ff","0011ff","0010ff","000fff"
	,"000eff","000dff","000cff","000bff","000aff","0009ff"
	,"0008ff","0007ff","0006ff","0005ff","0004ff","0003ff"
	,"0002ff","0001ff","0000ff","0000ff","0100ff","0200ff"
	,"0300ff","0400ff","0500ff","0600ff","0700ff","0800ff"
	,"0900ff","0a00ff","0b00ff","0c00ff","0d00ff","0e00ff"
	,"0f00ff","1000ff","1100ff","1200ff","1300ff","1400ff"
	,"1500ff","1600ff","1700ff","1800ff","1900ff","1a00ff"
	,"1b00ff","1c00ff","1d00ff","1e00ff","1f00ff","2000ff"
	,"2100ff","2200ff","2300ff","2400ff","2500ff","2600ff"
	,"2700ff","2800ff","2900ff","2a00ff","2b00ff","2c00ff"
	,"2d00ff","2e00ff","2f00ff","3000ff","3100ff","3200ff"
	,"3300ff","3400ff","3500ff","3600ff","3700ff","3800ff"
	,"3900ff","3a00ff","3b00ff","3c00ff","3d00ff","3e00ff"
	,"3f00ff","4000ff","4100ff","4200ff","4300ff","4400ff"
	,"4500ff","4600ff","4700ff","4800ff","4900ff","4a00ff"
	,"4b00ff","4c00ff","4d00ff","4e00ff","4f00ff","5000ff"
	,"5100ff","5200ff","5300ff","5400ff","5500ff","5600ff"
	,"5700ff","5800ff","5900ff","5a00ff","5b00ff","5c00ff"
	,"5d00ff","5e00ff","5f00ff","6000ff","6100ff","6200ff"
	,"6300ff","6400ff","6500ff","6600ff","6700ff","6800ff"
	,"6900ff","6a00ff","6b00ff","6c00ff","6d00ff","6e00ff"
	,"6f00ff","7000ff","7100ff","7200ff","7300ff","7400ff"
	,"7500ff","7600ff","7700ff","7800ff","7900ff","7a00ff"
	,"7b00ff","7c00ff","7d00ff","7e00ff","7f00ff","8000ff"
	,"8100ff","8200ff","8300ff","8400ff","8500ff","8600ff"
	,"8700ff","8800ff","8900ff","8a00ff","8b00ff","8c00ff"
	,"8d00ff","8e00ff","8f00ff","9000ff","9100ff","9200ff"
	,"9300ff","9400ff","9500ff","9600ff","9700ff","9800ff"
	,"9900ff","9a00ff","9b00ff","9c00ff","9d00ff","9e00ff"
	,"9f00ff","a000ff","a100ff","a200ff","a300ff","a400ff"
	,"a500ff","a600ff","a700ff","a800ff","a900ff","aa00ff"
	,"ab00ff","ac00ff","ad00ff","ae00ff","af00ff","b000ff"
	,"b100ff","b200ff","b300ff","b400ff","b500ff","b600ff"
	,"b700ff","b800ff","b900ff","ba00ff","bb00ff","bc00ff"
	,"bd00ff","be00ff","bf00ff","c000ff","c100ff","c200ff"
	,"c300ff","c400ff","c500ff","c600ff","c700ff","c800ff"
	,"c900ff","ca00ff","cb00ff","cc00ff","cd00ff","ce00ff"
	,"cf00ff","d000ff","d100ff","d200ff","d300ff","d400ff"
	,"d500ff","d600ff","d700ff","d800ff","d900ff","da00ff"
	,"db00ff","dc00ff","dd00ff","de00ff","df00ff","e000ff"
	,"e100ff","e200ff","e300ff","e400ff","e500ff","e600ff"
	,"e700ff","e800ff","e900ff","ea00ff","eb00ff","ec00ff"
	,"ed00ff","ee00ff","ef00ff","f000ff","f100ff","f200ff"
	,"f300ff","f400ff","f500ff","f600ff","f700ff","f800ff"
	,"f900ff","fa00ff","fb00ff","fc00ff","fd00ff","fe00ff"
	,"ff00ff");

		if ($this->colorize == 0  || $ele < 0) return "#000000";

		$total = count($color_spectrum)-1;
		$n = floor(($ele - $this->ele_bound[0]) * $total / ($this->ele_bound[1] - $this->ele_bound[0]));
		//if ($n >= $total)
		//printf("ele=%d %f %f $n\n",$ele, $this->ele_bound[0], $this->ele_bound[1]);
		return sprintf("#%s", $color_spectrum[$n]);
	}
	function out_tracks() {
		if (empty($this->track)) return;
		for($i=0;$i<count($this->track);$i++) {
			$trk_id = sprintf("t%d", $i+1);
			printf('	<g id="%s (%s) track" opacity="0.9">',$this->track[$i]['name'], $trk_id);
			for($j=0;$j<count($this->track[$i]['point'])-1;$j++) {
				$p_id = $j+1;
				printf('		<line fill="none" id="%s p%d" stroke="%s" stroke-width="2" x1="%.4f" x2="%.4f" y1="%.4f" y2="%.4f" />'."\n",$trk_id, $p_id, 
					$this->color($this->track[$i]['point'][$j]['ele']), 
					$this->track[$i]['point'][$j]['rel'][0], $this->track[$i]['point'][$j+1]['rel'][0],
					$this->track[$i]['point'][$j]['rel'][1], $this->track[$i]['point'][$j+1]['rel'][1]);

			}
			printf("        </g>\n");
			// 輸出 label
			if ($this->show_label_trk == 1) {
				// 位置在第一個點 x+20
				printf('       <g id="%s (%s) label" opacity="1" transform="translate(0,0)" x="%.4f" y="%.4f">		<text fill="#ff0000" font-family="WenQuanYi-Zen-Hei-Mono-Regular" font-size="%d" id="%s (%s) name" opacity="1" text-anchor="middle" x="%.4f" y="%.4f">%s</text></g>'. "\n", 
					$this->track[$i]['name'], $trk_id,
					$this->track[$i]['point'][0]['rel'][0]+20,
					$this->track[$i]['point'][0]['rel'][1],
					$this->fontsize,
					$this->track[$i]['name'], $trk_id,
					$this->track[$i]['point'][0]['rel'][0]+20,
					$this->track[$i]['point'][0]['rel'][1],
					$this->track[$i]['name']);


			}
		}
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
	function out_index() {
		$left = $this->width -  $this->pixel_per_km;
		//$top = 25;
		//1. 計算能放下多少 wpt,  剩下折行, 最多 $line_len 字
		//第二行超過就變成 … 
		// $total_row = count($this->waypoint);
		$line_len = 24;
        	$j=1;$index=1;	
		mb_internal_encoding('UTF-8');
		$line[0] = array('index' => 'No.', 'data'=>"座標及說明");
		foreach($this->waypoint as $wpt) {
			$str = sprintf("%d,%d %s",$wpt['tw67'][0], $wpt['tw67'][1],trim($wpt['name']));
			$d = $this->word_split($str, $line_len);
		    for($i=0;$i<count($d);$i++) {
				if($i==0)
				 $line[$j]['index'] = $index++;
				else
				 $line[$j]['index'] = " ";
				$line[$j]['data'] = $d[$i];
				$j++;
			}
		}
		//error_log(print_r($line,true));
		$total_row = count($line);
		// 總高度不能超過
		$total = floor(($this->height - 50 )/$this->fontsize)-1;
		if ($total_row > $total) $total_row = $total;
		//error_log("total_row=".$total_row);

		$height = $this->fontsize * $total_row  + 2;

		$top = 25 + ($this->height - 50  - $height) / 2; // vcenter
		echo "<g id=\"index\">\n";
		// 2. 畫出框框
		printf("    <rect id='index_frame' x='%d' y='%d' height='%d' width='300' fill='#FFFFFF'/>\n",$left,$top,$height);
		printf("     <text id='index_col_num' x='%d' y='%d' font-size='%d' font-weight='bold' fill='#000000'>\n", $left, $top, $this->fontsize);
		$j=0;
		// 號碼
		foreach($line as $useless) {
			if ($j > $total_row ) break;
			//printf("        <tspan x='%d' y='%d'>%s</tspan>\n",$left, ($j*$this->fontsize)+$top, $j++ );
			printf("        <tspan x='%d' y='%d'>%s</tspan>\n",$left, ($j+1)*$this->fontsize+$top, $line[$j++]['index'] );

		}
		echo "</text>\n";
		$left2 = $left + 2*$this->fontsize;
		printf("<text id='index_col_name' x='%d' y='%d' font-size='%d' fill='#000000'>\n", $left2,$top, $this->fontsize);
		$j=0;
		foreach($line as $useless) {
			if ($j > $total_row ) break;
			// printf("<tspan x='%d' y='%d'>%d,%d %s</tspan>\n",$left2, ($j*$this->fontsize)+ $top, $wpt['tw67'][0],$wpt['tw67'][1],$wpt['name']);
			printf("<tspan x='%d' y='%d'>%s</tspan>\n",$left2, ($j+1)*$this->fontsize+ $top,$line[$j]['data']);
			$j++;
		}
		echo "</text></g>";

	}
	function out_waypoints() {
		if (empty($this->waypoint)) return;
		echo ' <g id="Waypoints">';
		$j=1;
		foreach($this->waypoint as $wpt) {
			printf('	<circle cx="%.4f" cy="%.4f" fill="#000000" fill-opacity="1" id="%s (w%d) marker" r="2" stroke="#000000" stroke-opacity="1" stroke-width="1.0" />'.  "\n" ,
				$wpt['rel'][0], $wpt['rel'][1],$wpt['name'], $j );
			// 顯示 wpt label: 1 顯示 index 2. 顯示在旁邊 3. 都要
			if ($this->show_label_wpt > 0 ) {
				if ($this->show_label_wpt == 1 )
					$wpt_label = "$j";
				else if ($this->show_label_wpt == 2)
					$wpt_label = $wpt['name'];
				else if ($this->show_label_wpt == 3)
					$wpt_label = sprintf("%d %s",$j,$wpt['name']);

				printf('	<g id="%s (w%d) label" transform="translate(0,0)" x="%.4f" y="%.4f"><text fill="#000000" font-family="WenQuanYi-Zen-Hei-Mono-Regular" font-size="%d" id="%s (w%d) name" text-anchor="start" x="%.4f" y="%.4f">%s</text> </g>' . "\n",
					str_replace("&","_",$wpt['name']), $j, $wpt['rel'][0], $wpt['rel'][1],
					$this->fontsize,
					str_replace("&","_",$wpt['name']), $j, $wpt['rel'][0], $wpt['rel'][1],
					$wpt_label
				);
			}

			$j++;
		}
		echo "</g>\n";
		// 如果是顯示 indexed table
		if ($this->show_label_wpt == 1 || $this->show_label_wpt == 3) {
			$this->out_index();
		}
		// 產生 logo
		if ($this->logotext) {
			$locx = 40;
			$locy = $this->height - 10 ;
			printf('	<text fill="#000000" font-family="WenQuanYi-Zen-Hei-Mono-Regular" font-size="%d" id="twmap3svg"  text-anchor="start" x="%.4f" y="%.4f">%s</text>'."\n", $this->fontsize * 2, $locx, $locy, $this->logotext);
		}
	}
	// 相對 pixel
	function rel_px($x, $y) {
		$px = ($x - $this->bound['tl'][0])*$this->ratio['x'];
		$py = ($this->bound['tl'][1]- $y)*$this->ratio['y'];
		return array($px,$py);
	}
	function header() {
		echo '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
?>
<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.0//EN" "http://www.w3.org/TR/2001/REC-SVG-20010904/DTD/svg10.dtd">
			<svg height="<?php echo $this->height;?>" onload="init(evt)" width="<?php echo $this->width;?>" xmlns="http://www.w3.org/2000/svg" xmlns:svg="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">        <script type="text/ecmascript"><![CDATA[
			var SVGDoc; var SVGRoot;
		function init(evt) {
			SVGDoc = evt.getTarget().getOwnerDocument();
			SVGRoot = SVGDoc.getDocumentElement();
		}]]>
			</script>     
<?php
	}

	function footer() {
		echo "</svg>\n";
	}
	function out_background() {
		printf('<g id="background image" opacity="1" transform="translate(0,0)">');
		if ($this->bgimg) {
			printf('<image height="%d" id="background map 1" opacity="1" width="%d" x="0" y="0" xlink:href="%s" />', $this->height, $this->width, $this->bgimg);
		}
		echo "</g>\n";
	}
	function is_taiwan($lon,$lat) {
		if ($lon < 119.31 || $lon > 124.56 || $lat < 21.88 || $lat >25.31  ) {
			return 0;
		} else if ( $lon > 119.72 ) {
			return 1;
		} else
			// 澎湖
			return 2;
	}
	function svg2png_inkscape($insvg, $outimage,$resize=array()) {
		$cmd = sprintf("inkscape -o '%s' %s 2>&1", $outimage, $insvg);
		exec($cmd, $out, $ret);
		if (strstr($out[count($out)-1],"saved")) {
			// 再 resize 一下
			if (!empty($resize) && $resize[0] >=  $this->pixel_per_km && $resize[1] >=  $this->pixel_per_km) {
				$cmd2 = sprintf("convert %s -resize %dx%d\! %s", $outimage, $resize[0],$resize[1],$outimage);
				exec($cmd2, $out2, $ret);
			}
			return array(true, implode("+",$out+$out2));
		}
		return array(false, implode("+",$out));
	}
	// use convert .. 
	// http://map.happyman.idv.tw/map/out/000003/90912/276000x2677000-5x7-v3.svg2
	// http://map.happyman.idv.tw/map/out/000003/90912/test.png
	function svg2png_magick($insvg, $outimage,$resize=array()) {
		$cmd = sprintf("convert 'msvg:%s' %s", $insvg, $outimage);
		exec($cmd, $out, $ret);
		if ($ret == 0) {
			// 再 resize 一下
			if (!empty($resize) && $resize[0] >=$this->pixel_per_km && $resize[1] >= $this->pixel_per_km) {
				$cmd2 = sprintf("convert %s -resize %dx%d\! %s", $outimage, $resize[0],$resize[1],$outimage);
				exec($cmd2, $out2, $ret);
			}
			return array(true, implode("+",$out+$out2));
		}
		return array(false, implode("+",$out));
	}
	// wrap it
	function svg2png($in, $out, $resize=array()) {
		list ($st, $msg) = $this->svg2png_magick($in,$out,$resize);
		if ($st === false)
			return $this->svg2png_inkscape($in,$out,$resize);
		else
			return array($st, $msg);   
	}
};
	
function obj2array($obj, $level=0) {

	$items = array();

	if(!is_object($obj)) return $items;

	$child = (array)$obj;

	if(sizeof($child)>1) {
		foreach($child as $aa=>$bb) {
			if(is_array($bb)) {
				foreach($bb as $ee=>$ff) {
					if(!is_object($ff)) {
						$items[$aa][$ee] = $ff;
					} else
						if(get_class($ff)=='SimpleXMLElement') {
							$items[$aa][$ee] = obj2array($ff,$level+1);
						}
				}
			} else
				if(!is_object($bb)) {
					$items[$aa] = $bb;
				} else
					if(get_class($bb)=='SimpleXMLElement') {
						$items[$aa] = obj2array($bb,$level+1);
					}
		}
	} else
		if(sizeof($child)>0) {
			foreach($child as $aa=>$bb) {
				if(!is_array($bb)&&!is_object($bb)) {
					$items[$aa] = $bb;
				} else
					if(is_object($bb)) {
						$items[$aa] = obj2array($bb,$level+1);
					} else {
						foreach($bb as $cc=>$dd) {
							if(!is_object($dd)) {
								$items[$obj->getName()][$cc] = $dd;
							} else
								if(get_class($dd)=='SimpleXMLElement') {
									$items[$obj->getName()][$cc] = obj2array($dd,$level+1);
								}
						}
					}
			}
		}

	return $items;
}


