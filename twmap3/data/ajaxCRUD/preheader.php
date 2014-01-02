<?php

#a session variable is set by class for much of the CRUD functionality -- eg adding a row
session_start();

require_once("../../lib/functions.inc.php");
#for pesky IIS configurations without silly notifications turned off
error_reporting(E_ALL - E_NOTICE);

#this is the info for your database connection
####################################################################################
##
####################################################################################

$db = mysql_connect($CONFIG['db']['host'], $CONFIG['db']['user'], $CONFIG['db']['pass']);

if(!$db){
	echo('Unable to connect to db' . mysql_error());
	exit;
}
mysql_select_db($CONFIG['db']['db']);

# what follows are custom database handling functions - required for the ajaxCRUD class
# ...but these also may be helpful in your application(s) :-)
if (!function_exists('q')) {
	function q($q, $debug = 0){
		$r = mysql_query($q);
		if(mysql_error()){
			echo mysql_error();
			echo "$q<br>";
		}

		if($debug == 1)
			echo "<br>$q<br>";

		if(stristr(substr($q,0,8),"delete") ||	stristr(substr($q,0,8),"insert") || stristr(substr($q,0,8),"update")){
			if(mysql_affected_rows() > 0)
				return true;
			else
				return false;
		}
		if(mysql_num_rows($r) > 1){
			while($row = mysql_fetch_array($r)){
				$results[] = $row;
			}
		}
		else if(mysql_num_rows($r) == 1){
			$results = array();
			$results[] = mysql_fetch_array($r);
		}

		else
			$results = array();
		return $results;
	}
}

if (!function_exists('q1')) {
	function q1($q, $debug = 0){
		$r = mysql_query($q);
		if(mysql_error()){
			echo mysql_error();
			echo "<br>$q<br>";
		}

		if($debug == 1)
			echo "<br>$q<br>";
		$row = @mysql_fetch_array($r);

		if(count($row) == 2)
			return $row[0];
		else
			return $row;
	}
}

if (!function_exists('qr')) {
	function qr($q, $debug = 0){
		$r = mysql_query($q);
		if(mysql_error()){
			echo mysql_error();
			echo "<br>$q<br>";
		}

		if($debug == 1)
			echo "<br>$q<br>";

		if(stristr(substr($q,0,8),"delete") ||	stristr(substr($q,0,8),"insert") || stristr(substr($q,0,8),"update")){
			if(mysql_affected_rows() > 0)
				return true;
			else
				return false;
		}

		$results = array();
		$results[] = mysql_fetch_array($r);
		$results = $results[0];

		return $results;
	}
}
