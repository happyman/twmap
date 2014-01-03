<?php

require_once("config.inc.php");
require_once("lib/print_pdf.inc.php");


$mid = $_GET['mid'];
$map = map_get_single($mid);
if ($map == null ) {
	  echo "<h1>無此 map".print_r($_GET,true)."</h1>";
		  exit(0);
}


$files = map_files($map['filename']);
foreach($files as $f ) {
	if (preg_match("/_\d+\.png/",$f)) {
		$imgarr[] = $f;
	}
}
//  // 排序一下
usort($imgarr, 'indexcmp');
$pdf = new print_pdf(array('title'=> $map['title'], 'subject'=> str_replace(".tag.png", "", basename($map['filename'])), 'outfile' => str_replace("png","pdf",$map['filename']), 'infiles' => $imgarr));
$pdf->doit();
// $pdf->create_pdf_meta();

readfile(dirname($map['filename']) . "/info.txt");

