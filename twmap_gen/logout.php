<?php
// $Id: logout.php 302 2012-10-29 08:18:22Z happyman $
session_start([
   //     'read_and_close' => true,
]);
require_once("config.inc.php");
$_SESSION = array();
$_SESSION['loggedin']=false;
session_destroy();
session_commit();
if (isset($_GET['return']) && $_GET['return'] == 'twmap3' ) {
		//$_SESSION['redirto'] = $TWMAP3URL;
      $nexturl = $TWMAP3URL;
	} else{
      $nexturl = "login.php";
   }
?>
<html>
<head>
<meta http-equiv="REFRESH" content="1;url=<?php echo $nexturl; ?>">
<title>Log Out</title>
</head>
<body>
Logout...
</body>
