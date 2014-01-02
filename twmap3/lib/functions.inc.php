<?php
require_once(dirname(dirname(__FILE__))."/config.inc.php");
function is_admin() {
	global $CONFIG;
	$admin = $CONFIG['admin'];
  if (!isset($_SESSION['mylogin'])) return false;
	if (!in_array($_SESSION['mylogin']['email'],$admin)){
		return false;
	}
	return true;
}
