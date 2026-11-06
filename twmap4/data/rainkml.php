<?php
// v16
// 降水預報 geo-reference 重量測 (一次定版, 四張圖共用同一底圖):
// 以 Natural Earth 台灣本島海岸線 (406 點) 與圖上海岸黑線做 ICP 配準
// (356 點吻合, 殘差 lon 0.75km / lat 0.65km), 修正舊 box 偏西/偏北約 1km
// 舊: W118.9466 E122.3905 S21.6837 N25.8697 -> 新: W118.9520 E122.3870 S21.6860 N25.8608
// (圖內含標題/圖例/離島 inset, 那些仍是版面配置, 只有主圖海岸與底圖對齊)
// v15
// 可見光去雜點: B03 夜間資料有大量鹽雜訊 (65531, 白天合法上限約 11076),
// 逐張 min/max 拉伸會把雜點放大成滿屏雪花. 改為轉檔前先把 >20000 的像素清零
// (gdal_calc), 再以固定 0..12000 縮放 + gamma 0.6; 夜間剩正常黑夜, 白天更清楚
// v14
// 兩張衛星圖 PNG 頂端加資訊列: 圖名 + 開始觀測/結束觀測 (ObsTime 起 +10 分鐘),
// 黑底白字, 位置 左圖名/中開始/右結束 (仿官網 Himawari 雲圖版面)
// v13
// 紅外線改偽彩色 (取代 v12 的灰階反相+gamma): 亮度溫度以固定物理範圍
// -80..40C 線性縮放後套官方 LCC_IR1 溫度色票 (藍->綠->黃->紅->紫->白),
// 效果類似官網 Himawari 紅外線雲圖, 但用 O-B0056 原始資料轉出, 無燒入的
// 標題/格線/海岸/色階尺, 且地理對位正確 (官網 JPG 無座標, 硬套 LatLonBox 會錯位)
// v12
// 衛星圖快取改放 sys_get_temp_dir()/twmap4_sat/{dataid} (/tmp), PNG 由本檔
// ?term=sat_vis|sat_ir&img=1 直接吐出, 不再依賴 data/cache 公開目錄的寫入權限
// 紅外線色調強化: 亮度溫度反相 + gamma 0.65 (冷雲變亮、地表變暗, 慣用紅外線顯示) [v13 起改偽彩色]
// 兩者轉檔皆加 -dstalpha, 資料覆蓋外的角落透明, 疊圖時露出底圖
// v11
// 衛星圖(可見光/紅外線):
//   O-B0055-001 東亞可見光反照率 / O-B0056-001 東亞紅外線亮度溫度
//   實際檔為 .gtif.zip (內含 data4/hsd/PG261/*.LCC.gtif, 3000x3000 UInt16, Lambert)
//   瀏覽器無法直接顯示 GeoTIFF, 故後端下載後以 gdalwarp(轉 EPSG:4326, 裁到 GeoInfo 範圍)
//   + gdal_translate(-scale 依 gdalinfo 統計值自動拉伸) 轉成 PNG 快取於 data/cache/{dataid}/
//   LatLonBox 直接用各產品 json 的 GeoInfo (可見光 102-155, -1-48 / 紅外線 102-155, 0-50)
// v10
// 單雷達合成回波圖 (降雨雷達 北/中/南):
//   O-A0084-001 樹林 (北) / O-A0084-002 南屯 (中) / O-A0084-003 林園 (南)
//   https://opendata.cwa.gov.tw/dataset/130074
//   範圍: 3600x3600, 顯示範圍 距雷達150公里, 每1~2分鐘更新, 無 Cache-Control
//   站點經緯度 001(121.40,25.00) 002(120.58,24.14) 003(120.38,22.53)
//   LatLonBox 以 O-A0058-003 (已知經緯範圍) 為基準, 比對海岸線反推 (誤差 <0.01 度)
// v9
// 雷達回波: https://cwaopendata.s3.ap-northeast-1.amazonaws.com/Observation/O-A0058-003.png
// (O-A0058-003 雷達整合回波圖-臺灣(鄰近區域)_無地形, 每10分鐘更新)
// 範圍 LongitudeRange 118.0-124.0, LatitudeRange 20.5-26.5, ImageDimension 3600x3600
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
function outkml($name,$url,$type='forecast',$opacity=0.5,$box=null) {
kmlhead();
?>
<GroundOverlay>
        <name><?php echo htmlspecialchars($name, ENT_XML1, 'UTF-8'); ?></name>
        <color><?php echo dechex((int)round($opacity*255));?>ffffff</color>
        <Icon>
                <href><?php echo htmlspecialchars($url, ENT_XML1, 'UTF-8'); ?></href>
                <viewBoundScale>0.75</viewBoundScale>
        </Icon>
        <LatLonBox>
<?php
if ($box) {
?>
	<LatLonBox>
		<north><?php echo $box['north']; ?></north>
		<south><?php echo $box['south']; ?></south>
		<east><?php echo $box['east']; ?></east>
		<west><?php echo $box['west']; ?></west>
	</LatLonBox>

<?php
} else if ($type=='forecast') {
?>
<LatLonBox>
	<north>25.86079</north>
	<south>21.68604</south>
	<east>122.38698</east>
	<west>118.95197</west>
</LatLonBox>
<?php
} else if ($type=='radar') {
?>
	<LatLonBox>
		<north>26.5</north>
		<south>20.5</south>
		<east>124.0</east>
		<west>118.0</west>
	</LatLonBox>

<?php
} else if ($type=='radar_n') {
?>
	<LatLonBox>
		<north>26.3478</north>
		<south>23.6492</south>
		<east>122.8938</east>
		<west>119.9062</west>
	</LatLonBox>

<?php
} else if ($type=='radar_c') {
?>
	<LatLonBox>
		<north>25.4853</north>
		<south>22.7887</south>
		<east>122.0590</east>
		<west>119.1010</west>
	</LatLonBox>

<?php
} else if ($type=='radar_s') {
?>
	<LatLonBox>
		<north>23.8809</north>
		<south>21.1761</south>
		<east>121.8446</east>
		<west>118.9154</west>
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

// 衛星圖: 抓 CWA 開放資料 json 元資料 (ProductURL/ObsTime/GeoInfo),
// 下載 .gtif.zip -> 解壓取 *.gtif -> gdalwarp 轉 EPSG:4326 裁到 GeoInfo 範圍
// -> gdal_translate 依統計值縮放轉 PNG, 快取於 sys_get_temp_dir()/twmap4_sat/{dataid}/
// 紅外線($enhance=true) 做偽彩色: 固定 -80..40C 縮放 + 溫度色票 (見 satellite_ir_colorize)
// 成功回傳 array('url'=>..., 'box'=>array('north'=>..,'south'=>..,'east'=>..,'west'=>..)), 失敗回傳 null
function satellite_cache_dir($dataid) {
	return rtrim(sys_get_temp_dir(), '/') . '/twmap4_sat/' . $dataid;
}
function satellite_latest_png($dataid) {
	$files = glob(satellite_cache_dir($dataid) . '/*.png');
	if (!$files) {
		return null;
	}
	usort($files, function($a, $b) { return filemtime($b) - filemtime($a); });
	return (is_file($files[0]) && filesize($files[0]) > 0) ? $files[0] : null;
}

// 紅外線偽彩色: 灰階值 0..255 對應 -80..40C (固定物理範圍, 非逐張 min/max),
// 依官方溫度色票分段上色 (暖->冷: 深藍/藍/綠/黃綠/黃/橘/紅/紫紅/紫/白)
function satellite_ir_colorize($png_path) {
	if (!function_exists('imagecreatefrompng')) {
		return false;
	}
	$im = @imagecreatefrompng($png_path);
	if (!$im) {
		return false;
	}
	imagepalettetotruecolor($im);
	$w = imagesx($im);
	$h = imagesy($im);
	$out = imagecreatetruecolor($w, $h);
	imagealphablending($out, false);
	imagesavealpha($out, true);
	$bands = array(
		array(28, 40, array(20, 20, 170)),
		array(23, 28, array(0, 60, 255)),
		array(0, 23, array(0, 90, 255)),
		array(-20, 0, array(0, 200, 0)),
		array(-25, -20, array(150, 220, 0)),
		array(-31, -25, array(255, 220, 0)),
		array(-42, -31, array(255, 140, 0)),
		array(-53, -42, array(255, 0, 0)),
		array(-59, -53, array(220, 0, 120)),
		array(-63, -59, array(255, 0, 255)),
		array(-80, -63, array(255, 255, 255)),
	);
	$lut = array_fill(0, 256, array(0, 0, 0));
	foreach ($bands as $b) {
		$lo = (int)round(($b[0] + 80) / 120 * 255);
		$hi = (int)round(($b[1] + 80) / 120 * 255);
		for ($i = $lo; $i <= $hi && $i < 256; $i++) {
			$lut[$i] = $b[2];
		}
	}
	$cols = array();
	foreach ($lut as $rgb) {
		$cols[] = imagecolorallocate($out, $rgb[0], $rgb[1], $rgb[2]);
	}
	for ($y = 0; $y < $h; $y++) {
		for ($x = 0; $x < $w; $x++) {
			$c = imagecolorat($im, $x, $y);
			$g = ($c >> 16) & 0xff;
			$a = ($c >> 24) & 0x7f;
			imagesetpixel($out, $x, $y, $cols[$g] | ($a << 24));
		}
	}
	$tmp = $png_path . '.pc.png';
	$ok = imagepng($out, $tmp);
	imagedestroy($im);
	imagedestroy($out);
	if ($ok && is_file($tmp)) {
		rename($tmp, $png_path);
		return true;
	}
	return false;
}

// 在 PNG 頂端畫資訊列: 黑底白字, 左=圖名, 中=開始觀測, 右=結束觀測
function satellite_draw_caption($png_path, $title, $start_str, $end_str) {
	if (!function_exists('imagettftext')) {
		return false;
	}
	$font = null;
	$candidates = array(
		'/usr/share/fonts/truetype/wqy/wqy-microhei.ttc',
		'/usr/share/fonts/truetype/wqy/wqy-zenhei.ttc',
		'/usr/share/fonts/truetype/droid/DroidSansFallbackFull.ttf',
		'/usr/share/fonts/truetype/noto/NotoSansCJK-Regular.ttc',
	);
	foreach ($candidates as $f) {
		if (is_file($f)) {
			$font = $f;
			break;
		}
	}
	if (!$font) {
		return false;
	}
	$im = @imagecreatefrompng($png_path);
	if (!$im) {
		return false;
	}
	imagepalettetotruecolor($im);
	imagesavealpha($im, true);
	imagealphablending($im, false);
	$w = imagesx($im);
	$h = imagesy($im);
	$bar = (int)round($h * 0.028);
	if ($bar < 36) { $bar = 36; }
	if ($bar > 56) { $bar = 56; }
	$black = imagecolorallocatealpha($im, 0, 0, 0, 0);
	imagefilledrectangle($im, 0, 0, $w - 1, $bar - 1, $black);
	$white = imagecolorallocatealpha($im, 255, 255, 255, 0);
	$size = 26;
	$texts = array($title, '開始觀測 ' . $start_str, '結束觀測 ' . $end_str);
	$xs = array(16, 0, 0);
	$b = imagettfbbox($size, 0, $font, $texts[1]);
	$xs[1] = (int)(($w - ($b[2] - $b[0])) / 2);
	$b = imagettfbbox($size, 0, $font, $texts[2]);
	$xs[2] = $w - 16 - ($b[2] - $b[0]);
	$y = $bar - 12;
	foreach ($texts as $i => $t) {
		imagettftext($im, $size, 0, (int)$xs[$i], $y, $white, $font, $t);
	}
	$tmp = $png_path . '.cap.png';
	$ok = imagepng($im, $tmp);
	imagedestroy($im);
	if ($ok && is_file($tmp)) {
		rename($tmp, $png_path);
		return true;
	}
	return false;
}
function satellite_png($dataid, $fallback_box, $enhance = false, $term = 'sat_vis') {
	$meta_url = 'https://cwaopendata.s3.ap-northeast-1.amazonaws.com/Observation/' . $dataid . '.json';
	$ctx = stream_context_create(array('http' => array('timeout' => 15)));
	$meta_json = @file_get_contents($meta_url, false, $ctx);
	$meta = $meta_json ? json_decode($meta_json, true) : null;
	$dataset = isset($meta['cwaopendata']['dataset']) ? $meta['cwaopendata']['dataset'] : null;
	$cache_dir = satellite_cache_dir($dataid);

	if (!$dataset || empty($dataset['Resource']['ProductURL'])) {
		// 元資料抓不到時退回既有快取 PNG (box 用官方 GeoInfo 預設值)
		$cached = satellite_latest_png($dataid);
		if ($cached) {
			return array('url' => satellite_cache_url($term), 'box' => $fallback_box);
		}
		return null;
	}

	$product_url = $dataset['Resource']['ProductURL'];
	$parts = parse_url($product_url);
	if (!isset($parts['host']) || $parts['host'] !== 'cwaopendata.s3.ap-northeast-1.amazonaws.com' || $parts['scheme'] !== 'https') {
		return null;
	}
	$obs_time = isset($dataset['ObsTime']['Datetime']) ? $dataset['ObsTime']['Datetime'] : '';
	$version = preg_replace('/[^0-9]/', '', $obs_time);
	if ($version === '') {
		$version = date('YmdHi');
	}
	// GeoInfo 格式如 "102.0-155.0" / "-1.0-48.0"
	$lon = isset($dataset['GeoInfo']['LongitudeRange']) ? $dataset['GeoInfo']['LongitudeRange'] : '';
	$lat = isset($dataset['GeoInfo']['LatitudeRange']) ? $dataset['GeoInfo']['LatitudeRange'] : '';
	if (!preg_match('/^(-?[0-9]+(?:\.[0-9]+)?)-(-?[0-9]+(?:\.[0-9]+)?)$/', $lon, $m1) ||
	    !preg_match('/^(-?[0-9]+(?:\.[0-9]+)?)-(-?[0-9]+(?:\.[0-9]+)?)$/', $lat, $m2)) {
		return null;
	}
	$box = array('west' => (float)$m1[1], 'east' => (float)$m1[2], 'south' => (float)$m2[1], 'north' => (float)$m2[2]);

	$png_name = $dataid . '_' . $version . ($enhance ? '_pc' : '_dn') . '_cap.png';
	$png_path = $cache_dir . '/' . $png_name;
	if (is_file($png_path) && filesize($png_path) > 0) {
		return array('url' => satellite_cache_url($term), 'box' => $box);
	}

	if (!is_dir($cache_dir) && !@mkdir($cache_dir, 0755, true)) {
		return null;
	}
	$lock = @fopen($cache_dir . '/lock', 'c');
	if ($lock) {
		flock($lock, LOCK_EX);
	}
	// 拿到 lock 後再檢查一次 (避免併發重複轉檔)
	if (is_file($png_path) && filesize($png_path) > 0) {
		if ($lock) { flock($lock, LOCK_UN); fclose($lock); }
		return array('url' => satellite_cache_url($term), 'box' => $box);
	}

	$tmp_dir = $cache_dir . '/tmp_' . getmypid();
	$ok = false;
	$zip_path = $tmp_dir . '/src.zip';
	$gtif_path = null;
	if (@mkdir($tmp_dir, 0755, true)) {
		$zip_data = @file_get_contents($product_url, false, stream_context_create(array('http' => array('timeout' => 120))));
		if ($zip_data !== false && file_put_contents($zip_path, $zip_data) !== false) {
			$zip = new ZipArchive();
			if ($zip->open($zip_path) === true) {
				// 只取出 .gtif/.tif, 寫到 basename 避免 zip-slip 與子目錄路徑問題
				for ($i = 0; $i < $zip->numFiles; $i++) {
					$entry = $zip->getNameIndex($i);
					if (preg_match('/\.(gtif|tif)$/i', $entry)) {
						$content = $zip->getFromIndex($i);
						$gtif_path = $tmp_dir . '/' . basename($entry);
						if ($content !== false) {
							file_put_contents($gtif_path, $content);
						}
					}
				}
				$zip->close();
			}
		}
	}

	if ($gtif_path && is_file($gtif_path)) {
		// 取統計值決定 dstnodata: 可見光反照率與紅外線亮度溫度量級不同
		$info_out = array();
		$info_code = 0;
		exec('/usr/bin/gdalinfo -json -stats ' . escapeshellarg($gtif_path) . ' 2>&1', $info_out, $info_code);
		$info = json_decode(implode("\n", $info_out), true);
		$stats = isset($info['bands'][0]['metadata']['']['STATISTICS_MINIMUM']) ? $info['bands'][0]['metadata'][''] : null;
		if ($stats && isset($stats['STATISTICS_MINIMUM'], $stats['STATISTICS_MAXIMUM'])) {
			$src_min = (float)$stats['STATISTICS_MINIMUM'];
			$src_max = (float)$stats['STATISTICS_MAXIMUM'];
			$warped = $tmp_dir . '/warped.tif';
			$tmp_png = $tmp_dir . '/out.png';
			// 可見光先去雜點: 夜間 B03 有鹽雜訊 65531 (白天合法上限約 11076),
			// 把 >20000 的像素清零再 warp, 否則 bilinear 會把雜點擴散成雪花斑
			$warp_src = $gtif_path;
			if (!$enhance) {
				$masked = $tmp_dir . '/masked.tif';
				$cmdm = sprintf('/usr/bin/gdal_calc.py -A %s --outfile %s --calc %s --overwrite 2>&1',
					escapeshellarg($gtif_path), escapeshellarg($masked), escapeshellarg('where(A>20000,0,A)'));
				$outm = array(); $codem = 0;
				exec($cmdm, $outm, $codem);
				if ($codem === 0 && is_file($masked)) {
					$warp_src = $masked;
				}
			}
			// 資料外圍 (dstnodata) 設成縮放後會變黑的那一端, 再加 -dstalpha 變透明
			$nodata = $src_min;
			$cmd1 = sprintf('/usr/bin/gdalwarp -overwrite -t_srs EPSG:4326 -te %s %s %s %s -ts 2200 0 -r bilinear -dstnodata %s -dstalpha -of GTiff %s %s 2>&1',
				escapeshellarg((string)$box['west']), escapeshellarg((string)$box['south']),
				escapeshellarg((string)$box['east']), escapeshellarg((string)$box['north']),
				escapeshellarg((string)$nodata),
				escapeshellarg($warp_src), escapeshellarg($warped));
			$out1 = array(); $code1 = 0;
			exec($cmd1, $out1, $code1);
			if ($code1 === 0 && is_file($warped)) {
				// 紅外線: 固定物理範圍 -80..40C (19315..31315) 線性縮放, 之後套偽彩色票;
				// 可見光: 固定 0..12000 縮放 + gamma 0.6 (勿用逐張 min/max, 夜間會把雜點拉滿)
				$scale_args = $enhance
					? '-scale 19315 31315 0 255'
					: '-scale 0 12000 0 255 -exponent 0.6';
				$cmd2 = sprintf('/usr/bin/gdal_translate -of PNG -ot Byte %s %s %s 2>&1', $scale_args, escapeshellarg($warped), escapeshellarg($tmp_png));
				$out2 = array(); $code2 = 0;
				exec($cmd2, $out2, $code2);
				if ($code2 === 0 && is_file($tmp_png) && filesize($tmp_png) > 0) {
					if ($enhance) {
						satellite_ir_colorize($tmp_png);
					}
					// 資訊列最後畫 (紅外線先上色再畫, 否則黑條白字會被吃掉)
					$start_ts = strtotime($obs_time);
					if ($start_ts) {
						satellite_draw_caption($tmp_png,
							$enhance ? '衛星圖(紅外線)' : '衛星圖(可見光)',
							date('Y/m/d H:i', $start_ts),
							date('Y/m/d H:i', $start_ts + 600));
					}
					rename($tmp_png, $png_path);
					$ok = is_file($png_path);
				}
			}
		}
	}

	// 清掉舊版 PNG 與暫存目錄, 只留最新一份
	foreach (glob($cache_dir . '/*.png') as $old) {
		if (basename($old) !== $png_name) {
			@unlink($old);
		}
	}
	if (is_dir($tmp_dir)) {
		foreach (glob($tmp_dir . '/*') as $f) {
			@unlink($f);
		}
		@rmdir($tmp_dir);
	}
	if ($lock) { flock($lock, LOCK_UN); fclose($lock); }

	return $ok ? array('url' => satellite_cache_url($term), 'box' => $box) : null;
}

// PNG 由本檔吐出 (快取在 /tmp, 網頁讀不到), KML 的 href 指向 ?term=...&img=1
function satellite_cache_url($term) {
	$query = '?term=' . urlencode($term) . '&img=1';
	if (!empty($_SERVER['HTTP_HOST']) && !empty($_SERVER['SCRIPT_NAME'])) {
		//$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
		$scheme="https";
		return $scheme . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/rainkml.php' . $query;
	}
	return 'rainkml.php' . $query;
}

function satellite_serve($term) {
	$map = array(
		'sat_vis' => array('O-B0055-001', array('north'=>48.0,'south'=>-1.0,'east'=>155.0,'west'=>102.0), false),
		'sat_ir'  => array('O-B0056-001', array('north'=>50.0,'south'=>0.0,'east'=>155.0,'west'=>102.0), true),
	);
	if (!isset($map[$term])) {
		header("HTTP/1.0 404 Not Found");
		exit(0);
	}
	list($dataid, $fallback_box, $enhance) = $map[$term];
	$path = satellite_latest_png($dataid);
	if (!$path) {
		satellite_png($dataid, $fallback_box, $enhance, $term);
		$path = satellite_latest_png($dataid);
	}
	if (!$path) {
		header("HTTP/1.0 503 Service Unavailable");
		exit(0);
	}
	header('Content-Type: image/png');
	header('Content-Length: ' . filesize($path));
	header('Cache-Control: public, max-age=120');
	readfile($path);
	exit(0);
}


$opacity=0.5;
$box=null;
$term=$_GET['term'];
if (!empty($_GET['img'])) {
	satellite_serve($term);
}
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
			$half = 0; // (date("i")<30)? 0 : 3; 不知道半小時圖甚麼時候產生
			$url=sprintf("https://www.cwa.gov.tw/Data/rainfall/%s_%02d%d0.QZJ8.jpg",date("Y-m-d",strtotime("today")),date("H"),$half);
			//$url = sprintf('http://www.cwb.gov.tw/V7/observe/rainfall/Data/hkb%s%d.jpg', date("dG"), $half);
			$type = 'observation';
		break;
		case 'radar':
			$name = '雷達回波';
			// 每10分鐘更新, 無 Cache-Control -> 加時間戳 bust 瀏覽器快取
			$stamp = date('YmdH') . sprintf('%02d', intdiv((int)date('i'), 10) * 10);
			$url = 'https://cwaopendata.s3.ap-northeast-1.amazonaws.com/Observation/O-A0058-003.png?t=' . $stamp;
			$type = 'radar';
			$opacity = 0.7;
		break;
		case 'radar_n':
			// 單雷達 樹林 (北), 每1~2分鐘更新 -> 分鐘時間戳
			$name = '降雨雷達(北)';
			$url = 'https://cwaopendata.s3.ap-northeast-1.amazonaws.com/Observation/O-A0084-001.png?t=' . date('YmdHi');
			$type = 'radar_n';
			$opacity = 0.7;
		break;
		case 'radar_c':
			// 單雷達 南屯 (中)
			$name = '降雨雷達(中)';
			$url = 'https://cwaopendata.s3.ap-northeast-1.amazonaws.com/Observation/O-A0084-002.png?t=' . date('YmdHi');
			$type = 'radar_c';
			$opacity = 0.7;
		break;
		case 'radar_s':
			// 單雷達 林園 (南)
			$name = '降雨雷達(南)';
			$url = 'https://cwaopendata.s3.ap-northeast-1.amazonaws.com/Observation/O-A0084-003.png?t=' . date('YmdHi');
			$type = 'radar_s';
			$opacity = 0.7;
		break;
		case 'sat_vis':
			// 衛星圖(可見光): O-B0055 東亞可見光反照率, 後端轉 PNG (見 satellite_png)
			$sat = satellite_png('O-B0055-001', array('north'=>48.0,'south'=>-1.0,'east'=>155.0,'west'=>102.0), false, 'sat_vis');
			if (!$sat) { header("HTTP/1.0 503 Service Unavailable"); exit(0); }
			$name = '衛星圖(可見光)';
			$url = $sat['url'];
			$box = $sat['box'];
			$type = 'satellite';
			$opacity = 0.7;
		break;
		case 'sat_ir':
			// 衛星圖(紅外線): O-B0056 東亞紅外線亮度溫度, 後端轉 PNG + 色調強化 (見 satellite_png)
			$sat = satellite_png('O-B0056-001', array('north'=>50.0,'south'=>0.0,'east'=>155.0,'west'=>102.0), true, 'sat_ir');
			if (!$sat) { header("HTTP/1.0 503 Service Unavailable"); exit(0); }
			$name = '衛星圖(紅外線)';
			$url = $sat['url'];
			$box = $sat['box'];
			$type = 'satellite';
			$opacity = 0.7;
		break;
		default:
			header("HTTP/1.0 404 Not Found");
			exit(0);
		break;
}
outkml($name,$url,$type,$opacity,$box);
