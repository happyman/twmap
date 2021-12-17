import requests

url = "https://www.aptg.com.tw/coverage1/MOCN202107.kml"
r= requests.get(url)
r.encoding = "utf-8-sig"
kmldata = str(r.text)

from pykml import parser

k=parser.fromstring(kmldata.encode('utf8'))
g = (k.findall('.//{http://earth.google.com/kml/2.0}GroundOverlay'))

ret = []
for gg in g:
    ret.append({
        "img": gg.Icon.href,
        "bound": {
            "north": gg.LatLonBox.north,
            "south": gg.LatLonBox.south,
            "east": gg.LatLonBox.east,
            "west": gg.LatLonBox.west,
        }
    })

ret1 = { 'aptg5g': ret}
print(ret1)
