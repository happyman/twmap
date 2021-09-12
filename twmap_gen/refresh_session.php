<?php
  session_start();
  //https://stackoverflow.com/questions/12597176/how-to-keep-session-alive-without-reloading-page

  if (isset($_SESSION['uid'])) { 
    $_SESSION['uid'] = $_SESSION['uid'];
    $_SESSION['mylogin'] = $_SESSION['mylogin'];
    echo "Hello " . $_SESSION['mylogin']['email'];
  }
