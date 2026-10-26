<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

$u = require_login($pdo);
$userId = (int)$u['id'];
$isAdmin = (int)($u['is_admin'] ?? 0) === 1;

/* ============================
   USER STATISTICS
============================ */

$st = $pdo->prepare("SELECT COUNT(*) FROM comments WHERE user_id = ?");
$st->execute([$userId]);
$myCommentsCount = (int)$st->fetchColumn();

$st = $pdo->prepare("SELECT COUNT(*) FROM post_likes WHERE user_id = ?");
$st->execute([$userId]);
$postLikesGiven = (int)$st->fetchColumn();

$st = $pdo->prepare("SELECT COUNT(*) FROM comment_likes WHERE user_id = ?");
$st->execute([$userId]);
$commentLikesGiven = (int)$st->fetchColumn();

$totalLikesGiven = $postLikesGiven + $commentLikesGiven;

$st = $pdo->prepare("
    SELECT COUNT(*)
    FROM post_likes pl
    JOIN posts p ON p.id = pl.post_id
    WHERE p.created_by = ?
");
$st->execute([$userId]);
$likesOnMyPosts = (int)$st->fetchColumn();

/* ============================
   RECENT PUBLIC POSTS
============================ */

$recentPosts = $pdo->query("
    SELECT title, slug, created_at
    FROM posts
    ORDER BY created_at DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

/* ============================
   ADMIN STATISTICS
============================ */

$totalUsers = $totalPosts = $totalComments = 0;

if ($isAdmin) {
    $totalUsers    = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $totalPosts    = (int)$pdo->query("SELECT COUNT(*) FROM posts")->fetchColumn();
    $totalComments = (int)$pdo->query("SELECT COUNT(*) FROM comments")->fetchColumn();
}

/* ============================
   PROFILE PHOTO URL
============================ */

$photoUrl = profile_photo_src($u['profile_photo'] ?? null);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="assets/css/style.css?v=9999">
  <title>Dashboard • PatoworldTV</title>
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
      <span class="muted">Hi,</span> <?= e($u['full_name'] ?? $u['username']) ?> |
      <a href="index.php">Home</a> |
      <a href="logout.php">Logout</a>
      <?php if ($isAdmin): ?>
        | <a class="btn" href="admin/post_new.php">New Post</a>
      <?php endif; ?>
    </div>
  </div>
</header>

<div class="page-content">
  <div class="container">

    <section class="dash-hero">
      <h2>Dashboard</h2>
      <p class="muted">Overview of your activity on Patoworld TV.</p>
    </section>

    <section class="dash-grid">

      <!-- LEFT SIDE: USER STATS + RECENT POSTS -->
      <div>
        <h3>Your stats</h3>
        <ul class="muted">
          <li><strong>Comments written:</strong> <?= $myCommentsCount ?></li>
          <li><strong>Likes you gave:</strong> <?= $totalLikesGiven ?></li>
          <li><strong>Likes on your posts:</strong> <?= $likesOnMyPosts ?></li>
        </ul>

        <h3 style="margin-top:20px;">Recent posts</h3>
        <ul class="muted">
          <?php foreach ($recentPosts as $p): ?>
            <li>
              <a href="post.php?slug=<?= e($p['slug']) ?>"><?= e($p['title']) ?></a>
              <small> • <?= e($p['created_at']) ?></small>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>

      <!-- RIGHT SIDE: ACCOUNT INFO -->
      <aside>

        <!-- Profile photo -->
        <div style="width:80px; height:80px; border-radius:50%; overflow:hidden; background:#eee; margin-bottom:10px;">
          <img src="<?= e($photoUrl) ?>"
               alt="Profile photo"
               style="width:100%; height:100%; object-fit:cover; display:block;">
        </div>

        <h3>Account</h3>
        <p class="muted">
          <strong>Username:</strong> <?= e($u['username']) ?><br>
          <strong>Email:</strong> <?= e($u['email']) ?><br>
          <?php if (!empty($u['phone'])): ?>
            <strong>Phone:</strong> <?= e($u['phone']) ?><br>
          <?php endif; ?>
          <strong>Role:</strong> <?= $isAdmin ? 'Admin' : 'User' ?>
        </p>

        <!-- ⭐ NEW: EDIT PROFILE BUTTON -->
        <p style="margin-top:10px;">
          <a class="btn-link" href="account.php">Edit Profile</a>
        </p>

        <?php if ($isAdmin): ?>
          <h3 style="margin-top:20px;">Admin overview</h3>
          <ul class="muted">
            <li><strong>Total users:</strong> <?= $totalUsers ?></li>
            <li><strong>Total posts:</strong> <?= $totalPosts ?></li>
            <li><strong>Total comments:</strong> <?= $totalComments ?></li>
          </ul>

          <p style="margin-top:10px;">
            <a class="btn-link" href="admin/post_new.php">Create post</a><br>
            <a class="btn-link" href="admin/users.php">Manage users</a>
          </p>
        <?php endif; ?>

      </aside>

    </section>

  </div>
</div>

</body>
</html>