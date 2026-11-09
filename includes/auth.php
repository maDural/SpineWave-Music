<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}

require_once __DIR__ . '/../config/database.php';

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    static $cached = null;
    static $cachedId = null;
    $id = (int)$_SESSION['user_id'];
    if ($cachedId === $id && $cached !== null) {
        return $cached;
    }
    global $pdo;
    try {
        $stmt = $pdo->prepare('SELECT id, username, email, display_name, bio, role, created_at FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $found = $stmt->fetch();
        if (!$found) {
            unset($_SESSION['user_id']);
            return null;
        }
        $cached = $found;
        $cachedId = $id;
        return $found;
    } catch (PDOException $e) {
        return null;
    }
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        $target = $_SERVER['REQUEST_URI'] ?? 'index.php';
        header('Location: login.php?next=' . urlencode($target));
        exit;
    }
    return $user;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function check_csrf(): bool
{
    $token = $_POST['csrf_token'] ?? '';

    // For JSON API requests, fall back to the parsed body if present.
    if ($token === '' && ($_SERVER['CONTENT_TYPE'] ?? '') === 'application/json') {
        if (!isset($GLOBALS['__spinwave_json_body'])) {
            $raw = file_get_contents('php://input');
            $parsed = json_decode($raw ?: '', true);
            $GLOBALS['__spinwave_json_body'] = is_array($parsed) ? $parsed : [];
        }
        $token = $GLOBALS['__spinwave_json_body']['csrf_token'] ?? '';
    }

    return is_string($token)
        && $token !== ''
        && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function verify_csrf(): void
{
    if (!check_csrf()) {
        http_response_code(419);
        exit('Invalid or expired form token. Go back and try again.');
    }
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function pull_flashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

function safe_redirect(string $default = 'index.php'): string
{
    $next = $_GET['next'] ?? $_POST['next'] ?? '';
    if (!is_string($next) || $next === '') {
        return $default;
    }
    // Only allow relative paths within the app.
    if (preg_match('#^[a-zA-Z0-9_\-./?=&%]+$#', $next)
        && strpos($next, '//') === false
        && strpos($next, '://') === false
        && strpos($next, ':') === false
        && substr($next, 0, 1) !== '/') {
        return $next;
    }
    return $default;
}