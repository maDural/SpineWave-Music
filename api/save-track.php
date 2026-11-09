<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/playlists.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');

function json_response(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

$user = current_user();
if (!$user) {
    json_response(401, ['error' => 'Sign in to save tracks.']);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(405, ['error' => 'POST required.']);
}

// Parse JSON body once and stash it for verify_csrf().
$raw = file_get_contents('php://input');
$input = json_decode($raw ?: '', true);
if (!is_array($input)) {
    $input = $_POST;
}
$GLOBALS['__spinwave_json_body'] = $input;

verify_csrf();

$action = $input['action'] ?? '';
$trackData = [
    'source' => 'youtube',
    'source_id' => (string)($input['source_id'] ?? ''),
    'title' => (string)($input['title'] ?? ''),
    'artist_name' => (string)($input['artist_name'] ?? ''),
    'cover_url' => (string)($input['cover_url'] ?? ''),
    'embed_url' => (string)($input['embed_url'] ?? ''),
];

if ($action === 'like') {
    if ($trackData['source_id'] === '') {
        json_response(422, ['error' => 'Invalid track data.']);
    }
    $trackId = get_or_create_track($pdo, $trackData);
    if (!$trackId) {
        json_response(422, ['error' => 'Invalid track data.']);
    }
    try {
        $pdo->prepare('INSERT IGNORE INTO user_favourites (user_id, track_id) VALUES (?, ?)')
            ->execute([$user['id'], $trackId]);
        json_response(200, ['ok' => true, 'track_id' => $trackId]);
    } catch (PDOException $e) {
        error_log('Like failed: ' . $e->getMessage());
        json_response(500, ['error' => 'Could not save track.']);
    }
}

if ($action === 'add_to_playlist') {
    $playlistId = (int)($input['playlist_id'] ?? 0);
    $stmt = $pdo->prepare('SELECT id FROM playlists WHERE id = ? AND user_id = ?');
    $stmt->execute([$playlistId, $user['id']]);
    if (!$stmt->fetch()) {
        json_response(404, ['error' => 'Playlist not found.']);
    }
    if ($trackData['source_id'] === '') {
        json_response(422, ['error' => 'Invalid track data.']);
    }
    $trackId = get_or_create_track($pdo, $trackData);
    if (!$trackId) {
        json_response(422, ['error' => 'Invalid track data.']);
    }
    $stmt = $pdo->prepare('SELECT COALESCE(MAX(position), 0) + 1 FROM playlist_tracks WHERE playlist_id = ?');
    $stmt->execute([$playlistId]);
    $position = (int)$stmt->fetchColumn();
    try {
        $pdo->prepare('INSERT IGNORE INTO playlist_tracks (playlist_id, track_id, position) VALUES (?, ?, ?)')
            ->execute([$playlistId, $trackId, $position]);
        json_response(200, ['ok' => true, 'track_id' => $trackId]);
    } catch (PDOException $e) {
        error_log('Add to playlist failed: ' . $e->getMessage());
        json_response(500, ['error' => 'Could not add to playlist.']);
    }
}

json_response(400, ['error' => 'Unknown action.']);