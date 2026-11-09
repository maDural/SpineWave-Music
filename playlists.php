<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
$user = require_login();
$pageTitle = 'My playlists | SpinWave Music';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim((string)($_POST['name'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    if ($name === '' || strlen($name) > 120) $error = 'Playlist name is required and must be 120 characters or fewer.';
    elseif (strlen($description) > 500) $error = 'Description must be 500 characters or fewer.';
    else {
        $stmt = $pdo->prepare('INSERT INTO playlists (user_id,name,description) VALUES (:uid,:name,:description)');
        $stmt->execute(['uid'=>$user['id'],'name'=>$name,'description'=>$description ?: null]);
        flash('Playlist created.');
        redirect('playlists.php');
    }
}
$stmt = $pdo->prepare('SELECT p.id,p.name,p.description,p.created_at,COUNT(pt.track_id) AS track_count FROM playlists p LEFT JOIN playlist_tracks pt ON pt.playlist_id=p.id WHERE p.user_id=:uid GROUP BY p.id ORDER BY p.created_at DESC');
$stmt->execute(['uid'=>$user['id']]);
$playlists = $stmt->fetchAll();
$notice = take_flash();
require __DIR__ . '/includes/header.php';
?>
<main class="main-content">
<section class="section-block"><p class="eyebrow">YOUR COLLECTION</p><h1>My playlists</h1><p class="muted">Create collections for every mood and moment.</p></section>
<?php if ($notice): ?><p class="notice success"><?= e($notice['message']) ?></p><?php endif; ?>
<?php if ($error): ?><p class="notice error"><?= e($error) ?></p><?php endif; ?>
<div class="profile-grid">
<section class="panel-card"><h2>Create a playlist</h2><form method="post" class="stack-form"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><label>Playlist name<input name="name" required maxlength="120" placeholder="Late night favourites"></label><label>Description<textarea name="description" rows="3" maxlength="500" placeholder="What's the vibe?"></textarea></label><button class="button button-primary" type="submit">Create playlist</button></form></section>
<section class="panel-card"><h2>Your playlists</h2><?php if ($playlists): ?><div class="playlist-list"><?php foreach ($playlists as $pl): ?><a class="playlist-item" href="playlist.php?id=<?= (int)$pl['id'] ?>"><span class="playlist-cover">♫</span><span class="playlist-meta"><strong><?= e($pl['name']) ?></strong><small><?= (int)$pl['track_count'] ?> tracks · <?= e($pl['description'] ?: 'Your personal playlist') ?></small></span><span>›</span></a><?php endforeach; ?></div><?php else: ?><p class="muted">Your first playlist is one click away.</p><?php endif; ?></section>
</div>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
