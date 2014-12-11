地圖產生器 Quickstart
v0.1 2014.12.11 happyman

## 如何自行產生經建版地圖
環境:  Linux, command line interface

1. 取得 twmap code

  ```shell
  git clone https://github.com/happyman/twmap.git
  Cloning into 'twmap'...
```

2. get  map image data (全部 22GB)

  ```shell
  # v1
  curl http://rs.happyman.idv.tw/mapcache/STB.tar.a[a-c] -o STB.tar.#1
  # extract
  cat STB.tar.a  STB.tar.b  STB.tar.c |tar xvf -
  # the data is in stb/ directory
  # 下列步驟需要 20G 空間, 如果需要離線使用, 請下載
  curl http://rs.happyman.idv.tw/mapcache/cache.7z.0[01-10] -o cache.7z.#1
  # extract, the data in cache/ directory
  7z x cache.7z.01
```

3. install require packages

  ```shell
  zypper in ImageMagick proj gpsbabel wget p7zip php5-zlib curl
  # Optional:
  pngquant
  # http://pngquant.org/
  # todo: web: gdal inkscape memcache ape ..
```

4. 設定 twmap
  ```shell
  cd twmap/twmap_gen
  cp config.inc.php.sample config.inc.php
  vi config.inc.php
  # edit $stdpath
  # 一版位置
  $stdpath = "/home/happyman/map/stb";
  # 三版位置, 如果沒下載(有網路連線), 則給一個空白目錄即可
  $tilecachepath = "/home/happyman/map/cache";
  # 暫存目錄
  $tmppath = "/tmp";
```

5. 執行
  ```shell
  php cmd_make2.php
  Usage: cmd_make2.php -r 236:2514:6:4 [-g gpx:0:0] [-G]-O dir [-e] -v 1|3 -t title -i localhost
       -r params: startx:starty:shiftx:shifty
       -O outdir: /home/map/out/000003
       -v 1|3: version of map,default 3
       -t title: title of image
       -i ip: log remote address
       -p 1|0: 1 if is pong-hu
       -g gpx_fpath:trk_label:wpt_label
       -d debug
       -e draw 100M grid
       -s 1-5: stage 1: create_tag_png 2: split images 3: make simages 4: create txt/kmz 5: create pdf. debug purpose
          1 is done then go to 2, 3 ..
       -S use with -s, if -s 2 -S, means do only step 2
       -l channel:uniqid to notify web, email from web interface
  # v3
  php cmd_make2.php -r 261:2607:3:3 -O /tmp/testmap -v 3 -t '嘆息灣'
  # v1
  php cmd_make2.php -r 261:2607:3:3 -O /tmp/testmap -v 1 -p 0  -t '嘆息灣'
  # 到 /tmp/testmap 去看產生完的吧圖
```

6. 限制 (or TODO)

  ```shell
-G require gps track database, not working here.
-g gpx_file requires -r 給定 bounds. check backend_make.php for more information.
```
