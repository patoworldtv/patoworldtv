<?php
declare(strict_types=1);

require __DIR__ . '/../../app/bootstrap.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

try {
    // 1. Authenticate user access privileges
    $admin = require_admin($pdo);
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new RuntimeException('Method not allowed.');
    }

    if (empty($_FILES['media']) || $_FILES['media']['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('No file upload payload detected or error code present.');
    }

    $file = $_FILES['media'];
    $tmpName = (string)$file['tmp_name'];

    // 2. Fallback validation check for file signatures
    $mime = mime_content_type($tmpName) ?: '';
    
    // Explicitly handle standard images and videos if config values are unmapped
    $allowedImages = $CONFIG['app']['allowed_image_types'] ?? ['image/jpeg', 'image/png', 'image/webp'];
    $allowedVideos = $CONFIG['app']['allowed_video_types'] ?? ['video/mp4', 'video/webm', 'video/quicktime'];

    $isImage = in_array($mime, $allowedImages, true);
    $isVideo = in_array($mime, $allowedVideos, true);

    if (!$isImage && !$isVideo) {
        throw new RuntimeException('Invalid file type format. System requires standard image or video loops.');
    }

    // Determine correct extension mapping
    $ext = match ($mime) {
        'image/jpeg'      => 'jpg',
        'image/png'       => 'png',
        'image/webp'      => 'webp',
        'video/mp4'       => 'mp4',
        'video/webm'      => 'webm',
        'video/quicktime' => 'mov',
        default           => 'jpg',
    };

    // 3. Resolve absolute environment upload path dynamically
    // Bypasses hardcoded system path errors in app/config.php
    $baseUploadDir = __DIR__ . '/../img/uploads/posts';
    if (!is_dir($baseUploadDir)) {
        mkdir($baseUploadDir, 0755, true);
    }

    // 4. Sanitize file naming structures completely (removes spacing and quotes)
    $cleanFilename = bin2hex(random_bytes(16)) . '.' . $ext;
    $destinationPath = $baseUploadDir . DIRECTORY_SEPARATOR . $cleanFilename;

    if (!move_uploaded_file($tmpName, $destinationPath)) {
        throw new RuntimeException('Server permission restriction: Cannot commit file storage write operation.');
    }

    // 5. Build safe public URL route parameter references
    $publicUrl = '/img/uploads/posts/' . $cleanFilename;
    $cleanType = $isVideo ? 'video' : 'image';

    echo json_encode([
        'success' => true,
        'url'     => $publicUrl,
        'path'    => $destinationPath,
        'type'    => $cleanType
    ]);

} catch (Throwable $t) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $t->getMessage()
    ]);
}
exit;