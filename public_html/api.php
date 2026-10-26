<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$action = isset($_GET['action']) ? trim((string)$_GET['action']) : '';

/**
 * Normalize media URLs for API output.
 */
function api_media_url(array $CONFIG, string $url): ?string
{
    $url = trim($url);
    if ($url === '') {
        return null;
    }

    // Already absolute
    if (preg_match('~^https?://~i', $url)) {
        return $url;
    }

    // Remove query string
    $url = preg_replace('~\?.*$~', '', $url);

    // Ensure leading slash
    if ($url[0] !== '/') {
        $url = '/' . $url;
    }

    // Remove old /public prefix if present
    if (strpos($url, '/public/') === 0) {
        $url = substr($url, strlen('/public'));
        if ($url === '') {
            $url = '/';
        }
    }

    // Build final absolute URL
    return rtrim($CONFIG['app']['base_url'], '/') . $url;
}

/**
 * Send JSON error response and exit.
 */
function json_error(int $code, string $message): void
{
    http_response_code($code);
    echo json_encode(['error' => $message], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

switch ($action) {
    case 'list_posts':
        list_posts($pdo, $CONFIG);
        break;

    case 'get_post':
        get_post($pdo, $CONFIG);
        break;

    case 'list_comments':
        list_comments($pdo);
        break;

    default:
        json_error(400, 'Unknown action');
}

/**
 * GET /api.php?action=list_posts&cat=news&limit=20
 */
function list_posts(PDO $pdo, array $CONFIG): void
{
    $cat   = isset($_GET['cat']) ? trim((string)$_GET['cat']) : '';
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
    if ($limit <= 0 || $limit > 50) {
        $limit = 20;
    }

    $params = [];
    $sql = "
        SELECT p.id, p.title, p.slug, p.created_at,
               p.media_url, p.media_type, p.cover_image,
               c.name AS category_name, c.slug AS category_slug
        FROM posts p
        JOIN categories c ON c.id = p.category_id
    ";

    if ($cat !== '') {
        if (!preg_match('/^[a-z0-9_-]{2,30}$/i', $cat)) {
            json_error(400, 'Invalid category');
        }
        $sql .= " WHERE c.slug = ?";
        $params[] = $cat;
    }

    $sql .= " ORDER BY p.created_at DESC LIMIT " . $limit;

    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    $out = [];
    foreach ($rows as $r) {
        $rawMedia = $r['media_url'] ?: ($r['cover_image'] ?? '');
        $mediaUrl = $rawMedia !== '' ? api_media_url($CONFIG, (string)$rawMedia) : null;

        $out[] = [
            'id'         => (int)$r['id'],
            'title'      => (string)$r['title'],
            'slug'       => (string)$r['slug'],
            'created_at' => (string)$r['created_at'],
            'category'   => [
                'name' => (string)$r['category_name'],
                'slug' => (string)$r['category_slug'],
            ],
            'media'      => [
                'url'  => $mediaUrl,
                'type' => $r['media_type'] ?: null,
            ],
        ];
    }

    echo json_encode(['posts' => $out], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

/**
 * GET /api.php?action=get_post&slug=some-slug
 */
function get_post(PDO $pdo, array $CONFIG): void
{
    $slug = isset($_GET['slug']) ? trim((string)$_GET['slug']) : '';
    if ($slug === '' || !preg_match('/^[a-z0-9\-]+$/i', $slug)) {
        json_error(400, 'Invalid slug');
    }

    $st = $pdo->prepare("
        SELECT p.*, c.name AS category_name, c.slug AS category_slug
        FROM posts p
        JOIN categories c ON c.id = p.category_id
        WHERE p.slug = ?
        LIMIT 1
    ");
    $st->execute([$slug]);
    $post = $st->fetch(PDO::FETCH_ASSOC);

    if (!$post) {
        json_error(404, 'Post not found');
    }

    $likeCount = (int)$pdo
        ->query("SELECT COUNT(*) FROM post_likes WHERE post_id=" . (int)$post['id'])
        ->fetchColumn();

    $rawMediaUrl  = trim((string)($post['media_url'] ?? ''));
    $rawMediaType = strtolower(trim((string)($post['media_type'] ?? '')));
    $rawCover     = trim((string)($post['cover_image'] ?? ''));

    $chosen   = $rawMediaUrl !== '' ? $rawMediaUrl : $rawCover;
    $mediaUrl = $chosen !== '' ? api_media_url($CONFIG, $chosen) : null;

    if ($mediaUrl && !in_array($rawMediaType, ['image', 'video'], true)) {
        $ext = strtolower(pathinfo(parse_url($mediaUrl, PHP_URL_PATH) ?? $mediaUrl, PATHINFO_EXTENSION));
        $rawMediaType = in_array($ext, ['mp4', 'webm', 'mov', 'm4v', 'ogg'], true) ? 'video' : 'image';
    }

    $out = [
        'id'         => (int)$post['id'],
        'title'      => (string)$post['title'],
        'slug'       => (string)$post['slug'],
        'body'       => (string)$post['body'],
        'created_at' => (string)$post['created_at'],
        'category'   => [
            'name' => (string)$post['category_name'],
            'slug' => (string)$post['category_slug'],
        ],
        'media'      => [
            'url'  => $mediaUrl,
            'type' => $mediaUrl ? $rawMediaType : null,
        ],
        'likes'      => $likeCount,
    ];

    echo json_encode($out, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

/**
 * GET /api.php?action=list_comments&slug=some-slug
 */
function list_comments(PDO $pdo): void
{
    $slug = isset($_GET['slug']) ? trim((string)$_GET['slug']) : '';
    if ($slug === '' || !preg_match('/^[a-z0-9\-]+$/i', $slug)) {
        json_error(400, 'Invalid slug');
    }

    $st = $pdo->prepare("SELECT id FROM posts WHERE slug = ? LIMIT 1");
    $st->execute([$slug]);
    $post = $st->fetch(PDO::FETCH_ASSOC);

    if (!$post) {
        json_error(404, 'Post not found');
    }

    $postId = (int)$post['id'];

    $st = $pdo->prepare("
        SELECT c.id, c.content, c.created_at, c.is_deleted,
               u.username
        FROM comments c
        JOIN users u ON u.id = c.user_id
        WHERE c.post_id = ? AND c.parent_id IS NULL
        ORDER BY c.created_at DESC
    ");
    $st->execute([$postId]);
    $comments = $st->fetchAll(PDO::FETCH_ASSOC);

    $stReplies = $pdo->prepare("
        SELECT c.id, c.content, c.created_at, c.is_deleted,
               c.parent_id, u.username
        FROM comments c
        JOIN users u ON u.id = c.user_id
        WHERE c.post_id = ? AND c.parent_id IS NOT NULL
        ORDER BY c.created_at ASC
    ");
    $stReplies->execute([$postId]);
    $replies = $stReplies->fetchAll(PDO::FETCH_ASSOC);

    $byParent = [];
    foreach ($replies as $r) {
        $pid = (int)$r['parent_id'];
        $byParent[$pid][] = [
            'id'         => (int)$r['id'],
            'username'   => (string)$r['username'],
            'content'    => (int)($r['is_deleted'] ?? 0) === 1 ? null : (string)$r['content'],
            'is_deleted' => (bool)$r['is_deleted'],
            'created_at' => (string)$r['created_at'],
        ];
    }

    $out = [];
    foreach ($comments as $c) {
        $cid = (int)$c['id'];
        $out[] = [
            'id'         => $cid,
            'username'   => (string)$c['username'],
            'content'    => (int)($c['is_deleted'] ?? 0) === 1 ? null : (string)$c['content'],
            'is_deleted' => (bool)$c['is_deleted'],
            'created_at' => (string)$c['created_at'],
            'replies'    => $byParent[$cid] ?? [],
        ];
    }

    echo json_encode(['comments' => $out], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}
