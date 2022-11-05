#!/usr/bin/php
<?php

// require db user, password defination
// 0. require packages
// 0.1 geojsplit nodejs package
// 0.2 modified geojsontoosm nodejs package
// 0.3 osmium 1.10 . ogr2ogr 
require_once("exportpbf.inc.php"); // store $dbuser, $dbpass, $debug flag
// 1. export trk and wpt from postgresql
// 2. convert geojson to osm, with special tag for rudy map.
// 3. sort and convert to pbf
// 4. convert pbf to map
// x. publish
$opt = getopt("d:s:");
if (!isset($opt['d'])) {
	$dd = date("Ymd_His");
} else {
	$dd = $opt['d'];
}
//$dd = "test";
$split_bucket = 3000;
@mkdir($dd);
$out_trk = $dd . "/trk_".$dd.".json";
$out_wpt = $dd . "/wpt_".$dd.".json";

$rmt_trk = "/tmp/trk_".$dd.".json";
$rmt_wpt = "/tmp/wpt_".$dd.".json";

if (!file_exists($out_trk)) {
	// 1. export
	$cmd = sprintf("ssh mountain@%s \"ogr2ogr -explodecollections -f GeoJSON %s 'PG:host=localhost dbname=twmap user=%s password=%s' -sql 'select abs(mid),A.*  from gpx_trk A,taiwan_poly B where ST_Within(A.wkb_geometry,B.wkb_geometry) order by abs(mid)'; gzip %s\"",$dbhost,$rmt_trk,$dbuser,$dbpass,$rmt_trk);
	echo "#export gpx_trk table...\n";
	if ($debug)  	printf("%s\n",$cmd);
	$cmd3 = sprintf("rsync -av -e ssh mountain@%s:%s.gz $dd;gzip -cd $dd/%s.gz > %s", $dbhost,$rmt_trk,basename($out_trk),$out_trk); 
	exec($cmd);
	if ($debug)  	printf("%s\n",$cmd3);
	exec($cmd3);
}
if (!file_exists($out_wpt)) {
	$cmd = sprintf("ssh mountain@%s \"ogr2ogr -explodecollections -f GeoJSON %s 'PG:host=localhost dbname=twmap user=%s password=%s' -sql 'select abs(mid),A.*  from gpx_wp A,taiwan_poly B where ST_Within(A.wkb_geometry,B.wkb_geometry) order by abs(mid)';gzip %s\"",$dbhost,$rmt_wpt,$dbuser,$dbpass,$rmt_wpt);
	echo "#export gpx_wp table...\n";
	if ($debug) 	printf("%s\n",$cmd);
	exec($cmd);
	$cmd3 = sprintf("rsync -av -e ssh mountain@%s:%s.gz $dd;gzip -cd $dd/%s.gz > %s", $dbhost,$rmt_wpt,basename($out_wpt),$out_wpt);
	exec($cmd);
	if ($debug)     printf("%s\n",$cmd3);
	exec($cmd3);
}


// 2. convert geojson to osm
// 2.1 split geojson to smaller pieces  .json to xxx.geojson

$geojson = glob($dd . "/trk/*.geojson.osm.xml");
if (count($geojson) == 0 ) {
	$cmd = sprintf("geojsplit -o %s/trk -l %d %s",$dd, $split_bucket, $out_trk);
	echo "#split $out_trk...\n";
	//if ($debug) 	printf("%s\n",$cmd);
	myexec($cmd);
	// convert to osm no care about id
	$geojson =glob($dd . "/trk/*.geojson");
	for($i=0;$i<count($geojson);$i++){
		$cmd = sprintf("node --max-old-space-size=10000 %s %s > %s.osm.xml;", $geojsontoosm_bin, $geojson[$i], $geojson[$i]);
		printf("%d/%d %s\n",$i+1,count($geojson),$cmd);
		exec($cmd);

	}
}
$geojson =glob($dd . "/wpt/*.geojson.osm.xml");
if (count($geojson) == 0 ) {
	$cmd = sprintf("geojsplit -o %s/wpt -l %d %s",$dd, $split_bucket, $out_wpt);
	echo "#split $out_wpt...\n";
	//if ($debug) 	printf("%s\n",$cmd);
	myexec($cmd);
	// convert to osm, no care about id
	$geojson =glob($dd . "/wpt/*.geojson");
	for($i=0;$i<count($geojson);$i++){
		$cmd = sprintf("node --max-old-space-size=10000 %s %s > %s.osm.xml;", $geojsontoosm_bin, $geojson[$i], $geojson[$i]);
		printf("%d/%d %s\n",$i+1,count($geojson),$cmd);
		exec($cmd);
	}
}

// now we have .geojson.xml
// 2.3 convert trk to osmpbf
$pbfs =  glob($dd ."/trk/*.xml.pbf");
if (count($pbfs) == 0 ) {
	$min_node_id = 8000000000;
	$min_way_id = 5000000000;
	$min_rel_id = 214748364; // not used
	$osms = glob($dd ."/trk/*.osm.xml");
	$node_id = $min_node_id;
	$way_id =$min_way_id ;
	$rel_id = $min_rel_id;
	foreach($osms as $fn) {
		$out=array();
		$cmd = sprintf("osmium fileinfo -e %s |grep nodes ",$fn);
		exec($cmd,$out,$ret);
		if (preg_match("/: (\d+)/",$out[0],$mat)) {
			$num = intval($mat[1]);
			$node_id += ($num + 1);
			$rel_id += 40000;
			$file = str_replace(".osm.xml","",$fn);
			// split bulcket very important
			$way_id += ($split_bucket+2);
			printf("%s %s %d %d %d\n",$file,$mat[1],$node_id,$way_id,$rel_id);
			trans($file,$node_id,$way_id,$rel_id);


		}
	}
}

$pbfs =  glob($dd ."/wpt/*.xml.pbf");
if (count($pbfs) == 0 ) {
	// convert wpt to osmpbf
	$min_node_id = 9000000000;
	$min_way_id = 5000000000; // not used
	$min_rel_id = 214748364; // not used
	$osms = glob($dd ."/wpt/*.osm.xml");
	$node_id = $min_node_id;
	$way_id =$min_way_id ;
	$rel_id = $min_rel_id;
	foreach($osms as $fn) {
		$out=array();
		$cmd = sprintf("osmium fileinfo -e %s |grep nodes ",$fn);
		exec($cmd,$out,$ret);
		if (preg_match("/: (\d+)/",$out[0],$mat)) {
			$num = intval($mat[1]);
			$node_id += ($num + 1);
			$rel_id += 40000;
			$file = str_replace(".osm.xml","",$fn);
			$way_id += ($split_bucket+1);
			printf("%s %s %d %d %d\n",$file,$mat[1],$node_id,$way_id,$rel_id);
			trans($file,$node_id,$way_id,$rel_id);


		}
	}
}
function trans($fn,$node_id,$way_id,$rel_id) {

	global $geojsontoosm_bin;
	$cmd = sprintf("node --max-old-space-size=10000 %s --start_node_id %ld --start_way_id %ld --start_relation_id %ld %s > %s.xml;",  $geojsontoosm_bin, $node_id, $way_id , $rel_id, $fn, $fn);
	$cmd .= sprintf("osmium sort --overwrite %s.xml -o %s.xml.pbf",$fn,$fn);
	//echo "$cmd\n";
	myexec($cmd);

}

// 3. final sort
if (!file_exists($dd."/trk.pbf")) {
	$cmd = sprintf("osmium sort --overwrite %s/trk/*.pbf -o %s/trk.pbf",$dd,$dd);
	myexec($cmd);
}
if (!file_exists($dd."/wpt.pbf")) {
	$cmd = sprintf("osmium sort --overwrite %s/wpt/*.pbf -o %s/wpt.pbf",$dd,$dd);
	myexec($cmd);
}

// 3.1 cleanup
//$cmd = sprintf("rm %s; rm %s; rm -r %s/trk; rm -r %s/wpt",$out_trk,$out_wpt,$dd,$dd);	
printf("postgresql to osm pbf =>  %s/trk.pbf %s/wpt.pbf\n",$dd,$dd);
//exec($cmd);



// 4. convert to map
$trk_pbf = "$dd/trk.pbf";
$wpt_pbf = "$dd/wpt.pbf";
$wpt_pbf_ren = "$dd/wpt_renum.pbf";
$trk_pbf_ren = "$dd/trk_renum.pbf";
$finalpbf = "$dd/Happyman.pbf";
$finalmap = "$dd/Happyman.map";
if (!file_exists($finalpbf)) {
	$cmd=sprintf("osmium  renumber -s 1,1,0 %s -Oo %s", $trk_pbf, $trk_pbf_ren);
	myexec($cmd);
	// merge
	$cmd = sprintf("osmium fileinfo -e %s |grep -A1 'Number of nodes' |awk -F: '{print $2}'",$trk_pbf_ren);
	$out = array();
	exec($cmd, $out, $ret);
	$s_node = intval(trim($out[0]))+1;
	$s_way = intval(trim($out[1]))+1;
	$cmd = sprintf("osmium renumber -s %d,%d,0 %s -Oo %s", $s_node, $s_way, $wpt_pbf, $wpt_pbf_ren);
	myexec($cmd);
	$cmd = sprintf("osmium merge %s %s -Oo %s", $trk_pbf_ren, $wpt_pbf_ren, $finalpbf);
	myexec($cmd);
}
// check if Happyman.pbf contains WaterSource
$cmd = sprintf("osmium cat %s -f osm |grep WaterSource",$finalpbf);
exec($cmd,$out,$ret);
if ($ret != 0 ) {
	echo "Happyman.pbf was not merged correctly\n";
	exit(1);
} else {
	echo "Happyman.pbf contains WaterSource, looks good!\n";
}
echo "finally...\n";
if (!file_exists($finalmap)){
	$cmd = sprintf("export JAVACMD_OPTIONS=\"-Xmx30G\";
			unbuffer $osmosis_bin \
			--read-pbf \"%s\" \
			--buffer --mapfile-writer \
			type=ram \
			threads=8 \
			bbox=21.55682,118.12141,26.44212,122.31377 \
			preferred-languages=\"zh,en\" \
			tag-conf-file=\"%s\" \
			polygon-clipping=true way-clipping=true label-position=false \
			zoom-interval-conf=6,0,6,10,7,11,14,12,21 \
			map-start-zoom=10 \
			comment=\"%s /  (c) Map: Happyman\" \
			file=\"%s/Happyman.map\" > %s/Happyman.log 2>&1 &",
			$finalpbf, $mapping_xml, $dd, $dd , $dd);

	// not yet
	echo "$cmd\n";
	$pid = exec($cmd);
	echo "process in background...\n";
	while(1) {
		system("tail -1 $dd/Happyman.log");
		exec(sprintf("fgrep \"finished...\" %s/Happyman.log",$dd),$out,$ret);
		if ($ret == 0 ) {
			exec("ps ax |grep osmosis |grep java |awk '{print $1}' |xargs kill");
			echo "done...\n";
			break;
		}
		sleep(10);
	}
}

// 5. publish
printf("copy to remote osmosis/%s/Happyman.map . and please release manually\n", $dd);
$cmd = sprintf("scp %s mountain@%s:/tmp/Happyman_%s.map; ssh mountain@%s \"mkdir osmosis/%s;mv /tmp/Happyman_%s.map osmosis/%s/Happyman.map\"",$finalmap, $dbhost,$dd, $dbhost, $dd, $dd, $dd);
myexec($cmd);



function myexec($cmd){
	printf("%s\n",$cmd);
	exec($cmd);
}

