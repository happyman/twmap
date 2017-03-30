<?php

require_once("../config.inc.php");

// $twDEM_path = "../db/DEM/twdtm_asterV2_30m.tif";
$loc = $_REQUEST['loc'];
$is_shapes = $_REQUEST['infoshapes'];
if (empty($loc) && empty($is_shapes)) {
	ajaxerr("insufficent parameters");
}
if (!empty($is_shapes)){
	
	if ($is_shapes  == 1  && empty($_POST['data'])) {
?>
<html>
 <script src="https://code.jquery.com/jquery-2.1.4.min.js"></script>
 <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
  				
<form action="getelev.php" method="post" name="shapeform">
<input name="infoshapes" value="2" type="hidden">
<input name="data" id="data" value="" type="hidden">
</form>
<script type="text/javascript">
window.onload = function() {
	// var data = localStorage.getItem("infoshapes");
   $("#data").val(localStorage.getItem("infoshapes"));
   document.forms["shapeform"].submit();
   
}
</script>
	
<?php
exit();
	}
	//print_r($_POST);
	$data = json_decode($_POST['data'], true);

	$shapes = $data['shapes'];
	?>
	<html>
	<head>
    <link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
  <script src="https://code.jquery.com/jquery-1.12.4.js"></script>
  <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
 <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
 <script>
  google.charts.load('current', {packages: ['corechart']});
    $( function() {
    $( "#tabs" ).tabs({ active: 1 });
  } );
  </script>
  </head><body>
  <hr>
<p>
 <?php
	foreach($shapes as $shape){
		switch($shape['type']) {
			case 'polyline':
				$path = $shape['path'];
				// print_r($path);
				echo "<h2>這是一條 polyline </h2>";
				echo "端點座標<br><textarea style='width:200px;height:70px;border:2px #ccc solid;overflow:hidden'>";
				foreach($path as $pt){
					$pts[] = array($pt['lon'],$pt['lat']);
					$pts1[] = sprintf("%f %f",$pt['lon'],$pt['lat']);
					printf("%.06f %.06f\n",$pt['lon'],$pt['lat']);
				}
				echo "</textarea>\n";
				$sum=0;
				for($i=1;$i<count($pts);$i++){
					$sum+=get_distance($pts[$i-1],$pts[$i]);
				}
				printf("<br>距離: %.02f 公尺",$sum);
				$wkt_str = sprintf("LINESTRING(%s)",implode(",",$pts1));
				
				list ($status, $result) = get_distance2($wkt_str, twDEM_path);
				if ($status === true) {
					distance_display($result);
					printf("<br>高度圖");
				}
				?>
				<div id="chart_div"></div>
				<script>
				google.charts.setOnLoadCallback(drawBackgroundColor);

function drawBackgroundColor() {
      var data = new google.visualization.DataTable();
      data.addColumn('number', 'X');
      data.addColumn('number', '高度');

      data.addRows([
<?php
		echo $result['chart'];
?>
      ]);

      var options = {
        hAxis: {
          title: '距離(M)'
        },
        vAxis: {
          title: '海拔高度(M)'
        },
        backgroundColor: '#ffffff'
      };

      var chart = new google.visualization.LineChart(document.getElementById('chart_div'));
      chart.draw(data, options);
    }</script>
	</body>
	</html>
				<?php
				break;
			case 'polygon':
				//print_r($shape);
				echo "<hr><p><h1>這是一個多邊形 polygon</h1>";
				echo "端點座標<br><textarea style='width:200px;height:70px;border:2px #ccc solid;overflow:hidden'>";
				foreach($shape['paths'][0]['path'] as $pt) {
					$pts[] = sprintf("%f %f",$pt['lon'],$pt['lat']);
					printf("%.06f %.06f\n",$pt['lon'],$pt['lat']);
				}
				echo "</textarea>";
				$pts[] = $pts[0];
				$wkt_str = sprintf("POLYGON((%s))",implode(",",$pts));
				$result = get_AREA($wkt_str);
				//printf ("</ul><br>面積: %.04f Km^2", $result[0]['st_area'] / 1000000 );
				display_area($result[0]['st_area']);
				break;
			case 'rectangle':
				$ne = $shape['bounds']['northEast'];
				$sw = $shape['bounds']['southWest'];
				echo "<hr><p><h1>這是一個長方形 rectangle </h1>";
				echo "<br>東北座標:" . coord($ne); 
				echo "<br>西南座標:" . coord($sw);
				$pts[] = sprintf("%f %f",$sw['lon'],$ne['lat']);
				$pts[] = sprintf("%f %f",$ne['lon'],$ne['lat']);
				$pts[] = sprintf("%f %f",$ne['lon'],$sw['lat']);
				$pts[] = sprintf("%f %f",$sw['lon'],$sw['lat']);
				$pts[] = $pts[0];
				// print_r($pts);
				$wkt_str = sprintf("POLYGON((%s))",implode(",",$pts));
				$result = get_AREA($wkt_str);

				// printf ("<br>面積: %.04f Km^2", $result[0]['st_area'] / 1000000 );
				display_area($result[0]['st_area']);
				break;
			default:
			echo "<div id=tabs></div>";
			echo "我的老天鵝~~沒有資訊喔!";
		}
	}
	exit;
}
function coord($p){
	return sprintf("%.06f,%.06f",$p['lat'],$p['lon']);
}
/*
楊南郡的八通關古道東段調查研究報告的附錄中
公里          公尺    華里          日里      日町    日間

1             1000    1.736         0.255     9.167   550.03
0.001         1       1.736*10(-3)  0.000255  0.0092  0.55
0.576         576     1             0.147     5.28    316.8
3.927         3927    6.818         1         36      2160
0.109         109.09  0.189         0.0278    1       60
1.818*10(-3)  1.818   0.003         0.00046   0.0167  1

華制：
中國舊制長度單位，也有人直接1公里=2華里
ex：明代築的萬里長城13萬里約等於7300公里
1里=180丈=1800尺

日制：
日本舊制長度單位
1里=36町=2160間=12960尺
1台尺=1日尺=0.30303公尺
1華尺=0.32公尺
*/
function distance_display($result){
	$unit_a = array("km" => 0.001, "m" => 1, "hm"=> 1.376e-3, "jkm"=> 0.000255, "jt"=> 0.09167, "jj"=> 0.55003, "tm"=> 10/33);
	$unit_n = array("km" => "公里", "m" => "公尺", "hm"=> "華里", "jkm"=> "日里", "jt"=> "日町", "jj"=> "日間","tm"=> "台尺");
	echo "<div id=tabs>";
		echo "<ul>";
		foreach($unit_a as $key => $val){
			printf("<li><a href='#%s_unit'>%s</a></li>\n",$key, $unit_n[$key]);
		}
	echo "</ul>";
	foreach($unit_a as $unit =>$val){
		printf("<div id='%s_unit'>",$unit);
			printf("<br>線段取樣距離 %d 公尺",$result['step']);
					printf("<br>距離: %.02f %s",$result['d'] * $unit_a[$unit], $unit_n[$unit]);
					printf("<br>總距離(考慮高程): %d %s",$result['d1']* $unit_a[$unit], $unit_n[$unit]);
					if ($result['outofrange']==1)
						printf("<br><b>超出 DEM 資料範圍，以下資料不正確</b>");
					printf("<br>最大高度: %.02f %s",$result['maxele']* $unit_a[$unit], $unit_n[$unit]);
					printf("<br>最小高度: %.02f %s",$result['minele']* $unit_a[$unit], $unit_n[$unit]);
					printf("<br>平均高度: %.02f %s",$result['avgele']* $unit_a[$unit], $unit_n[$unit]);
					printf("<br>總爬升: %.02f %s",$result['ascent']* $unit_a[$unit], $unit_n[$unit]);
					printf("<br>總下降: %.02f %s",$result['descent']* $unit_a[$unit], $unit_n[$unit]);
					if ($unit != 'm') 
						printf("<br>備註: 1 公尺 = %s	 %s",$unit_a[$unit],$unit_n[$unit]);
					printf("</div>\n");
	}
	echo "</div>";
}
/*
地籍測量常用面積單位換算：

1平方公尺 = 0.3025坪
1坪 = 3.3058平方公尺
1公頃 = 10000平方公尺 = 3025坪 = 1.03102甲
1甲 = 10分 = 2934坪 = 0.96992公頃
1平方公里 = 100公頃
*/
function display_area($sqm){
	$unit_n = array("公頃","平方公里","甲","英畝","公畝","坪","平方公尺","平方呎");
	$unit_a = array(0.0001,	0.00001,0.000103,0.000247,0.01,0.3025,1,10.7639);
	echo "<div id=tabs><ul>";
	for($i=0;$i<count($unit_n);$i++){
		printf("<li><a href=#tab_%d>%s</a></li>",$i,$unit_n[$i]);
	}
	echo "</ul>";
	for($i=0;$i<count($unit_n);$i++){
		printf("<div id='tab_%d'>面積: %.02f %s<br>註: 1 平方公尺 = %f %s</div>",$i,$sqm * $unit_a[$i], $unit_n[$i],$unit_a[$i], $unit_n[$i]);
	}
	echo "</div>";
}
// 以下為取得點位資訊的部分

list($lat,$lon)=explode(",",$_REQUEST['loc']);
// 取得高度
$ele = get_elev(twDEM_path, $lat, $lon, 1);
$data['elevation'] = $ele;
// 取得行政區
$towns = get_administration($lon,$lat, "town");
if ($towns || count($towns) > 0 ) {
	foreach($towns as $town) {
		$town_name[] = sprintf("%s%s%s",$town['C_Name'],$town['T_Name'],($town['permit']=='t')? "(入山)" : "" );
	}
	$data["admin"] = implode(",",$town_name);
	$data["weather_forcast_url"] = sprintf("http://www.cwb.gov.tw/V7/forecast/town368/towns/%s.htm?type=Weather&time=7Day",$town['Town_ID']);
	if (!empty($town['cwb_tribe_code'])) {
		$data["tribe_weather"] = get_tribe_weather($town['cwb_tribe_code']);
	}
}
// 取得是否在國家公園內
$nps = get_administration($lon,$lat, "nature_park");
$nrs = get_administration($lon,$lat, "nature_reserve");
$np_name = array();
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
// 取得原鄉天氣的預報網址, depends on CWB
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
