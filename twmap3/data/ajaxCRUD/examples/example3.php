<?php

	//note: this example reloads the page on an ADD ROW because it allows file uploading
	require_once('../preheader.php'); // <-- this include file MUST go first before any HTML/output

	#the code for the class
	include_once ('../ajaxCRUD.class.php'); // <-- this include file MUST go first before any HTML/output

	#create an instance of the class
    $tblFriend = new ajaxCRUD("Friend", "tblFriend", "pkFriendID", "../");
?>
<!DOCTYPE html PUBLIC"-//W3C//DTD XHTML 1.0 Strict//EN"
"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html>
	<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

		<?php $tblFriend->insertHeader(); ?>

		<!-- these js/css includes are ONLY to make the calendar widget work (fldDateMet);
			 these includes are not necessary for the class to work!! -->
		<link rel="stylesheet" href="includes/jquery.ui.all.css">
		<script src="includes/jquery.ui.core.js"></script>
		<!--script src="includes/jquery.ui.widget.js"></script-->
		<script src="includes/jquery.ui.datepicker.js"></script>
	</head>

<?php

	#change orientation (if desired/needed for large number of fields in a table)
	//$tblFriend->setOrientation("vertical"); //if you want the table to arrange vertically

    #i can define a relationship to another table
    #the 1st field is the fk in the table, the 2nd is the second table, the 3rd is the pk in the second table, the 4th is field i want to retrieve as the dropdown value
    #http://ajaxcrud.com/api/index.php?id=defineRelationship
    $tblFriend->defineRelationship("fkMarriedTo", "tblLadies", "pkLadyID", "fldName", "fldSort DESC"); //last var (sorting) is optional; see reference documentation

    #how you want the fields to visually display in the table header
    $tblFriend->displayAs("pkFriendID", "ID");
	$tblFriend->displayAs("fldName", "Name");
	$tblFriend->displayAs("fldAddress", "Address");
	$tblFriend->displayAs("fldCity", "City");
	$tblFriend->displayAs("fldState", "State");
	$tblFriend->displayAs("fldZip", "Zip");
	$tblFriend->displayAs("fldPhone", "Phone");
	$tblFriend->displayAs("fldEmail", "Email");
	$tblFriend->displayAs("fldBestFriend", "Best Friend?");
	$tblFriend->displayAs("fldDateMet", "Date We Met");
	$tblFriend->displayAs("fldFriendRating", "Rating");
	$tblFriend->displayAs("fldOwes", "Owes Me How Much?");
	$tblFriend->displayAs("fldPicture", "Image");
	$tblFriend->displayAs("fkMarriedTo", "Married To");

	#disallow new friends to be added (removes the add button)
	//$tblFriend->disallowAdd();

	#use if you only want to show a few of the fields (not all)
	//$tblFriend->showOnly("fldName, fldAddress, fldState, fldOwes");

	#use if you want to rearrange the order your fields display (different from table schema)
	//$tblFriend->orderFields("pkFriendID, fldAddress, fldName, fldState");

	#set the number of rows to display (per page)
    $tblFriend->setLimit(2);

    #set a filter box at the top of the table
    $tblFriend->addAjaxFilterBox('fldName', 20);
    $tblFriend->addAjaxFilterBox('fldDateMet');
    $tblFriend->addAjaxFilterBox('fkMarriedTo');

	#allow picture to be a file upload
	$tblFriend->setFileUpload('fldPicture','uploads/','uploads/');
	//$tblFriend->disallowEdit("fldPicture");
	$tblFriend->onAddExecuteCallBackFunction("myFunctionAfterAdd");

	#format field output
	$tblFriend->formatFieldWithFunction('fldOwes', 'addDollarSign');
	$tblFriend->formatFieldWithFunction('fldPicture', 'displayImage');

	$tblFriend->defineCheckbox("fldBestFriend", "Y", "N");

	#modify field with class
	$tblFriend->modifyFieldWithClass("fldDateMet", "datepicker");
	$tblFriend->modifyFieldWithClass("fldZip", "zip required");
	$tblFriend->modifyFieldWithClass("fldPhone", "phone required");
	$tblFriend->modifyFieldWithClass("fldEmail", "email");

	#set allowable values for certain fields
	$ratingVals   = array("0","1", "2","3","4","5");
    $tblFriend->defineAllowableValues("fldFriendRating", $ratingVals);

	$states = array(
				array("AL","Alabama"),
				array("AK","Alaska"),
				array("AZ","Arizona"),
				array("AR","Arkansas"),
				array("CA","California"),
				array("CO","Colorado"),
				array("CT","Connecticut"),
				array("DE","Delaware"),
				array("DC","District Of Columbia"),
				array("FL","Florida"),
				array("GA","Georgia"),
				array("HI","Hawaii"),
				array("ID","Idaho"),
				array("IL","Illinois"),
				array("IN","Indiana"),
				array("IA","Iowa"),
				array("KS","Kansas"),
				array("KY","Kentucky"),
				array("LA","Louisiana"),
				array("ME","Maine"),
				array("MD","Maryland"),
				array("MA","Massachusetts"),
				array("MI","Michigan"),
				array("MN","Minnesota"),
				array("MS","Mississippi"),
				array("MO","Missouri"),
				array("MT","Montana"),
				array("NE","Nebraska"),
				array("NV","Nevada"),
				array("NH","New Hampshire"),
				array("NJ","New Jersey"),
				array("NM","New Mexico"),
				array("NY","New York"),
				array("NC","North Carolina"),
				array("ND","North Dakota"),
				array("OH","Ohio"),
				array("OK","Oklahoma"),
				array("OR","Oregon"),
				array("PA","Pennsylvania"),
				array("RI","Rhode Island"),
				array("SC","South Carolina"),
				array("SD","South Dakota"),
				array("TN","Tennessee"),
				array("TX","Texas"),
				array("UT","Utah"),
				array("VT","Vermont"),
				array("VA","Virginia"),
				array("WA","Washington"),
				array("WV","West Virginia"),
				array("WI","Wisconsin"),
				array("WY","Wyoming")
				);

	$tblFriend->defineAllowableValues("fldState", $states);

	#show CSV export button
	$tblFriend->showCSVExportOption();

	#use if you want to move the add form to the top of the page
	//$tblFriend->displayAddFormTop();

	#order the table by any field you want
	$tblFriend->addOrderBy("ORDER BY fldName");

	#add a button at the bottom of the table which simply goes to another page
	$tblFriend->addButton("No More Friends. Take Me Home", "./");

	//$tblFriend->turnOffSorting(); //turns off ajax sorting by pressing header links
	//$tblFriend->disableTableHeaders(); //disables table headers from displaying

	//$tblFriend->turnOffAjaxEditing(); //turns of ajax editing of all data

	#some logic if we want to add a field automatically on add
	$state = "";
	if (isset($_REQUEST['state'])){
		$state = $_REQUEST['state'];
	}
	if ($state){
		$tblFriend->addWhereClause("WHERE fldState = \"$state\"");
		$tblFriend->omitAddField("fldState");
		$tblFriend->addValueOnInsert("fldState", $state);
	}


	echo "<h3>Example3 tests FormatFieldWithFunction, defineRelationship, Multiple Filters, and a Date Picker</h3>\n";

	$tblFriend->showTable();

	echo "<p>Above is a table of my friends. The javascript masking and validation are in the fields 'phone' and 'zip' (required). There's a datapicker on the 'Date we Met' field.</p>\n";

	#self-defined functions used for formatFieldWithFunction
	function addDollarSign($val) {
		return "$" . $val;
	}

	function displayImage($val){
		return "<img src=\"uploads/$val\" width=\"90\">";
	}

	function myFunctionAfterAdd($array){
		//print_r($array);
	}
?>

	</body>
</html>