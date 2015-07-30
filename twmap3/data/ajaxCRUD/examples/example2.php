<?php

	require_once('../preheader.php'); // <-- this include file MUST go first before any HTML/output
	include ('../ajaxCRUD.class.php'); // <-- this include file MUST go first before any HTML/output
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	</head>
<?php
    $tblDemo = new ajaxCRUD("Item", "tblDemo", "pkID", "../");
    $tblDemo->omitPrimaryKey();
    $tblDemo->displayAs("fldField1", "Field1");
    $tblDemo->displayAs("fldField2", "Field2");
    $tblDemo->displayAs("fldCertainFields", "Pick List");
    $tblDemo->displayAs("fldLongField", "Long Field");
    $tblDemo->displayAs("fldCheckbox", "Is Selected?");
    $tblDemo->setTextareaHeight('fldLongField', 100);

    $allowableValues = array("Allowable Value1", "Allowable Value2", "Dropdown Value", "CRUD");
    $tblDemo->defineAllowableValues("fldCertainFields", $allowableValues);

    //set field fldCheckbox to be a checkbox
    $tblDemo->defineCheckbox("fldCheckbox", "1", "0");

    $tblDemo->setLimit(5);
    $tblDemo->addAjaxFilterBox('fldField1');
	$tblDemo->formatFieldWithFunction('fldField1', 'makeBlue');
	$tblDemo->formatFieldWithFunction('fldField2', 'makeBold');
	echo "<h2>Table tblDemo</h2>\n";
	$tblDemo->showTable();

	echo "<br /><hr ><br />\n";

    $tblDemo2 = new ajaxCRUD("Item", "tblDemo2", "pkID");
    $tblDemo2->omitPrimaryKey();
    $tblDemo2->displayAs("fldField1", "Field1");
    $tblDemo2->displayAs("fldField2", "Field2");
    $tblDemo2->displayAs("fldCertainFields", "Color");
    $tblDemo2->displayAs("fldLongField", "Long Field");

    $allowableValues2 = array("Green", "Blue", "Red", "Periwinkle");
    $tblDemo2->defineAllowableValues("fldCertainFields", $allowableValues2);

    $tblDemo2->setTextareaHeight('fldLongField', 50);
    $tblDemo2->setLimit(20);
    $tblDemo2->addAjaxFilterBox('fldField1');
	$tblDemo2->formatFieldWithFunction('fldField2', 'makeBlue');
	$tblDemo2->formatFieldWithFunction('fldField1', 'makeBold');
	echo "<h2>Table tblDemo2</h2>\n";
	$tblDemo2->showTable();


	function makeBold($val){
		return "<b>$val</b>";
	}

	function makeBlue($val){
		return "<span style='color: blue;'>$val</span>";
	}

?>