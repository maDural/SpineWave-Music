<?php /* Shared player UI used on every Stage 3 page. */ ?>
<div class="player-dock" id="player-dock">
    <div id="youtube-player-host" class="youtube-player-host" aria-label="Official YouTube embedded video player"></div>
    <div class="player-dock-note">Playback is provided by YouTube. Availability and controls are subject to YouTube's terms.</div>
</div>
<div class="player-bar" id="player-bar">
    <div class="now-playing">
        <div class="mini-art" id="mini-art">♫</div>
        <div class="now-playing-text"><strong id="player-title">Nothing playing</strong><span id="player-artist">Choose a song to get started</span></div>
    </div>
    <div class="player-center">
        <div class="player-controls">
            <button type="button" class="player-control" id="previous-button" aria-label="Previous track">|◀</button>
            <button type="button" class="play-button" id="play-button" aria-label="Play or pause">▶</button>
            <button type="button" class="player-control" id="next-button" aria-label="Next track">▶|</button>
        </div>
        <div class="player-progress"><span id="player-status" role="status">Ready when you are</span><div class="progress-track"><div class="progress-fill" id="progress-fill"></div></div></div>
    </div>
    <div class="player-volume"><span>♪</span><input id="volume-control" type="range" min="0" max="100" value="70" aria-label="Volume"></div>
</div>
