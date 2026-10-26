<?php
declare(strict_types=1);

/**
 * Authentication System for PatoworldTV
 * Handles login, logout, sessions, remember-me, and admin protection.
 */

function auth_bootstrap(array $CONFIG, PDO $pdo): void {
    // If already logged in, nothing to do
    if (!empty($_SESSION['user_id'])) {
        return;
    }

    // Check remember-me cookie
    $cookieName = $CONFIG['app']['remember_cookie_name'];
    if (empty($_COOKIE[$cookieName])) {
        return;
    }

    $raw = $_COOKIE[$cookieName];
    $parts = explode(':', $raw, 2);
    if (count($parts) !== 2) {
        return;
    }

    [$selector, $verifier] = $parts;

    // Validate format
    if (!preg_match('/^[a-f0-9]{24}$/', $selector)) return;
    if (!preg_match('/^[a-f0-9]{64}$/', $verifier)) return;

    // Fetch token
    $st = $pdo->prepare("
        SELECT rt.user_id, rt.verifier_hash, rt.expires_at, u.is_blocked
        FROM remember_tokens rt
        JOIN users u ON u.id = rt.user_id
        WHERE rt.selector = ?
        LIMIT 1
    ");
    $st->execute([$selector]);
    $row = $st->fetch();

    if (!$row) return;
    if ((int)$row['is_blocked'] === 1) return;
    if (strtotime($row['expires_at']) < time()) return;

    // Verify token
    $calc = hash('sha256', hex2bin($verifier));
    if (!hash_equals($row['verifier_hash'], $calc)) {
        return;
    }

    // Success — log user in
    $_SESSION['user_id'] = (int)$row['user_id'];
    session_regenerate_id(true);
}

function current_user(PDO $pdo): ?array {
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $st = $pdo->prepare("
        SELECT id, username, full_name, email, phone, profile_photo, is_admin, is_blocked
        FROM users
        WHERE id = ?
        LIMIT 1
    ");
    $st->execute([$_SESSION['user_id']]);
    $u = $st->fetch();

    if (!$u || (int)$u['is_blocked'] === 1) {
        unset($_SESSION['user_id']);
        return null;
    }

    return $u;
}

function require_login(PDO $pdo): array {
    $u = current_user($pdo);
    if (!$u) {
        redirect('login.php');
    }
    return $u;
}

function require_admin(PDO $pdo): array {
    $u = require_login($pdo);
    if ((int)$u['is_admin'] !== 1) {
        http_response_code(403);
        exit('Admins only');
    }
    return $u;
}

function login_password(PDO $pdo, array $CONFIG, string $email, string $password, bool $remember): bool {
    $st = $pdo->prepare("
        SELECT id, password_hash, is_blocked
        FROM users
        WHERE email = ?
        LIMIT 1
    ");
    $st->execute([$email]);
    $u = $st->fetch();

    if (!$u || (int)$u['is_blocked'] === 1) return false;
    if (empty($u['password_hash'])) return false;
    if (!password_verify($password, $u['password_hash'])) return false;

    // Login success
    $_SESSION['user_id'] = (int)$u['id'];
    session_regenerate_id(true);

    if ($remember) {
        issue_remember_cookie($pdo, $CONFIG, (int)$u['id']);
    }

    return true;
}

function logout(PDO $pdo, array $CONFIG): void {
    $cookieName = $CONFIG['app']['remember_cookie_name'];

    // Delete token from DB
    if (!empty($_COOKIE[$cookieName])) {
        $parts = explode(':', $_COOKIE[$cookieName], 2);
        if (count($parts) === 2) {
            $selector = $parts[0];
            $st = $pdo->prepare("DELETE FROM remember_tokens WHERE selector = ?");
            $st->execute([$selector]);
        }
    }

    // Remove cookie
    setcookie(
        $cookieName,
        '',
        time() - 3600,
        '/',
        '',
        (bool)$CONFIG['app']['cookie_secure'],
        true
    );

    // Destroy session
    $_SESSION = [];
    session_destroy();
}

function issue_remember_cookie(PDO $pdo, array $CONFIG, int $userId): void {
    $selector = bin2hex(random_bytes(12)); // 24 hex chars
    $verifier_raw = random_bytes(32);
    $verifier_hex = bin2hex($verifier_raw); // 64 hex chars

    $verifier_hash = hash('sha256', $verifier_raw);
    $exp = date('Y-m-d H:i:s', time() + (int)$CONFIG['app']['remember_lifetime']);

    // Store token
    $st = $pdo->prepare("
        INSERT INTO remember_tokens (user_id, selector, verifier_hash, expires_at)
        VALUES (?, ?, ?, ?)
    ");
    $st->execute([$userId, $selector, $verifier_hash, $exp]);

    // Set cookie
    setcookie(
        $CONFIG['app']['remember_cookie_name'],
        $selector . ':' . $verifier_hex,
        [
            'expires'  => time() + (int)$CONFIG['app']['remember_lifetime'],
            'path'     => '/',
            'secure'   => (bool)$CONFIG['app']['cookie_secure'],
            'httponly' => true,
            'samesite' => $CONFIG['app']['cookie_samesite'] ?? 'Lax',
        ]
    );
}