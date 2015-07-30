<?php

	require_once('../../preheader.php');
	$page_title = "Create an Event";

	#the code for the class
	include ('../../ajaxCRUD.class.php');

    #Create an instance of the class
    $tblEvent = new ajaxCRUD("Event", "tblEvent","pkEventID", "../");
    $tblEvent->setCSSFile("cuscosky.css");

    $viewPastEvents = FALSE;
    if ($_REQUEST['eventFilter'] == 'pastEvents') $viewPastEvents = TRUE;

?>
<!DOCTYPE html PUBLIC"-//W3C//DTD XHTML 1.0 Strict//EN"
"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html>
	<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
		<? 	$tblEvent->insertHeader(); ?>

		<!-- these js/css includes are ONLY to make the calendar widget work
			 these includes are not necessary for the class to work!! -->
		<link rel="stylesheet" href="../includes/jquery.ui.all.css">
		<script src="../includes/jquery.ui.core.js"></script>
		<script src="../includes/jquery.ui.datepicker.js"></script>
	</head>

<?php
	include ('header.php');

	if (!$viewPastEvents){
		echo "<h2>Upcoming Events:</h2>\n";
	}
	else{
		echo "<h2>Previous/Past Events:</h2>\n";
	}
?>

	<div style="float: right">
		Filter Events:
		<form name="filterForm" id="filterForm" method="get" style="display: inline;" action="<?=$_SERVER['PHP_SELF']?>">
			<select name="eventFilter" onchange="document.getElementById('filterForm').submit();">
				<option value="pastEvents" <? if ($viewPastEvents) echo "selected";?>>Past/Previous Events</option>
				<option value="" <? if (!$viewPastEvents) echo "selected"?>>Upcoming Events</option>
			</select>
		</form>
	</div>
	<div style="clear: both;"></div><br />

<?php

    //$tblEvent->omitPrimaryKey();
    #Create custom display fields
	$tblEvent->displayAs("pkEventID","Event ID");
	$tblEvent->displayAs("fldTitle","Title");
    $tblEvent->displayAs("fldDate", "Date");
	$tblEvent->displayAs("fldTime", "Time");
	$tblEvent->displayAs("fldLocation", "Location");
	$tblEvent->displayAs("fldAdditionalInformation", "Additional Information");
	$tblEvent->displayAs("fldImage", "Image");
	$tblEvent->displayAs("fldType", "Public/Private?");

	$tblEvent->disallowDelete();

	$validValues = array("Public", "Private");
    $tblEvent->defineAllowableValues("fldType", $validValues);
    //$tblEvent->category_required['fldType'] = FALSE;

	$tblEvent->addOrderBy("ORDER BY fldDate ASC");

	#Add filter boxes (if i wanted them)
	//$tblEvent->addAjaxFilterBox("fldFirstName");
	//$tblEvent->addAjaxFilterBox("fldLastName");
	//$tblEvent->addAjaxFilterBox("fldAttending");

	$today = date("Y-m-d");
	if (!$viewPastEvents){
		#Add Where clause - only show future events
		$tblEvent->addWhereClause("WHERE (fldDate >= \"$today\") OR fldDate = \"0000-00-00\"");
		$signupText = "Sign up for Event";
		$tblEvent->setFileUpload("fldImage", "uploads/", "uploads/");
	}
	else{
		#Add Where clause - only show past events
		$tblEvent->addWhereClause("WHERE (fldDate < \"$today\") AND fldDate != \"0000-00-00\"");
		$signupText = "View Who Signed Up";
		$tblEvent->turnOffAjaxEditing();
	}

	$tblEvent->addButtonToRow($signupText, "index.php");

	$tblEvent->setTextareaHeight("fldAdditionalInformation", 70);

	#set the number of rows to display (per page)
    //$tblEvent->setLimit(25);

    $tblEvent->setAddPlaceholderText("fldDate", "YYYY-mm-dd");
    $tblEvent->setAddPlaceholderText("fldTime", "7:00pm");
    $tblEvent->setAddFieldNote("fldDate", "Make sure to enter in format <b>YYYY-mm-dd</b>.");
    $tblEvent->setAddFieldNote("fldImage", "If you want to add an image to the event (optional).");
    $tblEvent->setAddFieldNote("fldType", "Public means anyone can come; Private means you want this event to be exclusive.");

	//$tblEvent->addButtonToRowWindowOpen = "same"; //this is default behavior so i have it commented out

	$tblEvent->formatFieldWithFunction("fldDate", "highlightDate");
	$tblEvent->formatFieldWithFunction("fldImage", "showImage");

	$tblEvent->modifyFieldWithClass("fldDate", "datepicker");

	$tblEvent->showTable();

	include ('footer.php');

	function highlightDate($date){
		if ($date == "0000-00-00") return "NOT YET SET";

		if ($date == date("Y-m-d")){
			return $date . "<br /><b style='font-size:8px;'>(TODAY/TONIGHT!)</b>";
		}
		return $date;
	}

	function showImage($image){
		if ($image){
			return "<img src=\"uploads/$image\" width=\"100\" \>\n";
		}
		return "";
	}

?>