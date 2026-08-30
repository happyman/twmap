function closeMeerkat() {
  const wrap = document.getElementById('meerkat-wrap');
  const frame = document.getElementById('meerkatiframe');
  if (!wrap) {
    return;
  }
  wrap.classList.add('hidden');
  if (frame && frame.src !== 'about:blank') {
    frame.src = 'about:blank';
  }
}

function showmeerkat(url, options) {
  const wrap = document.getElementById('meerkat-wrap');
  const frame = document.getElementById('meerkatiframe');
  if (!wrap || !frame) {
    return;
  }

  if (!wrap.classList.contains('hidden') && frame.getAttribute('data-url') === url) {
    closeMeerkat();
    return;
  }

  const screenwidth = (window.innerWidth > 0) ? window.innerWidth : screen.width;
  let width = (options && options.width) ? parseInt(options.width, 10) : 830;
  if (width > screenwidth) {
    width = screenwidth - 50;
  }
  if (width < 260) {
    width = 260;
  }

  wrap.style.width = width + 'px';
  frame.setAttribute('data-url', url);
  frame.src = url;
  wrap.classList.remove('hidden');
}

document.addEventListener('DOMContentLoaded', function () {
  const closeBtn = document.getElementById('meerkat-close');
  if (closeBtn) {
    closeBtn.addEventListener('click', closeMeerkat);
  }

  const gotoBtn = document.getElementById('goto');
  if (gotoBtn) {
    gotoBtn.addEventListener('click', function () {
      const tags = document.getElementById('tags');
      const query = (tags && tags.value || '').trim();
      if (!query) {
        return;
      }
      const byCoord = query.match(/^([-+]?\d+(?:\.\d+)?)\s*[,\s]\s*([-+]?\d+(?:\.\d+)?)$/);
      if (byCoord && typeof mapApi !== 'undefined') {
        const lat = parseFloat(byCoord[1]);
        const lon = parseFloat(byCoord[2]);
        mapApi.setView([lon, lat], 14);
      }
    });
  }
});

window.addEventListener('message', function (e) {
  if (e.data && e.data.function === 'markerReloadSingle' && typeof window.markerReloadSingle === 'function') {
    window.markerReloadSingle(e.data);
  }
});