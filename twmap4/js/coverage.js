const mobileCoverage = {

// https://coverage.cht.com.tw/coverage/jss/mobile/mobileMap.js
// https://coverage.cht.com.tw/coverage/jss/mobile/mEmbr.json
// 以上先 pre-process 成下面的格式
//"cht":[{"bound":{"north":25.43018,"south":24.842169,"east":122.13962,"west":120.842934},"img":"https:\/\/coverage.cht.com.tw\/coverage\/images\/mobile\/4g_tw_n1.png"},{"bound":{"north":25.029276,"south":24.343359,"east":122.051264,"west":120.538564},"img":"https:\/\/coverage.cht.com.tw\/coverage\/images\/mobile\/4g_tw_n2.png"},{"bound":{"north":24.677594,"south":23.795453,"east":122.071623,"west":120.126456},"img":"https:\/\/coverage.cht.com.tw\/coverage\/images\/mobile\/4g_tw_n3.png"},{"bound":{"north":24.228047,"south":23.34593,"east":121.875136,"west":119.929961},"img":"https:\/\/coverage.cht.com.tw\/coverage\/images\/mobile\/4g_tw_c1.png"},{"bound":{"north":23.778346,"south":22.896225,"east":121.777428,"west":119.832241},"img":"https:\/\/coverage.cht.com.tw\/coverage\/images\/mobile\/4g_tw_c2.png"},{"bound":{"north":23.349102,"south":22.515958,"east":121.674017,"west":119.836867},"img":"https:\/\/coverage.cht.com.tw\/coverage\/images\/mobile\/4g_tw_s1.png"},{"bound":{"north":22.919832,"south":22.135679,"east":121.669127,"west":119.940022},"img":"https:\/\/coverage.cht.com.tw\/coverage\/images\/mobile\/4g_tw_s2.png"},{"bound":{"north":22.372363,"south":21.784372,"east":121.747311,"west":120.450631},"img":"https:\/\/coverage.cht.com.tw\/coverage\/images\/mobile\/4g_tw_s3.png"},{"bound":{"north":26.649731,"south":25.675754,"east":120.539653,"west":119.875697},"img":"https:\/\/coverage.cht.com.tw\/coverage\/images\/mobile\/4g_li.png"},{"bound":{"north":23.829135,"south":23.155507,"east":119.750269,"west":119.291055},"img":"https:\/\/coverage.cht.com.tw\/coverage\/images\/mobile\/4g_ph.png"},{"bound":{"north":25.765986,"south":23.609424,"east":119.542326,"west":118.071174},"img":"https:\/\/coverage.cht.com.tw\/coverage\/images\/mobile\/4g_km.png"}],
//"cht3G":[{"bound":{"north":25.43018,"south":24.842178,"east":122.139592,"west":120.842909},"img":"https:\/\/coverage.cht.com.tw\/coverage\/images\/mobile\/3g_tw_n1.png"},{"bound":{"north":25.029284,"south":24.343359,"east":122.051273,"west":120.538555},"img":"https:\/\/coverage.cht.com.tw\/coverage\/images\/mobile\/3g_tw_n2.png"},{"bound":{"north":24.677597,"south":23.795474,"east":122.071777,"west":120.126585},"img":"https:\/\/coverage.cht.com.tw\/coverage\/images\/mobile\/3g_tw_n3.png"},{"bound":{"north":24.227892,"south":23.345766,"east":121.875341,"west":119.930157},"img":"https:\/\/coverage.cht.com.tw\/coverage\/images\/mobile\/3g_tw_c1.png"},{"bound":{"north":23.778187,"south":22.896059,"east":121.777333,"west":119.832157},"img":"https:\/\/coverage.cht.com.tw\/coverage\/images\/mobile\/3g_tw_c2.png"},{"bound":{"north":23.349203,"south":22.516062,"east":121.674229,"west":119.837071},"img":"https:\/\/coverage.cht.com.tw\/coverage\/images\/mobile\/3g_tw_s1.png"},{"bound":{"north":22.919834,"south":22.135668,"east":121.669168,"west":119.940435},"img":"https:\/\/coverage.cht.com.tw\/coverage\/images\/mobile\/3g_tw_s2.png"},{"bound":{"north":22.372341,"south":21.784289,"east":121.747479,"west":120.450794},"img":"https:\/\/coverage.cht.com.tw\/coverage\/images\/mobile\/3g_tw_s3.png"},{"bound":{"north":26.638896352988,"south":25.685195668379,"east":120.54003645266,"west":119.87593395},"img":"https:\/\/coverage.cht.com.tw\/coverage\/images\/mobile\/3g_li.png"},{"bound":{"north":23.82888325,"south":23.155240570044,"east":119.750629983516,"west":119.291551544694},"img":"https:\/\/coverage.cht.com.tw\/coverage\/images\/mobile\/3g_ph.png"},{"bound":{"north":25.755553664373,"south":23.617318030723,"east":119.543362995567,"west":118.07252925},"img":"https:\/\/coverage.cht.com.tw\/coverage\/images\/mobile\/3g_km.png"}]
"cht": [{'img': 'https://coverage.cht.com.tw\/coverage/images/mobile/5G_li.png', 'bound': {'east': 120.771867726397, 'south': 25.927295980521, 'west': 119.65220174854, 'north': 26.4045842}}, {'img': 'https://coverage.cht.com.tw/coverage/images/mobile/5G_tw_n2.png', 'bound': {'east': 122.051500405599, 'south': 24.359742648673, 'west': 120.5392375, 'north': 25.01235036815}}, {'img': 'https://coverage.cht.com.tw/coverage/images/mobile/5G_pg.png', 'bound': {'east': 122.284720952388, 'south': 25.541325529312, 'west': 121.879743863379, 'north': 25.7211324}}, {'img': 'https://coverage.cht.com.tw/coverage/images/mobile/5G_ds.png', 'bound': {'east': 116.997188826726, 'south': 20.584006689101, 'west': 116.465473963936, 'north': 20.8289364}}, {'img': 'https://coverage.cht.com.tw/coverage/images/mobile/5G_tw_s1.png', 'bound': {'east': 121.673891492513, 'south': 22.530644107649, 'west': 119.83757225, 'north': 23.333872579376}}, {'img': 'https://coverage.cht.com.tw/coverage/images/mobile/5G_tw_s3.png', 'bound': {'east': 121.747141347656, 'south': 21.792808908841, 'west': 120.450916, 'north': 22.363307586122}}, {'img': 'https://coverage.cht.com.tw/coverage/images/mobile/5G_tw_s2.png', 'bound': {'east': 121.668914463542, 'south': 22.148482147975, 'west': 119.940614, 'north': 22.906702194427}}, {'img': 'https://coverage.cht.com.tw/coverage/images/mobile/5G_tw_n3.png', 'bound': {'east': 122.071408521484, 'south': 23.815220184854, 'west': 120.1270705, 'north': 24.657288297184}}, {'img': 'https://coverage.cht.com.tw/coverage/images/mobile/5G_wq.png', 'bound': {'east': 119.707845581582, 'south': 24.8779004, 'west': 119.204270560542, 'north': 25.10267}}, {'img': 'https://coverage.cht.com.tw/coverage/images/mobile/5G_tw_c1.png', 'bound': {'east': 121.875138521484, 'south': 23.364081075074, 'west': 119.9308005, 'north': 24.209098366992}}, {'img': 'https://coverage.cht.com.tw/coverage/images/mobile/5G_tw_c2.png', 'bound': {'east': 121.777003521484, 'south': 22.912967522589, 'west': 119.8326655, 'north': 23.760881950797}}, {'img': 'https://coverage.cht.com.tw/coverage/images/mobile/5G_km.png', 'bound': {'east': 118.525445844076, 'south': 24.36431183685, 'west': 118.12107025, 'north': 24.539144666124}}, {'img': 'https://coverage.cht.com.tw/coverage/images/mobile/5G_ph.png', 'bound': {'east': 120.294656154273, 'south': 23.155063000222, 'west': 118.747719570648, 'north': 23.828883498}}, {'img': 'https://coverage.cht.com.tw/coverage/images/mobile/5G_tw_n1.png', 'bound': {'east': 122.139681347656, 'south': 24.857103311611, 'west': 120.843456, 'north': 25.414446204973}}], 

"cht3G": [{'img': 'https://coverage.cht.com.tw/coverage/images/mobile/3G_li.png', 'bound': {'east': 120.763994047616, 'south': 25.922017661905, 'west': 119.659921690727, 'north': 26.4100005}}, {'img': 'https://coverage.cht.com.tw/coverage/images/mobile/3G_tw_n1.png', 'bound': {'east': 122.139512678125, 'south': 24.846948982184, 'west': 120.843456, 'north': 25.424753231083}}, {'img': 'https://coverage.cht.com.tw/coverage/images/mobile/3G_wq.png', 'bound': {'east': 119.707845581582, 'south': 24.8779004, 'west': 119.204270560542, 'north': 25.10267}}, {'img': 'https://coverage.cht.com.tw/coverage/images/mobile/3G_km.png', 'bound': {'east': 118.555444334865, 'south': 24.347554730357, 'west': 118.09259560669, 'north': 24.5550429375}}, {'img': 'https://coverage.cht.com.tw/coverage/images/mobile/3G_ds.png', 'bound': {'east': 116.997188826726, 'south': 20.584006689101, 'west': 116.465473963936, 'north': 20.8289364}}, {'img': 'https://coverage.cht.com.tw/coverage/images/mobile/3G_pg.png', 'bound': {'east': 122.284720952388, 'south': 25.541325529312, 'west': 121.879743863379, 'north': 25.7211324}}, {'img': 'https://coverage.cht.com.tw/coverage/images/mobile/3G_tw_s1.png', 'bound': {'east': 121.67365254401, 'south': 22.516009945082, 'west': 119.83757225, 'north': 23.348726804538}}, {'img': 'https://coverage.cht.com.tw/coverage/images/mobile/3G_tw_s2.png', 'bound': {'east': 121.668689570833, 'south': 22.134668001923, 'west': 119.940614, 'north': 22.920724071999}}, {'img': 'https://coverage.cht.com.tw/coverage/images/mobile/3G_tw_n3.png', 'bound': {'east': 122.071155517187, 'south': 23.799878395958, 'west': 120.1270705, 'north': 24.672860789672}}, {'img': 'https://coverage.cht.com.tw/coverage/images/mobile/3G_tw_n2.png', 'bound': {'east': 122.051303624479, 'south': 24.347852672414, 'west': 120.5392375, 'north': 25.024419141045}}, {'img': 'https://coverage.cht.com.tw/coverage/images/mobile/3G_tw_c1.png', 'bound': {'east': 121.874885517188, 'south': 23.34868555455, 'west': 119.9308005, 'north': 24.224725399103}}, {'img': 'https://coverage.cht.com.tw/coverage/images/mobile/3G_tw_c2.png', 'bound': {'east': 121.776750517188, 'south': 22.897519218623, 'west': 119.8326655, 'north': 23.776562560086}}, {'img': 'https://coverage.cht.com.tw/coverage/images/mobile/3G_tw_s3.png', 'bound': {'east': 121.746972678125, 'south': 21.782414891844, 'west': 120.450916, 'north': 22.373857904126}}, {'img': 'https://coverage.cht.com.tw/coverage/images/mobile/3G_ph.png', 'bound': {'east': 120.283777836993, 'south': 23.147611236, 'west': 118.758385484617, 'north': 23.83653006}}],

// 20211214 twn view-source:https://www.taiwanmobile.com/mobile/calculate/cover_map.html

"twn": [
  {
    "bound": {
      "east": 122.248449008024,
      "west": 119.834104991976,
      "south": 21.73053598103,
      "north": 25.49458277897
    },
    "img": "https://www.taiwanmobile.com/mobile/calculate/maps/4G/Taiwan.png?r=20260818"
  },
  {
    "bound": {
      "east": 118.699254155749,
      "west": 118.014474244251,
      "south": 24.163333296853,
      "north": 24.711072163147
    },
    "img": "https://www.taiwanmobile.com/mobile/calculate/maps/4G/KM.png?r=20260818"
  },
  {
    "bound": {
      "east": 119.923261747476,
      "west": 119.224642052524,
      "south": 23.106697637927,
      "north": 23.883389062073
    },
    "img": "https://www.taiwanmobile.com/mobile/calculate/maps/4G/PF.png?r=20260818"
  },
  {
    "bound": {
      "east": 120.575646422936,
      "west": 119.824042177064,
      "south": 25.857306809423,
      "north": 26.450044270577
    },
    "img": "https://www.taiwanmobile.com/mobile/calculate/maps/4G/MZ.png?r=20260818"
  }
],


/* 20211214  
         {
                "southwest": "21.813526812557,117.496893247948",
                "northeast": "26.515893187443,122.704393752052",
                "imageUrl": "https://ecare.fetnet.net/DigService/resources/serviceCoverage/coverage3.5G.png",
                "name": "UMTS上網涵蓋",
                "typeImg": null,
                "type": "UTMS"
            },
            {
                "southwest": "20.836803614465,115.147497884158",
                "northeast": "27.14942156425,125.88518063312",
                "imageUrl": "https://ecare.fetnet.net/DigService/resources/serviceCoverage/Coverage4g5g.png",
                "name": "4.5G+5G網路涵蓋率",
                "typeImg": null,
                "type": "4G5G"
            }

https://apis.fetnet.net/eservice/api/serviceCoverageController/getServiceCoverageInfo
2022/9/21
"serviceCoverageMapInfoList":[{"southwest":"20.912682441736,116.322882125754","northeast":"26.322813780907,124.708821581148","imageUrl":"https://ecare.fetnet.net/DigService/resources/serviceCoverage/coverageVoice.png","name":"語音涵蓋","typeImg":null,"type":"VOICE"},
{"southwest":"21.813526812557,117.496893247948","northeast":"26.515893187443,122.704393752052","imageUrl":"https://ecare.fetnet.net/DigService/resources/serviceCoverage/coverage3.5G.png","name":"UMTS上網涵蓋","typeImg":null,"type":"UTMS"},
// 5G
{"southwest":"21.357521455642,116.04888306975","northeast":"26.634589252325,125.43662458532","imageUrl":"https://ecare.fetnet.net/DigService/resources/serviceCoverage/Coverage4g5g.png","name":"4.5G+5G網路涵蓋率","typeImg":null,"type":"4G5G"}],
2026.8.24 same API
[
  {
	      "southwest": "21.607599777863,117.012202993627",
	      "northeast": "26.619749863308,124.133183349856",
	      "imageUrl": "https://ecare.fetnet.net/DigService/resources/serviceCoverage/Coverage4g5g.png",
	      "name": "4.5G+5G網路涵蓋率",
	          "typeImg": null,
	      "type": "4G5G"
	    }
]
*/
//"fet":[{"bound":{"north":26.634589252325,"south":21.357521455642,"west":116.04888306975,"east":125.43662458532},"img":"https://ecare.fetnet.net/DigService/resources/serviceCoverage/Coverage4g5g.png"}],
"fet":[{"bound":{"north":26.619749863308,"south":21.607599777863,"west":117.012202993627,"east":124.133183349856},"img":"https://ecare.fetnet.net/DigService/resources/serviceCoverage/Coverage4g5g.png"}],
"fet3G":[{"bound":{"north":26.515893187443,"south":21.813526812557,"west":117.496893247948,"east":122.704393752052},"img":"https://ecare.fetnet.net/DigService/resources/serviceCoverage/coverage3.5G.png"}],
// #59 
'aptg': [{'img': 'https://www.aptg.com.tw/coverage1/N_MOCN202107.png', 'bound': {'north': 25.319524, 'south': 24.118276, 'west': 119.39645, 'east': 122.36755}}, {'img': 'https://www.aptg.com.tw/coverage1/M_MOCN202107.png', 'bound': {'north': 24.149725, 'south': 22.948875, 'west': 118.738465, 'east': 121.681535}}, {'img': 'https://www.aptg.com.tw/coverage1/S_MOCN202107.png', 'bound': {'north': 23.039725, 'south': 21.838875, 'west': 119.047514, 'east': 121.966486}}, {'img': 'https://www.aptg.com.tw/coverage1/KM_MOCN202107.png', 'bound': {'north': 24.922454, 'south': 23.924146, 'west': 116.99133, 'east': 119.45467}}, {'img': 'https://www.aptg.com.tw/coverage1/LJ_MOCN202107.png', 'bound': {'north': 26.805656, 'south': 25.804944, 'west': 118.720987, 'east': 121.229013}}]


};

let coverageOverlayLayers = [];

function clearCoverageOverlays() {
  for (const layer of coverageOverlayLayers) {
    map.removeLayer(layer);
  }
  coverageOverlayLayers = [];
}

function coverage_overlay(op) {
  clearCoverageOverlays();
  if (!op || op === 'none' || !mobileCoverage[op]) {
    return;
  }
  mobileCoverage[op].forEach(function (tile, i) {
    const layer = mapApi.addImageLayer({
      id: 'coverage-' + op + '-' + i,
      visible: true,
      opacity: 0.7,
      zIndex: 5
    });
    layer.setSource(new ol.source.ImageStatic({
      url: tile.img,
      imageExtent: [tile.bound.west, tile.bound.south, tile.bound.east, tile.bound.north],
      projection: 'EPSG:4326'
    }));
    coverageOverlayLayers.push(layer);
  });
}

if (typeof globalThis !== 'undefined') {
  globalThis.coverage_overlay = coverage_overlay;
}
