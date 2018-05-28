<?php

require_once("header.inc");
require_once("../config.inc.php");

function pointcrud($inp, $owner_uid, $admin) {
		$db=get_conn();


	switch($inp['action']) {

		case 'list':
			$where=array();
			if (isset($inp['id']) && !empty($inp['id'])) {
				$where[] = "id = " . $inp['id'];
			}
			if ($admin != 1 )  {
				$where[] = sprintf(" owner = %d", $owner_uid);
			}
			// pending approval
			if (isset($inp['contribute']) && $inp['contribute'] == 1 ) {
				$where[] = "contribute=1";
			}
			$where_str = (count($where)>0)? "WHERE ".implode(" AND ",$where) : "";
			$sql = sprintf("SELECT id,name,alias,alias2,type,class,number,status,ele,mt100,checked,comment,ST_X(coord) AS x,ST_Y(coord) as y,owner,contribute,prominence,prominence_index,fclass,fzone,cclass,sname FROM point3 %s ORDER BY %s OFFSET %d LIMIT %d", 
			$where_str, $inp['jtSorting'], $inp['jtStartIndex'], $inp['jtPageSize']);
			$db->SetFetchMode(ADODB_FETCH_ASSOC);
			if (($rs = $db->GetAll($sql)) === false) {
				$errmsg[] = "fail sql: $sql";
			}
			break;
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
			$sql = sprintf("insert into point3 (id, name,alias,type,class,number,status,ele,mt100,checked,comment,coord,owner,contribute,alias2,prominence,prominence_index,fclass,cclass,fzone,sname) values ( DEFAULT, '%s','%s','%s','%s',%s
				,'%s', %s,'%s','%s','%s',%s, %d, %d, '%s',%d, %d, '%s', '%s', '%s','%s') returning id",
					$inp['name'],$inp['alias'],$inp['type'],$inp['class'], $inp['number'],
					$inp['status'],$inp['ele'],$inp['mt100'],$checked,pg_escape_string($inp['comment']),$pp, ($adimin==1)? $inp['owner'] : $owner_uid, $contribute, $inp['alias2'],$inp['prominence'],$inp['prominence_index'],
					$inp['fclass'],$inp['cclass'],pg_escape_string($inp['fzone']), pg_escape_string($inp['sname']));
			if (($rs = $db->GetAll($sql)) === false) {
				$errmsg[] = "fail $sql" . $db->ErrorMsg();
			}
			$newid = $rs[0]['id'];
			$sql = sprintf("SELECT id,name,alias,type,class,number,status,ele,mt100,checked,comment,ST_X(coord) AS x,ST_Y(coord) AS y,contribute,owner,sname,fclass,fzone,cclass FROM point3 WHERE id=%d AND owner=%d",$newid, $owner_uid);
			if (($rs = $db->GetAll($sql)) === false) {
				$errmsg[] = "fail $sql" . $db->ErrorMsg();
			}
			break;
		case 'update':
			if (isset($inp['checked']) && $inp['checked'] == 1)
				$checked = 1; 
			else
				$checked = 0;
			if (isset($inp['contribute']))
				$contribute = intval($inp['contribute']);
			else
				$contribute = 0;
			$pp = sprintf("ST_GeomFromText('SRID=4326;POINT(%f %f)')",$inp['x'],$inp['y']);
			$inp['number'] = (empty($inp['number']))? "NULL": intval($inp['number']);
			$inp['ele'] = (empty($inp['ele']))? "NULL": intval($inp['ele']);
			$inp['alias2'] = (empty($inp['alias2']))? "": intval($inp['alias2']);
			$inp['prominence'] = (empty($inp['prominence']))? 0 : intval($inp['prominence']);
			$inp['prominence_index'] = (empty($inp['prominence_index']))? 0 : intval($inp['prominence_index']);
			// 1. 檢查身份
			$sql = sprintf("select owner from point3 where id=%d",$inp['id']);
			if (($rs = $db->GetAll($sql)) === false) {
				$errmsg[] = "fail $sql" . $db->ErrorMsg();
			}
			$point_owner_id = $rs[0]['owner'];
			if (!$admin) {
				if ($owner_uid != $point_owner_id) {
					$errmsg[] = "not owner";
					break;
				}
				$sql = sprintf("update point3 set name='%s',alias='%s',type='%s',class='%s',number=%s,status='%s',ele=%s,mt100='%s',checked='%s',comment='%s',coord=%s,contribute='%s',mdate=now(),alias2='%s',prominence=%d,prominence_index=%d,fclass='%s',cclass='%s',fzone='%s',sname='%s'  WHERE id=%s and owner=%d",
						$inp['name'],$inp['alias'],$inp['type'],$inp['class'], $inp['number'],
						$inp['status'],$inp['ele'],$inp['mt100'],$checked,pg_escape_string($inp['comment']),$pp,$contribute,  $inp['alias2'], $inp['prominence'],$inp['prominence_index'],
						$inp['fclass'],$inp['cclass'],pg_escape_string($inp['fzone']), pg_escape_string($inp['sname']),$inp['id'],$owner_uid );

			} else {
				$sql = sprintf("update point3 set name='%s',alias='%s',type='%s',class='%s',number=%s,status='%s',ele=%s,mt100='%s',checked='%s',comment='%s',coord=%s,contribute='%s',owner=%d,mdate=now(),alias2='%s',prominence=%d,prominence_index=%d,fclass='%s',cclass='%s',fzone='%s',sname='%s' WHERE id=%s",
						$inp['name'],$inp['alias'],$inp['type'],$inp['class'], $inp['number'],
						$inp['status'],$inp['ele'],$inp['mt100'],$checked,pg_escape_string($inp['comment']),$pp,$contribute, $inp['owner'],$inp['alias2'],$inp['prominence'],$inp['prominence_index'],
						$inp['fclass'],$inp['cclass'],pg_escape_string($inp['fzone']), pg_escape_string($inp['sname']),$inp['id']);
			}
			if (($rs = $db->Execute($sql)) === false) {
				$errmsg[] = "fail sql: $sql";
			}
			$sql = sprintf("SELECT id,name,alias,type,class,number,status,ele,mt100,checked,comment,ST_X(coord) AS x,ST_Y(coord) as y,owner,contribute,prominence,prominence_index,fclass,cclass,fzone,sname FROM point3 WHERE id=%d",$inp['id']);
			if (($rs = $db->GetAll($sql)) === false) {
				$errmsg[] = "fail $sql" . $db->ErrorMsg();
			}

			break;
		case 'delete':
			if (!$admin) 
				$sql = sprintf("DELETE from point3 WHERE id=%d and owner=%d",$inp['id'],$owner_uid);
			else
				$sql = sprintf("DELETE from point3 WHERE id=%d",$inp['id']);
			if (($rs = $db->Execute($sql)) === false) {
				$errmsg[] = "fail sql: $sql";
			}
			break;


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
	else
		$jTableResult['Records'] = $rs;
	print json_encode($jTableResult);



}
list($st, $uid) = userid();
if ($st === true)
	pointcrud($_REQUEST, $uid, is_admin());
	else
	header("Location: ". $site_html_root . "/login.php");
