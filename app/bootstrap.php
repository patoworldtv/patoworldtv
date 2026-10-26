<?php
declare(strict_types=1);

/**
 * PatoworldTV – Core Bootstrap
 * Loads configuration, helpers, database, security, auth, upload handling,
 * and global profile photo normalization.
 */

require __DIR__ . '/config.php';
require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';
require __DIR__ . '/csrf.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/upload.php';

/**
 * Normalize profile photo URLs for Hostinger.
 * Works for:
 *  - /img/uploads
 *  - absolute URLs
 *  - old localhost/public paths
 *  - missing photos (fallback avatar)
 */
function profile_photo_src(?string $path): string
{
    if (!$path || trim($path) === '') {
        return '/assets/img/default-avatar.png'; // fallback
    }

    $path = trim($path);

    // Absolute URL
    if (preg_match('~^https?://~i', $path)) {
        return $path;
    }

    // Remove old prefixes
    $path = str_replace('/public', '', $path);
    $path = str_replace('/patoworldtv/public', '', $path);

    // Ensure leading slash
    if ($path[0] !== '/') {
        $path = '/' . ltrim($path, '/');
    }

    return $path;
}

// Start secure session
init_session($CONFIG);

// Create PDO connection
$pdo = db($CONFIG);

// Initialize authentication (sessions, remember-me, current user, etc.)
auth_bootstrap($CONFIG, $pdo);