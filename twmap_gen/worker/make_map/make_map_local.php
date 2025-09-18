<?php
require_once('config.ini');
use xobotyi\beansclient\Client as beansclient;
use xobotyi\beansclient\Socket\SocketsSocket as beansconnect;

$sock   = new beansconnect(host: $CONFIG['beanstalk_server'], port: $CONFIG['beanstalk_port'], connectionTimeout: 2);
$client = new beansclient(socket: $sock, defaultTube: $CONFIG['beanstalk_tube']);

while(1){
	printf("%s waiting for job\n", $CONFIG['agent']);
	$job = $client->reserve();
	$params = $job['payload'];
	$opt = parseCliString($params,"O:r:v:t:i:p:g:Ges:dSl:c3m:a:D:",array("agent:","logurl_prefix:","logfile:","getopt","check"));
	// replace callback
	list($startx,$starty,$shiftx,$shifty,$datum)=explode(":",$opt['r']);
	# 大圖就 bury 給 make_map_big 處理
	if ($shiftx < 15 && $shifty < 15){
		$cmd = sprintf("/usr/bin/php %s %s --agent local --logurl_prefix '%s' --logfile '/tmp/%s.log'",
			$CONFIG['cmd_make_local'], $params,$CONFIG['logurl_prefix'], basename($argv[0]) );
		system($cmd, $ret);
	} else {
		print_r($opt);
		$ret=20;
	}
	if ($ret==0)
		$client->delete($job['id']);
	else if ($ret == 20) {
		$client->bury($job['id']);
	}
	else {
		$client->release($job['id']);
		sleep(5);
	}
	error_log("return $ret");
}
