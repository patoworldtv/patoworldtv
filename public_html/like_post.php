<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

require_post();
csrf_check();
$u = require_login($pdo);
$userId = (int)$u['id'];

$postId = (int)($_POST['post_id'] ?? 0);
// Default to 'like' if no reaction type is provided, but allow dynamic types
$reactionType = trim($_POST['reaction_type'] ?? 'like');

// White-list of allowed reactions
$allowedReactions = ['like', 'funny', 'angry', 'wow'];
if (!in_array($reactionType, $allowedReactions, true)) {
    $reactionType = 'like';
}

if ($postId <= 0) {
    http_response_code(400);
    exit('Invalid post');
}

// Ensure post exists
$chk = $pdo->prepare("SELECT id FROM posts WHERE id = ?");
$chk->execute([$postId]);
if (!$chk->fetchColumn()) {
    http_response_code(404);
    exit('Post not found');
}

// Check if user already reacted to this post
$st = $pdo->prepare("
    SELECT reaction_type 
    FROM post_likes 
    WHERE post_id = ? AND user_id = ?
");
$st->execute([$postId, $userId]);
$existingReaction = $st->fetchColumn();

if ($existingReaction !== false) {
    if ($existingReaction === $reactionType) {
        // If they clicked the SAME reaction, remove it (unlike/unreact)
        $del = $pdo->prepare("
            DELETE FROM post_likes 
            WHERE post_id = ? AND user_id = ?
        ");
        $del->execute([$postId, $userId]);
        $actionResult = 'removed';
    } else {
        // If they clicked a DIFFERENT reaction, update it
        $upd = $pdo->prepare("
            UPDATE post_likes 
            SET reaction_type = ? 
            WHERE post_id = ? AND user_id = ?
        ");
        $upd->execute([$reactionType, $postId, $userId]);
        $actionResult = 'updated';
    }
} else {
    // If they haven't reacted yet, insert new reaction
    $ins = $pdo->prepare("
        INSERT INTO post_likes (post_id, user_id, reaction_type) 
        VALUES (?, ?, ?)
    ");
    $ins->execute([$postId, $userId, $reactionType]);
    $actionResult = 'added';
}

// Fetch up-to-date reaction counts for this post
$countsSt = $pdo->prepare("
    SELECT reaction_type, COUNT(*) as count 
    FROM post_likes 
    WHERE post_id = ? 
    GROUP BY reaction_type
");
$countsSt->execute([$postId]);
$rawCounts = $countsSt->fetchAll(PDO::FETCH_ASSOC);

// Initialize all reactions to 0
$reactionCounts = array_fill_keys($allowedReactions, 0);
$totalLikes = 0;
foreach ($rawCounts as $row) {
    if (array_key_exists($row['reaction_type'], $reactionCounts)) {
        $reactionCounts[$row['reaction_type']] = (int)$row['count'];
    }
    $totalLikes += (int)$row['count'];
}

// Determine if request is AJAX
$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || isset($_POST['ajax']);

if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'action' => $actionResult,
        'user_reaction' => ($actionResult === 'removed') ? null : $reactionType,
        'counts' => $reactionCounts,
        'total' => $totalLikes
    ]);
    exit;
}

// Safe redirect backup
$ref = $_SERVER['HTTP_REFERER'] ?? '';
if (!is_string($ref) || !preg_match('~^https?://~i', $ref)) {
    $ref = 'index.php';
}

redirect($ref);