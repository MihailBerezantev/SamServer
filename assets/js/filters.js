/**
 * Mango Dragon — Filters
 * Client-side filtering via data attributes on cards.
 */
(function () {
  'use strict';

  function initFilters() {
    var filterBar = document.getElementById('filter-bar');
    var grid      = document.getElementById('cards-grid');
    var resetBtn  = document.getElementById('filter-reset');
    if (!filterBar || !grid) return;

    // Exclut les pills de TRI (.sort-pill) : elles sont gérées par le module de tri.
    var pills  = filterBar.querySelectorAll('.filter-pill:not(.sort-pill)');
    var cards  = grid.children;
    var active = {}; // { taxonomy: Set }

    pills.forEach(function (pill) {
      pill.addEventListener('click', function () {
        var tax = pill.dataset.filter;
        var val = pill.dataset.value;
        if (!active[tax]) active[tax] = new Set();

        if (active[tax].has(val)) {
          active[tax].delete(val);
          pill.classList.remove('active');
          pill.setAttribute('aria-pressed', 'false');
        } else {
          active[tax].add(val);
          pill.classList.add('active');
          pill.setAttribute('aria-pressed', 'true');
        }
        apply();
      });
    });

    if (resetBtn) {
      resetBtn.addEventListener('click', function () {
        Object.keys(active).forEach(function (k) { active[k].clear(); });
        pills.forEach(function (p) { p.classList.remove('active'); p.setAttribute('aria-pressed', 'false'); });
        apply();
      });
    }

    function attrMap(tax) {
      switch (tax) {
        case 'genre':        return 'data-genres';
        case 'release_type': return 'data-release-type';
        case 'artist_type':  return 'data-type';
        case 'artists':      return 'data-artists';
        case 'visual_type':  return 'data-visual-type';
        default:             return 'data-' + tax;
      }
    }

    function apply() {
      var hasActive = Object.values(active).some(function (s) { return s.size > 0; });
      if (resetBtn) resetBtn.style.display = hasActive ? '' : 'none';

      Array.from(cards).forEach(function (card) {
        if (!hasActive) { card.style.display = ''; return; }

        var show = true;
        for (var tax in active) {
          if (!active[tax].size) continue;
          var vals = (card.getAttribute(attrMap(tax)) || '').split(',').filter(Boolean);
          var match = false;
          active[tax].forEach(function (v) { if (vals.indexOf(v) !== -1) match = true; });
          if (!match) { show = false; break; }
        }
        card.style.display = show ? '' : 'none';
      });
    }
  }

  initFilters();
  window.mdInitFilters = initFilters;
})();

/**
 * Mango Dragon — Sort switch (Releases)
 * Client-side reordering of #cards-grid: Latest / Oldest / Random.
 * Coexists with the filters above (filtering toggles display; sorting reorders
 * the DOM — the two are independent).
 */
(function () {
  'use strict';

  function initSort() {
    var grid = document.getElementById('cards-grid');
    var btns = document.querySelectorAll('.sort-pill');
    if (!grid || !btns.length) return;

    function dateOf(card) {
      return card.getAttribute('data-date') || '';
    }

    function applySort(mode) {
      var cards = Array.prototype.slice.call(grid.children);

      if (mode === 'random') {
        // Fisher–Yates shuffle
        for (var i = cards.length - 1; i > 0; i--) {
          var j = Math.floor(Math.random() * (i + 1));
          var tmp = cards[i]; cards[i] = cards[j]; cards[j] = tmp;
        }
      } else {
        cards.sort(function (a, b) {
          var da = dateOf(a), db = dateOf(b);
          // Cartes sans date en dernier, quel que soit le sens.
          if (!da && !db) return 0;
          if (!da) return 1;
          if (!db) return -1;
          if (da === db) return 0;
          if (mode === 'oldest') { return da < db ? -1 : 1; }
          return da > db ? -1 : 1; // latest
        });
      }

      cards.forEach(function (card) { grid.appendChild(card); });
    }

    btns.forEach(function (btn) {
      btn.addEventListener('click', function () {
        btns.forEach(function (b) {
          b.classList.remove('active');
          b.setAttribute('aria-pressed', 'false');
        });
        btn.classList.add('active');
        btn.setAttribute('aria-pressed', 'true');
        applySort(btn.dataset.sort);
      });
    });

    // Applique le tri par défaut au chargement (pill .active, sinon la première).
    var def = document.querySelector('.sort-pill.active') || btns[0];
    if (def) applySort(def.dataset.sort);
  }

  initSort();
  window.mdInitSort = initSort;
})();

/**
 * Mango Dragon — Carrousel des Services (page Studio)
 * Une slide par service : photo (haut) → points (milieu) → détails (bas).
 * Navigation : points, flèches, et défilement/swipe latéral.
 */
(function () {
  'use strict';

  function initStudioSlider() {
    var slider = document.getElementById('studio-slider');
    if (!slider) return;

    var track   = slider.querySelector('.slider-photos__track');
    var slides  = slider.querySelectorAll('.slide-photo');
    var dots    = slider.querySelectorAll('.slider-dots .dot');
    var details = slider.querySelectorAll('.slide-detail');
    var count   = slides.length;
    if (count < 1) return;

    var index = 0;

    function go(i) {
      index = ((i % count) + count) % count; // boucle
      if (track) track.style.transform = 'translateX(' + ( -index * 100 ) + '%)';
      dots.forEach(function (d, j) {
        var active = j === index;
        d.classList.toggle('active', active);
        d.setAttribute('aria-selected', active ? 'true' : 'false');
      });
      details.forEach(function (d, j) { d.classList.toggle('active', j === index); });
    }

    dots.forEach(function (d, j) { d.addEventListener('click', function () { go(j); }); });

    var prev = slider.querySelector('.slider-arrow--prev');
    var next = slider.querySelector('.slider-arrow--next');
    if (prev) prev.addEventListener('click', function () { go(index - 1); });
    if (next) next.addEventListener('click', function () { go(index + 1); });

    // Défilement latéral tactile (swipe)
    var startX = null;
    var vp = slider.querySelector('.slider-photos__viewport') || slider;
    vp.addEventListener('touchstart', function (e) { startX = e.touches[0].clientX; }, { passive: true });
    vp.addEventListener('touchend', function (e) {
      if (startX === null) return;
      var dx = e.changedTouches[0].clientX - startX;
      if (Math.abs(dx) > 40) { go(index + ( dx < 0 ? 1 : -1 )); }
      startX = null;
    }, { passive: true });

    go(0);
  }

  initStudioSlider();
  window.mdInitStudioSlider = initStudioSlider;
})();
