<?php
function sanitize_output($buffer) {
        $search = [
                '/\>[^\S ]+/s',
                '/[^\S ]+\</s',
                '/(\s)+/s',
                '/<!--(.|\s)*?-->/'
        ];

        $replace = [
                '>',
                '<',
                '\\1',
                ''
        ];

        $buffer = preg_replace($search, $replace, $buffer);

	return $buffer .  <<< EOL

<!--
 ⠀⠀⠀⠀       ⢀⠤⠒⠒⠢⢄⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀
⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⢀⡯⠴⠶⠶⠒⠢⢇⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀
⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⡎⡤⠖⠂⡀⠒⡢⡌⢣⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀
⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⣷⠯⢭⣵⠑⣯⡭⢹⡎⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀
⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⢻⡆⠀⢠⣤⠄⠀⣸⠇⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀
⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠸⣷⢄⣈⣟⢁⢴⠿⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀
⠀⠀⠀⠀⠀⠀⠀⣀⢴⠒⡝⠁⠬⠛⣚⡩⠔⠉⢻⠒⣦⢄⠀⠀⠀⠀⠀⠀⠀⠀
⠀⠀⠀⠀⠀⢀⢎⠁⡌⢰⠁⠀⠀⠀⠀⠀⠀⠀⢸⠀⡛⠀⡷⡀⠀⠀⠀⠀⠀⠀
⠀⠀⠀⠀⣀⣾⣷⣠⠃⢸⠀⠀⠀⠀⠀⠀⠀⠀⣸⠀⢹⢰⠁⢳⠀⠀⠀⠀⠀⠀
⠀⠀⠀⠀⢸⡿⠟⢿⢳⡏⠀⠀⠀⠀⠀⠀⠀⢠⡟⣶⣘⢞⡀⠘⡆⠀⠀⠀⠀⠀
⠀⠀⠀⠀⡼⢺⣯⢹⢰⡏⠒⠒⠒⠊⠀⠐⢒⣾⣹⣸⢹⣾⡇⠀⢣⠀⠀⠀⠀⠀
⠀⠀⠀⠀⣏⣾⠃⠀⣼⡟⣢⣀⡠⠤⣀⡰⢋⡝⣱⣹⠇⣿⣧⣴⠸⡄⠀⠀⠀⠀
⠀⠀⠀⠀⡏⡞⡆⢠⡇⣟⠭⡒⠭⠭⠤⠒⣡⠔⣽⡇⣂⣿⠟⠃⢀⡇⠀⠀⠀⠀
⠀⠀⠀⠀⢧⡇⡧⢫⠃⣷⣽⣒⣍⣉⣈⡩⢴⠾⡳⢡⢸⣛⣪⡗⢴⠁⠀⠀⠀⠀
⠀⠀⠀⠀⣼⢃⠷⣸⣤⣯⢞⡥⢍⣐⣂⠨⠅⠊⡠⢃⣟⢏⠹⣎⣆⡀⠀⠀⠀⠀
⠀⡠⠶⠚⠛⠛⠽⢹⡟⡖⢓⠿⣝⠓⠒⠒⠒⠭⢤⠗⣯⣩⣽⣿⠷⣾⣿⢷⣆⠀
⠜⣌⠢⢄⣀⡀⠀⡞⢡⠘⢄⠑⠨⢉⣀⠉⣀⠄⢊⠜⡸⠛⣿⡍⠉⠉⠈⢁⠁⠇
⠈⢯⡓⠦⠤⠬⠭⣵⠀⠱⢄⠑⠲⠤⠤⠤⠤⠒⢁⡔⠁⢠⣏⣡⣤⣤⡶⠜⣻⠃
⠀⠈⠙⠛⠒⠛⠻⠯⠕⠤⣀⣉⣓⣒⣂⣒⣒⣊⣁⣠⠔⠛⠂⠒⠛⠓⠛⠚⠉⠀

        A-di-đà Phật
-->
EOL;
}

ob_start("sanitize_output");

session_start([
    'read_and_close' => true,
]);
$ver = trim(file_get_contents("VERSION"));
require_once("lib/functions.inc.php");
list ($st, $info) = login_info();
?>
<!DOCTYPE html>
<html>
<head>
	<meta http-equiv="content-type" content="text/html; charset=UTF-8"/>
	<meta name="apple-mobile-web-app-capable" content="yes" />
	<meta name = "viewport" content = "width=device-width, initial-scale=1.0, user-scalable=0">

	<title>地圖瀏覽器 v<?=$ver?></title>
	<script src="//maps.googleapis.com/maps/api/js?v=3&key=<?php echo $CONFIG['gmap_api_key']; ?>&libraries=geometry,drawing&callback=Function.prototype"></script>
	<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
	<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.js"></script>
	<link rel="stylesheet" href="//code.jquery.com/ui/1.13.2/themes/redmond/jquery-ui.css">
	<!--
    <script src="https://code.jquery.com/jquery-2.1.4.min.js"></script>
    <script src="https://code.jquery.com/ui/1.11.4/jquery-ui.min.js"></script>
    <link type="text/css" href="https://code.jquery.com/ui/1.11.4/themes/flick/jquery-ui.css" rel="Stylesheet" />  
-->
	<link href="css/font-awesome.min.css" rel="stylesheet" />
	   
<!-- build:js js/vender.js -->
	<script  src="js/ui.dropdownchecklist.js" charset="utf-8"></script>
	<script  src='js/proj4js-combined.js'></script>
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
	<script  src="js/jquery.noty.js"></script>
	<script  src="js/shapedraw.js"></script>
	<script  src="js/loadgpx.js"></script>
<!-- endbuild -->
<!-- build:css css/twmap3.css -->
	<link rel="stylesheet" type="text/css" href="css/twmap3_main.css" />
<!-- endbuild -->
<script>
<?php
// 檢查是否登入
if ($st === true) {
	echo "var login_role = 1;\n";
	printf("var login_uid = %d;\n",$info['uid']);
} else {
	echo "var login_role = 0;\n";
}
printf("var getkml_url = '%s';",$CONFIG['getkml_url']);
printf("var geocodercache_url = '%s';",$CONFIG['geocodercache_url']);
printf("var pointdata_url = '%s';",$CONFIG['pointdata_url']);
printf("var pointdata_admin_url = '%s';",$CONFIG['pointdata_admin_url']);
printf("var promlist_url = '%s';", $CONFIG['promlist_url']);
printf("var get_waypoints_url = '%s';",$CONFIG['get_waypoints_url']);
printf("var get_elev_url = '%s';",$CONFIG['get_elev_url']);
printf("var viewshed_url = '%s';",$CONFIG['viewshed_url']);
printf("var exportkml_url = '%s';", $CONFIG['exportkml_url']);
printf("var poisearch_url = '%s';", $CONFIG['poisearch_url']);
printf("var callmake_url = '%s';", $CONFIG['site_twmap_html_root'] . "main.php?tab=0&");
printf("var shorten_url = '%s';",$CONFIG['shorten_url']);
?>
</script>
<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-Q1DHK68EH5"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());

  gtag('config', 'G-Q1DHK68EH5');
</script>
	</head>
	<body>
	<div id="loading">
	歡迎使用 地圖瀏覽器 v<?=$ver?><br>
  		<img src="icon/twmap3.jpg"><p>
  		<img src="img/loading20x20.gif">   載入中...
	</div>
		<div id="locContainer">
			<div id="loc"></div>
		</div>
		<div id="opContainer" >
		<!-- https://fontawesome.com/cheatsheet?from=io#social-buttons   -->	
			<div id="mapIdControl" title="選擇地圖">
				<select title="切換背景圖" name="changebmap" id="changebmap"  style="height: 32px; font-weight: 900;font-family: FontAwesome">
					<option value="jm20k_1904">&#xf1da; &nbsp;堡圖1904</option>
					<option value="jm20k_1904_tri">&#xf1da; &nbsp;三角點1904</option>
					<option value="fandi">&#xf1da; &nbsp;蕃地1916</option>
					<option value="jm20k_1921">&#xf1da; &nbsp;堡圖1921</option>
					<option value="jm50k">&#xf1da; &nbsp;陸測1924</option>
					<option value="jm50k_1924">&#xf1da; &nbsp;陸測1924新</option>
					<option value="tw50k">&#xf1da; &nbsp;老五萬1956</option>
					<option value="tm50k_1966">&#xf129; &nbsp;水利1966</option>
					<option value="geo2016">&#xf129; &nbsp;地質圖</option>
					<option value="twmapv1">&#xf1da; &nbsp;經建1 1989</option>
					<option value="tri1999">&#xf129; &nbsp;林崇雄百岳圖</option>
					<option value="tw5kariel">&#xf1da; &nbsp;TW5K 2000</option>
					<option value="taiwan">&#xf1da; &nbsp;經建3 2001</option>
					<option value="moi_osm">&#xf164; &nbsp;魯地圖</option>
					<option value="moi_osm_en">&#xf164; &nbsp;RUDY</option>
					<option value="nlsc_emap">&#xf164; &nbsp;EMAP5</option>
					<option value="roadmap">&#xf1a0; &nbsp;Google圖</option>
					<option value="terrain">&#xf1a0; &nbsp;地形圖</option>
					<option value="hillshading">&#xf0ac; &nbsp;陰影</option>
					<option value="theme">&#xf0ac; &nbsp;主題</option>
					<option value="debug">&#xf0ac; &nbsp;除錯</option>
					<option value="satellite">&#xf1a0; &nbsp;衛星圖</option>
					<option value="atis">&#xf279; &nbsp;農航所</option>
					<option value="nlsc_photo_mix">&#xf279; &nbsp;nlsc正射圖</option>
				</select>
			</div>
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
			<!-- <button type="button" id="changemap" name="changemap" title="切換一版與三版地形圖" >經建三</button>-->
			<div id="CGMAP">
				<select title="切換前景圖" name="changemap" id="changemap" style="height: 32px; font-family: FontAwesome, Ariel">
					<option value="moi_osm">&#xf164; &nbsp;魯地圖</option>
					<option value="tw25k_v3">&#xf279; &nbsp;經建三</option>
					<option value="tw25k_v1">&#xf279; &nbsp;經建一</option>
				</select>
			</div>
			 <div id='CGNAME'>
				     <select title="切換路圖" name="road" id="changegname" style="height: 32px; font-family: FontAwesome, Ariel;">
						     <option value="GoogleNames">&#xf1a0; &nbsp;道路</option>
							 <option value="NLSCNames">nlsc 道路</option>
							 <option value="RUDY_BN">魯地圖BN</option>
							 <option value="RUDY_DN">魯地圖DN</option>
							 <option value="Happyman">Happyman</option>
							 <option value="Compartment">林班界</option>
							 <option value="MOI_CONTOUR2">等高線2015</option>
							 <option value="MOI_CONTOUR">等高線2005</option>
							 <option value="None">無道路</option></select>
			 </div>
		</div>

		<div id="map_canvas"  data-tap-disabled="true"></div>

		<div id="drop-container"><div id="drop-silhouette"></div></div>		<div id="title" class="title">
		  <form id="gotoform" name="gotoform">
			<span id="about" title="關於" class="ui-state-default ui-corner-all" > 地圖瀏覽器 v<?=$ver?></span> <span id="search_text"><img src='img/loading20x20.gif' /></span> <input id="tags" type="text" class="ui-corner-all"  title="輸入山頭名稱或地標,或者座標" disabled>
			<button type=button class="ui-state-default ui-corner-all" id="goto" title="搜尋並定位">到</button>
			<button id="kml_sw" class="ui-state-default ui-corner-all" title="山友登山軌跡(支援z=13到18)" type=button>行跡</button>
			<button id="label_sw" class="ui-state-default ui-corner-all" title="三角點名稱"  type=button>標籤</button>
	 		<!-- <button id="delaunay_sw" class="ui-state-default ui-corner-all disable" title="三角點連線"  type=button>連線</button> -->
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
		<option selected="selected" value="10">獨立峰</option>
			<option selected="selected" value="7">其他</option>
			</select>
			
			</form>
		</div>
		<div id="params"></div>
		<button type="button" id="setup" name="setup" style='display:none' class="ui-state-default ui-corner-all">設定</button>
		<div id="CGRID">
		<select title="切換 Grid" name="grid" id="changegrid" class="ui-corner-all">
		<option value="TWD67" selected >TWD67 Grid</option><option value="TWD67PH">TWD67澎</option><option value="WGS84">經緯度</option><option value="None">無Grid</option>
		<option value="TWD67_EXT">TWD67 EXT</option><option value="TWD97" >TWD97 Grid</option><option value="TWD97PH">TWD97澎</option><option value="TWD97_EXT">TWD97 EXT</option>
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
		<option value="cht3G">cht3G</option>
		<option value="cht">cht4+5G</option>
		<option value="twn3G">twn3G</option>
		<option value="twn">twn4+5G</option>
		<option value="fet3G">fet3G</option>
		<option value="fet">fet4+5G</option>
		<option value="aptg">aptg4+5G</option>
		</select>
		</div>

		<div id="inputtitleform" style="display:none">
			<br>請輸入地圖標題: <br><br><input id="inputtitle" type="text" size="2" />
			<br>
			<select id="datum">
				<option value="TWD67" >TWD67 紅色框</option>
				<option value="TWD97" selected>TWD97 綠色框</option>
			</select>
			<br>
			<input type="button" id='inputtitlebtn' value="送出" />
			<input type="button" id='inputtitlebtn2' value="取消" />
		</div>
		<div id="mobile_setup">
			<a href="#" class="close-meerkat2">close</a>
			<div id="mobile_export_kml">
			<button id="export_kml" class="ui-state-default ui-corner-all">下載圖資</button>
			</div>
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
	<div id="ranking">
	<iframe id="ranking_iframe" frameborder="0" marginwidth="0" marginheight="0"  width="100%" height="100%" allowfullscreen></iframe>
	</div>
	<div id="consolediv" style="display:hidden"> <textarea id="console" readonly="readonly" style="width:100%;height:100%;border:0px;margin:0px;background-color:#e0f0a0;"></textarea></div>
	  <div id="buttons" style="font-family: Font Awesome\ 5 Free; padding: 2px; margin-top: 2px">
            <button title="刪除形狀" id="delete-button"><i class="fa fa-times"></i></button>
            <button title="刪除全部形狀" id="clear-button"><i class="fa fa-times-circle"></i></button>
			<button title="顯示形狀資訊" id="shapeinfo-button"><i class="fa fa-info"></i></button>
      </div>
	<div id="msg" style="background-color: white;">
	</div>
<script>
	var _gaq = _gaq || [];
	_gaq.push(['_setAccount', 'UA-19949015-1']);
	_gaq.push(['_trackPageview']);
	$(function() {
	$.getScript( "js/main.js?ts=<?php echo time();?>" ).done(function() {
		initialize();
		$(window).resize(function() {
			resizeMap();
		});
		$('input').autoGrowInput({
			comfortZone: 20,
			maxWidth: 2000
		});
		/* accept message from iframe (point3 admin) */
		window.onmessage = function(e){
    			if (e.data.function == 'markerReloadSingle') {
				markerReloadSingle(e.data);
			}
	
    		};
	});

});

</script>
</body>
</html>
