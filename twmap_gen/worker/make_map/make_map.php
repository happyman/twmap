<?php

require_once('config.ini');
use xobotyi\beansclient\Client as beansclient;
use xobotyi\beansclient\Socket\SocketsSocket as beansconnect;

$sock   = new beansconnect(host: $CONFIG['beanstalk_server'], port: $CONFIG['beanstalk_port'], connectionTimeout: 2);
$client = new beansclient(socket: $sock, defaultTube: $CONFIG['beanstalk_tube']);


// 使用 docker, 需要置換 -O 參數
while(1){
	printf("%s waiting for job\n", $CONFIG['agent']);
	$job = $client->reserve();
	$workload = $job['payload'];

	error_log("$id get workload: $workload");

	if (preg_match("/-O\s(\S+)\s/",$workload, $mat)){
        $outdir = $mat[1];
        @mkdir($outdir,0755, true);
        $image="happyman/docker-twmap-cli:". $CONFIG['docker_ver'];
        $cmd = sprintf("docker run --rm -i  --shm-size=5gb -v %s:/workdir -v /tmp:/tmp --user=1001:1001 %s /usr/bin/php /twmap/twmap_gen/cmd_make2.php -O /workdir %s --agent %s --logurl_prefix %s", $outdir, $image, str_replace("-O $outdir","",$workload),$CONFIG['agent'],$CONFIG['logurl_prefix']);
        error_log($cmd);
        system($cmd, $ret);
		if ($ret==0)
			$client->delete($job['id']);
		else
			$client->release($job['id']);
        error_log("return $ret");
	}
}
