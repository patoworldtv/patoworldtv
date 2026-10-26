<?php
declare(strict_types=1);

require __DIR__ . '/../../app/bootstrap.php';

require_post();
csrf_check();
require_admin($pdo);

$userId = (int)($_POST['user_id'] ?? 0);
if ($userId <= 0) {
    http_response_code(400);
    exit('Invalid user');
}

// Prevent admin from removing their own admin rights
$current = current_user($pdo);
if ($userId === (int)$current['id']) {
    exit('You cannot remove your own admin rights.');
}

// Ensure user exists
$chk = $pdo->prepare("SELECT id FROM users WHERE id = ? LIMIT 1");
$chk->execute([$userId]);
if (!$chk->fetchColumn()) {
    http_response_code(404);
    exit('User not found');
}

// Toggle admin rights
$st = $pdo->prepare("
    UPDATE users
    SET is_admin = CASE WHEN is_admin = 1 THEN 0 ELSE 1 END
    WHERE id = ?
");
$st->execute([$userId]);

// Safe redirect
$ref = $_SERVER['HTTP_REFERER'] ?? 'users.php';
if (!is_string($ref) || !preg_match('~^https?://~i', $ref)) {
    $ref = 'users.php';
}

redirect($ref);
