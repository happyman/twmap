#!/bin/sh
echo wrap
gdalwarp  -wt Float32 -ot Float32  -co BIGTIFF=YES -co TILED=YES -co COMPRESS=LZW -co PREDICTOR=2 -t_srs "+proj=merc +ellps=sphere +R=6378137 +a=6378137 +units=m" -r bilinear -tr 10 10 raw.tiff wraped.tif
# gdalwarp -ts 1600 0 -r cubic -co "TFW=YES" wraped.tif wraped1.tif
echo hillshade
gdaldem hillshade -z 2 wraped.tif hillshade.tif
echo transparent
gdaldem color-relief hillshade.tif -alpha shade.rmap hillshade-overlay.tif

echo mv hillshade-overlay.tif /var/www/etc
