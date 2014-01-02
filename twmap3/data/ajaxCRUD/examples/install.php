<?php

	require_once('../preheader.php');

    $success = q1("CREATE TABLE tblDemo(pkID INT PRIMARY KEY AUTO_INCREMENT,fldField1 VARCHAR(45),fldField2 VARCHAR(45),fldCertainFields VARCHAR(40),fldLongField TEXT);");

    if ($success){
        echo "TABLE <b>tblDemo</b> CREATED <br /><br />\n";
    }

	$success = q1("CREATE TABLE tblDemo2(pkID INT PRIMARY KEY AUTO_INCREMENT,fldField1 VARCHAR(45),fldField2 VARCHAR(45),fldCertainFields VARCHAR(40),fldLongField TEXT);");
    if ($success){
        echo "TABLE <b>tblDemo2</b> CREATED <br /><br />\n";
    }

    $success = qr("INSERT INTO tblDemo (fldField1, fldField2, fldCertainFields, fldLongField) VALUES (\"Testing\", \"Testing2\", \"CRUD\", \"First ajaxCRUD Test\")");
    $success = qr("INSERT INTO tblDemo2 (fldField1, fldField2, fldCertainFields, fldLongField) VALUES (\"Testing\", \"Testing2\", \"CRUD\", \"Second ajaxCRUD Test\")");

    if ($success){
        echo "Example rows entered into <b>tblDemo</b> and <b>tblDemo2</b><br /><br />\n";
    }

    echo "<p><a href='example.php'>Try out the demo</a></p>\n";
    echo "<p><a href='example.php'>Try out a demo with two ajaxCRUD tables.</a></p>\n";

?>