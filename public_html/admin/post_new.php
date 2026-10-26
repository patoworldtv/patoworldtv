<?php
declare(strict_types=1);

require __DIR__ . '/../../app/bootstrap.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Confirm admin access privileges
$admin = require_admin($pdo);

// Build structured options dropdown menu
$cats = $pdo->query("SELECT id, name FROM categories ORDER BY id")->fetchAll();
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify native protection tokens
    csrf_check();

    $title     = trim($_POST['title'] ?? '');
    $body      = trim($_POST['body'] ?? '');
    $catId     = (int)($_POST['category_id'] ?? 0);
    $mediaUrl  = !empty($_POST['async_media_url']) ? trim($_POST['async_media_url']) : null;
    $mediaPath = !empty($_POST['async_media_path']) ? trim($_POST['async_media_path']) : null;
    $mediaType = !empty($_POST['async_media_type']) ? trim($_POST['async_media_type']) : null;

    if ($title === '' || $body === '' || $catId <= 0) {
        $err = 'Please fill out all required fields.';
    } else {
        try {
            // Synchronize inserts using atomic transaction processing
            $pdo->beginTransaction();

            $slug = slugify($title) . '-' . bin2hex(random_bytes(3));

            // Record 1: Store entry parameters directly inside the core posts table structure
            $st = $pdo->prepare("
                INSERT INTO posts (category_id, title, slug, body, created_by, media_url, media_path, media_type)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $st->execute([
                $catId,
                $title,
                $slug,
                $body,
                (int)$admin['id'],
                $mediaUrl,
                $mediaPath,
                $mediaType
            ]);

            $newPostId = $pdo->lastInsertId();

            // Record 2: If a media file exists, establish references within the relational table
            if ($mediaUrl !== null) {
                $stMedia = $pdo->prepare("
                    INSERT INTO post_media (post_id, media_type, media_url, sort_order)
                    VALUES (?, ?, ?, 0)
                ");
                $stMedia->execute([
                    (int)$newPostId,
                    $mediaType,
                    $mediaUrl
                ]);
            }

            $pdo->commit();
            
            // Seamless view path redirection
            redirect('../post.php?slug=' . urlencode($slug));
        } catch (Throwable $t) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $err = 'Failed to publish post: ' . $t->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  
  <!-- SEO & Open Graph Meta Characteristics -->
  <title>Create New Post | Patoworld TV Admin</title>
  <meta name="robots" content="noindex, nofollow">
  <meta name="facebook-domain-verification" content="5sm2gr73fwebhteaqf8g3o2tsnp16" />

  <link rel="stylesheet" href="../assets/css/style.css?v=9999">
  <style>
    .upload-status { margin-top: 8px; font-size: 0.9rem; font-weight: bold; }
    .status-loading { color: #d97706; }
    .status-success { color: #16a34a; }
    .status-error { color: #dc2626; }
  </style>
</head>
<body>

<div class="page-content">
  <div class="container">
    <section class="hero">
      <div>
        <h2>Create Post</h2>
        <p class="muted">Add interactive text articles, feature cover images, or video loops.</p>
      </div>
    </section>

    <?php if ($err !== ''): ?>
        <p class="err" style="color: red; margin-bottom: 20px; font-weight: bold;"><?= e($err) ?></p>
    <?php endif; ?>

    <form method="post" action="" id="mainPostForm">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      
      <input type="hidden" name="async_media_url" id="async_media_url" value="">
      <input type="hidden" name="async_media_path" id="async_media_path" value="">
      <input type="hidden" name="async_media_type" id="async_media_type" value="">

      <div class="form-grid">
        <div class="field">
          <label for="category_id">Category</label>
          <select name="category_id" id="category_id" required>
            <option value="" disabled selected>Select a category...</option>
            <?php foreach ($cats as $c): ?>
              <option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field">
          <label for="title">Title</label>
          <input type="text" name="title" id="title" required placeholder="Enter post title...">
        </div>

        <div class="field" style="grid-column: 1 / -1;">
          <label for="body">Body Content</label>
          <textarea name="body" id="body" required rows="10" placeholder="Write your post details here..."></textarea>
        </div>
        
        <div class="field" style="grid-column: 1 / -1;">
          <label for="media_file">Post Media Attachment (Photo or Video)</label>
          <input type="file" id="media_file" accept="image/*,video/*">
          <div id="uploadIndicator" class="upload-status"></div>
          <p class="muted" style="margin:8px 0 0;">Supported: standard image sets or clip feeds (Max 25MB)</p>
        </div>
      </div>

      <div class="actions" style="margin-top: 20px;">
        <button type="submit" id="submitBtn">Publish</button>
        <a class="btn-outline" href="../dashboard.php" style="margin-left: 10px; text-decoration: none;">Back</a>
      </div>
    </form>
  </div>
</div>

<script>
document.getElementById('media_file').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (!file) return;

    const indicator = document.getElementById('uploadIndicator');
    const submitBtn = document.getElementById('submitBtn');
    
    const formData = new FormData();
    formData.append('media', file);

    indicator.textContent = "Processing and uploading asset file securely...";
    indicator.className = "upload-status status-loading";
    submitBtn.disabled = true;

    // Asynchronously dispatch binary chunks straight to the standalone processor route
    fetch('upload_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) throw new Error('File rejected. Check file formatting size guidelines.');
        return response.json();
    })
    .then(data => {
        if (data.success) {
            document.getElementById('async_media_url').value = data.url;
            document.getElementById('async_media_path').value = data.path || '';
            document.getElementById('async_media_type').value = data.type || '';
            
            indicator.textContent = "Media successfully attached and optimized!";
            indicator.className = "upload-status status-success";
        } else {
            throw new Error(data.message || 'File assembly pipeline failure.');
        }
    })
    .catch(err => {
        indicator.textContent = "Upload failed: " + err.message;
        indicator.className = "upload-status status-error";
        console.error(err);
    })
    .finally(() => {
        submitBtn.disabled = false;
    });
});
</script>

</body>
</html>