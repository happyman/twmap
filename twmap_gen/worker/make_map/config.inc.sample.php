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

function parseCliString($input, $shortOpts = [], $longOpts = []) {
    $tokens = preg_split('/\s+/', $input);
    $result = [];
    $lastOpt = null;

    foreach ($tokens as $token) {
        if (preg_match('/^--([^=]+)=(.*)$/', $token, $matches)) {
            // Long option with value: --option=value
            $result[$matches[1]] = $matches[2];
        } elseif (preg_match('/^--(.+)$/', $token, $matches)) {
            // Long option without value
            $result[$matches[1]] = true;
            $lastOpt = $matches[1];
        } elseif (preg_match('/^-([a-zA-Z])$/', $token, $matches)) {
            // Short option without value: -a
            $result[$matches[1]] = true;
            $lastOpt = $matches[1];
        } elseif (preg_match('/^-([a-zA-Z])(.+)$/', $token, $matches)) {
            // Short option with value glued: -bvalue
            $result[$matches[1]] = $matches[2];
        } else {
            // If previous token was an option, treat this as its value
            if ($lastOpt !== null && $result[$lastOpt] === true) {
                $result[$lastOpt] = $token;
                $lastOpt = null;
            }
        }
    }

    return $result;
}
