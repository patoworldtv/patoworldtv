<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

$err = '';
$old = [
    'username'  => '',
    'full_name' => '',
    'email'     => '',
    'phone'     => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $old['username']  = trim((string)($_POST['username'] ?? ''));
    $old['full_name'] = trim((string)($_POST['full_name'] ?? ''));
    $old['email']     = strtolower(trim((string)($_POST['email'] ?? '')));
    $old['phone']     = trim((string)($_POST['phone'] ?? ''));
    $pass             = (string)($_POST['password'] ?? '');

    if ($old['username'] === '' || $old['full_name'] === '' || $old['email'] === '' || $pass === '') {
        $err = 'Fill all required fields.';
    } elseif (!preg_match('/^[a-zA-Z0-9._-]{3,30}$/', $old['username'])) {
        $err = 'Username must be 3–30 characters (letters, numbers, dots, underscores, hyphens).';
    } elseif (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $err = 'Enter a valid email address.';
    } elseif (strlen($pass) < 6) {
        $err = 'Password must be at least 6 characters.';
    } else {
        try {
            // Upload profile photo (optional)
            $photoPath = handle_profile_upload($CONFIG, 'photo');

            // Hash password
            $hash = password_hash($pass, PASSWORD_DEFAULT);

            // Insert user
            $st = $pdo->prepare("
                INSERT INTO users (username, full_name, email, phone, password_hash, profile_photo)
                VALUES (?,?,?,?,?,?)
            ");
            $st->execute([
                $old['username'],
                $old['full_name'],
                $old['email'],
                $old['phone'] !== '' ? $old['phone'] : null,
                $hash,
                $photoPath
            ]);

            // Auto-login
            $_SESSION['user_id'] = (int)$pdo->lastInsertId();
            session_regenerate_id(true);

            redirect('dashboard.php');

        } catch (Throwable $t) {
            $err = 'Registration failed. Email or username may already be in use.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="assets/css/style.css?v=9999">
  <title>Register • Patoworld TV</title>
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
      <a href="login.php">Login</a>
    </div>
  </div>
</header>

<div class="container">
  <div class="auth-container">
    <h2>Create account</h2>

    <?php if ($err !== ''): ?>
      <p class="err"><?= e($err) ?></p>
    <?php endif; ?>

    <form class="auth-form" method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

      <div class="form-grid">

        <div class="field">
          <label>Username</label>
          <input name="username" required value="<?= e($old['username']) ?>">
        </div>

        <div class="field">
          <label>Full name</label>
          <input name="full_name" required value="<?= e($old['full_name']) ?>">
        </div>

        <div class="field">
          <label>Email</label>
          <input type="email" name="email" required value="<?= e($old['email']) ?>">
        </div>

        <div class="field">
          <label>Phone</label>
          <input name="phone" value="<?= e($old['phone']) ?>">
        </div>

        <div class="field">
          <label>Password</label>
          <input type="password" name="password" required>
        </div>

        <div class="field">
          <label>Photo</label>
          <input type="file" name="photo" accept="image/png,image/jpeg,image/webp">
        </div>

      </div>

      <div class="actions">
        <button type="submit" class="btn-primary">Register</button>
        <a class="btn-link" href="login.php">Already have an account? Login</a>
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
