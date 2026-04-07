/**
 * Mango Dragon — Theme Toggle (dark/bright mode)
 */
(function () {
  'use strict';

  var STORAGE_KEY = 'md-theme';

  function applyTheme() {
    var saved = localStorage.getItem(STORAGE_KEY);
    if (saved === 'dark') {
      document.documentElement.classList.add('dark-mode');
    } else if (!saved && window.matchMedia('(prefers-color-scheme: dark)').matches) {
      document.documentElement.classList.add('dark-mode');
    }
  }

  function toggle() {
    var isDark = document.documentElement.classList.toggle('dark-mode');
    localStorage.setItem(STORAGE_KEY, isDark ? 'dark' : 'light');
  }

  function bindToggle() {
    document.querySelectorAll('.theme-toggle').forEach(function (btn) {
      btn.removeEventListener('click', toggle);
      btn.addEventListener('click', toggle);
    });
  }

  applyTheme();
  bindToggle();

  window.mdBindThemeToggle = bindToggle;
})();
