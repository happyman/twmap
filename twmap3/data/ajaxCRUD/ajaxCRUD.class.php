<?php
	/*
		Basic users should not need to edit this file
		Instead - post questions or feature requests to our forum -
		http://ajaxcrud.com/forum/viewforum.php?f=2
	*/

	/************************************************************************/
	/* ajaxCRUD.class.php	v9.1                                            */
	/* ===========================                                          */
	/* Copyright (c) 2013 by Loud Canvas Media (arts@loudcanvas.com)        */
	/* http://www.ajaxcrud.com by http://www.loudcanvas.com                 */
	/*                                                                      */
	/* This program is free software. You can redistribute it and/or modify */
	/* it under the terms of the GNU General Public License as published by */
	/* the Free Software Foundation; either version 2 of the License.       */
	/************************************************************************/
	# thanks to the following for help on v6.0:
	# Mariano Monta�ez Ureta, from Argentina; twitter: @nanomo
	# Jing Ling, New Hampshire

	#thanks to Francisco Campos of WebLemurs.com for helping with other misc core updates for v7.2

	define('EXECUTING_SCRIPT', $_SERVER['PHP_SELF']);

	if (isset($_REQUEST['customAction'])){
		$customAction = $_REQUEST['customAction'];
		if ($customAction != ""){
			if ($customAction == 'exportToCSV'){
				$csvData = $_REQUEST['tableData'];
				$fileName = $_REQUEST['fileName'];
				header("Content-type: application/csv");
				header("Content-Disposition: attachment; filename=$fileName");
				header("Pragma: no-cache");
				header("Expires: 0");
				echo str_replace('\"','"',$csvData);
			}
			exit();
		}
	}

	#this top part is for the ajax actions themselves. the class is below

	if (isset($_REQUEST['ajaxAction'])){
		$ajaxAction = $_REQUEST['ajaxAction'];

		if ($ajaxAction != ""){

			# these lines make sure caching do not cause ajax saving/displaying issues
			header("Cache-Control: no-cache, must-revalidate"); //this is why ajaxCRUD.class.php must be before any other headers (html) are outputted
			# a date in the past
			header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");

			$table		= isset($_REQUEST["table"]) ? $_REQUEST["table"] : "";
			$pk			= isset($_REQUEST["pk"]) ? trim($_REQUEST["pk"]) : "";
			$field		= isset($_REQUEST["field"]) ? trim($_REQUEST["field"]) : "";
			$id 		= isset($_REQUEST["id"]) ? $_REQUEST["id"] : "";
			$val		= isset($_REQUEST["val"]) ? $_REQUEST["val"] : "";
			$table_num	= isset($_REQUEST["table_num"]) ? $_REQUEST["table_num"] : "";

			if (!is_numeric($id)){
				$sql_id = "\"$id\"";
			}
			else{
				$sql_id = $id;
			}

			if ($ajaxAction == 'add'){
				echo $_SESSION[$table];
			}

			if ($ajaxAction == 'filter'){
				echo $_SESSION[$table];
			}

			if ($ajaxAction == 'sort'){
				echo $_SESSION[$table];
			}

			if ($ajaxAction == 'getRowCount'){
				echo $_SESSION[$table . '_row_count'];
			}

			if ($ajaxAction == 'update'){
				$val = addslashes($val);
				//check to see if  record exists
				$row_current_value = q1("SELECT $pk FROM $table WHERE $pk = $sql_id");
				if ($row_current_value  == ''){
					qr("INSERT INTO $table ($pk) VALUES (\"$id\")");
				}

				$success = qr("UPDATE $table SET $field = \"$val\" WHERE $pk = $sql_id");

				if ($val == '') $val = "&nbsp;&nbsp;";

				//when updating, we use the Table name, Field name, & the Primary Key (id) to feed back to client-side-processing
				$prefield = trim($table . $field . $id);

				if (isset($_REQUEST['dropdown_tbl'])){
					$val = "{selectbox}";
				}

				if ($success){
					echo $prefield . "|" . stripslashes($val);
				}
				else{
					echo "error|" . $prefield . "|" . stripslashes($val);
				}
			}

			if ($ajaxAction == 'delete'){
				qr("DELETE FROM $table WHERE $pk = $sql_id");
				echo $table . "|" . $id;
			}

			exit();
		}
	}

// THE AJAXCRUD CLASS FOLLOWS:

// Use:
// Create an ajaxCRUD object.
// $table = new ajaxCRUD(name of item, table name, primary key);

// Example:
// $tblFAQ = new ajaxCRUD("FAQ", "tblFAQ", "pkFAQID");
// $tblFAQ->showTable();
//
// Note: !! Your table must have AUTO_INCREMENT enabled for the primary key !!
// Note: !! Your version of mySQL must support string->INT conversion (thus "1" = 1) and "" is a NULL value !!

class ajaxCRUD{

    var $ajaxcrud_root;
    var $ajax_file;
    var $css_file;
    var $css = true; 	//indicates a css spredsheet WILL be used
    var $add = true;    //adding is ok

    var $includeTableHeaders = true; //include table headers (default)
    var $includeJQuery 	= true; //include jquery (default)

    var $allowHeaderInsert = true; //insert the jquery/css files by default [you can insert whereever you want in your script with $yourObject->insertHeader();]

    var $doActionOnShowTable; //boolean var. When true and showTable() is called, doAction() is also called. turn off when you want to only have a table show in certain conditions but CRUD operations can take place on the table "behind the scenes"

    var $item_plural;
	var $item;

	var $db_table;
	var $db_table_pk;
	var $db_main_field;
    var $row_count;

    var $table_html; //the html for the table (to be modified on ADD via ajax)

    var $cellspacing;

    var $showPaging = true;
    var $limit; // limit of rows to display on one page. defaults to 50
    var $sql_limit;

    var $filtered_table = false; //the table is by default unfiltered (eg no 'where clause' on it)
    var $ajaxFilter_fields = array(); //array of fields that can be are filtered by ajax (creates a textbox at the top of the table)
    var $ajaxFilterBoxSize = array(); //array (sub fieldname) holding size of the input box

    //all fields in the table
    var $fields = array();
	var $field_count;

    //field datatypes
    var $field_datatype = array(); //$field_datatype[field] = datatype

    //allow delete of fields | boolean variable set to true by default
    var $delete;

    //defines if the add functionality uses ajax
    var $ajax_add = true;

    //defines if the class allows you to edit all fields (only used to turn off ALL editing completely)
    var $ajax_editing = true;

    //defines if the class allows you to sort all fields
    var $ajax_sorting = true;

    //the fields to be displayed
    var $display_fields = array();

    //the fields to be inputted when adding a new entry (90% time will be all fields). can be changed via the omitAddField method
    var $add_fields = array();
    var $add_form_top = FALSE; //the add form (by default) is below the table. use displayAddFormTop() to bring it to the top

    //the fields which are displayed, but not editable
    var $uneditable_fields = array();

    //the header fields which are displayed, but not sortable (i.e. click to sort)
    var $unsortable_fields = array();

	var $sql_where_clause;
    var $sql_where_clauses = array(); //array used IF there is more than one where clause

	var $sql_order_by;
    var $num_where_clauses;

    var $on_add_specify_primary_key = false;

    //table border - default is off: 0
    var $border;

    var $orientation; //orientation of table (detault is horizontal)

	var $showCSVExport = false;	// indicates whether to show the "Export Table to CSV" button

    //array containing values for a button next to the "go back" button at the bottom. [0] = value [1] = url [2] = extra tags/javascript
    var $bottom_button = array();

    //array with value being the url for the buttom to go to (passing the id) [0] = value [1] = url
    var $row_button = array();

	//array with value being "same" or "new" - specifying the target window for the opening the page. index of array is the button 'id'
    var $addButtonToRowWindowOpen = "";

    ################################################
    #
    # The following are parallel arrays to help in the definition of a defined db relationship
    #
    ################################################

    //values will be the name(s) of the foreign key(s) for a category table
	var $db_table_fk_array = array();

    //values will be the name(s) of the category table(s)
	var $category_table_array = array();

    //values will be the name(s) of the primary key for the category table(s)
	var $category_table_pk_array = array();

    //values will be the name(s) of the field to return in the category table(s)
	var $category_field_array = array();

    //values will be the (optional) name of the field to sort by in the category table(s)
    var $category_sort_field_array = array();

    //values will be the (optional) whereclause for the fk clause
    var $category_whereclause_array = array();

    //for dropdown (to make an empty box). (format: array[field] = true/false)
    var $category_required = array();

    // allowable values for a field. the key is the name of the field
    var $allowed_values = array();

	// "on" and "off" values for a checkbox. the key is the name of the field
    var $checkbox = array();

	// holds the field names of columns that will have a "check all" checkbox
	var $checkboxall = array();

    //values to be set to a particular field when a new row is added. the array is set as $field_name => $add_value
    var $add_values = array();

    //destination folder to be set for a particular field that allows uploading of files. the array is set as $field_name => $destination_folder
    var $file_uploads = array();
    var $file_upload_info = array(); //array[$field_name]['destination_folder'], array[$field_name]['relative_folder'], and array[$field_name]['permittedFileExts']
    var $filename_append_field = "";

    //array dictating that "dropdown" fields do not show dropdown (but text editor) on edit (format: array[field] = true/false);
    //used in defineAllowableValues function
    var $field_no_dropdown = array();

    //array holding the (user-defined) function to format a field with on display (format: array[field] = function_name);
    //used in formatFieldWithFunction function
    var $format_field_with_function 	= array();
    var $validate_delete_with_function 	= ""; //used to determine if a particular row can be deleted or not (user-defined function)

    //used in formatFieldWithFunctionAdvanced function (takes a second param - the id of the row)
    var $format_field_with_function_adv = array();

    var $onAddExecuteCallBackFunction;
    var $onUpdateExecuteCallBackFunction = array(); //this is an array because callback methods for update (unlike add) are field based
    var $onFileUploadExecuteCallBackFunction;
    var $onDeleteFileExecuteCallBackFunction;

    //(if true) put a checkbox before each row
    var $showCheckbox;

    var $loading_image_html;

    var $emptyTableMessage;

	/* these default to english words (e.g. "Add", "Delete" below); but can be
	   changed by setting them via $obj->addText = "A�adir"
	*/
	var $addText, $deleteText, $cancelText, $actionText, $fileDeleteText, $fileEditText; //text values for buttons and other table text
	var $addButtonText; //if you want to replace the entire add button text with a phrase or other text. Added in 8.81
	var $addMessage; //used when onAddExecuteCallBackFunction is leveraged

    var $sort_direction; //used when sorting the table via ajax

    ################################################
    #
    # displayAs array is for linking a particular field to the name that displays for that field
    #
    ################################################

    //the indexes will be the name of the field. the value is the displayed text
    var $displayAs_array = array();

    //height of the textarea for certain fields. the index is the field and the value is the height
    var $textarea_height = array();

    var $textboxWidth = array(); //if defined for regular text input boxes, this will alter how ADD fields are displayed

    //any 'notes' to display next to a field when adding a row
    var $fieldNote = array();

    //a placeholder text to give to the field when ADDing a new row
    var $placeholderText = array();

    //variable used to capture which search fields should be EXACT matches vs approximate match (using LIKE %search%)
    var $exactSearchField = array(); //set by setExactSearchField (and automatically set for fields using defineRelationship and defineAllowableValues)

    //set manually - initial value for a field (when adding a row)
    var $initialFieldValue = array();

	// Array to include css style classes in specified fields
	var $display_field_with_class_style = array();

	// Constructor
    //by default ajaxCRUD assumes all necessary files are in the same dir as the script calling it (eg $ajaxcrud_root = "")
    function ajaxCRUD($item, $db_table, $db_table_pk, $ajaxcrud_root = "") {

        //global variable - for allowing multiple ajaxCRUD tables on one page
        global $num_ajaxCRUD_tables_instantiated;
        if ($num_ajaxCRUD_tables_instantiated === "") $num_ajaxCRUD_tables_instantiated = 0;

        global $headerAdded;
        if ($headerAdded === "") $$headerAdded = FALSE;

        $this->showCheckbox     = false;
        $this->ajaxcrud_root    = $ajaxcrud_root;
        $this->ajax_file        = EXECUTING_SCRIPT;

		$this->item 			= $item;
		$this->item_plural		= $item . "s";

		$this->db_table			= $db_table;
		$this->db_table_pk		= $db_table_pk;

		$this->fields 			= $this->getFields($db_table);
		$this->field_count 		= count($this->fields);

        //by default paging is turned on; limit is 50
        $this->showPaging       = true;
        $this->limit            = 50;
        $this->num_where_clauses = 0;

        $this->delete           = true;
        $this->add              = true;

        //assumes the primary key is auto incrementing
        $this->primaryKeyAutoIncrement = true;

        $this->border           = 0;
        $this->css              = true;
        $this->ajax_add         = true;
        $this->orientation 		= 'horizontal';

        $this->addButtonToRowWindowOpen = 'same'; //global window to open pages in - used when adding custom buttons to a row

        $this->doActionOnShowTable = true;

        $this->loading_image_html = "<center><br /><br  /><img src=\'" . $this->ajaxcrud_root . "css/loading.gif\'><br /><br /></center>"; //changed via setLoadingImageHTML()

        $this->addText			 = "Add";
        $this->deleteText		 = "Delete";
        $this->cancelText		 = "Cancel";
        $this->actionText		 = "Action";
        $this->fileEditText	 	 = "edit"; //added in 8.81 (for file crud)
        $this->fileDeleteText	 = "del"; //added in 8.81 (for file crud)

        $this->emptyTableMessage = "No data in this table. Click add button below.";
        $this->addButtonText	 = ""; //when blank, button text defaults to 'Add {Item}' text unless when $addText is set then defaults to '{addText} {Item}'; if set the entire button is replaced with {addButtonText}
        $this->addMessage		 = ""; //when blank, defaults to generic '{Item} added' message

        $this->onAddExecuteCallBackFunction         = '';
        $this->onFileUploadExecuteCallBackFunction  = '';
        $this->onDeleteFileExecuteCallBackFunction  = '';

        //don't allow primary key to be editable
        $this->uneditable_fields[] = $this->db_table_pk;

        $this->display_fields   = $this->fields;
        $this->add_fields       = $this->fields;

        //default sort direction
        $this->sort_direction	= "desc";

		if ($this->field_count == 0){
			$error_msg[] = "No fields in this table!";
			echo_msg_box();
			exit();
		}

		return true;
	}

	function getNumRows(){
		$sql = "SELECT COUNT(*) FROM " . $this->db_table . $this->sql_where_clause;
		$numRows = q1($sql);
		return $numRows;
	}

	function setAjaxFile($ajax_file){
        $this->ajax_file = $ajax_file;
    }

	function setOrientation($orientation){
        $this->orientation = $orientation;
    }

    function turnOffAjaxADD(){
        $this->ajax_add = false;
    }

    function turnOffAjaxEditing(){
        $this->ajax_editing = false;
        foreach ($this->fields as $field){
			$this->disallowEdit($field);
		}
    }

    function turnOffSorting(){
        $this->ajax_sorting = false;
        foreach ($this->fields as $field){
			$this->disallowSort($field);
		}
    }

    function turnOffPaging($limit = ""){
        $this->showPaging = false;
        if ($limit != ''){
            $this->sql_limit = " LIMIT $limit";
        }
    }

	function disableTableHeaders() {
		$this->includeTableHeaders = false;
	}

	function disableJQuery() {
		$this->includeJQuery = false;
	}


    function setCSSFile($css_file){
        $this->css_file = $css_file;
    }

    function setLoadingImageHTML($html){
        $this->loading_image_html = $html;
    }

    function addTableBorder(){
        $this->border = 1;
    }

    function addAjaxFilterBox($field_name, $textboxSize = 10, $exactSearch = FALSE){
        $this->ajaxFilter_fields[] = $field_name;

        //defaults to size of "10" (unless changed via setAjaxFilterBoxSize)
        $this->setAjaxFilterBoxSize($field_name, $textboxSize);
        if ($exactSearch === TRUE){
        	$this->setExactSearchField($field_name);
        }
    }

    function setAjaxFilterBoxSize($field_name, $size){
        $this->ajaxFilterBoxSize[$field_name] = $size; //this function is deprecated, as of v6.0
    }

    function addAjaxFilterBoxAllFields(){
        //unset($this->ajaxFilter_fields);
        foreach ($this->display_fields as $field){
            $this->addAjaxFilterBox($field);
        }
    }

    function displayAddFormTop(){
    	$this->add_form_top = TRUE;
    }

    function addWhereClause($sql_where_clause){
        $this->num_where_clauses++;
        $this->sql_where_clauses[] = $sql_where_clause;

        if ($this->num_where_clauses <= 1){
            $this->sql_where_clause = " " . $sql_where_clause;
        }
        else{
            //chain multiple together
            $whereClause = ""; //start the clause now chain to it
            $count = 0;
            foreach($this->sql_where_clauses as $where_clause){
				if ($count > 0){
					//$where_clause = str_replace("WHERE", "AND", $where_clause);
					$where_clause = preg_replace('/WHERE/', 'AND', $where_clause, 1); // Only replace the FIRST instance; the magic is in the optional fourth parameter [Limit] (this is important because of sub queries which uses a second WHERE statement)
				}
				$whereClause .= " $where_clause";
				$count++;
            }

            $this->sql_where_clause = " $whereClause";
        }

		$_SESSION['ajaxcrud_where_clause'][$this->db_table] = $this->sql_where_clause;
	}

	function addOrderBy($sql_order_by){
		$this->sql_order_by = " " . $sql_order_by;
	}

	/* added in release 6.0 */
	function orderFields($fieldsString){
		/* warning - if you add a field to this list which is not in the database,
		   you may have unintended results */

		//separate fieldsString with ","
		$fieldsString = str_replace(" ", "", $fieldsString); //parse out any spaces
		$fieldsArray = explode(",", $fieldsString);

		foreach($this->display_fields as $d){
			if(!in_array($d,$fieldsArray))
				$fieldsArray[] = $d;
		}

		$this->display_fields = $fieldsArray;
	}

    function formatFieldWithFunction($field, $function_name){
        $this->format_field_with_function[$field] = $function_name;
    }

    function formatFieldWithFunctionAdvanced($field, $function_name){
        $this->format_field_with_function_adv[$field] = $function_name;
    }

    /*	added in R8.7
    	uses a user-defined function which will return true or false re whether THAT ROW
    	is deletable (validateDeleteWithFunction)  or any any field in that row is editable (validateUpdateWithFunction)
    	Example and Documentation: http://ajaxcrud.com/api/index.php?id=validateDeleteWithFunction
    	Example and Documentation: http://ajaxcrud.com/api/index.php?id=validateUpdateWithFunction
    */
    function validateDeleteWithFunction($function_name){
    	$this->validate_delete_with_function = $function_name;
    }

    function validateUpdateWithFunction($function_name){
    	$this->validate_update_with_function = $function_name;
    }

    function defineRelationship($field, $category_table, $category_table_pk, $category_field_name, $category_sort_field = "", $category_required = "1", $where_clause = ""){

        $this->db_table_fk_array[]          = $field;
        $this->category_table_array[]       = $category_table;
        $this->category_table_pk_array[]    = $category_table_pk;
        $this->category_field_array[]       = $category_field_name;
        $this->category_sort_field_array[]  = $category_sort_field;
        $this->category_whereclause_array[] = $where_clause;

        //make the relationship required for the field
        if ($category_required == "1"){
            $this->category_required[$field] = TRUE;
        }

        $this->setExactSearchField($field); //set search field to use exact matching (as of v7.2.1)
    }

    function relationshipFieldOptional(){
        $this->cat_field_required = FALSE;
    }

	function defineAllowableValues($field, $array_values, $onedit_textbox = FALSE){
		//array with the setup [0] = value [1] = display name (both the same)
		$new_array = array();

		foreach($array_values as $array_value){
			if (!is_array($array_value)){
                //a two-dimentential array --> set both the value and dropdown text to be the same
                $new_array[] = array(0=> $array_value, 1=>$array_value);
            }
            else{
                //a 2-dimentential array --> value and dropdown text are different
                $new_array[] = $array_value;
            }
		}

		if ($onedit_textbox != FALSE){
			$this->field_no_dropdown[$field] = TRUE;
		}

		$this->allowed_values[$field] = $new_array;
		$this->setExactSearchField($field); //set search field to use exact matching (as of 7.2.1)
	}

	function defineCheckbox($field, $value_on="1", $value_off="0"){
		$new_array = array($value_on, $value_off);

		$this->checkbox[$field] = $new_array;
	}

	function showCheckboxAll($field, $display_data) {
		$this->checkboxall[$field] = $display_data;
	}

    function displayAs($field, $the_field_name){
        $this->displayAs_array[$field] = $the_field_name;
    }

    function setTextareaHeight($field, $height){
        $this->textarea_height[$field] = $height;
    }

    function setTextboxWidth($field, $width){
        $this->textboxWidth[$field] = $width;
    }

    function setAddFieldNote($field, $caption){
        $this->fieldNote[$field] = $caption;
    }

    /* added in R8.0 */
    function setAddPlaceholderText($field, $placeholder){
        $this->placeholderText[$field] = $placeholder;
    }

	/* added in R7.2.1 */
	function setExactSearchField($field) {
		$this->exactSearchField[$field] = true;
	}

    function setInitialAddFieldValue($field, $value){
        $this->initialFieldValue[$field] = $value;
    }

    function setLimit($limit){
        $this->limit = $limit;
    }

    //DEPRECATED - use insertRowsReturned instead for realtime updating with ajax
    function getRowCount(){
        if ($_SESSION['row_count'] == ""){
        	$count = $this->getNumRows();
        }
        else{
        	$count = $_SESSION['row_count'];
        }
        //return $count;
        return "<span id='" . $this->db_table . "_RowCount'>" . $count . "</span>";
    }

    function getTotalRowCount(){
        $count = q1("SELECT COUNT(*) FROM " . $this->db_table);
        return $count;
    }

	function omitField($field_name){
        $key = array_search($field_name, $this->display_fields);

        if ($this->fieldInArray($field_name, $this->display_fields)){
            unset($this->display_fields[$key]);
        }
        else{
            $error_msg[] = "Error in your doNotDisplay function call. There is no field named <b>$field_name</b> in the table <b>" . $this->db_table . "</b>";
        }
    }

    function omitAddField($field_name){
        $key = array_search($field_name, $this->add_fields);

        if ($key !== FALSE){
            unset($this->add_fields[$key]);
        }
        else{
            $error_msg[] = "Error in your omitAddField function call. There is no field named <b>$field_name</b> in the table <b>" . $this->db_table . "</b>";
        }
    }

    function omitFieldCompletely($field_name){
        $this->omitField($field_name);
        $this->omitAddField($field_name);
    }

	/* added with R6.0 */
	function showOnly($fieldsString){
		//separate fieldsString with ","
		$fieldsString = str_replace(" ", "", $fieldsString); //parse out any spaces
		$fieldsArray = explode(",", $fieldsString);

        $this->display_fields   = $fieldsArray;
        $this->add_fields       = $fieldsArray;
    }

    function addValueOnInsert($field_name, $insert_value){
        $this->add_values[] = array(0 => $field_name, 1 => $insert_value);
    }

    function onAddExecuteCallBackFunction($function_name){
        $this->onAddExecuteCallBackFunction = $function_name;
        $this->ajax_add = false;
    }

    function onUpdateExecuteCallBackFunction($field_name, $function_name){
        $this->onUpdateExecuteCallBackFunction[$field_name] = $function_name;
    }

    function onFileUploadExecuteCallBackFunction($function_name){
        $this->onFileUploadExecuteCallBackFunction = $function_name;
    }

    function onDeleteFileExecuteCallBackFunction($function_name){
        $this->onDeleteFileExecuteCallBackFunction = $function_name;
    }

    function primaryKeyNotAutoIncrement(){
        $this->primaryKeyAutoIncrement = false;
    }

    //the forth optional param (permittedFileExts) was added in v8.9; it is an ARRAY of permitted file extensions allowed for upload; e.g. array("png", "jpg")
    function setFileUpload($field_name, $destination_folder, $relative_folder = "", $permittedFileExts = ""){
        //put values into array
        $this->file_uploads[] = $field_name;
        $this->file_upload_info[$field_name]['destination_folder'] = $destination_folder;
        $this->file_upload_info[$field_name]['relative_folder'] = $relative_folder;

        //added in v8.9
        if (is_array($permittedFileExts)){
	        $this->file_upload_info[$field_name]['permittedFileExts'] = $permittedFileExts;
	    }

        //the filenames that are saved are not editable
        //$this->disallowEdit($field_name);

        //have to add the row via POST now
        $this->ajax_add = false;
    }

    function appendUploadFilename($append_field){
        $this->filename_append_field = $append_field;
    }

    function omitPrimaryKey(){

        //99% time it'll be in key 0, but just in case do search
        $key = array_search($this->db_table_pk, $this->display_fields);
        unset($this->display_fields[$key]);
    }

	function showCSVExportOption() {
		$this->showCSVExport = true;
	}

	function modifyFieldWithClass($field, $class_name){
        $this->display_field_with_class_style[$field] = $class_name;
    }

    function insertRowsReturned(){
    	$numRows = $this->getNumRows();
    	echo "<span class='" . $this->db_table . "_rowCount'>" . $numRows . "</span>";
    }

    function insertHeader($ajax_file = "ajaxCRUD.inc.php"){

        global $headerAdded;
        $headerAdded = TRUE;

        if ($this->css_file == ''){
            $this->css_file = 'default.css';
        }

		/* Load Javascript dependencies */
		if ($this->includeJQuery){
			//echo "<script type=\"text/javascript\" src=\"http://ajax.googleapis.com/ajax/libs/jquery/1.3.2/jquery.min.js\"></script>\n"; //rel 3.5 - using jquery instead of protoculous
    		//echo "<script type=\"text/javascript\" src=\"http://code.jquery.com/jquery-latest.min.js\"></script>\n"; //rel 6 - using latest version of jquery from jquery site (http://docs.jquery.com/Plugins/Validation/Validator)
			if (isset($LOCAL_JS) && $LOCAL_JS) {
				echo "<script type=\"text/javascript\" src=\"" . $this->ajaxcrud_root . "js/jquery.min.js\"></script>\n";          // EDITED 1/16/2012 - library on code.jquery site stopped working correctly!! (giving error TypeError: $.browser is undefined)
				echo "<script type=\"text/javascript\" src=\"" . $this->ajaxcrud_root . "js/jquery.validate.min.js\"></script>\n"; //rel 6 - added ability to validate forms fields
				echo "<script type=\"text/javascript\" src=\"" . $this->ajaxcrud_root . "js/jquery.maskedinput.js\"></script>\n";  //rel 6 - ability to mask fields (http://digitalbush.com/projects/masked-input-plugin/)
			} else {
				echo "<script type=\"text/javascript\" src=\"http://ajax.googleapis.com/ajax/libs/jquery/1.8.3/jquery.min.js\"></script>\n"; // EDITED 1/16/2012 - library on code.jquery site stopped working correctly!! (giving error TypeError: $.browser is undefined)
				echo "<script type=\"text/javascript\" src=\"http://ajax.aspnetcdn.com/ajax/jquery.validate/1.7/jquery.validate.min.js\"></script>\n"; //rel 6 - added ability to validate forms fields
				echo "<script type=\"text/javascript\" src=\"http://ajaxcrud.com/code/jquery.maskedinput.js\"></script>\n"; //rel 6 - ability to mask fields (http://digitalbush.com/projects/masked-input-plugin/)
			}
			echo "<script type=\"text/javascript\" src=\"" . $this->ajaxcrud_root . "js/validation.js\"></script>\n";
		}
        echo "<meta http-equiv=\"content-type\" content=\"text/html; charset=UTF-8\" />\n";
        echo "<script type=\"text/javascript\" src=\"" . $this->ajaxcrud_root . "js/javascript_functions.js\"></script>\n";
        echo "<link href=\"" . $this->ajaxcrud_root . "css/" . $this->css_file . "\" rel=\"stylesheet\" type=\"text/css\" media=\"screen\" />\n";

        echo "
            <script>\n
                ajax_file = \"$this->ajax_file\"; \n
                this_page = \"" . $_SERVER['REQUEST_URI'] . "\"\n
                loading_image_html = \"$this->loading_image_html\"; \n

                function validateAddForm(tableName, usePost){
            		var validator = $('#add_form_' + tableName).validate();
            		if (validator.form()){
						if (!usePost){
							setLoadingImage(tableName);
							var fields = getFormValues(document.getElementById('add_form_' + tableName), '');
							fields = fields + '&table=' + tableName;
							var req = '" . $this->getThisPage() . "action=add&' + fields;
							//validator.resetForm();
							clearForm('add_form_' + tableName);
							sndAddReq(req, tableName);
							return false;
						}
						else{
							//post the form normally (e.g. if using file uploads)
							$('#add_form_' + tableName).submit();
						}

                    }
                    return false;
                }

				$(document).ready(function(){
					$(\"#add_form_{$this->db_table}\").validate();
				});

            </script>\n";
		echo "
			<style>
				/* this will only work when your HTML doctype is in \"strict\" mode.
					In other words - put this in your header:
				   <!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Strict//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd\">
				*/

				.hand_cursor{
					cursor: pointer; /* hand-shaped cursor */
					cursor: hand; /* for IE 5.x */
				}

				.editable:hover, p.editable:hover{
					background-color: #FFFF99;
				}
			</style>\n";
			// happyman
		  if ($_GET['form'] == 'add'){
                                        echo "<script>$(document).ready(function(){
                                                $('#add_form_point').slideDown('slow');});</script>";
                                }

		return true;
	}

    function disallowEdit($field){
        $this->uneditable_fields[] = $field;
    }

    function disallowSort($field){
        $this->unsortable_fields[] = $field;
    }

    function disallowDelete(){
        $this->delete = false;
    }

    function disallowAdd(){
        $this->add = false;
    }

    function addButton($value, $url, $tags = ""){
        $this->bottom_button[] = array(0 => $value, 1 => $url, 2 => $tags);
    }

    function addButtonToRow($value, $url, $attach_params = "", $javascript_tags = "", $windowToOpen = "same"){
        $this->row_button[] = array(0 => $value, 1 => $url, 2 => $attach_params, 3 => $javascript_tags, 4 => $windowToOpen);
    }

    function onAddSpecifyPrimaryKey(){
        $this->on_add_specify_primary_key = true;
    }

    function doCRUDAction(){
        if ($_REQUEST['action'] != ''){
            $this->doAction($_REQUEST['action']);
        }
    }

	function doAction($action){

		global $error_msg;
		global $report_msg;

		$item = $this->item;

		if ($action == 'delete' && $_REQUEST['id'] != ''){
			$delete_id = $_REQUEST['id'];
            $success = qr("DELETE FROM $this->db_table WHERE $this->db_table_pk = \"$delete_id\"");
			if ($success){
				$report_msg[] = "$item " . $this->deleteText . "d";
			}
			else{
				$error_msg[] = "$item could not be deleted. Please try again.";
			}
		}//action = delete

		#adding new item (via traditional way, non-ajax -- note: this is the ONLY way files can be uploaded with ajaxCRUD)
		if ($action == 'add'){

            //this if condition is so MULTIPLE ajaxCRUD tables can be used on the same page.
            if ($_REQUEST['table'] == $this->db_table){

                //for sql insert statement
                $submitted_values = array();

                //for callback function (if defined)
                $submitted_array = array();

                //this new row has (a) file(s) coming with it
                $uploads_on = $_REQUEST['uploads_on'];
                if ($uploads_on == 'true' && $_FILES){
                    $uploads_on = true;
                }

				$arrayKeyForFoundPK = array_search($this->db_table_pk, $this->fields);
                foreach($this->fields as $field){

                    $submitted_value_cleansed = "";
                    if ($_REQUEST[$field] == ''){
                        if ($this->fieldIsInt($this->getFieldDataType($field)) || $this->fieldIsDecimal($this->getFieldDataType($field))){
                            $submitted_value_cleansed = 0;
                        }
                    }
                    else{
                        $submitted_value_cleansed = $_REQUEST[$field];
                    }

					if ($uploads_on){
						if ($this->fieldInArray($field, $this->file_uploads)){
							$submitted_value_cleansed = $_FILES[$field]["name"];
						}
					}

                    $submitted_values[] = $submitted_value_cleansed;
                    //also used for callback function
                    $submitted_array[$field] = $submitted_value_cleansed;
                }

                //for adding values to the row which were not in the ADD row table - but are specified by ADD on INSERT
                if (count($this->add_values) > 0){
                    foreach ($this->add_values as $add_value){
                        $field_name 	= $add_value[0];
                        $the_add_value 	= $add_value[1];

                        if ($submitted_array[$field_name] == ''){
                            $submitted_array[$field_name] = $the_add_value;
                        }

                        //reshuffle numeric indexed array
                        unset($submitted_values);
                        $submitted_values = array();
                        foreach($submitted_array as $field){
							//$field = str_replace('"', "'", $field);
							//$field = str_replace('\\', "/", $field);
							$field = addslashes($field);
                            $submitted_values[] = $field;
                        }

                    }//foreach
                }//if count add_values > 0

                //get rid of the primary key in the fields column
                if (!$this->on_add_specify_primary_key){
					if (is_numeric($arrayKeyForFoundPK)){
	                    unset($submitted_values[$arrayKeyForFoundPK]);
	                }
                }

                //wrap each field in quotes
                $string_submitted_values = "\"" . implode("\",\"", $submitted_values) . "\"";

                //for getting datestamp of new row for mysql's "NOW" to work
                $string_submitted_values = str_replace('"NOW()"', 'NOW()', $string_submitted_values);

                if ($string_submitted_values != ''){
                    if (!$this->on_add_specify_primary_key && $this->primaryKeyAutoIncrement){
                        //don't allow the primary key to be inputted
                        $fields_array_without_pk = $this->fields;

                        if (is_numeric($arrayKeyForFoundPK)){
                        	unset($fields_array_without_pk[$arrayKeyForFoundPK]);
                        }

                        $string_fields_without_pk = implode(",", $fields_array_without_pk);

                        $query = "INSERT INTO $this->db_table($string_fields_without_pk) VALUES ($string_submitted_values)";
                    }
                    else{
                        if (!$this->primaryKeyAutoIncrement){
                            $primary_key_value = q1("SELECT MAX($this->db_table_pk) FROM $this->db_table");
                            if ($primary_key_value > 0){
                            	$primary_key_value++;
                            }
                            $primary_key_value = $primary_key_value . ", ";
                        }

                        $string_fields_with_pk = implode(",", $this->fields);
                        $query = "INSERT INTO $this->db_table($string_fields_with_pk) VALUES ($primary_key_value $string_submitted_values)";
                    }
                    $success = qr($query);

                    if ($success){
                        global $mysqliConn, $useMySQLi;
                        if ($useMySQLi){
                        	$insert_id = $mysqliConn->insert_id;
                        }
                        else{
                        	$insert_id = mysql_insert_id(); //the old, mysql way of getting it (procedurual php)
                        }

                        if ($this->addMessage == ""){
                        	$report_msg[] = "$item Added";
                        }
                        else{
                        	$report_msg[] = $this->addMessage;
                        }

                        if ($uploads_on){
                            foreach($this->file_uploads as $field_name){
                                $file_dest  = $this->file_upload_info[$field_name]['destination_folder'];

                                $allowedExts = "";
                                if (isset($this->file_upload_info[$field_name]['permittedFileExts'])){
                                	$allowedExts = $this->file_upload_info[$field_name]['permittedFileExts'];
                                }

                                if ($_FILES[$field_name]['name'] != ''){
                                    $this->uploadFile($insert_id, $field_name, $file_dest, $allowedExts);
                                }
                            }
                        }

                        if ($this->onAddExecuteCallBackFunction != ''){
                            $submitted_array['id'] = $insert_id;
                            $submitted_array[$this->db_table_pk] = $insert_id;
                            call_user_func($this->onAddExecuteCallBackFunction, $submitted_array);
                        }

                    }
                    else{
                        $error_msg[] = "$item could not be added. Please try again.";
                    }
                }
                else{
                    $error_msg[] = "All fields were omitted.";
                }

            }//if POST parameter 'table' == db_table
		}//action = add

		//this code is for POST requests (i.e. non-ajax updates)
		if ($action == 'update' && $_REQUEST['field_name'] != "" && $_REQUEST['id'] != ""){

			if ($_REQUEST['table'] == $this->db_table){//added this conditional in v8.5 to account for multiple tables
				$paramName = $_REQUEST['paramName']; //this is the param which the field update value will come by
				$val = addslashes($_REQUEST[$paramName]);
				$pkID = $_REQUEST['id'];

				$field_name = $_REQUEST['field_name'];
				$success = qr("UPDATE $this->db_table SET $field_name  = \"$val\" WHERE $this->db_table_pk = $pkID");

				if ($success){
					$report_msg[] = "$item Updated";
					if ($this->onUpdateExecuteCallBackFunction[$field_name] != ''){
						$updatedRowArray = qr("SELECT * FROM $this->db_table WHERE $this->db_table_pk = $pkID");
						$callBackSuccess = call_user_func($this->onUpdateExecuteCallBackFunction[$field_name], $updatedRowArray);
						if (!$callBackSuccess){
							//commented this out because not all callback functions will return true or false
							//$error_msg[] = "Callback function could not be executed; please check the name of your callback function.";
						}
					}
				}
				else{
					$error_msg[] = "$item could not be updated (likely because no data has not changed). Please try again.";
				}
			}
		}

        if ($action == 'upload' && $_REQUEST['field_name'] && $_REQUEST['id'] != '' && is_array($this->file_uploads) && in_array($_REQUEST['field_name'],$this->file_uploads)){
            $update_id      = $_REQUEST['id'];
            $file_field     = $_REQUEST['field_name'];
            $upload_folder  = $this->file_upload_info[$file_field]['destination_folder'];

			$allowedExts = "";
			if (isset($this->file_upload_info[$file_field]['permittedFileExts'])){
				$allowedExts = $this->file_upload_info[$file_field]['permittedFileExts'];
			}

            $success = $this->uploadFile($update_id, $file_field, $upload_folder, $allowedExts);

            if ($success){
                $report_msg[] = "File Uploaded Sucessfully.";
            }
            else{
                //$error_msg[] = "There was an error uploading your file.";
            }

        }//action = upload

        if ($action == 'delete_file' && $_REQUEST['field_name'] && $_REQUEST['id'] != ''){
            $delete_id      = $_REQUEST['id'];
            $file_field     = $_REQUEST['field_name'];
            $upload_folder  = $_REQUEST['upload_folder'];

            $filename = q1("SELECT $file_field FROM $this->db_table WHERE $this->db_table_pk = $delete_id");
            $success = qr("UPDATE $this->db_table SET $file_field = \"\" WHERE $this->db_table_pk = $delete_id");

            if ($success){
                $file_dest  = $this->file_upload_info[$file_field]['destination_folder'];

                unlink($file_dest . $filename);
                $report_msg[] = "File Deleted Sucessfully.";

                if ($this->onDeleteFileExecuteCallBackFunction != ''){
                    $delete_file_array = array();
                    $delete_file_array[id]        = $delete_id;
                    $delete_file_array[field]     = $file_field;
                    call_user_func($this->onDeleteFileExecuteCallBackFunction, $delete_file_array);
                }

            }
            else{
                $error_msg[] = "There was an error deleting your file.";
            }

        }//action = delete_file

	}//doAction

	// Cleans data up for CSV output
	function escapeCSVValue($value) {
		$value = str_replace('"', '&quot;', $value); // First off escape all " and make them HTML quotes
		if(preg_match('/,/', $value) or preg_match("/\n/", $value)) { // Check if I have any commas or new lines
			return '&quot;'.$value.'&quot;'; // If I have new lines or commas escape them
		} else {
			return $value; // If no new lines or commas just return the value
		}
	}

	// Gathers and returns table data to create a CSV file
	function createCSVOutput() {

		$headers = "";
		$data = "";
		// Gather table heading data
		$exportTableHeadings = array();
		foreach ($this->display_fields as $field){
			$field_name = $field;
			if ($this->displayAs_array[$field] != ''){
				$field = $this->displayAs_array[$field];
			}
			$field = $this->escapeCSVValue($field);

			if ($field == "ID") {
				$field = "Id";			// To prevent the SYLK error in Excel
			}

			$exportTableHeadings[] = $field;
		}
		$headers = join(',', $exportTableHeadings) . "\n";

		$sql = "SELECT * FROM " . $this->db_table . $this->sql_where_clause . $this->sql_order_by;
		$rows = q($sql);
		foreach($rows as $row){
			$exportTableData = array();
			foreach($this->display_fields as $field){
				$cell_value = $row[$field]; 	// retain original data
				$cell_data = $cell_value;

				// Check for user defined formatting functions
				if (isset($this->format_field_with_function[$field]) && $this->format_field_with_function[$field] != ''){
                    $cell_data = call_user_func($this->format_field_with_function[$field], $cell_data);
                }

				if (isset($this->format_field_with_function_adv[$field]) && $this->format_field_with_function_adv[$field] != ''){
					$cell_data = call_user_func($this->format_field_with_function_adv[$field], $cell_data, $id);
				}

				// Check whether field is a foreign key linking to another table
				$found_category_index = array_search($field, $this->db_table_fk_array);
				if (is_numeric($found_category_index)) {
					//this field is a reference to another table's primary key (eg it must be a foreign key)
					$category_field_name = $this->category_field_array[$found_category_index];
					$category_table_name = $this->category_table_array[$found_category_index];
					$category_table_pk 	 = $this->category_table_pk_array[$found_category_index];

					$selected_dropdown_text = "--"; //in case value is blank
					if ($cell_data != ""){
						$selected_dropdown_text = q1("SELECT $category_field_name FROM $category_table_name WHERE $category_table_pk = \"" . $cell_value . "\"");
						//echo "field: $field - $selected_dropdown_text <br />\n";
						$cell_data = $selected_dropdown_text;
					}
				}

				$exportTableData[] = $this->escapeCSVValue($cell_data);
			}
			$data .= join(',',$exportTableData) . "\n";
		}

		// clean up
		unset($exportTableHeadings);
		unset($exportTableData);

		return $headers.$data;
	}

    //a file must have been "sent"/posted for this to work
    function uploadFile($row_id, $file_field, $upload_folder, $allowedExts = ""){
        global $report_msg, $error_msg;

        @$fileName  = $_FILES[$file_field]['name'];
        @$tmpName   = $_FILES[$file_field]['tmp_name'];
        @$fileSize  = $_FILES[$file_field]['size'];
        @$fileType  = $_FILES[$file_field]['type'];

        if (is_array($allowedExts)){
	        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION)); //gets file ext (lowercase)
	        if ( !in_array($fileExt, $allowedExts)){
	        	$error_msg[] = "Upload failed. Selected file was extention <b>.{$fileExt}</b> but this is not an permitted file extension.";
	        	return false;
	        }
	    }

        $new_filename = make_filename_safe($fileName);
        if ($this->filename_append_field != ""){
            if ($_REQUEST[$this->filename_append_field] != ''){
                $new_filename = $_REQUEST[$this->filename_append_field] . "_" . $new_filename;
            }
            else{
                if ($this->filename_append_field == $this->db_table_pk){
                    $new_filename = $row_id . "_" . $new_filename;
                }
                else{
                    @$db_value_to_append = q1("SELECT $this->filename_append_field FROM $this->db_table WHERE $this->db_table_pk = $row_id");
                    if ($db_value_to_append != ""){
                        $new_filename = $db_value_to_append . "_" . $new_filename;
                    }
                }

            }
        }

        $destination = $upload_folder . $new_filename;

        $success = move_uploaded_file ($tmpName, $destination);

        if ($success){
            $update_success = qr("UPDATE $this->db_table SET $file_field = \"$new_filename\" WHERE $this->db_table_pk = $row_id");

            if ($this->onFileUploadExecuteCallBackFunction != ''){
                $file_info_array = array();
                $file_info_array[id]        = $row_id;
                $file_info_array[field]     = $file_field;
                $file_info_array[fileName]  = $new_filename;
                $file_info_array[fileSize]  = $fileSize;
                $file_info_array[fldType]   = $fldType;
                call_user_func($this->onFileUploadExecuteCallBackFunction, $file_info_array);
            }

            if ($update_success) return true;
        }
        else{
			$error_msg[] = "There was an error uploading your file. Check permissions of the destination directory (make sure is set to 777).";
		}

		return false;
    }

	function showTable(){

        global $error_msg;
        global $report_msg;
        global $warning_msg_displayed;
        global $num_ajaxCRUD_tables_instantiated;
        global $headerAdded;

        $num_ajaxCRUD_tables_instantiated++;


        /* Filter Table (if there are request parameters)
        */
		$count_filtered = 0;
		$action = "";
		if (isset($_REQUEST['action'])){
			$action = $_REQUEST['action'];
		}
        foreach ($this->fields as $field){
			//this if condition is so MULTIPLE ajaxCRUD tables can be used on the same page.
			if ($_REQUEST['table'] == $this->db_table){

				if (isset($_REQUEST[$field]) && $_REQUEST[$field] != '' && ($action != 'add' && $action != 'delete' && $action != 'update' && $action != 'upload' && $action != 'delete_file')){
					$filter_field = $field;
					$filter_value = $_REQUEST[$field];
					if ($this->exactSearchField[$filter_field]){
						//exact search (is set by
						$filter_where_clause = "WHERE $filter_field = \"$filter_value\"";
					}
					else{
						//approximate search (default)
						$filter_where_clause = "WHERE $filter_field LIKE \"%" . $filter_value . "%\"";
					}

					$this->addWhereClause($filter_where_clause);
					$this->filtered_table = true;
					$count_filtered++;
				}
			}
		}
        if ($count_filtered > 0){
            $this->filtered_table;
        }
        else{
            $this->filtered_table = false;
        }

        /* Sort Table
           Note: this cancels out default sorting set by addOrderBy()
        */
        if (isset($_REQUEST['table']) && isset($_REQUEST['sort_field'])){
			if ($this->db_table == $_REQUEST['table'] && $_REQUEST['sort_field'] != ''){
				$sort_field = $_REQUEST['sort_field'];
				$user_sort_order_direction = $_REQUEST['sort_direction'];

				if ($user_sort_order_direction == 'asc'){
					$this->sort_direction = "desc";
				}
				else{
					$this->sort_direction = "asc";
				}
				$sort_sql = " ORDER BY $sort_field $this->sort_direction";
				$this->addOrderBy($sort_sql);
				$this->sorted_table = true;
			}
		}

        //the HTML to display
        $top_html = "";     //top header stuff
        $table_html = "";   //for the html table itself
        $bottom_html = "";
        $add_html = "";     //for the add form

        $html = ""; //all combined

        if ( $num_ajaxCRUD_tables_instantiated == 1 && !$headerAdded){
            //pull in the  css and javascript files
            $this->insertHeader($this->ajax_file);
        }

        if ($this->doActionOnShowTable){
            if (isset($_REQUEST['action']) && $_REQUEST['action'] != ''){
                $this->doAction($_REQUEST['action']);
            }
        }

		$item = $this->item;

		//this array is used to populate the dropdown boxes set by defined relationships (to other tables)
		$dropdown_array = array();
		foreach ($this->category_table_array as $key => $category_table){
            $category_field_name = $this->category_field_array[$key];
            $category_table_pk   = $this->category_table_pk_array[$key];

            $order_by = '';
            if ($this->category_sort_field_array[$key] != ''){
                $order_by = " ORDER BY " . $this->category_sort_field_array[$key];
            }

            $whereclause  = '';
            if ($this->category_whereclause_array[$key] != ''){
                $whereclause = $this->category_whereclause_array[$key];
            }

            $dropdown_array[] = q("SELECT $category_table_pk, $category_field_name FROM $category_table $whereclause $order_by");
		}

		$top_html .= "<a name='ajaxCRUD" . $num_ajaxCRUD_tables_instantiated ."' id='ajaxCRUD" . $num_ajaxCRUD_tables_instantiated  ."'></a>\n";

        if (!isset($extra_query_params)) $extra_query_params = "";//this is used by certain applications which require extra query params to be passed (not typical)

        if (count($this->ajaxFilter_fields) > 0){
            $top_html .= "<form id=\"" . $this->db_table . "_filter_form\" class=\"ajaxCRUD\">\n";
            $top_html .= "<table cellspacing='5' align='center'><tr><thead>";

            foreach ($this->ajaxFilter_fields as $filter_field){
                $display_field = $filter_field;
                if ($this->displayAs_array[$filter_field] != ''){
                    $display_field = $this->displayAs_array[$filter_field];
                }

                //TODO: this var is used to see if there is a defined relationship with the field (I hate this approach and need to re-architect it!)
                $found_category_index = array_search($filter_field, $this->db_table_fk_array);

                $textbox_size = $this->ajaxFilterBoxSize[$filter_field];

                $filter_value = "";
                if (isset($_REQUEST[$filter_field]) && $_REQUEST[$filter_field] != ''){
                	//$filter_value = $_REQUEST[$filter_field];
                	$filter_value = utf8_encode($_REQUEST[$filter_field]);
                }

                $top_html .= "<th><b>$display_field</b>:";

				//check for valid values (set by defineAllowableValues)
				if (isset($this->allowed_values[$filter_field]) && is_array($this->allowed_values[$filter_field])){
					$top_html .= "<select name=\"$filter_field\" onChange=\"filterTable(this, '" . $this->db_table . "', '$filter_field', '$extra_query_params');\">";
					$top_html .= "<option value=\"\">==Select==</option>\n";
					foreach ($this->allowed_values[$filter_field] as $list){
						if (is_array($list)){
							$list_val = $list[0];
							$list_option = $list[1];
						}
						else{
							$list_val = $list;
							$list_option = $list;
						}
						$top_html .= "<option value=\"$list_val\">$list_option</option>\n";
					}
					$top_html .= "</select>\n";
				}
				//check for defined link to another db table (pk/fk relationship) (set by defineRelationship)
				else if (is_numeric($found_category_index)){
					$top_html .= "<select name=\"$filter_field\" onChange=\"filterTable(this, '" . $this->db_table . "', '$filter_field', '$extra_query_params');\">";
					$top_html .= "<option value=\"\">==Select==</option>\n";

					//this field is a reference to another table's primary key (eg it must be a foreign key)
					$category_field_name = $this->category_field_array[$found_category_index];
					$category_table_name = $this->category_table_array[$found_category_index];
					$category_table_pk 	 = $this->category_table_pk_array[$found_category_index];

					//this array is set above (used a few places in the class) - sorry, a bit of repeating code here :-(
					foreach ($dropdown_array[$found_category_index] as $dropdown){
						$dropdown_value = $dropdown[$this->category_table_pk_array[$found_category_index]];
						$dropdown_text = $dropdown[$this->category_field_array[$found_category_index]];
						$top_html .= "<option value=\"$dropdown_value\">$dropdown_text</option>\n";
					}

					$top_html .= "</select>\n";
				}
				//check for a checkbox for this field
				else if (isset($this->checkbox[$filter_field]) && is_array($this->checkbox[$filter_field])){
					$values = $this->checkbox[$filter_field];
					$value_on = $values[0];
					$value_off = $values[1];

					$checked = '';
					if (isset($field_value) && $field_value == $value_on) $checked = "checked";

					$top_html .= "<input type=\"checkbox\" name=\"$filter_field\" $checked value=\"$value_on\" onClick=\"filterTable(this, '" . $this->db_table . "', '$filter_field', '$extra_query_params');\">";
				}
				//a "regualar" textbox filter box
				else{
					$customClass = "";
					if (isset($this->display_field_with_class_style[$filter_field]) && $this->display_field_with_class_style[$filter_field] != '') {
						$customClass = $this->display_field_with_class_style[$filter_field];
					}

                	$top_html .= "<input type=\"text\" class=\"$customClass\" size=\"$textbox_size\" name=\"$filter_field\" value=\"$filter_value\" onKeyUp=\"filterTable(this, '" . $this->db_table . "', '$filter_field', '$extra_query_params');\">";
                }
                $top_html .= "&nbsp;&nbsp;</th>";
            }
            $top_html .= "</tr></thead></table>\n";
            $top_html .= "</form>\n";
        }


		#############################################
		#
		# Begin code for displaying database elements
		#
		#############################################

		$select_fields = implode(",", $this->fields);

        $sql = "SELECT * FROM " . $this->db_table . $this->sql_where_clause . $this->sql_order_by;//added name for table (t) in case where clauses want to use it (7.2.2)

        if ($this->showPaging){
            $pageid = "";
            if (isset($_REQUEST['pid'])){
            	$pageid        = $_REQUEST['pid'];//Get the pid value
            }
            if(intval($pageid) == 0) $pageid  = 1;
            $Paging        = new paging();
            $Paging->tableName = $this->db_table;

            $total_records = $Paging->myRecordCount($sql);//count records
            $totalpage     = $Paging->processPaging($this->limit,$pageid);
            $rows          = $Paging->startPaging($sql);//get records in the databse
            $links         = $Paging->pageLinks(basename($_SERVER['PHP_SELF']));//1234 links
            unset($Paging);
        }
        else{
            $rows = q($sql . $this->sql_limit);
        }
        //echo $sql;

		//$row_count = count($rows); //count should NOT consider paging
		$row_count = $this->getNumRows();

        $this->row_count = $row_count;
        $_SESSION['row_count'] = $row_count; //DEPRECATED
        $_SESSION[$this->db_table . '_row_count'] = $row_count;

        if ($row_count == 0){
            $report_msg[] = $this->emptyTableMessage;
        }

        #this is an optional function which will allow you to display errors or report messages as desired. comment it out if desired
        //only show the message box if it hasn't been displayed already
        if ($warning_msg_displayed == 0 || $warning_msg_displayed == ''){
            echo_msg_box();
        }

        $top_html .= "<div id='$this->db_table'>\n";

        if ($row_count > 0){

            /*
            commenting out the 'edit item' text at the top; feel free to add back in if you want
            $edit_word = "Edit";
            if ($row_count == 0) $edit_word = "No";
            $top_html .= "<h3>Edit " . $this->item_plural . "</h3>\n";
            */

            //for vertical display, have a little spacing in there
            if ($this->orientation == 'vertical' && $this->cellspacing == ""){
            	$this->cellspacing = 2;
            }

            $table_html .= "<table align='center' class='ajaxCRUD' name='table_" . $this->db_table . "' id='table_" . $this->db_table . "' cellspacing='" . $this->cellspacing . "' border=" . $this->border . ">\n";

			//only show the header (field names) at top for horizontal display (default)
			if ($this->orientation != 'vertical'){

				if ($this->includeTableHeaders){
					$table_html .= "<thead><tr>\n";
					//for an (optional) checkbox
					if ($this->showCheckbox){
						$table_html .= "<th>&nbsp;</th>";
					}

					foreach ($this->display_fields as $field){
						$field_name = $field;
						if ($this->displayAs_array[$field] != ''){
							$field = $this->displayAs_array[$field];
						}

						if (!$this->fieldInArray($field_name, $this->unsortable_fields)){
							$fieldHeaderHTML = "<a href='javascript:;' onClick=\"changeSort('$this->db_table', '$field_name', '$this->sort_direction');\" >" . $field . "</a>";
						}
						else{
							$fieldHeaderHTML = $field;
						}

						if (array_key_exists($field_name, $this->checkboxall)) {
							$table_html .= "<th><input type=\"checkbox\" name=\"$field_name" . "_checkboxall\" value=\"checkAll\" onClick=\"
								if (this.checked) {
									setAllCheckboxes('$field_name" . "_fieldckbox',false);
								} else {
									setAllCheckboxes('$field_name" . "_fieldckbox',true);
								}
								\">";

							if ($this->checkboxall[$field_name] == true) {
								$table_html .= $fieldHeaderHTML;
							}
							$table_html .= "</th>";
						}
						else {
							$table_html .= "<th>$fieldHeaderHTML</th>";
						}
					}

					if ($this->delete || (count($this->row_button)) > 0){
						$table_html .= "<th>" . $this->actionText . "</th>\n";
					}

					$table_html .= "</tr></thead>\n";
				}
			}

            $count = 0;
            $class = "odd";

            $attach_params = "";

			$valign = "middle";

            foreach ($rows as $row){
                $id = $row[$this->db_table_pk];

				if ($this->orientation == 'vertical'){
					$class = "vertical" . " $class";
					$valign = "middle";
				}

                $table_html .= "<tr class='$class' id=\"" . $this->db_table . "_row_$id\" valign='{$valign}'>\n";


                if ($this->showCheckbox && $this->orientation != 'vertical'){
                    $checkbox_selected = "";
                    if ($id == $_REQUEST[$this->db_table_pk]) $checkbox_selected = " checked";
                    $table_html .= "<td><input type='checkbox' $checkbox_selected onClick=\"window.location ='" . $_SERVER['PHP_SELF'] . "?$this->db_table_pk=$id'\" /></td>";
                }

				$canRowBeUpdated = true;
				if (isset($this->validate_update_with_function) && $this->validate_update_with_function != ''){
					$canRowBeUpdated = call_user_func($this->validate_update_with_function, $id);
				}

                foreach($this->display_fields as $field){
                    $cell_data = $row[$field];

                    //for adding a button via addButtonToRow
                    if (count($this->row_button) > 0){
                        $attach_params .= "&" . $field . "=" . urlencode($cell_data);
                    }

                    $cell_value = $cell_data; //retain original value in new variable (before executing callback method)

                    if (isset($this->format_field_with_function[$field]) && $this->format_field_with_function[$field] != ''){
                        $cell_data = call_user_func($this->format_field_with_function[$field], $cell_data);
                    }

                    if (isset($this->format_field_with_function_adv[$field]) && $this->format_field_with_function_adv[$field] != ''){
                        $cell_data = call_user_func($this->format_field_with_function_adv[$field], $cell_data, $id);
                    }

                    //try to find a reference to another table relationship
                    $found_category_index = array_search($field, $this->db_table_fk_array);

					//if orientation is vertical show the field name next to the field
					if ($this->orientation == 'vertical'){
						if ($this->displayAs_array[$field] != ''){
							$fieldName = $this->displayAs_array[$field];
						}
						else{
							$fieldName = $field;
						}
						$table_html .= "<th class='vertical'>$fieldName</th>";
					}

                    //don't allow uneditable fields (which usually includes the primary key) to be editable
                    if ( !$canRowBeUpdated || $this->fieldInArray($field, $this->file_uploads) || ( ($this->fieldInArray($field, $this->uneditable_fields) && (!is_numeric($found_category_index))) ) ){

                        $table_html .= "<td>";

                        $key = array_search($field, $this->display_fields);

                        if ($this->fieldInArray($field, $this->file_uploads) && !$this->fieldInArray($field, $this->uneditable_fields)){

                            //a file exists for this field
                            $file_dest = "";
                            if ($cell_data != ''){
                                $file_link = $this->file_upload_info[$field]['relative_folder'] . $row[$field];
                                $file_dest = $this->file_upload_info[$field]['destination_folder'];

                                $table_html .= "<span style=\"font-size: 9px;\" id='text_" . $field . $id . "'><a target=\"_new\" href=\"$file_link\">" . $cell_data . "</a> <a style=\"font-size: 9px;\" href=\"javascript:\" onClick=\"document.getElementById('file_$field$id').style.display = ''; document.getElementById('text_$field$id').style.display = 'none'; \">" . $this->fileEditText . "</a> | <a style=\"font-size: 9px;\" href=\"javascript:\" onClick=\"deleteFile('$field', '$id')\">" . $this->fileDeleteText . "</a></span>\n";

                                $table_html .= "<div id='file_" . $field . $id . "' style='display:none;'>\n";
                                $table_html .= $this->showUploadForm($field, $file_dest, $id);
                                $table_html .= "</div>\n";
                            }
                            else{
                                $table_html .= "<span id='text_" . $field . $id . "'><a style=\"font-size: 9px;\" href=\"javascript:\" onClick=\"document.getElementById('file_$field$id').style.display = ''; document.getElementById('text_$field$id').style.display = 'none'; \">Add File</a></span> \n";

                                $table_html .= "<div id='file_" . $field. $id . "' style='display:none;'>\n";
                                $table_html .= $this->showUploadForm($field, $file_dest, $id);
                                $table_html .= "</div>\n";
                            }
                        }
                        else{
                            //added in 6.5. allows defineAllowableValues to work even when in readonly mode
                            if (isset($this->allowed_values[$field]) && is_array($this->allowed_values[$field])){
								foreach ($this->allowed_values[$field] as $list){
									if (is_array($list)){
										$list_val = $list[0];
										$list_option = $list[1];
									}
									else{
										$list_val = $list;
										$list_option = $list;
									}

									//fixed bug in 8.76. cell_value ensures we're looking at original value vs value set by user-defined function
									if ($list_val == $cell_value) $table_html .= $list_option;
								}
                            }
                            else{
                            	$table_html .= stripslashes($cell_data);
                            }
                        }
                    }//if field is not editable
                    else{
                        $table_html .= "<td>";

                        if (!is_numeric($found_category_index)){

                            //was allowable values for this field defined?
                            if ( (isset($this->allowed_values[$field]) && is_array($this->allowed_values[$field])) && !isset($this->field_no_dropdown[$field]) ){
                                $table_html .= $this->makeAjaxDropdown($id, $field, $cell_value, $this->db_table, $this->db_table_pk, $this->allowed_values[$field]);
                            }
                            else{

                                //if a checkbox
                                if (isset($this->checkbox[$field]) && is_array($this->checkbox[$field])){
                                    $table_html .= $this->makeAjaxCheckbox($id, $field, $cell_value);
                                }
                                else{
                                    //is an editable field
                                    //if ($cell_data == '') $cell_data = "&nbsp;&nbsp;";

                                    $field_onKeyPress = "";
                                    if ($this->fieldIsInt($this->getFieldDataType($field)) || $this->fieldIsDecimal($this->getFieldDataType($field))){
                                        $field_onKeyPress = "return fn_validateNumeric(event, this, 'n');";
                                        if ($this->fieldIsDecimal($this->getFieldDataType($field))){
                                            $field_onKeyPress = "return fn_validateNumeric(event, this, 'y');";
                                        }
                                    }

                                    if ($this->fieldIsEnum($this->getFieldDataType($field))){
                                        $allowed_enum_values_array = $this->getEnumArray($this->getFieldDataType($field));
                                        $table_html .= $this->makeAjaxDropdown($id, $field, $cell_value, $this->db_table, $this->db_table_pk, $allowed_enum_values_array);
                                    }
                                    else{
										//updated logic in 7.1 to enable a textarea to be 'forced' if desired [thanks to dpruitt for code revision]
										$field_length = strlen($row[$field]);
										if(isset($this->textarea_height[$field]) && $this->textarea_height[$field] != '' || $field_length > 51){
											$textarea_height = '';
											if ($this->textarea_height[$field] != '') $textarea_height = $this->textarea_height[$field];
											$table_html .= $this->makeAjaxEditor($id, $field, $cell_value, 'textarea', $textarea_height, $cell_data, $field_onKeyPress);
										}
										else{
                                            //if the textbox width was set manually with function setTextboxWidth
                                            if (isset($this->textboxWidth[$field]) && $this->textboxWidth[$field] != ''){
                                            	$field_length = $this->textboxWidth[$field];
                                            }

											$table_html .= $this->makeAjaxEditor($id, $field, $cell_value, 'text', $field_length, $cell_data, $field_onKeyPress);
										}
                                    }
                                }
                            }
                        }
                        else{
                            //this field is a reference to another table's primary key (eg it must be a foreign key)
                            $category_field_name = $this->category_field_array[$found_category_index];
                            $category_table_name = $this->category_table_array[$found_category_index];
                            $category_table_pk 	 = $this->category_table_pk_array[$found_category_index];

                            $selected_dropdown_text = "--"; //in case value is blank
                            if ($cell_data != ""){
                                $selected_dropdown_text = q1("SELECT $category_field_name FROM $category_table_name WHERE $category_table_pk = \"" . $cell_value . "\"");
                                //echo "field: $field - $selected_dropdown_text <br />\n";
                            }
                            if (!$this->fieldInArray($field, $this->uneditable_fields)){
                                $table_html .= $this->makeAjaxDropdown($id, $field, $cell_value, $category_table_name, $category_table_pk, $dropdown_array[$found_category_index], $selected_dropdown_text);
                            }
                            else{
                                $table_html .= $selected_dropdown_text;
                            }
                        }

                    }

                    $table_html .= "</td>";
                    if ($this->orientation == 'vertical'){
                    	$table_html .= "</tr><tr class='$class' id=\"" . $this->db_table . "_row_$id\" valign='middle'>\n";
                    }

                }//foreach displayFields

                if ($this->delete || (count($this->row_button)) > 0){

					if ($this->orientation == 'vertical'){
						$table_html .= "<th class='vertical'>" . $this->actionText . "</th>";
					}

                    $table_html .= "<td>\n";

                    if ($this->delete){

						$canRowBeDeleted = true;
						if (isset($this->validate_delete_with_function) && $this->validate_delete_with_function != ''){
							$canRowBeDeleted = call_user_func($this->validate_delete_with_function, $id);
						}

                        if ($canRowBeDeleted){
                        	$table_html .= "<input type=\"button\" class=\"btn editingSize\" onClick=\"confirmDelete('$id', '" . $this->db_table . "', '" . $this->db_table_pk ."');\" value=\"" . $this->deleteText . "\" />\n";
                        }
                    }

                    if (count($this->row_button) > 0){
                        foreach ($this->row_button as $button_id => $the_row_button){
                            $value = $the_row_button[0];
                            $url = $the_row_button[1];
                            $attach_param = $the_row_button[2]; //optional param
                            $javascript_onclick_function = $the_row_button[3]; //optional param
                            $window_to_open = $the_row_button[4]; //optional param

                            if ($attach_param == "all"){
                                $attach = "?attachments" . $attach_params;
                            }
                            else{
                                $char = "?";
                                if (stristr($url, "?") !== FALSE){
                                	$char = "&"; //the url already has get parameters; attach the id with it
                                }

                                $getParam = $this->db_table_pk;
								$valueToPass = $id;
                                if ($attach_param != "all" && $attach_param != ""){
                                	$getParam = $attach_param;
									//check to see if the field being passed is a db column
									if ($this->fieldInArray($attach_param, $this->fields)){
										$valueToPass = $row[$attach_param];
									}
                                }
                                $valueToPass = urlencode($valueToPass);
                                $attach = $char . $getParam . "=$valueToPass";
                            }

                            //its most likely a user-defined ajax function
                            if ($javascript_onclick_function != ""){
                                $javascript_for_button = "onClick=\"" . $javascript_onclick_function . "($id);\"";
                            }
                            else{
                                //either button-specific window is 'same' or global (all buttons' window is 'same'
                                if ($window_to_open == "same" && $this->addButtonToRowWindowOpen == "same"){
                                	$javascript_for_button = "onClick=\"location.href='" . $url . $attach . "'\"";
                                }
                                else{
                                	$javascript_for_button = "onClick=\"window.open('" . $url . $attach . "')\"";
                                }
                            }


                            $table_html .= "<input type=\"button\" $javascript_for_button class=\"btn editingSize\" value=\"$value\" />\n";
                        }
                    }

                    $table_html .= "</td>\n";
                }

                $table_html .= "</tr>";

				if ($this->orientation == 'vertical'){
					$table_html .= "<tr><td colspan='2' style='border-top: 4px silver solid;' ></td></tr>\n";
				}


                if($count%2==0){
                    $class="cell_row";
                }
                else{
                    $class="odd";
                }

                $count++;


            }//foreach row

            $table_html .= "</table>\n";

            //paging links
            if ($totalpage > 1){
                $table_html .= "<br /><div style='width: 800px; position: relative; left: 50%; margin-left: -400px; text-align: center;'><center> $links </center></div><br /><br />";
            }

        }//if rows > 0

        //closing div for paging links (if applicable)
        $bottom_html = "</div><br />\n";

		// displaying the export to csv button
		if ($this->showCSVExport) {
			$add_html .= "<center>\n";
			$add_html .= "<form action=\"" . $_SERVER["SCRIPT_NAME"] . "\" name=\"CSVExport\" method=\"POST\" >\n";
			$add_html .= "  <input type=\"hidden\" name=\"fileName\" value=\"tableoutput.csv\" />\n";
			$add_html .= "  <input type=\"hidden\" name=\"customAction\" value=\"exportToCSV\" />\n";
			$add_html .= "	<input type=\"hidden\" name=\"tableData\" value=\"" . $this->createCSVOutput() . "\" />\n";
			$add_html .= "  <input type=\"submit\" name=\"submit\" value=\"Export Table To CSV\" class=\"btn editingSize\"/>\n";
			$add_html .= "</form>\n";
			$add_html .= "</center>\n";
		}

        $add_html .= "<center>\n";
        //now we come to the "add" fields
        if ($this->add){
            $addButtonVal = $this->addText . " " . $item;
            if ($this->addButtonText != ""){
            	$addButtonVal = $this->addButtonText;
            }

            $add_html .= "   <input type=\"button\" value=\"$addButtonVal\" class=\"btn editingSize\" onClick=\"$('#add_form_$this->db_table').slideDown('fast'); x = document.getElementById('add_form_$this->db_table'); t = setTimeout('x.scrollIntoView(false)', 200); \">\n";
        }

		if (count($this->bottom_button) > 0){
			foreach($this->bottom_button as $button){
				$button_value = $button[0];
				$button_url = $button[1];
				$button_tags = $button[2];

				if ($button_tags == ''){
					$tag_stuff = "onClick=\"location.href = '$button_url';\"";
				}
				else{
					$tag_stuff = $button_tags;
				}
				$add_html .= "  <input type=\"button\" value=\"$button_value\" href=\"$button_url\" class=\"btn\" $tag_stuff>\n";
			}
		}

        if ($this->add){
            //$add_html .= "  <input type=\"button\" value=\"Go Back\" class=\"btn\" onClick=\"history.back();\">\n";
            $add_html .= "</center>\n";

            $formActionURL = $_SERVER['PHP_SELF'];
            if (isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] != ""){
            	//some web applications require posting to the same exact page (with parameters included); this is useful if/when onAddExecuteCallbackFunction is used
            	$formActionURL = $_SERVER['PHP_SELF'] . '?' . $_SERVER['QUERY_STRING'];
            }

            $add_html .= "<form action=\"" . $formActionURL . "#ajaxCRUD\" id=\"add_form_$this->db_table\" method=\"POST\" ENCTYPE=\"multipart/form-data\" style=\"display:none;\">\n";
            //$add_html .= "<br /><h3 align='center'>New <b>$item</b></h3>\n";
            $add_html .= "<br />\n";
            $add_html .= "<table align='center' name='form'>\n";

            //for here display ALL 'addable' fields
            foreach($this->add_fields as $field){
				$add_html .= "<tr>\n";
                if ($field != $this->db_table_pk || $this->on_add_specify_primary_key){
                    $field_value = "";

					$hideOnClick = "";
					$placeholder = "";
					//if a date field, show helping text
					if ($this->fieldIsDate($this->getFieldDataType($field))){
						//$placeholder = "YYYY-mm-dd";
						$placeholder = date("Y-m-d");
						//$hideOnClick = TRUE;
					}

                    //if initial field value for field is set
                    if (isset($this->initialFieldValue[$field]) && $this->initialFieldValue[$field] != ""){
                    	$field_value = $this->initialFieldValue[$field];
                    	//$hideOnClick = TRUE;
                    }

                    //the request (post/get) will overwrite any initial values though
                    if (isset($_REQUEST[$field]) && $_REQUEST[$field] != '') {
                    	//$field_value = $_REQUEST[$field];  //note: disable because caused problems
                    	//$hideOnClick = FALSE;
                    }

                    if ($hideOnClick){
                    	//$hideOnClick = "onClick = \"this.value = ''\"";
                    }

                    if ($this->displayAs_array[$field] != ''){
                        $display_field = $this->displayAs_array[$field];
                    }
                    else{
                        $display_field = $field;
                    }

                    $note = "";
                    if (isset($this->fieldNote[$field]) && $this->fieldNote[$field] != ""){
                    	$note = "&nbsp;&nbsp;<i>" . $this->fieldNote[$field] . "</i>";
                    }

                    if (isset($this->placeholderText[$field]) && $this->placeholderText[$field] != ""){
                    	$placeholder = $this->placeholderText[$field];
                    }

                    //if a checkbox
                    if (isset($this->checkbox[$field]) && is_array($this->checkbox[$field])){
                        $values = $this->checkbox[$field];
                        $value_on = $values[0];
                        $value_off = $values[1];
                        $add_html .= "<th>$display_field</th><td>\n";
                        $add_html .= "<input type='checkbox' name=\"$field\" value=\"$value_on\">\n";
                        $add_html .= "$note</td>\n";
                    }
                    else{
                        $found_category_index = array_search($field, $this->db_table_fk_array);
                        if (!is_numeric($found_category_index) && $found_category_index == ''){

                            //it's from a set of predefined allowed values for this field
                            if (isset($this->allowed_values[$field]) && is_array($this->allowed_values[$field])){
                                $add_html .= "<th>$display_field</th><td>\n";
                                $add_html .= "<select name=\"$field\" class='editingSize'>\n";
                                foreach ($this->allowed_values[$field] as $dropdown){
                                    $selected = "";
                                    $dropdown_value = $dropdown[0];
                                    $dropdown_text  = $dropdown[1];
                                    if ($field_value == $dropdown_value) $selected = " selected";
                                    $add_html .= "<option value=\"$dropdown_value\" $selected>$dropdown_text</option>\n";
                                }
                                $add_html .= "</select>$note</td>\n";
                            }
                            else{
                                if ($this->fieldInArray($field, $this->file_uploads)){
                                    //this field is an file upload
                                    $add_html .= "<th>$display_field</th><td><input class=\"editingSize\" type=\"file\" name=\"$field\" size=\"15\">$note</td></tr>\n";
                                    $file_uploads = true;
                                }
                                else{
                                    if ($this->fieldIsEnum($this->getFieldDataType($field))){
                                        $allowed_enum_values_array = $this->getEnumArray($this->getFieldDataType($field));

                                        $add_html .= "<th>$display_field</th><td>\n";
                                        $add_html .= "<select name=\"$field\" class='editingSize'>\n";
                                        foreach ($allowed_enum_values_array as $dropdown){
                                            $dropdown_value = $dropdown;
                                            $dropdown_text  = $dropdown;
                                            if ($field_value == $dropdown_value) $selected = " selected";
                                            $add_html .= "<option value=\"$dropdown_value\" $selected>$dropdown_text</option>\n";
                                        }
                                        $add_html .= "</select>$note</td></tr>\n";
                                    }//if enum field
                                    else{
                                        $field_onKeyPress = "";
                                        if ($this->fieldIsInt($this->getFieldDataType($field)) || $this->fieldIsDecimal($this->getFieldDataType($field))){
                                            $field_onKeyPress = "return fn_validateNumeric(event, this, 'n');";
                                            if ($this->fieldIsDecimal($this->getFieldDataType($field))){
                                                $field_onKeyPress = "return fn_validateNumeric(event, this, 'y');";
                                            }
                                        }

                                        //textarea fields
                                        if (isset($this->textarea_height[$field]) && $this->textarea_height[$field] != ''){
                                            $add_html .= "<th>$display_field</th><td><textarea $hideOnClick onKeyPress=\"$field_onKeyPress\" class=\"editingSize\" name=\"$field\" style='width: 97%; height: " . $this->textarea_height[$field] . "px;'>$field_value</textarea>$note</td></tr>\n";
                                        }
                                        else{
                                            //any ol' text data (generic text box)
                                            $fieldType = "text";
                                            $fieldSize = "";

                                            //change the type of textbox field if a password (HTML 5 compatible)
                                            if (stristr($field, "password")){
                                            	$fieldType = "password";
                                            }

                                            //change the type of textbox field if a password (HTML 5 compatible)
                                            if (stristr($field, "email")){
                                            	$fieldType = "email";
                                            }


                                            if ($this->fieldIsInt($this->getFieldDataType($field)) || $this->fieldIsDecimal($this->getFieldDataType($field))){
                                                $fieldSize = 7;
                                                $fieldType = "number";
                                            }

                                            //if the textbox width was set manually with function setTextboxWidth
                                            if (isset($this->textboxWidth[$field]) && $this->textboxWidth[$field] != ''){
                                            	$fieldSize = $this->textboxWidth[$field];
                                            }

											$customClass = "";
											// Apply custom CSS class to field if applicable
											if (isset($this->display_field_with_class_style[$field]) && $this->display_field_with_class_style[$field] != '') {
												$customClass = $this->display_field_with_class_style[$field];
											}
											$add_html .= "<th>$display_field</th><td><input onKeyPress=\"$field_onKeyPress\" class=\"editingSize $customClass\" type=\"$fieldType\" id=\"$field\" name=\"$field\" size=\"$fieldSize\" maxlength=\"150\" value=\"$field_value\" placeholder=\"$placeholder\" >$note</td></tr>\n";
											$placeholder = "";
                                        }
                                    }//else not enum field
                                }//not an uploaded file
                            }//not a pre-defined value
                        }//not from a foreign/primary key relationship
                        else{
                            //field is from a defined relationship
                            $key = $found_category_index;
                            $add_html .= "<th>$display_field</th><td>\n";
                            $add_html .= "<select name=\"$field\" class='editingSize'>\n";

                            if ($this->category_required[$field] != TRUE){
                                if ($this->fieldIsInt($this->getFieldDataType($field)) || $this->fieldIsDecimal($this->getFieldDataType($field))){
                                    $add_html .= "<option value='0'>--Select--</option>\n";
                                }
                                else{
                                    $add_html .= "<option value=''>--Select--</option>\n";
                                }
                            }

                            foreach ($dropdown_array[$key] as $dropdown){
                                $selected = "";
                                $dropdown_value = $dropdown[$this->category_table_pk_array[$key]];
                                $dropdown_text  = $dropdown[$this->category_field_array[$key]];
                                if ($field_value == $dropdown_value) $selected = " selected";
                                $add_html .= "<option value=\"$dropdown_value\" $selected>$dropdown_text</option>\n";
                            }
                            $add_html .=  "</select>$note</td></tr>\n";
                        }
                    }//not a checkbox
                }//not the primary pk
            }//foreach

            $add_html .= "</tr><tr><td>\n";

			$postForm = "false";
			if (!$this->ajax_add){
				$postForm = "true";
			}
			$add_html .= "<input class=\"editingSize\" type=\"button\" onClick=\"validateAddForm('$this->db_table', $postForm);\" value=\"Save $item\">";


            $add_html .= "</td><td><input style='float: right;' class=\"btn editingSize\" type=\"button\" onClick=\"this.form.reset();$('#add_form_$this->db_table').slideUp('slow');\" value=\"" . $this->cancelText . "\"></td></tr>\n</table>\n";
            $add_html .= "<input type=\"hidden\" name=\"action\" value=\"add\">\n";
            $add_html .= "<input type=\"hidden\" name=\"table\" value=\"$this->db_table\">\n";

            if (isset($file_uploads) && $file_uploads){
                $add_html .= "<input type=\"hidden\" name=\"uploads_on\" value=\"true\">\n";
            }

            $add_html .= "</form>\n";

        }//if adding fields is "allowed"

        /*
        THIS IS IMPORTANT
        for ajax retrieval (see top of page)
        */
		$_SESSION[$this->db_table] = $table_html;

        $html = $top_html . $table_html . $bottom_html . $add_html;
        if ($this->add_form_top){
        	$html = $add_html . $top_html . $table_html . $bottom_html;
        }

        echo $html;

	}

	function getFields($table){
		$query = "SHOW COLUMNS FROM $table";
		$rs = q($query);

		//print_r($rs);
		$fields = array();
		foreach ($rs as $r){
			//r sub0 is the name of the field (hey ... it works)
			$fields[] = $r[0];
            $this->field_datatype[$r[0]] = $r[1];
		}

		if (count($fields) > 0){
			return $fields;
		}

		return false;
	}

    function getFieldDataType($field_name){
        return $this->field_datatype[$field_name];
    }

    function fieldIsInt($datatype){
        if (stristr($datatype, "int") !== FALSE){
            return true;
        }
        return  false;
    }

    function fieldIsDecimal($datatype){
        if (stristr($datatype, "decimal") !== FALSE || stristr($datatype, "double") !== FALSE){
            return true;
        }
        return  false;

    }

    function fieldIsEnum($datatype){
        if (stristr($datatype, "enum") !== FALSE){
            return true;
        }
        return  false;
    }

	function fieldIsDate($datatype){
		if (stristr($datatype, "date") !== FALSE){
			return true;
		}
		return  false;
	}

    function getEnumArray($datatype){
        $enum = substr($datatype, 5);
        $enum = substr($enum, 0, (strlen($enum) - 1));
        $enum = str_replace("'", "", $enum);
        $enum = str_replace('"', "", $enum);
        $enum_array = explode(",", $enum);

        return ($enum_array);
    }


    function fieldInArray($field, $the_array){

        //try to find index for arrays with array[key] = field_name
        $found_index = array_search($field, $the_array);
        if ($found_index !== FALSE){
            return true;
        }

        //for arrays with array[0] = field_name and array[1] = value
        foreach ($the_array as $the_array_values){
            $field_name = $the_array_values[0];
            if ($field_name == $field){
                return true;
            }
        }

        return false;
    }

	function makeAjaxEditor($unique_id, $field_name, $field_value, $type = 'textarea', $fieldSize = "", $field_text = "", $onKeyPress_function = ""){

        $prefield = trim($this->db_table . $field_name . $unique_id);

		$input_name = $type . "_" . $prefield;

        $return_html = "";

		if ($field_text == "") $field_text = $field_value;

		$cell_data = "";
		if (isset($this->format_field_with_function[$field_name]) && $this->format_field_with_function[$field_name] != ''){
			$cell_data = call_user_func($this->format_field_with_function[$field_name], $field_value);
		}
		if (isset($this->format_field_with_function_adv[$field_name]) && $this->format_field_with_function_adv[$field_name] != ''){
			$cell_data = call_user_func($this->format_field_with_function_adv[$field_name], $field_value, $unique_id);
		}

		if ( (strip_tags($cell_data) == "") && $field_value == "") $field_text = "--";

		//for getting rid of the html space, replace with actual no text
		if ($field_value == "&nbsp;&nbsp;") $field_value = "";

		$field_value = stripslashes(htmlspecialchars($field_value));

		$postEditForm = false; //default action is for form NOT to be submitted but processed through ajax
		if (isset($this->onUpdateExecuteCallBackFunction[$field_name]) && $this->onUpdateExecuteCallBackFunction[$field_name] != ''){
			$postEditForm = true; //a callback function is specified for this field; as such it cannot be edited with ajax but must be posted
		}

        $return_html .= "<span class=\"editable hand_cursor\" id=\"" . $prefield ."_show\" onClick=\"
			document.getElementById('" . $prefield . "_edit').style.display = '';
			document.getElementById('" . $prefield . "_show').style.display = 'none';
			document.getElementById('" . $input_name . "').focus();
            \">" . stripslashes($field_text) . "</span>
        <span id=\"" . $prefield ."_edit\" style=\"display: none;\">
            <form style=\"display: inline;\" name=\"form_" . $prefield . "\" id=\"form_" . $prefield . "\" method=\"POST\" onsubmit=\"";

			if ($postEditForm){
				$return_html .= "return true;\">";
			}
			else{
				$return_html .= "
					document.getElementById('" . $prefield . "_edit').style.display='none';
					document.getElementById('" . $prefield . "_save').style.display='';
					var sndValue = document.getElementById('" . $input_name . "').value;
					sndValue = cleanseStrForURIEncode(sndValue);
					var req = '" . $this->ajax_file . "?ajaxAction=update&id=" . $unique_id . "&field=" . $field_name . "&table=" . $this->db_table . "&pk=" . $this->db_table_pk . "&val=' + sndValue;
					sndUpdateReq(req);
					return false;
				\">";
			}

            if ($type == 'text'){
                if ($fieldSize == "") $fieldSize = 15;
				if (isset($this->display_field_with_class_style[$field_name]) && $this->display_field_with_class_style[$field_name] != '') {
					$customClass = $this->display_field_with_class_style[$field_name];
					$return_html .= "<input ONKEYPRESS=\"$onKeyPress_function\" id=\"$input_name\" name=\"$input_name\" type=\"text\" class=\"editingSize editMode $customClass\" size=\"$fieldSize\" value=\"$field_value\"/>\n";
				}
				else {
					$return_html .= "<input ONKEYPRESS=\"$onKeyPress_function\" id=\"$input_name\" name=\"$input_name\" type=\"text\" class=\"editingSize editMode\" size=\"$fieldSize\" value=\"$field_value\"/>\n";
				}
			}
			else{
                if ($fieldSize == "") $fieldSize = 80;
                $return_html .= "<textarea ONKEYPRESS=\"$onKeyPress_function\" id=\"$input_name\" name=\"textarea_$prefield\" class=\"editingSize editMode\" style=\"width: 100%; height: " . $fieldSize . "px;\">$field_value</textarea>\n";
                $return_html .= "<br /><input type=\"submit\" class=\"editingSize\" value=\"Ok\">\n";
			}

        $return_html .= "
			<input type=\"hidden\" name=\"id\" value=\"$unique_id\">
			<input type=\"hidden\" name=\"field_name\" value=\"$field_name\">
			<input type=\"hidden\" name=\"table\" value=\"$this->db_table\">
			<input type=\"hidden\" name=\"paramName\" value=\"$input_name\">
			<input type=\"hidden\" name=\"action\" value=\"update\">
			<input type=\"button\" class=\"editingSize\" value=\"Cancel\" onClick=\"
				document.getElementById('" . $prefield . "_show').style.display = '';
				document.getElementById('" . $prefield . "_edit').style.display = 'none';
			\"/>
			</form>
		</span>
        <span style=\"display: none;\" id=\"" . $prefield . "_save\" class=\"savingAjaxWithBackground\">Saving...</span>";

        return $return_html;

	}//makeAjaxEditor

    function makeAjaxDropdown($unique_id, $field_name, $field_value, $dropdown_table, $dropdown_table_pk, $array_list, $selected_dropdown_text = "NOTHING_ENTERED"){
        $return_html = "";

        $prefield = trim($this->db_table . $field_name . $unique_id);
        $input_name = "dropdown" . "_" . $prefield;

		$cell_data = $field_value;
		if (isset($this->format_field_with_function[$field_name]) && $this->format_field_with_function[$field_name] != ''){
			$cell_data = call_user_func($this->format_field_with_function[$field_name], $field_value);
		}
		if (isset($this->format_field_with_function_adv[$field_name]) && $this->format_field_with_function_adv[$field_name] != ''){
			$cell_data = call_user_func($this->format_field_with_function_adv[$field_name], $field_value, $unique_id);
		}

        if ($selected_dropdown_text == "NOTHING_ENTERED"){

            $selected_dropdown_text = $cell_data;

            foreach ($array_list as $list){
                if (is_array($list)){
                    $list_val = $list[0];
                    $list_option = $list[1];
                }
                else{
                    $list_val = $list;
                    $list_option = $list;
                }

                //if ($list_val == $field_value) $selected_dropdown_text = $list_option;
            }
        }

        $no_text = false;
        if ($selected_dropdown_text == '' || $selected_dropdown_text == '&nbsp;&nbsp;'){
            $no_text = true;
            $selected_dropdown_text = "&nbsp;--&nbsp;";
        }

		$postEditForm = false; //default action is for form NOT to be submitted but processed through ajax
		if (isset($this->onUpdateExecuteCallBackFunction[$field_name]) && $this->onUpdateExecuteCallBackFunction[$field_name] != ''){
			$postEditForm = true; //a callback function is specified for this field; as such it cannot be edited with ajax but must be posted
		}

        $return_html = "<span class=\"editable hand_cursor\" id=\"" . $prefield . "_show\" onClick=\"
			document.getElementById('" . $prefield . "_edit').style.display = '';
			document.getElementById('" . $prefield . "_show').style.display = 'none';
			\">" . $selected_dropdown_text . "</span>

            <span style=\"display: none;\" id=\"" . $prefield . "_edit\">
                <form style=\"display: inline;\" method=\"POST\" name=\"form_" . $prefield . "\" id=\"form_" . $prefield . "\">
                <select class=\"editingSize editMode\" name=\"$input_name\" id=\"$input_name\" onChange=\"";

			if ($postEditForm){
				$return_html .= "this.form.submit();\">";
			}
			else{
				$return_html .= "
					var selected_index_value = document.getElementById('" . $input_name . "').value;
					document.getElementById('" . $prefield . "_edit').style.display='none';
					document.getElementById('" . $prefield . "_save').style.display='';
					var req = '" . $this->ajax_file . "?ajaxAction=update&id=" . $unique_id . "&field=" . $field_name . "&table=" . $this->db_table . "&pk=" . $this->db_table_pk . "&dropdown_tbl=" . $dropdown_table . "&val=' + selected_index_value;
					sndUpdateReq(req);
					return false;
				\">";
			}

		if ($no_text || (isset($this->category_required[$field_name]) && $this->category_required[$field_name] != TRUE)){
			if ($this->fieldIsInt($this->getFieldDataType($field_name)) || $this->fieldIsDecimal($this->getFieldDataType($field_name))){
				$return_html .= "<option value='0'>--Select--</option>\n";
			}
			else{
				$return_html .= "<option value=''>--Select--</option>\n";
			}
		}

		foreach($array_list as $list){
			$selected = '';
			if (is_array($list)){
				$list_val = $list[0];
				$list_option = $list[1];
			}
			else{
				$list_val = $list;
				$list_option = $list;
			}

			if ($list_val == $field_value) $selected = " selected";
			$return_html .= "<option value=\"$list_val\" $selected >$list_option</option>";
		}
		$return_html .= "</select>";

		$return_html .= "<input type=\"hidden\" name=\"id\" value=\"$unique_id\">
			<input type=\"hidden\" name=\"field_name\" value=\"$field_name\">
			<input type=\"hidden\" name=\"table\" value=\"$this->db_table\">
			<input type=\"hidden\" name=\"paramName\" value=\"$input_name\">
			<input type=\"hidden\" name=\"action\" value=\"update\">
			<input type=\"button\" class=\"editingSize\" value=\"Cancel\" onClick=\"
				document.getElementById('" . $prefield . "_show').style.display = '';
				document.getElementById('" . $prefield . "_edit').style.display = 'none';
			\"/>

			</form>
			</span>
	        <span style=\"display: none;\" id=\"" . $prefield . "_save\" class=\"savingAjaxWithBackground\">Saving...</span>\n";

        return $return_html;

	}//makeAjaxDropdown


	function makeAjaxCheckbox($unique_id, $field_name, $field_value){
		$prefield = trim($this->db_table) . trim($field_name) . trim($unique_id);
		$input_name = "checkbox" . "_" . $prefield;

        $return_html = "";

		$values = $this->checkbox[$field_name];
		$value_on = $values[0];
		$value_off = $values[1];

		$checked = '';
		if ($field_value == $value_on) $checked = "checked";

		$show_value = '';
		if ($checked == '') {
			$show_value = $value_off;
		} else {
			$show_value = $value_on;
		}

		//strip quotes
		$value_on = str_replace('"', "'", $value_on);
		$value_off = str_replace('"', "'", $value_off);

		$checkboxValue = 0;
		if (isset($this->checkboxall[$field_name])){
			$checkboxValue = (int)$this->checkboxall[$field_name];
		}

		//added in R9.0
		$postEditForm = false; //default action is for form NOT to be submitted but processed through ajax
		if (isset($this->onUpdateExecuteCallBackFunction[$field_name]) && $this->onUpdateExecuteCallBackFunction[$field_name] != ''){
			$postEditForm = true; //a callback function is specified for this field; as such it cannot be edited with ajax but must be posted
		}

        $return_html .= "<form style=\"display: inline;\" method=\"POST\" name=\"form_" . $prefield . "\" id=\"form_" . $prefield . "\">";
        $return_html .= "<input type=\"checkbox\" $checked name=\"$input_name\" id=\"$input_name\" onClick=\"";

		if ($postEditForm){
			$return_html .= "if (this.checked){this.value = '" . $value_on . "';} else{this.checked = true; this.value = '" . $value_off . "';}";
			$return_html .= "this.form.submit();\">";
		}
		else{
			$return_html .= "
				var " . $prefield . "_value = '';

				if (this.checked){
					" . $prefield . "_value = '$value_on';
					if (" . $checkboxValue . ") {
						document.getElementById('$field_name$unique_id" . "_label').innerHTML = '$value_on';
					}
				}
				else{
					". $prefield . "_value = '$value_off';
					if (" . $checkboxValue . ") {
						document.getElementById('$field_name$unique_id" . "_label').innerHTML = '$value_off';
					}
				}
				var req = '" . $this->ajax_file . "?ajaxAction=update&id=$unique_id&field=$field_name&table=$this->db_table&pk=$this->db_table_pk&val=' + " . $prefield . "_value;

				sndReqNoResponseChk(req);
			\">";
		}

		$return_html .= "<input type=\"hidden\" name=\"id\" value=\"$unique_id\">
			<input type=\"hidden\" name=\"field_name\" value=\"$field_name\">
			<input type=\"hidden\" name=\"table\" value=\"$this->db_table\">
			<input type=\"hidden\" name=\"paramName\" value=\"$input_name\">
			<input type=\"hidden\" name=\"action\" value=\"update\"></form>";


		if (isset($this->checkboxall[$field_name]) && $this->checkboxall[$field_name] == true) {
			$return_html .= "<label for=\"$field_name$unique_id\" id=\"" . $field_name . $unique_id . "_label\">$show_value</label>";
		}

        return $return_html;

	}//makeAjaxCheckbox

    function showUploadForm($field_name, $upload_folder, $row_id){
        $return_html = "";

        $return_html .= "<form action=\"" . $_SERVER['PHP_SELF'] . "?" . $_SERVER['QUERY_STRING'] . "#ajaxCRUD\" name=\"Uploader\" method=\"POST\" ENCTYPE=\"multipart/form-data\">\n";
        $return_html .=  "  <input type=\"file\" size=\"10\" name=\"$field_name\" />\n";
        $return_html .= "  <input type=\"hidden\" name=\"upload_folder\" value=\"$upload_folder\" />\n";
        $return_html .= "  <input type=\"hidden\" name=\"field_name\" value=\"$field_name\" />\n";
        $return_html .= "  <input type=\"hidden\" name=\"id\" value=\"$row_id\" />\n";
        $return_html .= "  <input type=\"hidden\" name=\"action\" value=\"upload\" />\n";
        $return_html .= "  <input type=\"submit\" name=\"submit\" value=\"Upload\" />\n";
        $return_html .= "</form>\n";

        return $return_html;
    }

	function getThisPage(){
		if (stristr($_SERVER['REQUEST_URI'], "?")){
			return $_SERVER['REQUEST_URI'] . "&";
		}
		return $_SERVER['REQUEST_URI'] . "?";
	}

}//class


# In an effect to make ajaxCRUD thin we are attaching this (paging) class and a few functions all together

class paging{

	var $pRecordCount;
	var $pStartFile;
	var $pRowsPerPage;
	var $pRecord;
	var $pCounter;
	var $pPageID;
	var $pShowLinkNotice;
	var $tableName;

	function processPaging($rowsPerPage,$pageID){
       $record = $this->pRecordCount;
       if($record >=$rowsPerPage)
            $record=ceil($record/$rowsPerPage);
       else
            $record=1;
        if(empty($pageID) or $pageID==1){
            $pageID=1;
            $startFile=0;
        }
        if($pageID>1)
            $startFile=($pageID-1)*$rowsPerPage;

        $this->pStartFile   = $startFile;
        $this->pRowsPerPage = $rowsPerPage;
        $this->pRecord      = $record;
        $this->pPageID      = $pageID;

        return $record;
	}
	function myRecordCount($query){
		global $mysqliConn, $useMySQLi;

		if ($useMySQLi){
			$rs      			= $mysqliConn->query($query) or die(mysqli_error()."<br>".$query);
			$rsCount 			= mysqli_num_rows($rs);
		}
		else{
			$rs      			= mysql_query($query) or die(mysql_error()."<br>".$query);
			$rsCount 			= mysql_num_rows($rs);
		}

		$this->pRecordCount = $rsCount;
		unset($rs);
		return $rsCount;
	}

	function startPaging($query){
		$query    = $query." LIMIT ".$this->pStartFile.",".$this->pRowsPerPage;
		$rs = q($query);
		return $rs;
	}

	function pageLinks($url){
        $cssclass = "paging_links";
		$this->pShowLinkNotice = "&nbsp;";
		if($this->pRecordCount>$this->pRowsPerPage){
			$this->pShowLinkNotice = "Page ".$this->pPageID. " of ".$this->pRecord;
			//Previous link
			$link = "";
			if($this->pPageID!==1){
                $prevPage = $this->pPageID - 1;
                $link = "<a href=\"javascript:;\" onClick=\"" . $this->getOnClick("&pid=1") . "\" class=\"$cssclass\">|<<</a>\n ";
                $link .= "<a href=\"javascript:;\" onClick=\"" . $this->getOnClick("&pid=$prevPage") ."\" class=\"$cssclass\"><<</a>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;\n";
			}
			//Number links 1.2.3.4.5.
			for($ctr=1;$ctr<=$this->pRecord;$ctr++){
				if($this->pPageID==$ctr)
                $link .=  "<a href=\"javascript:;\" onClick=\"" . $this->getOnClick("&pid=$ctr") . "\" class=\"$cssclass\"><b>$ctr</b></a>\n";
				else
                $link .= "  <a href=\"javascript:;\" onClick=\"" . $this->getOnClick("&pid=$ctr") . "\" class=\"$cssclass\">$ctr</a>\n";
			}
			//Previous Next link
			if($this->pPageID<($ctr-1)){
                $nextPage = $this->pPageID + 1;
                $link .= "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a href=\"javascript:;\" onClick=\"" . $this->getOnClick("&pid=$nextPage") . "\" class=\"$cssclass\">>></a>\n";
                $link .="<a href=\"javascript:;\" onClick=\"" . $this->getOnClick("&pid=".$this->pRecord) . "\" class=\"$cssclass\">>>|</a>\n";
			}
			return $link;
		}
	}

	function getOnClick($paging_query_string){
		global $db_table;
		//if any hardcoding is needed...(advanced feature for special needs)
		$extra_query_params = "";
		//$extra_query_params = "&Dealer=" . htmlentities($_REQUEST['Dealer']);
		return "pageTable('" . $extra_query_params . "$paging_query_string', '$this->tableName');";
	}

}

/* Random functions which may or may not be used */
if (!function_exists('echo_msg_box')){
    function echo_msg_box(){

        global $error_msg;
        global $report_msg;

        if (is_string($error_msg)){
            $error_msg = array();
        }
        if (is_string($report_msg)){
            $report_msg = array();
        }

        //for passing errors/reports over get variables
        if (isset($_REQUEST['err_msg']) && $_REQUEST['err_msg'] != ''){
            $error_msg[] = $_REQUEST['err_msg'];
        }
        if (isset($_REQUEST['rep_msg']) && $_REQUEST['rep_msg'] != ''){
            $report_msg[] = $_REQUEST['rep_msg'];
        }

        if(is_array($report_msg)){
            $first = true;
                foreach ($report_msg as $e){
                    if($first){
                        $reports.= "&nbsp;&nbsp; $e";
                        $first = false;
                    }
                    else
                        $reports.= "<br /> $e";
                }
        }
        if(isset($reports) && $reports != ''){
            echo "<div class='report'>$reports</div>";
        }

        if(is_array($error_msg)){
            $first = true;
                foreach ($error_msg as $e){
                    if($first){
                        $errors.= "&nbsp;&nbsp; $e";
                        $first = false;
                    }
                    else
                        $errors.= "<br />$e";
                }
        }
        if(isset($errors) && $errors != ''){
            echo "<div class='error'>$errors</div>";
        }
    }
}

if (!function_exists('make_filename_safe')) {

   function make_filename_safe($filename) {
      $normalizeChars = array(
         '�' => 'S',
         '�' => 's',
         '�' => 'Dj',
         '�' => 'Z',
         '�' => 'z',
         '�' => 'A',
         '�' => 'A',
         '�' => 'A',
         '�' => 'A',
         '�' => 'A',
         '�' => 'A',
         '�' => 'A',
         '�' => 'C',
         '�' => 'E',
         '�' => 'E',
         '�' => 'E',
         '�' => 'E',
         '�' => 'I',
         '�' => 'I',
         '�' => 'I',
         '�' => 'I',
         '�' => 'N',
         '�' => 'O',
         '�' => 'O',
         '�' => 'O',
         '�' => 'O',
         '�' => 'O',
         '�' => 'O',
         '�' => 'U',
         '�' => 'U',
         '�' => 'U',
         '�' => 'U',
         '�' => 'Y',
         '�' => 'B',
         '�' => 'Ss',
         '�' => 'a',
         '�' => 'a',
         '�' => 'a',
         '�' => 'a',
         '�' => 'a',
         '�' => 'a',
         '�' => 'a',
         '�' => 'c',
         '�' => 'e',
         '�' => 'e',
         '�' => 'e',
         '�' => 'e',
         '�' => 'i',
         '�' => 'i',
         '�' => 'i',
         '�' => 'i',
         '�' => 'o',
         '�' => 'n',
         '�' => 'o',
         '�' => 'o',
         '�' => 'o',
         '�' => 'o',
         '�' => 'o',
         '�' => 'o',
         '�' => 'u',
         '�' => 'u',
         '�' => 'u',
         '�' => 'y',
         '�' => 'y',
         '�' => 'b',
         '�' => 'y',
         '�' => 'f');

      $strip = array(
         "~",
         "`",
         "!",
         "@",
         "#",
         "$",
         "%",
         "^",
         "&",
         "*",
         "(",
         ")",
         "=",
         "+",
         "[",
         "{",
         "]",
         "}",
         "\\",
         "|",
         ";",
         ":",
         "\"",
         "'",
         "&#8216;",
         "&#8217;",
         "&#8220;",
         "&#8221;",
         "&#8211;",
         "&#8212;",
         "—",
         "–",
         ",",
         "<",
         ">",
         "/",
         "?");

      $toClean = str_replace(' ', '_', $filename);
      $toClean = str_replace('--', '-', $toClean);
      $toClean = strtr(stripslashes($toClean), $normalizeChars);
      $toClean = str_replace($strip, "", $toClean);
      $toClean = rand(1, 9999) . "_" . $toClean;

      return $toClean;

   }
}
?>
