<?php
// v8
// 改為氣象署 cwb -> cwa
// https://www.cwa.gov.tw/V8/C/P/Rainfall/Rainfall_QZJ.html
// 昨日: https://www.cwa.gov.tw/Data/rainfall/2020-04-02_0000.QZJ8.jpg (今日凌晨)
// 前日: https://www.cwa.gov.tw/Data/rainfall/2020-04-01_0000.QZJ8.jpg
// 今日: https://www.cwa.gov.tw/Data/rainfall/2020-04-02_1600.QZJ8.jpg (目前時間)
//  forecast 
// https://www.cwa.gov.tw/V8/C/W/analysis.html
// 12hr: https://www.cwa.gov.tw/Data/fcst_img/QPF_ChFcstPrecip_12_12.png
// 24hr: https://www.cwa.gov.tw/Data/fcst_img/QPF_ChFcstPrecip_12_24.png
// 36hr: https://www.cwa.gov.tw/Data/fcst_img/QPF_ChFcstPrecip_12_36.png
// 48hr: https://www.cwa.gov.tw/Data/fcst_img/QPF_ChFcstPrecip_12_48.png
// v7
// 產生降雨 kml'
//

date_default_timezone_set('Asia/Taipei');

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
	<north>25.8697383</north>
	<south>21.6837340</south>
	<east>122.3905090</east>
	<west>118.9466449</west>
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


$opacity=0.5;
$term=$_GET['term'];
switch($term) {
		case 'f12h':
			$name = '定量降水預報1';
			$url =  'https://www.cwa.gov.tw/Data/fcst_img/QPF_ChFcstPrecip_12_12.png';
			$type = 'forecast';
		break;
		case 'f24h':
			$name = '定量降水預報2';
			$url =  'https://www.cwa.gov.tw/Data/fcst_img/QPF_ChFcstPrecip_12_24.png';
			$type = 'forecast';
		break;
		case 'f36h':
			$name = '定量降水預報3';
			$url =  'https://www.cwa.gov.tw/Data/fcst_img/QPF_ChFcstPrecip_12_36.png';
			$type = 'forecast';
		break;
		case 'f48h':
			$name = '定量降水預報4';
			$url =  'https://www.cwa.gov.tw/Data/fcst_img/QPF_ChFcstPrecip_12_48.png';
			$type = 'forecast';
		break;
		case 'o2d':
			$name = '前日雨量';
			//$url = sprintf('http://www.cwb.gov.tw/V7/observe/rainfall/Data/hkb%s000.jpg', date("d",strtotime("-1 day")));
			$url=sprintf("https://www.cwa.gov.tw/Data/rainfall/%s_0000.QZJ8.jpg",date("Y-m-d",strtotime("-1 day")));
			$type = 'observation';
		break;
		case 'o1d':
			$name = '昨日雨量';
			//$url = sprintf('http://www.cwb.gov.tw/V7/observe/rainfall/Data/hkb%s000.jpg', date("d",strtotime("today")));
			$url=sprintf("https://www.cwa.gov.tw/Data/rainfall/%s_0000.QZJ8.jpg",date("Y-m-d",strtotime("today")));
			$type = 'observation';
		break;
		case 'now':
			$name = '今日累積雨量';
			$half = (date("i")<30)? 0 : 3;
			$url=sprintf("https://www.cwa.gov.tw/Data/rainfall/%s_%02d%d0.QZJ8.jpg",date("Y-m-d",strtotime("today")),date("H"),$half);
			//$url = sprintf('http://www.cwb.gov.tw/V7/observe/rainfall/Data/hkb%s%d.jpg', date("dG"), $half);
			$type = 'observation';
		break;
		default:
			header("HTTP/1.0 404 Not Found");
			exit(0);
		break;
}
outkml($name,$url,$type,$opacity);
