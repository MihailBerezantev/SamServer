<?php
/**
 * Template Part: Player Bar — Persistent sticky audio player
 */
?>
<!-- Persistent Audio Player -->
<div class="player-bar" id="player-bar" role="complementary" aria-label="<?php esc_attr_e( 'Lecteur audio', 'mango-dragon' ); ?>">
    <audio id="audio-element" preload="auto"></audio>

    <div class="player-container">
        <!-- Track Info -->
        <div class="player-info">
            <div class="player-artwork" id="player-artwork">
                <img src="<?php echo esc_url( MD_URI . '/assets/images/logo.svg' ); ?>" alt="" id="player-artwork-img">
            </div>
            <div class="player-meta">
                <span class="player-title" id="player-title">—</span>
                <span class="player-artist" id="player-artist">—</span>
            </div>
        </div>

        <!-- Controls -->
        <div class="player-controls">
            <button class="player-btn" id="player-prev" aria-label="Piste précédente">
                <svg viewBox="0 0 24 24"><path d="M6 6h2v12H6zm3.5 6l8.5 6V6z"/></svg>
            </button>
            <button class="player-btn player-btn--play" id="player-play" aria-label="Lecture">
                <svg viewBox="0 0 24 24" class="icon-play"><path d="M8 5v14l11-7z"/></svg>
                <svg viewBox="0 0 24 24" class="icon-pause" style="display:none;"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/></svg>
            </button>
            <button class="player-btn" id="player-next" aria-label="Piste suivante">
                <svg viewBox="0 0 24 24"><path d="M6 18l8.5-6L6 6v12zM16 6v12h2V6h-2z"/></svg>
            </button>
            <button class="player-btn" id="player-shuffle" aria-label="Lecture aléatoire">
                <svg viewBox="0 0 24 24"><path d="M10.59 9.17L5.41 4 4 5.41l5.17 5.17 1.42-1.41zM14.5 4l2.04 2.04L4 18.59 5.41 20 17.96 7.46 20 9.5V4h-5.5zm.33 9.41l-1.41 1.41 3.13 3.13L14.5 20H20v-5.5l-2.04 2.04-3.13-3.13z"/></svg>
            </button>
            <button class="player-btn" id="player-repeat" aria-label="Répéter">
                <svg viewBox="0 0 24 24"><path d="M7 7h10v3l4-4-4-4v3H5v6h2V7zm10 10H7v-3l-4 4 4 4v-3h12v-6h-2v4z"/></svg>
            </button>
        </div>

        <!-- Progress Bar -->
        <div class="player-progress">
            <span class="player-time" id="player-current-time">0:00</span>
            <div class="player-progress-bar" id="player-progress-bar" role="slider" aria-label="Progression" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" tabindex="0">
                <div class="player-progress-fill" id="player-progress-fill"></div>
            </div>
            <span class="player-time" id="player-duration">0:00</span>
        </div>

        <!-- Volume -->
        <div class="player-volume">
            <button class="player-btn" id="player-volume-btn" aria-label="Volume">
                <svg viewBox="0 0 24 24" class="icon-volume"><path d="M3 9v6h4l5 5V4L7 9H3zm13.5 3c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02zM14 3.23v2.06c2.89.86 5 3.54 5 6.71s-2.11 5.85-5 6.71v2.06c4.01-.91 7-4.49 7-8.77s-2.99-7.86-7-8.77z"/></svg>
            </button>
            <div class="player-volume-bar" id="player-volume-bar" role="slider" aria-label="Volume" aria-valuemin="0" aria-valuemax="100" aria-valuenow="80" tabindex="0">
                <div class="player-volume-fill" id="player-volume-fill"></div>
            </div>
        </div>

        <!-- Queue Toggle -->
        <button class="player-btn player-queue-btn" id="player-queue-btn" aria-label="File d'attente" aria-expanded="false" aria-controls="player-queue">
            <svg viewBox="0 0 24 24"><path d="M15 6H3v2h12V6zm0 4H3v2h12v-2zM3 16h8v-2H3v2zM17 6v8.18c-.31-.11-.65-.18-1-.18-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3V8h3V6h-5z"/></svg>
        </button>
    </div>

    <!-- Queue Panel -->
    <div class="player-queue" id="player-queue" aria-hidden="true">
        <div class="queue-header">
            <h3>File d'attente</h3>
            <button class="queue-clear" id="queue-clear" aria-label="Vider la file d'attente">Vider</button>
        </div>
        <ul class="queue-list" id="queue-list"></ul>
    </div>
</div>
