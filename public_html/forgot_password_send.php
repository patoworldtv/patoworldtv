<?php
declare(strict_types=1);

// 1. Move imports to the very top of the script
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../app/bootstrap.php';
require_post();
csrf_check();

$email = trim($_POST['email'] ?? '');

if ($email === '') {
    exit('Email is required.');
}

// Check if user exists
$st = $pdo->prepare("SELECT id FROM users WHERE email = ?");
$st->execute([$email]);
$user = $st->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    // For security, pretend it worked to prevent user enumeration
    redirect('forgot_password.php');
}

// Generate 6-digit code
$code = (string)random_int(100000, 999999);

// Store in password_resets
$st = $pdo->prepare("
    INSERT INTO password_resets (email, code, created_at)
    VALUES (?, ?, NOW())
");
$st->execute([$email, $code]);

/**
 * SEND EMAIL USING PHPMailer (manual include)
 * -------------------------------------------
 * Folder: /public_html/phpmailer/
 */

// Load PHPMailer files in the correct dependency order (Exception must be first)
require __DIR__ . '/phpmailer/Exception.php';
require __DIR__ . '/phpmailer/PHPMailer.php';
require __DIR__ . '/phpmailer/SMTP.php';

$mail = new PHPMailer(true);

try {
    // SMTP SETTINGS
    $mail->isSMTP();
    $mail->Host       = 'smtp.hostinger.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'noreply@patoworldtv.com';
    $mail->Password   = 'Action2025@'; // Hostinger SMTP password
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // 'ssl' is secure port 465
    $mail->Port       = 465;

    // FROM + TO
    $mail->setFrom('noreply@patoworldtv.com', 'Patoworld TV');
    $mail->addAddress($email);

    // EMAIL CONTENT
    $mail->isHTML(false);
    $mail->Subject = 'Patoworld TV Password Reset Code';
    $mail->Body =
        "Hello,\n\n" .
        "Your Patoworld TV password reset code is: {$code}\n\n" .
        "If you did not request this, you can ignore this email.\n\n" .
        "Patoworld TV";

    $mail->send();

} catch (Exception $e) {
    // Fail silently for security to avoid exposing system paths, but log internally if needed:
    // error_log("Mailer Error: " . $e->getMessage());
}

// Redirect to reset page
redirect('reset_password.php?email=' . urlencode($email));