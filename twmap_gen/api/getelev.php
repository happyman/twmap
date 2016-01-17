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
$data["weather_forcast_url"] = sprintf("http://www.cwb.gov.tw/V7/forecast/town368/towns/%s.htm?type=Weather&time=7Day",$town['Town_ID']);
if (!empty($town['cwb_tribe_code'])) {
	$data["tribe_weather"] = get_tribe_weather($town['cwb_tribe_code']);
}
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

function get_tribe_weather($code) {
	$tribe=array(
			"007"=>"泰雅族",
			"001"=>"卑南族",
			"003"=>"太魯閣族",
			"004"=>"布農族",
			"010"=>"邵族",
			"012"=>"阿美族",
			"005"=>"排灣族",
			"013"=>"雅美族(達悟族)",
			"011"=>"鄒族",
			"006"=>"撒奇萊雅族",
			"014"=>"魯凱族",
			"002"=>"噶瑪蘭族",
			"008"=>"賽夏族",
			"009"=>"賽德克族" );

	$codes = explode(",",$code);
	foreach($codes as $c) {
		if (preg_match("/(\S{3})_(\d+)(A\S{2})/",$c, $mat)) {
			$url[] = sprintf("<a href='http://www.cwb.gov.tw/V7/forecast/entertainment/tribes/%s.htm' class='weather-link'  target='cwb'>%s</a>",$c,$tribe[$mat[1]]);
		}
	}
	return implode(",",$url);
}
