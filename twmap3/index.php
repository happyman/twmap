<?php
if(!ob_start("ob_gzhandler")) ob_start();
session_start();
$ver = trim(file_get_contents("VERSION"));
?>
<!DOCTYPE html>
<html>
<head>
	<meta http-equiv="content-type" content="text/html; charset=UTF-8"/>
	<meta name="apple-mobile-web-app-capable" content="yes" />
	<meta name = "viewport" content = "width=device-width, initial-scale=1.0, user-scalable=0">

	<title>地圖瀏覽器 v<?=$ver?></title>
	<!--<script src="//maps.google.com/maps/api/js?sensor=true&v=3"></script> -->
	<script src="//maps.googleapis.com/maps/api/js?v=3&sensor=false&libraries=geometry"></script>
       <!--<script  src="js/jquery-1.7.1.min.js"></script>
       <script  src="js/jquery-ui-1.8.17.custom.min.js"></script>
-->
	        <script src="https://code.jquery.com/jquery-2.1.4.min.js"></script>
                <script src="https://code.jquery.com/ui/1.11.4/jquery-ui.min.js"></script>
	   <link type="text/css" href="https://code.jquery.com/ui/1.11.4/themes/flick/jquery-ui.css" rel="Stylesheet" />  
<!-- build:js js/vender.js -->
	<script  src="js/ui.dropdownchecklist.js" charset="utf-8"></script>
	<script  src='js/proj4js-combined.js'></script>
	<script  src="js/keydragzoom-2.0.6.js"></script>
	<script  src="js/infobox.js"></script>
	<script  src='js/ExtDraggableObject.js'></script>
	<script  src="js/label.js"></script>
	<script  src="js/oms.min.js"></script>
	<script  src='js/jquery.blockUI.js'></script>
	<script  src='js/jquery.meerkat.1.3.js'></script>
	<script  src="js/iframeResizer.contentWindow.min.js"></script>
	<script  src="js/geolocationmarker.js"></script>
	<script  src="js/v3_ll_grat.js"></script>
	<script  src="js/jquery-autoGrowInput.js" charset="utf-8"></script>
	<script  src="js/jquery.geolocation.js" charset="utf-8"></script>
	<script  src="js/jquery.cookie.js"></script>
	<script  src="js/jqbrowser.js"></script>
	<script  src="js/ProjectedOverlay.js"></script>
	<script  src="js/geoxml3.js"></script>
	<script  src="js/functions.js"></script>
	<script  src="js/javascript.util.min.js"></script>
	<script  src="js/jsts.min.js"></script>
	<script  src="js/triangle.js"></script>
<!-- endbuild -->
<!--
	<link rel="stylesheet" type="text/css" href="css/sunny/jquery-ui-1.8.17.custom.css" />
-->
<!-- build:css css/twmap3.css -->
	<link rel="stylesheet" type="text/css" href="css/twmap3_main.css" />
<!-- endbuild -->
<script>
<?php
require_once("lib/functions.inc.php");
// 檢查是否登入
list ($st, $info) = login_info();
if ($st === true) {
	echo "var login_role = 1;\n";
	printf("var login_uid = %d\n",$info['uid']);
} else {
	echo "var login_role = 0;\n";
}
printf("var getkml_url = '%s';\n",$CONFIG['getkml_url']);
printf("var geocodercache_url = '%s';\n",$CONFIG['geocodercache_url']);
printf("var pointdata_url = '%s';\n",$CONFIG['pointdata_url']);
printf("var pointdata_admin_url = '%s';\n",$CONFIG['pointdata_admin_url']);
printf("var get_waypoints_url = '%s';\n",$CONFIG['get_waypoints_url']);
printf("var get_elev_url = '%s';\n",$CONFIG['get_elev_url']);
printf("var viewshed_url = '%s';\n",$CONFIG['viewshed_url']);
printf("var callmake_url = '%s';\n", $CONFIG['site_twmap_html_root'] . "main.php?tab=0&");
?>
</script>
	</head>
	<body>
	<div id="loading">
	歡迎使用 地圖瀏覽器 v<?=$ver?><br>
  		<img src="/twmap/icons/twmap3.jpg"><p>
  		<img src="img/loading20x20.gif">   載入中...
	</div>
		<div id="locContainer">
			<div id="loc"></div>
		</div>
		<div id="opContainer" >
			<div id="less" title="背景清楚一點">
				&nbsp;-&nbsp;
			</div>
			<div id="opSlider">
				&nbsp;透明度 (<span id='opv'></span>)%
				<div id="op" title="拉我調整透明度">&nbsp;</div>
			</div>
			<div id="more" title="前景清楚一點">
				&nbsp;+&nbsp;
			</div>
			<button type="button" id="changemap" name="changemap" title="切換一版與三版地形圖" >經建三</button>
			 <div id='CGNAME'>
				     <select title="切換路圖" name="road" id="changegname">
						     <option value="GoogleNames">Google道路</option><option value="NLSCNames">nlsc道路</option><option value="None">無路圖</option></select>
			 </div>
		</div>

		<div id="map_canvas"  data-tap-disabled="true"></div>

		<div id="title" class="title">
		  <form id="gotoform" name="gotoform">
			<span id="about" title="關於" class="ui-state-default ui-corner-all" > 地圖瀏覽器 v<?=$ver?></span> <span id="search_text"><img src='img/loading20x20.gif' /></span> <input id="tags" type="text" class="ui-corner-all"  title="輸入山頭名稱或地標,或者座標" disabled>
			<button type=button class="ui-state-default ui-corner-all" id="goto" title="搜尋並定位">到</button>
			<button id="kml_sw" class="ui-state-default ui-corner-all" title="山友登山軌跡(支援z=13到18)" type=button>行跡</button>
			<button id="label_sw" class="ui-state-default ui-corner-all" title="三角點名稱"  type=button>標籤</button>
			<button id="delaunay_sw" class="ui-state-default ui-corner-all disable" title="三角點連線"  type=button>連線</button>
			<button id="marker_reload" class="ui-state-default ui-corner-all" type=button>重載</button>
			<select id="marker_sw_select" multiple="multiple">
      		<option selected="selected" value="a">全部</option>
			<option selected="selected" value="1">一等</option>
			<option selected="selected" value="2">二等</option>
			<option selected="selected" value="3">三等</option>
			<option selected="selected" value="4">森林</option>
      		<option selected="selected" value="5">百岳</option>
      		<option selected="selected" value="6">小百岳</option>
      		<option selected="selected" value="9">百名山</option>
      		<option selected="selected" value="8">溫泉</option>
			<option selected="selected" value="7">其他</option>
			</select>
			
			</form>
		</div>
		<div id="params"></div>
		<button type="button" id="setup" name="setup" style='display:none' class="ui-state-default ui-corner-all">設定</button>
		<div id="CGRID">
		<select title="切換 Grid" name="grid" id="changegrid" class="ui-corner-all">
		<option value="TWD67" selected >TWD67 Grid</option><option value="TWD67PH">TWD67澎</option><option value="WGS84">經緯度</option><option value="None">無Grid</option>
		<option value="TWD67_EXT">TWD67 EXT</option>
		</select>
		</div>
		<div id="FORECAST">
		<select title="雨量" name="rainfall" id="rainfall" class="ui-corner-all">
		<option value="none" selected >雨量圖</option>
		<option value="o2d" >前日</option>
		<option value="o1d" >昨日</option>
		<option value="now" >今日</option>
		<option value="f12h" >未來12h</option>
		<option value="f24h" >未來24h</option>
		</select>
		</div>
		<div id="MCOVERAGE">
		<select title="訊號" mame="mcover" id="mcover" class="ui-corner-all">
		<option value="none" selected>訊號</option>
		<option value="cht2G">cht2G</option>
		<option value="cht3G">cht3G</option>
		<option value="cht">cht4G</option>
		<option value="twn2G">twn2G</option>
		<option value="twn3G">twn3G</option>
		<option value="twn">twn4G</option>
		<option value="fet2G">fet2G</option>
		<option value="fet3G">fet3G</option>
		<option value="fet">fet4G</option>
		</select>
		</div>

		<div id="inputtitleform" style="display:none">
			<br>請輸入地圖標題: <br><br><input id="inputtitle" type="text" size="2" />
			<br>
			<input type="button" id='inputtitlebtn' value="送出" />
			<input type="button" id='inputtitlebtn2' value="取消" />
		</div>
		<div id="mobile_setup">
			<a href="#" class="close-meerkat2">close</a>
		</div>
	<div id="meerkat">
	<a href="#" class="close-meerkat">close</a>
	<div id="meerkat-content">
				Here we go :*
		</div>
		<div id="copylink" title="複製連結">
		<input id="copylinkurl"><br>
			<button type="button" id="copylinkurlgo">Go</button>
			<button type="button" id="copylinkurlshort">Shorten</button>
		</div>
</div>
<script>
	var _gaq = _gaq || [];
	_gaq.push(['_setAccount', 'UA-19949015-1']);
	_gaq.push(['_trackPageview']);
	// wait for google maps initialized
	$(function() {
	// 初始
	$.getScript( "js/main.js?ts=<?php echo time();?>" ).done(function() {
		initialize();
		$(window).resize(function() {
			resizeMap();
		});
		$('input').autoGrowInput({
			comfortZone: 20,
			maxWidth: 2000
		});
	});
	var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
	ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
	var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
	if (window.location.href != window.top.location.href) {
		$('#openwin').html('<a href="/~happyman/twmap3/" target=_top>獨立視窗</a>');
	}
});

</script>
	</body>
</html>
