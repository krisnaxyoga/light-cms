/* LightCMS default theme — vanilla JS only (PRD §2.2: no jQuery). */
(function () {
  'use strict';

  // Lazy-load any <img> that a template forgot to mark loading="lazy".
  document.querySelectorAll('img:not([loading])').forEach(function (img) {
    img.setAttribute('loading', 'lazy');
  });

  // Mobile nav toggle, if the theme adds a .lcms-nav-toggle button.
  var toggle = document.querySelector('.lcms-nav-toggle');
  var nav = document.querySelector('.lcms-nav');
  if (toggle && nav) {
    toggle.addEventListener('click', function () {
      nav.classList.toggle('is-open');
    });
  }
})();
