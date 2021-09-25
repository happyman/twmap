<?php
  session_start();
  //https://stackoverflow.com/questions/12597176/how-to-keep-session-alive-without-reloading-page

  if (isset($_SESSION['uid'])) {
    $_SESSION['uid'] = $_SESSION['uid'];
    $_SESSION['mylogin'] = $_SESSION['mylogin'];
    if (empty($_SESSION['mylogin']['email'])) {
        header("Location: logout.php");
        echo "FAIL";
    }
    else
        echo "Hello " . $_SESSION['mylogin']['email'];
  } else {
        echo "FAIL";
}
