/**
 * Mango Dragon — App (AJAX navigation + init orchestrator)
 * Swaps <main id="content"> without reloading — keeps audio player alive.
 */
(function () {
  'use strict';

  var origin = window.location.origin;

  /* ==================== AJAX Navigation ==================== */

  function bindLinks() {
    document.querySelectorAll('a[data-ajax-link]').forEach(function (a) {
      if (a._md) return;
      a._md = true;
      a.addEventListener('click', onLinkClick);
    });
  }

  function onLinkClick(e) {
    if (e.ctrlKey || e.metaKey || e.shiftKey || this.target === '_blank') return;
    var url = this.href;
    if (!url || !url.startsWith(origin)) return;
    if (url === window.location.href) { e.preventDefault(); return; }
    e.preventDefault();
    navigateTo(url);
  }

  function navigateTo(url, push) {
    if (push === undefined) push = true;
    document.body.classList.add('is-loading');

    fetch(url).then(function (r) {
      if (!r.ok) throw new Error(r.status);
      return r.text();
    }).then(function (html) {
      var doc = new DOMParser().parseFromString(html, 'text/html');

      /* 1. main content */
      var nMain = doc.getElementById('content');
      var cMain = document.getElementById('content');
      if (nMain && cMain) cMain.innerHTML = nMain.innerHTML;

      /* 2. site-header is always visible — no swap needed */

      /* 4. title + body class */
      document.title = doc.title;
      var preserveClasses = ['dark-mode', 'menu-open'];
      var keep = preserveClasses.filter(function (c) { return document.body.classList.contains(c); });
      document.body.className = doc.body.className;
      keep.forEach(function (c) { document.body.classList.add(c); });
      document.body.classList.remove('is-loading');

      /* 5. history */
      if (push) history.pushState({ url: url }, '', url);

      /* 6. scroll */
      window.scrollTo(0, 0);

      /* 7. re-init */
      initPage();
      bindLinks();

    }).catch(function () {
      window.location.href = url;
    });
  }

  window.addEventListener('popstate', function (e) {
    if (e.state && e.state.url) navigateTo(e.state.url, false);
  });

  /* ==================== Page init ==================== */

  function initPage() {
    if (window.mdInitBands)       window.mdInitBands();
    if (window.mdInitFilters)     window.mdInitFilters();
    if (window.mdInitForms)       window.mdInitForms();
    if (window.mdBindThemeToggle) window.mdBindThemeToggle();
    initTracklist();
  }

  /* ---------- Tracklist (single-release page) ---------- */
  function initTracklist() {
    var tl = document.getElementById('tracklist');
    if (!tl || !window.mdPlayer) return;

    var artwork = tl.dataset.releaseArtwork || '';
    var artists = tl.dataset.releaseArtists || '';

    tl.querySelectorAll('.tracklist__item--playable').forEach(function (item) {
      item.addEventListener('click', function () {
        window.mdPlayer.playTrack({
          title: item.dataset.trackTitle,
          artist: artists,
          audioUrl: item.dataset.audioUrl,
          artwork: artwork,
          duration: item.dataset.trackDuration,
        });
      });
    });

    var playAll = document.getElementById('play-all-tracks');
    if (playAll) {
      playAll.addEventListener('click', function () {
        var tracks = [];
        tl.querySelectorAll('.tracklist__item--playable').forEach(function (item) {
          tracks.push({
            title: item.dataset.trackTitle,
            artist: artists,
            audioUrl: item.dataset.audioUrl,
            artwork: artwork,
            duration: item.dataset.trackDuration,
          });
        });
        window.mdPlayer.playAll(tracks);
      });
    }
  }

  /* ---------- Navbar scroll state ---------- */
  function initNavScroll() {
    var nav = document.getElementById('main-nav');
    if (!nav) return;
    function onScroll() { nav.classList.toggle('scrolled', window.scrollY > 50); }
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  }

  /* ---------- Mobile menu ---------- */
  function initMobileMenu() {
    var btn     = document.getElementById('mobile-menu-toggle');
    var overlay = document.getElementById('mobile-menu-overlay');
    if (!btn || !overlay) return;

    btn.addEventListener('click', function () {
      var open = overlay.getAttribute('aria-hidden') !== 'false';
      overlay.setAttribute('aria-hidden', String(!open));
      btn.setAttribute('aria-expanded', String(open));
      btn.classList.toggle('is-open', open);
      document.body.classList.toggle('menu-open', open);
    });

    overlay.querySelectorAll('a').forEach(function (a) {
      a.addEventListener('click', function () {
        overlay.setAttribute('aria-hidden', 'true');
        btn.setAttribute('aria-expanded', 'false');
        btn.classList.remove('is-open');
        document.body.classList.remove('menu-open');
      });
    });
  }

  /* ==================== Boot ==================== */
  function boot() {
    history.replaceState({ url: window.location.href }, '', window.location.href);
    bindLinks();
    initPage();
    initNavScroll();
    initMobileMenu();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
