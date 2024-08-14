# 花蓮地震圖層 hl20240403 製作 Note 20240813

1. 原始檔案 gpx 匯入 Qgis 3.16, 只取tracks

1. 將圖層匯出成 geojson, (Optional: Vector->Geometry Tools->Line to polygon )
    
1. 使用 exportpbf 的 geojsontoosm 轉換成 osm
    ```
    geojsontoosm HL3.geojson > HL.osm
    ```
1. osm 裡面的 description 太長, strip 掉(CludeAI 幫忙寫的)
    ```
    python rmdesc.py 產生 HL_ref.osm
        
    ```
1. 將 osm 轉成 pbf
   ```
   osmium sort --overwrite HL_ref.osm -o HL.pbf
   ```
1. 將 pbf 轉成 
   ```
   pbf2map.php 
   ```
1. 使用 mapsforgesrv 將 tile serve 出去
   ```
   #!/bin/bash
   export JAVA_FONTS=/usr/share/fonts/truetype/msttcorefonts/
    mapsforgesrv_bin="java -Xmx512m -jar /home/happyman/mapsforge/mapsforgesrv-fatjar.jar"
    cd /home/happyman/mapsforge  
   exec setuidgid happyman $mapsforgesrv_bin -m HL.map -t HL.xml -o elmt-track,elmt-waymarks,elmt-waypoint -p 8994
   ```
   其中 HL.xml 是直接複製 MOI_OSM_twmap.xml 只改 gpx trk 的 <rule e="way" k="color" v="~"> 的顏色

1. tilestache 做 proxy, 記得 transparent=true, nginx 的 reverse proxy 設定


1. 感謝東華大學張光承提供 原始檔案
