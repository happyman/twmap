<?php
require_once('config.ini');
use xobotyi\beansclient\Client as beansclient;
use xobotyi\beansclient\Socket\SocketsSocket as beansconnect;

$sock   = new beansconnect(host: $CONFIG['beanstalk_server'], port: $CONFIG['beanstalk_port'], connectionTimeout: 2);
$client = new beansclient(socket: $sock, defaultTube: $CONFIG['beanstalk_tube']);

// check https://raw.githubusercontent.com/beanstalkd/beanstalkd/master/doc/protocol.txt
// beanstalk protocol 
//
function stats($debug=0) {
	global $CONFIG,$client;
	$r = $client->statsTube($CONFIG['beanstalk_tube']);
	if ($debug)
		print_r($r);
	/*
	 *     [name] => make_map
	 [current-jobs-urgent] => 0
	 [current-jobs-ready] => 0
	 [current-jobs-reserved] => 8
	 [current-jobs-delayed] => 0
	 [current-jobs-buried] => 0
	 [total-jobs] => 64
	 [current-using] => 5
	 [current-waiting] => 4
	 [current-watching] => 5
	 [pause] => 0
	 [cmd-delete] => 56
	 [cmd-pause-tube] => 0
	 [pause-time-left] => 0
	 */
	printf("目前進行中: %d\n",$r['current-jobs-reserved']);
	printf("已進行: %d\n",$r['total-jobs']);
	printf("已完成: %d\n",$r['cmd-delete']);
	printf("工人數: %d\n",$r['current-using']);
	$todo=$r['total-jobs']-$r['cmd-delete'];
	if ($todo > 0 ) {
		$count=0;
		$i=1;
		while(1){
			$j = $client->statsJob($i++);
			if (!is_null($j)){
				$jb = $client->peek($j['id']);
				printf("未完成(%d): %s %s\n",$j['age'],$j['id'],$jb['payload']);
				if ($debug)
					print_r($j);
				$count++;
				if ($count == $todo)
					break;
			}
		}
	}
}

$opt=getopt("d");
// main
stats(isset($opt['d']));
