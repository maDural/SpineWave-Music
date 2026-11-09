/* SpinWave Music - Stage 2 & 3 YouTube search, IFrame playback, and Source routing. */
(() => {
    'use strict';

    let youtubePlayer = null;
    let playerReady = false;
    let pendingVideoId = null;
    let currentTrack = null;
    let currentQueue = [];
    let currentIndex = -1;
    let isPlaying = false;

    const byId = (id) => document.getElementById(id);
    const cfg = window.SPINWAVE || { loggedIn: false, csrfToken: '', playlists: [] };

    function setStatus(message) {
        const status = byId('player-status');
        if (status) status.textContent = message;
    }

    function updatePlayButton() {
        const button = byId('play-button');
        if (!button) return;
        button.textContent = isPlaying ? 'Ⅱ' : '▶';
        button.setAttribute('aria-label', isPlaying ? 'Pause playback' : 'Start playback');
    }

    function setNowPlaying(track) {
        currentTrack = track;
        byId('player-title').textContent = track.title || 'Unknown video';
        byId('player-artist').textContent = track.artist_name || 'YouTube creator';

        const art = byId('mini-art');
        art.replaceChildren();
        if (track.cover_url) {
            const image = document.createElement('img');
            image.src = track.cover_url;
            image.alt = '';
            image.referrerPolicy = 'no-referrer';
            art.appendChild(image);
        } else {
            art.textContent = '♫';
        }
    }

    function showPlayerDock() {
        const dock = byId('player-dock');
        if (dock) dock.classList.add('is-visible');
    }

    function playTrack(track, queue = [], index = -1) {
        if (!track || track.source !== 'youtube' || !track.source_id) {
            setStatus('Choose a YouTube search result to play.');
            return;
        }

        currentQueue = Array.isArray(queue) ? queue : [];
        currentIndex = Number.isInteger(index) ? index : -1;
        setNowPlaying(track);
        showPlayerDock();

        if (!playerReady || !youtubePlayer) {
            pendingVideoId = track.source_id;
            setStatus('YouTube player is loading. Your selection is ready.');
            return;
        }

        pendingVideoId = null;
        setStatus('Loading video from YouTube…');
        youtubePlayer.loadVideoById({ videoId: track.source_id });
    }

    function playNext() {
        if (!currentQueue.length) {
            setStatus('Search for songs to build a playback queue.');
            return;
        }
        const nextIndex = currentIndex + 1;
        if (nextIndex >= currentQueue.length) {
            setStatus('You have reached the end of this search queue.');
            isPlaying = false;
            updatePlayButton();
            return;
        }
        playTrack(currentQueue[nextIndex], currentQueue, nextIndex);
    }

    function playPrevious() {
        if (!currentQueue.length) {
            if (youtubePlayer && playerReady && currentTrack) youtubePlayer.seekTo(0, true);
            else setStatus('There is no previous track in the queue.');
            return;
        }
        if (currentIndex <= 0) {
            if (youtubePlayer && playerReady) youtubePlayer.seekTo(0, true);
            return;
        }
        const previousIndex = currentIndex - 1;
        playTrack(currentQueue[previousIndex], currentQueue, previousIndex);
    }

    async function saveSearchTrack(track, action, playlistId = null) {
        if (!cfg.loggedIn) {
            window.location.href = 'login.php';
            return;
        }
        const response = await fetch('api/library-save.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-Token': cfg.csrfToken || ''
            },
            body: JSON.stringify({
                action,
                playlist_id: playlistId,
                source: track.source || 'youtube',
                source_id: track.source_id,
                title: track.title,
                artist_name: track.artist_name,
                cover_url: track.cover_url
            })
        });
        const data = await response.json();
        if (!response.ok) throw new Error(data.error || 'Could not save this track.');
        setStatus(data.message || 'Saved to your library.');
    }

    function renderResults(container, items) {
        container.replaceChildren();
        if (!items.length) {
            const empty = document.createElement('p');
            empty.className = 'search-hint';
            empty.textContent = 'No embeddable video results were returned. Try a different search.';
            container.appendChild(empty);
            return;
        }

        items.forEach((track, index) => {
            const row = document.createElement('article');
            row.className = 'result-row';
            const image = document.createElement('img');
            image.src = track.cover_url || '';
            image.alt = '';
            image.loading = 'lazy';
            image.referrerPolicy = 'no-referrer';
            const info = document.createElement('div');
            info.className = 'result-info';
            const title = document.createElement('strong');
            title.textContent = track.title || 'Untitled video';
            const creator = document.createElement('span');
            creator.textContent = `${track.artist_name || 'YouTube creator'} · YouTube`;
            info.append(title, creator);

            const actions = document.createElement('div');
            actions.className = 'result-actions';
            const playButton = document.createElement('button');
            playButton.type = 'button';
            playButton.className = 'result-play-button';
            playButton.textContent = 'Play';
            playButton.addEventListener('click', () => {
                playTrack(track, items, index);
                document.querySelectorAll('.result-play-button').forEach((b) => b.textContent = 'Play');
                playButton.textContent = 'Selected';
            });
            actions.appendChild(playButton);

            if (cfg.loggedIn) {
                const likeButton = document.createElement('button');
                likeButton.type = 'button';
                likeButton.className = 'result-save-button';
                likeButton.textContent = '♡';
                likeButton.title = 'Save to liked songs';
                likeButton.addEventListener('click', async () => {
                    likeButton.disabled = true;
                    try { await saveSearchTrack(track, 'favourite'); likeButton.textContent = '♥'; }
                    catch (error) { setStatus(error.message); }
                    finally { likeButton.disabled = false; }
                });
                actions.appendChild(likeButton);

                if (Array.isArray(cfg.playlists) && cfg.playlists.length) {
                    const select = document.createElement('select');
                    select.className = 'result-playlist-select';
                    select.setAttribute('aria-label', 'Add to playlist');
                    const placeholder = document.createElement('option');
                    placeholder.value = '';
                    placeholder.textContent = '+ Playlist';
                    select.appendChild(placeholder);
                    cfg.playlists.forEach((playlist) => {
                        const option = document.createElement('option');
                        option.value = String(playlist.id);
                        option.textContent = playlist.name;
                        select.appendChild(option);
                    });
                    select.addEventListener('change', async () => {
                        if (!select.value) return;
                        try { await saveSearchTrack(track, 'playlist', Number(select.value)); select.value = ''; }
                        catch (error) { setStatus(error.message); }
                    });
                    actions.appendChild(select);
                }
            }
            row.append(image, info, actions);
            container.appendChild(row);
        });
    }

    async function searchYouTube(query, target) {
        const term = String(query || '').trim();
        if (!term) {
            target.textContent = 'Enter a song or artist name first.';
            return;
        }
        target.innerHTML = '<p class="search-hint">Searching YouTube…</p>';

        try {
            const response = await fetch(`api/youtube-search.php?q=${encodeURIComponent(term)}`, {
                headers: { 'Accept': 'application/json' }
            });
            const data = await response.json();
            if (!response.ok) throw new Error(data.error || 'YouTube search failed.');
            renderResults(target, data.items || []);
            if (data.items && data.items.length) setStatus(`${data.items.length} YouTube results ready. Select one to play.`);
        } catch (error) {
            target.replaceChildren();
            const message = document.createElement('p');
            message.className = 'search-hint error-message';
            message.textContent = error.message || 'Something went wrong while searching.';
            target.appendChild(message);
        }
    }

    window.onYouTubeIframeAPIReady = function () {
        const host = byId('youtube-player-host');
        if (!host || !window.YT || !YT.Player) return;

        youtubePlayer = new YT.Player('youtube-player-host', {
            width: '320',
            height: '200',
            playerVars: { autoplay: 0, controls: 1, playsinline: 1, rel: 0 },
            events: {
                onReady: () => {
                    playerReady = true;
                    const volume = byId('volume-control');
                    if (volume) youtubePlayer.setVolume(Number(volume.value));
                    if (pendingVideoId) {
                        const videoId = pendingVideoId;
                        pendingVideoId = null;
                        youtubePlayer.loadVideoById({ videoId });
                    }
                },
                onStateChange: (event) => {
                    if (event.data === YT.PlayerState.PLAYING) {
                        isPlaying = true;
                        updatePlayButton();
                        setStatus('Playing from YouTube');
                    } else if (event.data === YT.PlayerState.PAUSED) {
                        isPlaying = false;
                        updatePlayButton();
                        setStatus('Paused');
                    } else if (event.data === YT.PlayerState.BUFFERING) {
                        setStatus('Buffering YouTube video…');
                    } else if (event.data === YT.PlayerState.ENDED) {
                        isPlaying = false;
                        updatePlayButton();
                        playNext();
                    } else if (event.data === YT.PlayerState.CUED) {
                        isPlaying = false;
                        updatePlayButton();
                        setStatus('Video ready. Press play in the YouTube player or SpinWave controls.');
                    }
                },
                onError: (event) => {
                    isPlaying = false;
                    updatePlayButton();
                    const reasons = {
                        2: 'The video ID is invalid.',
                        5: 'The video cannot be played in the HTML5 player.',
                        100: 'The video was removed or is private.',
                        101: 'The owner does not allow this video to play in embedded players.',
                        150: 'The owner does not allow this video to play in embedded players.'
                    };
                    setStatus(reasons[event.data] || 'YouTube could not play this video. Try another result.');
                }
            }
        });
    };

    document.addEventListener('DOMContentLoaded', () => {
        const youtubeForm = byId('youtube-search-form');
        const mainForm = byId('music-search-form');

        youtubeForm?.addEventListener('submit', (event) => {
            event.preventDefault();
            searchYouTube(byId('youtube-query').value, byId('youtube-results'));
        });

        mainForm?.addEventListener('submit', (event) => {
            event.preventDefault();
            const query = byId('music-search').value.trim();
            if (!query) return;
            byId('youtube-query').value = query;
            byId('youtube').scrollIntoView({ behavior: 'smooth', block: 'start' });
            searchYouTube(query, byId('youtube-results'));
        });

        document.querySelectorAll('.track-card, .track-card-inline').forEach((card) => {
            card.addEventListener('click', () => {
                try {
                    const track = JSON.parse(card.dataset.track);
                    if (track.source === 'youtube') playTrack(track);
                    else {
                        setNowPlaying(track);
                        setStatus('This library item does not yet have a supported playback integration.');
                    }
                } catch {
                    setStatus('Could not load this library track.');
                }
            });
        });

        byId('play-button')?.addEventListener('click', () => {
            if (!youtubePlayer || !playerReady || !currentTrack) {
                setStatus('Search for a YouTube track and select Play first.');
                return;
            }
            if (isPlaying) youtubePlayer.pauseVideo();
            else youtubePlayer.playVideo();
        });

        byId('next-button')?.addEventListener('click', playNext);
        byId('previous-button')?.addEventListener('click', playPrevious);
        byId('volume-control')?.addEventListener('input', (event) => {
            if (youtubePlayer && playerReady) youtubePlayer.setVolume(Number(event.target.value));
        });

        // Handle the Music Source Cards click behavior
        document.querySelectorAll('.source-card').forEach(card => {
            card.addEventListener('click', (e) => {
                const source = card.getAttribute('data-source');
                
                // YouTube card uses the default anchor link
                if (source === 'youtube') return;

                // Other sources: prevent default anchor jump, scroll to search, and notify
                e.preventDefault();
                
                // If we are on a different page, redirect to index.php#discover
                if (!byId('music-search')) {
                    window.location.href = 'index.php#discover';
                    return;
                }

                const searchInput = byId('music-search');
                const discoverSection = byId('discover');
                
                if (searchInput && discoverSection) {
                    discoverSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    
                    // Focus the input after a small delay to allow scroll to finish
                    setTimeout(() => {
                        searchInput.focus();
                        const title = source.charAt(0).toUpperCase() + source.slice(1);
                        setStatus(`${title} integration is coming soon! For now, try searching YouTube.`);
                    }, 400);
                }
            });
        });
    });
})();
