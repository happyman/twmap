<?php

require_once("../config.inc.php");

$twDEM_path = "../db/DEM/twdtm_asterV2_30m.tif";
$loc = $_REQUEST['loc'];

if (empty($loc)) {
	ajaxerr("insufficent parameters");
}
list($lat,$lon)=explode(",",$_REQUEST['loc']);

$ele = get_elev($twDEM_path, $lat, $lon, 1);
$data['elevation'] = $ele;

$towns = get_administration($lon,$lat, "town");
foreach($towns as $town) {
	$town_name[] = sprintf("%s%s%s",$town['C_Name'],$town['T_Name'],($town['permit']=='t')? "(入山)" : "" );
}
$data["admin"] = implode(",",$town_name);
$nps = get_administration($lon,$lat, "nature_park");
$nrs = get_administration($lon,$lat, "nature_reserve");
if (count($nps) > 0 ) {
	foreach($nps as $np) {
		$np_name[] = $np['name'];
	}
}
if (count($nrs) > 0 ) {
	foreach($nrs as $nr) {
		$np_name[] = $nr['Name'];
	}
}
if (count($np_name) > 0 ) {
	$data['nature'] = implode(",",$np_name);
}

ajaxok($data);
