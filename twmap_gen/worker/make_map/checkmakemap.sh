#!/bin/bash

cd  /home/happyman/make_map
for i in $(seq 1 10);
do
ps ax |grep make_map0$i |grep -v grep|grep -v tmux > /dev/null
if [ $? -ne 0 ]; then
	if [ -f "make_map0$i.php" ]; then
		echo "create session m$i"
		tmux new-session -d -s m$i php make_map0$i.php &
	fi
fi
done
