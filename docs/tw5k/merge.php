<?php

$f=glob("9*/*.TIF");

$twd67 = '+proj=tmerc +lat_0=0 +lon_0=121 +k=0.9999 +x_0=250000 +y_0=0 +ellps=aust_SA +towgs84=-752,-358,-179,-0.0000011698,0.0000018398,0.0000009822,0.00002329 +units=m +no_defs';
foreach($f as $ff) {
	list($fname,$ext) = explode(".",$ff);
	$cmd = sprintf("gdalwarp %s %s.tiff -overwrite -s_srs '%s' -t_srs epsg:4326",$ff,$fname,$twd67);
	echo $cmd  ."\n";
}

