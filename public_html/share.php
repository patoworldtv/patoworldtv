<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

$slug = trim((string)($_GET['slug'] ?? ''));
if ($slug === '' || !preg_match('/^[a-zA-Z0-9\-]+$/', $slug)) {
    http_response_code(400);
    exit('Invalid post');
}

// Build full share URL using your configured base_url
$url = rtrim($CONFIG['app']['base_url'], '/') . '/post.php?slug=' . urlencode($slug);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Share</title>
</head>
<body>

<h3>Share this post</h3>

<ul>
  <li>
    <a target="_blank" rel="noopener"
       href="https://www.facebook.com/sharer/sharer.php?u=<?= e($url) ?>">
       Facebook
    </a>
  </li>

  <li>
    <a target="_blank" rel="noopener"
       href="https://twitter.com/intent/tweet?url=<?= e($url) ?>">
       X (Twitter)
    </a>
  </li>

  <li>
    <a target="_blank" rel="noopener"
       href="https://wa.me/?text=<?= e($url) ?>">
       WhatsApp
    </a>
  </li>

  <li>
    <a target="_blank" rel="noopener"
       href="https://www.linkedin.com/sharing/share-offsite/?url=<?= e($url) ?>">
       LinkedIn
    </a>
  </li>
</ul>

<p>
  <a href="post.php?slug=<?= e($slug) ?>">Back</a>
</p>

</body>
</html>
