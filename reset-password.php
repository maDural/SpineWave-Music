<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/password-reset.php';

if (current_user()) {
    header('Location: index.php', true, 302);
    exit;
}

$token = (string)($_GET['token'] ?? $_POST['token'] ?? '');
$email = (string)($_GET['email'] ?? $_POST['email'] ?? '');
$errors = [];
$success = false;
$resetRow = null;

// Validate the token up front so the form can show/hide.
if ($token === '' || $email === '') {
    $errors[] = 'This reset link is missing or malformed.';
} else {
    $resetRow = pwreset_find($pdo, $token, $email);
    if (!$resetRow) {
        $errors[] = 'This reset link is invalid, expired, or has already been used. Request a new one.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $resetRow) {
    if (!check_csrf()) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $password = (string)($_POST['password'] ?? '');
        $passwordConfirm = (string)($_POST['password_confirm'] ?? '');

        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        } elseif ($password !== $passwordConfirm) {
            $errors[] = 'Passwords do not match.';
        } else {
            try {
                // Consume the token first — this is single-use.
                if (!pwreset_consume($pdo, (int)$resetRow['id'])) {
                    $errors[] = 'This reset link has already been used. Request a new one.';
                } else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                        ->execute([$hash, (int)$resetRow['user_id']]);

                    // Invalidate all other outstanding resets for this user.
                    $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL')
                        ->execute([(int)$resetRow['user_id']]);

                    // Auto sign-in: regenerate session, preserve CSRF token,
                    // set user_id.
                    if (session_status() !== PHP_SESSION_ACTIVE) {
                        session_start();
                    }
                    $preservedCsrf = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32));
                    if (!session_regenerate_id(true)) {
                        error_log('reset-password: session_regenerate_id failed');
                    }
                    $_SESSION['user_id'] = (int)$resetRow['user_id'];
                    $_SESSION['csrf_token'] = $preservedCsrf;
                    session_write_close();

                    flash('success', 'Your password has been updated. You are now signed in.');
                    header('Location: index.php', true, 302);
                    exit;
                }
            } catch (PDOException $e) {
                error_log('Password reset failed: ' . $e->getMessage());
                $errors[] = 'We could not update your password. Please try again.';
            }
        }
    }
}

$pageTitle = 'Reset password · SpinWave Music';
require_once __DIR__ . '/includes/header.php';
?>
<main class="main-content auth-page">
    <div class="auth-card">
        <p class="eyebrow">CHOOSE A NEW PASSWORD</p>
        <h1>Reset your password</h1>

        <?php foreach ($errors as $error): ?>
            <p class="form-error"><?= htmlspecialchars($error) ?></p>
        <?php endforeach; ?>

        <?php if ($resetRow && !$success): ?>
            <form method="post" class="stack-form" autocomplete="off">
                <?= csrf_field() ?>
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>">
                <label>New password
                    <input type="password" name="password" required minlength="8" autocomplete="new-password">
                </label>
                <label>Confirm new password
                    <input type="password" name="password_confirm" required minlength="8" autocomplete="new-password">
                </label>
                <button class="button button-primary" type="submit">Update password</button>
            </form>
        <?php else: ?>
            <p class="auth-helper">Request a new reset link to continue.</p>
            <p><a class="button button-primary" href="forgot-password.php">Request new link</a></p>
        <?php endif; ?>

        <p class="auth-switch"><a href="login.php">← Back to sign in</a></p>
    </div>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>