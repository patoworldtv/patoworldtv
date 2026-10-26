<?php
declare(strict_types=1);

require __DIR__ . '/../../app/bootstrap.php';

require_post();
csrf_check();
require_admin($pdo);

$userId = (int)($_POST['id'] ?? 0);
$block  = (int)($_POST['block'] ?? -1);

if ($userId <= 0 || ($block !== 0 && $block !== 1)) {
    http_response_code(400);
    exit('Invalid request');
}

// Prevent admin from blocking themselves
if ($userId === (int)$admin['id']) {
    exit('You cannot block your own account.');
}

// Ensure user exists
$chk = $pdo->prepare("SELECT id FROM users WHERE id = ? LIMIT 1");
$chk->execute([$userId]);
if (!$chk->fetchColumn()) {
    http_response_code(404);
    exit('User not found');
}

// Update block status
$st = $pdo->prepare("UPDATE users SET is_blocked = ? WHERE id = ?");
$st->execute([$block, $userId]);

// Safe redirect
$ref = $_SERVER['HTTP_REFERER'] ?? 'users.php';
if (!is_string($ref) || !preg_match('~^https?://~i', $ref)) {
    $ref = 'users.php';
}

redirect($ref);
