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
	<script  src="//maps.google.com/maps/api/js?sensor=false"></script>
       <!--<script  src="js/jquery-1.7.1.min.js"></script>
       <script  src="js/jquery-ui-1.8.17.custom.min.js"></script>
-->
	        <script src="https://code.jquery.com/jquery-2.1.4.min.js"></script>
                <script src="https://code.jquery.com/ui/1.11.4/jquery-ui.min.js"></script>
	   <link type="text/css" href="https://code.jquery.com/ui/1.11.4/themes/sunny/jquery-ui.css" rel="Stylesheet" />  
	<script  src="js/ui.dropdownchecklist.js" charset="utf-8"></script>
<!-- build:js js/vender.js -->
	<script  src='js/proj4js-combined.js'></script>
	<script  src="js/keydragzoom-2.0.6.js"></script>
	<script  src="js/infobox.js"></script>
	<script  src='js/ExtDraggableObject.js'></script>
	<script  src="js/label.js"></script>
	<script  src="js/oms.min.js"></script>
	<script  src='js/jquery.blockUI.js'></script>
	<script  src='js/jquery.meerkat.1.3.min.js'></script>
	<script  src="js/iframeResizer.contentWindow.min.js"></script>
	<script  src="js/geolocationmarker.js"></script>
	<script  src="js/v3_ll_grat.js"></script>
	<script  src="js/jquery-autoGrowInput.js" charset="utf-8"></script>
	<script  src="js/jquery.geolocation.js" charset="utf-8"></script>
	<script  src="js/functions.js"></script>
	<script  src="js/jquery.cookie.js"></script>
	<script  src="js/geoxml3.js"></script>
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
if (is_admin())
	echo "var admin_role = 1;\n";
else {
	// prepare login
	$_SESSION['redirto'] = $_SERVER["REQUEST_URI"];
	echo "var admin_role = 0;\n";
}
printf("var getkml_url = '%s';\n",$CONFIG['getkml_url']);
printf("var geocodercache_url = '%s';\n",$CONFIG['geocodercache_url']);
printf("var pointdata_url = '%s';\n",$CONFIG['pointdata_url']);
printf("var get_waypoints_url = '%s';",$CONFIG['get_waypoints_url']);
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
		<button type="button" id="generate" name="generate" title="將參數傳送到地圖產生器" class="ui-state-default ui-corner-all" >產生</button>
		<button type="button" id="setup" name="setup" style='display:none' class="ui-state-default ui-corner-all">設定</button>
		<div id="CGRID">
		<select title="切換 Grid" name="grid" id="changegrid" class="ui-corner-all">
		<option value="TWD67" selected >TWD67 Grid</option><option value="TWD67PH">TWD67澎</option><option value="WGS84">經緯度</option><option value="None">無Grid</option>
		<option value="TWD67_EXT">TWD67 EXT</option>
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
		<div id="footer" title="About"  name="footer">
			<div id="openwin"></div>
			本程式功能
			<ul>
			<li>瀏覽台灣<a href="http://gissrv4.sinica.edu.tw/gis/twhgis.aspx" target="sinica">等高線地形圖</a>,以<a href="http://maps.nlsc.gov.tw/">衛星圖</a>套疊作為登山前參考
			<li>選擇範圍以便使用<a href="/twmap/" target="twmap">地圖產生器</a>,<a href="/twmap/login.php">登入</a>
			<li>歡迎<a href="https://www.facebook.com/pages/%E5%9C%B0%E5%9C%96%E7%94%A2%E7%94%9F%E5%99%A8/283886151658168" target="_blank">建議或討論</a>
			</ul>
			小秘訣
			<ul>
			<li>按住 shift 可以框選縮放
			<li>按右鍵可以顯示目前座標
			<li>按左鍵可以選擇範圍
			<li>搜尋框可打入山名,地標,座標 lon,lat 或 twd67 / twd97 座標 x,y
				<li>參考 <a href="http://blog.yam.com/amimitea/article/48657866" target="_blank">介紹文</a>
			</ul>
			Powered by <a href="https://developers.google.com/maps/documentation/javascript/reference?hl=zh-tw" target="_blank">Google Maps API</a>, 台灣經建版 25000:1 一版/三版, 國土測量中心地圖, coded by <a href="https://www.facebook.com/happyman.chiu" target="_blank">蚯蚓</a>,謝謝使用.
		</div>
<div id=meerkat>
	<a href="#" class="close-meerkat">close</a>
	<div class="meerkat-content">
				Here we go :*
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
