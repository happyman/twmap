<?php
require_once("../config.inc.php");
if(!ob_start("ob_gzhandler")) ob_start();

$mid = (isset($_REQUEST['mid']))? $_REQUEST['mid'] : null;
$to_kml = 0;
if ($_REQUEST['kml'] && $_REQUEST['kml'] == 1)
	$to_kml = 1;
if (empty($mid)) {
	ajaxerr("insufficent parameters");
}
// 1. check is mid associated gpx exists? if yes. return nothing

$map = map_get_single($mid);
//$gpx = str_replace(".tag.png", ".gpx", $map['filename']);
$gpx = map_file_name($map['filename'],'gpx');
if (file_exists($gpx)) {
	ajaxerr("please download gpx instead. I only export GPX which not exist in filesystem, from postgis");
}
// 2. export track from postgis
$merged_gpx = sprintf("%s/merged_%s.gpx",$tmppath, $mid);
//echo "export to $merged_gpx";
if (!(file_exists($merged_gpx) && (time() - filemtime($merged_gpx) < 86400*15))) {
	
	unlink($merged_gpx);
	list ($st, $msg) = ogr2ogr_export_gpx($mid,$merged_gpx);
	if ($st === false)
		ajaxerr($msg);
}

if ($to_kml) {
	$kml = str_replace(".gpx",".kml",$merged_gpx);
	if (!file_exists($kml)) {
		$cmd = sprintf("gpsbabel -i gpx -f %s -x nuketypes,points -o kml,lines=1,points=0,line_width=3.line_color=FFFF00EF -F %s", $merged_gpx, $kml);
		exec($cmd);
	}
header('Content-type: application/vnd.google-earth.kml+xml');
header('Cache-Control: ');  //leave blank to avoid IE errors
header('Pragma: ');  //leave blank to avoid IE errors
header('Content-Disposition: attachment; filename="' . sprintf("twmap_%d.kml",$mid) .'"');
header('Content-Transfer-Encoding: binary');	
readfile($kml);	
} else {
header('Content-type: application/gpx+xml;');
header('Cache-Control: ');  //leave blank to avoid IE errors
header('Pragma: ');  //leave blank to avoid IE errors
header('Content-Disposition: attachment; filename="' . sprintf("twmap_%d.gpx",$mid) .'"');
header('Content-Transfer-Encoding: binary');	
readfile($merged_gpx);
}