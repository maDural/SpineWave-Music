<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/password-reset.php';

// If already logged in, no need for a reset.
if (current_user()) {
    header('Location: index.php', true, 302);
    exit;
}

$errors = [];
$email = '';
$successMessage = null;
$devLink = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!check_csrf()) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $email = trim((string)($_POST['email'] ?? ''));

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
            $errors[] = 'Enter the email address you registered with.';
        } else {
            try {
                pwreset_cleanup($pdo);

                $stmt = $pdo->prepare('SELECT id, username, email, display_name FROM users WHERE email = ? LIMIT 1');
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                // Always show the same success message to avoid revealing
                // whether an account exists for the given email.
                $successMessage = 'Click the link below to reset the password';

                if ($user) {
                    $result = pwreset_create($pdo, $user);
                    if ($result['ok']) {
                        $delivery = pwreset_deliver($user, $result['link']);
                        if (!empty($delivery['error'])) {
                            $errors[] = 'We could not send the email. Please try again later.';
                        }
                        if ($delivery['mode'] === 'display' && !empty($delivery['link'])) {
                            // Only shown when config/mail.php mode === 'display'.
                            $devLink = $delivery['link'];
                        }
                    } elseif ($result['reason'] === 'throttled') {
                        // Still show the generic message; add a hint.
                        $successMessage = 'A reset link was requested recently. Please wait a minute before requesting another.';
                    } else {
                        $errors[] = 'Something went wrong. Please try again.';
                    }
                }
            } catch (PDOException $e) {
                error_log('Forgot-password DB error: ' . $e->getMessage());
                $errors[] = 'Something went wrong. Please try again.';
            }
        }
    }
}

$pageTitle = 'Forgot password · SpinWave Music';
require_once __DIR__ . '/includes/header.php';
?>
<main class="main-content auth-page">
    <div class="auth-card">
        <p class="eyebrow">ACCOUNT RECOVERY</p>
        <h1>Forgot your password?</h1>
        <p class="auth-helper">Enter the email address you registered with and we'll send you a reset link.</p>

        <?php if ($successMessage !== null): ?>
            <div class="flash flash-success" style="margin:0 0 16px;"><?= htmlspecialchars($successMessage) ?></div>
        <?php endif; ?>

        <?php if ($devLink !== null): ?>
            <div class="dev-reset-link">
                <p><strong>Dev mode:</strong> no email was sent. Use this link to reset your password:</p>
                <p><a href="<?= htmlspecialchars($devLink) ?>"><?= htmlspecialchars($devLink) ?></a></p>
                <p class="small">Change <code>'mode' =&gt; 'display'</code> to <code>'mail'</code> in <code>config/mail.php</code> before going live.</p>
            </div>
        <?php endif; ?>

        <?php foreach ($errors as $error): ?>
            <p class="form-error"><?= htmlspecialchars($error) ?></p>
        <?php endforeach; ?>

        <form method="post" class="stack-form" autocomplete="on">
            <?= csrf_field() ?>
            <label>Email address
                <input type="email" name="email" required maxlength="150" value="<?= htmlspecialchars($email) ?>" autocomplete="email">
            </label>
            <button class="button button-primary" type="submit">Send reset link</button>
        </form>

        <p class="auth-switch"><a href="login.php">← Back to sign in</a></p>
    </div>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>