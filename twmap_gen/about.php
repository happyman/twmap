<?php
if (!isset($_SESSION))
	session_start([
		'read_and_close' => true,
	]);

require_once("config.inc.php");

$smarty->assign("twmap_gen_version", $twmap_gen_version);

if (!empty($_SESSION['loggedin'])){
	$smarty->assign("show_stats", 1);
	list ($total_maps, $total_size, $all_users, $active_users, $created)=stats();
	$smarty->assign("size", humanreadable($total_size) );
	$smarty->assign("all_users", $all_users );
	$smarty->assign("created", $created );
	$smarty->assign("total_maps", $total_maps );
	$smarty->assign("active_users", $active_users );
	$smarty->assign("avg_maps", floor($total_maps / $active_users ) );
}
$smarty->assign("server_info", implode(" ",[php_uname("s"),php_uname("n"),php_uname('m'), php_uname("r")]));

$smarty->display("about.html");
