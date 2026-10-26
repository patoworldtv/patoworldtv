<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

$slug = trim($_GET['slug'] ?? '');
if ($slug === '') {
    http_response_code(404);
    exit('Post not found');
}

// Fetch post
$st = $pdo->prepare("
    SELECT p.*, u.profile_photo
    FROM posts p
    JOIN users u ON u.id = p.created_by
    WHERE p.slug = ?
    LIMIT 1
");
$st->execute([$slug]);
$post = $st->fetch(PDO::FETCH_ASSOC);

if (!$post) {
    http_response_code(404);
    exit('Post not found');
}

// ⭐ GENERATE DYNAMIC POST SEO & SOCIAL SHARING VARIABLES
$postTitle = $post['title'] . ' | Patoworld TV';
$postDesc = mb_substr(strip_tags($post['body']), 0, 160) . '...';
$postUrl = 'https://patoworldtv.com/post.php?slug=' . urlencode($post['slug']);

$postImage = 'https://patoworldtv.com/img/logo.png'; // fallback image
if (!empty($post['media_url']) && $post['media_type'] !== 'video') {
    $postImage = str_starts_with($post['media_url'], 'http') ? $post['media_url'] : 'https://patoworldtv.com/' . ltrim($post['media_url'], '/');
} elseif (!empty($post['cover_image'])) {
    $postImage = str_starts_with($post['cover_image'], 'http') ? $post['cover_image'] : 'https://patoworldtv.com/' . ltrim($post['cover_image'], '/');
}

$user = current_user($pdo);
$isLogged = $user !== null;
$isAdmin = $isLogged && (int)$user['is_admin'] === 1;
$userId = $isLogged ? (int)$user['id'] : 0;

// Creator admin rule
$isCreatorAdmin = $isAdmin && $userId === (int)$post['created_by'];

// ⭐ FETCH NOTIFICATIONS IF LOGGED IN
$notifications = [];
$unreadCount = 0;
if ($isLogged) {
    $notifQuery = $pdo->prepare("
        SELECT n.*, u.username, u.profile_photo, p.slug AS post_slug
        FROM notifications n
        LEFT JOIN users u ON u.id = n.sender_id
        LEFT JOIN posts p ON p.id = n.post_id
        WHERE n.user_id = ?
        ORDER BY n.created_at DESC
        LIMIT 5
    ");
    $notifQuery->execute([$userId]);
    $notifications = $notifQuery->fetchAll(PDO::FETCH_ASSOC);

    $unreadQuery = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $unreadQuery->execute([$userId]);
    $unreadCount = (int)$unreadQuery->fetchColumn();
}

// Fetch Trending Posts
$trendingQuery = $pdo->query("
    SELECT p.id, p.title, p.slug, p.media_url, p.media_type, p.created_at,
           (SELECT COUNT(*) FROM comments WHERE post_id = p.id AND (is_deleted = 0 OR is_deleted IS NULL)) AS comment_count,
           (SELECT COUNT(*) FROM post_likes WHERE post_id = p.id) AS reaction_count
    FROM posts p
    ORDER BY (comment_count + reaction_count) DESC
    LIMIT 5
");
$trendingPosts = $trendingQuery->fetchAll(PDO::FETCH_ASSOC);

// Determine sorting method
$sort = $_GET['sort'] ?? 'newest';
$allowedSorts = ['newest', 'top', 'oldest'];
if (!in_array($sort, $allowedSorts, true)) {
    $sort = 'newest';
}

$orderBy = "c.created_at DESC";
if ($sort === 'top') {
    $orderBy = "likes_count DESC, c.created_at DESC";
} elseif ($sort === 'oldest') {
    $orderBy = "c.created_at ASC";
}

// Fetch comments
$commentsQuery = $pdo->prepare("
    SELECT c.*, u.username, u.profile_photo,
           (SELECT COUNT(*) FROM comment_likes WHERE comment_id = c.id) AS likes_count,
           IFNULL((SELECT 1 FROM comment_likes WHERE comment_id = c.id AND user_id = ? LIMIT 1), 0) AS user_liked
    FROM comments c
    JOIN users u ON u.id = c.user_id
    WHERE c.post_id = ? AND (c.is_deleted = 0 OR c.is_deleted IS NULL)
    ORDER BY {$orderBy}
");
$commentsQuery->execute([$userId, (int)$post['id']]);
$rawComments = $commentsQuery->fetchAll(PDO::FETCH_ASSOC);

$commentsByParent = [];
foreach ($rawComments as $c) {
    $pId = $c['parent_id'] === null ? 0 : (int)$c['parent_id'];
    $commentsByParent[$pId][] = $c;
}

if (!function_exists('renderCommentsTree')) {
    function renderCommentsTree(int $parentId, array $commentsByParent, bool $isLogged, int $userId, bool $isAdmin): void {
        if (!isset($commentsByParent[$parentId])) {
            return;
        }

        foreach ($commentsByParent[$parentId] as $c) {
            $commentId = (int)$c['id'];
            $class = $parentId === 0 ? "comment" : "comment-reply";
            ?>
            <div class="<?= $class ?>" id="comment-<?= $commentId ?>">
                <div style="display: flex; gap: 14px; align-items: flex-start;">
                    <div class="comment-photo">
                        <img src="<?= e(profile_photo_src($c['profile_photo'])) ?>" alt="" style="width: <?= $parentId === 0 ? '40px' : '30px' ?>; height: <?= $parentId === 0 ? '40px' : '30px' ?>; border-radius: 50%; object-fit: cover;">
                    </div>

                    <div class="comment-body" id="comment-body-<?= $commentId ?>" style="flex:1;">
                        <strong><?= e($c['username']) ?></strong>
                        <p style="margin: 6px 0; line-height:1.5;"><?= nl2br(e($c['content'])) ?></p>
                        <small style="color:var(--muted);"><?= e($c['created_at']) ?></small>

                        <div class="comment-actions">
                            <?php if ($isLogged): ?>
                                <form method="post" action="like_comment.php" style="display:inline; border:none; padding:0; margin:0; background:none;">
                                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="comment_id" value="<?= $commentId ?>">
                                    <button type="submit"><?= $c['user_liked'] ? '❤️ Liked' : '👍 Like' ?></button>
                                </form>
                            <?php endif; ?>
                            <span><?= $c['likes_count'] ?> likes</span>

                            <?php if ($isLogged): ?>
                                <button onclick="toggleReplyForm(<?= $commentId ?>)">Reply</button>
                            <?php endif; ?>

                            <?php if ($isLogged && $userId === (int)$c['user_id']): ?>
                                <a href="edit_comment.php?id=<?= $commentId ?>">Edit</a>
                            <?php endif; ?>

                            <?php if ($isAdmin): ?>
                                <form method="post" action="admin/comment_delete.php" class="delete-comment-form" onsubmit="return confirm('Delete permanently?');">
                                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="comment_id" value="<?= $commentId ?>">
                                    <button type="submit">Delete</button>
                                </form>
                            <?php endif; ?>
                        </div>

                        <?php if ($isLogged): ?>
                            <div class="reply-form-container" id="reply-form-<?= $commentId ?>">
                                <form method="post" action="comment_add.php" class="ajax-comment-form" style="margin-top:10px; border: 1px solid var(--line); background:#fafafa; border-radius:10px; padding:12px;">
                                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="post_id" value="<?= (int)$c['post_id'] ?>">
                                    <input type="hidden" name="parent_id" value="<?= $commentId ?>">
                                    <textarea name="content" required placeholder="Write a reply..." style="width:100%; height:60px; margin-bottom:8px; border-radius:8px;"></textarea>
                                    <button class="btn" style="padding: 6px 12px; font-size:13px;">Send Reply</button>
                                </form>
                            </div>
                        <?php endif; ?>

                        <div class="replies-list" id="replies-list-<?= $commentId ?>" style="margin-top: 8px;">
                            <?php renderCommentsTree($commentId, $commentsByParent, $isLogged, $userId, $isAdmin); ?>
                        </div>

                    </div>
                </div>
            </div>
            <?php
        }
    }
}

// Reactions setup
$reactionCounts = ['like' => 0, 'funny' => 0, 'angry' => 0, 'wow' => 0];
$reactionQuery = $pdo->prepare("
    SELECT reaction_type, COUNT(*) as count 
    FROM post_likes 
    WHERE post_id = ? 
    GROUP BY reaction_type
");
$reactionQuery->execute([(int)$post['id']]);
$rawCounts = $reactionQuery->fetchAll(PDO::FETCH_ASSOC);

foreach ($rawCounts as $row) {
    if (array_key_exists($row['reaction_type'], $reactionCounts)) {
        $reactionCounts[$row['reaction_type']] = (int)$row['count'];
    }
}
$totalReactions = array_sum($reactionCounts);

$userReaction = null;
if ($isLogged) {
    $chk = $pdo->prepare("SELECT reaction_type FROM post_likes WHERE post_id = ? AND user_id = ? LIMIT 1");
    $chk->execute([(int)$post['id'], $userId]);
    $res = $chk->fetchColumn();$userReaction = $res !== false ? $res : null;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  
  <!-- SEO & Open Graph Meta Characteristics -->
  <title><?= e($postTitle) ?></title>
  <meta name="description" content="<?= e($postDesc) ?>">
  <link rel="canonical" href="<?= e($postUrl) ?>">
  <meta name="robots" content="index, follow">
  <meta name="facebook-domain-verification" content="5sm2gr73fwebhteaqf8g3o2tsnp16" />

  <meta property="og:title" content="<?= e($postTitle) ?>">
  <meta property="og:description" content="<?= e($postDesc) ?>">
  <meta property="og:image" content="<?= e($postImage) ?>">
  <meta property="og:type" content="article">
  <meta property="og:url" content="<?= e($postUrl) ?>">
  <meta property="og:site_name" content="Patoworld TV">

  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:site" content="@Patoworldtv">
  <meta name="twitter:title" content="<?= e($postTitle) ?>">
  <meta name="twitter:description" content="<?= e($postDesc) ?>">
  <meta name="twitter:image" content="<?= e($postImage) ?>">

  <link rel="stylesheet" href="assets/css/style.css?v=9999">

  <style>
    .layout-grid {
      display: grid;
      grid-template-columns: 1fr;
      gap: 30px;
    }
    @media (min-width: 992px) {
      .layout-grid {
        grid-template-columns: 2.5fr 1fr;
      }
    }

    .sidebar-trending {
      background: #fff;
      border: 1px solid var(--line);
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      padding: 20px;
      position: sticky;
      top: 100px;
      height: fit-content;
    }
    .sidebar-trending h3 {
      margin-top: 0;
      margin-bottom: 15px;
      font-size: 18px;
      border-bottom: 2px solid var(--brand2);
      padding-bottom: 8px;
    }
    .trending-item {
      display: flex;
      gap: 12px;
      margin-bottom: 15px;
      align-items: center;
      text-decoration: none;
      color: #333;
    }
    .trending-item:last-of-type {
      margin-bottom: 0;
    }
    .trending-thumb {
      width: 60px;
      height: 60px;
      border-radius: 8px;
      object-fit: cover;
      background: #eaeaea;
    }
    .trending-info h4 {
      margin: 0 0 4px;
      font-size: 14px;
      line-height: 1.3;
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }
    .trending-meta {
      font-size: 11px;
      color: var(--muted);
    }

    .share-popup {
      position: fixed;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      background: #fff;
      padding: 24px;
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      border: 1px solid var(--line);
      z-index: 9999;
      display: none;
      width: 320px;
      animation: fadeIn 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    }
    .share-popup h4 {
      margin: 0 0 16px;
      font-size: 18px;
      text-align: center;
    }
    .share-popup a {
      display: block;
      margin: 8px 0;
      padding: 10px;
      background: #f5f5f5;
      color: #000;
      border-radius: 10px;
      text-decoration: none;
      font-size: 14px;
      font-weight: bold;
      text-align: center;
      transition: .2s;
    }
    .share-popup a:hover {
      background: #eaeaea;
    }
    @keyframes fadeIn {
      from { opacity: 0; transform: translate(-50%, -55%); }
      to   { opacity: 1; transform: translate(-50%, -50%); }
    }

    .comment {
      border-left: 3px solid var(--brand2);
      margin-top: 20px;
      padding: 4px 0 4px 16px;
    }
    .comment-reply {
      border-left: 2px solid var(--brand1);
      margin-top: 14px;
      padding-left: 16px;
    }
    .comment-actions {
      display: flex;
      gap: 12px;
      align-items: center;
      margin-top: 8px;
      font-size: 13px;
    }
    .comment-actions button, .comment-actions a {
      background: none;
      border: none;
      color: var(--brand2);
      cursor: pointer;
      padding: 0;
      font-weight: bold;
      font-size: 13px;
      text-decoration: none;
    }
    .comment-actions button:hover, .comment-actions a:hover {
      text-decoration: underline;
    }
    .reply-form-container {
      display: none;
      margin-top: 10px;
    }
    .comment-photo img {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      object-fit: cover;
    }
    
    .reaction-btn {
      cursor: pointer;
      padding: 8px 16px;
      border-radius: 999px;
      font-size: 14px;
      font-weight: 700;
      transition: all 0.2s;
    }
    .reaction-btn:hover {
      transform: translateY(-2px);
    }

    .delete-comment-form {
      display: inline;
      margin: 0;
      padding: 0;
    }
    .delete-comment-form button {
      color: #cc0000 !important;
    }

    .comment-sort-bar {
      display: flex;
      align-items: center;
      gap: 10px;
      margin: 15px 0;
      font-size: 14px;
      color: var(--muted);
    }
    .comment-sort-bar a {
      text-decoration: none;
      color: #000;
      background: #f5f5f5;
      padding: 6px 12px;
      border-radius: 999px;
      font-weight: 700;
      border: 1px solid var(--line);
      transition: background 0.2s;
    }
    .comment-sort-bar a.active {
      background: var(--text);
      color: #fff;
      pointer-events: none;
    }
    .comment-sort-bar a:hover:not(.active) {
      background: #eaeaea;
    }

    #comments-list-wrapper {
      display: flex;
      flex-direction: column;
      gap: 12px;
    }

    .notification-container {
      position: relative;
      display: inline-block;
    }
    .bell-btn {
      background: none;
      border: none;
      font-size: 16px;
      cursor: pointer;
      position: relative;
      padding: 4px 8px;
    }
    .bell-badge {
      position: absolute;
      top: -4px;
      right: -4px;
      background: red;
      color: white;
      font-size: 10px;
      font-weight: bold;
      border-radius: 50%;
      padding: 1px 5px;
    }
    .notification-dropdown {
      display: none;
      position: absolute;
      right: 0;
      top: 35px;
      background: #fff;
      border: 1px solid var(--line);
      box-shadow: var(--shadow);
      width: 290px;
      border-radius: var(--radius);
      z-index: 1000;
      text-align: left;
    }
    .notification-header {
      padding: 12px;
      font-weight: bold;
      border-bottom: 1px solid var(--line);
      font-size: 13px;
      display: flex;
      justify-content: space-between;
    }
    .notification-list {
      max-height: 250px;
      overflow-y: auto;
    }
    .notification-item {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 12px;
      border-bottom: 1px solid #f9f9f9;
      text-decoration: none;
      color: #333;
      font-size: 12px;
      transition: background .2s;
    }
    .notification-item:hover {
      background: #f9f9f9;
    }
    .notification-item.unread {
      background: #f0f7ff;
    }
    .notification-avatar {
      width: 32px;
      height: 32px;
      border-radius: 50%;
      object-fit: cover;
    }
  </style>

  <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-8617574226257214" crossorigin="anonymous"></script>
</head>
<body>

<header>
  <div class="header-inner">
    <div class="brand">
      <h1>
        <a href="index.php" class="home-link">
          <img src="img/logo.png" alt="Patoworld TV" style="height: 45px; width: auto; vertical-align: middle;">
        </a>
      </h1>
      <small>Your daily dose of news, sports and entertainment...</small>
    </div>

    <div class="toplinks">
      <a href="index.php">Home</a>
      <?php if ($isLogged): ?>
        | 
        <div class="notification-container">
          <button class="bell-btn" onclick="toggleNotifications()">
            🔔 <?php if ($unreadCount > 0): ?><span class="bell-badge"><?= $unreadCount ?></span><?php endif; ?>
          </button>
          <div class="notification-dropdown" id="notificationDropdown">
            <div class="notification-header">
              <span>Notifications</span>
              <a href="#" onclick="markAllRead(event)" style="font-size:11px; text-decoration:none; color:var(--brand2);">Mark all read</a>
            </div>
            <div class="notification-list">
              <?php if (!empty($notifications)): ?>
                <?php foreach ($notifications as $notif): ?>
                  <a href="post.php?slug=<?= urlencode($notif['post_slug']) ?>#comment-<?= $notif['comment_id'] ?>" 
                     class="notification-item <?= !$notif['is_read'] ? 'unread' : '' ?>"
                     onclick="markSingleRead(<?= $notif['id'] ?>)">
                    <img src="<?= e(profile_photo_src($notif['profile_photo'])) ?>" class="notification-avatar">
                    <div>
                      <strong><?= e($notif['username']) ?></strong> <?= e($notif['message']) ?>
                      <div style="font-size:10px; color:var(--muted); margin-top:2px;"><?= e($notif['created_at']) ?></div>
                    </div>
                  </a>
                <?php endforeach; ?>
              <?php else: ?>
                <div style="padding:15px; text-align:center; color:var(--muted); font-size:13px;">No alerts yet.</div>
              <?php endif; ?>
            </div>
          </div>
        </div>
        
        | <a href="dashboard.php">Dashboard</a>
        | <a href="logout.php">Logout</a>
      <?php else: ?>
        | <a href="login.php">Login</a>
      <?php endif; ?>
    </div>
  </div>
</header>

<div class="page-content">
  <div class="container">

    <div class="layout-grid">
      
      <main class="main-content">
        <article class="post-hero">

          <h2 class="post-title"><?= e($post['title']) ?></h2>

          <div class="post-meta">
            <span><?= e($post['created_at']) ?></span>
          </div>

          <?php if ($isAdmin): ?>
            <div style="margin:12px 0;">
              <?php if ($isCreatorAdmin): ?>
                <a class="btn btn-outline" style="display: inline-flex; align-items:center;" href="admin/post_edit.php?id=<?= (int)$post['id'] ?>">Edit</a>
              <?php endif; ?>

              <form method="post" action="admin/post_delete.php" style="display:inline; border:none; padding:0; margin:0; background:none;">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="post_id" value="<?= (int)$post['id'] ?>">
                <button class="btn btn-outline" onclick="return confirm('Delete this post?')">Delete</button>
              </form>
            </div>
          <?php endif; ?>

          <?php if (!empty($post['media_url'])): ?>
            <div class="post-media">
              <?php if ($post['media_type'] === 'video'): ?>
                <video controls src="<?= e($post['media_url']) ?>"></video>
              <?php else: ?>
                <img src="<?= e($post['media_url']) ?>" alt="">
              <?php endif; ?>
            </div>
          <?php endif; ?>

          <div class="post-body">
            <?= nl2br(e($post['body'])) ?>
          </div>

          <button onclick="openSharePopup()" class="btn btn-outline" style="margin-top:14px;">
            Share
          </button>

          <div class="reactions-container" style="margin-top: 20px; background: #fafafa; padding: 16px; border-radius: var(--radius); border: 1px solid var(--line);">
            <span style="font-weight: bold; display: block; margin-bottom: 12px; font-size: 14px; color: #000;">
              Reactions (<span id="total-reactions-count"><?= $totalReactions ?></span>)
            </span>
            
            <div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
              <?php 
              $emojis = [
                  'like'  => ['👍', 'Like'], 
                  'funny' => ['😂', 'Funny'], 
                  'angry' => ['😡', 'Angry'], 
                  'wow'   => ['😲', 'Wow']
              ];
              ?>
              
              <?php foreach ($emojis as $type =>$data): ?>
                <?php 
                  $isActive = ($userReaction ===$type);
                  $btnStyle =$isActive 
                    ? "background: var(--text); color: #fff; border: 1px solid var(--text);" 
                    : "background: #fff; color: #333; border: 1px solid var(--line);";
                ?>
                
                <?php if ($isLogged): ?>
                  <form method="post" action="like_post.php" class="reaction-form" style="margin: 0; padding: 0; border: none; background: none;">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="post_id" value="<?= (int)$post['id'] ?>">
                    <input type="hidden" name="reaction_type" value="<?= $type ?>">
                    <button type="submit" class="reaction-btn" data-type="<?= $type ?>" style="<?= $btnStyle ?>">
                      <?= $data[0] ?> <?=$data[1] ?> <strong class="reaction-count-<?= $type ?>"><?= $reactionCounts[$type] ?></strong>
                    </button>
                  </form>
                <?php else: ?>
                  <span style="padding: 8px 16px; background: #eaeaea; border-radius: 999px; font-size: 14px; color: #000; font-weight:700; border: 1px solid var(--line);">
                    <?= $data[0] ?> <?=$data[1] ?> <strong><?= $reactionCounts[$type] ?></strong>
                  </span>
                <?php endif; ?>
              <?php endforeach; ?>
            </div>
          </div>

        </article>

        <hr style="border:none; border-top:1px solid var(--line); margin:30px 0;">

        <section class="comments">
          <h3>Comments</h3>

          <div class="comment-sort-bar">
            <span>Sort by:</span>
            <a href="post.php?slug=<?= urlencode($post['slug']) ?>&sort=newest" class="<?= $sort === 'newest' ? 'active' : '' ?>">Newest</a>
            <a href="post.php?slug=<?= urlencode($post['slug']) ?>&sort=top" class="<?= $sort === 'top' ? 'active' : '' ?>">Top Liked</a>
            <a href="post.php?slug=<?= urlencode($post['slug']) ?>&sort=oldest" class="<?= $sort === 'oldest' ? 'active' : '' ?>">Oldest</a>
          </div>

          <div id="comments-list-wrapper">
            <?php 
              renderCommentsTree(0, $commentsByParent,$isLogged, $userId,$isAdmin); 
            ?>
          </div>

          <?php if ($isLogged): ?>
            <form method="post" action="comment_add.php" class="comment-form ajax-comment-form" style="margin-top: 24px;">
              <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="post_id" value="<?= (int)$post['id'] ?>">
              <textarea name="content" required placeholder="Write a comment..."></textarea>
              <button class="btn">Post Comment</button>
            </form>
          <?php else: ?>
            <p class="muted" style="margin-top:20px;">Login to comment.</p>
          <?php endif; ?>

        </section>
      </main>

      <aside class="sidebar-trending">
        <h3>Trending Today</h3>
        <?php if (!empty($trendingPosts)): ?>
          <?php foreach ($trendingPosts as$tp): ?>
            <a href="post.php?slug=<?= urlencode($tp['slug']) ?>" class="trending-item">
              <?php if (!empty($tp['media_url']) &&$tp['media_type'] !== 'video'): ?>
                <img class="trending-thumb" src="<?= e($tp['media_url']) ?>" alt="">
              <?php else: ?>
                <div class="trending-thumb" style="display:flex; align-items:center; justify-content:center; background:linear-gradient(90deg,var(--brand2),var(--brand1)); color:white; font-weight:bold; font-size:20px;">P</div>
              <?php endif; ?>
              <div class="trending-info">
                <h4><?= e($tp['title']) ?></h4>
                <div class="trending-meta">
                  💬 <?= $tp['comment_count'] ?> comments  •  👍 <?= $tp['reaction_count'] ?> reactions
                </div>
              </div>
            </a>
          <?php endforeach; ?>
        <?php else: ?>
          <p class="muted" style="font-size:13px;">No trending articles found.</p>
        <?php endif; ?>
      </aside>

    </div>
  </div>
</div>

<div id="sharePopup" class="share-popup">
  <h4>Share this post</h4>
  <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode('https://patoworldtv.com/post.php?slug=' . $post['slug']) ?>" target="_blank">Facebook</a>
  <a href="https://api.whatsapp.com/send?text=<?= urlencode($post['title'] . ' - https://patoworldtv.com/post.php?slug=' . $post['slug']) ?>" target="_blank">WhatsApp</a>
  <a href="https://twitter.com/intent/tweet?url=<?= urlencode('https://patoworldtv.com/post.php?slug=' . $post['slug']) ?>&text=<?= urlencode($post['title']) ?>" target="_blank">X (Twitter)</a>
  <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?= urlencode('https://patoworldtv.com/post.php?slug=' . $post['slug']) ?>" target="_blank">LinkedIn</a>
  <a href="#" onclick="copyLink()">Copy Link</a>
</div>

<script>
function openSharePopup() {
  const popup = document.getElementById('sharePopup');
  popup.style.display = 'block';

  setTimeout(() => {
    popup.style.display = 'none';
  }, 4000);
}

function copyLink() {
  navigator.clipboard.writeText("https://patoworldtv.com/post.php?slug=<?= $post['slug'] ?>");
  alert("Link copied!");
}

function toggleReplyForm(commentId) {
  const form = document.getElementById('reply-form-' + commentId);
  if (form) {
    form.style.display = form.style.display === 'block' ? 'none' : 'block';
  }
}

function toggleNotifications() {
  const dropdown = document.getElementById('notificationDropdown');
  dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
}

document.addEventListener('click', function(e) {
  const container = document.querySelector('.notification-container');
  if (container && !container.contains(e.target)) {
    const dropdown = document.getElementById('notificationDropdown');
    if (dropdown) dropdown.style.display = 'none';
  }
});

function markAllRead(e) {
  e.preventDefault();
  fetch('mark_notifications_read.php', { method: 'POST' })
    .then(() => {
      const badge = document.querySelector('.bell-badge');
      if (badge) badge.remove();
      document.querySelectorAll('.notification-item').forEach(el => el.classList.remove('unread'));
    });
}

function markSingleRead(notifId) {
  const formData = new FormData();
  formData.append('id', notifId);
  fetch('mark_notifications_read.php', {
    method: 'POST',
    body: formData
  });
}

function escapeHTML(str) {
  return str.replace(/[&<>'"]/g, 
    tag => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[tag] || tag)
  );
}

document.addEventListener('submit', function(e) {
  const form = e.target.closest('.ajax-comment-form');
  if (!form) return;

  e.preventDefault();

  const formData = new FormData(form);
  formData.append('ajax', '1');

  const submitBtn = form.querySelector('button');
  if (submitBtn) submitBtn.disabled = true;

  fetch('comment_add.php', {
    method: 'POST',
    body: formData,
    headers: {
      'X-Requested-With': 'XMLHttpRequest'
    }
  })
  .then(response => {
    if (!response.ok) throw new Error('Network error');
    return response.json();
  })
  .then(data => {
    if (submitBtn) submitBtn.disabled = false;
    
    if (data.success) {
      form.reset();
      
      const parentIdField = form.querySelector('input[name="parent_id"]');
      const parentId = parentIdField ? parentIdField.value : null;

      const cleanUsername = escapeHTML(data.username);
      const cleanContent = escapeHTML(data.content).replace(/\n/g, '<br>');
      const cleanCreatedAt = escapeHTML(data.created_at);

      const buildCommentHTML = (id, username, content, createdAt, isReply) => `
        <div class="${isReply ? 'comment-reply' : 'comment'}" id="comment-${id}">
          <div style="display: flex; gap: 14px; align-items: flex-start;">
            <div class="comment-photo">
              <img src="uploads/avatars/${escapeHTML(data.profile_photo)}" style="width:${isReply ? '30px' : '40px'}; height:${isReply ? '30px' : '40px'}; border-radius:50%; object-fit: cover;">
            </div>
            <div class="comment-body" id="comment-body-${id}" style="flex:1;">
              <strong>${username}</strong>
              <p style="margin: 6px 0; line-height:1.5;">${content}</p>
              <small style="color:var(--muted);">${createdAt}</small>
              <div class="comment-actions">
                <form method="post" action="like_comment.php" style="display:inline; border:none; padding:0; margin:0; background:none;">
                  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="comment_id" value="${id}">
                  <button type="submit">👍 Like</button>
                </form>
                <span>0 likes</span>
                <button onclick="toggleReplyForm(${id})">Reply</button>
                <?php if ($isAdmin): ?>
                  <form method="post" action="admin/comment_delete.php" class="delete-comment-form" onsubmit="return confirm('Delete permanently?');">
                      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                      <input type="hidden" name="comment_id" value="${id}">
                      <button type="submit">Delete</button>
                    </form>
                <?php endif; ?>
              </div>

              <div class="reply-form-container" id="reply-form-${id}">
                <form method="post" action="comment_add.php" class="ajax-comment-form" style="margin-top:10px; border: 1px solid var(--line); background:#fafafa; border-radius:10px; padding:12px;">
                  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="post_id" value="<?= (int)$post['id'] ?>">
                  <input type="hidden" name="parent_id" value="${id}">
                  <textarea name="content" required placeholder="Write a reply..." style="width:100%; height:60px; margin-bottom:8px; border-radius:8px;"></textarea>
                  <button class="btn" style="padding: 6px 12px; font-size:13px;">Send Reply</button>
                </form>
              </div>

              <div class="replies-list" id="replies-list-${id}" style="margin-top: 8px;"></div>
            </div>
          </div>
        </div>`;

      if (parentId) {
        const repliesList = document.getElementById('replies-list-' + parentId);
        if (repliesList) {
          const replyHTML = buildCommentHTML(data.comment_id, cleanUsername, cleanContent, cleanCreatedAt, true);
          repliesList.insertAdjacentHTML('beforeend', replyHTML);
        }
        toggleReplyForm(parentId);
      } else {
        const wrapper = document.getElementById('comments-list-wrapper');
        const commentHTML = buildCommentHTML(data.comment_id, cleanUsername, cleanContent, cleanCreatedAt, false);
        const urlParams = new URLSearchParams(window.location.search);
        const sortOrder = urlParams.get('sort') || 'newest';

        if (sortOrder === 'oldest') {
          wrapper.insertAdjacentHTML('beforeend', commentHTML);
        } else {
          wrapper.insertAdjacentHTML('afterbegin', commentHTML);
        }
      }
    } else {
      alert(data.message || 'Error adding comment.');
    }
  })
  .catch(err => {
    if (submitBtn) submitBtn.disabled = false;
    console.error(err);
    alert('An unexpected error occurred.');
  });
});
</script>

<!--Start of Tawk.to Script-->
<script type="text/javascript">
var Tawk_API=Tawk_API||{}, Tawk_LoadStart=new Date();
(function(){
var s1=document.createElement("script"),s0=document.getElementsByTagName("script")[0];
s1.async=true;
s1.src='https://embed.tawk.to/6ad3b4cfc7ece634ca10c4b7/1k55fkivk';
s1.charset='UTF-8';
s1.setAttribute('crossorigin','*');
s0.parentNode.insertBefore(s1,s0);
})();
</script>
<!--End of Tawk.to Script-->

</body>
</html>