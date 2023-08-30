<?php
Namespace Happyman\Twmap;

class Proj {

	/*
	DMS to degree
		121d21'6.689"E => 121.351858056
		23d53'21.232"N => 23.8892311111
	*/
	static function check(){
		$req=[ 'cs2cs' => 'proj-bin', 'proj' => 'proj-bin' ];
		$err=0;
		$classname=get_called_class();
		foreach($req as $bin=>$package){
			exec("$bin >/dev/null 2>&1",$out,$ret);
			if ($ret!=0){
				printf("[%s] %s not installed, please install %s",$classname,$bin,$package);
				$err++;
			}else{
				printf("[%s] %s installed\n",$classname,$bin);
			}
		}
		if ($err>0)
			return false;
		else
			return true;
	}

	static function cs2cs_t67to97($x,$y,$ph) {
		if ($ph==1) $lon_0 = 119; else $lon_0 = 121;
		$proj = "cs2cs +proj=tmerc +ellps=aust_SA +towgs84=-764.558,-361.229,-178.374,-.0000011698,.0000018398,.0000009822,.00002329 +lon_0=$lon_0 +x_0=250000 +k=0.9999 +to +proj=tmerc +datum=WGS84 +lon_0=$lon_0 +x_0=250000 +k=0.9999";
		//echo $proj;
		$ret=shell_exec("echo $x $y | $proj");
		//echo $ret;
		list($x,$y,$junk)=preg_split("/\s+/",$ret);
		//$r=dms2deg($x,$y);
		//return $r;
		return array($x,$y);

	}
	static function cs2cs_t97to67($x,$y,$ph) {
		if ($ph==1) $lon_0 = 119; else $lon_0 = 121;
		$proj = "cs2cs -I +proj=tmerc +ellps=aust_SA +towgs84=-764.558,-361.229,-178.374,-.0000011698,.0000018398,.0000009822,.00002329 +lon_0=$lon_0 +x_0=250000 +k=0.9999 +to +proj=tmerc +datum=WGS84 +lon_0=$lon_0 +x_0=250000 +k=0.9999";
		//echo $proj;
		$ret=shell_exec("echo $x $y | $proj");
		//echo $ret;
		list($x,$y,$junk)=preg_split("/\s+/",$ret);
		//$r=dms2deg($x,$y);
		//return $r;
		return array($x,$y);
	}

	static function proj_67toge($p) {
		$x=$p[0];$y=$p[1];
		$r=t67to97($x,$y);
		$x=$r[0]; $y=$r[1];
		$proj="proj -I +proj=tmerc +ellps=aust_SA +lon_0=121 +x_0=250000 +k=0.9999  -f '%.8f'";
		$ret=shell_exec("echo $x $y | $proj");
		return preg_split("/\s+/",$ret);
		//$r=dms2deg($x,$y);
		//return $r;
	}
	static function proj_67toge2($p) {
		$x=$p[0];$y=$p[1];
		$r=self::cs2cs_t67to97($x,$y, 0);
		return self::proj_97toge2($r);

	}
	static function proj_97toge2($p){
		$x=$p[0];$y=$p[1];
		$proj="proj -I +proj=tmerc +ellps=aust_SA +lon_0=121 +x_0=250000 +k=0.9999  -f '%.8f'";
		$ret=shell_exec("echo $x $y | $proj");
		return preg_split("/\s+/",$ret);
	//	$r=dms2deg($x,$y);
		//return $r;
	}
	// 澎湖, 使用 cs2cs 的方式
	// http://www.mobile01.com/topicdetail.php?f=130&t=5743&p=2
	static function ph_proj_67toge2($p) {
		$x=$p[0];$y=$p[1];
		$r=self::cs2cs_t67to97($x,$y,1);
		return self::ph_proj_97toge2($r);
	}
	static function ph_proj_97toge2($p){
		$x=$p[0];$y=$p[1];
		$proj="proj -I +proj=tmerc +ellps=aust_SA +lon_0=119 +x_0=250000 +k=0.9999 -f '%.8f' ";
		$ret=shell_exec("echo $x $y | $proj");
		return preg_split("/\s+/",$ret);
	}
	static function dms2deg($x,$y) {
		$str="$x $y";
		$r=array();
		if (preg_match("/(\d+)d(\d+)'([\d.]+)\"E\s+(\d+)d(\d+)'([\d.]+)\"N/", $str, $matches)) {
			list ($junk, $ed, $em, $es, $nd, $nm, $ns) = $matches;
			$r[0] = $ed + $em / 60 + $es / 3600;
			$r[1] = $nd + $nm / 60 + $ns / 3600;
		}
		return $r;
	}
	// 澎湖 twd67 to Google Earth
	// http://wiki.osgeo.org/wiki/Taiwan_datums
	// 二參數轉換: TWD67橫座標=TWD97橫座標-828公尺, TWD67縱座標=TWD97縱座標+207公尺, 誤差約在5公尺以內
	static function ph_proj_67toge($p) {
		$x=$p[0]+828;$y=$p[1]-207;
		$proj="proj -I +proj=tmerc +ellps=aust_SA +lon_0=119 +x_0=250000 +k=0.9999";

		$ret=shell_exec("echo $x $y | $proj");
		list($x,$y)=preg_split("/\s+/",$ret);
		$r=self::dms2deg($x,$y);
		return $r;
	}
	static function proj_geto67($p) {
		$pp=array(self::deg2dms($p[0])."E",self::deg2dms($p[1])."N");
		// print_r($pp);
		$proj="proj +proj=tmerc +ellps=aust_SA +lon_0=121 +x_0=250000 +k=0.9999";
		$k=addslashes("$pp[0] $pp[1]");
		$cmd="echo $k | $proj";
		// echo $cmd ."\n";
		$ret= shell_exec("echo $k | $proj");
		list($x,$y)=preg_split("/\s+/",$ret);
		return t97to67($x,$y);
	}
	static function proj_geto97($p) {
			$pp=array(self::deg2dms($p[0])."E",self::deg2dms($p[1])."N");
		// print_r($pp);
		$proj="proj +proj=tmerc +ellps=aust_SA +lon_0=121 +x_0=250000 +k=0.9999";
		$k=addslashes("$pp[0] $pp[1]");
		$cmd="echo $k | $proj";
		// echo $cmd ."\n";
		$ret= shell_exec("echo $k | $proj");
		return preg_split("/\s+/",$ret);

	}
	static function proj_geto97_ph($p) {
			$pp=array(self::deg2dms($p[0])."E",self::deg2dms($p[1])."N");
		// print_r($pp);
		$proj="proj +proj=tmerc +ellps=aust_SA +lon_0=119 +x_0=250000 +k=0.9999";
		$k=addslashes("$pp[0] $pp[1]");
		$cmd="echo $k | $proj";
		// echo $cmd ."\n";
		$ret= shell_exec("echo $k | $proj");
		return preg_split("/\s+/",$ret);

	}
	static function proj_geto672($p) {
		$pp=array(self::deg2dms($p[0])."E",self::deg2dms($p[1])."N");
		// print_r($pp);
		$proj="proj +proj=tmerc +ellps=aust_SA +lon_0=121 +x_0=250000 +k=0.9999";
		$k=addslashes("$pp[0] $pp[1]");
		$cmd="echo $k | $proj";
		// echo $cmd ."\n";
		$ret= shell_exec("echo $k | $proj");
		list($x,$y)=preg_split("/\s+/",$ret);
		return self::cs2cs_t97to67($x,$y,0);
	}
	static function proj_geto672_ph($p) {
		$pp=array(self::deg2dms($p[0])."E",self::deg2dms($p[1])."N");
		// print_r($pp);
		$proj="proj +proj=tmerc +ellps=aust_SA +lon_0=119 +x_0=250000 +k=0.9999";
		$k=addslashes("$pp[0] $pp[1]");
		$cmd="echo $k | $proj";
		// echo $cmd ."\n";
		$ret= shell_exec("echo $k | $proj");
		list($x,$y)=preg_split("/\s+/",$ret);
		return self::cs2cs_t97to67($x,$y,0);
	}
	static function t67to97($x,$y) { 
		$A= 0.00001549;
		$B= 0.000006521;
		return array($x + 807.8 + $A * $x + $B * $y, $y - 248.6 + $A * $y + $B * $y);
	}
	static function t97to67($x,$y) {
		$A= 0.00001549;
		$B= 0.000006521;
		return array($x - 807.8 - $A * $x - $B * $y, $y + 248.6 - $A * $y - $B * $x);
	}
	/*
	degree to DMS
		121.351858056 => 121d21'6.689"E 
		23.8892311111 => 23d53'21.232"N 
		borrow: http://sourceforge.net/projects/geoclassphp
	*/  
	static function deg2dms($degFloat,$decPlaces = 3) {
		$deg = abs($degFloat) + 0.5 / 3600 / pow(10, $decPlaces);
		$degree = floor($deg);
		$deg = 60 * ($deg - $degree);
		$minutes = floor($deg);
		$deg = 60 * ($deg - $minutes);
		$seconds = floor($deg);
		$subseconds = ($deg - $seconds);
		for($i=1;$i<=$decPlaces;$i++) {
			$subseconds = 10 * $subseconds;
		}
		$subseconds = floor($subseconds);
		if ($decPlaces > 0) {
			$seconds = $seconds.".".sprintf("%03s",$subseconds);
		}
		return $degree."d$minutes'$seconds\"";
	}



/*
	static function Demo_TW() {
		$p=array(282745,2641869);
		print_r($p);
		echo "cs2cs法:\n";
		$p1=cs2cs_t67to97($p[0],$p[1],0);
		print_r($p1);
		echo "cs2cs法 invert:\n";
		$pp=cs2cs_t97to67($p1[0],$p1[1],0);
		print_r($pp);
		echo "平面四參數轉換:\n";
		$p1=t67to97($p[0],$p[1]);
		print_r($p1);
		echo "平面四參數轉換 Invert:\n";
		$p2=t97to67($p1[0],$p1[1]);
		print_r($p2);
		echo "使用平面四參數轉換:\n";
		$q=proj_67toge($p);
		print_r($q);
		echo "使用 cs2cs:\n";
		$q1=proj_67toge2($p);
		print_r($q);
		
		
	}
	// example:
	// 
	static function Demo_PH() {
		$p=array(285000,2643000);
		print_r($p);
		//$p1=t67to97($p[0],$p[1]);
		$p1 = array($p[0]+828,$p[1]-207);
		echo "二參數轉換:\n";
		print_r($p1);
		echo "cs2cs法:\n";
		$p1=cs2cs_t67to97($p[0],$p[1],1);
		print_r($p1);
		echo "cs2cs法 invert:\n";
		$pp=cs2cs_t97to67($p1[0],$p1[1],1);
		print_r($pp);
		echo "使用二參數轉換法:\n";
		$q=ph_proj_67toge($p);
		print_r($q);
		echo "使用 cs2cs:\n";
		$q1=ph_proj_67toge2($p);
		print_r($q1);
		//$p=proj_geto67($q);
		//print_r($p);
	}
*/
	static function LatLong2XYZ($lon,$lat,$zoom) {
		$xtile = floor((($lon + 180) / 360) * pow(2, $zoom));
		$ytile = floor((1 - log(tan(deg2rad($lat)) + 1 / cos(deg2rad($lat))) / pi()) /2 * pow(2, $zoom));
		return array($xtile,$ytile,$zoom);
	}
	/**
	 * returns a Rectangle2D with x = lon, y = lat, width=lonSpan, height=latSpan
	 * for an x,y,zoom as used by google.
	 */
	static function getLatLonXYZ($x, $y, $zoom) {
		$debug = isset($_GET['debug'])? $_GET['debug']: 0;
		$lon      = -180; // x
		$lonWidth = 360; // width 360

		$lat       = -1;
		$latHeight = 2;

		$tilesAtThisZoom = 1 << ($zoom);
		$lonWidth  = 360.0 / $tilesAtThisZoom;
		$lon       = -180 + ($x * $lonWidth);
		$latHeight = 2.0 / $tilesAtThisZoom;
		$lat       = (($tilesAtThisZoom/2 - $y-1) * $latHeight);

		if ($debug) {echo("(uniform) lat:$lat latHt:$latHeight<br>");}
		// convert lat and latHeight to degrees in a transverse mercator projection
		// note that in fact the coordinates go from about -85 to +85 not -90 to 90!
		$latHeight += $lat;
		$latHeight = (2 * atan(exp(PI() * $latHeight))) - (PI() / 2);
		$latHeight *= (180 / PI());

		$lat = (2 * atan(exp(PI() * $lat))) - (PI() / 2);
		$lat *= (180 / PI());


		if ($debug) {echo("pre subtract lat: $lat latHeight $latHeight<br>");}
		$latHeight -= $lat;
		if ($debug) {echo("lat: $lat latHeight $latHeight<br>");}

		if ($lonWidth < 0) {
			$lon      = $lon + $lonWidth;
			$lonWidth = -$lonWidth;
		}

		if ($latHeight < 0) {
			$lat       = $lat + $latHeight;
			$latHeight = -$latHeight;
		}


		$rect = new aRectangle();
		$rect->x = $lon;
		$rect->y = $lat;
		$rect->height = $latHeight;
		$rect->width= $lonWidth;

		return $rect;
	}
	// $src = "EPSG:4326"
	// $arr[] = "121.2323 23.1212";
	static function gdaltransform_multi($src,$dst,$arr) {
	$cmd = sprintf("printf \"%s\n\" | gdaltransform -s_srs %s -t_srs %s", implode("\\n",$arr),$src,$dst);
	exec($cmd,$out,$ret);
			if ($ret == 0 ) {
					return $out;
			}
			return false;
	}
}
class aRectangle {
	var $x,$y;
	var $width, $height;
}
