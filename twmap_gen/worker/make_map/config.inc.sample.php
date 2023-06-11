<?php
define('__ROOT__', dirname(__FILE__). "/");
require_once(__ROOT__."vendor/autoload.php");

$CONFIG['beanstalk_server'] = 'twmap';
$CONFIG['beanstalk_port'] = '11300';
$CONFIG['dev'] = true;

$CONFIG['beanstalk_tube'] = 'make_map';
$CONFIG['cmd_make_local'] = '/home/happyman/projects/twmap_prod/dist/twmap_gen/cmd_make2.php';
/* 開發使用不同的 worker queue, 但是因為想與 production worker 並存  */
$CONFIG['beanstalk_tube_dev'] = 'make_map_dev';
$CONFIG['cmd_make_local_dev'] = '/home/happyman/projects/twmap/dist/twmap_gen/cmd_make2.php';

$CONFIG['logurl_prefix'] = 'ws://twmap:9002/twmap_';
$CONFIG['docker_ver'] = 'latest';
$CONFIG['agent'] = 'nuk';

