#!/bin/sh

for n in 21 22 23 24 25 ; do
    for e in 119 120 121 ;do
        NAME=N${n}E${e}
            zip ${NAME}.hgt.zip ${NAME}.HGT
    done
done

