# 
# 2021.12  #58
import requests

url = "https://coverage.cht.com.tw/coverage/jss/mobile/mEmbr.json"
r= requests.get(url)
r.encoding = "utf-8-sig"
msg = str(r.text)

import json
data = json.loads(msg)
ret = {}
for k in data.keys():
    print(k)
    kimg = k.replace("4+","")
    ret[kimg] = { "bound": { 
    "north": data[k]["ulat"],
    "east": data[k]["ulon"],
    "south": data[k]["llat"], 
    "west":data[k]["llon"] },
    "img": "https://coverage.cht.com.tw/coverage/images/mobile/%s.png" % kimg }
#print(ret)

ret1 = {}
ret1['cht'] = [] 
for k in ret.keys():
    if k.startswith("5G"):
        ret1['cht'].append(ret[k])
ret1['cht3G'] = [] 
for k in ret.keys():
    if k.startswith("3G"):
        ret1['cht3G'].append(ret[k])

print(ret1)