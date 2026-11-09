<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/playlists.php';

$user = require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'like') {
        try {
            $trackId = get_or_create_track($pdo, [
                'source' => 'youtube',
                'source_id' => (string)($_POST['source_id'] ?? ''),
                'title' => (string)($_POST['title'] ?? ''),
                'artist_name' => (string)($_POST['artist_name'] ?? ''),
                'cover_url' => (string)($_POST['cover_url'] ?? ''),
                'embed_url' => (string)($_POST['embed_url'] ?? ''),
            ]);
            if ($trackId) {
                $pdo->prepare('INSERT IGNORE INTO user_favourites (user_id, track_id) VALUES (?, ?)')
                    ->execute([$user['id'], $trackId]);
                flash('success', 'Saved to your liked songs.');
            } else {
                flash('error', 'Could not save that track.');
            }
        } catch (PDOException $e) {
            error_log('Like failed: ' . $e->getMessage());
            flash('error', 'Could not save that track.');
        }
    } elseif ($action === 'unlike') {
        $trackId = (int)($_POST['track_id'] ?? 0);
        $pdo->prepare('DELETE FROM user_favourites WHERE user_id = ? AND track_id = ?')
            ->execute([$user['id'], $trackId]);
        flash('success', 'Removed from liked songs.');
    }
    header('Location: likes.php');
    exit;
}

$stmt = $pdo->prepare('SELECT t.*, f.created_at AS liked_at
                       FROM user_favourites f JOIN tracks t ON t.id = f.track_id
                       WHERE f.user_id = ? ORDER BY f.created_at DESC');
$stmt->execute([$user['id']]);
$liked = $stmt->fetchAll();

$pageTitle = 'Liked songs · SpinWave Music';
require_once __DIR__ . '/includes/header.php';
?>
<main class="main-content">
    <section class="section-block">
        <p class="eyebrow">YOUR COLLECTION</p>
        <h1>Liked songs</h1>
        <?php if ($liked): ?>
            <div class="playlist-tracks">
                <?php foreach ($liked as $i => $track): ?>
                    <div class="result-row">
                        <span class="track-index"><?= $i + 1 ?></span>
                        <?php if (!empty($track['cover_url'])): ?>
                            <img src="<?= htmlspecialchars($track['cover_url']) ?>" alt="" loading="lazy" referrerpolicy="no-referrer">
                        <?php endif; ?>
                        <div class="result-info">
                            <strong><?= htmlspecialchars($track['title']) ?></strong>
                            <span><?= htmlspecialchars($track['artist_name'] ?: 'Unknown artist') ?></span>
                        </div>
                        <?php if ($track['source'] === 'youtube' && $track['source_id']): ?>
                            <button type="button" class="result-play-button track-play"
                                data-track='<?= htmlspecialchars(json_encode([
                                    "source" => "youtube",
                                    "source_id" => $track['source_id'],
                                    "title" => $track['title'],
                                    "artist_name" => $track['artist_name'],
                                    "cover_url" => $track['cover_url'],
                                ], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG), ENT_QUOTES, 'UTF-8') ?>'>Play</button>
                        <?php endif; ?>
                        <form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="unlike">
                            <input type="hidden" name="track_id" value="<?= (int)$track['id'] ?>">
                            <button type="submit" class="link-danger">Remove</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state"><div class="empty-note">♫</div><h3>No liked songs yet</h3><p>Tap the heart on search results to save them here.</p></div>
        <?php endif; ?>
    </section>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>