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
ajaxok($data);
