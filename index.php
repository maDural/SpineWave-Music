<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
$pageTitle = 'SpinWave Music';
$currentUser = current_user();
$featuredTracks = [];
try {
    $stmt = $pdo->query("SELECT t.id, t.title, t.artist_name, t.cover_url, t.source, t.source_id, t.embed_url
                         FROM tracks t WHERE t.is_active = 1 ORDER BY t.created_at DESC LIMIT 8");
    $featuredTracks = $stmt->fetchAll();
} catch (PDOException $e) {
    // The setup page explains how to import the database if it is not ready yet.
}
require_once __DIR__ . '/includes/header.php';
?>
<main class="main-content">
    <section class="welcome-row">
        <div>
            <p class="eyebrow">YOUR SOUNDTRACK STARTS HERE</p>
            <h1>Good music. <span>Good energy.</span></h1>
            <p class="muted">Discover music from artists around the world.</p>
        </div>
        <div class="welcome-actions">
            <a class="button button-primary" href="#discover">Explore music</a>
        </div>
    </section>

    <section class="quick-picks" aria-label="Quick navigation">
        <a class="quick-pick active" href="#discover">All</a>
        <a class="quick-pick" href="#youtube">YouTube</a>
        <a class="quick-pick" href="#sources">Music sources</a>
    </section>

    <section class="section-block" id="discover">
        <div class="section-heading">
            <div>
                <p class="eyebrow">MADE FOR YOUR MOOD</p>
                <h2>Discover music</h2>
            </div>
        </div>
        <div class="search-panel">
            <form id="music-search-form" class="search-form">
                <span class="search-symbol" aria-hidden="true">⌕</span>
                <input id="music-search" type="search" placeholder="Search songs, artists or paste a YouTube video ID..." autocomplete="off">
                <button class="button button-primary" type="submit">Search</button>
            </form>
            <p class="search-hint">Search real YouTube videos. Results include the creator, thumbnail and an official embedded player.</p>
            <div id="search-results" class="search-results" aria-live="polite"></div>
        </div>
    </section>

    <section class="section-block" id="youtube">
        <div class="section-heading">
            <div>
                <p class="eyebrow">FROM AROUND THE WORLD</p>
                <h2>YouTube music</h2>
            </div>
            <span class="source-tag">Official embedded player</span>
        </div>
        <div class="youtube-search-card">
            <div class="youtube-mark">▶</div>
            <div class="youtube-copy">
                <h3>Find your next favourite song</h3>
                <p>Search YouTube and play eligible videos here using the official YouTube player.</p>
            </div>
            <form id="youtube-search-form" class="youtube-form">
                <input id="youtube-query" type="search" placeholder="Try an artist or song name" required maxlength="120">
                <button class="button button-primary" type="submit">Search YouTube</button>
            </form>
            <div id="youtube-results" class="youtube-results" aria-live="polite"></div>
        </div>
    </section>

    <section class="section-block" id="sources">
        <div class="section-heading">
            <div>
                <p class="eyebrow">ONE PLACE, MANY VIBES</p>
                <h2>Music sources</h2>
            </div>
        </div>
        <div class="source-grid">
            <a href="#youtube" class="source-card" data-source="youtube">
                <div class="source-icon youtube-icon">▶</div>
                <h3>YouTube</h3>
                <p>Search videos and play through YouTube's official embedded player.</p>
                <span class="source-status available">Starter integration</span>
            </a>
            <a href="#discover" class="source-card" data-source="spotify">
                <div class="source-icon spotify-icon">●</div>
                <h3>Spotify</h3>
                <p>Spotify embeds and supported API features, subject to account and playback rules.</p>
                <span class="source-status planned">Next integration</span>
            </a>
            <a href="#discover" class="source-card" data-source="deezer">
                <div class="source-icon deezer-icon">♫</div>
                <h3>Deezer</h3>
                <p>Connect supported catalogue and preview features where available.</p>
                <span class="source-status planned">Next integration</span>
            </a>
            <a href="#discover" class="source-card" data-source="jamendo">
                <div class="source-icon jamendo-icon">♪</div>
                <h3>Jamendo</h3>
                <p>Independent music with licensing terms suitable for your use.</p>
                <span class="source-status planned">Next integration</span>
            </a>
        </div>
    </section>

    <section class="section-block" id="library">
        <div class="section-heading">
            <div>
                <p class="eyebrow">YOUR SPINWAVE LIBRARY</p>
                <h2>Recently added</h2>
            </div>
        </div>
        <?php if ($featuredTracks): ?>
            <div class="track-grid">
                <?php foreach ($featuredTracks as $track): ?>
                    <button class="track-card" type="button"
                        data-track='<?= htmlspecialchars(json_encode($track, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP), ENT_QUOTES, "UTF-8") ?>'>
                        <div class="track-art">
                            <?php if (!empty($track['cover_url'])): ?>
                                <img src="<?= htmlspecialchars($track['cover_url']) ?>" alt="" loading="lazy">
                            <?php else: ?><span>♫</span><?php endif; ?>
                            <span class="art-play">▶</span>
                        </div>
                        <strong><?= htmlspecialchars($track['title']) ?></strong>
                        <span><?= htmlspecialchars($track['artist_name'] ?: 'Unknown artist') ?></span>
                    </button>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state"><div class="empty-note">♫</div><h3>Your library is ready for its first track</h3><p>Add a licensed track or use YouTube search above to start listening.</p></div>
        <?php endif; ?>
    </section>
    <footer class="site-footer"><span>© <?= date('Y') ?> SpinWave Music</span><span>Designed by Mthetheleli</span><span>Discover • Play • Repeat</span></footer>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>