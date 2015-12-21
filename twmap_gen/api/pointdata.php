<?php
require_once("../config.inc.php");
if(!ob_start("ob_gzhandler")) ob_start();


$id = (isset($_REQUEST['id']))? $_REQUEST['id'] : NULL;
// id == ALL
// lastest == 5
if (isset($_REQUEST['lastest'])) {
	$result = get_lastest_point(intval($_REQUEST['lastest']));
	switch($_REQUEST['err']) {
		case 1:
		 $str="請輸入要尋找的地標";
		  break;
                case 2:
		 $str="不在台澎範圍";
		 break;
                case 3:
		 $str="找不到喔! 座標格式: <ul><li> twd67 X,Y 如 310300,2703000 <li> t97 X/Y 如 310321/2702000 <li>含小數點經緯度 lat,lon 24.430623,121.603503</ul>";
		 break;
                case 4:
		 $str="cached: 不在台澎範圍";
		 break;
		default:
		$str="";
		break;
	}
	echo "<p align=right>最新興趣點<p><h2>$str</h2>";
	for($i=0; $i<count($result); $i++) {
		printf("<p><a href=#>%s</a>",$result[$i]['name']);
	}
	?>
<script src="https://code.jquery.com/jquery-2.1.4.min.js"></script>
<script>
$('a').each(function(index) {
       $(this).click(function(event) {
                event.preventDefault();
		var name=$(this).text();
		$("#tags",parent.document).val(name);
        	$("#goto",parent.document).trigger('click');
	});
});
</script>
	<?php
	exit;
} else {
	$result = get_point($id);
}

/*
角色
管理者: 回傳所有點, 可編輯所有點
使用者: 回傳公用點,個人點, 編輯個人點
*/
list($st,$info) = userid();
if ($id !== 'ALL') {
	$result[0]['story'] = tell_story($result[0]);
	if ($st === true) 
		$result[0]['info'] = $info;
	// 補上高度資料
	if ($result[0]['ele'] == 0 ) {
		$twDEM_path = "../db/DEM/twdtm_asterV2_30m.tif";
		$ele = get_elev($twDEM_path, $result[0]['y'], $result[0]['x'], 1);
		if ($ele > -1000 ) {
			$result[0]['ele'] = $ele;
		} else {
			unset($result[0]['ele']);
		}
	}

	
} else {
	
}
// 載入哪些點?
foreach($result as $row) {
	unset($row['coord']);
	// 已登入
	if ($st === true ) {
		if (is_admin()) {
			$rows[] = $row;
		} else if ($row['owner'] == $info || $row['owner'] == 0 ) {
			$rows[] = $row;
		}
	} else {
		if ($row['owner'] == 0 )
			$rows[] = $row;
	}
}

header('Content-Type: application/json');
echo json_encode($rows);
exit(0);


function tell_story($d) {
	global $site_html_root;
	
	$a = "";
	if (isset($d['ele'])) {
		$a= sprintf("<br>高度: %s M",$d['ele']);
	}

	$a .= sprintf("<br>類別: %s", $d['type']);
	if ($d['class'] > 1 && $d['class'] < 4 ) {
		$a .= sprintf("%d-%d", $d['class'],$d['number']);
	}
	if (strstr($d['type'],'點'))
		$a .= sprintf("<br>狀態: %s", $d['status']);
	if (!empty($d['alias']))
		$a .= sprintf("<br>別名: %s", $d['alias']);

	if ($d['mt100']>0) {
		$a .= "<br>我是";
		$astr=array();
		if ($d['mt100'] & 1 )
			$astr[] = "百岳";
		if ($d['mt100'] & 2 )
			$astr[] = "小百岳";
		if ($d['mt100'] & 4 )
			$astr[] = "百名山";
		$a .= sprintf("%s",implode(",",$astr));
	}
	if (!empty($d['comment']))
		$a .= sprintf("<br>註解: %s", $d['comment']);
	$a .= sprintf("<br>資料: %s",($d['checked'])? "ok" : "待查");
	list($st,$info) = userid();
	// 1. 未登入
	if ($st === false) {
		// do nothing
	} else {
		if (is_admin() == 1 || $info==$d['owner'])  {
		$a .= sprintf("<br>編輯<a href=# onClick=\"showmeerkat('%s/admin/index.php?id=%d',{});return false\">本點</a>",$site_html_root,$d['id']);
		$a .= sprintf("<br><a href=# onClick=\"showmeerkat('%s/admin/index.php',{});return false\">我的興趣點</a>",$site_html_root);
		} else {
		$a .= sprintf("<br><a href=# onClick=\"showmeerkat('%s/admin/index.php',{});return false\">我的興趣點</a>",$site_html_root);
		}
	}
	return $a;
}
