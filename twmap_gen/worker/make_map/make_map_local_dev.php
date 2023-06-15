<?php
require_once('config.ini');
use xobotyi\beansclient\Client as beansclient;
use xobotyi\beansclient\Socket\SocketsSocket as beansconnect;

$sock   = new beansconnect(host: $CONFIG['beanstalk_server'], port: $CONFIG['beanstalk_port'], connectionTimeout: 2);
$client = new beansclient(socket: $sock, defaultTube: $CONFIG['beanstalk_tube_dev']);

while(1){
	printf("%s waiting for job\n", $CONFIG['agent']);
	$job = $client->reserve();
	$params = $job['payload'];
	// replace callback
	$cmd = sprintf("/usr/bin/php %s %s --agent local --logurl_prefix '%s' --logfile '/tmp/%s.log'",
			$CONFIG['cmd_make_local_dev'], $params,$CONFIG['logurl_prefix'] ,basename($argv[0]));
	system($cmd, $ret);
	if ($ret==0)
		$client->delete($job['id']);
	else {
		$client->release($job['id']);
		sleep(10);
	}
	error_log("return $ret");
}
