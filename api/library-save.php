<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function api_reply(int $status, array $body): never {
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
$user = current_user();
if (!$user) api_reply(401, ['error'=>'Log in to save music to your library.']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') api_reply(405, ['error'=>'POST required.']);
$token = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if ($token === '' || !hash_equals(csrf_token(), $token)) api_reply(419, ['error'=>'Your session expired. Refresh and try again.']);
$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) api_reply(400, ['error'=>'Invalid request data.']);
$source = (string)($data['source'] ?? '');
$sourceId = trim((string)($data['source_id'] ?? ''));
$title = trim((string)($data['title'] ?? ''));
$artist = trim((string)($data['artist_name'] ?? 'Unknown artist'));
$cover = trim((string)($data['cover_url'] ?? ''));
$action = (string)($data['action'] ?? '');
$playlistId = filter_var($data['playlist_id'] ?? null, FILTER_VALIDATE_INT);
if ($source !== 'youtube' || !preg_match('/^[A-Za-z0-9_-]{6,20}$/', $sourceId)) api_reply(422, ['error'=>'Only valid YouTube search results can be saved at present.']);
if ($title === '' || strlen($title)>180 || strlen($artist)>180) api_reply(422, ['error'=>'Track title or artist is invalid.']);
if ($cover !== '' && (!filter_var($cover,FILTER_VALIDATE_URL) || !in_array(strtolower((string)parse_url($cover,PHP_URL_SCHEME)),['https','http'],true))) api_reply(422, ['error'=>'Thumbnail URL is invalid.']);
if (!in_array($action,['favourite','playlist'],true)) api_reply(422, ['error'=>'Choose a valid save action.']);
try {
    $find = $pdo->prepare('SELECT id FROM tracks WHERE source="youtube" AND source_id=:sid LIMIT 1');
    $find->execute(['sid'=>$sourceId]);
    $trackId = $find->fetchColumn();
    if (!$trackId) {
        $insert = $pdo->prepare('INSERT INTO tracks (title,artist_name,cover_url,source,source_id,embed_url) VALUES (:title,:artist,:cover,"youtube",:sid,:embed)');
        $insert->execute(['title'=>$title,'artist'=>$artist ?: 'YouTube creator','cover'=>$cover ?: null,'sid'=>$sourceId,'embed'=>'https://www.youtube.com/embed/'.$sourceId]);
        $trackId = (int)$pdo->lastInsertId();
    }
    if ($action === 'favourite') {
        $stmt = $pdo->prepare('INSERT IGNORE INTO user_favourites (user_id,track_id) VALUES (:uid,:tid)');
        $stmt->execute(['uid'=>$user['id'],'tid'=>$trackId]);
        api_reply(200, ['message'=>$stmt->rowCount() ? 'Added to your liked songs.' : 'This track is already in your liked songs.']);
    }
    if (!$playlistId || $playlistId < 1) api_reply(422, ['error'=>'Choose one of your playlists.']);
    $own = $pdo->prepare('SELECT id FROM playlists WHERE id=:pid AND user_id=:uid');
    $own->execute(['pid'=>$playlistId,'uid'=>$user['id']]);
    if (!$own->fetchColumn()) api_reply(403, ['error'=>'That playlist does not belong to your account.']);
    $add = $pdo->prepare('INSERT IGNORE INTO playlist_tracks (playlist_id,track_id) VALUES (:pid,:tid)');
    $add->execute(['pid'=>$playlistId,'tid'=>$trackId]);
    api_reply(200, ['message'=>$add->rowCount() ? 'Added to your playlist.' : 'This track is already in that playlist.']);
} catch (PDOException $ex) {
    error_log('SpinWave library save failed: '.$ex->getMessage());
    api_reply(500, ['error'=>'Could not save this track. Please try again.']);
}
