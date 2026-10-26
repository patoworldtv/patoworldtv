<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

require_post();
csrf_check();

$u = require_login($pdo);
$userId = (int)$u['id'];

$commentId = (int)($_POST['comment_id'] ?? 0);
if ($commentId <= 0) {
    http_response_code(400);
    exit('Invalid comment ID');
}

// 1. Verify comment exists and fetch its metadata
$chk = $pdo->prepare("SELECT post_id, user_id FROM comments WHERE id = ? LIMIT 1");
$chk->execute([$commentId]);
$comment = $chk->fetch(PDO::FETCH_ASSOC);

if (!$comment) {
    http_response_code(404);
    exit('Comment not found');
}

// 2. Check if user has already liked this comment
$likeCheck = $pdo->prepare("SELECT 1 FROM comment_likes WHERE comment_id = ? AND user_id = ? LIMIT 1");
$likeCheck->execute([$commentId, $userId]);
$hasLiked = $likeCheck->fetchColumn() !== false;

if ($hasLiked) {
    // Unlike comment
    $del = $pdo->prepare("DELETE FROM comment_likes WHERE comment_id = ? AND user_id = ?");
    $del->execute([$commentId, $userId]);
} else {
    // Like comment
    $ins = $pdo->prepare("INSERT INTO comment_likes (comment_id, user_id) VALUES (?, ?)");
    $ins->execute([$commentId, $userId]);

    // ⭐ TRIGGER NOTIFICATION ON COMMENT LIKE
    $commentOwnerId = (int)$comment['user_id'];
    $postId = (int)$comment['post_id'];
    $message = "liked your comment.";
    
    add_notification($pdo, $commentOwnerId, $userId, $postId, $commentId, 'comment_like', $message);
}

// 3. Safe redirect back to referrer
$ref = $_SERVER['HTTP_REFERER'] ?? '';
if (!is_string($ref) || !preg_match('~^https?://~i', $ref)) {
    $ref = 'index.php';
}

redirect($ref);