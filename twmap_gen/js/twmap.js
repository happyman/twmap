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
	$.get('api/notifyweb.php?channel=' + $("#formid").val());
}

