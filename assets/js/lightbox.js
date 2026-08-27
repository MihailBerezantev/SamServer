/**
 * Mango Dragon — Lightbox (collection de photos, page Visual)
 * Ouvre une photo en grand, navigation par flèches (même style que le
 * carrousel Studio) et boucle sur l'ensemble des photos du projet.
 */
(function () {
  'use strict';

  function initLightbox() {
    var grid = document.querySelector('[data-lightbox-gallery]');
    var lightbox = document.getElementById('visual-lightbox');
    if (!grid || !lightbox) return;

    var items = Array.prototype.slice.call(grid.querySelectorAll('.visual-collection__item'));
    var count = items.length;
    if (count < 1) return;

    var img      = lightbox.querySelector('.md-lightbox__image');
    var closeBtn = lightbox.querySelector('.md-lightbox__close');
    var prevBtn  = lightbox.querySelector('.md-lightbox__arrow--prev');
    var nextBtn  = lightbox.querySelector('.md-lightbox__arrow--next');
    var index    = 0;

    function show(i) {
      index = ((i % count) + count) % count; // boucle
      var item = items[index];
      var srcImg = item.querySelector('img');
      img.src = item.getAttribute('data-full') || '';
      img.alt = srcImg ? srcImg.alt : '';
    }

    function open(i) {
      show(i);
      lightbox.classList.add('is-open');
      lightbox.setAttribute('aria-hidden', 'false');
      document.body.classList.add('lightbox-open');
    }

    function close() {
      lightbox.classList.remove('is-open');
      lightbox.setAttribute('aria-hidden', 'true');
      document.body.classList.remove('lightbox-open');
    }

    items.forEach(function (el, i) {
      el.addEventListener('click', function () { open(i); });
    });

    if (closeBtn) closeBtn.addEventListener('click', close);
    if (prevBtn) prevBtn.addEventListener('click', function () { show(index - 1); });
    if (nextBtn) nextBtn.addEventListener('click', function () { show(index + 1); });

    lightbox.addEventListener('click', function (e) {
      if (e.target === lightbox) close();
    });

    document.addEventListener('keydown', function (e) {
      if (!lightbox.classList.contains('is-open')) return;
      if (e.key === 'Escape') close();
      if (e.key === 'ArrowLeft') show(index - 1);
      if (e.key === 'ArrowRight') show(index + 1);
    });
  }

  initLightbox();
  window.mdInitLightbox = initLightbox;
})();
