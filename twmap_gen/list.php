<?php
// $Id: list.php 354 2013-09-12 09:54:20Z happyman $
session_start([
        'read_and_close' => true,
]);

if (empty($_SESSION['loggedin'])) {
	header("Location: login.php");
	exit(0);
}

require_once("config.inc.php");
// 輸出基本表格
if (!isset($_REQUEST['ajax']))  {
	$count = map_list_count($_SESSION['uid']);
	$user = fetch_user($_SESSION['mylogin']);
	if ($user === false){
		header("Location: login.php");
		exit(0);
	}
	$ps = ($count /  $user['limit'] )*100;
	$psinfo = sprintf(" %d / %d 如不夠用可刪除舊圖", $count, $user['limit']);

	$smarty->assign("ps",$ps);
	$smarty->assign("psinfo",$psinfo);

	$smarty->assign("title", "地圖列表");
	echo $smarty->fetch("list.html");
} 
// 輸出 ajax
// Sort?
else {
	$maps = map_list_get($_SESSION['uid'],'DESC');
/*
	$start = intval($_REQUEST['iDisplayStart']);
	$limit = intval($_REQUEST['iDisplayLength']);
	$result_map = array();
	for($i=$start; $i< $start + $limit; $i++) {
		if (isset($maps[$i]))
			$result_map[] = $maps[$i];
	}
	$response['sEcho'] = intval($_REQUEST['sEcho']);
	$response['iTotalRecords'] = count($maps);
	$response['iTotalDisplayRecords'] = count($maps);
	$response['aaData'] = create_rows($result_map,$start);

	//print_r($_REQUEST);
	exit(json_encode($response));
	*/
	for($i=0; $i< count($maps); $i++) {
		if (isset($maps[$i]))
			$result_map[] = $maps[$i];
	}
	$response['data'] = create_rows($result_map,0);
	exit(json_encode($response));
}
function versionname($ver){
	if ($ver == 2016)
		return "魯地圖";
	else if ($ver == 3)
		return "經建3";
	else
		return "經建1";
}
function create_rows($maps,$startsn=0) {
	global $TWMAP3URL;
	$td = array();
	for($i=0;$i<count($maps);$i++) {
		if ($maps[$i]['gpx'] == 1) {
			// 產生瀏覽連結
			$gpx = sprintf("<span id='icon_mapshow' onclick=\"map_action('mapshow','%s?goto=%d,%d&show_kml_layer=1')\"></span>",$TWMAP3URL, $maps[$i]['locX'] + $maps[$i]['shiftX']*500, $maps[$i]['locY']-$maps[$i]['shiftY']*500 );
		} else $gpx = "";
		$rows[$i]['mid'] = sprintf("<span id='icon_save_link' onclick='map_action(\"view\",%d);'>%d</span>", $maps[$i]['mid'],$maps[$i]['mid']);
		$rows[$i]['sn'] = $i+1+$startsn;
		$rows[$i]['date'] =  preg_replace("#\.\d+$#","",$maps[$i]['cdate']);
		$rows[$i]['title'] = sprintf("%s <span id='icon_save_link' onclick='map_action(\"view\",%d);'>%s</span>", $gpx, $maps[$i]['mid'],$maps[$i]['title']);

		$rows[$i]['x'] = $maps[$i]['locX'];
		$rows[$i]['y'] = $maps[$i]['locY'];
		$rows[$i]['grid'] = sprintf("%dx%d",$maps[$i]['shiftX'], $maps[$i]['shiftY']);
		/*
		$rows[$i]['pages'] = $maps[$i]['pageX'] * $maps[$i]['pageY'];
		//if (map_file_exists($maps[$i]['filename'], 'pdf'))
		if (strtotime($rows[$i]['date'] ) > strtotime('2013-06-27'))
			$rows[$i]['pagetype'] = '<img src="imgs/pdf_icon.png" width="32px" alt="PDF" />';
		else

			$rows[$i]['pagetype'] = (determine_type($maps[$i]['shiftX'], $maps[$i]['shiftY'], 1) == 'A4R')? '<img src="imgs/a4r.png" width="20px" alt="橫印" title="A4橫" />' : "";
			*/
		$rows[$i]['version'] =  sprintf("TWD%s %s",$maps[$i]['datum'],versionname($maps[$i]['version']));
		$rows[$i]['size'] = humanreadable($maps[$i]['size']);

		$button_class='class="fg-button ui-state-default ui-corner-all"';
		$op = array();
		$op[] = sprintf("<span id='icon_delete' title='永久刪除'
			onclick=\"map_action('del',%d)\"></span>", $maps[$i]['mid']);
		// 如果地圖已經過期
		if ($maps[$i]['flag'] == 1 ) {
			// 看看是不是澎湖
			if (strstr($maps[$i]['filename'],'v3p') || strstr($maps[$i]['filename'],'v2016p')) 
					$ph = 1; else $ph = 0;
				if ($maps[$i]['gpx'] == 1 ) {
					$param = sprintf("mid=%s&title=%s&filename=%s",$maps[$i]['mid'],urlencode($maps[$i]['title']),$maps[$i]['filename']);
					$op[] = sprintf("<span id='icon_recreate' title=\"mid=%d 重新產生\" 
						onclick=\"map_action('recreate_gpx','%s')\"></span>", $maps[$i]['mid'],$param);
				} else {
					$param = sprintf("x=%d&y=%d&shiftx=%d&shifty=%d&title=%s&version=%d&ph=%d&datum=TWD%s",$maps[$i]['locX']/1000,$maps[$i]['locY']/1000,$maps[$i]['shiftX'],$maps[$i]['shiftY'],urlencode($maps[$i]['title']),$maps[$i]['version'],$ph,$maps[$i]['datum']);
					$op[] = sprintf("<span id='icon_recreate' title=\"mid=%d 重新產生\" 
						onclick=\"map_action('recreate','%s')\"></span>",$maps[$i]['mid'],  $param);

			}
		} else {
			$op[] = sprintf("<span id='icon_recycle' onclick=\"map_action('expire',%d)\" title=\"清理空間\"></span>",$maps[$i]['mid']);
			$op[] = sprintf("<span id='icon_save' onclick=\"map_action('view',%d)\" title=\"mid=%d%s 檢視下載\"></span>",$maps[$i]['mid'], $maps[$i]['mid'],($maps[$i]['keepon_id'])? ",keepon=".$maps[$i]['keepon_id'] : "");
			$op[] = sprintf("<span id='icon_browse' onclick=\"map_action('link',%d)\" title='外部連結'></span>\n", $maps[$i]['mid']);
		}
		$rows[$i]['op'] = implode("\n",$op);
		//$td[$i] = array($rows[$i]['sn'], $rows[$i]['date'], $rows[$i]['title'],$rows[$i]['x'],$rows[$i]['y'], $rows[$i]['grid'],sprintf("%s %s",$rows[$i]['pages'],$rows[$i]['pagetype']),$rows[$i]['version'],$rows[$i]['size'],$rows[$i]['op']);
		$td[$i] = array($rows[$i]['sn'], $rows[$i]['mid'],$rows[$i]['date'], $rows[$i]['title'],$rows[$i]['x'],$rows[$i]['y'], $rows[$i]['grid'],$rows[$i]['version'],$rows[$i]['size'],$rows[$i]['op']);
	}


	return $td;
}
