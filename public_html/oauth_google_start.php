<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

// Session already started in bootstrap.php

$clientId    = $CONFIG['oauth']['google_client_id'];
$redirectUri = $CONFIG['oauth']['google_redirect_uri'];
$scope       = 'openid email profile';

// Generate secure OAuth state token
$state = bin2hex(random_bytes(16));
$_SESSION['oauth2_state'] = $state;

// Build Google OAuth URL
$params = [
    'response_type' => 'code',
    'client_id'     => $clientId,
    'redirect_uri'  => $redirectUri,
    'scope'         => $scope,
    'state'         => $state,
    'access_type'   => 'offline',
    'prompt'        => 'consent',
];

$url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);

// Redirect to Google OAuth
header('Location: ' . $url);
exit;
