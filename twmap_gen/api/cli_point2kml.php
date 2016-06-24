<?php 


require_once("../config.inc.php");
/*
 export all points from database: point3  and convert to KML format
 */

if (php_sapi_name() != "cli")
	exit("must run from CLI");

$fpath = "/tmp/pp.json";
@unlink($fpath);
list($status, $reason) = ogr2ogr_export_points($fpath);
if ($status != true) 
	exit(sprintf("error export points: $reason"));

//print_r($re);
$content = file_get_contents($fpath);
$ret  = json_decode($content, true);

foreach($ret['features'] as $val) {
	// type
	//print_r($val);
	$type = $val['properties']['type'];
	//if ($type == '森林點') {
	//	$type .= $val['properties']['status'];
	//}
	$val['properties']['point'] = $val['geometry']['coordinates'];
	unset($val['properties']['owner']);
	unset($val['properties']['contribute']);
	$d = $val['properties']['mt100'];
	if ($d > 0 ) {
		$astr = array();
		if ($d &1 ) $astr[] = '百岳';
		if ($d &2 ) $astr[] = '小百岳';	
		if ($d &4 ) $astr[] = '百名山';
		$val['properties']['mt100_desc'] =   sprintf("%s",implode(",",$astr));
	} else {
		$val['properties']['mt100_desc'] = "";
	}
	$dd = $val['properties']['class'];
	if ($dd > 1 && $dd < 4 ) { // 2-4
		$val['properties']['stone'] = sprintf("%d-%d",$dd, $val['properties']['number']);
	} else {
		$val['properties']['stone'] = "";
	}
	/* update sql for empty ele
	if (intval($val['properties']['ele']) == 0){
		$val['properties']['ele']  =  get_elev("/home/happyman/github/twmap/dist/twmap_gen/db/DEM/twdtm_asterV2_30m.tif", $val['properties']['point'][1],$val['properties']['point'][0]);
		printf("update point3 set ele = %d where name = '%s' and type = '%s' and status='%s';\n", $val['properties']['ele'],$val['properties']['name'], $val['properties']['type'], $val['properties']['status']);
	}
	*/
	$res[$type][]= $val['properties'];



}
$i=0;
foreach($res as $key => $val) {
	// //map.happyman.idv.tw/icon/%s.png 
	$sid = sprintf("s%0d",$i++);
	$data[$sid]['name'] = sprintf("%s (%d)",$key,count($val));
	$data[$sid]['icon'] = sprintf("http://map.happyman.idv.tw/icon/%s.png", urlencode($key));
	//echo $key . "\n";
	usort($val, "ele_sort");
	$data[$sid]['markers'] = $val;
	
}
//print_r($data);

// 輸出
head(time());
style($data);
foreach($data as $style => $val) {
	folder($style,$val);
}
footer();
/*
print_r($data);
   [s25] => Array
        (
            [name] => 黑水池
            [icon] => http://map.happyman.idv.tw/icon/%E9%BB%91%E6%B0%B4%E6%B1%A0.png
            [markers] => Array
                (
                    [0] => Array
                        (
                            [name] => 馬洋池
                            [alias] =>
                            [type] => 黑水池
                            [class] => 0
                            [number] =>
                            [status] => 存在
                            [ele] =>
                            [mt100] => 0
                            [checked] => 1
                            [comment] =>
							[alias2] =>
                            [point] => Array
                                (
                                    [0] => 121.26743
                                    [1] => 24.48773
                                )

                        )

                )

        )

*/
function ele_sort($a,$b) {
if ($a['ele']==$b['ele']) return 0;
return ($a['ele']>$b['ele'])?-1:1;
}
function folder($style,$val){
	printf("<Folder><name>%s</name>",$val['name']);
	foreach($val['markers'] as $pp) {
		placemark($pp, $style);
	}
	printf("</Folder>");
}
function placemark($val,$style) {
	$cmt = implode("<br>",array($val['stone'],$val['mt100_desc'],$val['status'],$val['comment'],($val['checked'] == 0)?'待檢查':'OK'));
	$alias = ((!empty($val['alias']))? $val['alias'] : "" ). ((!empty($val['alias2']))? " " . $val['alias2']: "");
	$alias = (!empty(trim($alias)))? "(" . trim($alias) . ")" : "";
	$desc = sprintf("%s%s %sM<br>%s",$val['name'],$alias,$val['ele'],$cmt);

	$name = $val['name'];
	$x = sprintf("%f",$val['point'][0]);
	$y = sprintf("%f",$val['point'][1]);
?>
<Placemark>
	<description> <![CDATA[ <?php echo $desc; ?> ]]> </description>
	<name><?php echo $val['name'];?></name>
	<visibility>0</visibility>
	<LookAt>
	          <longitude><?php echo $x;?></longitude>
                <latitude><?php echo $y;?></latitude>

		   <range>6000</range>
    	<tilt>60</tilt>
    	<heading></heading>
  </LookAt>

		<Point>
			<coordinates><?php printf("%f,%f,%d",$x,$y,$val['ele']); ?></coordinates>
		</Point>
		<styleUrl>#<?php echo $style; ?></styleUrl>
</Placemark>

<?php
}

function head($lastupdate) {

echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<kml xmlns="http://www.opengis.net/kml/2.2">
<Document>
	        <name>地圖產生器圖資</name>
		<description>三角點小花2010,蚯蚓更新轉檔 KML 檔</description>
        <Snippet maxLines="2">Created <?php echo date("Y-m-d H:i:s",$lastupdate);?></Snippet>
        <LookAt>
                <longitude>121.174528</longitude>
                <latitude>23.545206</latitude>
                <altitude>4000</altitude>
                <range>515585.569479</range>
                <tilt>60</tilt>
                <heading>0</heading>
        </LookAt>
<?php
}
function style($style_arr) {
	foreach($style_arr as $key => $val) {
		?>
<StyleMap id="<?php echo $key;?>">
                <Pair>
                        <key>normal</key>
                        <styleUrl>#<?php echo $key;?>_normal</styleUrl>
                </Pair>
                <Pair>
                        <key>highlight</key>
                        <styleUrl>#<?php echo $key;?>_normal</styleUrl>
                </Pair>
        </StyleMap>
<Style id="<?php echo $key;?>_normal">
		               <IconStyle>
                        <scale>1.2</scale>
                        <Icon>
                                <href><?php echo $val['icon'];?></href>

                        </Icon>
                </IconStyle>
                <ListStyle>
                </ListStyle>
        </Style>
<?php
	}
}
function footer() {
	?>
	</Document>
</kml>
<?php
}
