<?php
// SpinWave Music - YouTube Data API v3 search endpoint.
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');

function respond(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

$query = trim((string)($_GET['q'] ?? ''));
if ($query === '' || (function_exists('mb_strlen') ? mb_strlen($query, 'UTF-8') : strlen($query)) > 120) {
    respond(400, ['error' => 'Enter a search term containing between 1 and 120 characters.']);
}

$services = require __DIR__ . '/../config/services.php';
$apiKey = trim((string)($services['youtube_api_key'] ?? ''));
if ($apiKey === '') {
    respond(503, ['error' => 'YouTube search is not configured. Add your YouTube Data API v3 key to config/services.php.']);
}
if (!function_exists('curl_init')) {
    respond(500, ['error' => 'PHP cURL is not enabled. Enable the cURL extension in XAMPP php.ini and restart Apache.']);
}

$endpoint = 'https://www.googleapis.com/youtube/v3/search?' . http_build_query([
    'part' => 'snippet',
    'type' => 'video',
    'videoEmbeddable' => 'true',
    'maxResults' => 15,
    'q' => $query,
    'safeSearch' => 'moderate',
    'key' => $apiKey,
]);

$curl = curl_init($endpoint);
curl_setopt_array($curl, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 8,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_USERAGENT => 'SpinWaveMusic/1.0',
]);
$responseBody = curl_exec($curl);
$curlError = curl_error($curl);
$statusCode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
curl_close($curl);

if ($responseBody === false) {
    error_log('SpinWave YouTube API cURL error: ' . $curlError);
    respond(502, ['error' => 'Could not connect to YouTube. Check your internet connection and PHP cURL configuration.']);
}

$apiData = json_decode($responseBody, true);
if ($statusCode !== 200 || !is_array($apiData)) {
    $reason = $apiData['error']['errors'][0]['reason'] ?? '';
    if ($reason === 'quotaExceeded' || $reason === 'dailyLimitExceeded') {
        respond(429, ['error' => 'The YouTube API quota has been reached. Try again after the quota resets.']);
    }
    if ($statusCode === 403) {
        respond(502, ['error' => 'YouTube rejected the API request. Check that YouTube Data API v3 is enabled and the API key is valid.']);
    }
    error_log('SpinWave YouTube API returned HTTP ' . $statusCode . ': ' . substr($responseBody, 0, 1000));
    respond(502, ['error' => 'YouTube search failed. Check your API key, API settings and quota.']);
}

$items = [];
foreach (($apiData['items'] ?? []) as $item) {
    $videoId = (string)($item['id']['videoId'] ?? '');
    if ($videoId === '') {
        continue;
    }
    $snippet = $item['snippet'] ?? [];
    $thumbnails = $snippet['thumbnails'] ?? [];
    $thumbnail = $thumbnails['high']['url']
        ?? $thumbnails['medium']['url']
        ?? $thumbnails['default']['url']
        ?? '';

    $items[] = [
        'source' => 'youtube',
        'source_id' => $videoId,
        'title' => html_entity_decode((string)($snippet['title'] ?? 'Untitled video'), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        'artist_name' => html_entity_decode((string)($snippet['channelTitle'] ?? 'YouTube creator'), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        'cover_url' => (string)$thumbnail,
        'embed_url' => 'https://www.youtube.com/embed/' . rawurlencode($videoId),
    ];
}

respond(200, ['items' => $items, 'count' => count($items)]);
