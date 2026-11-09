<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
$pageTitle = 'Log in | SpinWave Music';
$error = '';
$identity = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $identity = trim((string)($_POST['identity'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    if ($identity === '' || strlen($identity) > 150 || $password === '') $error = 'Enter your username/email and password.';
    else {
        $stmt = $pdo->prepare('SELECT id,username,email,password_hash,display_name FROM users WHERE username = :username OR email = :email LIMIT 1');
        $stmt->execute(['username'=>$identity,'email'=>strtolower($identity)]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password_hash'])) {
            if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
                $update = $pdo->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
                $update->execute(['hash'=>password_hash($password, PASSWORD_DEFAULT),'id'=>$user['id']]);
            }
            session_regenerate_id(true);
            unset($user['password_hash']);
            $_SESSION['user'] = $user;
            flash('Welcome back, ' . ($user['display_name'] ?: $user['username']) . '.');
            redirect('profile.php');
        }
        $error = 'The login details were not recognised.';
    }
}
require __DIR__ . '/includes/header.php';
?>
<main class="main-content narrow-page"><section class="section-block auth-card">
<p class="eyebrow">WELCOME BACK</p><h1>Log in to SpinWave</h1>
<p class="muted">Your favourites and playlists are waiting.</p>
<?php if ($error): ?><p class="notice error"><?= e($error) ?></p><?php endif; ?>
<form method="post" class="stack-form">
<input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
<label>Username or email<input name="identity" required maxlength="150" value="<?= e($identity) ?>" autocomplete="username"></label>
<label>Password<input type="password" name="password" required autocomplete="current-password"></label>
<button class="button button-primary" type="submit">Log in</button>
</form><p class="form-footnote">New to SpinWave? <a href="register.php">Create an account</a>.</p>
<p class="auth-forgot"><a href="forgot-password.php">Forgot password?</a></p>
</section></main>
<?php require __DIR__ . '/includes/footer.php'; ?>
