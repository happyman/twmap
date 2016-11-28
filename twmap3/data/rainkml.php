<?php

// 產生 cwb 降雨 kml
// forecast 
//   * http://www.cwb.gov.tw/V7/forecast/fcst/Data/QPF_ChFcstPrecip12.jpg 12hr
//   * http://www.cwb.gov.tw/V7/forecast/fcst/Data/QPF_ChFcstPrecip24.jpg 24hr
//   observation
//   * http://www.cwb.gov.tw/V7/observe/rainfall/Data/hk129000.jpg -2day (-1day) 
//   * http://www.cwb.gov.tw/V7/observe/rainfall/Data/hk130000.jpg -1day (today)
//   * http://www.cwb.gov.tw/V7/observe/rainfall/Data/hk.jpg now, 會自動 link 到相對應的時間
//   猜測是 hk 結束時間 hk130103 => 1/30 10:30 

function outkml($name,$url,$type='forecast',$opacity=0.5) {
header('Content-type: application/vnd.google-earth.kml+xml');
echo '<?xml version="1.0" encoding="UTF-8"?>';
echo "\n";
?>
<kml xmlns="http://www.opengis.net/kml/2.2" xmlns:gx="http://www.google.com/kml/ext/2.2" xmlns:kml="http://www.opengis.net/kml/2.2" xmlns:atom="http://www.w3.org/2005/Atom">
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
                <north>25.87155034385115</north>
                <south>21.5903425805556</south>
                <east>122.5509623333182</east>
                <west>118.7021084512983</west>
                <rotation>-0.08483965398068617</rotation>
<?php
} else {
?>
                <north>25.86751629674119</north>
                <south>21.87007933951254</south>
                <east>123.4476480835544</east>
                <west>119.3977113750858</west>
<?php
}
?>

        </LatLonBox>
</GroundOverlay>
</kml>
<?php
}

$opacity=0.5;
$term=$_GET['term'];
switch($term) {
		case 'f12h':
			$name = '定量降水預報1';
			$url =  'http://www.cwb.gov.tw/V7/forecast/fcst/Data/QPF_ChFcstPrecip12.jpg';
			$type = 'forecast';
		break;
		case 'f24h':
			$name = '定量降水預報2';
			$url =  'http://www.cwb.gov.tw/V7/forecast/fcst/Data/QPF_ChFcstPrecip24.jpg';
			$type = 'forecast';
			//$type = 'observation';
		break;
		case 'o2d':
			$name = '前日雨量';
			$url = sprintf('http://www.cwb.gov.tw/V7/observe/rainfall/Data/hkb%s000.jpg', date("d",strtotime("-1 day")));
			$type = 'observation';
		break;
		case 'o1d':
			$name = '前日雨量';
			$url = sprintf('http://www.cwb.gov.tw/V7/observe/rainfall/Data/hkb%s000.jpg', date("d",strtotime("today")));
			$type = 'observation';
		break;
		case 'now':
			$name = '今日累積雨量';
			$url = 'http://www.cwb.gov.tw/V7/observe/rainfall/Data/hk.jpg';
			$type = 'observation';
		break;
		default:
			header("HTTP/1.0 404 Not Found");
			exit(0);
		break;
}
outkml($name,$url,$type,$opacity);
