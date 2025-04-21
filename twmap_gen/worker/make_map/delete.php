<?php
require_once('config.ini');
use xobotyi\beansclient\Client as beansclient;
use xobotyi\beansclient\Socket\SocketsSocket as beansconnect;

$sock   = new beansconnect(host: $CONFIG['beanstalk_server'], port: $CONFIG['beanstalk_port'], connectionTimeout: 2);
$client = new beansclient(socket: $sock, defaultTube: $CONFIG['beanstalk_tube']);

$client->delete($argv[1]);
