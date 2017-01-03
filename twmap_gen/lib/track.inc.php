<?php
// Memo: track table keep 上傳的 gpx track 資訊, file system 放 track. 原始跟處理過後的共有 _o.ext, _p.kml _p.gpx
// 比較特別的是主要是處理 oruxmaps export 的含有 photo waypoint 的 kmz: see  process_oruxmap_kmz()
// db handles
function track_add($data){
	$db=get_conn();
	$sql = sprintf('insert into "track" ("uid","name","md5name","path","status","cdate","size","km_x","km_y","bbox","is_taiwan","keepon_id") values(%d,\'%s\',\'%s\',\'%s\',%d, now(),%d,%s,%s,\'%s\',\'%s\',\'%s\') returning tid',
	$data['uid'],pg_escape_string($data['name']),$data['md5name'],pg_escape_string($data['path']),$data['status'],$data['size'],
	$data['km_x'],$data['km_y'],pg_escape_string($data['bbox']),($data['is_taiwan']==1)? 't':'f', $data['keepon_id']);
	// error_log($sql);
	$rs = $db->getAll($sql);
	return $rs[0]['tid'];
}
function track_get($uid){
	$db=get_conn();
	$sql = sprintf("select * from \"track\" where uid=%d AND status <> 3",$uid);
	$rs = $db->getAll($sql);
	return $rs;
}
function track_get_single($tid){
	$db=get_conn();
	$sql = sprintf("select * from \"track\" where tid=%d",$tid);
	$rs = $db->getAll($sql);
	if (count($rs) == 1 )
		return $rs[0];
	else
		return NULL;
}
function track_expire($uid,$tid){
	$db=get_conn();
	$sql = sprintf("update \"track\" SET status=3,ddate=now() where uid=%d and tid=%d",$uid,$tid);
	$rs = $db->Execute($sql);
	return $rs;
	//echo $sql;
}
function track_files($tid) {
	// 路徑
	$data = track_get_single($tid);
	if ($data !== NULL) {
		$files =  glob(sprintf("%s/%d/*.*",$data['path'],$data['tid']));
		return $files;
	}
	return null;
}
// useless now
function track_size($tid){
	$files = track_files($tid);
	if ($files == null ) return 0;
	foreach($files as $f) {
		$total += filesize($f);
	}
	return $total;
}
function track_update($uid,$tid,$newname,$contribute){
	$db=get_conn();
	// $sql = sprintf("DELETE FROM \"track\" WHERE uid=%d AND tid=%d",$uid,$tid);
	$sql = sprintf("UPDATE \"track\" set name='%s',contribute=%d where uid=%d and tid=%d",pg_escape_string($newname),$contribute,$uid,$tid);
	$rs = $db->Execute($sql);
	return $rs;
	//echo $sql;
}

// kml process functions 
function migrate_track($fpath, $fpath2){
	$dir = dirname($fpath);
	$newdir = dirname($fpath2);
	$cmd = sprintf("mv %s %s", $dir, $newdir);
	exec($cmd);
}
function gpsbabel_convert($orig,$type,$dest,$dtype){
	global $debug;
	$cmd = sprintf("/usr/bin/gpsbabel -i %s -o %s -f %s -F %s", $type, $dtype, $orig, $dest);
	exec($cmd, $out, $ret);
	if ($debug) {
		echo "$cmd return $ret\n";
	}
	if ($ret == 0){
		return array(true,"ok");
	} else {
		return array(false, implode(" ",$out));
	}
}
//http://php.net/manual/en/function.glob.php
function glob_recursive($pattern, $flags = 0)
{
	$files = glob($pattern, $flags);
	
	foreach (glob(dirname($pattern).'/*', GLOB_ONLYDIR|GLOB_NOSORT) as $dir)
	{
		$files = array_merge($files, glob_recursive($dir.'/'.basename($pattern), $flags));
	}
	
	return $files;
}
function process_kmz_to_kml($fpath, $fname, $dest_gpx_path, $dest){
	global $debug;
	$extract_path = dirname($fpath) . "/" . $fname;
	$kml_path = $extract_path . "/doc.kml";
    $gpx_path = $extract_path . "/doc.gpx";
	$cmd = sprintf("unzip -d %s %s", escapeshellarg($extract_path), escapeshellarg($fpath));
	exec($cmd);
	if (file_exists($kml_path)){
		system("grep oruxmapsextensions $kml_path",$ret);
		if ($ret == 0){
			$cmd2 = sprintf("mogrify -format jpg  -auto-orient -thumbnail 1024x1024 '%s/files/*.jpg'",$extract_path);
			exec($cmd2, $out, $ret2);
			// if there's photo waypoints
			if ($ret2 == 0 ){
				if ($debug) {
					echo "it's a oruxmaps kmz with photos";
				}
				// do process oruxmaps kmz 
				$cwd = getcwd();
				gpsbabel_convert($kml_path,"kml",$gpx_path, "gpx");
				// $cmd3 = sprintf("gpsbabel -i kml -f %s -o gpx -F %s",escapeshellarg($kml_path), escapeshellarg($gpx_path));
				// exec($cmd3);
				$cmd4 = sprintf("rm %s; gpsbabel -i gpx -f %s -o kml,points=0 -F %s",$kml_path, escapeshellarg($gpx_path),escapeshellarg($kml_path));
				exec($cmd4);
				chdir($extract_path);
				$f=realpath("doc.kml");
				process_oruxmap_kmz(file_get_contents($f), $dest);
				chdir($cwd);
				exec("rm -r $extract_path");
				// kml convert back to gpx
				gpsbabel_convert($dest, "kml", $dest_gpx_path, "gpx");
				return true;
			}
			
		}
	} else {
		// try to find first kml and extract it
		$files = glob_recursive($extract_path ."/*.kml",0);
		if (count($files) > 0 ) {
			$cmd = sprintf("cp %s %s",$files[0],$dest);
			exec($cmd);
			gpsbabel_convert($dest, "kml", $dest_gpx_path, "gpx");
			return true;
		}
	}
	return false;
	
}
function process_oruxmap_kmz($file_string,$outfile){
	ob_start();
	// 1. decode html entities
	$lines = preg_split("/\n/",html_entity_decode($file_string));
	$folder_start =0;
	$photo_style = 0;
	foreach($lines as $line) {
		// 2. append style 3 before <Folder> tag
		if ($folder_start == 0 && preg_match("/<Folder>/",$line)) {
			echo '<Style id="oruxmap_photo_wpt"><BalloonStyle><text><![CDATA[<p align="left"><font size="+1"><b>$[name]</b></font></p> <p align="left">$[description]</p>]]></text></BalloonStyle><IconStyle><Icon><href>http://www.oruxmaps.com/iconos/wpts_foto.png</href></Icon><color>FFFFFFFF</color><colorMode>normal</colorMode><hotSpot x="0.5" xunits="fraction" y="0" yunits="fraction" /></IconStyle><LabelStyle><color>FFFFFFFF</color></LabelStyle></Style>';
			$folder_start = 1;	
		}
		// 3. replace img tag with embed html
		if (preg_match("@<img width=\"320\" src=\"(.*)\" /></td></tr>@",$line,$mat)) {
			list($width, $height) = getimagesize($mat[1]);
			if ($width > $height) $width=800; else $width=600;
			printf("<img width=%d src=\"%s\" /></td></tr>",$width, b64img($mat[1]));
			$photo_style = 1;
		} else {
			// 4. change style
			if ($photo_style == 1 && preg_match("@<styleUrl>#waypoint</styleUrl>@",$line)) {
				$photo_style = 0;
				echo "<styleUrl>#oruxmap_photo_wpt</styleUrl>\n";
				continue;
			}
			echo $line . "\n";
		}
	}
	$temp = ob_get_clean();
	file_put_contents($outfile,$temp);	
}
function b64img($path){
	$type = pathinfo($path, PATHINFO_EXTENSION);
$data = file_get_contents($path);
return  'data:image/' . $type . ';base64,' . base64_encode($data);
}
function process_track($fpath, $fname,$ext) {
	global $debug;
	if (!file_exists($fpath))
		return array(1,"no such file",array());
	$dir = dirname($fpath);
	$kml_fname = sprintf("%s/%s_p.kml",$dir,$fname);
	$gpx_fname = sprintf("%s/%s_p.gpx",$dir,$fname);
	if ($debug) 
		echo "do $ext convert\n";
	switch($ext){
		case 'kml':
			// kml just hard link
			link($fpath, $kml_fname);
			gpsbabel_convert($fpath,$ext,$gpx_fname,"gpx");
			break;
		case 'gdb':
			gpsbabel_convert($fpath,$ext,$gpx_fname,"gpx");
			gpsbabel_convert($fpath,$ext,$kml_fname,"kml");
			berak;
		case 'gpx':
			gpsbabel_convert($fpath,$ext,$kml_fname,"kml");
			link($fpath, $gpx_fname);
			break;
		case 'kmz':
			process_kmz_to_kml($fpath,$fname, $gpx_fname, $kml_fname);
			break;
		default:
			return array( 1, "unsuopported extension", array());
			break;
			
	}	

	if (file_exists($gpx_fname) && file_exists($kml_fname)){
		$svg = new gpxsvg(array("gpx"=> $gpx_fname, "width"=>1024, "fit_a4" => 0, "auto_shrink" => 0,	"show_label_trk" => 0, "show_label_wpt" => 2));
		$res =  $svg->detect_bbox();		
		// error_log(print_r($res,true));
		return array( 0, "done $kml_fname, $gpx_fname", 
			array("size"=>filesize($fpath)+filesize($kml_fname)+filesize($gpx_fname), 
			"bbox"=>$res[1]['bbox'], "km_x"=>$res[1]['x'], "km_y"=>$res[1]['y'], 
			"is_taiwan"=>$res[1]['is_taiwan']));
	}
	else {
		return array( 2, "unable to convert",array("size"=>filesize($fpath), "bbox"=>'', "km_x"=>0,"km_y"=>0,"is_taiwan"=>0));
	}
}	
