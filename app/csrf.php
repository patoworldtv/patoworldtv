<?php
declare(strict_types=1);

/**
 * CSRF Protection for PatoworldTV
 * Generates and validates CSRF tokens for all POST requests.
 */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_check(): void
{
    $token = $_POST['csrf'] ?? '';

    // Validate token
    if (
        !$token ||
        empty($_SESSION['csrf']) ||
        !hash_equals($_SESSION['csrf'], $token)
    ) {
        http_response_code(403);
        exit('CSRF check failed');
    }
}
