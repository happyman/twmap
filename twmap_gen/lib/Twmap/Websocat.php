<?php
Namespace Happyman\Twmap;

class Websocat {
    // websocket client: https://github.com/vi/websocat
    // cmd_make will persist port 
    static function notify_web_nc($msg,$port){
        $cmd = sprintf("/usr/bin/echo -n %s |nc 127.0.0.1 %d",escapeshellarg($msg),$port);
        exec($cmd);
    }
    static function notify_web($channel,$msg,$logurl_prefix="ws://twmap:9002/twmap_",$reuse_port=0,$debug=0){
        if ($reuse_port != 0)
            $cmd = sprintf("/usr/bin/echo -n %s |nc 127.0.0.1 %d",escapeshellarg($msg),$reuse_port);
        else
            $cmd = sprintf("/usr/bin/echo '%s' |base64 -d | /usr/bin/websocat --no-line -1 -t -  %s%s",base64_encode($msg_array[0]),$logurl_prefix,$channel);
        if ($debug == 1)
            echo "$cmd\n";
        exec($cmd);
    }
    static function persist($logurl_prefix,$log_channel){
        $port=self::find_free_port();
        $cmd = sprintf("websocat -t -1 -u tcp-l:127.0.0.1:%d reuse-raw:%s%s  >/dev/null 2>&1 & echo $!",$port,$logurl_prefix,$log_channel);
        $pid = exec($cmd,$output);
        return [$pid,$port];
    }
    static function find_free_port() {
        $sock = socket_create_listen(0);
        socket_getsockname($sock, $addr, $port);
        socket_close($sock);
        return $port;
    }
}
