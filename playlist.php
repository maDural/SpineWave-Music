<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
$user = require_login();
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) { http_response_code(404); exit('Playlist not found.'); }
$stmt = $pdo->prepare('SELECT id,name,description,created_at FROM playlists WHERE id=:id AND user_id=:uid');
$stmt->execute(['id'=>$id,'uid'=>$user['id']]);
$playlist = $stmt->fetch();
if (!$playlist) { http_response_code(404); exit('Playlist not found.'); }
$pageTitle = $playlist['name'] . ' | SpinWave Music';
$stmt = $pdo->prepare('SELECT t.id,t.title,t.artist_name,t.cover_url,t.source,t.source_id,pt.added_at FROM playlist_tracks pt JOIN tracks t ON t.id=pt.track_id WHERE pt.playlist_id=:id ORDER BY pt.position,pt.added_at DESC');
$stmt->execute(['id'=>$id]);
$tracks = $stmt->fetchAll();
$notice = take_flash();
require __DIR__ . '/includes/header.php';
?>
<main class="main-content">
<section class="section-block profile-hero"><div class="playlist-cover large">♫</div><div><p class="eyebrow">SPINWAVE PLAYLIST</p><h1><?= e($playlist['name']) ?></h1><p class="muted"><?= e($playlist['description'] ?: 'A personal collection') ?> · <?= count($tracks) ?> tracks</p></div></section>
<?php if ($notice): ?><p class="notice success"><?= e($notice['message']) ?></p><?php endif; ?>
<div class="playlist-toolbar"><a href="playlists.php">← All playlists</a><form method="post" action="library-action.php" onsubmit="return confirm('Delete this playlist?');"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete_playlist"><input type="hidden" name="playlist_id" value="<?= (int)$id ?>"><button class="text-button danger-text" type="submit">Delete playlist</button></form></div>
<?php if ($tracks): ?><div class="library-track-list"><?php foreach ($tracks as $track): ?><div class="library-track-row"><button class="track-card-inline" type="button" data-track="<?= e(json_encode($track, JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_TAG|JSON_HEX_AMP)) ?>"><span class="mini-track-art">♫</span><span><strong><?= e($track['title']) ?></strong><small><?= e($track['artist_name']) ?></small></span></button><form method="post" action="library-action.php"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="remove_playlist_track"><input type="hidden" name="playlist_id" value="<?= (int)$id ?>"><input type="hidden" name="track_id" value="<?= (int)$track['id'] ?>"><button class="text-button" type="submit">Remove</button></form></div><?php endforeach; ?></div><?php else: ?><div class="empty-state"><div class="empty-note">♫</div><h3>This playlist is empty</h3><p>Search for music on the home page and add tracks to this playlist.</p><a class="button button-primary" href="index.php#discover">Discover music</a></div><?php endif; ?>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
