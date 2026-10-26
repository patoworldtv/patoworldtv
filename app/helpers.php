<?php
declare(strict_types=1);

/**
 * Helper functions for PatoworldTV
 * Includes escaping, redirects, session handling, request validation, and slug creation.
 */

/**
 * Escape HTML safely
 */
function e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

/**
 * Redirect and stop execution
 */
function redirect(string $url): never
{
    header("Location: $url");
    exit;
}

/**
 * Initialize secure session
 */
function init_session(array $CONFIG): void
{
    $params = session_get_cookie_params();

    session_name($CONFIG['app']['session_name']);

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => $params['path'] ?? '/',
        'domain'   => $params['domain'] ?? '',
        'secure'   => (bool)$CONFIG['app']['cookie_secure'],   // HTTPS only
        'httponly' => (bool)$CONFIG['app']['cookie_httponly'], // JS cannot access
        'samesite' => $CONFIG['app']['cookie_samesite'] ?? 'Lax',
    ]);

    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

/**
 * Require POST request
 */
function require_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        exit('Method Not Allowed');
    }
}

/**
 * Create a clean URL slug
 */
function slugify(string $t): string
{
    $t = strtolower(trim($t));
    $t = preg_replace('~[^\pL\d]+~u', '-', $t) ?? $t;
    $t = preg_replace('~[^-\w]+~', '', $t) ?? $t;
    $t = trim($t, '-');

    return $t ?: bin2hex(random_bytes(6));
}

/**
 * Add an in-app notification for a user
 */
function add_notification(PDO $pdo, int $userId, ?int $senderId, ?int $postId, ?int $commentId, string $type, string $message): bool
{
    // Don't notify users of their own actions
    if ($userId === $senderId) {
        return false;
    }
    
    $st = $pdo->prepare("
        INSERT INTO notifications (user_id, sender_id, post_id, comment_id, type, message)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    return $st->execute([$userId, $senderId, $postId, $commentId, $type, $message]);
}