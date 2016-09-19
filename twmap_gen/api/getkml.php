<?php

$uid = 1;
require_once("../config.inc.php");
if(!ob_start("ob_gzhandler")) ob_start();

$mid = (isset($_REQUEST['mid']))? $_REQUEST['mid'] : null;

if (empty($mid)) {
	ajaxerr("insufficent parameters");
}

list ($status,$msg)=make_kml($mid);
if ($status == true) {
	header('Content-type: application/vnd.google-earth.kml+xml');
	header('Cache-Control: ');  //leave blank to avoid IE errors
	header('Pragma: ');  //leave blank to avoid IE errors
	header('Content-Disposition: attachment; filename="' . sprintf("twmap_%d.kml",$mid) .'"');
	header('Content-Transfer-Encoding: binary');	
	readfile($msg);
}
function make_kml($mid) {
	$map = map_get_single($mid);
	
	if ($map === false)  {
		return array(false, "no such map");
	}

	// 把地圖拿出來換成 kml
	$gpx = map_file_name($map['filename'],'gpx');
	if (!file_exists($gpx)) {
		header(sprintf("Location: export_mid_gpx.php?mid=%d&kml=1",$mid));
		exit(0);
	}
		
	// test kml happyman add test path
	$cachefile = sprintf("/srv/www/htdocs/map/gpxtmp/test/%06d/%d/%s",$map['uid'], $map['mid'], basename(str_replace(".gpx", ".kml", $gpx)));
	if (file_exists($gpx)) {
		if (0 && file_exists($cachefile) && filemtime($cachefile) >= filemtime($gpx)) {
			return array(true, $cachefile);
		}
		@mkdir(dirname($cachefile),0755, true);
		//$cmds_args[10]  = "-x nuketypes,tracks,routes -x simplify,count=10 -x position,distance=20k";
		//	$cmds_args[11]  = "-x nuketypes,tracks,routes -x position,distance=10k";
		//	$cmds_args[12]  = "-x nuketypes,tracks,routes -x position,distance=5k";
		//$cmds_args[13]  = "-x nuketypes,tracks,routes -x position,distance=2k";
		//$cmds_args[14]  = "-x nuketypes,tracks,routes -x position,distance=1k";
		//$cmds_args[15]  = "-x nuketypes,tracks,routes -x position,distance=500m";
		//$cmds_args[16]  = "-x nuketypes,tracks,routes -x position,distance=200m";
		//$cmds_args[17]  = "-x nuketypes,tracks,routes -x position,distance=100m";
		//$cmds_args[18]  = "-x nuketypes,tracks,routes -x position,distance=1m";
		$cmd = sprintf("gpsbabel -i gpx -f %s -x nuketypes,points -o kml,lines=1,points=0,line_color=%s,line_width=3 -F %s", $gpx, pick_color($mid),$cachefile);
		exec($cmd);
			return array(true, $cachefile);
	} else  {
				return array(false, "no gpx");
	}
}

function pick_color($mid) {
	return "FFFF00FF";
	$colors = array(
		"#000000","#000033","#000066","#000099","#0000CC","#0000FF","#003300","#003333","#003366","#003399","#0033CC","#0033FF","#006600","#006633","#006666","#006699","#0066CC","#0066FF","#009900","#009933","#009966","#009999","#0099CC","#0099FF","#00CC00","#00CC33","#00CC66","#00CC99","#00CCCC","#00CCFF","#00FF00","#00FF33","#00FF66","#00FF99","#00FFCC","#00FFFF","#330000","#330033","#330066","#330099","#3300CC","#3300FF","#333300","#333333","#333366","#333399","#3333CC","#3333FF","#336600","#336633","#336666","#336699","#3366CC","#3366FF","#339900","#339933","#339966","#339999","#3399CC","#3399FF","#33CC00","#33CC33","#33CC66","#33CC99","#33CCCC","#33CCFF","#33FF00","#33FF33","#33FF66","#33FF99","#33FFCC","#33FFFF","#660000","#660033","#660066","#660099","#6600CC","#6600FF","#663300","#663333","#663366","#663399","#6633CC","#6633FF","#666600","#666633","#666666","#666699","#6666CC","#6666FF","#669900","#669933","#669966","#669999","#6699CC","#6699FF","#66CC00","#66CC33","#66CC66","#66CC99","#66CCCC","#66CCFF","#66FF00","#66FF33","#66FF66","#66FF99","#66FFCC","#66FFFF","#990000","#990033","#990066","#990099","#9900CC","#9900FF","#993300","#993333","#993366","#993399","#9933CC","#9933FF","#996600","#996633","#996666","#996699","#9966CC","#9966FF","#999900","#999933","#999966","#999999","#9999CC","#9999FF","#99CC00","#99CC33","#99CC66","#99CC99","#99CCCC","#99CCFF","#99FF00","#99FF33","#99FF66","#99FF99","#99FFCC","#99FFFF","#CC0000","#CC0033","#CC0066","#CC0099","#CC00CC","#CC00FF","#CC3300","#CC3333","#CC3366","#CC3399","#CC33CC","#CC33FF","#CC6600","#CC6633","#CC6666","#CC6699","#CC66CC","#CC66FF","#CC9900","#CC9933","#CC9966","#CC9999","#CC99CC","#CC99FF","#CCCC00","#CCCC33","#CCCC66","#CCCC99","#CCCCCC","#CCCCFF","#CCFF00","#CCFF33","#CCFF66","#CCFF99","#CCFFCC","#CCFFFF","#FF0000","#FF0033","#FF0066","#FF0099","#FF00CC","#FF00FF","#FF3300","#FF3333","#FF3366","#FF3399","#FF33CC","#FF33FF","#FF6600","#FF6633","#FF6666","#FF6699","#FF66CC","#FF66FF","#FF9900","#FF9933","#FF9966","#FF9999","#FF99CC","#FF99FF","#FFCC00","#FFCC33","#FFCC66","#FFCC99","#FFCCCC","#FFCCFF","#FFFF00","#FFFF33","#FFFF66","#FFFF99","#FFFFCC","#FFFFFF" );
	return "FF". str_replace("#","",$colors[$mid % 216]);
}
function rand_abgr(){
	$r = dechex(mt_rand(0,255)); // generate the red component
	$g = dechex(mt_rand(0,255)); // generate the green component
	$b = dechex(mt_rand(0,255)); // generate the blue component
	$rgb = "FF" . $r.$g.$b;
	if($r == $g && $g == $b){
		$rgb = substr($rgb,0,3); // shorter version
	}
	return $rgb;
}
