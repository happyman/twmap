<?php

	//for more documentation go to http://ajaxcrud.com/api/index.php?id=ajaxcrud_where_clause

	session_start(); //i need in order to access session variables

	echo "<h2>Current WHERE Clauses in SESSION</h2><hr /<br /><br />\n";

	if (is_array($_SESSION['ajaxcrud_where_clause']) && isset($_SESSION['ajaxcrud_where_clause'])){
		$countWhereClauses = count($_SESSION['ajaxcrud_where_clause']);
		echo "<p>Number of WHERE Clauses in session: $countWhereClauses</p>";
		if ($countWhereClauses > 0){
			foreach ($_SESSION['ajaxcrud_where_clause'] as $table => $whereClause){
				echo "&nbsp;&nbsp;Table: <b>$table</b> | WHERE Clause: <i>$whereClause</i><br />";
			}
		}//if any where clauses exist
	}//if any where clauses are
	else{
		echo "<p>No WHERE clauses set by any ajaxCRUD table instances (yet); try going into an example and typing in a filter box; then come back and refresh this page.</p>\n";
	}
?>
<br /><hr /><br />

<p><i>What the hell is the point of this?</i><br /> This variable is really just used for advanced/special use cases; read doumentation here: <a href="http://ajaxcrud.com/api/index.php?id=ajaxcrud_where_clause">http://ajaxcrud.com/api/index.php?id=ajaxcrud_where_clause</a><p>