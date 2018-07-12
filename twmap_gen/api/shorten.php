<?php

$url=$_GET['url'];
if (!empty($url)) {
	$shorten = file_get_contents("http://tinyurl.com/api-create.php?url=".urlencode($url));

}
if (!empty($shorten)){
	echo $shorten;
} else {
	echo $url;
}
