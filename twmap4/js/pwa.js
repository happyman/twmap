var installAppBtn = document.getElementById('install-app-btn');
var installHelpDialog = document.getElementById('install-help-dialog');
var deferredInstallPrompt = null;

function isStandaloneMode() {
  var standalone = false;
  try {
    if (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) {
      standalone = true;
    }
  } catch (err) {
    standalone = false;
  }
  if (!standalone && navigator.standalone) {
    standalone = true;
  }
  return standalone;
}

function hideInstallBtn() {
  if (installAppBtn) {
    installAppBtn.classList.add('hidden');
  }
}

function showInstallBtn() {
  if (installAppBtn) {
    installAppBtn.classList.remove('hidden');
  }
}

function openInstallHelp() {
  if (installHelpDialog) {
    installHelpDialog.classList.remove('hidden');
  }
}

function closeInstallHelp() {
  if (installHelpDialog) {
    installHelpDialog.classList.add('hidden');
  }
}

function install() {
  if (deferredInstallPrompt) {
    deferredInstallPrompt.prompt();
    var choice = deferredInstallPrompt.userChoice;
    if (choice && typeof choice.then === 'function') {
      choice.then(function (result) {
        deferredInstallPrompt = null;
        hideInstallBtn();
      });
    }
    return;
  }
  openInstallHelp();
}

window.addEventListener('beforeinstallprompt', function (event) {
  event.preventDefault();
  deferredInstallPrompt = event;
  showInstallBtn();
});

window.addEventListener('appinstalled', function () {
  deferredInstallPrompt = null;
  hideInstallBtn();
  closeInstallHelp();
});

document.addEventListener('keydown', function (e) {
  if (e.key === 'Escape') {
    closeInstallHelp();
  }
});

if (installAppBtn) {
  installAppBtn.addEventListener('click', function () {
    install();
  });
}

if (isStandaloneMode()) {
  hideInstallBtn();
} else if ('serviceWorker' in navigator) {
  window.addEventListener('load', function () {
    navigator.serviceWorker.register('sw.js').then(function (registration) {
      if (registration && typeof registration.scope === 'string') {
        console.log('TWMap service worker registered at', registration.scope);
      }
    }).catch(function (err) {
      console.warn('TWMap service worker registration failed:', err);
    });
  });
}
