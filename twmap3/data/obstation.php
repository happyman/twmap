<?php

// <a href=/V7/observe/real/NewObs.htm?stationID=46694&idx=10017#ui-tabs-1 

// actual url 
$url = sprintf("http://www.cwb.gov.tw/V7/observe/real/%s.htm",$_REQUEST['stationID']);
header("Location: $url");
