<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

$u = require_login($pdo);

$commentId = (int)($_GET['id'] ?? 0);
if ($commentId <= 0) {
    http_response_code(404);
    exit('Comment not found');
}

// Fetch comment
$st = $pdo->prepare("
    SELECT c.*, p.slug
    FROM comments c
    JOIN posts p ON p.id = c.post_id
    WHERE c.id = ?
    LIMIT 1
");
$st->execute([$commentId]);
$comment = $st->fetch(PDO::FETCH_ASSOC);

if (!$comment) {
    http_response_code(404);
    exit('Comment not found');
}

// User can ONLY edit their own comment
if ((int)$comment['user_id'] !== (int)$u['id']) {
    http_response_code(403);
    exit('Not allowed');
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <link rel="stylesheet" href="assets/css/style.css?v=9999">
  <title>Edit Comment • PatoworldTV</title>
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
      <a href="dashboard.php">Dashboard</a> |
      <a href="logout.php">Logout</a>
    </div>
  </div>
</header>

<div class="container">
  <h2>Edit Comment</h2>

  <form method="post" action="comment_update.php">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="comment_id" value="<?= (int)$comment['id'] ?>">

    <textarea name="content" required style="width:100%; height:120px;"><?= e($comment['content']) ?></textarea>

    <button class="btn-primary" style="margin-top:10px;">Save Changes</button>
    <a class="btn-link" href="post.php?slug=<?= e($comment['slug']) ?>">Cancel</a>
  </form>
</div>

</body>
</html>