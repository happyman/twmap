<?php
$uid = 1;
require_once("../config.inc.php");
if(!ob_start("ob_gzhandler")) ob_start();


$r = $_REQUEST['r'];
$x = $_REQUEST['x'];
$y = $_REQUEST['y'];
$detail = $_REQUEST['detail'];
$dup=array();
$found=0;

if ($r>100)
	ajaxerr("too big range");
if ($r==0)
	ajaxerr("empty");
$wpt_data = get_waypoint($x,$y,$r,$detail);
$trk_data = get_track($x,$y,$r,$detail);
// 整理一下 data

if ($wpt_data === false || (count($wpt_data)==0 && count($trk_data)==0)) {
	header('Access-Control-Allow-Origin: *');
	ajaxerr("empty result");
}
if (empty($detail) || $detail == 0 ){
// 傳回高度
    $ele = get_elev(twDEM_path, $y, $x, 1);

	header('Access-Control-Allow-Origin: *');
	ajaxok(array("wpt"=>$wpt_data,"trk"=>$trk_data,"ele"=>$ele));
} else {
	// web page
	echo "<html>";
	echo "<head><title>TWMAP waypoint detail</title><meta charset=\"UTF-8\">";
	echo "<script src='https://code.jquery.com/jquery-2.1.4.min.js'></script>";
	echo "<style>
	/* Document level adjustments */
html {
  font-size: 17px;
}
@media (max-width: 900px) {
  html,table { font-size: 15px; }
}
@media (max-width: 400px) {
  html,table { font-size: 13px; }
}

table, td, th {
    border: 1px solid green;
}

th {
    background-color: green;
    color: white;
}

</style></head>";
	echo "<body><div id='wpt_info' align=center>";
	echo "<hr>以下 GPS 航跡皆為山友無私貢獻分享,請大家上山前做好準備,快樂出門,平安回家!";
	echo "<br>距座標點". $_REQUEST['r'] ."M 的範圍的航點資訊";
	echo "<table>";
	echo "<tr><th width='150px'>名稱<th>顯示<th width='200px'>下載<th>備註<th>評價";
    $ans = array();
	$to_show = array();
	
	foreach($wpt_data as $row){
		$row['type'] = 'wpt';
		$ans[$row['mid']][] = $row;
	}
	foreach($trk_data as $row){
		$row['type'] = 'trk';
		$ans[$row['mid']][] = $row;
	}
	$rank = new map_rank();
	foreach($ans as $mid_to_show => $rows){
		$rowspan=count($rows);
		for($i =0; $i < count($rows); $i++) {
			$row = $rows[$i];
			$wpt_icon =  sprintf("<img src='/twmap/icons/%s' />",($row['type']=='wpt')? 'wpt.png':'trk.png');
			if ($i > 0 ) {
				printf("<tr><td>%s%s</tr>",$wpt_icon, $row['name']);
				continue;
			}
			$show_url = sprintf("<a href='%s/show.php?mid=%s' target=_blank><img src='%s/icons/op_mapshow.png'>%s</a>",$site_html_root,$mid_to_show, $site_html_root,$mid_to_show);
			$rs = $rank->stats($mid_to_show);
			list ($login, $uid) = userid();
			if ($login === false){
				$rank_str = sprintf("<a href='%s/main.php?return=twmap3' target=_top title='登入給予評價'><img src='%s/icons/%s' alt='%s' /></a>",$site_html_root,$site_html_root, $rs['icon'],$rs['text']);
			} else {
				$rank_str = sprintf("<a href='#' data-id='%d'  data-title='%s' data-link='%s' data-ratelink='%s/api/rate.php?mid=%d' data-backurl='%s' class='rating'><img src='%s/icons/%s' alt='%s' /></a>",
				$mid_to_show, $row['title'],$show_url, $site_html_root, $mid_to_show, $_SERVER['REQUEST_URI'], $site_html_root, $rs['icon'], $rs['text']);
			}
			if ( !empty($row['keepon_id']) &&  $row['keepon_id'] != 'NULL' && !is_numeric($row['keepon_id']))
				$record_str = sprintf("<a href='http://www.keepon.com.tw/redirectMap-%s.html' target=_blank><img src='http://www.keepon.com.tw/img/ic_launcher-web.png' height='60px' border=0></a>",$row['keepon_id']);
			else
				$record_str = '';
			
			
			if ($row['flag'] != 2 ) {
				$html_root = $out_html_root . str_replace($out_root, "", dirname($row['filename']));
			
				if (file_exists(map_file_name($row['filename'],'gpx')))
					$gpx_url = sprintf("%s(<a href='%s%s/%s' class=download_track target=_blank>gpx</a>) (<a href='getkml.php?mid=%d' class=download_track target=_blank>kml</a>)",$row['title'],$site_url,$html_root,basename(map_file_name($row['filename'], 'gpx')), $mid_to_show);
				else
					$gpx_url = sprintf("%s (<a href='getkml.php?mid=%d' class=download_track target=_blank>kml</a>)",$row['title'],$mid_to_show);
				printf("<tr><td>%s%s<td rowspan=$rowspan><a href=# class='showkml' data-id='%d' data-title='%s' data-link='%s'>%s</a>
				<td rowspan=$rowspan>%s<td rowspan=$rowspan>%s<td rowspan=$rowspan>%s",
				$wpt_icon,$row['name'],
				$mid_to_show,$row['title'],rawurlencode($show_url),$mid_to_show,
				$gpx_url,(($row['flag'] == 0)? $show_url:"" ). $record_str, $rank_str);
			} else {
				printf("<tr><td>%s%s<td rowspan=$rowspan><a href=# class='showkml' data-id='%d' data-title='%s' data-link='%s'>%s</a>
				<td rowspan=$rowspan><a href='export_mid_gpx.php?mid=%d&kml=1' class=download_track target=_blank>%s (kml)</a>
				<td rowspan=$rowspan><img src='/twmap/icons/op_delete.png' title='原始 gpx 已刪除'/><td rowspan=$rowspan>%s", 
				$wpt_icon,$row['name'], 
				$mid_to_show,$row['title'],rawurlencode($show_url),$mid_to_show, $mid_to_show, 
				$row['title'],$rank_str);
			}
		}
	}	
	/*
	foreach($data as $row){
		if (isset($ans[$row['name']][$row['ele']]) && $ans[$row['name']][$row['ele']][0] == $row['title']){
					$row['dup'] = 1;
					// 尚未刪除的
					if ($row['flag'] == 0) {
						// $dup [ current mid ] = original mid, current still exist
						$dup[$row['mid']] = $ans[$row['name']][$row['ele']][1];
					}
		} else {
					$ans[$row['name']][$row['ele']] = array($row['title'],$row['mid']);
					$row['dup'] = 0;
		}
		$to_show[] = $row;
	}
	
	foreach($to_show as $row){
		
		if ($row['dup'] == 0 ) {
			// 如果有尚未刪除的, 列出尚未刪除的.
			if (count($dup)>0){
				$found = 0;
				foreach($dup as $d_cur => $d_orig){
					//echo  "$d_cur => $d_orig\n";
					if ($row['mid'] == $d_orig){
						$mid_to_show = $d_cur;
						$found = 1;
						break;
					}
				}
				if (!$found)
					$mid_to_show = $row['mid'];
			} else {
				$mid_to_show = $row['mid'];
			}
			if ( !empty($row['keepon_id']) &&  $row['keepon_id'] != 'NULL' && !is_numeric($row['keepon_id']))
				$record_str = sprintf("<a href='http://www.keepon.com.tw/redirectMap-%s.html' target=_blank><img src='http://www.keepon.com.tw/img/ic_launcher-web.png' height='60px' border=0></a>",$row['keepon_id']);
			else
				$record_str = '';
			$wpt_icon = "<img src='/twmap/icons/wpt.png' />";
			$show_url = sprintf("<a href='/twmap/show.php?mid=%s' target=_blank><img src='/twmap/icons/op_mapshow.png'>%s</a>",$mid_to_show);
			if ($row['flag'] != 2 ) {
				$html_root = $out_html_root . str_replace($out_root, "", dirname($row['filename']));
				// $show_url = sprintf("<a href='/twmap/show.php?mid=%s' target=_blank><img src='/twmap/icons/op_mapshow.png'/></a>",$mid_to_show);
				if (file_exists(map_file_name($row['filename'],'gpx')))
					$gpx_url = sprintf("%s(<a href='%s%s/%s' target=_blank>gpx</a>) (<a href='getkml.php?mid=%d' target=_blank>kml</a>)",$row['title'],$site_url,$html_root,basename(map_file_name($row['filename'], 'gpx')), $mid_to_show);
				else
					$gpx_url = sprintf("%s (<a href='getkml.php?mid=%d' target=_blank>kml</a>)",$row['title'],$mid_to_show);
				printf("<tr><td>%s%s<td><a href=# class='showkml' data-id='%d' data-title='%s' data-link='%s'>%s</a><td>%s<td>%s",
				$wpt_icon,$row['name'],
				$mid_to_show,$row['title'],rawurlencode($show_url),$mid_to_show,
				$gpx_url,(($row['flag'] == 0)? $show_url:"" ). $record_str);
			} else {
				printf("<tr><td>%s%s<td><a href=# class='showkml' data-id='%d' data-title='%s' data-link='%s'>%s<td><a href='export_mid_gpx.php?mid=%d&kml=1' target=_blank>%s (kml)</a><td><img src='/twmap/icons/op_delete.png' title='原始 gpx 已刪除'/>", 
				$wpt_icon,$row['name'], $mid_to_show,$row['title'],rawurlencode($show_url), $mid_to_show, $mid_to_show, $row['title']);
			}


		}
	}
	if (count($trk_data) > 0 ) {
		foreach($trk_data as $row) {
			$mid_to_show = $row['mid'];
			if ( !empty($row['keepon_id']) &&  $row['keepon_id'] != 'NULL' && !is_numeric($row['keepon_id']))
				$record_str = sprintf("<a href='http://www.keepon.com.tw/redirectMap-%s.html' target=_blank><img src='http://www.keepon.com.tw/img/ic_launcher-web.png' height='60px' border=0></a>",$row['keepon_id']);
			else
				$record_str = '';
			$trk_icon = "<img src='/twmap/icons/trk.png'/>";
			$show_url = sprintf("<a href='/twmap/show.php?mid=%s' target=_blank><img src='/twmap/icons/op_mapshow.png'>%s</a>",$mid_to_show);
			if ($row['flag'] != 2 ) {
				$html_root = $out_html_root . str_replace($out_root, "", dirname($row['filename']));
				
				if (file_exists(map_file_name($row['filename'],'gpx')))
					$gpx_url = sprintf("%s(<a href='%s%s/%s' target=_blank>gpx</a>) (<a href='getkml.php?mid=%d' target=_blank>kml</a>)",$row['title'],$site_url,$html_root,basename(map_file_name($row['filename'], 'gpx')), $mid_to_show);
				else
					$gpx_url = sprintf("%s (<a href='getkml.php?mid=%d' target=_blank>kml</a>)",$row['title'],$mid_to_show);
				printf("<tr><td>%s%s<td><a href=# class='showkml' data-id='%d' data-title='%s' data-link='%s'>%s</a><td>%s<td>%s",
						$trk_icon,$row['name'],
						$mid_to_show,$row['title'],rawurlencode($show_url),$mid_to_show,
						$gpx_url,(($row['flag'] == 0)?$show_url:"") . $record_str );
			} else {
				printf("<tr><td>%s%s<td><a href=# class='showkml' data-id='%d' data-title='%s' data-link='%s'>%s<td><a href='export_mid_gpx.php?mid=%d&kml=1' target=_blank>%s (kml)</a><td><img src='/twmap/icons/op_delete.png' title='原始 gpx 已刪除'/>",
				$trk_icon, $row['name'], 
				$mid_to_show,$row['title'],rawurlencode($show_url),$mid_to_show, $mid_to_show, $row['title']);
			}

		}
	}
	*/
	echo "</table>";
	echo "<hr>";
	if (is_admin()) {
?>
	mid:<input type=text id='kmlshowmid' name='kmlshowmid'><input type=button value="Show" id='kmlbtnshow'><input type=button value="add" id='gpximport'>
	<br><span id="response"></span>
<?php
	}
?>
<script>
$('document').ready(function(){ 
		$('.showkml').each(function(index) {
			$(this).click(function(event) {
				event.preventDefault();
				parent.showmapkml($(this).data('id'),$(this).data('title'),$(this).data('link'),false);
				});
			});
		$('.rating').each(function(index) {
			$(this).click(function(event){
				event.preventDefault();
				parent.showmapkml($(this).data('id'),$(this).data('title'),$(this).data('link'),false);
				parent.open_ranking_dialog($(this).data('id'),$(this).data('ratelink'),$(this).data('backurl'));
				
			});
		});
		// download warnning
		$('.download_track').each(function(index) {
			$(this).click(function(event) {
				event.preventDefault();
				if (confirm("地圖產生器所蒐集之行跡檔為山友貢獻,路線僅供參考之用,登山前請做好萬全準備,並且登山安全自行負責。 不同意請【取消】下載。")){
					window.location = $(this).attr('href');
				}
				});
		});
		$('#kmlbtnshow').click(function() {
			console.log("display mid:" + $("#kmlshowmid").val());
			if ($("#kmlshowmid").val()) 
				parent.showmapkml($("#kmlshowmid").val(),"","",true);
			});
		});
		
		$('#gpximport').click(function() {
			var mid = $("#kmlshowmid").val();
			console.log("import mid:" + mid);
			if (mid){
				var twmap_gpx_url = "<?php printf("%s/api/twmap_gpx.php",$site_html_root);?>";
				 $.ajax({
				 url: twmap_gpx_url, 
				 data: { "action": "add", "mid": mid},
				 type: 'POST',
				 success: function (data) {
					 if (data.ok === true)
						$('#response').append('<li>' + data.rsp + '</li>');
					else
						$('#response').append('<li style="color:red">' + data.rsp.msg + '</li>')
				 },
				 error: function (jxhr, msg, err) {
					 $('#response').append('<li style="color:red">' + msg + '</li>');
				 }
				 });
			}
		});
		 $('#kmlshowmid').keypress(function(e){
      if(e.keyCode==13)
      $('#kmlbtnshow').click();
    });
		
</script>
</div>
</html>
<?php
}

/*
   $tlx = $_REQUEST['tlx'];
   $tly = $_REQUEST['tly'];
   $brx = $_REQUEST['brx'];
   $bry = $_REQUEST['bry'];
   $gpx = (isset($_REQUEST['gpx'])) ? intval($_REQUEST['gpx']) : 0 ;
   $keys = (!empty($_REQUEST['keys'])) ? explode(",",$_REQUEST['keys']):array();
// 最多查幾筆
$maxkeys = ($_REQUEST['maxkeys']) ? intval($_REQUEST['maxkeys']) : 0;


if (empty($tlx) || empty($tly) || empty($brx) || empty($bry)) {
ajaxerr("insufficent parameters");
}

$bounds = array("tlx" => $tlx, "tly" => $tly, "brx" => $brx, "bry" => $bry );

$data = map_overlap($bounds, $gpx, $maxkeys);
/*
$mids = array();
$ret = array("add" => array(), "del" => array(), "all" => array(), "count"=> array("add" => 0 , "del" => 0 ));
foreach($data as $map) {
if ($map['hide'] == 1) continue;
if (!in_array($map['mid'],$keys)) {

$content =  sprintf("<a href='%s%s/show.php?mid=%s&info=%s&version=%d' target=_twmap>%s<img src='img/map.gif' title='地圖產生器' border=0/></a>",$site_url,$site_html_root, $map['mid'], urlencode(sprintf("%dx%s-%dx%d",$map['locX'],$map['locY'],$map['shiftX'],$map['shiftY'])), $map['version'], $map['title']);
if ($map['keepon_id'])
$content .= sprintf("<a href='http://www.keepon.com.tw/DocumentHandler.ashx?id=%s' target='_keepon'>%s</a>",$map['keepon_id'],"連結登山補給站");


$ret['add'][$map['mid']] = array('url' => sprintf('%s%s/api/getkml.php?mid=%d',$site_url, $site_html_root, $map['mid']),
'desc' =>  $content );
} 
$ret['all'][] = $map['mid'];
$mids[] = $map['mid'];
}
foreach($keys as $key) {
if (!in_array($key, $mids)) {
$ret['del'][$key] = 1;
}
}

$ret['count']['add'] = count($ret['add']);
$ret['count']['del'] = count($ret['del']);
ajaxok($ret);
 */
