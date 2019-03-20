<?php

// release dir:
$release_base = "/home/mountain/public_html/gpx_map/";
$release_webpath = "https://map.happyman.idv.tw/~mountain/gpx_map/";

$releases = glob($release_base ."*/*.md5");
$outformat = "html";
if ($argv[1] == 'json' ||(isset($_GET['type']) && $_GET['type'] == 'json')) {
	$outformat = "json";
}
if (count($releases)>0) {
	krsort($releases);
	foreach($releases as $fname){
		if (preg_match("/.*\/(\w+)\/Happyman.map.md5/",$fname,$mat)){
			$version = $mat[1];
			$md5sum = ` cut -d " " -f 1 $fname`;
			$url = $release_webpath . $version ."/" . str_replace('md5','zip',basename($fname));
			$data[] = array("version"=>$version,"md5"=>trim($md5sum), "url"=>$url, "size"=> filesize(str_replace(".md5","",$fname)));
		}
	}
}

if ($outformat == 'json') {
	echo json_encode($data);
	exit(0);
}
?>
<html>
<head>
<title>地圖產生器 GPX 離線圖資下載</title>
<style>
body {
	　font-family: sans-serif;
	font-size: 1.5em;
}
</style>
</head>
<body>
<div style="width: 100%">
<div style="float:left;width: 50%">
<p>Happyman.map (mapsforge) 圖資來自地圖產生器所蒐集之行跡檔，離線圖資的產出，要特別感謝魯地圖 Rudy 協助。本圖資與魯地圖共用 style 檔(2019-03-14之後版本)，並且可與魯地圖使用多地圖套疊。
<h3>本圖資所提供的行跡山友貢獻，路線僅供參考之用，登山前請做好萬全準備，登山安全自行負責。不同意【請勿】下載。</h3>
<hr>

<ul>
<?php
foreach($data as $d){
	printf("<li>版本: %s (%s bytes) <a href='%s' target=_blank>下載</a>\n",$d['version'],$d['size'],$d['url']);
}

?>
</ul><hr>
<a href='https://www.youtube.com/playlist?list=PLhDHBZwRvarRzR3-tSeceQZqhVEflsUC4&fbclid=IwAR1KCz3jH8zQboiOOvt52WUKaZZ9sQ_7HR6r3iH6C2s_jvFuq0wdsu7QJos' target=_blank>圖資套疊參考</a>,<a href='https://www.facebook.com/groups/taiwan.topo/permalink/1217865971702347/' target=_blank>魯地圖社群協助</a>

</div>
<div style="float:right;">
<img src="img/oruxmap.jpg" alt='image' />
</div>
</div>
</body></html>
