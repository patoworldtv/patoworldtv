<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

$u = current_user($pdo);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="assets/css/style.css?v=9999">
  <title>Forgot Password • PatoworldTV</title>
</head>
<body>

<header>
  <div class="header-inner">
    <div class="brand">
      <h1>Patoworld TV</h1>
      <small>Your daily dose of news, sports and entertainment...</small>
    </div>

    <div class="toplinks">
      <a href="index.php">Home</a> |
      <?php if ($u): ?>
        <a href="dashboard.php">Dashboard</a> |
        <a href="logout.php">Logout</a>
      <?php else: ?>
        <a href="login.php">Login</a> |
        <a href="register.php">Register</a>
      <?php endif; ?>
    </div>
  </div>
</header>

<div class="page-content">
  <div class="container">

    <section class="hero">
      <h2>Forgot your password?</h2>
      <p class="muted">Enter your email and we’ll send you a reset code.</p>
    </section>

    <form method="post" action="forgot_password_send.php" class="form-grid">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

      <div class="field" style="grid-column:1/-1;">
        <label>Email</label>
        <input type="email" name="email" required>
      </div>

      <div class="actions" style="grid-column:1/-1;">
        <button type="submit">Send Reset Code</button>
        <a class="btn-outline" href="login.php">Back to login</a>
      </div>
    </form>

  </div>
</div>

</body>
</html>
