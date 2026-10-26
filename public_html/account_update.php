<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

require_post();
csrf_check();

$u = require_login($pdo);
$userId = (int)$u['id'];

$fullName = trim($_POST['full_name'] ?? '');
$email    = trim($_POST['email'] ?? '');
$phone    = trim($_POST['phone'] ?? '');

if ($fullName === '' || $email === '') {
    exit('All required fields must be filled.');
}

// Upload new profile photo (if provided)
$newPhoto = null;
try {
    $newPhoto = handle_profile_upload($CONFIG, 'photo');
} catch (Throwable $t) {
    $newPhoto = null;
}

// Update user (username NOT touched)
$st = $pdo->prepare("
    UPDATE users
    SET full_name = ?, email = ?, phone = ?, profile_photo = COALESCE(?, profile_photo)
    WHERE id = ?
");

$st->execute([
    $fullName,
    $email,
    $phone,
    $newPhoto,
    $userId
]);

redirect('dashboard.php');
