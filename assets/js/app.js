/**
 * Mango Dragon — App (AJAX navigation + init orchestrator)
 * Swaps <main id="content"> without reloading — keeps audio player alive.
 */
(function () {
  'use strict';

  var origin = window.location.origin;

  /* ==================== AJAX Navigation ==================== */

  function bindLinks() {
    document.querySelectorAll('a[href]').forEach(function (a) {
      if (a._md) return;
      var href = a.getAttribute('href') || '';
      /* Skip hash-only, mailto, tel, download links */
      if (!href || href.charAt(0) === '#' || href.startsWith('mailto:') || href.startsWith('tel:') || a.hasAttribute('download')) return;
      a._md = true;
      a.addEventListener('click', onLinkClick);
    });
  }

  function onLinkClick(e) {
    if (e.ctrlKey || e.metaKey || e.shiftKey || this.target === '_blank') return;
    var url = this.href;
    if (!url || !url.startsWith(origin)) return;
    /* Skip WordPress internal paths */
    var path = new URL(url).pathname;
    if (path.startsWith('/wp-') || path.indexOf('/feed') !== -1) return;
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

  /* ==================== Active menu highlight (SPA-aware) ==================== */
  /* WP marque la page active au rendu serveur ; en navigation AJAX le header n'est
     pas rechargé → on recalcule l'élément actif depuis l'URL courante à chaque page. */
  function normPath(p) { return (p || '/').replace(/\/+$/, '') || '/'; }

  function updateActiveNav() {
    var here = normPath(window.location.pathname);
    document.querySelectorAll('.nav-links .menu-item, .mobile-nav-links .menu-item').forEach(function (li) {
      li.classList.remove('current-menu-item', 'current-menu-ancestor', 'current_page_item');
    });
    document.querySelectorAll('.nav-links .menu-item a, .mobile-nav-links .menu-item a').forEach(function (a) {
      var lp;
      try { lp = normPath(new URL(a.href).pathname); } catch (e) { return; }
      var li = a.closest('.menu-item');
      if (!li) return;
      if (lp === here) {
        li.classList.add('current-menu-item');                          /* page exacte */
      } else if (lp !== '/' && (here + '/').indexOf(lp + '/') === 0) {
        li.classList.add('current-menu-ancestor');                      /* sous-page (ex. fiche artiste) */
      }
    });
  }

  /* ==================== Page init ==================== */

  function initPage() {
    updateActiveNav();
    if (window.mdInitBands)       window.mdInitBands();
    if (window.mdInitFilters)     window.mdInitFilters();
    if (window.mdInitSort)        window.mdInitSort();
    if (window.mdInitStudioSlider) window.mdInitStudioSlider();
    if (window.mdInitLightbox)    window.mdInitLightbox();
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
    var audio   = document.getElementById('audio-element');

    /* Remove stale listeners from previous init (AJAX navigation) */
    if (window._mdTracklistCleanup) {
      window._mdTracklistCleanup();
      window._mdTracklistCleanup = null;
    }

    function fmtTime(s) {
      if (!isFinite(s)) return '0:00';
      var m = Math.floor(s / 60);
      var sec = Math.floor(s % 60);
      return m + ':' + (sec < 10 ? '0' : '') + sec;
    }

    /* Compare audio paths ignoring protocol+host (audio.src is browser-normalized) */
    function sameAudioPath(a, b) {
      if (!a || !b) return false;
      function path(u) { try { return new URL(u).pathname; } catch (e) { return u; } }
      return path(a) === path(b);
    }

    /* Pre-load durations for tracks that don't have one */
    tl.querySelectorAll('.release-tracklist__item--playable').forEach(function (item) {
      var dur = item.querySelector('.release-tracklist__duration');
      if (dur && dur.textContent.trim()) return; /* already has a duration */
      var url = item.dataset.audioUrl;
      if (!url) return;
      var probe = new Audio();
      probe.preload = 'metadata';
      probe.addEventListener('loadedmetadata', function () {
        if (dur) dur.textContent = fmtTime(probe.duration);
        item.dataset.trackDuration = fmtTime(probe.duration);
        probe.src = '';
      });
      probe.src = url;
    });

    function clearActive() {
      tl.querySelectorAll('.release-tracklist__item--active').forEach(function (el) {
        el.classList.remove('release-tracklist__item--active', 'is-playing');
        var ip = el.querySelector('.icon-play');
        var pp = el.querySelector('.icon-pause');
        if (ip) ip.style.display = '';
        if (pp) pp.style.display = 'none';
        /* Reset progress bar and current time */
        var fill = el.querySelector('.release-tracklist__progress-fill');
        var ct = el.querySelector('.release-tracklist__current-time');
        if (fill) fill.style.width = '0%';
        if (ct) ct.textContent = '';
      });
    }

    function setActive(item, playing) {
      clearActive();
      item.classList.add('release-tracklist__item--active');
      if (playing) item.classList.add('is-playing');
      var ip = item.querySelector('.icon-play');
      var pp = item.querySelector('.icon-pause');
      if (playing) {
        if (ip) ip.style.display = 'none';
        if (pp) pp.style.display = 'block';
      }
    }

    /* Collect all playable tracks in this release */
    function getAllTracks() {
      var tracks = [];
      tl.querySelectorAll('.release-tracklist__item--playable').forEach(function (t) {
        tracks.push({
          title:    t.dataset.trackTitle,
          artist:   artists,
          audioUrl: t.dataset.audioUrl,
          artwork:  artwork,
          duration: t.dataset.trackDuration,
        });
      });
      return tracks;
    }

    /* Track click handlers */
    tl.querySelectorAll('.release-tracklist__item--playable').forEach(function (item) {
      item.addEventListener('click', function (e) {
        /* Ignore clicks on progress bar and queue button */
        if (e.target.closest('.release-tracklist__progress')) return;
        if (e.target.closest('.release-tracklist__queue-btn')) return;

        var isActive = item.classList.contains('release-tracklist__item--active');
        var isPlaying = item.classList.contains('is-playing');

        if (isActive && isPlaying) {
          audio.pause();
          item.classList.remove('is-playing');
          var ip = item.querySelector('.icon-play');
          var pp = item.querySelector('.icon-pause');
          if (ip) ip.style.display = '';
          if (pp) pp.style.display = 'none';
          return;
        }

        if (isActive && !isPlaying) {
          audio.play();
          item.classList.add('is-playing');
          var ip2 = item.querySelector('.icon-play');
          var pp2 = item.querySelector('.icon-pause');
          if (ip2) ip2.style.display = 'none';
          if (pp2) pp2.style.display = 'block';
          return;
        }

        /* Play entire tracklist starting from clicked track */
        var allTracks = getAllTracks();
        var clickedIdx = 0;
        tl.querySelectorAll('.release-tracklist__item--playable').forEach(function (t, i) {
          if (t === item) clickedIdx = i;
        });
        setActive(item, true);
        window.mdPlayer.playFrom(allTracks, clickedIdx);
      });

      /* Queue button — add single track to global queue without interrupting playback */
      var queueBtn = item.querySelector('.release-tracklist__queue-btn');
      if (queueBtn) {
        queueBtn.addEventListener('click', function (e) {
          e.stopPropagation();
          window.mdPlayer.addToQueue({
            title:    item.dataset.trackTitle,
            artist:   artists,
            audioUrl: item.dataset.audioUrl,
            artwork:  artwork,
            duration: item.dataset.trackDuration,
          });
          queueBtn.classList.add('queued');
          setTimeout(function () { queueBtn.classList.remove('queued'); }, 1200);
        });
      }

      /* Seek via inline progress bar */
      var prog = item.querySelector('.release-tracklist__progress');
      if (prog) {
        prog.addEventListener('click', function (e) {
          e.stopPropagation();
          if (!audio.duration) return;
          var rect = prog.getBoundingClientRect();
          var pct = Math.max(0, Math.min(1, (e.clientX - rect.left) / rect.width));
          audio.currentTime = pct * audio.duration;
        });
      }
    });

    /* --- Audio event handlers (named for cleanup) --- */
    function onTimeUpdate() {
      if (!tl.isConnected) return;
      var active = tl.querySelector('.release-tracklist__item--active');
      if (!active || !sameAudioPath(audio.src, active.dataset.audioUrl)) return;
      var fill = active.querySelector('.release-tracklist__progress-fill');
      var ct = active.querySelector('.release-tracklist__current-time');
      if (fill && audio.duration) {
        fill.style.width = (audio.currentTime / audio.duration * 100) + '%';
      }
      if (ct) ct.textContent = fmtTime(audio.currentTime);
    }

    function onMetadata() {
      if (!tl.isConnected) return;
      var active = tl.querySelector('.release-tracklist__item--active');
      if (!active || !sameAudioPath(audio.src, active.dataset.audioUrl)) return;
      /* Only fill duration if PHP didn't provide one */
      var dur = active.querySelector('.release-tracklist__duration');
      if (dur && !dur.textContent.trim() && audio.duration) dur.textContent = fmtTime(audio.duration);
    }

    function onPause() {
      if (!tl.isConnected) return;
      var active = tl.querySelector('.release-tracklist__item--active');
      if (active) {
        active.classList.remove('is-playing');
        var ip = active.querySelector('.icon-play');
        var pp = active.querySelector('.icon-pause');
        if (ip) ip.style.display = '';
        if (pp) pp.style.display = 'none';
      }
    }

    function onPlay() {
      if (!tl.isConnected) return;
      var active = tl.querySelector('.release-tracklist__item--active');
      /* Handle auto-advance: active item may not match current audio src */
      if (!active || !sameAudioPath(active.dataset.audioUrl, audio.src)) {
        if (active) {
          active.classList.remove('release-tracklist__item--active', 'is-playing');
          var oip = active.querySelector('.icon-play');
          var opp = active.querySelector('.icon-pause');
          if (oip) oip.style.display = '';
          if (opp) opp.style.display = 'none';
          var ofill = active.querySelector('.release-tracklist__progress-fill');
          var oct   = active.querySelector('.release-tracklist__current-time');
          if (ofill) ofill.style.width = '0%';
          if (oct)   oct.textContent   = '0:00';
        }
        active = null;
        tl.querySelectorAll('.release-tracklist__item--playable').forEach(function (item) {
          if (sameAudioPath(item.dataset.audioUrl, audio.src)) active = item;
        });
        if (active) active.classList.add('release-tracklist__item--active');
      }
      if (active) {
        active.classList.add('is-playing');
        var ip = active.querySelector('.icon-play');
        var pp = active.querySelector('.icon-pause');
        if (ip) ip.style.display = 'none';
        if (pp) pp.style.display = 'block';
        /* Do not overwrite static PHP duration */
      }
    }

    function onEnded() {
      if (!tl.isConnected) return;
      clearActive();
    }

    if (audio) {
      audio.addEventListener('timeupdate', onTimeUpdate);
      audio.addEventListener('loadedmetadata', onMetadata);
      audio.addEventListener('pause', onPause);
      audio.addEventListener('play', onPlay);
      audio.addEventListener('ended', onEnded);
      window._mdTracklistCleanup = function () {
        audio.removeEventListener('timeupdate', onTimeUpdate);
        audio.removeEventListener('loadedmetadata', onMetadata);
        audio.removeEventListener('pause', onPause);
        audio.removeEventListener('play', onPlay);
        audio.removeEventListener('ended', onEnded);
      };
    }

    /* Play all button */
    var playAll = document.getElementById('play-all-tracks');
    if (playAll) {
      playAll.addEventListener('click', function () {
        var tracks = [];
        tl.querySelectorAll('.release-tracklist__item--playable').forEach(function (item) {
          tracks.push({
            title: item.dataset.trackTitle,
            artist: artists,
            audioUrl: item.dataset.audioUrl,
            artwork: artwork,
            duration: item.dataset.trackDuration,
          });
        });
        window.mdPlayer.playAll(tracks);
        var first = tl.querySelector('.release-tracklist__item--playable');
        if (first) setActive(first, true);
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
