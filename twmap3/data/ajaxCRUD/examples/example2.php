<?php

	require_once('../preheader.php');
	include ('../ajaxCRUD.class.php');
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<?
    $tblDemo = new ajaxCRUD("Item", "tblDemo", "pkID");
    $tblDemo->omitPrimaryKey();
    $tblDemo->displayAs("fldField1", "Field1");
    $tblDemo->displayAs("fldField2", "Field2");
    $tblDemo->displayAs("fldCertainFields", "Certain Fields");
    $tblDemo->displayAs("fldLongField", "Long Field");
    $tblDemo->setTextareaHeight('fldLongField', 200);

    $allowableValues = array("Allowable Value 1", "Allowable Value2", "Dropdown Value", "CRUD");
    $tblDemo->defineAllowableValues("fldCertainFields", $allowableValues);

    $tblDemo->setLimit(3);
    $tblDemo->addAjaxFilterBox('fldField1');
	$tblDemo->formatFieldWithFunction('fldField1', 'makeBlue');
	$tblDemo->formatFieldWithFunction('fldField2', 'makeBold');
	$tblDemo->showTable();

    $tblDemo2 = new ajaxCRUD("Item", "tblDemo2", "pkID");
    $tblDemo2->omitPrimaryKey();
    $tblDemo2->displayAs("fldField1", "Field1");
    $tblDemo2->displayAs("fldField2", "Field2");
    $tblDemo2->displayAs("fldCertainFields", "Certain Fields");
    $tblDemo2->displayAs("fldLongField", "Long Field");
    $tblDemo2->setTextareaHeight('fldLongField', 200);
    $tblDemo2->setLimit(20);
    $tblDemo2->addAjaxFilterBox('fldField1');
	$tblDemo2->formatFieldWithFunction('fldField2', 'makeBlue');
	$tblDemo2->formatFieldWithFunction('fldField1', 'makeBold');
	$tblDemo2->showTable();


	function makeBold($val){
		return "<b>$val</b>";
	}

	function makeBlue($val){
		return "$val";
	}

?>