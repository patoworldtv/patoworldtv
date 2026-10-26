<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

$u = current_user($pdo);
$isLogged = $u !== null;
$userId = $isLogged ? (int)$u['id'] : 0;

// ⭐ FETCH NOTIFICATIONS IF LOGGED IN
$notifications = [];
$unreadCount = 0;
if ($isLogged) {
    // Fetch recent 5 notifications
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

    // Get unread count
    $unreadQuery = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $unreadQuery->execute([$userId]);
    $unreadCount = (int)$unreadQuery->fetchColumn();
}

// CATEGORY + SEARCH
$cat = $_GET['cat'] ?? '';
$q   = trim($_GET['q'] ?? '');

$params = [];

/**
 * Normalize stored media URL so browser can load it on Hostinger.
 */
function media_src(string $url): string
{
    $url = trim($url);
    if ($url === '') return '';

    if (preg_match('~^https?://~i', $url)) {
        return $url;
    }

    $url = preg_replace('~\?.*$~', '', $url);

    if ($url[0] !== '/') {
        $url = '/' . ltrim($url, '/');
    }

    $url = str_replace('/patoworldtv/public', '', $url);
    $url = str_replace('/public', '', $url);

    return $url;
}

/* ============================
   SQL WITH SEARCH + CATEGORY
============================ */

$sql = "SELECT p.*, c.name AS category_name, c.slug AS category_slug
        FROM posts p
        JOIN categories c ON c.id = p.category_id
        WHERE 1=1";

if ($cat !== '') {
    $sql .= " AND c.slug = ?";
    $params[] = $cat;
}

if ($q !== '') {
    $sql .= " AND (p.title LIKE ? OR p.body LIKE ?)";
    $params[] = "%$q%";
    $params[] = "%$q%";
}

$sql .= " ORDER BY p.created_at DESC LIMIT 30";

$st = $pdo->prepare($sql);
$st->execute($params);
$posts = $st->fetchAll();

/* ============================
   SEO & OPEN GRAPH VARIABLES
============================ */
if ($q !== '') {
    $pageTitle = 'Search: ' . htmlspecialchars($q) . ' | Patoworld TV';
    $pageDesc = 'Search results for ' . htmlspecialchars($q) . ' on Patoworld TV.';
    $pageUrl = 'https://patoworldtv.com/index.php?q=' . urlencode($q);
} elseif ($cat !== '') {
    $pageTitle = htmlspecialchars(ucfirst($cat)) . ' News & Stories | Patoworld TV';
    $pageDesc = 'Get the latest ' . htmlspecialchars($cat) . ' updates, breaking news, and features on Patoworld TV.';
    $pageUrl = 'https://patoworldtv.com/index.php?cat=' . urlencode($cat);
} else {
    $pageTitle = "Patoworld TV - Nigeria's Daily News, Sports & Entertainment";
    $pageDesc = "Get the latest Nigerian news, sports updates, and entertainment stories. Patoworld TV delivers breaking stories daily.";
    $pageUrl = 'https://patoworldtv.com/index.php';
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  
  <!-- SEO Meta Tags -->
  <title><?= $pageTitle ?></title>
  <meta name="description" content="<?= $pageDesc ?>">
  <link rel="canonical" href="<?= $pageUrl ?>">
  <meta name="robots" content="index, follow">
  <meta name="facebook-domain-verification" content="5sm2gr73fwebhteaqf8g3o2tsnp16" />

  <!-- Open Graph / Social Sharing (Facebook, LinkedIn, WhatsApp) -->
  <meta property="og:title" content="<?= $pageTitle ?>">
  <meta property="og:description" content="<?= $pageDesc ?>">
  <meta property="og:image" content="https://patoworldtv.com/img/logo.png">
  <meta property="og:type" content="website">
  <meta property="og:url" content="<?= $pageUrl ?>">
  <meta property="og:site_name" content="Patoworld TV">

  <!-- Twitter Card Data -->
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:site" content="@Patoworldtv">
  <meta name="twitter:title" content="<?= $pageTitle ?>">
  <meta name="twitter:description" content="<?= $pageDesc ?>">
  <meta name="twitter:image" content="https://patoworldtv.com/img/logo.png">

  <link rel="stylesheet" href="assets/css/style.css?v=9999">

  <style>
    /* Clean video icon overlay */
    .video-icon {
        position:absolute;
        bottom:8px;
        right:8px;
        background:rgba(0,0,0,0.6);
        color:white;
        padding:6px 8px;
        border-radius:50%;
        font-size:14px;
        display:flex;
        align-items:center;
        justify-content:center;
    }
    .card-media--video {
        position:relative;
    }

    /* Long Title Layout Line-Clamping Rules */
    .card-content h2 {
        display: -webkit-box;
        -webkit-line-clamp: 2;
        /* autoprefixer: ignore next */
        -webkit-box-orient: vertical;  
        overflow: hidden;
        text-overflow: ellipsis;
        max-height: 2.8em; 
        line-height: 1.4;
    }

    /* ⭐ NOTIFICATION DROPDOWN STYLING */
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
    .notification-item div {
      flex: 1;
      display: -webkit-box;
      -webkit-line-clamp: 2;
      /* autoprefixer: ignore next */
      -webkit-box-orient: vertical;
      overflow: hidden;
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

    <nav>
      <a class="pill news" href="index.php?cat=news">News</a>
      <a class="pill sport" href="index.php?cat=sport">Sport</a>
      <a class="pill ent" href="index.php?cat=entertainment">Entertainment</a>
      <a class="pill general" href="index.php?cat=general">General</a>
      <a class="pill" href="index.php">All</a>
    </nav>

    <div class="toplinks">
      <?php if ($u): ?>
        <span class="muted">Hi,</span> <?= e($u['full_name']) ?> |
        
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
                      <div style="font-size:10px; color:var(--muted); margin-top:2px; display:block;"><?= e($notif['created_at']) ?></div>
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
        <?php if ((int)$u['is_admin'] === 1): ?>
          | <a class="btn" href="admin/post_new.php">New Post</a>
        <?php endif; ?>
      <?php else: ?>
        <a href="login.php">Login</a> |
        <a href="register.php">Register</a>
      <?php endif; ?>
    </div>
  </div>
</header>

<div class="page-content">
  <div class="container">

    <section class="hero">
      <div>
        <?php if ($q !== ''): ?>
          <h2>Search results for "<?= e($q) ?>"</h2>
          <p class="muted">Showing posts matching your search.</p>
        <?php elseif ($cat !== ''): ?>
          <h2><?= e(ucfirst($cat)) ?> Stories</h2>
          <p class="muted">Latest posts in <?= e($cat) ?>.</p>
        <?php else: ?>
          <h2>Top Stories</h2>
          <p class="muted">Browse the latest updates across categories.</p>
        <?php endif; ?>
      </div>
    </section>

    <form method="get" class="row" style="margin-top:20px;">
      <input
        type="text"
        name="q"
        placeholder="Search posts..."
        value="<?= e($q) ?>"
        style="max-width:300px;"
      >
      <?php if ($cat !== ''): ?>
        <input type="hidden" name="cat" value="<?= e($cat) ?>">
      <?php endif; ?>
      <button type="submit">Search</button>
    </form>

    <?php if (!$posts): ?>
      <p class="muted" style="margin-top:16px;">
        <?php if ($q !== ''): ?>
          No results found for "<strong><?= e($q) ?></strong>".
        <?php elseif ($cat !== ''): ?>
          No posts yet in this category.
        <?php else: ?>
          No posts yet.
        <?php endif; ?>

        <?php if ($u && (int)$u['is_admin'] === 1): ?>
          <a href="admin/post_new.php">Create the first post</a>.
        <?php endif; ?>
      </p>
    <?php endif; ?>

    <main class="grid">
      <?php foreach ($posts as $p): ?>
        <?php
          $rawMediaUrl = (string)($p['media_url'] ?? '');
          if ($rawMediaUrl === '') {
              $rawMediaUrl = (string)($p['cover_image'] ?? '');
          }

          $src  = media_src($rawMediaUrl);
          $type = strtolower(trim((string)($p['media_type'] ?? '')));

          if ($src !== '' && $type === '') {
              $type = 'image';
          }

          $hasImage = ($src !== '' && $type !== 'video');
          $hasVideo = ($src !== '' && $type === 'video');
        ?>

        <article class="card card--media">
          <a class="card-link" href="post.php?slug=<?= e($p['slug']) ?>" aria-label="Read story: <?= e($p['title']) ?>">

            <?php if ($hasImage): ?>
              <div class="card-media">
                <img
                  src="<?= e($src) ?>"
                  alt="<?= e($p['title']) ?>"
                  loading="lazy"
                  decoding="async"
                  onerror="this.closest('.card-media').style.display='none';"
                >
              </div>

            <?php elseif ($hasVideo): ?>
              <div class="card-media card-media--video">
                <video
                  src="<?= e($src) ?>"
                  aria-label="<?= e($p['title']) ?>"
                  preload="metadata"
                  muted
                  playsinline
                  style="width:100%; height:180px; object-fit:cover; border-radius:6px;"
                  onerror="this.closest('.card-media').style.display='none';"
                ></video>

                <div class="video-icon" aria-hidden="true">▶</div>
              </div>
            <?php endif; ?>

            <div class="card-content">
              <small><?= e($p['category_name']) ?> &bull; <?= e($p['created_at']) ?></small>
              <h2><?= e($p['title']) ?></h2>
            </div>

          </a>
        </article>

      <?php endforeach; ?>
    </main>

  </div>
</div>

<footer class="site-footer">
  <div class="footer-inner">
    <div class="footer-brand">
      <h3>Patoworld TV</h3>
      <p>Your daily dose of news, sports and entertainment.</p>
    </div>

    <div class="footer-links">
      <h4>Contact</h4>
      <p>Email: <a href="mailto:support@patoworldtv.com">support@patoworldtv.com</a></p>
      <p>Phone: +370 6216 7688</p>
    </div>

    <div class="footer-social">
      <h4>Follow Us</h4>
      <div class="social-icons">
        <a href="https://www.facebook.com/PatoworldTV" target="_blank" rel="noopener">Facebook</a>
        <a href="https://x.com/Patoworldtv" target="_blank" rel="noopener">X</a>
        <a href="https://www.instagram.com/patoworld.tv/" target="_blank" rel="noopener">Instagram</a>
        <a href="https://www.tiktok.com/@Patoworldtv" target="_blank" rel="noopener">TikTok</a>
        <a href="index.php" target="_blank" rel="noopener">YouTube</a>
      </div>
    </div>
  </div>

  <div class="footer-bottom">
    © <?= date('Y') ?> Patoworld TV. All rights reserved.
  </div>
</footer>

<script>
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