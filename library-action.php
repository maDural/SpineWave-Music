<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
$user = require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Method not allowed.'); }
verify_csrf();
$action = (string)($_POST['action'] ?? '');
if ($action === 'delete_playlist') {
    $id = filter_input(INPUT_POST, 'playlist_id', FILTER_VALIDATE_INT);
    $stmt = $pdo->prepare('DELETE FROM playlists WHERE id=:id AND user_id=:uid');
    $stmt->execute(['id'=>$id,'uid'=>$user['id']]);
    flash($stmt->rowCount() ? 'Playlist deleted.' : 'Playlist was not found.', $stmt->rowCount() ? 'success' : 'error');
    redirect('playlists.php');
}
if ($action === 'remove_playlist_track') {
    $pid = filter_input(INPUT_POST, 'playlist_id', FILTER_VALIDATE_INT);
    $tid = filter_input(INPUT_POST, 'track_id', FILTER_VALIDATE_INT);
    $stmt = $pdo->prepare('DELETE pt FROM playlist_tracks pt INNER JOIN playlists p ON p.id=pt.playlist_id WHERE pt.playlist_id=:pid AND pt.track_id=:tid AND p.user_id=:uid');
    $stmt->execute(['pid'=>$pid,'tid'=>$tid,'uid'=>$user['id']]);
    flash('Track removed from playlist.');
    redirect('playlist.php?id=' . (int)$pid);
}
if ($action === 'remove_favourite') {
    $tid = filter_input(INPUT_POST, 'track_id', FILTER_VALIDATE_INT);
    $stmt = $pdo->prepare('DELETE FROM user_favourites WHERE user_id=:uid AND track_id=:tid');
    $stmt->execute(['uid'=>$user['id'],'tid'=>$tid]);
    flash('Track removed from liked songs.');
    redirect('profile.php');
}
http_response_code(400);
exit('Invalid library action.');
