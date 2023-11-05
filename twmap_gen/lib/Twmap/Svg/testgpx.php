<?php
// detect bbox 及產生 cmd_make 的參數, 測試用

define('__ROOT__', dirname(__FILE__). "/");
require_once(__ROOT__."Gpx2Svg.php");
require_once(__ROOT__."../Proj.php");
require_once(__ROOT__."../../geoPHP/geoPHP.inc");
require_once(__ROOT__."../../slog/load.php");

$datum = "TWD97";   
$tmp_gpx = isset($argv[1])? $argv[1]: "";
if (!file_exists($tmp_gpx)){
    printf("Usage: %s gpx_file\n",$argv[0]);
    exit(1);
}
$svg = new Happyman\Twmap\Svg\Gpx2Svg(array("gpx" => $tmp_gpx, "width" => 1024, "fit_a4" => 1, "auto_shrink" => (isset($inp['auto_shrink'])) ? 1 : 0,
"show_label_trk" => 0, "show_label_wpt" =>1 , "datum"=> $datum));
$ret = $svg->process();
//  print_r($svg);
$inp['startx'] = $svg->bound_twdtm2['tl'][0] / 1000;
$inp['starty'] = $svg->bound_twdtm2['tl'][1] / 1000;
$inp['shiftx'] = ($svg->bound_twdtm2['br'][0] - $svg->bound_twdtm2['tl'][0]) / 1000;
$inp['shifty'] = ($svg->bound_twdtm2['tl'][1] - $svg->bound_twdtm2['br'][1]) / 1000;
$inp['ph'] = $svg->bound_twdtm2['ph'];
printf("參數:\n\n");
printf("php ../../../cmd_make2.php -r %s:%s:%s:%s:%s -p %d -g %s:0:1 -c -O /tmp -v 2016 -S\n",
$inp['startx'],$inp['starty'],$inp['shiftx'],$inp['shifty'],$datum,$inp['ph'],realpath($tmp_gpx) );