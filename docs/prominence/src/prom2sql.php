<?php
// 1. 與 prom2kml 同樣 input 要先 sort 過
// 2. head: peak_x,peak_y,peak_h,peak_name,prominence,col_x,col_y,col_h,parent_x,parent_y,parent_h,parent_name
// 3. pgsql
$opt=getopt("i:");
if (!isset($opt['i'])){
	echo "Usage: $argv[0] -i prom.csv > prom.sql\n";
	exit(0);
	
}

$data = file($opt['i']);

$c=0;
foreach($data as $line) {
	$row = explode(",",trim($line));
	if ($row[0]=='peak_x') continue;
	$c++;
	$row[4]=preg_replace("/\-2$/","",$row[4]);
	$key = sprintf("%.04f_%.04f_%d",$row[0],$row[1],$row[3]);
	$peak[$key]=$c;
}

$c=0;
foreach($data as $line) {
	$row = explode(",",trim($line));
	if ($row[0]=='peak_x') continue;
	$c++;
	// add serial number in first col
	array_splice($row,0,0,array($c));
	// print_r($row);
	$row[5]=preg_replace("/\-2$/","",$row[5]);
	$p_coord=sprintf("ST_GeomFromText('POINT(%f %f)',4326)",$row[1],$row[2]);
	if ($row[7]=="")
		$col_coord = 'NULL';
	else
		$col_coord=sprintf("ST_GeomFromText('POINT(%f %f)',4326)",$row[7],$row[8]);
	$row[13]=preg_replace("/\-2$/","",$row[13]);
	$key = sprintf("%.04f_%.04f_%d",$row[10],$row[11],$row[12]);
	$parent_sn=$peak[$key];
	
	printf("INSERT INTO prominence (sn, p_name, p_coord, p_h,prominence, col_name, col_coord, col_h, parent_sn) VALUES (%d, '%s', %s, %d, %d, '%s', %s, %d, %d) returning sn;\n", 
	$row[0],$row[5],$p_coord, $row[4],$row[6], "", $col_coord, $row[9],$parent_sn);
	if (!empty($row[5]))
		printf("UPDATE point3 SET prominence=%d,prominence_index=%d WHERE name='%s';\n", $row[6],$row[0],$row[5]);
}

