<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

require_post();
csrf_check();

$u = require_login($pdo);

$commentId = (int)($_POST['comment_id'] ?? 0);
$content   = trim($_POST['content'] ?? '');

if ($commentId <= 0 || $content === '') {
    http_response_code(400);
    exit('Bad request');
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

// Update comment
$st = $pdo->prepare("UPDATE comments SET content = ? WHERE id = ?");
$st->execute([$content, $commentId]);

redirect("post.php?slug=" . urlencode($comment['slug']));