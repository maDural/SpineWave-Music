<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
$pageTitle = 'Create account | SpinWave Music';
$errors = [];
$username = $email = $displayName = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = trim((string)($_POST['username'] ?? ''));
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $displayName = trim((string)($_POST['display_name'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    if (!preg_match('/^[A-Za-z0-9_]{3,50}$/', $username)) $errors[] = 'Username must be 3–50 characters using letters, numbers or underscores.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) $errors[] = 'Enter a valid email address.';
    if ($displayName !== '' && (strlen($displayName) > 100)) $errors[] = 'Display name must be 100 characters or fewer.';
    if (strlen($password) < 10 || strlen($password) > 200) $errors[] = 'Password must contain at least 10 characters.';
    if (!$errors) {
        $check = $pdo->prepare('SELECT id FROM users WHERE username = :u OR email = :e LIMIT 1');
        $check->execute(['u' => $username, 'e' => $email]);
        if ($check->fetch()) $errors[] = 'That username or email is already registered.';
    }
    if (!$errors) {
        $stmt = $pdo->prepare('INSERT INTO users (username,email,password_hash,display_name) VALUES (:u,:e,:p,:d)');
        $stmt->execute(['u'=>$username,'e'=>$email,'p'=>password_hash($password, PASSWORD_DEFAULT),'d'=>$displayName ?: $username]);
        session_regenerate_id(true);
        $_SESSION['user'] = ['id'=>(int)$pdo->lastInsertId(),'username'=>$username,'email'=>$email,'display_name'=>$displayName ?: $username];
        flash('Your SpinWave account has been created.');
        redirect('profile.php');
    }
}
require __DIR__ . '/includes/header.php';
?>
<main class="main-content narrow-page">
<section class="section-block auth-card">
<p class="eyebrow">JOIN SPINWAVE</p><h1>Create your account</h1>
<p class="muted">Save favourites and build playlists that are yours.</p>
<?php foreach ($errors as $error): ?><p class="notice error"><?= e($error) ?></p><?php endforeach; ?>
<form method="post" class="stack-form">
<input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
<label>Username<input name="username" required minlength="3" maxlength="50" pattern="[A-Za-z0-9_]{3,50}" value="<?= e($username) ?>" autocomplete="username"></label>
<label>Email<input type="email" name="email" required maxlength="150" value="<?= e($email) ?>" autocomplete="email"></label>
<label>Display name<input name="display_name" maxlength="100" value="<?= e($displayName) ?>" autocomplete="name"></label>
<label>Password<input type="password" name="password" required minlength="10" maxlength="200" autocomplete="new-password"></label>
<button class="button button-primary" type="submit">Create account</button>
</form><p class="form-footnote">Already registered? <a href="login.php">Log in</a>.</p>
</section></main>
<?php require __DIR__ . '/includes/footer.php'; ?>
