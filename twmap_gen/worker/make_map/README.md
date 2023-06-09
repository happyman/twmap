## make_map queue
1. runs with twmap_gen site, make_map_local.php
2. runs on remote using docker: make_map.php
3. stats.php to show queue status

Note: job can only be "release" or "delete" by the client who "reserve" this job.
    
```
php stats.php -d

Array
(
    [name] => make_map
    [current-jobs-urgent] => 0
    [current-jobs-ready] => 0
    [current-jobs-reserved] => 1
    [current-jobs-delayed] => 0
    [current-jobs-buried] => 0
    [total-jobs] => 120
    [current-using] => 5
    [current-waiting] => 4
    [current-watching] => 5
    [pause] => 0
    [cmd-delete] => 119
    [cmd-pause-tube] => 0
    [pause-time-left] => 0
)
目前進行中: 1
已進行: 120
已完成: 119
工人數: 5
未完成(57182): 163 -r 25:121:10:7:TWD97 -O /home/happyman/twmapcache/tmp/out/070976 -v 2016 -t '瑞芳' -i 127.0.0.1 -p 0  -m /dev/shm -l 8af8c4c45716624d844789a0a0660435 -e -G -c -3 -a https://twmap.happyman.idv.tw/gen/api/made.php
Array
(
    [id] => 163
    [tube] => make_map
    [state] => reserved
    [pri] => 1024
    [age] => 57182
    [delay] => 0
    [ttr] => 3600
    [time-left] => 772
    [file] => 1
    [reserves] => 18
    [timeouts] => 0
    [releases] => 0
    [buries] => 0
    [kicks] => 0
)
