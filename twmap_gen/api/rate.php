<?php
require_once("../config.inc.php");
if(!ob_start("ob_gzhandler")) ob_start();

$mid = $_REQUEST['mid'];

if (empty($mid)) {
	echo "Error mid";
	exit;
}

list ($login,$uid) = userid();
if ($login === false) {
	echo "請登入";
	exit;
}
$map = map_get_single($mid);
$rank = new map_rank();
$st = $rank->get_rank($mid,$uid);
$stat = $rank->get_comment($mid);
?>
<html>
<head>
<script src='https://code.jquery.com/jquery-2.1.4.min.js'></script>
<!-- <script src='https://rawgit.com/gjunge/rateit.js/master/scripts/jquery.rateit.js'></script>
 <link href="https://rawgit.com/gjunge/rateit.js/master/scripts/rateit.css" rel="stylesheet" type="text/css">
 -->
 <script  src="/~happyman/twmap3_dev/js/jquery.rateit.js"></script>
 	<link rel="stylesheet" type="text/css" href="/~happyman/twmap3_dev/css/rateit/rateit.css" />
 </head>
 <body>
<?php
printf("<b>%s</b><p>",$map['title']);
if (count($st) == 1){
	printf("<div id='rate' class=\"rateit bigstars\"  data-rateit-starwidth=32 data-rateit-starheight=32 data-rateit-step=1 data-rateit-value=\"%f\" data-rateit-ispreset=\"true\" ></div>",$st[0]['score']);
	$comment = $st[0]['comment'];
} else {
	printf("<div id='rate' data-rateit-step=1  data-rateit-starwidth=32 data-rateit-starheight=32 class=\"rateit bigstars\"></div>");
}
?>
<span id='hover_text'></span><br>
<span id='rate_text'></span>
<br>
評語: <input type='text' id='comment' value='<?php echo 	htmlspecialchars($comment)	; ?>'></input>
<button id='submit'>送出</button>
<?php
if(is_admin()){
	echo "<button id='gpxdel'>刪除</button>";
}
?>
<br><span id="response"></span>
<p>
<?php
if (count($stat)>0) {
	echo "<ul>";
	foreach($stat as $d){
		printf("<li>%s 說: %s %s",$d['name'],$d['text'],$d['comment']);
	}
	echo "</ul>";
}
?>
<script>
 $(function () {
	var mid = <?php echo $mid; ?>;
	var rateurl = "<?php printf("%s/api/rateset.php",$site_html_root);?>";
	var twmap_gpx_url = "<?php printf("%s/api/twmap_gpx.php",$site_html_root);?>";
	var rate_txt = [ "無", "糟糕","不佳","普通","好","精選"];
	$("#rate").bind('rated', function (event, value) { $('#rate_text').text('我給他評價: ' + rate_txt[Math.round(value)]); });
    $("#rate").bind('reset', function () { 
		$('#rate_text').text('我不給評價');
		$('#comment').val('');
		$('#submit').show();
	});
    $("#rate").bind('over', function (event, value) { 
		if (value !== null ) {
			$('#hover_text').text(value + rate_txt[Math.round(value)]);
		}else{
			$('#hover_text').text('');
		}
	});
	$('#submit').click(function(){
		var comment = $('#comment').val();
		var score = $("#rate").rateit('value');
		if (comment.length == 0 && score == 0 ) {
			if (!confirm("是否刪除評價?")){
				return;
			}
		}
			   $.ajax({
				 url: rateurl, //
				 data: { mid: mid, score: score, comment: comment, action: "set" }, //our data
				 type: 'POST',
				 success: function (data) {
					 $('#response').append('<li>' + data + '</li>');
					 $('#submit').hide();
					 setTimeout(function(){window.parent.jQuery('#ranking').dialog('close');},2000);
				 },
				 error: function (jxhr, msg, err) {
					 $('#response').append('<li style="color:red">' + msg + '</li>');
				 }
			 });
		 
	 });
		 <?php 
	if (is_admin()) {
	?>
	$('#gpxdel').click(function() {
			console.log("del gpx mid:" + mid);
			if (mid){
				 $.ajax({
				 url: twmap_gpx_url, 
				 data: { action: "del", mid: mid},
				 type: 'POST',
				 success: function (data) {
					if (data.ok === true){
						$('#response').append('<li>' + data.rsp + '</li>');
						setTimeout(function(){window.parent.jQuery('#ranking').dialog('close');},2000);
					}
					else
						$('#response').append('<li style="color:red">' + data.rsp + '</li>');
				 },
				 error: function (jxhr, msg, err) {
					 $('#response').append('<li style="color:red">' + msg + '</li>');
				 }
				 });
			}
		});
	
	<?php		
	}
	?>
 });

 </script>
</div>
</body>
