<?php
require_once("../config.inc.php");

if (php_sapi_name() != "cli")
        exit("must run from CLI");

$sql = sprintf("select * from prominence WHERE p_name <> ''");
$db=get_conn();

		if (($rs = $db->GetAll($sql)) === false) {
				$errmsg[] = "fail sql: $sql";
			}
			
			foreach($rs as $row){
				$sql = sprintf("UPDATE point3 SET prominence = %d, prominence_index = %d, owner =0 WHERE name = '%s';\n", $row['prominence'],$row['sn'],$row['p_name']);
				echo $sql;
				$db->Execute($sql);
			}
			
