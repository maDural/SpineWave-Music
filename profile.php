<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
$user = require_login();
$pageTitle = 'Your profile | SpinWave Music';
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $displayName = trim((string)($_POST['display_name'] ?? ''));
    $bio = trim((string)($_POST['bio'] ?? ''));
    if ($displayName === '' || strlen($displayName) > 100) $errors[] = 'Display name is required and must be 100 characters or fewer.';
    if (strlen($bio) > 500) $errors[] = 'Bio must be 500 characters or fewer.';
    if (!$errors) {
        $stmt = $pdo->prepare('UPDATE users SET display_name = :name, bio = :bio WHERE id = :id');
        $stmt->execute(['name'=>$displayName,'bio'=>$bio,'id'=>$user['id']]);
        $_SESSION['user']['display_name'] = $displayName;
        flash('Your profile has been updated.');
        redirect('profile.php');
    }
}
$stmt = $pdo->prepare('SELECT id,username,email,display_name,bio,created_at FROM users WHERE id = :id');
$stmt->execute(['id'=>$user['id']]);
$profile = $stmt->fetch();
$stmt = $pdo->prepare('SELECT t.id,t.title,t.artist_name,t.cover_url,t.source,t.source_id FROM user_favourites f JOIN tracks t ON t.id=f.track_id WHERE f.user_id=:id ORDER BY f.created_at DESC LIMIT 50');
$stmt->execute(['id'=>$user['id']]);
$favourites = $stmt->fetchAll();
$notice = take_flash();
require __DIR__ . '/includes/header.php';
?>
<main class="main-content">
<section class="section-block profile-hero">
<div class="profile-avatar"><?= e(strtoupper(substr($profile['display_name'] ?: $profile['username'],0,1))) ?></div>
<div><p class="eyebrow">YOUR SPINWAVE ACCOUNT</p><h1><?= e($profile['display_name'] ?: $profile['username']) ?></h1><p class="muted">@<?= e($profile['username']) ?> · Member since <?= e(date('M Y', strtotime($profile['created_at']))) ?></p></div>
</section>
<?php if ($notice): ?><p class="notice <?= e($notice['type']) ?>"><?= e($notice['message']) ?></p><?php endif; ?>
<?php foreach ($errors as $error): ?><p class="notice error"><?= e($error) ?></p><?php endforeach; ?>
<div class="profile-grid">
<section class="panel-card"><h2>Edit profile</h2>
<form method="post" class="stack-form">
<input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
<label>Display name<input name="display_name" maxlength="100" required value="<?= e($_POST['display_name'] ?? $profile['display_name'] ?? $profile['username']) ?>"></label>
<label>About you<textarea name="bio" rows="4" maxlength="500" placeholder="Tell people a little about your music taste..."><?= e($_POST['bio'] ?? $profile['bio'] ?? '') ?></textarea></label>
<button class="button button-primary" type="submit">Save profile</button>
</form></section>
<section class="panel-card"><h2>Quick links</h2><div class="quick-link-list"><a href="playlists.php">♫ Manage my playlists</a><a href="index.php#discover">⌕ Discover music</a><form method="post" action="logout.php"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><button type="submit" class="text-button">↪ Log out</button></form></div></section>
</div>
<section class="section-block"><div class="section-heading"><div><p class="eyebrow">YOUR COLLECTION</p><h2>Liked songs</h2></div></div>
<?php if ($favourites): ?><div class="track-grid"><?php foreach ($favourites as $track): ?><div class="favourite-card"><button class="track-card" type="button" data-track="<?= e(json_encode($track, JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_TAG|JSON_HEX_AMP)) ?>"><div class="track-art"><?php if ($track['cover_url']): ?><img src="<?= e($track['cover_url']) ?>" alt=""><?php else: ?><span>♫</span><?php endif; ?></div><strong><?= e($track['title']) ?></strong><span><?= e($track['artist_name']) ?></span></button><form method="post" action="library-action.php"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="remove_favourite"><input type="hidden" name="track_id" value="<?= (int)$track['id'] ?>"><button class="text-button" type="submit">Remove from liked songs</button></form></div><?php endforeach; ?></div><?php else: ?><div class="empty-state"><div class="empty-note">♡</div><h3>No liked songs yet</h3><p>Search YouTube and save tracks to build your library.</p><a class="button button-primary" href="index.php#discover">Find music</a></div><?php endif; ?>
</section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
