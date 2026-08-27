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
  var MOBILE_BP = 640;
  var MOBILE_FACTOR = 0.5;

  var tracks = [];  // store {track, basePps} for live preview access

  function isMobile() {
    return window.innerWidth <= MOBILE_BP;
  }

  function effectivePps(basePps) {
    return isMobile() ? basePps * MOBILE_FACTOR : basePps;
  }

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

      var basePps = (cfg.speed && cfg.speed[i] !== undefined) ? parseInt(cfg.speed[i], 10) : 80;
      tracks[i] = { track: track, basePps: basePps };

      /* Recalculate when all images in this band are loaded */
      var imgs = track.querySelectorAll('img');
      var loaded = 0;
      function onLoad() {
        loaded++;
        if (loaded >= imgs.length) calcDuration(track, effectivePps(basePps));
      }
      if (imgs.length === 0) {
        calcDuration(track, effectivePps(basePps));
      } else {
        imgs.forEach(function (img) {
          if (img.complete) { onLoad(); }
          else { img.addEventListener('load', onLoad); img.addEventListener('error', onLoad); }
        });
      }

      /* Recalculate on resize (also handles mobile ↔ desktop speed switch) */
      if (window.ResizeObserver) {
        new ResizeObserver(function () {
          var t = tracks[i];
          if (t) calcDuration(track, effectivePps(t.basePps));
        }).observe(band);
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
      tracks[i].basePps = newPps;
      if (cfg.speed) cfg.speed[i] = newPps;
    }
    calcDuration(tracks[i].track, effectivePps(tracks[i].basePps));
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initBands);
  } else {
    initBands();
  }
  window.mdInitBands = initBands;
})();
