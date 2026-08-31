const twProjections = (function () {
  const proj4 = window.proj4;
  let registered = false;
  const EPSG_TW67 = 'EPSG:3828';
  const EPSG_PH67 = 'EPSG:3827';
  const EPSG_TW97 = 'EPSG:3826';
  const EPSG_PH97 = 'EPSG:3825';
  const EPSG_WGS84 = 'WGS84';

  function ensureDefs() {
    if (registered || !proj4) {
      return;
    }
    proj4.defs('EPSG:3828', '+title=二度分帶：TWD67 TM2 台灣 +proj=tmerc +towgs84=-752,-358,-179,-.0000011698,.0000018398,.0000009822,.00002329 +lat_0=0 +lon_0=121 +x_0=250000 +y_0=0 +k=0.9999 +ellps=aust_SA +units=公尺');
    proj4.defs('EPSG:3827', '+title=二度分帶：TWD67 TM2 澎湖 +proj=tmerc +towgs84=-752,-358,-179,-.0000011698,.0000018398,.0000009822,.00002329 +lat_0=0 +lon_0=119 +x_0=250000 +y_0=0 +k=0.9999 +ellps=aust_SA +units=公尺');
    proj4.defs('EPSG:3826', '+proj=tmerc +lat_0=0 +lon_0=121 +k=0.9999 +x_0=250000 +y_0=0 +ellps=GRS80 +units=m +no_defs');
    proj4.defs('EPSG:3825', '+proj=tmerc +lat_0=0 +lon_0=119 +k=0.9999 +x_0=250000 +y_0=0 +ellps=GRS80 +units=m +no_defs');

    if (window.ol && ol.proj && ol.proj.proj4 && typeof ol.proj.proj4.register === 'function') {
      try {
        ol.proj.proj4.register(proj4);
      } catch (e) {
        console.warn('ol.proj.proj4.register failed:', e);
      }
    }
    registered = true;
  }

  function toWgs(proj, x, y) {
    ensureDefs();
    const d = proj4(proj, EPSG_WGS84, [parseFloat(x), parseFloat(y)]);
    return { x: d[0], y: d[1] };
  }

  function toTwd(proj, lon, lat) {
    ensureDefs();
    const d = proj4(EPSG_WGS84, proj, [parseFloat(lon), parseFloat(lat)]);
    return { x: d[0], y: d[1] };
  }

  function isTaiwan(lat, lon) {
    if (lon > 118.1 && lon < 118.52 && lat < 24.55 && lat > 24.35) {
      return 3;
    }
    if (lon > 119.5 && lon < 120.55 && lat < 26.4 && lat > 25.9) {
      return 4;
    }
    if (lon < 119.31 || lon > 124.56 || lat < 21.88 || lat > 25.64) {
      return 0;
    } else if (lon > 119.72) {
      return 1;
    }
    return 2;
  }

  function lonlat2twd67(lon, lat, ph) {
    return toTwd(ph === 1 ? EPSG_PH67 : EPSG_TW67, lon, lat);
  }

  function lonlat2twd97(lon, lat, ph) {
    return toTwd(ph === 1 ? EPSG_PH97 : EPSG_TW97, lon, lat);
  }

  function twd672lonlat(x, y, ph) {
    return toWgs(ph === 1 ? EPSG_PH67 : EPSG_TW67, x, y);
  }

  function twd972lonlat(x, y, ph) {
    return toWgs(ph === 1 ? EPSG_PH97 : EPSG_TW97, x, y);
  }

  function cad2twd67(Xcad, Ycad, unit) {
    unit = typeof unit !== 'undefined' ? unit : 'm';
    if (unit === 'm') {
      Xcad *= 0.55;
      Ycad *= 0.55;
    }
    const XCtm69 = 227361.634;
    const YCtm69 = 2632574.582;
    const XCcad = 5750;
    const YCcad = -21300;
    const A = 1.8182516286522;
    const B = -0.004167109289753;
    const Xtmtrn = A * (Xcad - XCcad) - B * (Ycad - YCcad) + XCtm69;
    const Ytmtrn = B * (Xcad - XCcad) + A * (Ycad - YCcad) + YCtm69;
    return [Xtmtrn, Ytmtrn];
  }

  function twd672cad(x, y, unit) {
    const XCtm69 = 227361.634;
    const YCtm69 = 2632574.582;
    const XCcad = 5750;
    const YCcad = -21300;
    const A = 1.8182516286522;
    const B = -0.004167109289753;
    let Xcad = (B * y - B * YCtm69 + B * B * XCcad + A * x - A * XCtm69 + A * A * XCcad) / (A * A + B * B);
    let Ycad = (A * y - A * YCtm69 + A * A * YCcad + B * B * YCcad - B * x + B * XCtm69) / (B * B + A * A);
    unit = typeof unit !== 'undefined' ? unit : 'm';
    if (unit === 'm') {
      Xcad /= 0.55;
      Ycad /= 0.55;
    }
    return { x: Xcad, y: Ycad };
  }

  function lonlat2cad(lon, lat, unit) {
    const p = lonlat2twd67(lon, lat, 0);
    return twd672cad(p.x, p.y, unit);
  }

  function lonlat_getblock(lon, lat, ph, unit) {
    unit = typeof unit !== 'undefined' ? unit : 1000;
    const p = lonlat2twd67(lon, lat, ph);
    const tl = { x: Math.floor(p.x / unit) * unit, y: Math.ceil(p.y / unit) * unit };
    const br = { x: Math.ceil(p.x / unit) * unit, y: Math.floor(p.y / unit) * unit };
    const p1 = twd672lonlat(tl.x, tl.y, ph);
    const p2 = twd672lonlat(br.x, br.y, ph);
    return [p1, p2, tl, br];
  }

  function lonlat_getblock97(lon, lat, ph, unit) {
    unit = typeof unit !== 'undefined' ? unit : 1000;
    const p = lonlat2twd97(lon, lat, ph);
    const tl = { x: Math.floor(p.x / unit) * unit, y: Math.ceil(p.y / unit) * unit };
    const br = { x: Math.ceil(p.x / unit) * unit, y: Math.floor(p.y / unit) * unit };
    const p1 = twd972lonlat(tl.x, tl.y, ph);
    const p2 = twd972lonlat(br.x, br.y, ph);
    return [p1, p2, tl, br];
  }

  function ConvertDDToDMS(D) {
    return [0 | D, 'd', 0 | (D < 0 ? D = -D : D) % 1 * 60, "'", 0 | D * 60 % 1 * 60, '"'].join('');
  }

  const api = {
    available() {
      return !!proj4;
    },
    isTaiwan,
    lonlat2twd67,
    lonlat2twd97,
    twd672lonlat,
    twd972lonlat,
    cad2twd67,
    twd672cad,
    lonlat2cad,
    lonlat_getblock,
    lonlat_getblock97,
    ConvertDDToDMS
  };

  return api;
})();

if (typeof globalThis !== 'undefined') {
  globalThis.twProjections = twProjections;
}