<?php
if (!isset($_SESSION))
	session_start();
require_once("lib/functions.inc.php");
  list($st, $info) = login_info();
                                if ($st === false ) {
                                        $greetings = sprintf("歡迎光臨");
                                        $greetings_admin = sprintf("管理<a href='%s'>登入</a>",$CONFIG['site_twmap_html_root'] . "main.php?return=twmap3" );
                                } else {
                                        $greetings = sprintf("歡迎 %s<img src='%s' />",$info['user_nickname'],$info['user_icon']);
					if (is_admin()) 
					$greetings_admin = "你的身份是管理者";
					else
					$greetings_admin = "你的身份是使用者";
					
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
                        <div align="right"><div id="openwin"></div>
			<h2><?php echo $greetings; ?></h2>
			</div>
			使用方法:
                        <ul>
                        <li>瀏覽台灣<a href="http://gissrv4.sinica.edu.tw/gis/twhgis.aspx" target="sinica">等高線地形圖</a>,以<a href="http://maps.nlsc.gov.tw/" target="nlsc">衛星圖</a>套疊作為登山前參考
                        <li>選擇範圍以便使用<a href="<?php echo $CONFIG['site_twmap_html_root']; ?>main.php" target="twmap">地圖產生器</a>
                        </ul>
                        小秘訣:
                        <ul>
                        <li>按住 shift 可以框選縮放
                        <li>按右鍵可以顯示目前座標
                        <li>按左鍵可以選擇範圍
                        <li>搜尋框可打入山名,地標,座標: 
				<ul><li>lon,lat :經度,緯度
                                <li>twd67 座標  : x,y
				<li>twd97 座標  : x/y
                                </ul>
			</ul>
			其他:
			<ul>
                        <li>參考 <a href="http://blog.yam.com/amimitea/article/48657866" target="_blank">介紹文</a>
                        <li><a href="https://www.facebook.com/pages/%E5%9C%B0%E5%9C%96%E7%94%A2%E7%94%9F%E5%99%A8/283886151658168" target="_blank">建議或討論</a>
			<li><?php echo $greetings_admin; ?>
                        </ul>
                </div>

</body>
