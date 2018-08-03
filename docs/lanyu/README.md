蘭嶼的堡圖位置修正

https://github.com/happyman/twmap/issues/34

在中研院尚未更新前，以下列方法製作:
steps:
 1. 將蘭嶼地區的 raster image 下載回來
 2. georeference 到正確的位置
 3. 重新製作 map tiles
 4. publish 到地圖產生器. 


detail steps:
 1. 使用 mobac 下載. 
   * http://mobac.sourceforge.net/
   * 在目錄的 mapsources 加入 jm20k_1904.xml
   ```
<?xml version="1.0" encoding="UTF-8"?>
<customMapSource>
        <name>堡圖1904</name>
        <minZoom>10</minZoom>
        <maxZoom>18</maxZoom>
        <tileType>png</tileType>
        <tileUpdate>IfNoneMatch</tileUpdate>
        <url>http://gis.sinica.edu.tw/tileserver/file-exists.php?img=JM20K_1904-jpg-{$z}-{$x}-{$y}</url>
        <backgroundColor>#000000</backgroundColor>
</customMapSource>
```
  * jm20k_1921.xml
   ```
<?xml version="1.0" encoding="UTF-8"?>
<customMapSource>
        <name>堡圖1921</name>
        <minZoom>10</minZoom>
        <maxZoom>18</maxZoom>
        <tileType>png</tileType>
        <tileUpdate>IfNoneMatch</tileUpdate>
        <url>http://gis.sinica.edu.tw/tileserver/file-exists.php?img=JM20K_1921-jpg-{$z}-{$x}-{$y}</url>
        <backgroundColor>#000000</backgroundColor>
</customMapSource>
```   
   * settings.xml 改一下 user agent string
   * 使用 PNG + Wordfile，縮放比例 16, 下載圖片
  2. 使用 qgis georeferencer plugin 做 georeference
   * 紅頭山、大森山、尖禿山 作為參考點
    ![image](https://user-images.githubusercontent.com/82296/43627108-7b59b8c4-9727-11e8-9761-51d3361ad3c3.png)
   * 產生 tif 檔案
  3. 使用 gdal2tiles.py 產生 image tile dir
   * 參考: https://wiki.openstreetmap.org/wiki/GDAL2Tiles
   * ubuntu 有 warnning: https://gis.stackexchange.com/questions/162767/gdal2tiles-py-gives-error-6-about-epsg900913-on-fresh-ubuntu-14-04-install 
   * 指令:
```
    gdal2tiles.py --profile=mercator -z 10-18 lanyu1921.tif lanyu1921
    gdal2tiles.py --profile=mercator -z 10-18 lanyu1904.tif lanyu1904
``` 
   * 將目錄搬到 web directory

  4. 使用必須在 gettileurl 修改一下. 
      原理是將 google maps api 的 xyz -> tile bound, 跟蘭嶼範圍 intersect 一下，如果在範圍內
      就改吐新的 tile.
```
   getTileUrl: function(tile,zoom) {
	    var lULP = new google.maps.Point(tile.x*256,(tile.y+1)*256);
            var lLRP = new google.maps.Point((tile.x+1)*256,tile.y*256);

            var projectionMap = new MercatorProjection();

            var lULg = projectionMap.fromDivPixelToLatLng(lULP, zoom);
            var lLRg  = projectionMap.fromDivPixelToLatLng(lLRP, zoom);
			var tileBounds = new google.maps.LatLngBounds(lULg, lLRg);
			
			if (tileBounds.intersects(LY_Bounds)){
				var y_tms = (1 << zoom) - tile.y - 1;
				return "/~mountain/lanyu1924/"+ zoom+"/"+tile.x+"/"+y_tms+".png";
			}
			return "//gis.sinica.edu.tw/tileserver/file-exists.php?img=JM20K_1921-jpg-"+zoom+"-"+ tile.x + "-" + tile.y;
    },
```  
    其中  MercatorProjection() 可在 js/functions.js 找到.
