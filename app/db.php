<?php
declare(strict_types=1);

/**
 * Database Connection for PatoworldTV
 * Returns a shared PDO instance using the configuration.
 */

function db(array $CONFIG): PDO
{
    static $pdo = null;

    // Return existing connection
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    // Create new PDO connection
    $pdo = new PDO(
        $CONFIG['db']['dsn'],
        $CONFIG['db']['user'],
        $CONFIG['db']['pass'],
        $CONFIG['db']['options']
    );

    return $pdo;
}