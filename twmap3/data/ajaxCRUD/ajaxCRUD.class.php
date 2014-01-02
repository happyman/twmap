<?php
	/************************************************************************/
	/* ajaxCRUD.class.php	v5.5                                            */
	/* ===========================                                          */
	/* Copyright (c) 2011 by Loud Canvas Media (arts@loudcanvas.com)        */
	/* http://www.loudcanvas.com                                            */
	/*                                                                      */
	/* This program is free software. You can redistribute it and/or modify */
	/* it under the terms of the GNU General Public License as published by */
	/* the Free Software Foundation; either version 2 of the License.       */
	/************************************************************************/

	define('EXECUTING_SCRIPT', $_SERVER['PHP_SELF']);

	#this top part is for the ajax actions themselves. the class is BELOa

    $ajaxAction = $_REQUEST['ajaxAction'];
    if ($ajaxAction != ""){

		//these lines make sure caching do not cause ajax saving/displaying issues
		header("Cache-Control: no-cache, must-revalidate");
		//A date in the past
		header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
		header("Content-Type: text/html; charset=utf-8");

		$table      = $_REQUEST['table'];
		$pk         = $_REQUEST['pk'];
		$field      = trim($_REQUEST['field']);
		$id         = $_REQUEST['id'];
		$val        = $_REQUEST['val'];
		$table_num  = $_REQUEST['table_num'];

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
			echo $_SESSION['row_count'];
		}

		if ($ajaxAction == 'update'){
			//$val = str_replace("<P>","<br /><br />", $val);
			//$val = str_replace("<p>","<br /><br />", $val);

			//$val = str_replace("</P>","", $val);
			//$val = str_replace("</p>","", $val);

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

    //defines if the add function uses ajax
    var $ajax_add = true;

    //defines if the class allows you to edit all fields
    var $ajax_editing = true;

    //the fields to be displayed
    var $display_fields = array();

    //the fields to be inputted when adding a new entry (90% time will be all fields). can be changed via the omitAddField method
    var $add_fields = array();
    var $add_form_top = FALSE; //the add form (by default) is below the table. use displayAddFormTop() to bring it to the top

    //the fields which are displayed, but not editable
    var $uneditable_fields = array();

	var $sql_where_clause;
    var $sql_where_clauses = array(); //array for IF there is more than one where clause
	var $sql_order_by;
    var $num_where_clauses;

    var $on_add_specify_primary_key = false;

    //table border - default is off: 0
    var $border;

    var $orientation; //orientation of table (detault is horizontal)

    //array containing values for a button next to the "go back" button at the bottom. [0] = value [1] = url [2] = extra tags/javascript
    var $bottom_button = array();

    //array with value being the url for the buttom to go to (passing the id) [0] = value [1] = url
    var $row_button = array();
    var $addButtonToRowWindowOpen; //default is "new" window

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
    var $file_upload_info = array(); //array[$field_name][destination_folder] and array[$field_name][relative_folder]
    var $filename_append_field = "";

    //array dictating that "dropdown" fields do not show dropdown (but text editor) on edit (format: array[field] = true/false);
    //used in defineAllowableValues function
    var $field_no_dropdown = array();

    //array holding the (user-defined) function to format a field with on display (format: array[field] = function_name);
    //used in formatFieldWithFunction function
    var $format_field_with_function = array();

    var $onAddExecuteCallBackFunction;
    var $onFileUploadExecuteCallBackFunction;
    var $onDeleteFileExecuteCallBackFunction;

    //(if true) put a checkbox before each row
    var $showCheckbox;

    var $loading_image_html;

    var $emptyTableMessage;

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

    //any 'notes' to display next to a field when adding a row
    var $fieldNote = array();

    //any initial values for a field (when adding a row)
    var $initialFieldValue = array();

	// Constructor
    //by default ajaxCRUD assumes all necessary files are in the same dir as the script calling it (eg $ajaxcrud_root = "")
    function ajaxCRUD($item, $db_table, $db_table_pk, $ajaxcrud_root = "") {

        //global variable - for allowing multiple ajaxCRUD tables on one page
        global $num_ajaxCRUD_tables_instantiated;
        if ($num_ajaxCRUD_tables_instantiated === "") $num_ajaxCRUD_tables_instantiated = 0;

        $this->showCheckbox     = false;
        $this->ajaxcrud_root    = $ajaxcrud_root;
        //$this->ajax_file        = "ajax_ajaxCRUD.php";
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

        $this->doActionOnShowTable = true;
        $this->addButtonToRowWindowOpen = "new"; //change to 'same' to open page in a new window with addButtonToRow function

        $this->loading_image_html = "<center><br /><br  /><img src=\'" . $this->ajaxcrud_root . "css/loading.gif\'><br /><br /></center>"; //changed via setLoadingImageHTML()
        $this->emptyTableMessage = "No data in this table. Click add button below.";

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

		//for filtering if there is a request parameter
		$count_filtered = 0;
		$action = $_REQUEST['action'];
        foreach ($this->fields as $field){
			if ($_REQUEST[$field] != '' && ($action != 'add' && $action != 'delete' && $action != 'update' && $action != 'upload' && $action != 'delete_file')){
				$filter_field = $field;
				$filter_value = $_REQUEST[$field];
				$filter_where_clause = "WHERE $filter_field LIKE \"%" . $filter_value . "%\"";
				$this->addWhereClause($filter_where_clause);
				$this->filtered_table = true;
                $count_filtered++;
			}
		}
        if ($count_filtered > 0){
            $this->filtered_table;
        }
        else{
            $this->filtered_table = false;
        }


		return true;
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
    }

    function turnOffPaging($limit = ""){
        $this->showPaging = false;
        if ($limit != ''){
            $this->sql_limit = " LIMIT $limit";
        }
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

    function addAjaxFilterBox($field_name){
        $this->ajaxFilter_fields[] = $field_name;

        //defaults to size of "10" (unless changed via setAjaxFilterBoxSize)
        $this->setAjaxFilterBoxSize($field_name, 10);
    }

    function setAjaxFilterBoxSize($field_name, $size){
        $this->ajaxFilterBoxSize[$field_name] = $size;
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
            foreach($this->sql_where_clauses as $where_clause){
                $new_where = str_replace("WHERE", "AND", $where_clause);
                $this->sql_where_clause .= " $new_where ";
            }
        }

	}

	function addOrderBy($sql_order_by){
		$this->sql_order_by = " " . $sql_order_by;
	}

    function formatFieldWithFunction($field, $function_name){
        $this->format_field_with_function[$field] = $function_name;
    }

    function defineRelationship($fkCategoryID, $category_table, $category_table_pk, $category_field_name, $category_sort_field = "", $category_required = "1", $where_clause = ""){

        $this->db_table_fk_array[]          = $fkCategoryID;
        $this->category_table_array[]       = $category_table;
        $this->category_table_pk_array[]    = $category_table_pk;
        $this->category_field_array[]       = $category_field_name;
        $this->category_sort_field_array[]  = $category_sort_field;
        $this->category_whereclause_array[] = $where_clause;

        //make the relationship required for the field
        if ($category_required == "1"){
            $this->category_required[$fkCategoryID] = TRUE;
        }
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

    function setAddFieldNote($field, $caption){
        $this->fieldNote[$field] = $caption;
    }

    function setInitialAddFieldValue($field, $value){
        $this->initialFieldValue[$field] = $value;
    }

    function setLimit($limit){
        $this->limit = $limit;
    }

    function getRowCount(){
        if ($_SESSION['row_count'] == ""){
        	$count = q1("SELECT COUNT(*) FROM " . $this->db_table . $this->sql_where_clause . $this->sql_order_by);
        }
        else{
        	$count = $_SESSION['row_count'];
        }
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

    function addValueOnInsert($field_name, $insert_value){
        //if the value is NOW() then it's a date field or if its an integer --> otherwise put quotes around it.
        //if ($insert_value != "NOW()" && !is_int($insert_value)){
        //    $insert_value = "\"" . $insert_value . "\"";
        //}
        $this->add_values[] = array(0 => $field_name, 1 => $insert_value);
    }

    function onAddExecuteCallBackFunction($function_name){
        $this->onAddExecuteCallBackFunction = $function_name;
        $this->ajax_add = false;
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

    function setFileUpload($field_name, $destination_folder, $relative_folder = ""){
        //put values into array
        $this->file_uploads[] = $field_name;
        $this->file_upload_info[$field_name][destination_folder] = $destination_folder;
        $this->file_upload_info[$field_name][relative_folder] = $relative_folder;

        //the filenames that are saved are not editable
        $this->disallowEdit($field_name);

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

    function insertHeader($ajax_file){

        if ($this->css_file == ''){
            $this->css_file = 'default.css';
        }


		/* Load Javascript dependencies */
		echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />';
		echo "<script type=\"text/javascript\" src=\"http://ajax.googleapis.com/ajax/libs/jquery/1.3.2/jquery.min.js\"></script>\n"; //per release 8, using jquery instead of protoculous
        echo "<script src=\"" . $this->ajaxcrud_root . "javascript_functions.js\" type=\"text/javascript\"></script>\n";
       // echo "<link href=\"" . $this->ajaxcrud_root . "css/ajaxcrud.css\" rel=\"stylesheet\" type=\"text/css\" media=\"screen\" />\n";
        echo "
            <script>\n
                ajax_file = \"$this->ajax_file\"; \n
                this_page = \"" . $_SERVER['PHP_SELF'] . "\"; \n
                loading_image_html = \"$this->loading_image_html\"; \n
		extraParams = \"&name=" . urlencode($_REQUEST['name']) . "&id=" . $_REQUEST['id'] ."&cond=".$_REQUEST['cond']."\";\n
            </script>\n";

        //are we even using a stylesheet? (it can be turned off)
        if ($this->css){
        	echo "<link href=\"" . $this->ajaxcrud_root . "css/" . $this->css_file ."\" rel=\"stylesheet\" type=\"text/css\" media=\"screen\" />\n";
        }

		return true;
	}

    function disallowEdit($field){
        $this->uneditable_fields[] = $field;
    }

    function disallowDelete(){
        $this->delete = false;
    }

    function disallowAdd(){
        $this->add = false;
    }

    function addButton($value, $url, $tags = ""){
        $this->bottom_button = array(0 => $value, 1 => $url, 2 => $tags);
    }

    function addButtonToRow($value, $url, $attach_params = "", $javascript_tags = ""){
        $this->row_button[] = array(0 => $value, 1 => $url, 2 => $attach_params, 3 => $javascript_tags);
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
				$report_msg[] = "$item Deleted";
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


                    $submitted_values[] = $submitted_value_cleansed;
                    //also used for callback function
                    $submitted_array[$field] = $submitted_value_cleansed;
                }

                //get rid of the primary key in the fields column
                if (!$this->on_add_specify_primary_key){
                    unset($submitted_values[0]);    //assumes the primary key is the FIRST field in the array
                }

                //for adding values to the row which were not in the ADD row table - but are specified by ADD on INSERT
                if (count($this->add_values) > 0){
                    foreach ($this->add_values as $add_value){
                        $field_name = $add_value[0];
                        $the_add_value = $add_value[1];

                        if ($submitted_array[$field_name] == ''){
                            $submitted_array[$field_name] = $the_add_value;
                        }

                        //reshuffle numeric indexed array
                        unset($submitted_values);
                        $submitted_values = array();
                        foreach($submitted_array as $field){
                            $submitted_values[] = $field;
                        }

                        //get rid of the primary key in the fields column
                        if (!$this->on_add_specify_primary_key){
                            unset($submitted_values[0]);    //assumes the primary key is the FIRST field in the array
                        }

                    }
                }

                //wrap each field in quotes
                $string_submitted_values = "\"" . implode("\",\"", $submitted_values) . "\"";;


                //for getting datestamp of new row for mysql's "NOW" to work
                $string_submitted_values = str_replace('"NOW()"', 'NOW()', $string_submitted_values);

                //print_r($submitted_values);

                if ($string_submitted_values != ''){
                    if (!$this->on_add_specify_primary_key && $this->primaryKeyAutoIncrement){
                        //don't allow the primary key to be inputted
                        $fields_array_without_pk = $this->fields;
                        unset($fields_array_without_pk[0]);   //assumes the primary key is the FIRST field in the array
                        $string_fields_without_pk = implode(",", $fields_array_without_pk);

                        $query = "INSERT INTO $this->db_table($string_fields_without_pk) VALUES ($string_submitted_values)";
                    }
                    else{
                        if (!$this->primaryKeyAutoIncrement){
                            $primary_key_value = q1("SELECT MAX($this->db_table_pk) FROM $this->db_table");
                            if ($primary_key_value > 0) $primary_key_value++;
                            $primary_key_value = $primary_key_value . ", ";
                        }

                        $string_fields_with_pk = implode(",", $this->fields);
                        $query = "INSERT INTO $this->db_table($string_fields_with_pk) VALUES ($primary_key_value $string_submitted_values)";
                    }
                    $success = qr($query);

                    if ($success){
                        $insert_id = mysql_insert_id();
                        //$_SESSION[insert_id] = $insert_id;

                        $report_msg[] = "$item Added";

                        if ($uploads_on){
                            foreach($this->file_uploads as $field_name){
                                $file_dest  = $this->file_upload_info[$field_name][destination_folder];

                                if ($_FILES[$field_name]['name'] != ''){
                                    $this->uploadFile($insert_id, $field_name, $file_dest);
                                }
                            }
                        }

                        if ($this->onAddExecuteCallBackFunction != ''){
                            $submitted_array[id] = $insert_id;
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

        if ($action == 'upload' && $_REQUEST['field_name'] && $_REQUEST['id'] != ''){
            $update_id      = $_REQUEST['id'];
            $file_field     = $_REQUEST['field_name'];
            $upload_folder  = $this->file_upload_info[$file_field][destination_folder];

            $success = $this->uploadFile($update_id, $file_field, $upload_folder);

            if ($success){
                $report_msg[] = "File Uploaded Sucessfully.";
            }
            else{
                $error_msg[] = "There was an error uploading your file (or none was selected).";
            }

        }//action = upload

        if ($action == 'delete_file' && $_REQUEST['field_name'] && $_REQUEST['id'] != ''){
            $delete_id      = $_REQUEST['id'];
            $file_field     = $_REQUEST['field_name'];
            $upload_folder  = $_REQUEST['upload_folder'];

            $filename = q1("SELECT $file_field FROM $this->db_table WHERE $this->db_table_pk = $delete_id");
            $success = qr("UPDATE $this->db_table SET $file_field = \"\" WHERE $this->db_table_pk = $delete_id");

            if ($success){
                $file_dest  = $this->file_upload_info[$file_field][destination_folder];

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


    //a file must have been "sent"/posted for this to work
    function uploadFile($row_id, $file_field, $upload_folder){
        @$fileName  = $_FILES[$file_field]['name'];
        @$tmpName   = $_FILES[$file_field]['tmp_name'];
        @$fileSize  = $_FILES[$file_field]['size'];
        @$fileType  = $_FILES[$file_field]['type'];

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

        }

        if ($update_success){
            return true;
            //$report_msg[] = "File Uploaded.";
        }
        else{
            return false;
            //$error_msg[] = "There was an error uploading your file (or none was selected).";
        }
    }

	function showTable(){

        global $error_msg;
        global $report_msg;
        global $warning_msg_displayed;
        global $num_ajaxCRUD_tables_instantiated;

        $num_ajaxCRUD_tables_instantiated++;

        /* Sort Table
           Note: this cancels out default sorting set by addOrderBy()
        */

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


        //the HTML to display
        $top_html = "";     //top header stuff
        $table_html = "";   //for the html table itself
        $bottom_html = "";
        $add_html = "";     //for the add form

        $html = ""; //all combined

        if ( $num_ajaxCRUD_tables_instantiated == 1 ){
            //pull in the  css and javascript files
            $this->insertHeader($this->ajax_file);
        }

        if ($this->doActionOnShowTable){
            if ($_REQUEST['action'] != ''){
                $this->doAction($_REQUEST['action']);
            }
        }

		$item = $this->item;

		$top_html .= "<a name='ajaxCRUD" . $num_ajaxCRUD_tables_instantiated ."' id='ajaxCRUD" . $num_ajaxCRUD_tables_instantiated  ."'></a>\n";

        if (count($this->ajaxFilter_fields) > 0){
            $top_html .= "<form id=\"" . $this->db_table . "_filter_form\">\n";
            $top_html .= "<table cellspacing='5' align='center'><tr>";

            foreach ($this->ajaxFilter_fields as $filter_field){
                $display_field = $filter_field;
                if ($this->displayAs_array[$filter_field] != ''){
                    $display_field = $this->displayAs_array[$filter_field];
                }

                $textbox_size = $this->ajaxFilterBoxSize[$filter_field];

                $filter_value = "";
                if ($_REQUEST[$filter_field] != ''){
                	$filter_value = $_REQUEST[$filter_field];
                }

                $top_html .= "<td><b>$display_field</b>: <input type=\"text\" size=\"$textbox_size\" name=\"$filter_field\" value=\"$filter_value\" onKeyUp=\"filterTable(this, '" . $this->db_table . "', '$filter_field', '$extra_query_params');\"></td>";
            }
            $top_html .= "</tr></table>\n";
            $top_html .= "</form>\n";
        }


		#############################################
		#
		# Begin code for displaying database elements
		#
		#############################################

		$select_fields = implode(",", $this->fields);

        $sql = "SELECT * FROM " . $this->db_table . $this->sql_where_clause . $this->sql_order_by;

        if ($this->showPaging){
            $pageid        = $_GET['pid'];//Get the pid value
            if(intval($pageid) == 0) $pageid  = 1;
            $Paging        = new paging();
            $Paging->tableName = $this->db_table;

            $total_records = $Paging->myRecordCount($sql);//count records
            $totalpage     = $Paging->processPaging($this->limit,$pageid);
            $rows          = $Paging->startPaging($sql);//get records in the databse
            $links         = $Paging->pageLinks(basename($PHP_SELF));//1234 links
            unset($Paging);
        }
        else{
            $rows = q($sql . $this->sql_limit);
        }

        //$rows = q("SELECT * FROM " . $this->db_table");
		$row_count = count($rows);
        $this->row_count = $row_count;
        $_SESSION['row_count'] = $row_count;

        if ($row_count == 0){
            $report_msg[] = $this->emptyTableMessage;
        }

        #this is an optional function which will allow you to display errors or report messages as desired. comment it out if desired
        //only show the message box if it hasn't been displayed already
        if ($warning_msg_displayed == 0 || $warning_msg_displayed == ''){
            echo_msg_box();
        }

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

				$table_html .= "<tr>\n";
				//for an (optional) checkbox
				if ($this->showCheckbox){
					$table_html .= "<th>&nbsp;</th>";
				}

				foreach ($this->display_fields as $field){
					$field_name = $field;
					if ($this->displayAs_array[$field] != ''){
						$field = $this->displayAs_array[$field];
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
								$table_html .= "<a href='javascript:;' onClick=\"changeSort('$this->db_table', '$field_name', '$this->sort_direction');\" >" . $field . "</a>";
							}
							$table_html .= "</th>";
						} else {
							$table_html .= "<th><a href='javascript:;' onClick=\"changeSort('$this->db_table', '$field_name', '$this->sort_direction');\" >" . $field . "</a></th>";
						}
				}

				if ($this->delete || (count($this->row_button)) > 0){
					$table_html .= "<th>Action</th>\n";
				}

				$table_html .= "</tr>\n";
			}

            $count = 0;
            $class = "odd";

            $attach_params = "";

            foreach ($rows as $row){
                $id = $row[$this->db_table_pk];

				$valign = "top";
				if ($this->orientation == 'vertical'){
					$class = "vertical";
					$valign = "middle";
				}

                $table_html .= "<tr class='$class' id=\"" . $this->db_table . "_row_$id\" valign='{$valign}'>\n";


                if ($this->showCheckbox && $this->orientation != 'vertical'){
                    $checkbox_selected = "";
                    if ($id == $_REQUEST[$this->db_table_pk]) $checkbox_selected = " checked";
                    $table_html .= "<td><input type='checkbox' $checkbox_selected onClick=\"window.location ='" . $_SERVER['PHP_SELF'] . "?$this->db_table_pk=$id'\" /></td>";
                }

                foreach($this->display_fields as $field){
                    $cell_data = $row[$field];

                    //for adding a button via addButtonToRow (using "all" as the "attach params" optional third parameter)
                    if (count($this->row_button) > 0){
                        $attach_params .= "&" . $field . "=" . $cell_data;
                    }

                    $cell_value = $cell_data; //retain original value in new variable (before executing callback method)

                    if ($this->format_field_with_function[$field] != ''){
                        $cell_data = call_user_func($this->format_field_with_function[$field], $cell_data);
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
                    if ( ($this->fieldInArray($field, $this->uneditable_fields) && (!is_numeric($found_category_index))) || !$this->ajax_editing){

                        $table_html .= "<td>";

                        $key = array_search($field, $this->display_fields);

                        if ($this->fieldInArray($field, $this->file_uploads)){

                            //a file exists for this field
                            if ($cell_data != ''){
                                $file_link = $this->file_upload_info[$field][relative_folder] . $row[$field];
                                $file_dest = $this->file_upload_info[$field][destination_folder];

                                $table_html .= "<span id='text_" . $field . $id . "'><a target=\"_new\" href=\"$file_link\">" . $cell_data . "</a> (<a style=\"font-size: 9px;\" href=\"javascript:\" onClick=\"document.getElementById('file_$field$id').style.display = ''; document.getElementById('text_$field$id').style.display = 'none'; \">edit</a> <a style=\"font-size: 9px;\" href=\"javascript:\" onClick=\"deleteFile('$field', '$id')\">delete</a>)</span> \n";

                                $table_html .= "<div id='file_" . $field . $id . "' style='display:none;'>\n";
                                $table_html .= $this->showUploadForm($field, $file_dest, $id);
                                $table_html .= "</div>\n";
                            }

                            if ($cell_data == ''){
                                $table_html .= "<span id='text_" . $field . $id . "'><a style=\"font-size: 9px;\" href=\"javascript:\" onClick=\"document.getElementById('file_$field$id').style.display = ''; document.getElementById('text_$field$id').style.display = 'none'; \">Add File</a></span> \n";

                                $table_html .= "<div id='file_" . $field. $id . "' style='display:none;'>\n";
                                $table_html .= $this->showUploadForm($field, $file_dest, $id);
                                $table_html .= "</div>\n";
                            }
                        }
                        else{
                            $table_html .= $cell_data;
                        }

                    }//if field is not editable
                    else{
                        $table_html .= "<td>";

                        if (!is_numeric($found_category_index)){

                            //was allowable values for this field defined?
                            if (is_array($this->allowed_values[$field]) && !$this->field_no_dropdown[$field]){
                                $table_html .= $this->makeAjaxDropdown($id, $field, $cell_data, $this->db_table, $this->db_table_pk, $this->allowed_values[$field]);
                            }
                            else{

                                //if a checkbox
                                if (is_array($this->checkbox[$field])){
                                    $table_html .= $this->makeAjaxCheckbox($id, $field, $cell_data);
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
                                        $table_html .= $this->makeAjaxDropdown($id, $field, $cell_data, $this->db_table, $this->db_table_pk, $allowed_enum_values_array);
                                    }
                                    else{

                                        $field_length = strlen($row[$field]);
                                        if ($field_length < 51){
                                            $table_html .= $this->makeAjaxEditor($id, $field, $cell_value, 'text', $field_length, $cell_data, $field_onKeyPress);
                                        }
                                        else{
                                            $textarea_height = '';
                                            if ($this->textarea_height[$field] != '') $textarea_height = $this->textarea_height[$field];
                                            $table_html .= $this->makeAjaxEditor($id, $field, $cell_value, 'textarea', $textarea_height, $cell_data, $field_onKeyPress);
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
						$table_html .= "<th class='vertical'>Action</th>";
					}

                    $table_html .= "<td>\n";

                    if ($this->delete){
                        $table_html .= "<input type=\"button\" class=\"editingSize\" onClick=\"confirmDelete('$id', '" . $this->db_table . "', '" . $this->db_table_pk ."');\" value=\"delete\" />\n";
                    }

                    if (count($this->row_button) > 0){
                        foreach ($this->row_button as $the_row_button){
                            $value = $the_row_button[0];
                            $url = $the_row_button[1];
                            $attach_param = $the_row_button[2];
                            $javascript_onclick_function = $the_row_button[3];
                            if ($attach_param == "all"){
                                $attach = "?attachments" . $attach_params;
                            }
                            else{
                                $attach = "?" . $this->db_table_pk . "=$id";
                            }

                            //its for a user-defined ajax function (used by advanced users only)
                            if ($javascript_onclick_function != ""){
                                $javascript_for_button = "onClick=\"" . $javascript_onclick_function . "($id);\"";
                            }
                            else{
                                //most people want it to open in a NEW window:
                                $javascript_for_button = "onClick=\"window.open('".$url.$attach."','_blank');\"";

                                //open page in SAME window (overwrite with addButtonToRowWindowOpen variable)
                                if ($this->addButtonToRowWindowOpen == 'same'){
                                	$javascript_for_button = "onClick=\"location.href='" . $url . $attach . "'\"";
                                }
                            }


                            $table_html .= "<input type=\"button\" $javascript_for_button class=\"btn editingSize\" value=\"$value\" />\n";
                        }
                    }

                    $table_html .= "</td>\n";
                }

                $table_html .= "</tr>";

				if ($this->orientation == 'vertical'){
					$table_html .= "<tr><td colspan='2' style='border-top: 1px silver solid;' ></td></tr>\n";
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
		    // happyman
                $table_html .= "<br /><div style='width: 800px; position: relative; left: 50%; margin-left: -400px; text-align: center;'><center> $links </center></div><br /><br />";
            }

        }//if rows > 0

        //closing div for paging links (if applicable)
        $bottom_html = "</div><br />\n";

        //now we come to the "add" fields
        if ($this->add){
            $add_html .= "<center>\n";
	    // add resize to 
	    //$add_html .= "<script>resizeTo('830',$(document).height());</script>";
            $add_html .= "   <input type=\"button\" value=\"Add $item\" class=\"btn editingSize\" onClick=\"$('#add_form_$this->db_table').slideDown('slow');\">\n";

            if (count($this->bottom_button) > 0){
                $button_value = $this->bottom_button[0];
                $button_url = $this->bottom_button[1];
                $button_tags = $this->bottom_button[2];

                if ($button_tags == ''){
                    $tag_stuff = "onClick=\"location.href = '$button_url';\"";
                }
                else{
                    $tag_stuff = $button_tags;
                }
                $add_html .= "  <input type=\"button\" value=\"$button_value\" href=\"$button_url\" class=\"btn\" $tag_stuff>\n";
            }

            //$add_html .= "  <input type=\"button\" value=\"Go Back\" class=\"btn\" onClick=\"history.back();\">\n";
            $add_html .= "</center>\n";

            $add_html .= "<form action=\"" . $_SERVER['PHP_SELF'] ."#ajaxCRUD\" id=\"add_form_$this->db_table\" method=\"POST\" ENCTYPE=\"multipart/form-data\" style=\"display:none;\">\n";
            $add_html .= "  <br /><h3>New <b>$item</b></h3>\n";
            $add_html .= "  <table align='center' name='form'>\n";
            $add_html .= "<tr valign='top'>\n";

            //for here display ALL 'addable' fields
            foreach($this->add_fields as $field){
                if ($field != $this->db_table_pk || $this->on_add_specify_primary_key){
                    $field_value = "";

					$hideOnClick = "";
					//if a date field, show helping text
					if ($this->fieldIsDate($this->getFieldDataType($field))){
						if ($field_value == ""){
							$field_value = "YYYY-mm-dd";
							//$hideOnClick = TRUE;
						}
					}

                    //if initial field value for field is set
                    if ($this->initialFieldValue[$field] != ""){
                    	$field_value = $this->initialFieldValue[$field];
                    	//$hideOnClick = TRUE;
                    }

                    //the request (post/get) will overwrite any initial values though
                    if ($_REQUEST[$field] != '') {
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
                    if ($this->fieldNote[$field] != ""){
                    	$note = "&nbsp;&nbsp;<i>" . $this->fieldNote[$field] . "</i>";
                    }

                    //if a checkbox
                    if (is_array($this->checkbox[$field])){
                        $values = $this->checkbox[$field];
                        $value_on = $values[0];
                        $value_off = $values[1];
                        $add_html .= "<th width='20%'>$display_field</th><td>\n";
                        $add_html .= "<input type='checkbox' name=\"$field\" value=\"$value_on\">\n";
                        $add_html .= "$note</td></tr>\n";
                    }
                    else{
                        $found_category_index = array_search($field, $this->db_table_fk_array);
                        if (!is_numeric($found_category_index) && $found_category_index == ''){

                            //it's from a set of predefined allowed values for this field
                            if (is_array($this->allowed_values[$field])){
                                $add_html .= "<th width='20%'>$display_field</th><td>\n";
                                $add_html .= "<select name=\"$field\" class='editingSize'>\n";
                                foreach ($this->allowed_values[$field] as $dropdown){
                                    $selected = "";
                                    $dropdown_value = $dropdown[0];
                                    $dropdown_text  = $dropdown[1];
                                    if ($field_value == $dropdown_value) $selected = " selected";
                                    $add_html .= "<option value=\"$dropdown_value\" $selected>$dropdown_text</option>\n";
                                }
                                $add_html .= "</select>$note</td></tr>\n";
                            }
                            else{
                                if ($this->fieldInArray($field, $this->file_uploads)){
                                    //this field is an file upload
                                    $add_html .= "<th width='20%'>$display_field</th><td><input class=\"editingSize\" type=\"file\" name=\"$field\" size=\"15\">$note</td></tr>\n";
                                    $file_uploads = true;
                                }
                                else{
                                    if ($this->fieldIsEnum($this->getFieldDataType($field))){
                                        $allowed_enum_values_array = $this->getEnumArray($this->getFieldDataType($field));

                                        $add_html .= "<th width='20%'>$display_field</th><td>\n";
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
                                        if ($this->textarea_height[$field] != ''){
                                            $add_html .= "<th width='20%'>$display_field</th><td><textarea $hideOnClick onKeyPress=\"$field_onKeyPress\" class=\"editingSize\" name=\"$field\" style='width: 97%; height: " . $this->textarea_height[$field] . "px;'>$field_value</textarea>$note</td></tr>\n";
                                        }
                                        else{
                                            //any ol' data will do
                                            $field_size = "";
                                            if ($this->fieldIsInt($this->getFieldDataType($field)) || $this->fieldIsDecimal($this->getFieldDataType($field))){
                                                $field_size = 7;
                                            }
                                            $add_html .= "<th width='20%'>$display_field</th><td><input $hideOnBlur onKeyPress=\"$field_onKeyPress\" class=\"editingSize\" type=\"text\" name=\"$field\" size=\"$field_size\" value=\"$field_value\" maxlength=\"150\">$note</td></tr>\n";
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
                                    $add_html .= "<option value=0>--Select--</option>\n";
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

            if ($this->ajax_add){
                $add_html .= "<input class=\"editingSize\" type=\"button\" onClick=\"
                    setLoadingImage('$this->db_table');
                    var fields = getFormValues(document.getElementById('add_form_$this->db_table'), '');
                    fields = fields + '&table=$this->db_table';
                    var req = '" . $_SERVER[PHP_SELF] . "?action=add&' + fields;
                    clearForm('add_form_$this->db_table');
                    sndAddReq(req, '$this->db_table');
                    return false;\" value=\"Save $item\">";
            }
            else{
                $add_html .= "<input class=\"editingSize\" type=\"submit\" value=\"Save $item\">";
            }

            $add_html .= "</td><td><input style='float: right;' class=\"editingSize\" type=\"button\" onClick=\"$('#add_form_$this->db_table').slideUp('slow');\" value=\"Cancel\"></td></tr>\n</table>\n";

            $add_html .= "<input type=\"hidden\" name=\"action\" value=\"add\">\n";
            $add_html .= "<input type=\"hidden\" name=\"table\" value=\"$this->db_table\">\n";

            if ($file_uploads){
                $add_html .= "<input type=\"hidden\" name=\"uploads_on\" value=\"true\">\n";
            }

            $add_html .= "</form>\n";

        }//if adding fields is "allowed"

        /*
        THIS IS IMPORTANT
        for ajax retrieval (see top of page) */
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
			//r sub0 is the name of the field (tricky ... but it works)
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

	function makeAjaxEditor($unique_id, $field_name, $field_value, $type = 'textarea', $field_size = "", $field_text = "", $onKeyPress_function = ""){

        $prefield = trim($this->db_table . $field_name . $unique_id);

		$input_name = $type . "_" . $prefield;

        $return_html = "";

		if ($field_text == "") $field_text = $field_value;
		if ($field_value == "") $field_text = "--";

        $return_html .= "<span class=\"editable hand_cursor\" id=\"" . $prefield ."_show\" onClick=\"
			document.getElementById('" . $prefield . "_edit').style.display = '';
			document.getElementById('" . $prefield . "_show').style.display = 'none';
			document.getElementById('" . $input_name . "').focus();
            \">" . $field_text . "</span>
        <span id=\"" . $prefield ."_edit\" style=\"display: none;\">
            <form style=\"display: inline;\" name=\"form_" . $prefield . "\" id=\"form_" . $prefield . "\" onsubmit=\"
				document.getElementById('" . $prefield . "_edit').style.display='none';
				document.getElementById('" . $prefield . "_save').style.display='';
                var req = '" . $this->ajax_file . "?ajaxAction=update&id=" . $unique_id . "&field=" . $field_name . "&table=" . $this->db_table . "&pk=" . $this->db_table_pk . "&val=' + encodeURIComponent(document.getElementById('" . $input_name . "').value);
				sndUpdateReq(req);
				return false;
			\">";

            //for getting rid of the html space, replace with actual no text
            if ($field_value == "&nbsp;&nbsp;") $field_value = "";

            if ($type == 'text'){
                if ($field_size == "") $field_size = 50;
                $return_html .= "<input ONKEYPRESS=\"$onKeyPress_function\" id=\"text_$prefield\" name=\"$input_name\" type=\"text\" class=\"editingSize editMode\" size=\"$field_size\" value=\"$field_value\"/>\n";
			}
			else{
                if ($field_size == "") $field_size = 80;
                $return_html .= "<textarea ONKEYPRESS=\"$onKeyPress_function\" id=\"$input_name\" name=\"textarea_$prefield\" class=\"editingSize editMode\" style=\"width: 100%; height: " . $field_size . "px;\">$field_value</textarea>\n";
                $return_html .= "<br /><input type=\"submit\" class=\"editingSize\" value=\"Ok\">\n";
			}

        $return_html .= "
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

        if ($selected_dropdown_text == "NOTHING_ENTERED"){

            $selected_dropdown_text = $field_value;

            foreach ($array_list as $list){
                if (is_array($list)){
                    $list_val = $list[0];
                    $list_option = $list[1];
                }
                else{
                    $list_val = $list;
                    $list_option = $list;
                }

                if ($list_val == $field_value) $selected_dropdown_text = $list_option;
            }
        }

        if ($selected_dropdown_text == '' || $selected_dropdown_text == '&nbsp;&nbsp;'){
            $no_text = true;
            $selected_dropdown_text = "&nbsp;--&nbsp;";
        }

        $prefield = trim($this->db_table . $field_name . $unique_id);

        $return_html = "<span class=\"editable hand_cursor\" id=\"" . $prefield . "_show\" onClick=\"
			document.getElementById('" . $prefield . "_edit').style.display = '';
			document.getElementById('" . $prefield . "_show').style.display = 'none';
			\">" . $selected_dropdown_text . "</span>

            <span style=\"display: none;\" id=\"" . $prefield . "_edit\">
                <form style=\"display: inline;\" name=\"form_" . $prefield . "\" id=\"form_" . $prefield . "\">
                <select class=\"editingSize editMode\" id=\"" . $prefield . "\" onChange=\"
                    var selected_index_value = document.getElementById('" . $prefield . "').value;
                    document.getElementById('" . $prefield . "_edit').style.display='none';
                    document.getElementById('" . $prefield . "_save').style.display='';
                    var req = '" . $this->ajax_file . "?ajaxAction=update&id=" . $unique_id . "&field=" . $field_name . "&table=" . $this->db_table . "&pk=" . $this->db_table_pk . "&dropdown_tbl=" . $dropdown_table . "&val=' + encodeURIComponent(selected_index_value);
                    sndUpdateReq(req);
                    return false;
                \">";

            if ($no_text || $this->category_required[$field_name] != TRUE){
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

			$return_html .= "<input type=\"button\" value=\"Cancel\" onClick=\"
				document.getElementById('" . $prefield . "_show').style.display = '';
				document.getElementById('" . $prefield . "_edit').style.display = 'none';
			\"/>
		</span>
		</form>

        <span style=\"display: none;\" id=\"" . $prefield . "_save\" class=\"savingAjaxWithBackground\">Saving...</span>\n";

        return $return_html;

	}//makeAjaxDropdown


	function makeAjaxCheckbox($unique_id, $field_name, $field_value){
		$prefield = trim($this->db_table) . trim($field_name) . trim($unique_id);

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

        $return_html .= "<input type=\"checkbox\" $checked name=\"$field_name" . "_fieldckbox\" id=\"$field_name$unique_id\" onClick=\"
			var " . $prefield . "_value = '';

			if (this.checked){
				" . $prefield . "_value = '$value_on';
				if (" . (int)$this->checkboxall[$field_name] . ") {
					document.getElementById('$field_name$unique_id" . "_label').innerHTML = '$value_on';
				}
			}
			else{
				". $prefield . "_value = '$value_off';
				if (" . (int)$this->checkboxall[$field_name] . ") {
					document.getElementById('$field_name$unique_id" . "_label').innerHTML = '$value_off';
				}
			}
			var req = '" . $this->ajax_file . "?ajaxAction=update&id=$unique_id&field=$field_name&table=$this->db_table&pk=$this->db_table_pk&val=' + " . $prefield . "_value;

			sndReqNoResponseChk(req);
		\">";

		if ($this->checkboxall[$field_name] == true) {
			$return_html .= "<label for=\"$field_name$unique_id\" id=\"" . $field_name . $unique_id . "_label\">$show_value</label>";
		}

        return $return_html;

	}//makeAjaxCheckbox

    function showUploadForm($field_name, $upload_folder, $row_id){
        $return_html = "";

        $return_html .= "<form action=\"" . $_SERVER['PHP_SELF'] . "#ajaxCRUD\" name=\"Uploader\" method=\"POST\" ENCTYPE=\"multipart/form-data\">\n";
        $return_html .=  "  <input type=\"file\" size=\"10\" name=\"$field_name\" />\n";
        $return_html .= "  <input type=\"hidden\" name=\"upload_folder\" value=\"$upload_folder\" />\n";
        $return_html .= "  <input type=\"hidden\" name=\"field_name\" value=\"$field_name\" />\n";
        $return_html .= "  <input type=\"hidden\" name=\"id\" value=\"$row_id\" />\n";
        $return_html .= "  <input type=\"hidden\" name=\"action\" value=\"upload\" />\n";
        $return_html .= "  <input type=\"submit\" name=\"submit\" value=\"Upload\" />\n";
        $return_html .= "</form>\n";

        return $return_html;
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
		$rs      			= mysql_query($query) or die(mysql_error()."<br>".$query);
		$rsCount 			= mysql_num_rows($rs);
		$this->pRecordCount = $rsCount;
		unset($rs);
		return $rsCount;
	}

	function startPaging($query){
		$query    = $query." LIMIT ".$this->pStartFile.",".$this->pRowsPerPage;
		$rs = q($query);
		//$rs       = mysql_query($query) or die(mysql_error()."<br>".$query);
		//mysql_free_result($rs);
		return $rs;
	}

	function pageLinks($url){
        global $choose_category,$sort, $num_ajaxCRUD_tables_instantiated;
        $cssclass = "paging_links";
		$this->pShowLinkNotice = "&nbsp;";
		if($this->pRecordCount>$this->pRowsPerPage){
			$this->pShowLinkNotice = "Page ".$this->pPageID. " of ".$this->pRecord;
			//Previous link
			if($this->pPageID!==1){
                $prevPage = $this->pPageID - 1;
                $link = "<a href=\"javascript:;\" onClick=\"" . $this->getOnClick("&pid=1&mid=$ltype&cid=$catid") . "\" class=\"$cssclass\">|<<</a>\n ";
                $link .= "<a href=\"javascript:;\" onClick=\"" . $this->getOnClick("&pid=$prevPage&mid=$ltype&cid=$catid") ."\" class=\"$cssclass\"><<</a>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;\n";
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
                $link .= "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a href=\"javascript:;\" onClick=\"" . $this->getOnClick("&pid=$nextPage&mid=$ltype&cid=$catid") . "\" class=\"$cssclass\">>></a>\n";
                $link .="<a href=\"javascript:;\" onClick=\"" . $this->getOnClick("&pid=".$this->pRecord."&mid=$ltype&cid=$catid") . "\" class=\"$cssclass\">>>|</a>\n";
			}
			return $link;
		}
	}

	function getOnClick($paging_query_string){
		global $db_table;
		//if any hardcoding is needed...
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
        if ($_REQUEST['err_msg'] != ''){
            $error_msg[] = $_REQUEST['err_msg'];
        }
        if ($_REQUEST['rep_msg'] != ''){
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
        if($reports != ''){
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
        if($errors != ''){
            echo "<div class='error'>$errors</div>";
        }
    }
}

if (!function_exists('make_filename_safe')){

    function make_filename_safe($filename){
        $filename = trim(str_replace(" ","_",$filename));
        $filename = str_replace("'", "", $filename);
        $filename = str_replace('"', '', $filename);
        $filename = str_replace('#', '_', $filename);
        $filename = str_replace('%20', '_', $filename);

        return stripslashes($filename);
    }
}
?>
