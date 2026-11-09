<?php
declare(strict_types=1);

function user_playlists(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare('SELECT p.id, p.name, p.description, p.is_public, p.created_at,
                                  (SELECT COUNT(*) FROM playlist_tracks pt WHERE pt.playlist_id = p.id) AS track_count
                           FROM playlists p WHERE p.user_id = ? ORDER BY p.created_at DESC');
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function playlist_tracks(PDO $pdo, int $playlistId, int $userId): array
{
    $stmt = $pdo->prepare('SELECT pt.position, t.*
                           FROM playlist_tracks pt
                           JOIN playlists p ON p.id = pt.playlist_id
                           JOIN tracks t ON t.id = pt.track_id
                           WHERE pt.playlist_id = ? AND p.user_id = ?
                           ORDER BY pt.position ASC, pt.added_at ASC');
    $stmt->execute([$playlistId, $userId]);
    return $stmt->fetchAll();
}

function get_or_create_track(PDO $pdo, array $track): ?int
{
    $source = $track['source'] ?? 'youtube';
    $sourceId = (string)($track['source_id'] ?? '');
    if ($sourceId === '') {
        return null;
    }
    $stmt = $pdo->prepare('SELECT id FROM tracks WHERE source = ? AND source_id = ? LIMIT 1');
    $stmt->execute([$source, $sourceId]);
    $existing = $stmt->fetchColumn();
    if ($existing) {
        return (int)$existing;
    }
    try {
        $stmt = $pdo->prepare('INSERT INTO tracks (title, artist_name, cover_url, source, source_id, embed_url, is_active)
                               VALUES (?, ?, ?, ?, ?, ?, 1)');
        $stmt->execute([
            substr((string)($track['title'] ?? 'Untitled'), 0, 180),
            substr((string)($track['artist_name'] ?? 'Unknown artist'), 0, 180),
            $track['cover_url'] ?? null,
            $source,
            substr($sourceId, 0, 255),
            $track['embed_url'] ?? ('https://www.youtube.com/embed/' . rawurlencode($sourceId)),
        ]);
        return (int)$pdo->lastInsertId();
    } catch (PDOException $e) {
        error_log('Track save failed: ' . $e->getMessage());
        return null;
    }
}