<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

require_post();
csrf_check();

$email    = trim($_POST['email'] ?? '');
$code     = trim($_POST['code'] ?? '');
$pass     = (string)($_POST['password'] ?? '');
$pass2    = (string)($_POST['password_confirm'] ?? '');

if ($email === '' || $code === '' || $pass === '' || $pass2 === '') {
    redirect('reset_password.php?email=' . urlencode($email) . '&error=missing');
}

if ($pass !== $pass2) {
    redirect('reset_password.php?email=' . urlencode($email) . '&error=nomatch');
}

// Check reset code (valid for 30 minutes)
$st = $pdo->prepare("
    SELECT id
    FROM password_resets
    WHERE email = ? AND code = ? AND created_at >= (NOW() - INTERVAL 30 MINUTE)
    ORDER BY id DESC
    LIMIT 1
");
$st->execute([$email, $code]);
$reset = $st->fetch(PDO::FETCH_ASSOC);

if (!$reset) {
    redirect('reset_password.php?email=' . urlencode($email) . '&error=invalidcode');
}

// Check user exists
$st = $pdo->prepare("SELECT id FROM users WHERE email = ?");
$st->execute([$email]);
$user = $st->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    redirect('reset_password.php?email=' . urlencode($email) . '&error=usernotfound');
}

// Update password
$newHash = password_hash($pass, PASSWORD_DEFAULT);

$st = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
$st->execute([$newHash, (int)$user['id']]);

// Clean up reset codes
$st = $pdo->prepare("DELETE FROM password_resets WHERE email = ?");
$st->execute([$email]);

redirect('login.php?reset=success');
