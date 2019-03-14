geojsontoosm
============

Converts [GeoJSON](http://www.geojson.org/) to [OSM](http://openstreetmap.org) [data](http://wiki.openstreetmap.org/wiki/OSM_XML).

Usage
-----

* as a **command line tool**:
  
        $ npm install -g geojsontoosm
        $ geojsontoosm file.geojson > file.osm
  
* as a **nodejs library**:
  
        $ npm install geojsontoosm
  
        var geojsontoosm = require('geojsontoosm');
        geojsontoosm(geojson_data);