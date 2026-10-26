<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

// Session already started in bootstrap.php

$clientId     = $CONFIG['oauth']['google_client_id'];
$clientSecret = $CONFIG['oauth']['google_client_secret'];
$redirectUri  = $CONFIG['oauth']['google_redirect_uri'];

$code  = $_GET['code']  ?? null;
$state = $_GET['state'] ?? null;

// Validate OAuth state
if (
    !$code ||
    !$state ||
    !isset($_SESSION['oauth2_state']) ||
    !hash_equals($_SESSION['oauth2_state'], $state)
) {
    exit('Invalid OAuth state.');
}

unset($_SESSION['oauth2_state']);

// Exchange code for tokens
$tokenResponse = file_get_contents(
    'https://oauth2.googleapis.com/token',
    false,
    stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => http_build_query([
                'code'          => $code,
                'client_id'     => $clientId,
                'client_secret' => $clientSecret,
                'redirect_uri'  => $redirectUri,
                'grant_type'    => 'authorization_code',
            ]),
        ],
    ])
);

if ($tokenResponse === false) {
    exit('Failed to get token from Google.');
}

$tokenData = json_decode($tokenResponse, true);
$idToken   = $tokenData['id_token'] ?? null;

if (!$idToken) {
    exit('No ID token returned by Google.');
}

// Decode ID token payload
$parts   = explode('.', $idToken);
$payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);

$email = $payload['email'] ?? null;
$name  = $payload['name']  ?? null;
$sub   = $payload['sub']   ?? null;

if (!$email || !$sub) {
    exit('Missing email or sub from Google.');
}

/* ============================
   FIND OR CREATE USER
============================ */

// Check if OAuth identity already exists
$st = $pdo->prepare("
    SELECT u.*
    FROM users u
    JOIN oauth_identities oi ON oi.user_id = u.id
    WHERE oi.provider = 'google' AND oi.provider_user_id = ?
    LIMIT 1
");
$st->execute([$sub]);
$user = $st->fetch();

if (!$user) {
    // Check if user exists by email
    $st = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
    $st->execute([$email]);
    $user = $st->fetch();

    if (!$user) {
        // Create new user
        $username = explode('@', $email)[0];

        $st = $pdo->prepare("
            INSERT INTO users (username, full_name, email, password_hash, is_admin)
            VALUES (?,?,?,?,0)
        ");
        $st->execute([
            $username,
            $name ?? $username,
            $email,
            password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
        ]);

        $userId = (int)$pdo->lastInsertId();
    } else {
        $userId = (int)$user['id'];
    }

    // Link OAuth identity
    $st = $pdo->prepare("
        INSERT INTO oauth_identities (provider, provider_user_id, user_id)
        VALUES ('google', ?, ?)
    ");
    $st->execute([$sub, $userId]);

} else {
    $userId = (int)$user['id'];
}

// Log user in
$_SESSION['user_id'] = $userId;
session_regenerate_id(true);

// Redirect to dashboard
redirect('dashboard.php');
