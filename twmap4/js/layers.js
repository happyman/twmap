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
  dark: {
    sourceId: 'dark',
    label: 'Carto 深色',
    url: 'https://b.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}.png'
  }
};
