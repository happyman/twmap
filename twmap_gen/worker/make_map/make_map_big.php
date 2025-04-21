<?php
require_once('config.ini');
use xobotyi\beansclient\Client as beansclient;
use xobotyi\beansclient\Socket\SocketsSocket as beansconnect;

$sock   = new beansconnect(host: $CONFIG['beanstalk_server'], port: $CONFIG['beanstalk_port'], connectionTimeout: 2);
$client = new beansclient(socket: $sock, defaultTube: $CONFIG['beanstalk_tube']);

while(1){
	printf("%s waiting for job\n", $CONFIG['agent']);
	//$job = $client->reserve();
	$job = $client->peekBuried();
	
	$params = $job['payload'];
	$opt = parseCliString($params,"O:r:v:t:i:p:g:Ges:dSl:c3m:a:D:",array("agent:","logurl_prefix:","logfile:","getopt","check"));
	// replace callback
	// list($startx,$starty,$shiftx,$shifty,$datum)=explode(":",$opt['r']);
	# local 可出大圖
	$cmd = sprintf("/usr/bin/php %s %s --agent local --logurl_prefix '%s' --logfile '/tmp/%s.log'",
			$CONFIG['cmd_make_local'], $params,$CONFIG['logurl_prefix'], basename($argv[0]) );
	system($cmd, $ret);
	if ($ret==0)
		$client->delete($job['id']);
	else {
		$client->bury($job['id']);
		sleep(5);
	}
	error_log("return $ret");
}
