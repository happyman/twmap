<?php
// $Id: mapform.php 356 2013-09-14 10:00:22Z happyman $
session_start();
if (empty($_SESSION['loggedin'])) {
	header("Location: login.php");
	exit(0);
}
require_once("config.inc.php");
$user = fetch_user($_SESSION['mylogin']);
if (isset($_GET['recreate']))  {
	// 填入參數, from list.php 重新產生
	$_SESSION['makeparam'] = $_GET;
	$recreate_flag = 1;
} else if (isset($_GET['recreate_gpx']))  {
	// 重新產生 gpx
	$_SESSION['makeparam2'] = $_GET;
	$recreate_flag = 1;
} else {
	$recreate_flag = 0;
}

if (disk_free_space("/mnt/nas") < 500000000) {
	printf("<h1>磁碟空間已滿</h1>");
  exit;
}

//if (count($maps) >= $user['limit'] ) {
if (map_full($_SESSION['uid'], $user['limit'], $recreate_flag)) {
	printf("<h1>已經達到產生數量限制".$user['limit']."</h1>");
	exit;
}

$smarty->assign("aashiftx", array(5,10,15,20,25,30,35));
$smarty->assign("aashifty", array(7,14,21,28,35));
$data = array();
for($i=1;$i<=36;$i++) $data[] = $i;
$smarty->assign("anyshiftx", $data);
$data = array();
for($i=1;$i<=36;$i++) $data[] = $i;
$smarty->assign("anyshifty", $data);
$data = array();
for($i=7;$i<=35;$i+=7) $data[] = $i;
$smarty->assign("aarshiftx", $data);
$data = array();
for($i=5; $i<=35; $i+=5) $data[] = $i;
$smarty->assign("aarshifty", $data);
$data = array();
// 產生 unique form id
$smarty->assign("formid", md5($_SESSION['mylogin']['email'] . uniqid(rand(), true)));

echo $smarty->fetch('mapform.html');

?>

<script type="text/javascript">
var called = 0;
<?php
// 自動填入參數
// 兩條路徑, 1: from main.php?GET
//           2. from list.php SESSION
if (isset($_SESSION['makeparam']) && isset($_SESSION['makeparam']['x'])) {
?>
	called = 1;
	$("#mapform input[name=startx]").val("<?php echo $_SESSION['makeparam']['x'];?>");
	$("#mapform input[name=starty]").val("<?php echo $_SESSION['makeparam']['y'];?>");
	$("#mapform select[name=anyshiftx]").val("<?php echo $_SESSION['makeparam']['shiftx']; ?>");
	$("#mapform select[name=anyshifty]").val("<?php echo $_SESSION['makeparam']['shifty']; ?>");
	$("#mapform input[name=kiss]").val(1);
	$("#all").show();
	$("#a4").hide();
	$("#mapform button[name=bt]").html('ANY');
	$("#mapform input[name=startx]").attr("readonly", true);
	$("#mapform input[name=starty]").attr("readonly", true);
	$("#mapform select[name=anyshiftx]").attr("disabled", true);
	$("#mapform select[name=anyshifty]").attr("disabled", true);
	$("#mapform select[name=version]").val("<?php echo $_SESSION['makeparam']['version']; ?>");
	$("#mapform select[name=ph]").val("<?php echo $_SESSION['makeparam']['ph']; ?>");
	$("#mapform input[name=ph").attr("readonly", true);

<?php
	if (isset($_SESSION['makeparam']['title'])) {
?>
		$("#mapform input[name=title]").val("<?php echo $_SESSION['makeparam']['title']; ?>");
		$("#dialog-message").hide();
<?php
	} else {
?>
		$("#mapform input[name=title]").focus();
		$("#dialog-message").dialog({
			modal: true,
				buttons: {
					Ok: function() {
						$( this ).dialog( "close" );
					}
				}
		});

<?php
	}
	unset($_SESSION['makeparam']);
} else if (isset($_SESSION['makeparam2'])) { // gpx重新產生
?>
	called = 2;
	$("#mapform input[name=title]").val("<?php echo $_SESSION['makeparam2']['title']; ?>");
	$("#mapform input[name=gpxmid]").val("<?php echo $_SESSION['makeparam2']['mid']; ?>");
	$("#mapform input[name=gpxfilename]").val("<?php echo str_replace(".tag.png",".gpx",basename($_SESSION['makeparam2']['filename'])); ?>");

<?php
	unset($_SESSION['makeparam2']);
} // makeparam2
?>
$('#bt').click(function() {
	if (called == 1) return;
	if ($("#mapform button[name=bt]").html() == 'A 4') {
		//$("#mapform button[name=bt]").val('A 4');
		$("#mapform button[name=bt]").html('ANY');
		$("#mapform input[name=kiss]").val(1);
		$("#all").show();
		$("#a4").hide();
		$("#a4r").hide();
	} else if ($("#mapform button[name=bt]").html() == 'A4R') {
		//$("#mapform button[name=bt]").val('ANY');
		$("#mapform button[name=bt]").html('A 4');
		$("#mapform input[name=kiss]").val(2);
		$("#a4").show();
		$("#all").hide();
		$("#a4r").hide();
	} else {
		$("#mapform button[name=bt]").html('A4R');
		$("#mapform input[name=kiss]").val(3);
		$("#a4r").show();
		$("#all").hide();
		$("#a4").hide();

	}
});
$('#bt1').click(function() {
	if ($("#mapform button[name=bt1]").html() == '輸入座標產生') {
		$("#mapform button[name=bt1]").html('上傳航跡檔產生');
		$(".gpx_mode").show();
		$("#create2").show();
		blinking('create2',1);
		blinking('create3',0);
		blinking('create',0);
		$("#create").hide();
		$("#create3").hide();
		$(".normal_mode").hide();
		$(".gpx_recreate_mode").hide();
		$("#mapform input[name=gps]").val(1);
		// tips
		$('#step_version').text("4");
		$('#step_go').text("5");
	} else {
		$("#mapform button[name=bt1]").html('輸入座標產生');
		$(".gpx_mode").hide();
		$(".normal_mode").show();
		$("#create").show();
		blinking('create',1);
		blinking('create3',0);
		blinking('create2',0);
		$("#create2").hide();
		$("#create3").hide();
		$(".gpx_recreate_mode").hide();
		$("#mapform input[name=gps]").val(0);
		$('#step_x').text("3");
		$('#step_y').text("4");
		$('#step_bound').text("5");
		$('#step_area').text("6");
		$('#step_version').text("7");
		$('#step_go').text("8");
	}
});
$('#create').click(function() {
	//if (!$("#mapform").valid()) return false;
	// 不用 validate plugin 了
	// 先 block 再 ajax
	if ($("#mapform input[name=title]").val().length < 2) {
		alert("請輸入標題喔(多一點字)");
		return false;
	}
	if ($("#mapform input[name=startx]").val().length < 3) {
		alert("請輸入 X 座標");
		return false;
	}
	if ($("#mapform input[name=starty]").val().length < 4) {
		alert("請輸入 Y 座標");
		return false;
	}
	$("#mapform select[name=anyshiftx]").attr("disabled", false);
	$("#mapform select[name=anyshifty]").attr("disabled", false);
	$.blockUI({ css: {
		border: 'none',
			padding: '15px',
			backgroundColor: '#000',
			'-webkit-border-radius': '10px',
			'-moz-border-radius': '10px',
			opacity: .5,
			color: '#fff'
	}, message: '<h1>讓我來慢慢產生,您去喝杯茶做做體操再回來!</h1>' });


	globalxdr = $.post("backend_make.php", $("#mapform").serialize(),function(data){
		$.unblockUI();
		if (data.status == "ok") {
			clearProgress();
			var $tabs = $('#tabs').tabs();
			$tabs.tabs('url',3,"show.php?tab=1&mid="+data.id);
			$tabs.tabs('url',0, "mapform.php");
			$tabs.tabs('select',3);
		}
		else
			alert("error: "+data.error);
	},"json");


});
$('#create2').click(function() {
	//$("#mapform").attr('action', "backend_make.php");
	if (!$('input:file').val()) {
		alert("請選擇檔案");
		return false;
	}
	if ($("#mapform input[name=title]").val().length < 2) {
		alert("請輸入標題喔(多一點字)");
		return false;
	}
	$.blockUI({ css: {
		border: 'none',
			padding: '15px',
			backgroundColor: '#000',
			'-webkit-border-radius': '10px',
			'-moz-border-radius': '10px',
			opacity: .5,
			color: '#fff'
	}, message: '<h1>讓我來慢慢產生,您去喝杯越南咖啡做做體操再回來!</h1>' });

	document.mapform.submit();
});
$('#create3').click(function() {
	$("#mapform input[name=gps]").val(2);
	if ($("#mapform input[name=title]").val().length < 2) {
		alert("請輸入標題喔(多一點字)");
		return false;
	}
	$.blockUI({ css: {
		border: 'none',
			padding: '15px',
			backgroundColor: '#000',
			'-webkit-border-radius': '10px',
			'-moz-border-radius': '10px',
			opacity: .5,
			color: '#fff'
	}, message: '<h1>讓我來慢慢產生,您去喝杯好了啦做做瑜伽再回來!</h1>' });

	globalxdr = $.post("backend_make.php", $("#mapform").serialize(),function(data){
		$.unblockUI();
		if (data.status == "ok") {
			clearProgress();
			var $tabs = $('#tabs').tabs();
			$tabs.tabs('url',3,"show.php?tab=1&mid="+data.id);
			$tabs.tabs('url',0, "mapform.php");
			$tabs.tabs('select',3);
		}
		else
			alert("error: "+data.error);
	},"json");


});
$('#switch_bt1').click(function() {
	$('#bt1').trigger('click');
});
$(document).ready(function(){
	//alert("here");
	//if (called != 1 )
	$("#dialog-message").hide();
	$("#mapform").validate();
	$(".gpx_mode").hide();
	// tips
	$('#step_x').text("2");
	$('#step_y').text("3");
	$('#step_bound').text("4");
	$('#step_area').text("5");
	$('#step_version').text("6");
	$('#step_go').text("7");
	blinking('bt1',1);
	if (called == 1 ) {
		$(".method").hide();
		$(".normal_note").hide();
		$('#step_go').text("這裡");
		blinking('create',1);
		blinking('create2',0);
		blinking('create3',0);
		$("#create3").hide();
		$("#create2").hide();
	}
	if (called == 2 ) {
		//alert(called);
		$(".normal_mode").hide();
		$(".gpx_mode").hide();
		$(".gpx_recreate_mode").show();
		$(".method").hide();
		$(".normal_note").hide();
		$("#create3").show();
		blinking('create3',1);
		blinking('create2',0);
		blinking('create',0);
		$("#create").hide();
		$("#create2").hide();
		//alert(called);
		//alert(called);
		// tip
		$('#step_version').text("3");
		$('#step_go').text("這裡");

	} else {
		$(".gpx_recreate_mode").hide();
		blinking('create',1);
		blinking('create2',0);
		blinking('create3',0);
		$("#create2").hide();
		$("#create3").hide();
	}
});

</script>
