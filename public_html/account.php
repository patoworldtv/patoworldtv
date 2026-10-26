<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

$u = require_login($pdo);
$userId = (int)$u['id'];

$photoUrl = profile_photo_src($u['profile_photo'] ?? null);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="assets/css/style.css?v=9999">
  <title>Edit Account • PatoworldTV</title>
</head>
<body>

<header>
  <div class="header-inner">
    <div class="brand">
      <h1>
         <a href="index.php" class="home-link">Patoworld TV</a>
      </h1>
      <small>Your daily dose of news, sports and entertainment...</small>
    </div>

    <div class="toplinks">
      <span class="muted">Hi,</span> <?= e($u['full_name'] ?? $u['username']) ?> |
      <a href="dashboard.php">Dashboard</a> |
      <a href="index.php">Home</a> |
      <a href="logout.php">Logout</a>
    </div>
  </div>
</header>

<div class="page-content">
  <div class="container">

    <section class="hero">
      <h2>Edit Account</h2>
      <p class="muted">Update your personal details and profile photo.</p>
    </section>

    <form method="post" action="account_update.php" enctype="multipart/form-data" class="form-grid">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

      <!-- Profile photo preview -->
      <div style="grid-column:1/-1; margin-bottom:20px;">
        <div style="width:100px; height:100px; border-radius:50%; overflow:hidden; background:#eee;">
          <img src="<?= e($photoUrl) ?>" style="width:100%; height:100%; object-fit:cover;">
        </div>
      </div>

      <div class="field">
        <label>Full Name</label>
        <input name="full_name" value="<?= e($u['full_name']) ?>" required>
      </div>

      <!-- Username is now READ-ONLY -->
      <div class="field">
        <label>Username (cannot be changed)</label>
        <input value="<?= e($u['username']) ?>" disabled>
      </div>

      <div class="field">
        <label>Email</label>
        <input type="email" name="email" value="<?= e($u['email']) ?>" required>
      </div>

      <div class="field">
        <label>Phone</label>
        <input name="phone" value="<?= e($u['phone']) ?>">
      </div>

      <div class="field" style="grid-column:1/-1;">
        <label>Profile Photo (optional)</label>
        <input type="file" name="photo" accept="image/*">
      </div>

      <div class="actions" style="grid-column:1/-1;">
        <button type="submit">Save Changes</button>
        <a class="btn-outline" href="dashboard.php">Cancel</a>
      </div>
    </form>

  </div>
</div>

</body>
</html>
