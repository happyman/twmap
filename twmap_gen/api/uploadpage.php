<?php
require_once("../config.inc.php");
if(!ob_start("ob_gzhandler")) ob_start();
list ($login,$uid) = userid();
if ($login === false) {
	echo "請登入";
	echo '<button onclick="window.history.back()">Back</button>';
	exit;
}
?>

<html>
<head>
<script src='https://code.jquery.com/jquery-2.1.4.min.js'></script>
<script src="https://code.jquery.com/ui/1.11.4/jquery-ui.min.js"></script>
<link type="text/css" href="https://code.jquery.com/ui/1.11.4/themes/flick/jquery-ui.css" rel="Stylesheet" />
<script src="../admin/jtable/jquery.jtable.js" type="text/javascript"></script>
<link href="../admin/jtable/themes/lightcolor/gray/jtable.css" rel="stylesheet" type="text/css" />
<script  src="../js/dropzone.js"></script>
<link rel="stylesheet" href="../css/dropzone.css">
</head>
<body>
<hr>
<p>
<p>beta 測試中,歡迎建議.	
<p>
請上傳行跡檔 (gdb, gpx, kml, kmz):

</p>
<form action="upload.php"
      class="dropzone"
      id="my-awesome-dropzone">
  <div class="fallback">
    <input name="file" type="file" multiple />
  </div>	  
</form>
<div id="TrackTableContainer" style="width:100%"></div>
<script>

$('document').ready(function(){

Dropzone.options.myAwesomeDropzone = {
   maxFiles: 10,
   maxFilesize: 20, // MB
   acceptedFiles: ".kml,.kmz,.gpx,.gdb",
   dictDefaultMessage: "請將檔案拖曳到此處上傳",
  init: function() {
	this.on("maxfilesexceeded", function(file){
        alert("No more files please!");
    });
    this.on("queuecomplete", function(file) { 
	  $('#TrackTableContainer').jtable('reload');
	  //alert("file uploaded"); 
	  });
	this.on('error', function(file, response) {
    $(file.previewElement).find('.dz-error-message').text(response);
	});
 } // init
};


  $('#TrackTableContainer').jtable({
                                title: '我的行跡',
                                sorting: true,
                                paging: true,
                                pageSize: 20,
                                defaultSorting: 'tid DESC',
                                toolbar: {
                                            hoverAnimation: true
                                },
                                actions: {
                                        listAction: '../admin/trackActions.php?action=list',
                                        //createAction: '../admin/trackActions.php?action=create',
                                        updateAction: '../admin/trackActions.php?action=update',
                                        deleteAction: '../admin/trackActions.php?action=delete'
                                },
								fields: {
									tid: {
										title: "顯示",
										key: true,
										create: false,
										edit: false,
										width: "10%",
										display: function(data){
											// note -1 means load track id.
											return '<a href=# class="showkml"  onclick="skmlclick(event,$(this))"  data-id="-'+ data.record.tid  + '" data-title="'+ data.record.name + '"  data-link="">' + data.record.tid + '</a>';
										}
									},
									name: {
										title: "名稱",
										edit: true,
										width: "60%",
										display: function(data){
											var nuoc_ngoai = "";
											//console.log(data.record.is_taiwan);
											if (data.record.is_taiwan == 'f')
												nuoc_ngoai = ' [國外]';
											return '<a href="getkml.php?mid=-'+ data.record.tid+'" target=_top>'+data.record.name+'</a>' + nuoc_ngoai;
										} 
								
									},
									km_x: {
										title: "範圍",
										edit: false,
										width: "15%",
										display: function(data){
											return data.record.km_x + "x" + data.record.km_y + " (km)";
										}
									},
									contribute: { 
										title: "投稿", 
										edit: true, 
										type: 'checkbox', values: {'0': '自己看', '1':'已投稿' } 
									}
  }});
   $('#TrackTableContainer').jtable('load');
   $('#TrackTableContainer').find('.jtable-toolbar-item.jtable-toolbar-item-add-record').remove();
   

    

});
function skmlclick(event, ele){
	event.preventDefault();
	parent.showmapkml(ele.data('id'),ele.data('title'),ele.data('link'),true);
}
</script>
 

</body>
</html>