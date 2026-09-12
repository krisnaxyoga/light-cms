/* LightCMS admin UI — vanilla JS only (PRD §2.2). */
(function () {
  'use strict';

  // Auto-dismiss flash alerts after a few seconds.
  document.querySelectorAll('.lcms-alert').forEach(function (alert) {
    setTimeout(function () {
      alert.style.opacity = '0';
      setTimeout(function () { alert.remove(); }, 300);
    }, 4000);
  });

  // Light/dark switch. The head script has already applied the saved theme;
  // this only keeps the toggle in sync and writes the choice back.
  var toggle = document.getElementById('lcms-theme-toggle');

  if (toggle) {
    var DARK = 'lightcmsdark';
    var LIGHT = 'lightcms';

    toggle.checked = document.documentElement.dataset.theme === DARK;

    toggle.addEventListener('change', function () {
      var theme = toggle.checked ? DARK : LIGHT;
      document.documentElement.dataset.theme = theme;

      try {
        localStorage.setItem('lcms-theme', theme);
      } catch (e) { /* private mode: the choice just does not persist */ }
    });
  }
})();
