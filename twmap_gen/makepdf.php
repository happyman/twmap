<?php

require_once("config.inc.php");
require_once(__ROOT__."lib/Twmap/Export/Pdf.php");


if (php_sapi_name() == "cli")
	$mid = $argv[1];
else
	$mid = $_GET['mid'];
$map = map_get_single($mid);
if ($map == null ) {
	  echo "<h1>無此 map".print_r($_GET,true)."</h1>";
	  exit(0);
}


$files = map_files($map['filename']);

$imgarr = array();
foreach($files as $f ) {
	if (preg_match("/_\d+\.png/",$f)) {
		$imgarr[] = $f;
	}
}
if (empty($imgarr)){
		$imgarr[] = map_fs_path($map['filename']);
}
//  // 排序一下
usort($imgarr, 'indexcmp');
$pdf = new Happyman\Twmap\Export\Pdf(array(
	'title'=> $map['title'],
	'subject'=> str_replace(".tag.png", "", basename($map['filename'])),
	'outfile' => map_fs_path(str_replace("tag.png","pdf",$map['filename'])),
	'infiles' => $imgarr,
	"a3"=>1,
	"twmap_ver"=> $twmap_gen_version,
	"quiet"=>1,
));
$pdf->print_cmd = 0;
$retval = $pdf->doit(function($msg){});
if ($retval === false) {
	echo "pdf generate failed\n";
	exit(1);
}
echo "saved to $retval\n";
