<?php

require_once("header.inc");
require_once("../config.inc.php");

function pointcrud($inp, $uid, $is_admin) {
		$db=get_conn();

if (!isset($inp['jtSorting']))
				$inp['jtSorting'] = 'sn';
			
$inp['jtPageSize'] = (isset($inp['jtPageSize']))? $inp['jtPageSize'] : 100;
$inp['jtStartIndex'] = (isset($inp['jtStartIndex']))? $inp['jtStartIndex'] : 0;
$inp['type'] = (isset($inp['type']))? $inp['type'] : "";
// overwrite
$inp['jtPageSize'] = (isset($inp['limit'])&& $inp['jtPageSize']==100)? $inp['limit'] :  $inp['jtPageSize'];
if ($inp['jtStartIndex'] == 0 && isset($inp['start']))
	$inp['jtStartIndex'] = $inp['start'];
switch($inp['action']) {
/*
action=list&type=h3000|h2000|h1000 limit 100 or no limit
action=list&type=all (list all)
*/
		case 'list':
		case 'exportcsv':
			$where=array();
			if ($inp['type'] == 'h3000')
				$where[] = sprintf("P.p_h >= 3000");
			else if ($inp['type'] == 'h1000'){
				$where[] = sprintf("P.p_h <= 1000");
			} else if ($inp['type'] == 'h2000'){
				$where[] = sprintf("P.p_h > 1000 AND P.p_h < 3000");
			}
			if (!isset($inp['jtSorting']))
				$inp['jtSorting'] = 'sn';
			
					
			$where_str = (count($where)>0)? "WHERE ".implode(" AND ",$where) : "";
			$db->SetFetchMode(ADODB_FETCH_ASSOC);
			$sql = sprintf("SELECT P.sn,row_number() over (order by P.sn) as rnum,P.p_name, P.p_h, P.prominence, ST_Y(P.p_coord)||' '||ST_X(P.p_coord) AS p_loc,P.col_h,ST_Y(P.col_coord)||' '||ST_X(P.col_coord) AS col_loc,
                           P1.p_name as parent_name, ST_Y(P1.p_coord)||' '||ST_X(P1.p_coord) AS parent_loc 
						   FROM prominence P  LEFT OUTER JOIN  prominence P1 ON P.parent_sn = P1.sn %s ORDER BY %s OFFSET %d LIMIT %d",
			$where_str, $inp['jtSorting'], $inp['jtStartIndex'], $inp['jtPageSize']);
			if (($rs = $db->GetAll($sql)) === false) {
				$errmsg[] = "fail sql: $sql";
			}
			if ($inp['action'] == 'exportcsv'){
				if (!empty($errmsg)) {
					header("400 error query");
					exit;
				}
				$filename=sprintf("promlist_%s.xls",date('Ymd-His'));
				header("Content-type: application/vnd.ms-excel");
				header('Content-Disposition: attachment; filename="'.$filename.'"');
				header('Expires: 0');
				$tsv = new ExportDataExcel('string');
				$tsv->filename = $filename;
				$tsv->initialize();
				$tsv->addRow(array("SN","ROW","P_NAME","P_ELE","PROMINENCE","P_X","P_Y","COL_X","COL_Y","COL_ELE","PARENT_NAME","PARENT_X","PARENT_Y"));
				foreach($rs as $row) {
					list($py,$px)=explode(" ",$row['p_loc']);
					if ($row['col_loc']){
					 list($py1,$px1)=explode(" ",$row['col_loc']);
					 list($py2,$px2)=explode(" ",$row['parent_loc']);
					} else {
						$px1=0;$px2=0;$py1=0;$py2=0;
					}
					$data = array($row['sn'],$row['rnum'],$row['p_name'],$row['p_h'],$row['prominence'],$px,$py,$px1,$px2,$row['col_h'],$row['parent_name'],$px2,$py2);
					$tsv->addRow($data);
					
				}
				$tsv->finalize();
	
				print $tsv->getString();
				exit(0);
			}
			break;
			/*
		case 'create':
			$sql = sprintf("SELECT count(*) as count FROM point3 WHERE owner=%d",$owner_uid);
			if (($rs = $db->GetAll($sql)) === false) {
				$errmsg[] = "fail $sql" . $db->ErrorMsg();
			}
			// 限制最多點數
			if ($rs[0]['count'] >= 100 ) {
				$errmsg[] = "too many rows: limit 100";
				break;
			}
			if (isset($inp['checked']))
				$checked = 1; 
			else
				$checked = 0;
			if (isset($inp['contribute']))
				$contribute = 1; 
			else
				$contribute = 0;
			$pp = sprintf("ST_GeomFromText('SRID=4326;POINT(%f %f)')",$inp['x'],$inp['y']);
			$inp['number'] = (empty($inp['number']))? "NULL": intval($inp['number']);
			$inp['ele'] = (empty($inp['ele']))? "NULL": intval($inp['ele']);
			$sql = sprintf("insert into point3 (id, name,alias,type,class,number,status,ele,mt100,checked,comment,coord,owner,contribute,alias2) values ( DEFAULT, '%s','%s','%s','%s',%s
				,'%s', %s,'%s','%s','%s',%s, %d, %d, '%s') returning id",
					$inp['name'],$inp['alias'],$inp['type'],$inp['class'], $inp['number'],
					$inp['status'],$inp['ele'],$inp['mt100'],$checked,pg_escape_string($inp['comment']),$pp, $owner_uid, $contribute, $inp['alias2'] );
			if (($rs = $db->GetAll($sql)) === false) {
				$errmsg[] = "fail $sql" . $db->ErrorMsg();
			}
			$newid = $rs[0]['id'];
			$sql = sprintf("SELECT id,name,alias,type,class,number,status,ele,mt100,checked,comment,ST_X(coord) AS x,ST_Y(coord) AS y,contribute,owner FROM point3 WHERE id=%d AND owner=%d",$newid, $owner_uid);
			if (($rs = $db->GetAll($sql)) === false) {
				$errmsg[] = "fail $sql" . $db->ErrorMsg();
			}
			break;
			*/
		case 'update':
			$orig_rs = $inp;
			if ($is_admin){
				$sql = sprintf("UPDATE prominence SET p_name = '%s' WHERE sn=%d",pg_escape_string($inp['p_name']),$inp['sn']);
				if (($rs = $db->Execute($sql)) === false) {
					$errmsg[] = "fail $sql" . $db->ErrorMsg();
				}
				$rs = $orig_rs;
			} else {
				$errms[] = "only admin can edit";
			}
		
			break;
			/*
		case 'delete':
			if (!$admin) 
				$sql = sprintf("DELETE from point3 WHERE id=%d and owner=%d",$inp['id'],$owner_uid);
			else
				$sql = sprintf("DELETE from point3 WHERE id=%d",$inp['id']);
			if (($rs = $db->Execute($sql)) === false) {
				$errmsg[] = "fail sql: $sql";
			}
			break;

*/
	}
	if (count($errmsg) > 0 ) {
		$jTableResult = array();
		$jTableResult['Result'] = "ERROR";
		$jTableResult['Message'] = implode("|",$errmsg);
		print json_encode($jTableResult);
		return;

	}
	$jTableResult = array();
	$jTableResult['Result'] = "OK";
	if ($inp['action'] == 'create' || $inp['action'] == 'update')
		$jTableResult['Record'] = $rs[0];
	else{
		$jTableResult['Records'] = $rs;
		$jTableResult['sql']=$sql;
		$jTableResult['param']=print_r($inp,true);
	}
	print json_encode($jTableResult);



}
list($st, $uid) = userid();
if ($st === true)
	pointcrud($_REQUEST, $uid, is_admin());
	else
header("Location: ". $site_html_root . "/login.php");
	
