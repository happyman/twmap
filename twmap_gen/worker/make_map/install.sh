#!/bin/bash

out=/home/happyman/make_map

echo "# do the follow commands "
mkdir -p $out

echo install checkmakemap.sh *.php vendor $out
rsync -av checkmakemap.sh *.php vendor $out
echo cd $out
echo "ln -s make_map_local.php make_map01.php"
echo "ln -s make_map_local.php make_map02.php"
echo "ln -s make_make_local_dev.php make_make03.php"
echo "#"
echo "ln -s make_map.php make_map01.php"
echo "ln -s make_map.php make_map02.php"


echo "cd "$out
echo "cp config.inc.sample.php config.ini"
echo "# and modify config params"
echo "./checkmakemap.sh"
