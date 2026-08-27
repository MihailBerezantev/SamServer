/**
 * Mango Dragon — Audio Player
 * Persistent HTML5 audio player with queue management.
 */
(function () {
  'use strict';

  var audio = document.getElementById('audio-element');
  var playerBar = document.getElementById('player-bar');
  if (!audio || !playerBar) return;

  /* ---------- state ---------- */
  var state = {
    queue: [],
    currentIndex: -1,
    isPlaying: false,
    shuffle: false,
    repeat: 'off', // off | all | one
    volume: 0.8,
  };

  /* ---------- DOM refs ---------- */
  var $ = function (id) { return document.getElementById(id); };
  var els = {
    play:         $('player-play'),
    prev:         $('player-prev'),
    next:         $('player-next'),
    shuffle:      $('player-shuffle'),
    repeat:       $('player-repeat'),
    title:        $('player-title'),
    artist:       $('player-artist'),
    artworkImg:   $('player-artwork-img'),
    currentTime:  $('player-current-time'),
    duration:     $('player-duration'),
    progressBar:  $('player-progress-bar'),
    progressFill: $('player-progress-fill'),
    volumeBtn:    $('player-volume-btn'),
    volumeBar:    $('player-volume-bar'),
    volumeFill:   $('player-volume-fill'),
    queueBtn:     $('player-queue-btn'),
    queuePanel:   $('player-queue'),
    queueList:    $('queue-list'),
    queueClear:   $('queue-clear'),
  };

  /* ---------- helpers ---------- */
  function fmtTime(s) {
    if (!isFinite(s)) return '0:00';
    var m = Math.floor(s / 60);
    var sec = Math.floor(s % 60);
    return m + ':' + (sec < 10 ? '0' : '') + sec;
  }

  /* ---------- core ---------- */
  function loadTrack(idx) {
    if (idx < 0 || idx >= state.queue.length) return;
    state.currentIndex = idx;
    var t = state.queue[idx];
    audio.src = t.audioUrl;
    els.currentTime.textContent  = '0:00';
    els.duration.textContent     = t.duration || '0:00';
    els.progressFill.style.width = '0%';
    els.title.textContent   = t.title  || '\u2014';
    els.artist.textContent  = t.artist || '\u2014';
    if (t.artwork) { els.artworkImg.src = t.artwork; els.artworkImg.alt = t.title; }
    playerBar.classList.add('player-bar--active');
    renderQueue();
  }

  function play()  { if (!audio.src) return; audio.play().then(function(){ state.isPlaying=true; updatePlayIcon(); }).catch(function(){}); }
  function pause() { audio.pause(); state.isPlaying=false; updatePlayIcon(); }
  function togglePlay() {
    if (state.queue.length === 0) {
      var allTracks = (window.mdPlayerData && window.mdPlayerData.tracks) ? window.mdPlayerData.tracks : [];
      if (allTracks.length) {
        var idx = Math.floor(Math.random() * allTracks.length);
        state.queue = allTracks.slice();
        loadTrack(idx);
        play();
      }
      return;
    }
    state.isPlaying ? pause() : play();
  }

  function nextTrack() {
    var n;
    if (state.shuffle) { n = Math.floor(Math.random() * state.queue.length); }
    else { n = state.currentIndex + 1; }
    if (n >= state.queue.length) {
      if (state.repeat === 'all') n = 0; else { pause(); return; }
    }
    loadTrack(n); play();
  }

  function prevTrack() {
    if (audio.currentTime > 3) { audio.currentTime = 0; return; }
    var p = state.currentIndex - 1;
    if (p < 0) p = state.queue.length - 1;
    loadTrack(p); play();
  }

  function updatePlayIcon() {
    var ip = els.play.querySelector('.icon-play');
    var pp = els.play.querySelector('.icon-pause');
    if (ip) ip.style.display = state.isPlaying ? 'none' : '';
    if (pp) pp.style.display = state.isPlaying ? '' : 'none';
  }

  /* ---------- audio events ---------- */
  audio.addEventListener('play', function () {
    state.isPlaying = true;
    updatePlayIcon();
  });

  audio.addEventListener('pause', function () {
    state.isPlaying = false;
    updatePlayIcon();
  });

  audio.addEventListener('timeupdate', function () {
    if (!audio.duration) return;
    var pct = (audio.currentTime / audio.duration) * 100;
    els.progressFill.style.width = pct + '%';
    els.currentTime.textContent = fmtTime(audio.currentTime);
  });

  audio.addEventListener('loadedmetadata', function () {
    /* Only use audio.duration as fallback if no PHP duration was provided */
    var t = state.queue[state.currentIndex];
    if (!t || !t.duration) {
      els.duration.textContent = fmtTime(audio.duration);
    }
  });

  audio.addEventListener('ended', function () {
    if (state.repeat === 'one') { audio.currentTime = 0; play(); }
    else nextTrack();
  });

  /* ---------- seek ---------- */
  els.progressBar.addEventListener('click', function (e) {
    var rect = els.progressBar.getBoundingClientRect();
    var pct  = (e.clientX - rect.left) / rect.width;
    if (audio.duration) audio.currentTime = pct * audio.duration;
  });

  /* ---------- volume ---------- */
  audio.volume = state.volume;
  els.volumeFill.style.width = (state.volume * 100) + '%';

  els.volumeBar.addEventListener('click', function (e) {
    var rect = els.volumeBar.getBoundingClientRect();
    state.volume = Math.max(0, Math.min(1, (e.clientX - rect.left) / rect.width));
    audio.volume = state.volume;
    els.volumeFill.style.width = (state.volume * 100) + '%';
  });

  els.volumeBtn.addEventListener('click', function () {
    if (audio.volume > 0) { audio.volume = 0; els.volumeFill.style.width = '0%'; }
    else { audio.volume = state.volume; els.volumeFill.style.width = (state.volume * 100) + '%'; }
  });

  /* ---------- control buttons ---------- */
  els.play.addEventListener('click', togglePlay);
  els.next.addEventListener('click', nextTrack);
  els.prev.addEventListener('click', prevTrack);

  els.shuffle.addEventListener('click', function () {
    state.shuffle = !state.shuffle;
    els.shuffle.classList.toggle('active', state.shuffle);
  });

  els.repeat.addEventListener('click', function () {
    var modes = ['off','all','one'];
    state.repeat = modes[(modes.indexOf(state.repeat) + 1) % 3];
    els.repeat.classList.toggle('active', state.repeat !== 'off');
    els.repeat.dataset.mode = state.repeat;
  });

  /* ---------- queue panel ---------- */
  els.queueBtn.addEventListener('click', function () {
    var open = els.queuePanel.getAttribute('aria-hidden') === 'false';
    els.queuePanel.setAttribute('aria-hidden', open ? 'true' : 'false');
    els.queueBtn.setAttribute('aria-expanded', String(!open));
  });

  els.queueClear.addEventListener('click', function () {
    state.queue = []; state.currentIndex = -1;
    pause(); audio.removeAttribute('src');
    els.title.textContent = '\u2014'; els.artist.textContent = '\u2014';
    playerBar.classList.remove('player-bar--active');
    renderQueue();
  });

  function renderQueue() {
    els.queueList.innerHTML = '';
    state.queue.forEach(function (t, i) {
      var li = document.createElement('li');
      li.className = 'queue-item' + (i === state.currentIndex ? ' queue-item--active' : '');
      li.innerHTML =
        '<span class="queue-item__title">' + escHtml(t.title) + '</span>' +
        '<span class="queue-item__artist">' + escHtml(t.artist || '') + '</span>' +
        '<span class="queue-item__duration">' + escHtml(t.duration || '') + '</span>';
      li.addEventListener('click', function () { loadTrack(i); play(); });
      els.queueList.appendChild(li);
    });
  }

  function escHtml(s) {
    var d = document.createElement('div'); d.textContent = s; return d.innerHTML;
  }

  /* ---------- public API ---------- */
  window.mdPlayer = {
    addToQueue: function (track) {
      state.queue.push(track);
      renderQueue();
      /* Make player bar visible so the user sees the queue */
      playerBar.classList.add('player-bar--active');
      /* Auto-start if player is completely idle */
      if (!state.isPlaying && (state.currentIndex < 0 || !audio.src)) {
        loadTrack(state.queue.length - 1);
        play();
      }
      /* Open queue panel briefly to confirm */
      els.queuePanel.setAttribute('aria-hidden', 'false');
      els.queueBtn.setAttribute('aria-expanded', 'true');
    },
    playTrack:  function (track) { state.queue.push(track); loadTrack(state.queue.length - 1); play(); },
    playAll:    function (tracks) { state.queue = tracks.slice(); if (state.queue.length) { loadTrack(0); play(); } },
    playFrom:   function (tracks, index) { state.queue = tracks.slice(); loadTrack(index || 0); play(); },
    getState:   function () { return JSON.parse(JSON.stringify(state)); },
  };
})();
