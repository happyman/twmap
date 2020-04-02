<?php
// v8
// observation
// https://www.cwb.gov.tw/V8/C/P/Rainfall/Rainfall_QZJ.html
// 昨日: https://www.cwb.gov.tw/Data/rainfall/2020-04-02_0000.QZJ8.jpg (今日凌晨)
// 前日: https://www.cwb.gov.tw/Data/rainfall/2020-04-01_0000.QZJ8.jpg
// 今日: https://www.cwb.gov.tw/Data/rainfall/2020-04-02_1600.QZJ8.jpg (目前時間)
//  forecast 
// https://www.cwb.gov.tw/V8/C/W/analysis.html
// 12hr: https://www.cwb.gov.tw/Data/fcst_img/QPF_ChFcstPrecip_12_12.png
// 24hr: https://www.cwb.gov.tw/Data/fcst_img/QPF_ChFcstPrecip_12_24.png
// v7
// 產生 cwb 降雨 kml'
//http://www.cwb.gov.tw/V7/forecast/fcst/QPF.htm
// forecast 
//   * http://www.cwb.gov.tw/V7/forecast/fcst/Data/QPF_ChFcstPrecip12.jpg 12hr
//   * http://www.cwb.gov.tw/V7/forecast/fcst/Data/QPF_ChFcstPrecip24.jpg 24hr
//   observation
//   * http://www.cwb.gov.tw/V7/observe/rainfall/Data/hkb29000.jpg -2day (-1day) 
//   * http://www.cwb.gov.tw/V7/observe/rainfall/Data/hkb30000.jpg -1day (today)
//   * http://www.cwb.gov.tw/V7/observe/rainfall/Data/hkb.jpg now, 會自動 link 到相對應的時間
//    hk 結束時間 hka13103 => 1/30 10:30 
//   http://www.cwb.gov.tw/V7/google/46755_map.htm, embed  http://www.cwb.gov.tw/wwwgis/kml/newcwbobs_gmap.kml
//

function kmlhead() {
header('Content-type: application/vnd.google-earth.kml+xml');
echo '<?xml version="1.0" encoding="UTF-8"?>';
echo "\n";
?>
<kml xmlns="http://www.opengis.net/kml/2.2" xmlns:gx="http://www.google.com/kml/ext/2.2" xmlns:kml="http://www.opengis.net/kml/2.2" xmlns:atom="http://www.w3.org/2005/Atom">
<?php
}
function outkml($name,$url,$type='forecast',$opacity=0.5) {
kmlhead();
if ($type  == 'observation')
	//show_obs();
?>
<GroundOverlay>
        <name><?php echo $name; ?></name>
        <color><?php echo dechex($opacity*255);?>ffffff</color>
        <Icon>
                <href><?php echo $url;?></href>
                <viewBoundScale>0.75</viewBoundScale>
        </Icon>
        <LatLonBox>
<?php 
if ($type=='forecast') {
?>
	<LatLonBox>
		<north>25.77516359334955</north>
		<south>21.81582388299667</south>
		<east>122.3146176402033</east>
		<west>118.839658837004</west>
	</LatLonBox>
<?php
} else {
?>
	<LatLonBox>
		<north>25.92518592100197</north>
		<south>21.51995816401952</south>
		<east>123.6015201651759</east>
		<west>119.1778952067079</west>
	</LatLonBox>
				
<?php
}
?>

        </LatLonBox>
</GroundOverlay>
<?php
kmlfoot();
}
function kmlfoot() {
echo "</kml>";
}
function show_obs(){
	$random = date("YmdHi");
	$url = "http://www.cwb.gov.tw/wwwgis/kml/newcwbobs_gmap.kml";
	$kml = file_get_contents($url . "?".$random);
	// dirty hack
	$kml = str_replace("<scale>3</scale>","<scale>1</scale>",$kml);
	$kml = str_replace("<a href=/V7/observe/real/NewObs.htm","<a href=data/obstation.php",$kml);
	$mat = preg_split("/<Document>/",str_replace("</Document>","<Document>",$kml));
	echo "<!-- from  $url -->\n";
	echo $mat[1];
	echo "\n";
}

$opacity=0.5;
$term=$_GET['term'];
switch($term) {
		case 'f12h':
			$name = '定量降水預報1';
			$url =  'https://www.cwb.gov.tw/Data/fcst_img/QPF_ChFcstPrecip_12_12.png';
			$type = 'forecast';
		break;
		case 'f24h':
			$name = '定量降水預報2';
			$url =  'https://www.cwb.gov.tw/Data/fcst_img/QPF_ChFcstPrecip_12_24.png';
			$type = 'forecast';
			//$type = 'observation';
		break;
		case 'o2d':
			$name = '前日雨量';
			//$url = sprintf('http://www.cwb.gov.tw/V7/observe/rainfall/Data/hkb%s000.jpg', date("d",strtotime("-1 day")));
			$url=sprintf("https://www.cwb.gov.tw/Data/rainfall/%s_0000.QZJ8.jpg",date("Y-m-d",strtotime("-1 day")));
			$type = 'observation';
		break;
		case 'o1d':
			$name = '昨日雨量';
			//$url = sprintf('http://www.cwb.gov.tw/V7/observe/rainfall/Data/hkb%s000.jpg', date("d",strtotime("today")));
			$url=sprintf("https://www.cwb.gov.tw/Data/rainfall/%s_0000.QZJ8.jpg",date("Y-m-d",strtotime("today")));
			$type = 'observation';
		break;
		case 'now':
			$name = '今日累積雨量';
			$half = (date("i")<30)? 0 : 3;
			$url=sprintf("https://www.cwb.gov.tw/Data/rainfall/%s_%d%d0.QZJ8.jpg",date("Y-m-d",strtotime("today")),date("H"),$half);
			//$url = sprintf('http://www.cwb.gov.tw/V7/observe/rainfall/Data/hkb%s%d.jpg', date("dG"), $half);
			$type = 'observation';
		break;
		default:
			header("HTTP/1.0 404 Not Found");
			exit(0);
		break;
}
outkml($name,$url,$type,$opacity);
