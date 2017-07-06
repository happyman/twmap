<?php
require_once("header.inc");

$params=array();
$params['action'] = 'list';
if (isset($_GET['type']))
	$params['type']=$_GET['type'];
	if ($_GET['type'] == 'h3000'){
		$table_title = "高山獨立峰列表 >=3000M";
	} else if ($_GET['type'] == 'h2000'){
		$table_title = "中級山獨立峰列表 1000M~3000M";
	} else if ($_GET['type'] == 'h1000') {
		$table_title = "郊山獨立峰列表 <1000M";
	} else {
		$table_title = "全部獨立峰列表";
	}
	// accept limit and start params
	$params['limit'] = 100;
if (isset($_GET['limit'])) $params['limit'] = $_GET['limit'];
if (isset($_GET['start'])) $params['start'] = $_GET['start'];

?>
<html>
        <head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

                <title>台灣獨立峰列表</title>
		 <script src="https://code.jquery.com/jquery-2.1.4.min.js"></script>
                <script src="https://code.jquery.com/ui/1.11.4/jquery-ui.min.js"></script>
		                <link type="text/css" href="https://code.jquery.com/ui/1.11.4/themes/flick/jquery-ui.css" rel="Stylesheet" /> 
                <link href="jtable/themes/lightcolor/gray/jtable.css" rel="stylesheet" type="text/css" />
                <script src="jtable/jquery.jtable.js" type="text/javascript"></script>
	        </head>
        <body>
                <h3 align=right>台灣獨立峰列表</h3>
                <div id="PointTableContainer" style="width:100%"></div>
		
		                <script type="text/javascript">

$(document).ready(function () {
	function letsgo(place){
		if (window.self === window.top){
			window.open("<?php echo $TWMAP3URL; ?>?goto="+place,"independent_peak");
			return;
		}
			$("#tags",parent.document).val(place);
			$("#goto",parent.document).trigger('click');
	}
	  $('#PointTableContainer').jtable({
                                title: '<?php echo $table_title;?>',
                                sorting: true,
								paging: true,
								pageSize: <?php echo $params['limit']; ?>,
                                defaultSorting: 'sn', 
                                toolbar: {
                                            hoverAnimation: true
                                },
                                actions: {
                                        listAction: 'promActions.php?<?php echo http_build_query($params); ?>',
                                        //createAction: 'pointActions.php?action=create',
                                        updateAction: 'promActions.php?action=update',
                                        //deleteAction: 'pointActions.php?action=delete'
                                },
								<?php
								if (!is_admin()) {
									?> 
								recordsLoaded: function (event, data) {
                                             $('.jtable-edit-command-button').hide(); 
                                },
								<?php
								}
								?>
								toolbar: {
    items: [
	{
		text: '中級山',
		click: function() {
			location.href='promlist.php?type=h2000';
			return;
		}
	},
		{
		text: '高山',
		click: function() {
			location.href='promlist.php?type=h3000';
			return;
		}
	},
		{
		text: '郊山',
		click: function() {
			location.href='promlist.php?type=h1000';
			return;
		}
	},
		{
       // icon: '/images/pdf.png',
        text: 'Export',
        click: function () {
            //perform your custom job...
			window.open("promActions.php?<?php $params['action']='exportcsv';  echo http_build_query($params); ?>","exportcsv");
			return;
        }
    }]
},
                        fields: {
							rnum:{
								title: "序號",
								width: "5%",
								edit: false
							
							},
                                        sn: {
						title: "排名",
                                                key: true,
                                                create: false,
						edit: false,
						list: true,
						width: "5%"
						},
					p_name: {
						title: "名稱",
						<?php
						if (is_admin()) {
							echo "edit: true,";
						} else {
							echo "edit: false,";
						}
						?>
						display: function(data) {
							var $link=$('<a href="#">' + data.record.p_name + '</a>');
							$link.click(function(e) {
							letsgo(data.record.p_name);
							e.preventDefault();
							});
							return $link;
						}
					},
					p_loc: {
						title: "座標",
						edit: false,
						display: function(data) {
							var $link=$('<a href="#">' + data.record.p_loc + '</a>');
							$link.click(function(e) {
								letsgo(data.record.p_loc);
								e.preventDefault();
							});
							return $link;
						}
					},
					p_h: {
						title: "高度(M)",
						width: "10%",
						edit: false
					},
					prominence: {
						title: "獨立度(M)",
						width: "10%",
						edit: false,
					},

					col_loc: {
						title: "主鞍座標",
						edit: false,
						display: function(data) {
							if (!data.record.col_loc) return "";
							var $link=$('<a href="#">' + data.record.col_loc + '</a>');
							$link.click(function(e) {
								letsgo(data.record.col_loc);
								e.preventDefault();
							});
							return $link;
						}
					},
					col_h: {
						title: "主鞍高度(M)",
						edit: false,
					},
					//parent_name: {
					//	title: "父峰",
					//	edit: false,
					//},
					parent_loc: {
						title: "父峰",
						edit: false,
						display: function(data) {
							if (!data.record.parent_loc) return "";
							var $link=$('<a href="#">' + data.record.parent_name + '</a>');
							$link.click(function(e) {
								letsgo(data.record.parent_loc);
								e.preventDefault();
							});
							return $link;
						}
					} 
			} // end of fields
	  });
		$('#PointTableContainer').jtable('load');
}); // end of ready function
</script>
</body>
</html>
