<?php
declare(strict_types=1);

require __DIR__ . '/../../app/bootstrap.php';

require_post();
csrf_check();
require_admin($pdo);

$postId = (int)($_POST['post_id'] ?? 0);
if ($postId <= 0) {
    http_response_code(400);
    exit('Invalid post');
}

// Ensure post exists
$chk = $pdo->prepare("SELECT id FROM posts WHERE id = ? LIMIT 1");
$chk->execute([$postId]);
if (!$chk->fetchColumn()) {
    http_response_code(404);
    exit('Post not found');
}

// Delete post
$del = $pdo->prepare("DELETE FROM posts WHERE id = ?");
$del->execute([$postId]);

// Redirect admin to dashboard (NOT the deleted post)
redirect('../dashboard.php');
