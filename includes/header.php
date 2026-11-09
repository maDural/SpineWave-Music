<?php
$currentUser = current_user();
$headerPlaylists = [];
if ($currentUser && isset($pdo)) {
    $q = $pdo->prepare('SELECT id,name FROM playlists WHERE user_id=:uid ORDER BY name');
    $q->execute(['uid'=>$currentUser['id']]);
    $headerPlaylists = $q->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="SpinWave Music — discover music from artists around the world.">
    <meta name="theme-color" content="#090d0b">
    <title><?= htmlspecialchars((string)($pageTitle ?? 'SpinWave Music'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="app-shell">
<aside class="sidebar">
    <a class="brand" href="index.php"><span class="brand-mark">S</span><span>SpinWave<span class="brand-light"> Music</span></span></a>
    <div class="sidebar-label">MENU</div>
    <nav class="side-nav">
        <a class="nav-link" href="index.php"><span>⌂</span> Home</a>
        <a class="nav-link" href="index.php#discover"><span>⌕</span> Search</a>
        <a class="nav-link" href="<?= $currentUser ? 'playlists.php' : 'login.php' ?>"><span>▤</span> Your library</a>
    </nav>
    <div class="sidebar-label playlist-label">YOUR COLLECTION</div>
    <nav class="side-nav">
        <a class="nav-link" href="<?= $currentUser ? 'profile.php' : 'login.php' ?>"><span>♡</span> Liked songs</a>
        <a class="nav-link" href="<?= $currentUser ? 'playlists.php' : 'login.php' ?>"><span>♫</span> Playlists</a>
        <a class="nav-link" href="index.php#sources"><span>＋</span> Music sources</a>
    </nav>
    <div class="sidebar-bottom">
        <div class="sidebar-promo"><div class="promo-icon">♫</div><strong>Find your vibe</strong><p>Your next favourite song is waiting.</p><a href="index.php#discover">Explore now →</a></div>
        <span class="sidebar-version">SPINWAVE MUSIC · STAGE 3</span>
    </div>
</aside>
<div class="page-column">
<header class="topbar">
    <div class="history-buttons"><button aria-label="Back" type="button" onclick="history.back()">‹</button><button aria-label="Forward" type="button" onclick="history.forward()">›</button></div>
    <div class="topbar-right">
        <span class="online-dot"></span>
        <?php if ($currentUser): ?>
            <a class="account-link" href="profile.php"><?= htmlspecialchars((string)($currentUser['display_name'] ?: $currentUser['username']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></a>
            <a class="account-link" href="playlists.php">Playlists</a>
            <span class="avatar"><?= htmlspecialchars(strtoupper(substr((string)($currentUser['display_name'] ?: $currentUser['username']), 0, 1)), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
        <?php else: ?>
            <span>Music for everyone</span><a class="account-link" href="login.php">Log in</a><a class="button button-primary compact-button" href="register.php">Join</a>
        <?php endif; ?>
    </div>
</header>
