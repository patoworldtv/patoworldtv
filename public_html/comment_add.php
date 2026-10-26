<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

// Ensure the request is a POST and check CSRF token
require_post();
csrf_check();

// Require user to be logged in
$u = require_login($pdo);

$postId   = (int)($_POST['post_id'] ?? 0);
$parentId = isset($_POST['parent_id']) && $_POST['parent_id'] !== '' ? (int)$_POST['parent_id'] : null;
$content  = trim($_POST['content'] ?? '');

// Determine if request is AJAX
$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || isset($_POST['ajax']);

if ($postId <= 0 || $content === '') {
    if ($isAjax) {
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode(['error' => 'Comment content cannot be empty.']);
        exit;
    }
    http_response_code(400);
    exit('Bad request');
}

// Ensure post exists
$st = $pdo->prepare("SELECT id FROM posts WHERE id = ? LIMIT 1");
$st->execute([$postId]);
if (!$st->fetchColumn()) {
    if ($isAjax) {
        header('Content-Type: application/json');
        http_response_code(404);
        echo json_encode(['error' => 'Post not found.']);
        exit;
    }
    http_response_code(404);
    exit('Post not found');
}

// If replying, ensure parent comment exists and belongs to the same post
if ($parentId) {
    $st = $pdo->prepare("SELECT id FROM comments WHERE id = ? AND post_id = ? LIMIT 1");
    $st->execute([$parentId, $postId]);
    if (!$st->fetchColumn()) {
        if ($isAjax) {
            header('Content-Type: application/json');
            http_response_code(404);
            echo json_encode(['error' => 'Parent comment not found.']);
            exit;
        }
        http_response_code(404);
        exit('Parent comment not found');
    }
}

// Insert comment
$st = $pdo->prepare("
    INSERT INTO comments (post_id, user_id, parent_id, content)
    VALUES (?, ?, ?, ?)
");
$st->execute([
    $postId,
    (int)$u['id'],
    $parentId,
    $content
]);

$commentId = (int)$pdo->lastInsertId();

// ⭐ TRIGGER NOTIFICATION ON REPLY
if ($parentId !== null) {
    // Fetch the parent comment's author
    $parentStmt = $pdo->prepare("SELECT user_id FROM comments WHERE id = ? LIMIT 1");
    $parentStmt->execute([$parentId]);
    $parentComment = $parentStmt->fetch(PDO::FETCH_ASSOC);

    if ($parentComment) {
        $parentAuthorId = (int)$parentComment['user_id'];
        $message = "replied to your comment.";
        // Trigger the notification helper
        add_notification($pdo, $parentAuthorId, (int)$u['id'], $postId, $commentId, 'reply', $message);
    }
}

if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'comment_id' => $commentId,
        'username' => htmlspecialchars($u['username']),
        'profile_photo' => htmlspecialchars($u['profile_photo'] ?? 'default.png'),
        'content' => htmlspecialchars($content),
        'created_at' => 'Just now'
    ]);
    exit;
}

// Safe redirect back
$ref = $_SERVER['HTTP_REFERER'] ?? '';
if (!is_string($ref) || !preg_match('~^https?://~i', $ref)) {
    $ref = 'index.php';
}

redirect($ref);