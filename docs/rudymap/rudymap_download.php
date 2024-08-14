#!/usr/bin/php
<?php
/// check version and donwload the weekly release
$admin_email ="happyman";
$fpath = "/home/happyman/mapsforge";
$cpath = "/home/happyman/stache";
$cur_ver_fpath = $fpath . "/VERSION";
$want = array("MOI_OSM_Taiwan_TOPO_Rudy.map.zip","MOI_OSM_Taiwan_TOPO_Rudy.poi.zip","MOI_OSM_Taiwan_TOPO_Rudy_style.zip");
$base = "http://rudy.basecamp.tw/";
//$base = "https://rudy.outdoors.tw/";
// prevent cache
$ver_data = file_get_contents($base . "/index.json". '?'.mt_rand());
$dirty = 0;
$v = json_decode($ver_data, true);

$opt = getopt("v");
if (isset($opt['v']))  
	$version_only = 1; 
else 
	$version_only = 0;

if (file_exists($cur_ver_fpath)) {
	$cur_ver = trim(file_get_contents($cur_ver_fpath));
}
if (empty($cur_ver)) 
{
	$cur_ver = "v0.0";
}

echo "cur_ver = $cur_ver ";
// print_r($v);
foreach($v as $vv) {
	if (in_array($vv['name'], $want)) {
		
		if ($vv['name'] == "MOI_OSM_Taiwan_TOPO_Rudy.map.zip" &&
				$vv['version'] != $cur_ver ) {
				$dirty = 1;
				$ver = $vv['version'];
		}
	}

	
}
if ($dirty == 1 ) {
echo ";online_ver = $ver\n";
	if ($version_only == 1 ) 
		exit(0);
	do_update($base,$ver,$cur_ver);
} else {
	echo "no update required..\n";
	mail($admin_email,"rudy map checked", "version is now $cur_ver");
}
//print_r($v);
function do_update($base,$ver,$old_ver) {
	// 1. download  and unzip 
	global $fpath,$cpath,$admin_email;
	
	@mkdir($fpath . "/$ver", 0755, true);
	chdir($fpath . "/$ver");
	$zips = array("MOI_OSM_Taiwan_TOPO_Rudy.map.zip","MOI_OSM_Taiwan_TOPO_Rudy.poi.zip","MOI_OSM_twmap_style.zip", "MOI_OSM_bn_style.zip","MOI_OSM_dn_style.zip", "MOI_OSM_Taiwan_TOPO_Rudy_style.zip","MOI_OSM_tn_style.zip");
	foreach($zips as $zip) {
		//my_system(sprintf("wget -O %s %s",$zip , $base . $zip));
		my_system(sprintf("rsync -avP homevm:rudy/static/%s ./%s",$zip,$zip));
		my_system("unzip -o $zip");
	}
	// check version
	// strings MOI_OSM_Taiwan_TOPO_Rudy.map
	$cmd=sprintf("strings MOI_OSM_Taiwan_TOPO_Rudy.map |grep -e \"RuMAP %s\"",$ver);
	my_system($cmd);
	chdir($fpath);
       // 3. clean tile cache
	echo "tilestache clean...\n";
	$layers = array("moi_osm","moi_osm_gpx","rudy_default","moi_happyman_nowp_nocache","twmap_happyman_nowp_nocache");
	foreach($layers as $layer) {
		echo "cleaning $layer...\n";
		$old=$layer.".old";
		chdir($cpath);
		if (file_exists($old)) {
			exec("rm -r $old");
			echo "$old removed\n";
		}
		exec("mv $layer $old &&  mkdir $layer");
		echo "$layer created\n";
	}
	// 4. serve it 
	chdir($fpath);
	exec("rm -f cur; ln -s $ver cur");
	// 5. update VERSION
	echo "update VERSION file\n";
	file_put_contents($fpath . "/VERSION", $ver);
	// 6. restart java
	echo "restart java tile server...\n";
	system("killall -9 java");
	// 7. update prop file
	// exec("cp MOI_OSM.xml.prop cur");
	// 8. purgecache from cloudflare
	//exec("bash /home/happyman/bin/purgecache");
	purge_cache();
	// 9. delete unzipped files from old directory
	echo "clean old directory...\n";
	exec("cd $fpath; bash rudymap_clean.sh $old_ver");
	// 10. email me
	mail($admin_email,"rudy map updated!", "version is now $ver");
	

}
function my_system($cmd) {
	global $ver;
	exec($cmd, $out, $ret);
	if ($ret != 0 ){
		exec("rm -r $fpath/$ver");
		echo "cmd fails: $cmd\n";
		exit(1);
	}
}
function purge_cache(){
	$auth_email="";
	$auth_key="";
	if (empty($auth_email)) return;
	$cmd=sprintf('curl -X POST "https://api.cloudflare.com/client/v4/zones/47e7b5f66904a67bbd6a7e58eda91331/purge_cache" -H "X-Auth-Email: %s" -H "X-Auth-Key: %s" -H "Content-Type: application/json" --data \'{"purge_everything":true}\'',$auth_email,$auth_key);
	echo $cmd;
	exec($cmd);
}
