#!/bin/bash
#!/bin/sh

BASEDIR=$(dirname $0)
cd $BASEDIR; cd ..
# pwd
sudo -u www-data -b php api_make.php > /dev/null

