<?php
require_once("header.inc");

$params=array();
if (isset($_GET['id'])) {
	$params['id']=$_GET['id'];
}
if (isset($_GET['pending'])) {
	$params['contribute'] = $_GET['pending'];
}
$params['action'] = 'list';
?>
<html>
        <head>
                <title>興趣點編輯</title>
		 <script src="https://code.jquery.com/jquery-2.1.4.min.js"></script>
                <script src="https://code.jquery.com/ui/1.11.4/jquery-ui.min.js"></script>
		                <link type="text/css" href="https://code.jquery.com/ui/1.11.4/themes/flick/jquery-ui.css" rel="Stylesheet" /> 
                <link href="jtable/themes/lightcolor/gray/jtable.css" rel="stylesheet" type="text/css" />
                <script src="jtable/jquery.jtable.js" type="text/javascript"></script>
	        </head>
        <body>
                <h1 align=right>興趣點編輯器</h1>
                <div id="PointTableContainer" style="width:100%"></div>
                <script type="text/javascript">
$(document).ready(function () {
		  function refresh_markers(action,data){
			// 改成 postMessage API, 否則過不了 same origin 
			window.parent.postMessage({ "function": "markerReloadSingle",  "action": action, "meta": data, "id": data.id  },'*');
			/*
		  	window.parent.markerReloadSingle({
		  		"action": action,
		  		"meta": data,
		  		"id": data.id
		  	});
			*/
		  }
		  $('#PointTableContainer').jtable({
                                title: '編輯',
                                sorting: true,
				paging: true,
				pageSize: 20,
                                defaultSorting: 'id DESC', 
                                toolbar: {
                                            hoverAnimation: true
                                },
                                actions: {
                                        listAction: 'pointActions.php?<?php echo http_build_query($params); ?>',
                                        createAction: 'pointActions.php?action=create',
                                        updateAction: 'pointActions.php?action=update',
                                        deleteAction: 'pointActions.php?action=delete'
                                },
                               // recordsLoaded: function() {
                                //        $(".shortbody").trunk8({lines: 5 });
                                //},
                                recordAdded: function(ev,data) {
                                	refresh_markers('add',data.record);
                                },
                                recordUpdated: function(ev, data){
                                	refresh_markers('update',data.record);
                                },
                                recordDeleted: function(ev, data){
                                	refresh_markers('delete',data.record);
                                },
                                fields: {
                                        id: {
						title: "序號",
                                                key: true,
                                                create: false,
						edit: false,
						list: true,
						width: "5%"
					},
					name: {
						title: "名稱",
			<?php
			if (isset($_GET['name'])) {
						printf('defaultValue: "%s",',$_GET['name']);
			}
			?>
						edit: true,
						display: function(data) {
							var $link=$('<a href="#">' + data.record.name + '</a>');
							$link.click(function() {
								$("#tags",parent.document).val(data.record.name);
								$("#goto",parent.document).trigger('click');
							});
							return $link;
						}
					},
					alias: {
						title: "別名",
						edit: true,
					},
					alias2: {
						title: "其他名稱",
						edit: true,
					},
					sname: {
						title: "冠字號碼",
						edit: true,
					},
			<?php
			if (isset($_GET['x']) && isset($_GET['y'])) {
					printf('x: { title: "lon", edit: true,  defaultValue: "%s", visibility: "hidden" },', $_GET['x']);
					printf('y: { title: "lat", edit: true,  defaultValue: "%s", visibility: "hidden" },', $_GET['y']);

			} else {
			?>
					x: { title: "lon", edit: true , visibility: "hidden"},
					y: { title: "lat", edit: true, visibility: "hidden" },
			<?php
			}
			?>
					type: { title: "種類", edit: true, options: [    '其他','一等點',
    '二等點',
    '三等點',
    '森林點',
    '未知森林點',
    '補點',
    '圖根點',
    '無基石山頭',
	'獨立峰',
    '溫泉',
    '湖泊',
    '谷地',
    '溪流',
    '瀑布',
    '獵寮',
    '營地',
    '水源',
    '乾溝',
    '黑水池',
    '積水池',
    '遺跡',
    '舊部落',
    '駐在所',
    '階梯',
    '岩石',
    '崩壁',
    '山屋',
    '吊橋',
    '蕃務駐在所', '警察駐在所'
    ],
	display: function(data) {
		var $img = $('<span><img src="//map.happyman.idv.tw/icon/' + encodeURIComponent(data.record.type) + '.png" />' + data.record.type + '</span>' );
		return $img;
	}
					},
					class: { title: "等", edit: true, options:  { '0': "無" , '1': "一", '2': "二", '3': "三", '4': "森" }, width: "5%"  },
					cclass: { title: "等(地籍)", edit: true, options: {'0': "未知", '1': "主", '2': "次", '3': "補" }, width: "5%" },
					fclass: { title: "等(森)", edit: true, options: {'0': "未知", '1': "主", '2': "次", '3': "補" }, width: "5%" },
					fzone: { title: "測量區(森)", edit: true },
					

					status: { title: "狀態", edit: true, options: [    '存在',
    '遺失',
    '森林點共用',
    '森林點未知',
    '森林點共存',
    '其他' ] , width: "5%"
					},
					number: { title: "號", edit: true, width: "5%" },
					ele: { title: "高度", edit: true, width: "5%" },
					mt100: { title: "百岳", edit: true, options: { '0': "不是", '1': "百岳", '2': "小百岳", '4': "百名山", '5': '百岳+名山', '6': '小百岳+名山' } },
					checked: { title: "檢查", edit: true, type: 'checkbox', values: { '0': '還沒', '1': 'done' }, width: "2%" },
					prominence: { title: "獨立度", edit: true, width: "5%"},
					prominence_index: { title: "獨立index", edit: true, width: "5%"},
			<?php
				if (is_admin()) {
				printf('owner: { title: "who", edit: true },');
				printf("contribute: { title: '投稿', edit: true, options: {'0': '自己看', '1':'已投稿', '2':'來自投稿' } },");
				} else {
				printf("contribute: { title: '投稿', edit: true, type: 'checkbox', values: {'0': '自己看', '1':'已投稿' } },");
				}
			?>
					comment: { title: "註解", edit: true },
				}
			});
		$('#PointTableContainer').jtable('load');
		<?php

			if (isset($_GET['x']) && isset($_GET['y'])) {
			?>
				$('#PointTableContainer').jtable('showCreateForm'); 
			<?php
			}
			?>
});
			        </script>
	<?php
	if (is_admin()) {
		printf("<p><a href='?pending=1'>待審核</a>");
		printf("<p><a href='promlist.php'>獨立峰列表(all)</a>,<a href='promlist.php?type=h2000'>中級山</a>,<a href='promlist.php?type=h1000'>郊山</a>");
	}
	?>
	<p>
	Q: 如何將興趣點貢獻給系統? <br>
	A: 將已建立的興趣點的 "投稿" 打勾, 確定狀態變成 "已投稿". <br>
	當系統管理員接受之後, 該點位就會變成系統點,並且在您的興趣點列表中消失.
        </body>
</html>
