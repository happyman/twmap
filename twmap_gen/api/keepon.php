<html>
<html>
<head>
<title>keepon test</title>
<meta content="text/html; charset=utf-8" http-equiv="Content-Type">
<meta content="Thu, 01 Jan 1970 00:00:00 GMT" http-equiv="Expires">
<meta content="no-store, no-cache, must-revalidate" http-equiv="Cache-Control">
<meta content="no-cache" http-equiv="Pragma">
<meta content="地圖產生器,台灣地圖,登山" name="keywords">
<script src="../js/jquery-1.4.4.min.js" type="text/javascript">
</script>
</head>
<form id="kk" action="keeponadd.php">
<ul>
<li>id:<input type=text name="id"></input>
<li>url:<input type=text name="url"></input>
<li>tm:<input type=text name="tm" length=6></input>
<li>title:<input type=text name="title"></input>
<input type=hidden name="cp"></input>
</ul>
<input type=submit value="送"></input>
</form>
<script>
$("#kk").submit( function() {
	if ($("input[name=tm]").val().length != 6  || $("input[name=title]").val().length==0 ||
		$("input[name=id]").val().length==0 || $("input[name=url]").val().length==0 ) {
			alert("不完整" + $("input[name=url]").val().length );
			return false;
		}

		var test = $("input[name=tm]").val();
		var cp = parseInt( test.substr(0,2)) + parseInt(test.substr(2,2)) + parseInt(test.substr(4,2)) + 100 - $("input[name=url]").val().length;
		$("input[name=cp]").val(cp);
		alert(cp);
		//return false;
});
</script>
<?php

require_once("../lib/memq.inc.php");

$ret = MEMQ::listqueue("keepon");
echo "<pre>";
var_dump($ret);
echo "</pre>";
