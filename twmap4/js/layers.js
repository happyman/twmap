function createTaitungForestTileUrl(baseUrl, tileCoord) {
  if (!tileCoord) {
    return undefined;
  }

  const zoom = String(tileCoord[0]).padStart(2, '0');
  const x = tileCoord[1].toString(16).padStart(8, '0');
  const y = (-tileCoord[2] - 1).toString(16).padStart(8, '0');
  return baseUrl + '/L' + zoom + '/R' + y + '/C' + x + '.png';
}

const mapSources = {
  osm: {
    sourceId: 'osm',
    label: 'OpenStreetMap',
    url: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'
  },
  nlsc_emap: {
    sourceId: 'nlsc_emap',
    label: 'NLSC 電子地圖',
    url: 'https://wmts.nlsc.gov.tw/wmts/EMAP5/default/EPSG:3857/{z}/{y}/{x}'
  },
  nlsc_photo: {
    sourceId: 'nlsc_photo',
    label: 'NLSC 正射影像',
    url: 'https://wmts.nlsc.gov.tw/wmts/PHOTO2/default/EPSG:3857/{z}/{y}/{x}'
  },
  nlsc_photo_mix: {
    sourceId: 'nlsc_photo_mix',
    label: 'NLSC 正射混合',
    url: 'https://wmts.nlsc.gov.tw/wmts/PHOTO_MIX/default/EPSG:3857/{z}/{y}/{x}'
  },
  rudy: {
    sourceId: 'rudy',
    label: '魯地圖',
    url: 'https://tile.happyman.idv.tw/map/rudy/{z}/{x}/{y}.png'
  },
  rudy_en: {
    sourceId: 'rudy_en',
    label: '魯地圖英文',
    url: 'https://tile.happyman.idv.tw/map/rudy_en/{z}/{x}/{y}.png'
  },
  moi_osm_twmap: {
    sourceId: 'moi_osm_twmap',
    label: '魯地圖 twmap 樣式',
    url: 'https://tile.happyman.idv.tw/map/moi_osm/{z}/{x}/{y}.png'
  },
  rudy_bn: {
    category: 'road',
    sourceId: 'rudy_bn',
    label: '魯地圖 BN 樣式',
    url: 'https://tile.happyman.idv.tw/map/rudy_bn/{z}/{x}/{y}.png'
  },
  rudy_dn: {
    category: 'road',
    sourceId: 'rudy_dn',
    label: '魯地圖 DN 樣式',
    url: 'https://tile.happyman.idv.tw/map/rudy_dn/{z}/{x}/{y}.png'
  },
  rudy_tn: {
    category: 'road',
    sourceId: 'rudy_tn',
    label: '魯地圖 TN 樣式',
    url: 'https://tile.happyman.idv.tw/map/rudy_tn/{z}/{x}/{y}.png'
  },
  nlsc_names: {
    category: 'road',
    sourceId: 'nlsc_names',
    label: 'NLSC 地名',
    url: 'https://wmts.nlsc.gov.tw/wmts/EMAP2/default/EPSG:3857/{z}/{y}/{x}'
  },
  tw25k: {
    sourceId: 'tw25k',
    label: '經建三版',
    url: 'https://tile.happyman.idv.tw/map/tw25k2001/{z}/{x}/{y}.png'
  },
  tw25k_v1: {
    sourceId: 'tw25k_v1',
    label: '經建一版',
    url: 'https://gis.sinica.edu.tw/tileserver/file-exists.php?img=TM25K_1989-jpg-{z}-{x}-{y}'
  },
  historical_1924: {
    sourceId: 'historical_1924',
    label: '日治陸測 1924',
    url: 'https://gis.sinica.edu.tw/tileserver/file-exists.php?img=JM50K_1924-jpg-{z}-{x}-{y}'
  },
  historical_1916: {
    sourceId: 'historical_1916',
    label: '日治蕃地 1916',
    url: 'https://gis.sinica.edu.tw/tileserver/file-exists.php?img=JM50K_1916-jpg-{z}-{x}-{y}'
  },
  historical_1924_new: {
    sourceId: 'historical_1924_new',
    label: '日治陸測 1924 新版',
    url: 'https://gis.sinica.edu.tw/tileserver/file-exists.php?img=JM50K_1924_new-jpg-{z}-{x}-{y}'
  },
  historical_1956: {
    sourceId: 'historical_1956',
    label: '臺灣五萬分之一 1956',
    url: 'https://gis.sinica.edu.tw/tileserver/file-exists.php?img=TM50K_1956-jpg-{z}-{x}-{y}'
  },
  historical_1966: {
    sourceId: 'historical_1966',
    label: '水利圖 1966',
    url: 'https://gis.sinica.edu.tw/tileserver/file-exists.php?img=TM50K_1966-jpg-{z}-{x}-{y}'
  },
  historical_1921: {
    sourceId: 'historical_1921',
    label: '日治臺灣堡圖 1921',
    url: 'https://gis.sinica.edu.tw/tileserver/file-exists.php?img=JM20K_1921-jpg-{z}-{x}-{y}'
  },
  historical_1904: {
    sourceId: 'historical_1904',
    label: '日治臺灣堡圖 1904',
    url: 'https://gis.sinica.edu.tw/tileserver/file-exists.php?img=JM20K_1904-jpg-{z}-{x}-{y}'
  },
  historical_1904_triangulation: {
    sourceId: 'historical_1904_triangulation',
    label: '日治三角測量圖',
    url: 'https://gis.sinica.edu.tw/tileserver/file-exists.php?img=JM20K_1904_triangulation-png-{z}-{x}-{y}'
  },
  tw5k: {
    sourceId: 'tw5k',
    label: '臺灣五千分之一相片基本圖',
    url: 'https://tile.happyman.idv.tw/map/tw5k/{z}/{x}/{y}.png'
  },
  atis: {
    sourceId: 'atis',
    label: '農航所正射影像',
    url: 'https://tile.happyman.idv.tw/map/atis/{z}/{x}/{y}.png'
  },
  geo2016: {
    sourceId: 'geo2016',
    label: '臺灣地質圖 2016',
    url: 'https://tile.happyman.idv.tw/map/geo2016/{z}/{x}/{y}.png'
  },
  tri1999: {
    sourceId: 'tri1999',
    label: '百岳及一等點 1999',
    url: 'https://tile.happyman.idv.tw/map/tri1999/{z}/{x}/{y}.png'
  },
  hl20240403: {
    category: 'road',
    sourceId: 'hl20240403',
    label: '花蓮地震崩壁 2024',
    url: 'https://tile.happyman.idv.tw/map/hl20240403/{z}/{x}/{y}.png'
  },
  ttfb3_0601: {
    sourceId: 'ttfb3_0601',
    label: '臺東郡國有林野圖',
    tileUrlFunction: function (tileCoord) {
      return createTaitungForestTileUrl('https://gis.sinica.edu.tw/taitung/map_TFB3_0601/Layers/_alllayers', tileCoord);
    }
  },
  ttfb3_0602: {
    sourceId: 'ttfb3_0602',
    label: '關山郡國有林野圖',
    tileUrlFunction: function (tileCoord) {
      return createTaitungForestTileUrl('https://gis.sinica.edu.tw/taitung/map_TFB3_0602/Layers/_alllayers', tileCoord);
    }
  },
  ttfb3_0603: {
    sourceId: 'ttfb3_0603',
    label: '新港郡國有林野圖',
    tileUrlFunction: function (tileCoord) {
      return createTaitungForestTileUrl('https://gis.sinica.edu.tw/taitung/map_TFB3_0603/Layers/_alllayers', tileCoord);
    }
  },
  gpx_track: {
    category: 'road',
    sourceId: 'gpx_track',
    label: 'GPX 航跡圖層',
    url: 'https://tile.happyman.idv.tw/map/gpxtrack/{z}/{x}/{y}.png'
  },
  happyman: {
    category: 'road',
    sourceId: 'happyman',
    label: 'Happyman 航跡圖',
    url: 'https://tile.happyman.idv.tw/map/happyman/{z}/{x}/{y}.png'
  },
  forest: {
    category: 'road',
    sourceId: 'forest',
    label: '林班界圖',
    url: 'https://tile.happyman.idv.tw/map/forest/{z}/{x}/{y}.png'
  },
  hillshading: {
    sourceId: 'hillshading',
    label: '山區陰影',
    url: 'https://tile.happyman.idv.tw/map/colorrelief/{z}/{x}/{y}.png'
  },
  contour_2005: {
    category: 'road',
    sourceId: 'contour_2005',
    label: '內政部等高線 2005',
    url: 'https://wmts.nlsc.gov.tw/wmts/MOI_CONTOUR/default/EPSG:3857/{z}/{y}/{x}'
  },
  contour_2015: {
    category: 'road',
    sourceId: 'contour_2015',
    label: '內政部等高線 2015',
    url: 'https://wmts.nlsc.gov.tw/wmts/MOI_CONTOUR_2/default/EPSG:3857/{z}/{y}/{x}'
  },
};

const layerMetadata = {
  historical_1904: { order: 10, icon: '\uf1da' },
  historical_1904_triangulation: { order: 20, icon: '\uf129' },
  historical_1916: { order: 30, icon: '\uf1da' },
  historical_1921: { order: 40, icon: '\uf1da' },
  historical_1924: { order: 50, icon: '\uf1da' },
  historical_1924_new: { order: 60, icon: '\uf1da' },
  ttfb3_0603: { order: 70, icon: '\uf1da' },
  ttfb3_0602: { order: 80, icon: '\uf1da' },
  ttfb3_0601: { order: 90, icon: '\uf1da' },
  historical_1956: { order: 100, icon: '\uf1da' },
  historical_1966: { order: 110, icon: '\uf129' },
  geo2016: { order: 120, icon: '\uf129' },
  tw25k_v1: { order: 130, icon: '\uf1da' },
  tri1999: { order: 140, icon: '\uf129' },
  tw5k: { order: 150, icon: '\uf1da' },
  tw25k: { order: 160, icon: '\uf1da' },
  moi_osm_twmap: { order: 170, icon: '\uf164' },
  rudy_en: { order: 180, icon: '\uf164' },
  nlsc_emap: { order: 190, icon: '\uf164' },
  atis: { order: 200, icon: '\uf279' },
  nlsc_photo_mix: { order: 210, icon: '\uf279' },
  osm: { order: 220, icon: '\uf279' },
  nlsc_photo: { order: 230, icon: '\uf279' },
  rudy: { order: 240, icon: '\uf164' },
  hillshading: { order: 250, icon: '\uf0ac' },
  nlsc_names: { order: 10, icon: '\uf1a0' },
  hl20240403: { order: 20, icon: '\uf1da' },
  rudy_bn: { order: 30, icon: '\uf164' },
  rudy_dn: { order: 40, icon: '\uf164' },
  rudy_tn: { order: 50, icon: '\uf164' },
  gpx_track: { order: 60, icon: '\uf279' },
  happyman: { order: 70, icon: '\uf164' },
  forest: { order: 80, icon: '\uf164' },
  contour_2015: { order: 90, icon: '\uf0ac' },
  contour_2005: { order: 100, icon: '\uf0ac' }
};

for (const [sourceId, metadata] of Object.entries(layerMetadata)) {
  Object.assign(mapSources[sourceId], metadata);
}
