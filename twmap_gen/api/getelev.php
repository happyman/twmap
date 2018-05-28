<?php
// 1. 取得高度及其他資訊
// 2. 輸入形狀取得高度 or 面積資訊

require_once("../config.inc.php");

// $twDEM_path = "../db/DEM/twdtm_asterV2_30m.tif";
$loc = isset($_REQUEST['loc'])? $_REQUEST['loc']: "";
$is_shapes =isset( $_REQUEST['infoshapes'])?  $_REQUEST['infoshapes'] : "";
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
	var cur_shape = localStorage.getItem("infoshapes");
	if (cur_shape.indexOf("circle")>=0) {
		$("#data").val(localStorage.getItem("shapes"));
	}else{
		$("#data").val(cur_shape);
	}
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
 <script src="../js/papaparse.min.js"></script>
 <script src="../js/randomColor.js"></script>
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
 $run_once = 0;
	foreach($shapes as $shape){
		if ($run_once == 1) break;
		switch($shape['type']) {
			case 'circle':
			?>
			<script>
		
			// buggy: localStorage has race condition issue
			function loadCirclelist(newshapes) {
				var myshapes;
				if (newshapes !== undefined)
					myshapes = newshapes;
				else 
					myshapes = localStorage.getItem("shapes");
				
				var jsonObject = eval("(" + myshapes + ")");
				console.log("loadcircles: " + jsonObject.shapes.length);
				if (jsonObject.shapes.length == 0 ){
					console.log(localStorage.getItem("shapes"));
					
				}
	/*
	{"shapes":[{"type":"circle","color":"undefined","center":{"lat":"22.571219105273084","lon":"120.8719253540039"},"radius":"4582.94087326265"},{"type":"circle","color":"undefined","center":{"lat":"22.571219105273084","lon":"120.91707229614258"},"radius":"229.39212386740388"}]}
	*/
				var radius;
				var color;
				var center_loc;
				// clear
				$("#shapes_editor").html("");
				for (var i = 0; i < jsonObject.shapes.length; i++){
					switch (jsonObject.shapes[i].type) {
						case 'circle':
						radius = parseFloat(jsonObject.shapes[i].radius);
						color = (jsonObject.shapes[i].color == null ) ? '#FFFFE0' : jsonObject.shapes[i].color;
						center_loc = parseFloat(jsonObject.shapes[i].center.lat).toFixed(4) + "," + parseFloat(jsonObject.shapes[i].center.lon).toFixed(4);
						$("#shapes_editor").append( "圓圈" + i + "中心:<a href=#>"+ center_loc+"</a>");
						$("#shapes_editor").append(":半徑=" + "<input type=text class='radius' size=10 data-index='"+ i +"' value='" + radius.toFixed(5) + "'>顏色:<input type=color class='color' data-index='"+ i +"' value='"+  jsonObject.shapes[i].color +"'><br>" );
						
						break;
						default:
						//document.write( i + " " + jsonObject.shapes[i].type + "<br>");
						break;	
					}
					
				}
				// update data for generating kml
				$('input[name="data"]').val(myshapes);
				$('#shapes_editor a').each(function(index) {
						$(this).click(function(event) {
						event.preventDefault();
						var name=$(this).text();
						$("#tags",parent.document).val(name);
						$("#goto",parent.document).trigger('click');
					});
				});
			}  // end of loadCirclelist

			$(document).ready(function(){
				$("#shapes_editor").on("change", "input", function() {
						var i = $(this).data("index");
						var newval = $(this).val();
						var field=$(this).attr('class');
						console.log("change_" + field + "i=" + i);
						var myshapes = localStorage.getItem("shapes");
						var jsonObject = eval("(" + myshapes + ")");
						// clear all shapes
						if (jsonObject.shapes[i].type == 'circle') {
							parent.shapesMap.shapesClearAll();
							// jsonObject.shapes[i].color = '#FF0000';
							jsonObject.shapes[i][field] = newval;
							localStorage.setItem("shapes",JSON.stringify(jsonObject));
							parent.shapesMap.shapesLoad();
						}
					}
				);
				
				$('#add').click(function(event){
					 event.preventDefault();
					// validate x,y,r
					if ($.isNumeric($("#addx").val()) && $.isNumeric($("#addy").val()) && $.isNumeric($("#addradius").val()) && Math.abs($("#addx").val()) <= 180 &&  Math.abs($("#addy").val()) <= 90  ) {
						console.log("add clicked");
						var myshapes = localStorage.getItem("shapes");
						var jsonObject = eval("(" + myshapes + ")");
						var i = jsonObject.shapes.length;
						jsonObject.shapes[i] = { "type": "circle","radius": $("#addradius").val(), "center": { "lat":  $("#addy").val(),"lon": $("#addx").val() }, "color": $("#addcolor").val() };
						parent.shapesMap.shapesClearAll();
						localStorage.setItem("shapes",JSON.stringify(jsonObject));
						parent.shapesMap.shapesLoad();
						loadCirclelist();
					} else {
						alert("輸入有誤喔");
					}
					});
				$('#multiadd').click(function(event){
					var result = Papa.parse($("#circlelines").val());
					if (result.errors.length == 0 ) {
						console.log(result.data);
						var myshapes = localStorage.getItem("shapes");
						var jsonObject = eval("(" + myshapes + ")");
						var j = jsonObject.shapes.length;
						for(var i=0;i<result.data.length;i++){
							var csv = result.data[i];
							if ($.isNumeric(csv[0]) && $.isNumeric(csv[1]) &&  Math.abs(csv[1]) <= 180 &&  Math.abs(csv[0]) <= 90) {
								jsonObject.shapes[j++] = { "type": "circle","radius": csv[2], "center": { "lat":  csv[0],"lon": csv[1]}, "color": (csv[3]=="")?randomColor():csv[3] };
							}				
						}
						// all loaded
						parent.shapesMap.shapesClearAll();
						localStorage.setItem("shapes",JSON.stringify(jsonObject));
						parent.shapesMap.shapesLoad();
						loadCirclelist();
					}
				});
				loadCirclelist();			
			});
			</script>
			<?php
			// print_r($shapes);
			echo "<div id=shapes_editor></div>";
			?>
			<form id="addcircle">
			新圓圈中心:<br>緯度:<input type=text size=12 id="addy">經度:<input type=text size=12 id="addx">,半徑=<input type=text id="addradius" size=10 value=1000>公尺 
			顏色:<input type=color id="addcolor">
			<button type="button" id="add">新增</button><br>
			或用 csv 輸入: lat,lon,radius,color (#646464)<br>
				<textarea id="circlelines" rows="4" cols="50">
				</textarea>
				<button type="button" id="multiadd">多行新增</button><br>
			</form>
			
			<button onclick="loadCirclelist();">重載</button>
			<?php
			downloadform();
			//echo "<h2>圓圈圈處理</h2>";
			// printf( "圓心座標:%.06f %.06f 半徑: %.06fM\n",$shapes[0]['center']['lon'],$shapes[0]['center']['lat'],$shapes[0]['radius'] );
			// downloadform();
			$run_once = 1;
			//printf ("</ul><br>面積: %.04f Km^2", $result[0]['st_area'] / 1000000 );
			// display_area(pow($shapes[0]['radius'], 2) * pi() );
				break;
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
				downloadform();
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
				echo "<p><h2>這是一個多邊形 polygon</h2>";
				echo "端點座標<br><textarea style='width:200px;height:70px;border:2px #ccc solid;overflow:hidden'>";
				foreach($shape['paths'][0]['path'] as $pt) {
					$pts[] = sprintf("%f %f",$pt['lon'],$pt['lat']);
					printf("%.06f %.06f\n",$pt['lon'],$pt['lat']);
				}
				echo "</textarea>";
					downloadform();
				$pts[] = $pts[0];
				$wkt_str = sprintf("POLYGON((%s))",implode(",",$pts));
				$result = get_AREA($wkt_str);
				//printf ("</ul><br>面積: %.04f Km^2", $result[0]['st_area'] / 1000000 );
				display_area($result[0]['st_area']);
				break;
			case 'rectangle':
				$ne = $shape['bounds']['northEast'];
				$sw = $shape['bounds']['southWest'];
				echo "<p><h2>這是一個長方形 rectangle </h2>";
				echo "<br>東北座標:" . coord($ne); 
				echo "<br>西南座標:" . coord($sw);
					downloadform();
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
	$unit_n = array("公頃","平方公里","甲","分","英畝","公畝","坪","平方公尺","平方呎");
	$unit_a = array(0.0001,	0.000001,0.000103,0.0000103,0.000247,0.01,0.3025,1,10.7639);
	echo "<div id=tabs><ul>";
	for($i=0;$i<count($unit_n);$i++){
		printf("<li><a href=#tab_%d>%s</a></li>",$i,$unit_n[$i]);
	}
	echo "</ul>";
	for($i=0;$i<count($unit_n);$i++){
		printf("<div id='tab_%d'>面積: %.02f %s",$i,$sqm * $unit_a[$i], $unit_n[$i]);
		if ($unit_a[$i] != 1 ) 
			printf("<br>註: 1 平方公尺 = %f %s</div>",$unit_a[$i], $unit_n[$i]);
	}
	echo "</div>";
}
function downloadform() {
	printf("<form id='exportform' action='shape2track.php' method='post'><input type=hidden name='data' value='%s'>",$_POST['data']);
	?>
	<input type=radio name=type value='gpx' >GPX </input>
	<input type=radio name=type value='kml' checked>KML </input>
	<button type="submit">下載</button>
	</form>
	<?php
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
