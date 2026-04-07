/**
 * Mango Dragon — Scroll Bands
 * Speed in px/s — recalculated after images load and on every resize,
 * so it's identical on all screen sizes.
 * Speeds and sizes are configurable from WP Admin > Personnaliser > Bandes défilantes.
 */
(function () {
  'use strict';

  /* =====================================================
   * VITESSES DES BARRES — valeurs par défaut (px/s)
   * Overridées par mdBands (Customizer) si présent.
   * ===================================================== */
  var defaults = { speed: [80, 80, 50] };
  var cfg = (window.mdBands && window.mdBands.speed) ? window.mdBands : defaults;

  var tracks = [];  // store {track, pps} for live preview access

  function calcDuration(track, pps) {
    var halfWidth = track.scrollWidth / 2;
    if (halfWidth <= 0) return;
    track.style.animationDuration = (halfWidth / pps).toFixed(2) + 's';
  }

  function initBands() {
    tracks = [];
    document.querySelectorAll('.scroll-band').forEach(function (band, i) {
      var track = band.querySelector('.band-track');
      if (!track) return;

      var pps = (cfg.speed && cfg.speed[i] !== undefined) ? parseInt(cfg.speed[i], 10) : 80;
      tracks[i] = { track: track, pps: pps };

      /* Recalculate when all images in this band are loaded */
      var imgs = track.querySelectorAll('img');
      var loaded = 0;
      function onLoad() {
        loaded++;
        if (loaded >= imgs.length) calcDuration(track, pps);
      }
      if (imgs.length === 0) {
        calcDuration(track, pps);
      } else {
        imgs.forEach(function (img) {
          if (img.complete) { onLoad(); }
          else { img.addEventListener('load', onLoad); img.addEventListener('error', onLoad); }
        });
      }

      /* Recalculate on resize */
      if (window.ResizeObserver) {
        new ResizeObserver(function () { calcDuration(track, tracks[i] ? tracks[i].pps : pps); }).observe(band);
      }

      /* hover pause */
      band.addEventListener('mouseenter', function () { track.style.animationPlayState = 'paused'; });
      band.addEventListener('mouseleave', function () { track.style.animationPlayState = 'running'; });

      /* touch pause */
      band.addEventListener('touchstart', function () { track.style.animationPlayState = 'paused'; }, { passive: true });
      band.addEventListener('touchend',   function () { track.style.animationPlayState = 'running'; }, { passive: true });
    });
  }

  /* Exposed for Customizer live preview */
  window.mdRecalcBand = function (i, newPps) {
    if (!tracks[i]) return;
    if (newPps !== undefined) {
      tracks[i].pps = newPps;
      if (cfg.speed) cfg.speed[i] = newPps;
    }
    calcDuration(tracks[i].track, tracks[i].pps);
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initBands);
  } else {
    initBands();
  }
  window.mdInitBands = initBands;
})();
