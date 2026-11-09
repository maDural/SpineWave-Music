<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

/**
 * Build the absolute base URL of the app, e.g. http://localhost/spinwave-music.
 * Respects config/mail.php 'base_url' override.
 */
function pwreset_base_url(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }

    $mail = require __DIR__ . '/../config/mail.php';
    if (!empty($mail['base_url'])) {
        $base = rtrim((string)$mail['base_url'], '/');
        return $base;
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = $_SERVER['SCRIPT_NAME'] ?? '/';
    // Strip the current script filename to get the app directory.
    $dir = rtrim(str_replace('\\', '/', dirname($script)), '/');
    // Walk up one level if we're inside a subfolder like /api or /includes.
    if (preg_match('#/(api|includes)$#', $dir)) {
        $dir = preg_replace('#/(api|includes)$#', '', $dir);
    }
    $base = $scheme . '://' . $host . ($dir === '/' ? '' : $dir);
    return $base;
}

/**
 * Delete expired or used tokens for housekeeping.
 */
function pwreset_cleanup(PDO $pdo): void
{
    try {
        $pdo->exec('DELETE FROM password_resets WHERE expires_at < (NOW() - INTERVAL 1 DAY) OR used_at IS NOT NULL');
    } catch (PDOException $e) {
        error_log('pwreset_cleanup failed: ' . $e->getMessage());
    }
}

/**
 * Create a reset token for the given user. Returns:
 *   ['ok' => true,  'token' => raw, 'link' => url, 'email' => ...]
 *   ['ok' => false, 'reason' => 'throttled'|'error']
 */
function pwreset_create(PDO $pdo, array $user): array
{
    $mail = require __DIR__ . '/../config/mail.php';
    $throttle = max(0, (int)($mail['throttle_seconds'] ?? 60));
    $expires  = max(1, (int)($mail['expires_minutes'] ?? 60));

    // Throttle: if a token was issued for this email within N seconds, refuse.
    if ($throttle > 0) {
        $stmt = $pdo->prepare('SELECT created_at FROM password_resets WHERE email = ? ORDER BY id DESC LIMIT 1');
        $stmt->execute([$user['email']]);
        $last = $stmt->fetchColumn();
        if ($last) {
            $age = time() - strtotime((string)$last);
            if ($age >= 0 && $age < $throttle) {
                return ['ok' => false, 'reason' => 'throttled'];
            }
        }
    }

    // Invalidate any previous unused tokens for this user.
    try {
        $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL')
            ->execute([$user['id']]);
    } catch (PDOException $e) {
        error_log('pwreset invalidate previous failed: ' . $e->getMessage());
    }

    // Generate a strong random token. Never store the raw token.
    $rawToken = bin2hex(random_bytes(32)); // 64 hex chars
    $tokenHash = hash('sha256', $rawToken);
    $expiresAt = date('Y-m-d H:i:s', time() + $expires * 60);
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;

    try {
        $stmt = $pdo->prepare('INSERT INTO password_resets (user_id, email, token_hash, expires_at, ip_address)
                               VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$user['id'], $user['email'], $tokenHash, $expiresAt, $ip]);
    } catch (PDOException $e) {
        error_log('pwreset insert failed: ' . $e->getMessage());
        return ['ok' => false, 'reason' => 'error'];
    }

    $link = pwreset_base_url() . '/reset-password.php?token=' . urlencode($rawToken)
          . '&email=' . urlencode($user['email']);

    return ['ok' => true, 'token' => $rawToken, 'link' => $link, 'email' => $user['email']];
}

/**
 * Find a valid (unexpired, unused) reset row by raw token + email.
 * Returns the row (with user_id) or null.
 */
function pwreset_find(PDO $pdo, string $rawToken, string $email): ?array
{
    if ($rawToken === '' || $email === '') {
        return null;
    }
    $tokenHash = hash('sha256', $rawToken);
    try {
        $stmt = $pdo->prepare('SELECT id, user_id, email, expires_at, used_at
                               FROM password_resets
                               WHERE token_hash = ? AND email = ? LIMIT 1');
        $stmt->execute([$tokenHash, $email]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        if (!empty($row['used_at'])) {
            return null;
        }
        if (strtotime((string)$row['expires_at']) < time()) {
            return null;
        }
        return $row;
    } catch (PDOException $e) {
        error_log('pwreset_find failed: ' . $e->getMessage());
        return null;
    }
}

/**
 * Mark the token as used (single-use enforcement).
 */
function pwreset_consume(PDO $pdo, int $resetId): bool
{
    try {
        $stmt = $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = ? AND used_at IS NULL');
        $stmt->execute([$resetId]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log('pwreset_consume failed: ' . $e->getMessage());
        return false;
    }
}

function pwreset_deliver(array $user, string $link): array
{
    $mail = require __DIR__ . '/../config/mail.php';
    $mode = (string)($mail['mode'] ?? 'display');
    $minutes = (int)($mail['expires_minutes'] ?? 60);
    $subject = (string)($mail['subject'] ?? 'Reset your SpinWave password');
    $fromEmail = (string)($mail['from_email'] ?? 'no-reply@spinwave.local');
    $fromName  = (string)($mail['from_name'] ?? 'SpinWave Music');

    $displayName = $user['display_name'] ?: $user['username'];
    $body = "Hi {$displayName},\n\n"
          . "Someone (hopefully you) requested a password reset for your SpinWave account.\n\n"
          . "Click the link below to choose a new password. It expires in {$minutes} minutes:\n\n"
          . "{$link}\n\n"
          . "If you didn't request this, you can safely ignore this email; your password will not change.\n\n"
          . "— SpinWave Music";

    $headers = "From: {$fromName} <{$fromEmail}>\r\n"
             . "Reply-To: {$fromEmail}\r\n"
             . "Content-Type: text/plain; charset=UTF-8\r\n"
             . "X-Mailer: SpinWave";

    switch ($mode) {
        case 'log':
            $logFile = (string)($mail['log_file'] ?? __DIR__ . '/../logs/password-resets.log');
            $dir = dirname($logFile);
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            $entry = '[' . date('c') . '] TO=' . $user['email'] . ' LINK=' . $link . PHP_EOL;
            @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
            return ['mode' => 'log', 'link' => null];

        case 'mail':
            $sent = @mail($user['email'], $subject, $body, $headers);
            if (!$sent) {
                error_log('pwreset mail() failed for ' . $user['email']);
                return ['mode' => 'mail', 'error' => true, 'link' => null];
            }
            return ['mode' => 'mail', 'link' => null];

        case 'display':
        default:
            // DEV ONLY: return the link so the page can show it.
            return ['mode' => 'display', 'link' => $link];
    }
}