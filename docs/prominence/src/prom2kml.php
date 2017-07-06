<?php
// 1. input 要先 sort 過
// 2. column 4 名稱要有值　也就是最好有　peak name　
// 3. head: sn,peak_x,peak_y,peak_h,peak_name,prominence,col_x,col_y,col_h,parent_x,parent_y,parent_h,parent_name
$opt=getopt("i:");
if (!isset($opt['i'])){
	echo "Usage: $argv[0] -i prom.csv > prom.kml\n";
	exit(0);
	
}

$data = file($opt['i']);


printf("<?xml version=\"1.0\" encoding=\"UTF-8\"?>");
printf("<kml xmlns=\"http://www.opengis.net/kml/2.2\">");

/*
for($i=1;$i<251;$i++) {
	$row = explode(",",trim($data[$i]));
	outpoint($row,$i);

}
*/
$date_str = date('Y-m-d');
$mt3000=array();
$mt2000=array();
$mt1500=array();
printf("<Document>");
printf("<name>台灣獨立峰列表 by 地表突起度</name>");
?>
	<description><![CDATA[依照 <a href="https://en.wikipedia.org/wiki/Topographic_prominence">Topographic Prominence</a>計算，
	使用 Rudy 修正過的台灣內政部 <a href="https://dl.dropboxusercontent.com/u/899714/src-data/moi/dem_20m-wgs84.tif">DEM</a> (hgt 檔案)</a>及 
	<a href="https://github.com/randall77/prominence">randall77</a> 的程式計算。
	Inspired by 小花。  蚯蚓 <?php echo $date_str; ?>]]></description>
	<LookAt>
		<longitude>121.174528</longitude>
		<latitude>23.545206</latitude>
		<altitude>4000</altitude>
		<heading>0</heading>
		<tilt>60</tilt>
		<range>515585.569479</range>
	</LookAt>
<?php
foreach($data as $line) {
	$row = explode(",",trim($line));
	if ($row[1]=='peak_x') continue;
	if ($row[3] > 3000) {
		// if (count($mt3000) > 100) continue;
		if ($row[5] < 100) continue; 
		$mt3000[] = $row;
	} else if ($row[3] < 1500) {
		if (count($mt1500) > 100) continue;
		$mt1500[] = $row;
	} else{
		if (count($mt2000) > 100) continue;
		$mt2000[] = $row;
	}
}
if (!empty($mt3000))
	outfolder($mt3000,"高山");
if (!empty($mt2000))
	outfolder($mt2000,"中級山");
if (!empty($mt1500))
	outfolder($mt1500,"郊山");

printf("</Document></kml>\n");
function outpoint($row,$index){
		// only one folder
		if ($index == $row[0])
			printf("<Placemark>\n<name>%d %s (%d)</name><styleUrl>#peak</styleUrl>\n",$index,$row[4],$row[5]);
		else
			printf("<Placemark>\n<name>%d(%d),%s [%d] %dM to %dM</name><styleUrl>#peak</styleUrl>\n",$index,$row[0],$row[4],$row[5],$row[3],$row[8]);
		
		printf("<description><![CDATA[高度 %.0f<br>突起度=%.0f]]></description> \n", $row[3], $row[5]);
	printf("   <Point>    <coordinates>%f,%f,%d</coordinates>  <altitudeMode>absolute</altitudeMode>\n", $row[1],$row[2],$row[3]);

	printf("</Point>\n</Placemark>\n");
	if (!empty($row[6])){
		// peak to key col
		printf("<Placemark><name>%s to %d</name>%s<LineString>",$row[4],$row[8],linewidth($row[5]));
		printf(" <coordinates>%f,%f,%d\n", $row[1],$row[2],$row[3]);
		printf(" %f,%f,%d\n", $row[6],$row[7],$row[8]);
		printf(" </coordinates>\n<altitudeMode>absolute</altitudeMode>\n");
		printf("</LineString>\n</Placemark>");
		// key col to parent peak
		printf("<Placemark><name>%s to %d</name>%s<LineString>",$row[12],$row[8],linewidth($row[5]));
		printf(" <coordinates>%f,%f,%d\n", $row[9],$row[10],$row[11]);
		printf(" %f,%f,%d\n", $row[6],$row[7],$row[8]);
		printf(" </coordinates>\n<altitudeMode>absolute</altitudeMode>\n");
		printf("</LineString>\n</Placemark>");
		$key=sprintf("%f_%f_%d_1",$row[6],$row[7],$row[8]);
		if (!isset($icon[$key])){
			printf("<Placemark><name>%s 主鞍</name><styleUrl>#col2</styleUrl><Style><IconStyle><heading>%f</heading></IconStyle></Style>\n",$row[4],headings($row[1],$row[2],$row[6],$row[7]));
				printf("<description><![CDATA[%s %dM 的主鞍部=%.0f]]></description> \n", $row[4], $row[3], $row[8]);
			printf("    <Point>   <coordinates>%f,%f,%d</coordinates>\n<altitudeMode>absolute</altitudeMode>\n", $row[6],$row[7],$row[8]);
		
			printf("</Point>\n</Placemark>\n");
			$icon[$key] = 1;
		}		
		$key=sprintf("%f_%f_%d_2",$row[6],$row[7],$row[8]);
		if (!isset($icon[$key])){
			printf("<Placemark><name>%s M to %s M</name><styleUrl>#col</styleUrl><Style><IconStyle><heading>%f</heading></IconStyle></Style>\n",$row[8],$row[11],headings($row[9],$row[10],$row[6],$row[7]));
				printf("<description><![CDATA[%s %dM 的主鞍部=%.0f 指向父峰 %s %dM]]></description> \n", $row[4], $row[3], $row[8], $row[12],$row[11]);
			printf("    <Point>   <coordinates>%f,%f,%d</coordinates>\n<altitudeMode>absolute</altitudeMode>\n", $row[6],$row[7],$row[8]);
		
			printf("</Point>\n</Placemark>\n");
			$icon[$key] = 1;
		}
		
	}
}
function linewidth($z){
	if ($z > 1000) {
		$width=5;
		$color = "#ff0000ff";
	}
	else if ($z > 400){
		$width=3;
		$color = "#ffff00ff";
	}
	else if ($z > 300) {
		$width = 2;
		$color = "#ff00ffff";
	} else {
		$width = 1;
		$color = "#ffffffff";
	}
	 $ret = sprintf("<Style> 
  <LineStyle>  
   <color>%s</color>
   <width>%d</width>
  </LineStyle> 
 </Style>",$color,$width);
	return $ret;
}
function headings($x,$y,$ax,$ay){
   $difx =   $ax - $x;
   $dify =   $ay - $y;
   $angle = atan2($difx, $dify) * 180 /M_PI;
   return $angle- 360;
}
function outfolder($data,$name){
	printf("<Folder><name>%s</name>\n",$name);
	printf("\n<Style id=\"peak\">
	 <IconStyle> <Icon> <href>http://maps.google.com/mapfiles/kml/shapes/mountains.png</href> </Icon></IconStyle>
	</Style> ");
	printf("<Style id=\"col\">
	 <IconStyle> <Icon> <href>http://maps.google.com/mapfiles/kml/pal4/icon28.png</href> </Icon></IconStyle>
	</Style> ");
		printf("<Style id=\"col2\">
	 <IconStyle> <Icon> <href>https://www.google.com/mapfiles/arrow.png</href> </Icon></IconStyle>
	</Style> ");
	for($i=0;$i<count($data);$i++){
		outpoint($data[$i],$i+1);
	}
	printf("</Folder>\n");
}		

