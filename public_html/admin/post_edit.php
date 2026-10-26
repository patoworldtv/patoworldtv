<?php
declare(strict_types=1);

require __DIR__ . '/../../app/bootstrap.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$admin = require_admin($pdo);

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit('Bad post');
}

// Load post
$st = $pdo->prepare("SELECT * FROM posts WHERE id = ?");
$st->execute([$id]);
$post = $st->fetch();

if (!$post) {
    http_response_code(404);
    exit('Post not found');
}

// Admin can edit ANY post (ownership check removed)

// Load categories
$cats = $pdo->query("SELECT id, name FROM categories ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $title = trim($_POST['title'] ?? '');
    $body  = trim($_POST['body'] ?? '');
    $catId = (int)($_POST['category_id'] ?? 0);

    if ($title === '' || $body === '' || $catId <= 0) {
        $err = 'Fill all fields';
    } else {
        try {
            // Optional new media update
            $mediaUrl  = $post['media_url'];
            $mediaPath = $post['media_path'] ?? null;
            $mediaType = $post['media_type'];

            if (function_exists('handle_post_media_upload') && isset($_FILES['media']) && $_FILES['media']['error'] !== UPLOAD_ERR_NO_FILE) {
                $media = handle_post_media_upload($CONFIG, 'media');
                $mediaUrl  = $media['url']  ?? $post['media_url'];
                $mediaPath = $media['path'] ?? ($post['media_path'] ?? null);
                $mediaType = $media['type'] ?? $post['media_type'];
            }

            $st = $pdo->prepare("
                UPDATE posts
                SET category_id = ?, title = ?, body = ?, media_url = ?, media_path = ?, media_type = ?
                WHERE id = ?
            ");
            $st->execute([
                $catId,
                $title,
                $body,
                $mediaUrl,
                $mediaPath,
                $mediaType,
                $post['id']
            ]);

            redirect('../post.php?slug=' . urlencode($post['slug']));
        } catch (Throwable $t) {
            $err = 'Failed to update post: ' . $t->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  
  <!-- SEO & Security Meta Characteristics -->
  <title>Edit Post | Patoworld TV Admin</title>
  <meta name="robots" content="noindex, nofollow">
  <meta name="facebook-domain-verification" content="5sm2gr73fwebhteaqf8g3o2tsnp16" />

  <link rel="stylesheet" href="../assets/css/style.css?v=9999">
</head>
<body>

<div class="page-content">
  <div class="container">
    <section class="hero">
      <div>
        <h2>Edit Post</h2>
        <p class="muted">Modify existing article details, content, or attachments.</p>
      </div>
    </section>

    <?php if ($err !== ''): ?>
      <p class="err" style="color: red; margin-bottom: 20px; font-weight: bold;"><?= e($err) ?></p>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

      <div class="form-grid">
        <div class="field">
          <label for="category_id">Category</label>
          <select name="category_id" id="category_id" required>
            <?php foreach ($cats as $c): ?>
              <option value="<?= (int)$c['id'] ?>" <?= (int)$c['id'] === (int)$post['category_id'] ? 'selected' : '' ?>>
                <?= e($c['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field">
          <label for="title">Title</label>
          <input type="text" name="title" id="title" required value="<?= e($post['title']) ?>">
        </div>

        <div class="field" style="grid-column: 1 / -1;">
          <label for="body">Body</label>
          <textarea name="body" id="body" required rows="10"><?= e($post['body']) ?></textarea>
        </div>

        <div class="field" style="grid-column: 1 / -1;">
          <label for="media">Replace Media (optional)</label>
          <input type="file" name="media" id="media" accept="image/*,video/*">
          <?php if (!empty($post['media_url'])): ?>
            <p class="muted" style="margin-top: 6px;">Current media attached: <a href="<?= e($post['media_url']) ?>" target="_blank">View file</a></p>
          <?php endif; ?>
        </div>
      </div>

      <div class="actions" style="margin-top: 20px;">
        <button type="submit">Save Changes</button>
        <a class="btn-outline" href="../post.php?slug=<?= e($post['slug']) ?>" style="margin-left: 10px; text-decoration: none;">Back</a>
      </div>
    </form>
  </div>
</div>

</body>
</html>