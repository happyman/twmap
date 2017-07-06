#!/bin/sh

for n in 21 22 23 24 25 ; do
    for e in 119 120 121 ;do 
        NAME=N${n}E${e}
        gdal_translate -of GTiff \
            -eco \
            -projwin ${e} $(($n + 1)) $(($e + 1)) $n \
            dem_20m-wgs84.tif \
            ${NAME}-src.tif

        if [ -f  N${n}E${e}-src.tif ] ; then
            gdalwarp -of GTiff -srcnodata 32767 \
                -order 3 -ts 3601 3601 -multi -r bilinear ${NAME}-src.tif ${NAME}.tif
            gdal_translate -of SRTMHGT ${NAME}.tif ${NAME}.HGT
        fi
    done
done
