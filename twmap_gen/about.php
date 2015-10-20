<?php
if (!$_SESSION)
	session_start();
if (empty($_SESSION['loggedin'])) {
	header("Location: login.php");
	exit(0);
}


require_once("config.inc.php");

$smarty->assign("twmap_gen_version", $twmap_gen_version);

list ($total_maps, $total_size, $all_users, $active_users, $created)=stats();
$smarty->assign("size", humanreadable($total_size) );
$smarty->assign("all_users", $all_users );
$smarty->assign("created", $created );
$smarty->assign("total_maps", $total_maps );
$smarty->assign("active_users", $active_users );
$smarty->assign("avg_maps", floor($total_maps / $active_users ) );

$smarty->display("about.html");
