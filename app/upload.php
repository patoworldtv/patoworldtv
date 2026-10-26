<?php
declare(strict_types=1);

/**
 * Upload profile image (jpg/png/webp)
 * Returns PUBLIC URL string or null if no file uploaded.
 */
function handle_profile_upload(array $CONFIG, string $field = 'photo'): ?string
{
    if (empty($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    $f = $_FILES[$field];

    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload failed');
    }

    $maxSize = (int)($CONFIG['app']['max_upload_size'] ?? 25 * 1024 * 1024);
    if ((int)($f['size'] ?? 0) > $maxSize) {
        throw new RuntimeException('File too large (max 25MB)');
    }

    $tmp = (string)($f['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        throw new RuntimeException('Invalid upload');
    }

    $mime = mime_content_type($tmp) ?: '';
    $allowed = $CONFIG['app']['allowed_image_types'] ?? ['image/jpeg', 'image/png', 'image/webp'];

    if (!in_array($mime, $allowed, true)) {
        throw new RuntimeException('Invalid image type');
    }

    $ext = match ($mime) {
        'image/png'  => 'png',
        'image/webp' => 'webp',
        default      => 'jpg',
    };

    // Hostinger-safe upload directory
    $uploadDir = rtrim((string)$CONFIG['app']['upload_dir'], '/\\');
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $name = bin2hex(random_bytes(16)) . '.' . $ext;
    $dest = $uploadDir . DIRECTORY_SEPARATOR . $name;

    if (!move_uploaded_file($tmp, $dest)) {
        throw new RuntimeException('Cannot move uploaded file');
    }

    $uploadUrl = rtrim((string)$CONFIG['app']['upload_url'], '/');
    return $uploadUrl . '/' . $name;
}



/**
 * Upload post media (image OR video)
 * Returns:
 *   [
 *     'url'  => '/img/uploads/posts/xxxx.jpg',
 *     'path' => '/home/.../public_html/img/uploads/posts/xxxx.jpg',
 *     'type' => 'image'|'video'
 *   ]
 * or null if no file uploaded.
 */
function handle_post_media_upload(array $CONFIG, string $field = 'media'): ?array
{
    if (empty($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    $f = $_FILES[$field];

    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload failed');
    }

    // Allow up to 25MB
    $max = (int)($CONFIG['app']['max_upload_size'] ?? (25 * 1024 * 1024));
    if ((int)($f['size'] ?? 0) > $max) {
        throw new RuntimeException('File too large (max 25MB)');
    }

    $tmp = (string)($f['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        throw new RuntimeException('Invalid upload');
    }

    $mime = mime_content_type($tmp) ?: '';

    $allowedImages = $CONFIG['app']['allowed_image_types'] ?? ['image/jpeg', 'image/png', 'image/webp'];
    $allowedVideos = $CONFIG['app']['allowed_video_types'] ?? ['video/mp4', 'video/webm', 'video/quicktime'];

    $isImage = in_array($mime, $allowedImages, true);
    $isVideo = in_array($mime, $allowedVideos, true);

    if (!$isImage && !$isVideo) {
        throw new RuntimeException('Invalid file type. Upload image or video.');
    }

    $ext = match ($mime) {
        'image/jpeg'      => 'jpg',
        'image/png'       => 'png',
        'image/webp'      => 'webp',
        'video/mp4'       => 'mp4',
        'video/webm'      => 'webm',
        'video/quicktime' => 'mov',
        default           => 'bin',
    };

    // Upload directory: /public_html/img/uploads/posts
    $baseDir  = rtrim((string)$CONFIG['app']['upload_dir'], '/\\');
    $postsDir = $baseDir . DIRECTORY_SEPARATOR . 'posts';

    if (!is_dir($postsDir)) {
        mkdir($postsDir, 0755, true);
    }

    $name = bin2hex(random_bytes(16)) . '.' . $ext;
    $dest = $postsDir . DIRECTORY_SEPARATOR . $name;

    if (!move_uploaded_file($tmp, $dest)) {
        throw new RuntimeException('Cannot move uploaded file');
    }

    // Public URL
    $baseUrl = rtrim((string)$CONFIG['app']['upload_url'], '/');
    $url = $baseUrl . '/posts/' . $name;

    return [
        'url'  => $url,
        'path' => $dest,
        'type' => $isVideo ? 'video' : 'image',
    ];
}