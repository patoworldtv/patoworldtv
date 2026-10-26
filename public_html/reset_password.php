<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

$u = current_user($pdo);
$email = trim($_GET['email'] ?? '');
$error = trim($_GET['error'] ?? '');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="assets/css/style.css?v=9999">
  <title>Reset Password • PatoworldTV</title>

  <style>
    .popup {
      position: fixed;
      top: 20px;
      right: 20px;
      background: #ffdddd;
      color: #900;
      padding: 15px 20px;
      border-left: 4px solid #c00;
      border-radius: 4px;
      box-shadow: 0 2px 6px rgba(0,0,0,0.2);
      animation: fadeIn 0.3s ease-out;
      z-index: 9999;
    }
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(-10px); }
      to   { opacity: 1; transform: translateY(0); }
    }
  </style>
</head>
<body>

<?php if ($error): ?>
  <div class="popup">
    <?php if ($error === 'nomatch'): ?>
      Passwords do not match.
    <?php elseif ($error === 'invalidcode'): ?>
      Invalid or expired reset code.
    <?php elseif ($error === 'missing'): ?>
      All fields are required.
    <?php elseif ($error === 'usernotfound'): ?>
      User not found.
    <?php else: ?>
      Something went wrong.
    <?php endif; ?>
  </div>
<?php endif; ?>

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
      <h2>Reset your password</h2>
      <p class="muted">Enter the code sent to your email and choose a new password.</p>
    </section>

    <form method="post" action="reset_password_update.php" class="form-grid">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

      <div class="field" style="grid-column:1/-1;">
        <label>Email</label>
        <input type="email" name="email" value="<?= e($email) ?>" required>
      </div>

      <div class="field">
        <label>Reset code</label>
        <input type="text" name="code" maxlength="6" required>
      </div>

      <div class="field">
        <label>New password</label>
        <input type="password" name="password" required>
      </div>

      <div class="field">
        <label>Confirm new password</label>
        <input type="password" name="password_confirm" required>
      </div>

      <div class="actions" style="grid-column:1/-1;">
        <button type="submit">Change Password</button>
        <a class="btn-outline" href="login.php">Back to login</a>
      </div>
    </form>

  </div>
</div>

</body>
</html>
