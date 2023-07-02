<?php
require_once("../config.inc.php");
if(!ob_start("ob_gzhandler")) ob_start();

if (php_sapi_name() == "cli")
	$keyword = $argv[1];
else
	$keyword = $_REQUEST['name'];


if (empty($keyword)){
	echo "請輸入要搜尋的名稱";
	exit(0);
}
?>
<html>
<head><title>POI search</title>
<!--// https://datatables.net/download/  include jquery 3 -->
<link href="https://cdn.datatables.net/v/dt/jq-3.6.0/dt-1.13.4/datatables.min.css" rel="stylesheet"/> 
<script src="https://cdn.datatables.net/v/dt/jq-3.6.0/dt-1.13.4/datatables.min.js"></script>

<style>
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
.loc {
	background-color: yellow;
	cursor: pointer;
}
</style>
</head>
<body>
<h2 align=right>魯地圖 POI search</h2>
<hr>
<form method=get><input id="keyword" type=text name="name" value="<?php echo $_REQUEST['name'];?>"><input type=submit value="POI搜尋">
<?php

class poi_search {
	var $file = "/home/mountain/mapsforge/cur/MOI_OSM_Taiwan_TOPO_Rudy.poi";
	var $db;
	var $cat; // category name
	var $cat_desc_arr; // category description
	function __construct($o=array()){
		if (isset($o['file']) && file_exists($o['file'])) $this->file=$o['file'];
		try {
		$this->db = new PDO("sqlite:".$this->file);
		$this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		} catch (PDOException $e) {
			echo "連接失敗: " . $e->getMessage();
			return false;
		}
		$this->load_category();
	}
	function load_category(){
		$stmt = $this->db->query("select * from poi_categories");
		$result = $stmt->fetchAll();
		foreach($result as $row){
			$this->cat[$row['id']] = array($row['id'],$row['name'],$row['parent']);
			  if ($row['name'] == 'root')
                                       $rootid = $row['id'];
		}
		foreach($result as $row) {
			$cur = $this->cat[$row['id']];
			//echo  "cur=" . $row['id'] . "<br>";
			while ($cur[0] != $rootid){
				$this->cat_desc_arr[$row['id']][] = $cur[1];
				$cur = $this->cat[$cur[2]];
				//echo "cur in loop=" . $cur[0] . "<br>";
			}
		}
	}
	function search($name){
		// version 1
		//$sql = sprintf("select A.id, A.data, A.category, (B.minLat+B.maxLat)/2 as Lat, (B.minLon+B.maxLon)/2 as Lon from poi_data A, poi_index B where A.id=B.id and data like '%%%s%%' order by category limit 300", pg_escape_string($name));
		// for better search performance, create new table instead of sub-query
		$sql_table = sprintf("create table if not exists poi_data1 AS select id,data,category from poi_data inner join poi_category_map using (id)");
		$this->db->query($sql_table);
		$sql = sprintf("select A.id as id, A.data as data, A.category as category, (B.minLat+B.maxLat)/2 as Lat, (B.minLon+B.maxLon)/2 as Lon from poi_data1 A, poi_index B where A.id=B.id and data like '%%%s%%' order by category limit 50", pg_escape_string($name));
		$stmt = $this->db->query($sql);
		$result= $stmt->fetchAll(PDO::FETCH_ASSOC);
		return $result;
	}
	function category_desc($id){
		return implode("->",array_reverse($this->cat_desc_arr[$id]));
	}
}

// from globle config
$ss = new poi_search(array( "file"=> $CONFIG['poi_file'] ));
if ($ss === false ){
	echo "請安裝 POI 檔";
	exit(0);
}
$result = $ss->search($keyword);
error_log(print_r($result,true));
if (count($result)>0) {
	echo "<br><table id='poitable' style='width: 100%'><thead><tr><td>data<td>category<td>location<td>行政區</tr></thead><tbody>";
//print_r($result);
//
	for($i=0; $i<count($result); $i++) {
			$data = $result[$i];
			$town_name = [];
			$towns = get_administration($data['Lon'],$data['Lat'], "town");
			if ($towns || count($towns) > 0 ) {
				foreach($towns as $town) {
					$town_name[] = sprintf("%s%s%s",$town['countyname'],$town['townname'],($town['permit']=='t')? "(入山)" : "" );
				}
				$town_string = implode(",",$town_name);
			} else {
				$rown_string = "未知";
			}
			$loc = sprintf("%f,%f",$data['Lat'],$data['Lon']);
        	printf("\n<tr><td><a href=# onclick='javascript:flyto(\"%s\");return false'>%s</a><td>%s<td><a href=# onclick='javascript:flyto(\"%s\");return false'>%s</a><td>%s</tr>"
			,$loc,$data['data'],htmlentities($ss->category_desc($data['category'])),$loc,$loc,$town_string);
	}
	
	?>
	</tbody></table>
<script>
function flyto(name){
		$("#tags",parent.document).val(name);
		$("#goto",parent.document).trigger('click');
}
$(document).ready(function() {
	$('table#poitable').dataTable( {
            bSort: true,
			lengthChange: false,
			pageLength: 100,
			dom: "lrtip",
           aoColumns: [ { sTitle: "keyword", bSortable: false, sWidth: '100px' }, 
					    { sTitle: '分類', bSortable: true, sWidth: '200px' }, 
						{ sTitle: '座標', bSortable: false }, 
						{ sTitle: '行政區', bSortable: true} ],
        // "scrollY":        "200px",
        "scrollCollapse": true,
        "info":           true,
        "paging":         true
    } );

});
</script>
	<?php
	
} else {
	echo "<br>查沒有結果, 是否試試看 google 地理編碼?<p>";
	?>
	<button onclick="parent.call_geocoder($('#keyword').val());">好,找找看</button>
	<?php
}


