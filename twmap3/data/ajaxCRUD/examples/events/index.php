<?php

	require_once('../../preheader.php');
	$eventID = $_REQUEST['pkEventID'];

	$idsAdded = array(); //event ids are pushed onto this array by onAddCallBackFunction

	if ($eventID){
		$eventInfo = qr("SELECT pkEventID, fldTitle, fldDate, fldTime, fldLocation, fldAdditionalInformation, fldImage, fldType FROM tblEvent WHERE pkEventID = $eventID");
		if ($eventInfo){
			extract($eventInfo);
			$fldTitle 					= stripslashes($fldTitle);
			$fldLocation 				= stripslashes($fldLocation);
			$fldAdditionalInformation 	= stripslashes($fldAdditionalInformation);

			if ($fldDate != "0000-00-00"){
				$eventDate = date("l, m/d/Y", strtotime($fldDate));
			}
			else{
				$eventDate = "Not Specified";
			}
			if ($fldDate != "0000-00-00" && (strtotime($fldDate . " " . $fldTime) < time()) ){
				$eventExpired = true;
			}
		}
		else{
			header("Location: events.php");
		}
	}

	$page_title = "Signup for Event: $fldTitle @ $fldLocation";

	#the code for the class
	include ('../../ajaxCRUD.class.php');

	if (!$eventID){
		header("Location: events.php");
	}

    #Create an instance of the class
    $tblEventAttendee = new ajaxCRUD("Person", "tblEventAttendee","pkAttendeeID", "../ajaxcrud/");
    $tblEventAttendee->doActionOnShowTable = false; //this ensures showTable() does not call doAction; i do not want to do this because my onAdd callback function creates a cookie
    $tblEventAttendee->omitPrimaryKey();

    #Create custom display fields
	//$tblEventAttendee->displayAs("pkAttendeeID","User ID");
	$tblEventAttendee->displayAs("fldFirstName","First Name");
    $tblEventAttendee->displayAs("fldLastName", "Last Name");
	$tblEventAttendee->displayAs("fldPhone", "Phone #");
	$tblEventAttendee->displayAs("fldWillBeLate", "Will You Be Late?");
	$tblEventAttendee->displayAs("fldTimeArriving", "Arrival Time");
	$tblEventAttendee->displayAs("fldComments", "Comments/Other");

	$tblEventAttendee->displayAs("fldIPAddress", "IPAddress");
	$tblEventAttendee->omitFieldCompletely("fldIPAddress");
	$tblEventAttendee->omitFieldCompletely("fkEventID");
	$tblEventAttendee->omitFieldCompletely("fldPhone");
	$tblEventAttendee->omitFieldCompletely("fldAttending");

	$tblEventAttendee->validateDeleteWithFunction("canRowBeModifiedOrDeleted");
	$tblEventAttendee->validateUpdateWithFunction("canRowBeModifiedOrDeleted");

	$tblEventAttendee->defineCheckbox("fldWillBeLate");
	$tblEventAttendee->addOrderBy("ORDER BY fldFirstName ASC");

	#Add WHERE clause so we only display information specific to the event that was selected
	$tblEventAttendee->addWhereClause("WHERE fkEventID = $eventID");

	#I could add filter boxes to the top, but have chosen not to do so; commenting these out
	//$tblEventAttendee->addAjaxFilterBox("fldFirstName");
	//$tblEventAttendee->addAjaxFilterBox("fldLastName");
	//$tblEventAttendee->addAjaxFilterBox("fldAttending");

	//using addValueOnInsert so when someone signs up it will automatically add them to this event
	$tblEventAttendee->addValueOnInsert("fkEventID", $eventID);

	//using addValueOnInsert to track their IP address
	$tblEventAttendee->addValueOnInsert("fldIPAddress", $_SERVER["REMOTE_ADDR"]);

	$tblEventAttendee->setTextboxWidth("fldTimeArriving", 8);
	$tblEventAttendee->setTextboxWidth("fldLastName", 25);
	$tblEventAttendee->setTextareaHeight("fldComments", 70); //comment field should be a bit wider height

	#set the number of rows to display (per page)
    $tblEventAttendee->setLimit(25); //after 25 people, the resultset will paginate

    $tblEventAttendee->setAddPlaceholderText("fldTimeArriving", $fldTime);
    $tblEventAttendee->setAddFieldNote("fldTimeArriving", "Leave blank if you'll be on time.");
    $tblEventAttendee->setAddFieldNote("fldWillBeLate", "If you will be arriving late, please enter the time in the next box.");

	$tblEventAttendee->modifyFieldWithClass("fldTimeArriving", "time");
	$tblEventAttendee->modifyFieldWithClass("fldLastName", "required");
	$tblEventAttendee->modifyFieldWithClass("fldFirstName", "required");

	$tblEventAttendee->formatFieldWithFunction("fldWillBeLate", "displayWillBeLate");
	$tblEventAttendee->formatFieldWithFunctionAdvanced("fldTimeArriving", "displayArrivalTime");

	$tblEventAttendee->emptyTableMessage = "No one has signed up yet! Press the '<b>Add Person</b>' button below to sign up for this event.";
	$tblEventAttendee->addMessage = "You have been added to this event.";
	$tblEventAttendee->setCSSFile("cuscosky.css"); //i liked this table feel; but any css can be used from http://icant.co.uk/csstablegallery

	#implement a callback function on signing up to create cookie
	$tblEventAttendee->onAddExecuteCallBackFunction("onAddCallBackFunction");

	//if event expired, do not allow people to sign up for it or change who had attended
	if ($eventExpired){
		$tblEventAttendee->disallowAdd();
		$tblEventAttendee->disallowDelete();
		$tblEventAttendee->turnOffAjaxEditing();
		$tblEventAttendee->omitFieldCompletely("fldWillBeLate");
		$tblEventAttendee->omitFieldCompletely("fldTimeArriving");
	}

	//this needs to be very low in the excution path to ensure the obj is loaded correctly with every other piece of logic
	//typically doAction is called within the showTable() method, but i do not want to do that because i modify headers with my onAdd callback function
	if (isset($_REQUEST['action']) && $_REQUEST['action'] != ''){
		$tblEventAttendee->doAction($_REQUEST['action']);
	}

	include ('header.php');

	if ($eventExpired){
		displayExpiration();
	}

	echo "<h3><u>$fldType</u> Event: $fldTitle - " . date("M d, Y", strtotime($fldDate)) . " @ $fldTime</h3>\n";
	if ($fldType == "Public"){
		echo "<div class=\"highlight\"><img src='youre-invited.png' width='150' hspace='10' align='left' /><center style='color: blue;font-size: 14px'><i>YOU'RE</i> invited! Who me? Yes, you! Can friends come?? YES!! All are welcome. <br />This is a <b><i>PUBLIC</i></b> event. You are welcome to join. Don't feel an invitation is required. Just sign up below to let us know you'll be there. :-)</center></div><br />";
	}

    echo "<div id='container' style=\"text-align:center; font-size:13px; width: 90%; margin: auto; text-align:center;\">";

	$marginLeft = "30%;";
    if ($fldImage){
    	echo "<div id='leftDiv' style='width: 35%; float: left;'><img src=\"uploads/$fldImage\" width=\"200px\" class=\"imagedropshadow\"></div>";
    	$marginLeft = "5px;";
    }

    echo "<div id='rightDiv' style='width: 60%; float: left;margin-left: {$marginLeft};'>";
    echo "<table style='border: 1px solid #eee; font-family:\"Arial Narrow\",\"sans-serif\";'>
    		<tr>
    			<td style='background:#1F497D; color:white'>What</td>
    			<td>*<b>$fldType</b> Event: $fldTitle</td>
    		</tr>
    		<tr>
    			<td style='background:#1F497D; color:white'>When</td>
    			<td>$eventDate @ $fldTime</td>
    		</tr>
    		<tr>
    			<td style='background:#1F497D; color:white'>Where</td>
    			<td>$fldLocation</td>
    		</tr>
    		<tr>
    			<td style='background:#1F497D; color:white'>Additional Info</td>
    			<td>" . hyperlinkText($fldAdditionalInformation) . "</td>
    		</tr>

    	    </table>";
    echo "</div>\n";
	echo "</div><br /><br /><div style='clear: both;'></div><br />\n";

	$today = date("Y-m-d");
	$events = q("SELECT pkEventID, fldDate, fldTitle FROM tblEvent WHERE fldDate >= \"$today\" ORDER BY fldDate DESC");

	if (count($events) > 0){?>

	<div style="float: right">
		Upcoming Public Events:
		<form name="filterForm" id="filterForm" method="get" style="display: inline;" action="<?=$_SERVER['PHP_SELF']?>">
			<select name="pkEventID" onchange="document.getElementById('filterForm').submit();">
				<option value="">=======Choose Upcoming Event=======</option>
				<?
				$today = date("Y-m-d");
				$events = q("SELECT pkEventID, fldDate, fldTitle FROM tblEvent WHERE fldDate >= \"$today\" AND fldType = \"Public\" ORDER BY fldDate ASC");
				foreach ($events as $event){
					$selected = "";
					$thisEventID = $event['pkEventID'];
					$thisEventDate = date("l m/d", strtotime($event['fldDate']));
					$event = stripslashes($event['fldTitle']);
					if ($thisEventID == $eventID) $selected = " selected";
					echo "<option $selected value=\"$thisEventID\">$event ($thisEventDate)</option>\n";
				}
				?>
			</select>
		</form>
	</div>
	<div style="clear: both;"></div><br />


<?
	}//endif

	echo "<p>Who's coming?? If you are, please add yourself to the list by pressing \"Add Person\" below</p>\n";

	$tblEventAttendee->showTable();

	if ($eventExpired){
		displayExpiration();
	}

	include ('footer.php');


	function onAddCallBackFunction($array){
		global $idsAdded, $report_msg, $error_msg;

		$newID = $array['pkAttendeeID'];
		$fkEventID = $array['fkEventID'];

		$firstName 	= $array['fldFirstName'];
		$lastName 	= $array['fldLastName'];

		$countMatchingRows = q1("SELECT COUNT(*) FROM tblEventAttendee WHERE fldFirstName = \"$firstName\" AND fldLastName = \"$lastName\" AND fkEventID = $fkEventID");

		if ($countMatchingRows > 1){
			$success = qr("DELETE FROM tblEventAttendee WHERE pkAttendeeID = $newID");
			if ($success){
				$error_msg[] = "Ignore that last message...it seems you have added yourself TWICE! You idiot! The system automatically deleted your second entry.";
				return;
			}
			else{
				$error_msg[] = "Error deleting the second database entry.";
				return;
			}
		}

		$idsAdded[] = $newID; //array push

		//set cookie 10 years in the future
		setcookie(
		  "ajaxCRUDEventID_$newID",
		  "cookieset",
		  time() + (10 * 365 * 24 * 60 * 60)
		);

		//print_r($_COOKIE);
	}

	function canRowBeModifiedOrDeleted($id){
		global $idsAdded;

		$ip = q1("SELECT fldIPAddress FROM tblEventAttendee WHERE pkAttendeeID = $id");

		if (in_array($id, $idsAdded)){
			return true;
		}

		//check to see if cookie is set for signing up to this event
		if (isset($_COOKIE['ajaxCRUDEventID_' . $id]) && $_COOKIE['ajaxCRUDEventID_' . $id]  != ""){
			return true;
		}
		//if the ip address is the same most likely it's the person to signed up (will not always work if user is behind a proxy server)
		if ($ip == $_SERVER["REMOTE_ADDR"]){
			return true;
		}

		return false;
	}

	//change display of ints to YES/NO values
	function displayWillBeLate($val){
		if ($val == 1){
			return "Yes";
		}
		return "No";
	}

	//display how null/empty values for the late field are shown
	function displayArrivalTime($val, $id){
		$late = q1("SELECT fldWillBeLate FROM tblEventAttendee WHERE pkAttendeeID = $id");

		if ($val == "" && $late == 0){
			return "On Time";
		}
		else if ($val == "" && $late == 1){
			return "Late";
		}

		//if jokers entered time in 24-hour format, convert time to 12-hour format (just because we can)
		$time_in_12_hour_format = date("g:ia", strtotime($val));
		return $time_in_12_hour_format;
	}

	//display text urls in the description as active links
	function hyperlinkText($text) {

		if (!$text) return "N/A";

		//$text = displayBlank($text);

      	//Not perfect but it works - please help improve it.
        $text=preg_replace('/([^(\'|")]((f|ht){1}tp(s?):\/\/)[-a-zA-Z0-9@:%_\+.~#?&;\/\/=]+[^(\'|")])/','<a href="\\1" target="_blank">\\1</a>', $text);
        $text=preg_replace("/(^|[ \\n\\r\\t])([^('|\")]www\.([a-zA-Z0-9_-]+(\.[a-zA-Z0-9_-]+)+)(\/[^\/ \\n\\r]*)*[^('|\")])/", '\\1<a href="http://\\2" target="_blank">\\2</a>', $text);
        $text=preg_replace("/(^|[ \\n\\r\\t])([^('|\")][_\.0-9a-z-]+@([0-9a-z][0-9a-z-]+\.)+[a-z]{2,4}[^('|\")])/",'\\1<a href="mailto:\\2" target="_blank">\\2</a>', $text);

        return $text;
	}

	function displayExpiration(){
		echo "<center><blink><h1 style='color: red;'>!! THIS EVENT HAS EXPIRED !! IT IS OVER ... IN THE PAST ... DONE ... COMPLETE ... NO MORE</h1></blink></center>\n";
	}
?>