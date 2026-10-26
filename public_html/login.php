<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

$err = '';
$oldEmail = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $oldEmail = trim((string)($_POST['email'] ?? ''));
    $pass     = (string)($_POST['password'] ?? '');
    $remember = !empty($_POST['remember']);

    if ($oldEmail === '' || $pass === '') {
        $err = 'Enter email and password.';
    } else {
        if (login_password($pdo, $CONFIG, $oldEmail, $pass, $remember)) {
            redirect('dashboard.php');
        }
        $err = 'Invalid login.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="assets/css/style.css?v=9999">
  <title>Login • PatoworldTV</title>
</head>
<body>

<header>
  <div class="header-inner">
    <div class="brand">
      <h1>
        <a href="index.php" class="home-link">
          <img src="img/logo.png" alt="Patoworld TV" style="height: 45px; width: auto; vertical-align: middle;">
        </a>
      </h1>
      <small>Your daily dose of news, sports and entertainment...</small>
    </div>

    <div class="toplinks">
      <a href="index.php">Home</a> |
      <a href="register.php">Register</a>
    </div>
  </div>
</header>

<div class="container">
  <div class="auth-container">
    <h2>Login</h2>

    <?php if ($err !== ''): ?>
      <p class="err"><?= e($err) ?></p>
    <?php endif; ?>

    <form class="auth-form" method="post" novalidate>
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

      <div class="form-grid">
        <div class="field">
          <label>Email</label>
          <input type="email" name="email" required value="<?= e($oldEmail) ?>">
        </div>

        <div class="field">
          <label>Password</label>
          <input type="password" name="password" required>
        </div>
      </div>

      <div class="actions">

        <label class="btn-link" style="display:inline-flex; align-items:center; gap:10px; border:1px solid transparent;">
          <input type="checkbox" name="remember" value="1" style="width:auto; margin:0;">
          Remember me
        </label>

        <button type="submit" class="btn-primary">Login</button>

        <a class="btn-link" href="register.php">New here? Create an account</a>

        <!-- ⭐ NEW: Forgot password link -->
        <a class="btn-link" href="forgot_password.php" style="margin-left:10px;">Forgot password?</a>

      </div>
    </form>

    <div class="auth-links">
      <p class="muted">Or</p>
      <a class="btn-outline" href="oauth_google_start.php">Continue with Google</a>
    </div>
  </div>
</div>

</body>
</html>
