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

    var pills  = filterBar.querySelectorAll('.filter-pill');
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
