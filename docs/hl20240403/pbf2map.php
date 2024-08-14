<?php
$dd="./eq";
exec("mkdir -p $dd");

$finalmap="$dd/HL.map";
$finalpbf="./HL.pbf";
$cmt="Rock Slides after 202404 earthquake";
$log="$dd/HL.log";

$mapping_xml = "/home/happyman/projects/taiwan-topo/osm_scripts/gpx-mapping.xml";
$osmosis_bin = "/home/happyman/projects/taiwan-topo/tools/osmosis-0.48.3/bin/osmosis";
echo "finally...\n";
if (!file_exists($finalmap)){
    $cmd = sprintf("export JAVACMD_OPTIONS=\"-Xmx30G\";
            unbuffer $osmosis_bin \
            --read-pbf \"%s\" \
            --buffer --mapfile-writer \
            type=ram \
            threads=8 \
            bbox=21.55682,118.12141,26.44212,122.31377 \
            preferred-languages=\"zh,en\" \
            tag-conf-file=\"%s\" \
            polygon-clipping=true way-clipping=true label-position=false \
            zoom-interval-conf=6,0,6,10,7,11,14,12,21 \
            map-start-zoom=10 \
            comment=\"%s /  (c) Map: Happyman\" \
            file=\"%s\" > %s 2>&1 &",
            $finalpbf, $mapping_xml, $cmt, $finalmap , $log);

    // not yet
    echo $cmd;
    $pid = exec($cmd);
    echo "process in background...\n";
    while(1) {
        //system("tail -1 $log");
        exec(sprintf("fgrep \"finished...\" %s",$log),$out,$ret);
        if ($ret == 0 ) {
            exec("ps ax |grep osmosis |grep java |awk '{print $1}' |xargs kill");
            echo "done...\n";
            break;
        }
        sleep(10);
    }
}
