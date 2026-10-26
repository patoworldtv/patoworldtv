<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

// Always return valid JSON headers immediately to prevent rendering issues
header('Content-Type: application/json');

// Ensure the request is POST and the user is logged in
$user = current_user($pdo);
if (!$user || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = (int)$user['id'];
$notificationId = isset($_POST['id']) ? (int)$_POST['id'] : null;

try {
    if ($notificationId !== null) {
        // Option A: Mark a SINGLE notification as read cleanly scoped
        $stmt = $pdo->prepare("
            UPDATE notifications 
            SET is_read = 1 
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$notificationId, $userId]);
    } else {
        // Option B: Mark ALL notifications as read for this active session user
        $stmt = $pdo->prepare("
            UPDATE notifications 
            SET is_read = 1 
            WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
    }

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    http_response_code(500);
    // Suppress stack traces to end users for production environmental configurations
    echo json_encode(['success' => false, 'error' => 'Database operation failed']);
}