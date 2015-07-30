/* javascript_functions.js */

var deleteMessageText = "Are you sure you want to delete this item from the database? This cannot be undone.";

/********* THERE SHOULD BE LITTLE NEED TO EDIT BELOW THIS LINE *******/

var loading_image_html; //set via setLoadingImageHTML()
var filterReq = "";	// used in filtering the table
var pageReq = "";	// used in pagination
var sortReq = ""; 	// used for sorting the table
var this_page;		// the php file loading ajaxCRUD (including all params)

/* Ajax functions */
function createRequestObject() {
     var http_request = false;
      if (window.XMLHttpRequest) { // Mozilla, Safari,...
         http_request = new XMLHttpRequest();
         if (http_request.overrideMimeType) {
         	//set type accordingly to anticipated content type (not used anymore)
            //http_request.overrideMimeType('text/xml');
            //http_request.overrideMimeType('text/html');
            //http_request.overrideMimeType('text/plain;charset=ISO-8859-1');
            //http_request.overrideMimeType('application/x-www-form-urlencoded; charset=UTF-8');
         }
      } else if (window.ActiveXObject) { // IE
         try {
            http_request = new ActiveXObject("Msxml2.XMLHTTP");
         } catch (e) {
            try {
               http_request = new ActiveXObject("Microsoft.XMLHTTP");
            } catch (e) {}
         }
      }
      if (!http_request) {
         alert('Cannot create XMLHTTP instance');
         return false;
      }

      return http_request;
}

var http = createRequestObject();
var add_http = createRequestObject();
var filter_http = createRequestObject();
var sort_http = createRequestObject();
var other_http = createRequestObject();

//used for updating
function sndUpdateReq(action) {
    http.open('get', action);
    http.onreadystatechange = handleUpdateResponse;
    http.send(null);
}

function getThisPage(){
	var returnPageName = this_page;
	var paramChar = "?";
	if (returnPageName.indexOf("?") != -1){
		paramChar = "&";
	}
	returnPageName = returnPageName + paramChar;
	return returnPageName;
}

/* Ajax Deleting */
function sndDeleteReq(action) {
    http.open('get', action);
    http.onreadystatechange = function(){
		if(http.readyState == 4){
			var return_string = http.responseText;
			var broken_string = return_string.split("|");
			var table = broken_string[0];
			var id = broken_string[1];

			//$('#' + table + '_row_' + id).fadeOut('slow');
			//this new line was added in v5.4 to support vertical layout
			$("tr[id^=" + table + "_row_" + id +"]").fadeOut('slow');
			updateRowCount(table);

		}
	}
    http.send(null);
}

/* Ajax Adding */
function sndAddReq(action, table) {
    http.open('get', action);
    http.onreadystatechange = function() {
		if(http.readyState == 4){

			/* this checks if there is a question mark in the url already (custom apps)
			   and replaces with an ampersand
			*/

			var markki = "?";
			if (ajax_file.search("[?]") > 0) { markki = "&"; }
			var action2 = ajax_file + markki + "ajaxAction=add&table=" + table;

			add_http.open('get', action2);
			add_http.onreadystatechange = function(){
				if(add_http.readyState == 4){
					var return_string = add_http.responseText;
					var table_html = return_string;
					document.getElementById(table).innerHTML = table_html;
					doValidation(); //rebind any validation functions to the new elements
					updateRowCount(table);
				}
			}
			add_http.send(null);
		}
 	}
    http.send(null);
}

/* Ajax Filtering */
function sndFilterReq(action, table) {
    http.open('get', action);
    http.onreadystatechange = function(){
		if(http.readyState == 4){

			//this checks if there is a question mark in the url already (custom apps) and replaces with an ampersand
			var markki = "?";
			if (ajax_file.search("[?]") > 0) { markki = "&"; }

			filter_http.open("get", ajax_file + markki + "ajaxAction=filter&table=" + table);
			filter_http.onreadystatechange = function(){
				if(filter_http.readyState == 4){
					var table_html = filter_http.responseText;
					document.getElementById(table).innerHTML = table_html;
					//$("#" + table).html(table_html); //maybe use this method some day if helpful
					doValidation(); //rebind any validation functions to the new elements
					updateRowCount(table);
				}
			}
			filter_http.send(null);
		}
	}

    http.send(null);

}

/* Ajax Sorting */
function sndSortReq(action, table) {
    http.open('get', action);
    http.onreadystatechange = function() {
		if(http.readyState == 4){
			var markki = "?";
			if (ajax_file.search("[?]") > 0) { markki = "&"; }
    		var sortURL = ajax_file + markki +"ajaxAction=sort&table=" + table;
    		sort_http.open('get', sortURL);
			sort_http.onreadystatechange = function(){
				if(sort_http.readyState == 4){
					var table_html = sort_http.responseText;
					document.getElementById(table).innerHTML = table_html;
					doValidation(); //rebind any validation functions to the new elements
				}
			}
			sort_http.send(null);
		}
 	}
    http.send(null);
}

/* Update Row Count (if used with function insertRowsReturned() ) - added in v6.9 */
function updateRowCount(table) {

	var markki = "?";
	if (ajax_file.search("[?]") > 0) { markki = "&"; }

    http.open('get', ajax_file + markki + "ajaxAction=getRowCount&table=" + table);
    http.onreadystatechange = function(){
		if(http.readyState == 4){
			var numRows = http.responseText;
			$("." + table + "_rowCount").html(numRows);
		}
	}
    http.send(null);
}

function sndReqNoResponse(action) {
    http.open('get', action);
    http.onreadystatechange = doNothing;
    http.send(null);
}

function sndReqNoResponseChk(action) {
    //due to speed, we do this call without async
    http.open('get', action, false);
    http.onreadystatechange = doNothing;
    http.send(null);
}

//do NOTHING! :-)
function doNothing(){
}


/* other necessary js functions */

function changeSort(table, field_name, sort_direction){
	//this should also maintain the filtering when sorting
	sortReq = "&sort_field=" + field_name + "&sort_direction=" + sort_direction;
	var req = getThisPage() + "table=" + table + sortReq + filterReq;
	sndSortReq(req, table);
	return false;
}

function pageTable(params, table){
	var req = getThisPage() + "table=" + table + params + sortReq + filterReq;
	pageReq = params;
	//setLoadingImage(table);
	sndSortReq(req, table);
	return false;
}

function setLoadingImage(table){
	document.getElementById(table).innerHTML = loading_image_html;
}

function filterTable(obj, table, field, query_string){
	var filter_fields = getFormValues(document.getElementById(table + '_filter_form'), '');
    if (filter_fields != ''){
    	var req = getThisPage() + filter_fields + "&table=" + table + "&" + query_string;
    	filterReq = "&table=" + table + "&" + filter_fields + "&" + query_string; //this var declared at the top of this file
    }
    else{
		var req = getThisPage() + "action=unfilter";
		filterReq = "&action=unfilter";
	}

	// function to send the filter
	var func = function() {
		setLoadingImage(table);
		sndFilterReq(req, table);
	};

	// Check to see if there is already a timeout and if so...cancel it and create a new one
	if ( obj.zid ) {
		clearTimeout(obj.zid);
	}

	//set a timeout after typing in filter field (reduces number of calls to db)
	obj.zid = setTimeout(func, 1200);
}

function confirmDelete(id, table, pk){
	if(confirm(deleteMessageText)) {
		ajax_deleteRow(id, table, pk);
	}
}
function deleteFile(field, id){
	if(confirm('Are you sure you want to delete this file? This cannot be undone.')) {
		location.href="?action=delete_file&field_name=" + field + "&id=" + id;
	}
}

function ajax_deleteRow(id, table, pk){
	var markki = "?";
	if (ajax_file.search("[?]") > 0) { markki = "&"; }

	var req = ajax_file + markki + 'ajaxAction=delete&id=' + id + '&table=' + table + '&pk=' + pk;
	sndDeleteReq(req);
}

//for handling all ajax editing
//TODO: make function name less generic
function handleUpdateResponse() {
    if(http.readyState == 4){

        var return_string = http.responseText;

        //if there's an error in the update
        if (return_string.substring(0,5) == 'error'){
            var broken_string = return_string.split("|");
            var id = broken_string[1];
            var old_value = broken_string[2];

            //only enter an alert if you want to. we removed because so many people complained
            //window.alert('No changes made to cell.');

            //display the display section, fill it with prior content
            document.getElementById(id+'_show').innerHTML = old_value;
            document.getElementById(id+'_show').style.display = '';
            //hide editing and saving sections
            document.getElementById(id+'_edit').style.display = 'none';
            document.getElementById(id+'_save').style.display = 'none';
        }
        else{
            var broken_string = return_string.split("|");
            var id = broken_string[0];
            var replaceText = myStripSlashes(broken_string[1]);

			//display the display section, fill it with new content
			if (replaceText != "{selectbox}"){
				if (replaceText != null){
					document.getElementById(id+'_show').innerHTML = replaceText;
				}
				else{
					document.getElementById(id+'_show').innerHTML = "";
				}
			}
			else{
				var the_selectbox = document.getElementById("dropdown_" + id);
				document.getElementById(id+'_show').innerHTML = the_selectbox.options[the_selectbox.selectedIndex].text;
			}
            document.getElementById(id+'_show').style.display = '';
            //hide editing and saving sections
            document.getElementById(id+'_edit').style.display = 'none';
            document.getElementById(id+'_save').style.display = 'none';
        }
    }
}

function getFormValues(fobj,valFunc) {

	var str = "";
	var valueArr = null;
	var val = "";
	var cmd = "";
	var element_type;
	for(var i = 0;i < fobj.elements.length;i++) {
		element_type = fobj.elements[i].type;

		if (element_type == 'text' || element_type == 'textarea'){
			if(valFunc) {
				//use single quotes for argument so that the value of
				//fobj.elements[i].value is treated as a string not a literal
				cmd = valFunc + "(" + 'fobj.elements[i].value' + ")";
				val = eval(cmd)
			}

			//str += fobj.elements[i].name + "=" + escape(fobj.elements[i].value) + "&";
			str += fobj.elements[i].name + "=" + cleanseStrForURIEncode(myAddSlashes(fobj.elements[i].value)) + "&";
		}
		else if(element_type == 'select-one'){
			str += fobj.elements[i].name + "=" + fobj.elements[i].options[fobj.elements[i].selectedIndex].value + "&";
		}
		else if(element_type == 'checkbox'){
			var chkValue = '';
			if (fobj.elements[i].checked){
				//var chkValue = escape(fobj.elements[i].value);
				var chkValue = cleanseStrForURIEncode(fobj.elements[i].value);
			}
			str += fobj.elements[i].name + "=" + chkValue + "&";
		}
	}

	str = str.substr(0,(str.length - 1));
	return str;
}

function clearForm(formIdent){
	var form, elements, i, elm;
	form = document.getElementById ? document.getElementById(formIdent) : document.forms[formIdent];

	if (document.getElementsByTagName){
		elements = form.getElementsByTagName('input');
		for( i=0, elm; elm=elements.item(i++); ){
			if (elm.getAttribute('type') == "text"){
				elm.value = '';
			}
			else if (elm.getAttribute('type') == "checkbox"){
				elm.checked = false;
			}
		}
		elements = form.getElementsByTagName('select');
		for( i=0, elm; elm=elements.item(i++); ){
			elm.options.selectedIndex=0;
		}
		elements = form.getElementsByTagName('textarea');
		for( i=0, elm; elm=elements.item(i++); ){
			elm.value = '';
		}

	}
	else{
		elements = form.elements;
		for( i=0, elm; elm=elements[i++]; ){
			if (elm.type == "text"){
				elm.value ='';
			}
		}
	}
}

/*
 * This function is to not allow non-numeric values for fields with an INT or DECIMAL datatype
 * Colaborator Juan David Ramírez
 * fenixjuano@gmail.com
 */
function fn_validateNumeric(evento, elemento, dec) {
    var valor=elemento.value;
    var charWich=evento.which;
    var charCode=evento.keyCode;
    if(charWich==null){
        charWich=charCode;
    }

    //48-57 are numbers; 8 is backspace, 9 is tab, 37 is left arrow, 39 is right arrow, 46 is delete, 13 is enter, 45 is dash (for negative numbers, 35 is end key, 36 is home key)
    if ( (charWich>=48 && charWich<=57) || charCode==8 || charCode==9 || charCode==37 || charCode==39 || charCode==46 || charWich==46 || charWich==13 || charWich==45 || charCode==35 || charCode==36) {

        if(dec=="n" && charWich == 46){
            return false;
        }
        else{
            if(valor.indexOf('.')!=-1 && charWich==46){
                return false;
            }
        }
        return true;
    }
    else{
        return false;
    }
}

//added 2-3-2013, to fix the issue with # characters not being encoded properly by encodeURI function
function cleanseStrForURIEncode(str){
	str = encodeURI(str);
	str = str.replace(/#/g, '%23');
	str = str.replace(/&/g, '%26');
	str = str.replace(/>/g, "&gt;");
	str = str.replace(/</g, "&lt;");
	str = str.replace(/"/g, "&quot;");
	return str;
}

function myAddSlashes(str) {
    str=str.replace(/\"/g,'\\"');
    return str;
}

function myStripSlashes(str) {
    str=str.replace(/\\'/g,'\'');
    str=str.replace(/\\"/g,'"');
    return str;
}

var prior_class = '';
function hover(obj){
    //obj.className='class_hover';
    obj.style.backgroundColor = '#FFFF99';

}

function unHover(obj){
    obj.className = '';
}

function setAllCheckboxes(str, ck) {
	var ckboxes = document.getElementsByName(str);
	for (var i=0; i < ckboxes.length; i++){
		if (ckboxes[i].checked == ck) {
			ckboxes[i].checked = ck;
			ckboxes[i].click();
		}
	}
}

//I do not know why javascript does not have this function built into the language!
Array.prototype.findIndex = function(value){
	var ctr = "";
	for (var i=0; i < this.length; i++) {
		// use === to check for Matches. ie., identical (===), ;
		if (this[i] == value) {
			return i;
		}
	}
	return ctr;
};

if('function' != typeof Array.prototype.splice) {
	Array.prototype.splice = function(s, dC) {
		s = +s || 0;
		var a = [],
		n = this.length,
		nI = Math.min(arguments.length - 2, 0), i, j;
		s = (0 > s) ? Math.max(s + n, 0) : Math.min(s, n);
		dC = Math.min(Math.max(+dC || 0, 0), n - s);
		for(i = 0; i < dC; ++i) {a[i] = this[s + i];}
		if(nI < dC) {
			for(i = s, j = n - dC; i < j; ++i) {
				this[i + nI] = this[i + dC];
			}
		} else if(nI > dC) {
			for(i = n - 1, j = s + dC; i >= j; --i) {
				this[i + nI - dC] = this[i];
			}
		}
		for(i = s, j = 2; j < nI; ++i, ++j) {this[i] = arguments[j];}
		this.length = n - dC + nI;
		return a;
	};
}

/* javascript_functions.js END */