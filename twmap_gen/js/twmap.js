/* for blink steps */
var timer = [];

/*
function blinking(elm, action) {
    if (action === 0) {
        if (timer[elm]) {
            clearInterval(timer[elm]);
            delete timer[elm];
        }
        return;
    }
    timer[elm] = setInterval(blink, 400);

    function blink() {
        $("#" + elm).fadeOut(200, function() {
            $("#" + elm).fadeIn(200, function() {
                if (!timer[elm]) $("#" + elm).hide();
            });
        });
    }
}
*/
/* ready function */
$(function() {
/* 如果被 twmap_gen 包在 iframe 中, 就把自己關閉 */
if (loggedin == 1 ) {
  if ($("#meerkat-wrap",window.parent.document).is(":visible"))  {
  	$('#meerkat-wrap',window.parent.document).hide().queue(function() {
		top.location.reload();
	});
	return;
  }
}
    $("#tabs").tabs({
	heightStyle: "fill",
        activate: function(event, ui) {
            var index = $("#tabs").tabs('option', 'active');
            if (index == 1) {
                if (iframe_loaded === 0 && $("#mapbrowse").attr('src') === '') {
                    console.log("loading twmap3 for first time");
                    $("#mapbrowse").attr('src', mapbrowse_url);
                    iframe_loaded = 1;
                }
            }
            // 更改一下 url
            window.history.pushState({}, '', 'main.php?tab=' + index);
        },
        // http://www.datatables.net/examples/api/tabs_and_scrolling.html
        create: function(event, ui) {
            var oTable = $('div.dataTables_scrollBody>table.display', ui.newPanel).dataTable();
            if (oTable.length > 0) {
                oTable.fnAdjustColumnSizing();
            }
        }
    });
    $("#tabs").tabs("option", "active", initial_tab);
// set minimal height for browse window
	$("#tabs").tabs().css({
   'min-height': '600px',
   'overflow': 'auto'
});
    $(".showtip").tipTip();
    $("#log_message").hide();
    // ape stuff
    client = new APE.Client(ape_host);
    client.load();
    client.addEvent('load', function() {
        client.core.start({
            name: "web"
        });
    });
    client.addEvent('ready', function() {
        client.core.join(log_channel);
        client.onRaw('data', function(raw, pipe) {
            var pat = new RegExp("^([a-f0-9]{32}):(.*)$");
            var mat = [];
            // 只處理我的 format
            if (!(mat = raw.data.msg.match(pat))) {
                	console.log(raw.data.msg);
                	console.log("not match" + mat[1]);
                return;
            }
            // 只處理同一個 form 的
            if ($("#formid").val() != mat[1]) {
                //	 console.log(raw.data.msg);
                //	 console.log(mat[1] + " not match" + $("#formid").val());
                return;
            }
            // note jquery ui 1.9 will change selected to active
            // only show in mapform tab
            // 第 0 個 tab 才處理.
            if ($("#tabs").tabs("option", "active") !== 0) {
                makeprogress.progressbar("value", 0);
                makeprogress.hide();
                $("#log_message").text("").hide();
            }
            // 第一次收到 msg, 顯示
            if ($("#log_message").css("display") == "none") {
                $("#log_message").css("height", $("#makemaptable").height());
                $("#log_message").show();
                makeprogress.show();
            }
            var logmsg = mat[2];
            if (logmsg.indexOf("ps%") === 0) {
                var pst = logmsg.substr(logmsg.indexOf("%") + 1);
                // 如果是新增的話 ps:+2
                if (pst.substr(0, 1) == "+") {
                    var val = arguments.callee.startval;
                    var addval = Number(pst.substr(1));
                    pst = addval + Number(val);
                    pst = String(pst);
                    //console.log(val + "+" + String(addval) + " = " + pst);
                } else {
                    // 如果沒有 + 才更新
                    arguments.callee.startval = Number(pst);
                    //console.log("update  collee" + arguments.callee.startval );
                }
                if (Number(pst) == 100) {
					console.log("background command finished");
                    // clearProgress();
                    // 5 秒之後檢查跳頁: test
                    // setTimeout("checkFinished()", 3000);
                }
                // 更新 progress bar
                $(".psLabel").text(pst + " %");
                makeprogress.progressbar("value", Number(pst));
            } else {
                // console.log(raw.data);
		// log window
                $("#log_message").prepend(logmsg);
                if (logmsg.indexOf("err:") === 0) {
                    // 出錯了 要 keep 嘛?
                    clearProgress();
                } else if (logmsg.indexOf("finished!") === 0) {
					// 處理 finished message: 因為 proxy 會中斷連線
					console.log("got finished msg:" + logmsg);
					var final_mid = logmsg.substr(logmsg.indexOf("!")+1);

					$("#tabs li").eq(0).data("loaded", false).find('a').attr("href","mapform.php");
					$("#tabs li").eq(3).data("loaded", false).find('a').attr("href","show.php?tab=1&mid="+final_mid);
					$('#tabs').tabs('option',"active",3);
					clearProgress();
					$.unblockUI();
				}
				
            }
        });
    });
    // progressbar stuff
    makeprogress = $("#makeprogress").progressbar({
        "value": 0
    }).hide();
    makeprogress.children().css("background", "lightgreen");
    $(".psLabel").css("background", "");

	$("#mapbrowse").iFrameResize(
	{ log: false,
          heightCalculationMethod: 'max', 
	  minHeight: 750,
	  maxHeight: 2000
	});
	$("#mapbrowse").on('load', function() {
		$(window).resize();
		console.log("iframe loaded and trigger resize");
	});

$(window).bind("load", function() { 
       
       var footerHeight = 0,
           footerTop = 0,
           $footer = $("#footer");
           
       positionFooter();
       
       function positionFooter() {
       
                footerHeight = $footer.height();
                footerTop = ($(window).scrollTop()+$(window).height()-footerHeight)+"px";
		console.log("sT:"+$(window).scrollTop() + " wh=" + $(window).height() + " fh=" + footerHeight);
       
               if ( ($(document.body).height()+footerHeight) < $(window).height()) {
                   $footer.css({
                        position: "absolute"
                   }).animate({
                        top: footerTop
                   });
               } else {
                   $footer.css({
                        position: "static"
                   });
               }
               
       }

       $(window)
               .scroll(positionFooter)
               .resize(positionFooter);
               
});

}); // ready
function clearProgress() {
    makeprogress.progressbar("value", 0);
    makeprogress.hide();
    $("#log_message").text("").hide();
}
// 開啟 log 視窗 
function testnotify() {
 $.get('api/notifyweb.php?c=' + user_email + ":" + $("#formid").val());
}


// google
/*
var _gaq = _gaq || [];
_gaq.push(['_setAccount', 'UA-19949015-1']);
_gaq.push(['_trackPageview']);
(function() {
    var ga = document.createElement('script');
    ga.type = 'text/javascript';
    ga.async = true;
    ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
    var s = document.getElementsByTagName('script')[0];
    s.parentNode.insertBefore(ga, s);
})();
*/
