<?php
if (!isset($_SESSION))
	session_start( [
    'read_and_close' => true,
]);
require_once("lib/functions.inc.php");
list($st, $info) = login_info();
                                if ($st === false ) {
                                        $greetings = sprintf("歡迎光臨");
                                        $greetings_admin = sprintf("請<a href='%s' target=_top >登入</a>",$CONFIG['site_twmap_html_root'] . "main.php?return=twmap3" );
                                } else {
                                        $greetings = sprintf("歡迎 %s<img src='%s'  title='uid=%d'/>",$info['user_nickname'],$info['user_icon'],$_SESSION['uid']);
					if (is_admin()) 
					$greetings_admin = sprintf("你的身份是管理者 (%d) (%s)",$_SESSION['uid'],php_uname('n'));
					else
					$greetings_admin = sprintf("你的身份是使用者 (%d)",$_SESSION['uid']);
					
                                }

?>
<html>
        <meta http-equiv="content-type" content="text/html; charset=UTF-8"/>
        <meta name="apple-mobile-web-app-capable" content="yes" />

<head><title>關於地圖瀏覽器</title>
 <script src="https://code.jquery.com/jquery-2.1.4.min.js"></script>
</head>
<script>
$(document).ready(function() {
	parent.toggle_user_role();
	if (window.parent.location.href != window.top.location.href) {
                $('#openwin').html('<a href="<?php echo $site_html_root; ?>" target=_top>獨立視窗</a>');
        }
});
</script>
<body><hr>
                <div id="footer" title="About"  name="footer">
                        <div style="text-align: right"><div id="openwin"></div>
			<h2><?php echo $greetings; ?></h2>
			</div>
			地圖瀏覽器使用方法:
                        <ul>
                        <li>瀏覽台灣<a href="http://gissrv4.sinica.edu.tw/gis/twhgis.aspx" target="sinica">等高線地形圖,歷史圖資</a>,以<a href="http://maps.nlsc.gov.tw/" target="nlsc">衛星圖</a>套疊作為登山前參考。
                        <li>選擇範圍以便使用<a href="<?php echo $CONFIG['site_twmap_html_root']; ?>main.php" target="twmap">地圖產生器</a>產生方便列印的地圖, 或者下載登山相關興趣點圖資。
                        <li>按住 shift 可以框選縮放
                        <li>按右鍵可以顯示目前座標,高程(台澎金馬)
                        <li>按左鍵可以框選欲出圖範圍: 待範圍出現後，在範圍內按<b>右鍵</b><a href="<?php echo $CONFIG['site_twmap_html_root']; ?>api/exportkml.php">可下載圖資(kml)</a> 或得知 bbox
                        <li>搜尋框可打入山名,地標,座標: 
				<ul><li>lat,lng :緯度,緯度 (用小數點方式)
                                <li>TWD67 TM2座標  : x,y
				<li>TWD97 TM2座標  : x/y
				<li>地籍座標(間): cj: x.x,y.y
				<li>地籍座標(公尺) cm: x.x,y.y
                                </ul>
			<li>點選行跡可下載行跡檔。(kml/gpx)
			<li>上載行跡(beta): 請
						<?php if ($st === false) echo "上載."; else {
							?><a href='<?php echo $site_twmap_html_root;?>/api/uploadpage.php'>上載</a>行跡檔.	
						<?php } ?>
			<li>可以在地圖上畫線/多邊形 計算長度/面積。
			<li>POI search: 找圖上的關鍵字 <a href='<?php echo $site_twmap_html_root;?>/api/poi_search.php?name=玉山'>玉山</a>, <a href='<?php echo $site_twmap_html_root;?>/api/poi_search.php?name=3-7214'>3-7214</a>
			</ul>	
			其他:
			<ul>
                        <li>參考 <a href="https://hiking.biji.co/index.php?q=review&act=info&review_id=1695" target="_blank">介紹文</a>
                        <li><a href="https://www.facebook.com/pages/%E5%9C%B0%E5%9C%96%E7%94%A2%E7%94%9F%E5%99%A8/283886151658168" target="_blank">建議或討論</a>
			<li><?php echo $greetings_admin; ?>
                        </ul>
                </div>

</body>
