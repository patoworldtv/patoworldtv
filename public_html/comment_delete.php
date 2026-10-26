<?php
declare(strict_types=1);

require __DIR__ . '/../../app/bootstrap.php';

require_post();
csrf_check();
require_admin($pdo);

$cid = (int)($_POST['comment_id'] ?? 0);
if ($cid <= 0) {
    http_response_code(400);
    exit('Invalid comment');
}

// Soft delete comment
$st = $pdo->prepare("UPDATE comments SET is_deleted = 1 WHERE id = ?");
$st->execute([$cid]);

// Safe redirect
$ref = $_SERVER['HTTP_REFERER'] ?? '../index.php';
if (!is_string($ref) || !preg_match('~^https?://~i', $ref)) {
    $ref = '../index.php';
}

redirect($ref);